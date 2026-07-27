<?php
/**
 * Live Pace Map & Scenario Alerts + Speed Ratings (FSr) integration.
 *
 * Combines the horse's Fhorsite speed rating (FSr) and Last Time Out speed
 * rating (SR_LTO) with a derived pace projection to build, per race:
 *
 *   1. A Pace Mapping Score (PMS) and four pace zones
 *        Zone 1 Pace Setters  → Zone 4 Rear Chasers
 *   2. A speed-weighting layer (FSr + SR_LTO top-3 / bottom-3 rankings)
 *   3. Automated "Pace Scenario" alerts:
 *        🚀 Dangerous Lone Leader   (Golden Bet setup)
 *        ⚠️  Vulnerable Leader
 *        🎯 Swooper Alert
 *   4. A horizontal zone grid dashboard showing each runner's FSr.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * PMS priority:
 *   1. Prefer career running-style rates from historic in-race comments
 *      (net_leader_score via bricks_running_style_*), when available.
 *   2. Fall back to a derived proxy (draw / bias / fitness / form) for
 *      horses with no qualifying comment history.
 * Style and proxy scores are deliberately independent of the FSr / SR_LTO
 * *speed* axis so the pace-vs-speed cross-reference stays meaningful.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Usage:  [pace_map race_id="12345"]
 *         (race_id also resolved from the `race_id` query var or a /race/{id} URL)
 */

if (!defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------------------
// Data layer
// -----------------------------------------------------------------------------

if (!function_exists('bricks_pace_map_resolve_race')) {
    /**
     * Resolve a race to its correct table set (today's beta vs. advance/tomorrow),
     * mirroring the race-detail resolver in functions.php.
     *
     * @return array{race:object, speed_table:string, runners_table:string}|null
     */
    function bricks_pace_map_resolve_race($race_id) {
        global $wpdb;

        $race_id = intval($race_id);
        if ($race_id <= 0) {
            return null;
        }

        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $races_table   = 'advance_daily_races_beta';
        $runners_table = 'advance_daily_runners_beta';
        $speed_table   = 'speed&performance_table';

        $race = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$races_table` WHERE race_id = %d",
            $race_id
        ));

        if (!$race) {
            $races_table   = 'advance_daily_races';
            $runners_table = 'advance_daily_runners';
            $speed_table   = 'adv_speed&performance_table';
            $race = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM `$races_table` WHERE race_id = %d",
                $race_id
            ));
        } elseif (isset($race->meeting_date) && $race->meeting_date === $tomorrow) {
            $races_table   = 'advance_daily_races';
            $runners_table = 'advance_daily_runners';
            $speed_table   = 'adv_speed&performance_table';
            $advance_race = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM `$races_table` WHERE race_id = %d",
                $race_id
            ));
            if ($advance_race) {
                $race = $advance_race;
            }
        }

        if (!$race) {
            return null;
        }

        return [
            'race'          => $race,
            'speed_table'   => $speed_table,
            'runners_table' => $runners_table,
        ];
    }
}

if (!function_exists('bricks_pace_map_get_runners')) {
    /**
     * Fetch active (non-NR) runners for a race with the fields the pace map needs.
     *
     * @return array<int, object>
     */
    function bricks_pace_map_get_runners($race_id, $speed_table) {
        global $wpdb;

        $race_id = intval($race_id);
        if ($race_id <= 0) {
            return [];
        }

        // The speed table name legitimately contains "&"; it is an internal
        // constant (never user input) so direct interpolation is safe here.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT `runner_id`, `name`, `cloth_number`, `stall_number`,
                    `fhorsite_rating`, `SR_LTO`, `form_figures`, `days_since_ran`,
                    `draw_bias_pct`, `forecast_price_decimal`,
                    `course`, `Time`, `Distance`, `race_title`, `race_type`
             FROM `$speed_table`
             WHERE `race_id` = %d",
            $race_id
        ));

        if (!is_array($rows) || empty($rows)) {
            return [];
        }

        // Exclude non-runners.
        $nr = $wpdb->get_col($wpdb->prepare(
            "SELECT runner_id FROM non_runners WHERE race_id = %d",
            $race_id
        ));
        $nr_lookup = [];
        if (is_array($nr)) {
            foreach ($nr as $runner_id) {
                $nr_lookup[(string) intval($runner_id)] = true;
            }
        }

        $runners = [];
        foreach ($rows as $row) {
            $rid = isset($row->runner_id) ? (string) intval($row->runner_id) : '';
            if ($rid !== '' && isset($nr_lookup[$rid])) {
                continue;
            }
            if (trim((string) ($row->name ?? '')) === '') {
                continue;
            }
            $runners[] = $row;
        }

        return $runners;
    }
}

// -----------------------------------------------------------------------------
// Derived pace projection (PMS)
// -----------------------------------------------------------------------------

if (!function_exists('bricks_pace_map_form_momentum')) {
    /**
     * Parse a form string into a 0..1 "front-of-field momentum" proxy.
     * Recent runs are weighted more heavily. Horses that habitually finish
     * near the front tend to race handily/prominently, so this feeds the pace
     * projection (NOT the raw speed axis).
     *
     * @return float 0..1, or 0.5 when form is unknown (e.g. a debutant).
     */
    function bricks_pace_map_form_momentum($form_figures) {
        $form = strtoupper(trim((string) $form_figures));
        if ($form === '') {
            return 0.5;
        }

        // Keep only finishing tokens, most-recent last.
        $tokens = preg_split('//u', $form, -1, PREG_SPLIT_NO_EMPTY);
        $scores = [];
        foreach ($tokens as $ch) {
            if ($ch === '-' || $ch === '/') {
                continue; // season separators
            }
            if (ctype_digit($ch)) {
                $pos = intval($ch);
                if ($pos === 0) {
                    $scores[] = 0.15; // "0" = finished outside the top 9
                } elseif ($pos === 1) {
                    $scores[] = 1.0;
                } elseif ($pos === 2) {
                    $scores[] = 0.85;
                } elseif ($pos === 3) {
                    $scores[] = 0.70;
                } elseif ($pos === 4) {
                    $scores[] = 0.55;
                } elseif ($pos === 5) {
                    $scores[] = 0.45;
                } else {
                    $scores[] = 0.30;
                }
            } else {
                // P (pulled up), F (fell), U, R, B, etc. — non-completions.
                $scores[] = 0.10;
            }
        }

        if (empty($scores)) {
            return 0.5;
        }

        // Weight recent runs more (last token = highest weight).
        $scores = array_slice($scores, -6);
        $total = 0.0;
        $weight_sum = 0.0;
        $n = count($scores);
        foreach ($scores as $i => $s) {
            $w = $i + 1; // oldest = 1 ... newest = n
            $total += $s * $w;
            $weight_sum += $w;
        }

        return $weight_sum > 0 ? ($total / $weight_sum) : 0.5;
    }
}

if (!function_exists('bricks_pace_map_compute_proxy_pms')) {
    /**
     * Derived fallback PMS (0..100) when comment-based style rates are unavailable.
     *
     * @param array<int,object> $runners
     * @return array<int,object>
     */
    function bricks_pace_map_compute_proxy_pms(array $runners) {
        if (empty($runners)) {
            return $runners;
        }

        $weights = apply_filters('pace_map_pms_weights', [
            'draw'    => 0.25,
            'bias'    => 0.20,
            'fitness' => 0.15,
            'form'    => 0.40,
        ]);
        $w_sum = array_sum($weights);
        if ($w_sum <= 0) {
            $weights = ['draw' => 0.25, 'bias' => 0.20, 'fitness' => 0.15, 'form' => 0.40];
            $w_sum = 1.0;
        }

        $low_draw_is_forward = (bool) apply_filters('pace_map_low_draw_is_forward', true);

        $stalls = [];
        $biases = [];
        foreach ($runners as $r) {
            $stall = intval($r->stall_number ?? 0);
            if ($stall > 0) {
                $stalls[] = $stall;
            }
            if (isset($r->draw_bias_pct) && $r->draw_bias_pct !== null && $r->draw_bias_pct !== '') {
                $biases[] = floatval($r->draw_bias_pct);
            }
        }
        $stall_min = !empty($stalls) ? min($stalls) : 0;
        $stall_max = !empty($stalls) ? max($stalls) : 0;
        $bias_min  = !empty($biases) ? min($biases) : 0.0;
        $bias_max  = !empty($biases) ? max($biases) : 0.0;

        foreach ($runners as $r) {
            $stall = intval($r->stall_number ?? 0);
            if ($stall > 0 && $stall_max > $stall_min) {
                $norm = ($stall - $stall_min) / ($stall_max - $stall_min);
                $draw_c = $low_draw_is_forward ? (1.0 - $norm) : $norm;
            } else {
                $draw_c = 0.5;
            }

            if (isset($r->draw_bias_pct) && $r->draw_bias_pct !== null && $r->draw_bias_pct !== '' && $bias_max > $bias_min) {
                $bias_c = (floatval($r->draw_bias_pct) - $bias_min) / ($bias_max - $bias_min);
            } else {
                $bias_c = 0.5;
            }

            if (isset($r->days_since_ran) && $r->days_since_ran !== null && $r->days_since_ran !== '') {
                $days = max(0, intval($r->days_since_ran));
                $fitness_c = max(0.1, min(1.0, 1.0 - ($days / 120.0)));
            } else {
                $fitness_c = 0.5;
            }

            $form_c = bricks_pace_map_form_momentum($r->form_figures ?? '');

            $raw = (
                $weights['draw']    * $draw_c +
                $weights['bias']    * $bias_c +
                $weights['fitness'] * $fitness_c +
                $weights['form']    * $form_c
            ) / $w_sum;

            $r->pms_proxy = (int) round(max(0.0, min(1.0, $raw)) * 100);
            $r->pms_parts = [
                'draw'    => round($draw_c, 3),
                'bias'    => round($bias_c, 3),
                'fitness' => round($fitness_c, 3),
                'form'    => round($form_c, 3),
            ];
        }

        return $runners;
    }
}

if (!function_exists('bricks_pace_map_style_score_to_pms')) {
    /**
     * Map net_leader_score (typically ~[-3, 2]) onto a 0..100 PMS scale.
     */
    function bricks_pace_map_style_score_to_pms($net_leader_score) {
        $score = floatval($net_leader_score);
        // Theoretical bounds: rates sum → [-3, 2]; pad slightly for safety.
        $normalized = ($score + 3.0) / 5.0;
        return (int) round(max(0.0, min(1.0, $normalized)) * 100);
    }
}

if (!function_exists('bricks_pace_map_compute_pms')) {
    /**
     * Compute PMS (0..100) for every runner.
     *
     * Prefer comment-derived net_leader_score when career runs exist; otherwise
     * use the draw/form proxy. Attaches ->pms, ->pms_source, ->pms_parts.
     *
     * @param array<int,object> $runners
     * @return array<int,object>
     */
    function bricks_pace_map_compute_pms(array $runners) {
        if (empty($runners)) {
            return $runners;
        }

        if (function_exists('bricks_running_style_attach_to_runners')) {
            $runners = bricks_running_style_attach_to_runners($runners);
        }

        $runners = bricks_pace_map_compute_proxy_pms($runners);

        $style_weight = (float) apply_filters('pace_map_style_weight', 0.85);
        $style_weight = max(0.0, min(1.0, $style_weight));

        foreach ($runners as $r) {
            $proxy = intval($r->pms_proxy ?? 50);

            if (!empty($r->style_available) && $r->style_net_leader_score !== null) {
                $style_pms = bricks_pace_map_style_score_to_pms($r->style_net_leader_score);
                $r->pms_style = $style_pms;
                $r->pms = (int) round(($style_weight * $style_pms) + ((1.0 - $style_weight) * $proxy));
                $r->pms_source = 'style';
            } else {
                $r->pms_style = null;
                $r->pms = $proxy;
                $r->pms_source = 'proxy';
            }
        }

        return $runners;
    }
}

// -----------------------------------------------------------------------------
// Speed-weighting helpers (Step 2: FSr / SR_LTO baseline power metrics)
// -----------------------------------------------------------------------------

if (!function_exists('bricks_pace_map_numeric_or_null')) {
    /**
     * Return a float value, or null when the field is genuinely missing.
     */
    function bricks_pace_map_numeric_or_null($value) {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return floatval($value);
    }
}

if (!function_exists('bricks_pace_map_rank_set')) {
    /**
     * Build "top N" and "bottom N" runner-key sets for a numeric metric across
     * the field. Ties at the cut-off value are all included.
     *
     * @param array<int,object> $runners
     * @param string $prop   runner property holding the metric
     * @param int    $n
     * @return array{top:array<string,bool>, bottom:array<string,bool>, max:float|null, min:float|null}
     */
    function bricks_pace_map_rank_set(array $runners, $prop, $n = 3) {
        $values = [];
        foreach ($runners as $r) {
            $val = bricks_pace_map_numeric_or_null($r->{$prop} ?? null);
            if ($val === null) {
                continue;
            }
            $values[] = $val;
        }

        $result = ['top' => [], 'bottom' => [], 'max' => null, 'min' => null];
        if (empty($values)) {
            return $result;
        }

        sort($values); // ascending
        $result['min'] = $values[0];
        $result['max'] = $values[count($values) - 1];

        $desc = $values;
        rsort($desc);
        $top_threshold    = $desc[min($n, count($desc)) - 1];
        $bottom_threshold = $values[min($n, count($values)) - 1];

        foreach ($runners as $r) {
            $val = bricks_pace_map_numeric_or_null($r->{$prop} ?? null);
            if ($val === null) {
                continue;
            }
            $key = bricks_pace_map_runner_key($r);
            if ($val >= $top_threshold) {
                $result['top'][$key] = true;
            }
            if ($val <= $bottom_threshold) {
                $result['bottom'][$key] = true;
            }
        }

        return $result;
    }
}

if (!function_exists('bricks_pace_map_runner_key')) {
    function bricks_pace_map_runner_key($runner) {
        $rid = isset($runner->runner_id) ? (string) intval($runner->runner_id) : '';
        if ($rid !== '' && $rid !== '0') {
            return 'rid_' . $rid;
        }
        return 'nm_' . strtolower(trim((string) ($runner->name ?? '')));
    }
}

// -----------------------------------------------------------------------------
// Zone assignment + scenario alerts (Step 3)
// -----------------------------------------------------------------------------

if (!function_exists('bricks_pace_map_compute')) {
    /**
     * Full computation: PMS, zones, speed rankings, contested-pace flag, alerts.
     *
     * @param array<int,object> $runners
     * @return array<string,mixed>
     */
    function bricks_pace_map_compute(array $runners) {
        $runners = bricks_pace_map_compute_pms($runners);

        // Speed axis rankings.
        $fsr = bricks_pace_map_rank_set($runners, 'fhorsite_rating', 3);
        $lto = bricks_pace_map_rank_set($runners, 'SR_LTO', 3);

        // Annotate each runner with its speed flags for the UI/alerts.
        foreach ($runners as $r) {
            $key = bricks_pace_map_runner_key($r);
            $fsr_val = bricks_pace_map_numeric_or_null($r->fhorsite_rating ?? null);
            $lto_val = bricks_pace_map_numeric_or_null($r->SR_LTO ?? null);

            $r->_key           = $key;
            $r->fsr_val        = $fsr_val;
            $r->lto_val        = $lto_val;
            $r->is_top3_fsr    = isset($fsr['top'][$key]);
            $r->is_top3_lto    = isset($lto['top'][$key]);
            $r->is_bottom3_lto = isset($lto['bottom'][$key]);
            $r->is_max_fsr     = ($fsr['max'] !== null && $fsr_val !== null && $fsr_val >= $fsr['max']);
            $r->is_max_lto     = ($lto['max'] !== null && $lto_val !== null && $lto_val >= $lto['max']);
        }

        // Sort by PMS desc for zone assignment (tie-break: draw prominence, name).
        usort($runners, function ($a, $b) {
            if ($a->pms !== $b->pms) {
                return $b->pms <=> $a->pms;
            }
            $ad = $a->pms_parts['draw'] ?? 0.5;
            $bd = $b->pms_parts['draw'] ?? 0.5;
            if ($ad !== $bd) {
                return $bd <=> $ad;
            }
            return strcasecmp((string) ($a->name ?? ''), (string) ($b->name ?? ''));
        });

        $contest_gap = (float) apply_filters('pace_map_contest_gap', 8);
        $contest_min = (int) apply_filters('pace_map_contest_min', 2);

        $zones = [1 => [], 2 => [], 3 => [], 4 => []];

        if (!empty($runners)) {
            $top_pms = $runners[0]->pms;

            // Zone 1 — Pace Setters: the leader plus anyone within contest_gap of it.
            $rest = [];
            foreach ($runners as $r) {
                if ($r->pms >= $top_pms - $contest_gap) {
                    $r->zone = 1;
                    $zones[1][] = $r;
                } else {
                    $rest[] = $r;
                }
            }

            // Remaining split into Zone 2 / 3 / 4 by thirds (lowest PMS = Zone 4).
            $r_count = count($rest);
            if ($r_count > 0) {
                $third = (int) ceil($r_count / 3);
                foreach ($rest as $i => $r) {
                    if ($i < $third) {
                        $r->zone = 2;
                    } elseif ($i < $third * 2) {
                        $r->zone = 3;
                    } else {
                        $r->zone = 4;
                    }
                    $zones[$r->zone][] = $r;
                }
            }
        }

        $contested = count($zones[1]) >= $contest_min;

        $alerts = bricks_pace_map_build_alerts($zones, $contested);

        return [
            'runners'   => $runners,
            'zones'     => $zones,
            'contested' => $contested,
            'fsr'       => $fsr,
            'lto'       => $lto,
            'alerts'    => $alerts,
        ];
    }
}

if (!function_exists('bricks_pace_map_build_alerts')) {
    /**
     * Build the three enhanced Pace Scenario alerts.
     *
     * @param array<int,array<int,object>> $zones
     * @param bool $contested
     * @return array<int,array{type:string,icon:string,title:string,tone:string,horses:array,note:string}>
     */
    function bricks_pace_map_build_alerts(array $zones, $contested) {
        $alerts = [];
        $zone1 = $zones[1] ?? [];
        $zone4 = $zones[4] ?? [];

        // 🚀 Dangerous Lone Leader (Golden Bet): exactly one pace setter whose
        // FSr OR SR_LTO ranks top-3 in the field.
        if (count($zone1) === 1) {
            $leader = $zone1[0];
            if (!empty($leader->is_top3_fsr) || !empty($leader->is_top3_lto)) {
                $alerts[] = [
                    'type'   => 'lone_leader',
                    'icon'   => '🚀',
                    'title'  => 'Dangerous Lone Leader',
                    'tone'   => 'golden',
                    'horses' => [$leader],
                    'note'   => 'Uncontested pace setter that also owns top-3 raw speed — a potential Golden Bet setup.',
                ];
            }
        }

        // ⚠️ Vulnerable Leader: a pace setter whose SR_LTO is bottom-3 in the field.
        $vulnerable = [];
        foreach ($zone1 as $leader) {
            if (!empty($leader->is_bottom3_lto)) {
                $vulnerable[] = $leader;
            }
        }
        if (!empty($vulnerable)) {
            $alerts[] = [
                'type'   => 'vulnerable_leader',
                'icon'   => '⚠️',
                'title'  => 'Vulnerable Leader',
                'tone'   => 'warning',
                'horses' => $vulnerable,
                'note'   => 'Projected to set the pace but carries bottom-3 raw speed — likely to be swallowed up late.',
            ];
        }

        // 🎯 Swooper Alert: contested pace AND a rear chaser owns the field's
        // highest FSr OR SR_LTO.
        if ($contested) {
            $swoopers = [];
            foreach ($zone4 as $closer) {
                if (!empty($closer->is_max_fsr) || !empty($closer->is_max_lto)) {
                    $swoopers[] = $closer;
                }
            }
            if (!empty($swoopers)) {
                $alerts[] = [
                    'type'   => 'swooper',
                    'icon'   => '🎯',
                    'title'  => 'Swooper Alert',
                    'tone'   => 'closer',
                    'horses' => $swoopers,
                    'note'   => 'Hot, contested pace up front with the fastest horse held up — a perfect setup for a high-speed closer.',
                ];
            }
        }

        return $alerts;
    }
}

// -----------------------------------------------------------------------------
// Rendering (Step 4: dashboard)
// -----------------------------------------------------------------------------

if (!function_exists('bricks_pace_map_zone_meta')) {
    function bricks_pace_map_zone_meta() {
        return [
            1 => ['label' => 'Zone 1 · Pace Setters', 'sub' => 'Front-runners', 'class' => 'zone-1'],
            2 => ['label' => 'Zone 2 · Prominent',    'sub' => 'Handy / stalkers', 'class' => 'zone-2'],
            3 => ['label' => 'Zone 3 · Midfield',     'sub' => 'Mid-division', 'class' => 'zone-3'],
            4 => ['label' => 'Zone 4 · Rear Chasers', 'sub' => 'Held-up closers', 'class' => 'zone-4'],
        ];
    }
}

if (!function_exists('bricks_pace_map_fsr_display')) {
    function bricks_pace_map_fsr_display($runner) {
        $val = $runner->fsr_val ?? null;
        return $val === null ? '—' : (string) (0 + $val);
    }
}

if (!function_exists('bricks_pace_map_render')) {
    /**
     * @param array<string,mixed> $data  output of bricks_pace_map_compute()
     * @param object $race
     */
    function bricks_pace_map_render(array $data, $race) {
        $zones = $data['zones'];
        $alerts = $data['alerts'];
        $contested = $data['contested'];
        $zone_meta = bricks_pace_map_zone_meta();

        // Header details (prefer the race row; fall back to speed-table fields).
        $sample = null;
        foreach ($zones as $z) {
            if (!empty($z)) { $sample = $z[0]; break; }
        }
        $course = $race->course ?? ($sample->course ?? '');
        $time   = $race->scheduled_time ?? ($sample->Time ?? '');
        $title  = $race->race_title ?? ($sample->race_title ?? '');
        $distance = $race->Distance ?? ($sample->Distance ?? '');

        ob_start();
        ?>
        <section class="pace-map" aria-label="Live pace map and scenario alerts">
            <header class="pace-map-head">
                <div class="pace-map-head-main">
                    <span class="pace-map-eyebrow">📊 Live Pace Map &amp; Scenario Alerts</span>
                    <h2 class="pace-map-title">
                        <?php echo esc_html(trim($course . ($time ? ' · ' . $time : ''))); ?>
                    </h2>
                    <?php if ($title !== '' || $distance !== ''): ?>
                        <p class="pace-map-sub">
                            <?php echo esc_html(trim($title . ($distance ? ' · ' . $distance : ''))); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="pace-map-flags">
                    <?php if ($contested): ?>
                        <span class="pace-map-flag pace-map-flag-hot">🔥 Contested Pace Warning</span>
                    <?php else: ?>
                        <span class="pace-map-flag pace-map-flag-calm">🟢 Uncontested Pace Projection</span>
                    <?php endif; ?>
                </div>
            </header>

            <?php if (!empty($alerts)): ?>
            <div class="pace-map-alerts" role="list">
                <?php foreach ($alerts as $alert): ?>
                    <div class="pace-map-alert pace-map-alert--<?php echo esc_attr($alert['tone']); ?>" role="listitem">
                        <div class="pace-map-alert-icon" aria-hidden="true"><?php echo esc_html($alert['icon']); ?></div>
                        <div class="pace-map-alert-body">
                            <div class="pace-map-alert-title"><?php echo esc_html($alert['title']); ?></div>
                            <div class="pace-map-alert-horses">
                                <?php
                                $names = [];
                                foreach ($alert['horses'] as $h) {
                                    $stall = intval($h->stall_number ?? 0);
                                    $names[] = esc_html($h->name ?? '') .
                                        ($stall > 0 ? ' <span class="pace-map-stall-inline">(Stall ' . intval($stall) . ')</span>' : '') .
                                        ' <span class="pace-map-fsr-inline">FSr: ' . esc_html(bricks_pace_map_fsr_display($h)) . '</span>';
                                }
                                echo implode(' · ', $names); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html above
                                ?>
                            </div>
                            <div class="pace-map-alert-note"><?php echo esc_html($alert['note']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p class="pace-map-noalert">No scenario alerts triggered for this race.</p>
            <?php endif; ?>

            <div class="pace-map-grid">
                <?php foreach ($zone_meta as $zone_num => $meta): ?>
                    <div class="pace-map-col <?php echo esc_attr($meta['class']); ?>">
                        <div class="pace-map-col-head">
                            <span class="pace-map-col-title"><?php echo esc_html($meta['label']); ?></span>
                            <span class="pace-map-col-sub"><?php echo esc_html($meta['sub']); ?></span>
                        </div>
                        <div class="pace-map-col-body">
                            <?php if (empty($zones[$zone_num])): ?>
                                <div class="pace-map-empty">—</div>
                            <?php else: ?>
                                <?php foreach ($zones[$zone_num] as $h): ?>
                                    <?php
                                    $stall = intval($h->stall_number ?? 0);
                                    $box_classes = ['pace-map-box'];
                                    if (!empty($h->is_top3_fsr) || !empty($h->is_top3_lto)) {
                                        $box_classes[] = 'is-fast';
                                    }
                                    if (!empty($h->is_bottom3_lto)) {
                                        $box_classes[] = 'is-slow';
                                    }
                                    ?>
                                    <div class="<?php echo esc_attr(implode(' ', $box_classes)); ?>">
                                        <div class="pace-map-box-name">
                                            <?php echo esc_html($h->name ?? ''); ?>
                                            <?php if (!empty($h->is_top3_fsr) || !empty($h->is_top3_lto)): ?>
                                                <span class="pace-map-tag pace-map-tag-fast" title="Top-3 speed in field">⚡</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="pace-map-box-meta">
                                            <?php if ($stall > 0): ?>
                                                <span class="pace-map-chip">Stall <?php echo intval($stall); ?></span>
                                            <?php endif; ?>
                                            <span class="pace-map-chip pace-map-chip-fsr">FSr <?php echo esc_html(bricks_pace_map_fsr_display($h)); ?></span>
                                            <?php if ($h->lto_val !== null): ?>
                                                <span class="pace-map-chip pace-map-chip-lto">LTO <?php echo esc_html((string) (0 + $h->lto_val)); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($h->style_available) && $h->style_net_leader_score !== null): ?>
                                                <span class="pace-map-chip pace-map-chip-net">Net <?php echo esc_html(number_format((float) $h->style_net_leader_score, 2)); ?></span>
                                            <?php endif; ?>
                                            <span class="pace-map-chip pace-map-chip-pms">PMS <?php echo intval($h->pms ?? 0); ?><?php echo !empty($h->pms_source) && $h->pms_source === 'proxy' ? '*' : ''; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="pace-map-legend">
                <span><span class="pace-map-dot pace-map-dot-fast"></span> Top-3 FSr / LTO speed</span>
                <span><span class="pace-map-dot pace-map-dot-slow"></span> Bottom-3 LTO speed</span>
                <span><strong>FSr</strong> = Fhorsite speed rating · <strong>LTO</strong> = last-time-out speed · <strong>Net</strong> = running-style net leader score · <strong>PMS</strong> = pace projection (* proxy fallback)</span>
            </div>

            <details class="pace-map-method">
                <summary>How the Pace Mapping Score (PMS) is derived</summary>
                <p>
                    PMS now prioritises <strong>historic running-style rates</strong>
                    parsed from in-race comments (via net leader score), then blends
                    in the original draw/form proxy as a fallback where style history
                    is missing. This keeps pace projection independent from the
                    FSr/LTO <em>speed</em> axis so pace-vs-speed cross-reference
                    remains meaningful. A trailing <strong>*</strong> on PMS means
                    the value came from proxy fallback only.
                </p>
            </details>
        </section>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('bricks_pace_map_styles')) {
    function bricks_pace_map_styles() {
        static $printed = false;
        if ($printed) {
            return '';
        }
        $printed = true;

        ob_start();
        ?>
        <style id="pace-map-styles">
        .pace-map{--pm-green:#16a34a;--pm-amber:#d97706;--pm-red:#dc2626;--pm-blue:#2563eb;margin:1.5rem 0;font-family:inherit;color:#0f172a}
        .pace-map-head{display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;justify-content:space-between;margin-bottom:1rem}
        .pace-map-eyebrow{display:inline-block;font-size:.75rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#64748b}
        .pace-map-title{margin:.25rem 0 0;font-size:clamp(1.25rem,2.5vw,1.6rem);line-height:1.2}
        .pace-map-sub{margin:.2rem 0 0;color:#475569;font-size:.95rem}
        .pace-map-flags{display:flex;gap:.5rem;flex-wrap:wrap}
        .pace-map-flag{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .7rem;border-radius:999px;font-size:.8rem;font-weight:700}
        .pace-map-flag-hot{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
        .pace-map-flag-calm{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0}
        .pace-map-alerts{display:grid;gap:.6rem;margin-bottom:1.25rem}
        .pace-map-alert{display:flex;gap:.85rem;padding:.85rem 1rem;border-radius:12px;border:1px solid #e2e8f0;background:#fff}
        .pace-map-alert-icon{font-size:1.5rem;line-height:1.4;flex-shrink:0}
        .pace-map-alert-title{font-weight:800;font-size:1rem}
        .pace-map-alert-horses{margin-top:.15rem;font-size:.95rem;color:#0f172a}
        .pace-map-alert-note{margin-top:.25rem;font-size:.85rem;color:#64748b}
        .pace-map-stall-inline{color:#64748b;font-weight:500}
        .pace-map-fsr-inline{font-weight:700;color:#15803d}
        .pace-map-alert--golden{background:linear-gradient(180deg,#fffbeb 0%,#fff 100%);border-color:#fcd34d}
        .pace-map-alert--warning{background:linear-gradient(180deg,#fffbeb 0%,#fff 100%);border-color:#fdba74}
        .pace-map-alert--closer{background:linear-gradient(180deg,#eff6ff 0%,#fff 100%);border-color:#93c5fd}
        .pace-map-noalert{margin:0 0 1.25rem;color:#64748b;font-size:.9rem;padding:.75rem 1rem;border:1px dashed #e2e8f0;border-radius:10px}
        .pace-map-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem}
        .pace-map-col{border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;overflow:hidden;display:flex;flex-direction:column}
        .pace-map-col-head{padding:.6rem .7rem;border-bottom:1px solid #e2e8f0}
        .pace-map-col-title{display:block;font-size:.82rem;font-weight:800;line-height:1.2}
        .pace-map-col-sub{display:block;font-size:.72rem;color:#64748b}
        .pace-map-col.zone-1 .pace-map-col-head{background:#fef2f2}
        .pace-map-col.zone-2 .pace-map-col-head{background:#fff7ed}
        .pace-map-col.zone-3 .pace-map-col-head{background:#f0f9ff}
        .pace-map-col.zone-4 .pace-map-col-head{background:#f0fdf4}
        .pace-map-col-body{padding:.55rem;display:flex;flex-direction:column;gap:.5rem;flex:1}
        .pace-map-box{background:#fff;border:1px solid #e2e8f0;border-radius:9px;padding:.5rem .6rem}
        .pace-map-box.is-fast{border-color:#16a34a;box-shadow:0 0 0 1px rgba(22,163,74,.25)}
        .pace-map-box.is-slow{border-color:#f59e0b}
        .pace-map-box-name{font-weight:700;font-size:.88rem;line-height:1.25;display:flex;align-items:center;gap:.3rem}
        .pace-map-tag{font-size:.8rem}
        .pace-map-box-meta{margin-top:.35rem;display:flex;flex-wrap:wrap;gap:.3rem}
        .pace-map-chip{font-size:.68rem;font-weight:700;padding:.15rem .4rem;border-radius:5px;background:#f1f5f9;color:#334155;white-space:nowrap}
        .pace-map-chip-fsr{background:#dcfce7;color:#166534}
        .pace-map-chip-lto{background:#e0f2fe;color:#075985}
        .pace-map-chip-net{background:#fff7ed;color:#9a3412}
        .pace-map-chip-pms{background:#ede9fe;color:#5b21b6}
        .pace-map-empty{color:#cbd5e1;text-align:center;padding:.5rem 0}
        .pace-map-legend{margin-top:.85rem;display:flex;flex-wrap:wrap;gap:.75rem 1.25rem;font-size:.75rem;color:#64748b;align-items:center}
        .pace-map-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:.25rem;vertical-align:middle}
        .pace-map-dot-fast{background:#16a34a}
        .pace-map-dot-slow{background:#f59e0b}
        .pace-map-method{margin-top:.85rem;font-size:.82rem;color:#475569}
        .pace-map-method summary{cursor:pointer;font-weight:700;color:#334155}
        .pace-map-method p{margin:.5rem 0 0;line-height:1.55}
        @media (max-width:900px){
            .pace-map-grid{grid-template-columns:repeat(2,1fr)}
        }
        @media (max-width:560px){
            .pace-map-grid{grid-template-columns:1fr}
        }
        </style>
        <?php
        return ob_get_clean();
    }
}

// -----------------------------------------------------------------------------
// Shortcode
// -----------------------------------------------------------------------------

if (!function_exists('bricks_pace_map_resolve_race_id')) {
    function bricks_pace_map_resolve_race_id($atts) {
        $race_id = 0;
        if (!empty($atts['race_id'])) {
            $race_id = intval($atts['race_id']);
        }
        if ($race_id <= 0) {
            $qv = get_query_var('race_id');
            if ($qv) {
                $race_id = intval($qv);
            }
        }
        if ($race_id <= 0 && isset($_GET['race_id'])) {
            $race_id = intval($_GET['race_id']);
        }
        if ($race_id <= 0 && !empty($_SERVER['REQUEST_URI'])) {
            if (preg_match('/race\/([A-Za-z0-9_-]+)/', (string) $_SERVER['REQUEST_URI'], $m)) {
                if (function_exists('bricks_decode_entity_id')) {
                    $race_id = intval(bricks_decode_entity_id($m[1], 'race'));
                } elseif (ctype_digit($m[1])) {
                    $race_id = intval($m[1]);
                }
            }
        }
        return $race_id;
    }
}

if (!function_exists('bricks_pace_map_shortcode')) {
    function bricks_pace_map_shortcode($atts = []) {
        $atts = shortcode_atts([
            'race_id' => '',
        ], $atts, 'pace_map');

        $race_id = bricks_pace_map_resolve_race_id($atts);
        if ($race_id <= 0) {
            return '<div class="pace-map-error" style="padding:1rem;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;color:#991b1b;">Pace map: a <code>race_id</code> is required.</div>';
        }

        $resolved = bricks_pace_map_resolve_race($race_id);
        if (!$resolved) {
            return '<div class="pace-map-error" style="padding:1rem;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;color:#991b1b;">Pace map: race not found.</div>';
        }

        $runners = bricks_pace_map_get_runners($race_id, $resolved['speed_table']);
        if (empty($runners)) {
            return '<div class="pace-map-error" style="padding:1rem;border:1px solid #e2e8f0;background:#f8fafc;border-radius:8px;color:#475569;">Pace map: no runner speed data available for this race yet.</div>';
        }

        $data = bricks_pace_map_compute($runners);

        return bricks_pace_map_styles() . bricks_pace_map_render($data, $resolved['race']);
    }
}
add_shortcode('pace_map', 'bricks_pace_map_shortcode');

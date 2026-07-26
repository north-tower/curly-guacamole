<?php
/**
 * Horse Running Style Classification Engine & Competitors Pace Card.
 *
 * Parses historic in-race comments into career running-style rates:
 *   leader, closeup, chaser, heldup, midi, lagger
 * plus net_leader_score = (leader + closeup) - (heldup + midi + lagger).
 *
 * Shortcode: [competitors_pace_card race_id="12345"]
 * (race_id also resolved from the race detail URL / query var)
 */

if (!defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------------------
// Keyword dictionary (case-insensitive; longer phrases matched first)
// -----------------------------------------------------------------------------

if (!function_exists('bricks_running_style_keywords')) {
    /**
     * @return array<string, string[]> category => keyword phrases
     */
    function bricks_running_style_keywords() {
        return (array) apply_filters('running_style_keywords', [
            'leader' => [
                'made all',
                'set pace',
                'made running',
                'led',
                'ld',
            ],
            'closeup' => [
                'tracked leader',
                'pressed leader',
                'close up',
                'prominent',
                'prom',
            ],
            'chaser' => [
                'chased leaders',
                'chased leader',
                'tracked leaders',
                'chsd lds',
            ],
            'heldup' => [
                'held up in touch',
                'settled midfield',
                'held up',
                'hld up',
            ],
            'midi' => [
                'mid-division',
                'raced midfield',
                'in mid-pack',
                'mid-div',
                'midfield',
            ],
            'lagger' => [
                'held up in rear',
                'always towards rear',
                'slowly away',
                'slwly awy',
                'in rear',
                'detached',
                'dwelt',
            ],
        ]);
    }
}

if (!function_exists('bricks_running_style_skip_patterns')) {
    /**
     * Comments matching these (case-insensitive) are excluded from career runs.
     *
     * @return string[]
     */
    function bricks_running_style_skip_patterns() {
        return (array) apply_filters('running_style_skip_patterns', [
            'withdrawn',
            'non-runner',
            'non runner',
            'nonrunner',
        ]);
    }
}

// -----------------------------------------------------------------------------
// Classification
// -----------------------------------------------------------------------------

if (!function_exists('bricks_running_style_normalize_comment')) {
    function bricks_running_style_normalize_comment($comment) {
        $text = strtolower(trim((string) $comment));
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return $text;
    }
}

if (!function_exists('bricks_running_style_is_skipped')) {
    function bricks_running_style_is_skipped($comment) {
        $text = bricks_running_style_normalize_comment($comment);
        if ($text === '') {
            return true;
        }
        foreach (bricks_running_style_skip_patterns() as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern !== '' && strpos($text, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('bricks_running_style_keyword_matches')) {
    /**
     * Word-boundary match for short tokens; substring match for multi-word phrases.
     */
    function bricks_running_style_keyword_matches($haystack, $keyword) {
        $keyword = strtolower(trim((string) $keyword));
        if ($keyword === '' || $haystack === '') {
            return false;
        }

        // Short abbreviation tokens need word boundaries (e.g. "ld", "prom").
        if (strpos($keyword, ' ') === false && strlen($keyword) <= 4) {
            return (bool) preg_match(
                '/\b' . preg_quote($keyword, '/') . '\b/u',
                $haystack
            );
        }

        return strpos($haystack, $keyword) !== false;
    }
}

if (!function_exists('bricks_running_style_classify_comment')) {
    /**
     * Classify a single in-race comment.
     *
     * @return array<string,bool>|null  hit map, or null if the row is skipped
     */
    function bricks_running_style_classify_comment($comment) {
        if (bricks_running_style_is_skipped($comment)) {
            return null;
        }

        $text = bricks_running_style_normalize_comment($comment);
        $keywords = bricks_running_style_keywords();

        // Flatten + sort longest-first so "held up in rear" claims before "held up".
        $flat = [];
        foreach ($keywords as $category => $phrases) {
            foreach ((array) $phrases as $phrase) {
                $phrase = strtolower(trim((string) $phrase));
                if ($phrase === '') {
                    continue;
                }
                $flat[] = [$category, $phrase, strlen($phrase)];
            }
        }
        usort($flat, function ($a, $b) {
            return $b[2] <=> $a[2];
        });

        $hits = [];
        foreach ($flat as [$category, $phrase]) {
            if (bricks_running_style_keyword_matches($text, $phrase)) {
                $hits[$category] = true;
                // Remove matched phrase so shorter overlapping keywords don't also fire.
                if (strpos($phrase, ' ') !== false || strlen($phrase) > 4) {
                    $text = str_ireplace($phrase, ' ', $text);
                } else {
                    $text = preg_replace(
                        '/\b' . preg_quote($phrase, '/') . '\b/ui',
                        ' ',
                        $text
                    );
                }
                $text = preg_replace('/\s+/', ' ', trim($text));
            }
        }

        return $hits;
    }
}

if (!function_exists('bricks_running_style_empty_counts')) {
    function bricks_running_style_empty_counts() {
        return [
            'runs'    => 0,
            'leader'  => 0,
            'closeup' => 0,
            'chaser'  => 0,
            'heldup'  => 0,
            'midi'    => 0,
            'lagger'  => 0,
        ];
    }
}

if (!function_exists('bricks_running_style_aggregate_comments')) {
    /**
     * Aggregate raw category counts from a list of comments.
     *
     * @param string[] $comments
     * @return array<string,int>
     */
    function bricks_running_style_aggregate_comments(array $comments) {
        $counts = bricks_running_style_empty_counts();
        foreach ($comments as $comment) {
            $hits = bricks_running_style_classify_comment($comment);
            if ($hits === null) {
                continue;
            }
            $counts['runs']++;
            foreach (['leader', 'closeup', 'chaser', 'heldup', 'midi', 'lagger'] as $cat) {
                if (!empty($hits[$cat])) {
                    $counts[$cat]++;
                }
            }
        }
        return $counts;
    }
}

if (!function_exists('bricks_running_style_rates_from_counts')) {
    /**
     * Convert raw counts into career rates + net_leader_score.
     *
     * @param array<string,int> $counts
     * @return array{
     *   runs:int,
     *   leader:float|null, closeup:float|null, chaser:float|null,
     *   heldup:float|null, midi:float|null, lagger:float|null,
     *   net_leader_score:float|null,
     *   available:bool
     * }
     */
    function bricks_running_style_rates_from_counts(array $counts) {
        $runs = intval($counts['runs'] ?? 0);
        $cats = ['leader', 'closeup', 'chaser', 'heldup', 'midi', 'lagger'];

        if ($runs <= 0) {
            $out = ['runs' => 0, 'net_leader_score' => null, 'available' => false];
            foreach ($cats as $cat) {
                $out[$cat] = null;
            }
            return $out;
        }

        $rates = ['runs' => $runs, 'available' => true];
        foreach ($cats as $cat) {
            $rates[$cat] = round(intval($counts[$cat] ?? 0) / $runs, 2);
        }
        $rates['net_leader_score'] = round(
            ($rates['leader'] + $rates['closeup'])
            - ($rates['heldup'] + $rates['midi'] + $rates['lagger']),
            2
        );

        return $rates;
    }
}

// -----------------------------------------------------------------------------
// Historic comment fetch (career)
// -----------------------------------------------------------------------------

if (!function_exists('bricks_running_style_fetch_comments_by_runners')) {
    /**
     * Fetch historic in-race comments for a set of runner_ids.
     *
     * Prefers historic_runners_beta; falls back to daily_comment_history.
     *
     * @param int[] $runner_ids
     * @return array<int, array{comments:string[], avg_btn:float|null}>
     */
    function bricks_running_style_fetch_comments_by_runners(array $runner_ids) {
        global $wpdb;

        $runner_ids = array_values(array_unique(array_filter(array_map('intval', $runner_ids))));
        if (empty($runner_ids)) {
            return [];
        }

        sort($runner_ids);
        $cache_key = 'bricks_runstyle_cmt_' . md5(implode(',', $runner_ids));
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $placeholders = implode(',', array_fill(0, count($runner_ids), '%d'));
        $out = [];
        foreach ($runner_ids as $rid) {
            $out[$rid] = ['comments' => [], 'avg_btn' => null];
        }

        $table = 'historic_runners_beta';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists === $table) {
            $sql = "SELECT runner_id, in_race_comment, distance_beaten
                    FROM `$table`
                    WHERE runner_id IN ($placeholders)
                      AND in_race_comment IS NOT NULL
                      AND in_race_comment != ''";
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$runner_ids));
        } else {
            $table = 'daily_comment_history';
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                set_transient($cache_key, $out, 15 * MINUTE_IN_SECONDS);
                return $out;
            }
            $sql = "SELECT runner_id, in_race_comment, distance_beaten
                    FROM `$table`
                    WHERE runner_id IN ($placeholders)
                      AND in_race_comment IS NOT NULL
                      AND in_race_comment != ''";
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$runner_ids));
        }

        $btn_sums = [];
        $btn_ns = [];
        foreach ((array) $rows as $row) {
            $rid = intval($row->runner_id ?? 0);
            if ($rid <= 0 || !isset($out[$rid])) {
                continue;
            }
            $comment = (string) ($row->in_race_comment ?? '');
            if ($comment === '') {
                continue;
            }
            $out[$rid]['comments'][] = $comment;

            if (isset($row->distance_beaten) && $row->distance_beaten !== null && $row->distance_beaten !== '' && is_numeric($row->distance_beaten)) {
                $btn_sums[$rid] = ($btn_sums[$rid] ?? 0.0) + floatval($row->distance_beaten);
                $btn_ns[$rid] = ($btn_ns[$rid] ?? 0) + 1;
            }
        }

        foreach ($btn_ns as $rid => $n) {
            if ($n > 0) {
                $out[$rid]['avg_btn'] = round($btn_sums[$rid] / $n, 2);
            }
        }

        set_transient($cache_key, $out, 30 * MINUTE_IN_SECONDS);
        return $out;
    }
}

if (!function_exists('bricks_running_style_profiles_for_runners')) {
    /**
     * Build style rate profiles keyed by runner_id.
     *
     * @param int[] $runner_ids
     * @return array<int, array>
     */
    function bricks_running_style_profiles_for_runners(array $runner_ids) {
        $by_runner = bricks_running_style_fetch_comments_by_runners($runner_ids);
        $profiles = [];

        foreach ($by_runner as $rid => $payload) {
            $counts = bricks_running_style_aggregate_comments($payload['comments']);
            $rates = bricks_running_style_rates_from_counts($counts);
            $rates['avg_btn'] = $payload['avg_btn'];
            $rates['counts'] = $counts;
            $profiles[intval($rid)] = $rates;
        }

        return $profiles;
    }
}

if (!function_exists('bricks_running_style_attach_to_runners')) {
    /**
     * Attach style_* properties onto runner objects (mutates in place).
     *
     * @param array<int,object> $runners
     * @return array<int,object>
     */
    function bricks_running_style_attach_to_runners(array $runners) {
        $ids = [];
        foreach ($runners as $r) {
            $rid = intval($r->runner_id ?? 0);
            if ($rid > 0) {
                $ids[] = $rid;
            }
        }

        $profiles = bricks_running_style_profiles_for_runners($ids);

        foreach ($runners as $r) {
            $rid = intval($r->runner_id ?? 0);
            $profile = $profiles[$rid] ?? bricks_running_style_rates_from_counts(bricks_running_style_empty_counts());
            $r->style_runs = intval($profile['runs'] ?? 0);
            $r->style_leader = $profile['leader'];
            $r->style_closeup = $profile['closeup'];
            $r->style_chaser = $profile['chaser'];
            $r->style_heldup = $profile['heldup'];
            $r->style_midi = $profile['midi'];
            $r->style_lagger = $profile['lagger'];
            $r->style_net_leader_score = $profile['net_leader_score'];
            $r->style_available = !empty($profile['available']);
            $r->style_avg_btn = $profile['avg_btn'] ?? null;
        }

        return $runners;
    }
}

// -----------------------------------------------------------------------------
// Competitors Pace Card — data + render
// -----------------------------------------------------------------------------

if (!function_exists('bricks_competitors_pace_card_get_runners')) {
    /**
     * @return array<int,object>
     */
    function bricks_competitors_pace_card_get_runners($race_id) {
        if (!function_exists('bricks_pace_map_resolve_race') || !function_exists('bricks_pace_map_get_runners')) {
            return [];
        }

        $resolved = bricks_pace_map_resolve_race($race_id);
        if (!$resolved) {
            return [];
        }

        $runners = bricks_pace_map_get_runners($race_id, $resolved['speed_table']);
        if (empty($runners)) {
            return [];
        }

        // Pull gender / jockey / trainer / weight / age from speed table if present.
        // pace_map_get_runners already selects a subset; enrich if columns missing.
        global $wpdb;
        $speed_table = $resolved['speed_table'];
        $ids = array_map(function ($r) {
            return intval($r->runner_id ?? 0);
        }, $runners);
        $ids = array_values(array_filter($ids));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $extra = $wpdb->get_results($wpdb->prepare(
                "SELECT `runner_id`, `gender`, `age`, `jockey_name`, `trainer_name`,
                        `weight_pounds`, `forecast_price`, `forecast_price_decimal`,
                        `days_since_ran`, `cloth_number`, `stall_number`, `name`
                 FROM `$speed_table`
                 WHERE `race_id` = %d AND `runner_id` IN ($placeholders)",
                $race_id,
                ...$ids
            ));
            $lookup = [];
            foreach ((array) $extra as $row) {
                $lookup[intval($row->runner_id)] = $row;
            }
            foreach ($runners as $r) {
                $rid = intval($r->runner_id ?? 0);
                if (!isset($lookup[$rid])) {
                    continue;
                }
                $src = $lookup[$rid];
                foreach (['gender', 'age', 'jockey_name', 'trainer_name', 'weight_pounds', 'forecast_price', 'forecast_price_decimal', 'days_since_ran', 'cloth_number', 'stall_number', 'name'] as $field) {
                    if ((!isset($r->{$field}) || $r->{$field} === null || $r->{$field} === '') && isset($src->{$field})) {
                        $r->{$field} = $src->{$field};
                    }
                }
            }
        }

        bricks_running_style_attach_to_runners($runners);

        usort($runners, function ($a, $b) {
            return intval($a->cloth_number ?? 0) <=> intval($b->cloth_number ?? 0);
        });

        return $runners;
    }
}

if (!function_exists('bricks_running_style_format_rate')) {
    function bricks_running_style_format_rate($value, $runs) {
        if (intval($runs) <= 0 || $value === null) {
            return 'n/a';
        }
        return number_format((float) $value, 2, '.', '');
    }
}

if (!function_exists('bricks_running_style_rate_cell_class')) {
    function bricks_running_style_rate_cell_class($value, $runs) {
        if (intval($runs) <= 0 || $value === null) {
            return 'cpc-na';
        }
        if ((float) $value >= 0.40) {
            return 'cpc-hot';
        }
        return '';
    }
}

if (!function_exists('bricks_running_style_format_weight')) {
    function bricks_running_style_format_weight($weight_pounds) {
        if ($weight_pounds === null || $weight_pounds === '' || !is_numeric($weight_pounds)) {
            return '—';
        }
        $lbs = intval($weight_pounds);
        $st = intdiv($lbs, 14);
        $rem = $lbs % 14;
        return $st . '-' . $rem;
    }
}

if (!function_exists('bricks_competitors_pace_card_styles')) {
    function bricks_competitors_pace_card_styles() {
        static $printed = false;
        if ($printed) {
            return '';
        }
        $printed = true;

        return '<style id="competitors-pace-card-styles">
        .cpc{margin:1.5rem 0 2rem;color:#0f172a;font-family:inherit}
        .cpc-head{margin:0 0 .75rem}
        .cpc-eyebrow{display:inline-block;font-size:.75rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#64748b}
        .cpc-title{margin:.2rem 0 0;font-size:clamp(1.2rem,2.4vw,1.5rem);line-height:1.25}
        .cpc-sub{margin:.25rem 0 0;color:#64748b;font-size:.9rem}
        .cpc-wrap{overflow-x:auto;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
        .cpc-table{width:100%;border-collapse:collapse;font-size:.82rem;min-width:980px}
        .cpc-table th,.cpc-table td{padding:.55rem .5rem;border-bottom:1px solid #e2e8f0;text-align:center;white-space:nowrap}
        .cpc-table th{background:#0f172a;color:#fff;font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;position:sticky;top:0;z-index:1}
        .cpc-table td.cpc-name,.cpc-table th.cpc-name{text-align:left;font-weight:700}
        .cpc-table tbody tr:nth-child(even){background:#f8fafc}
        .cpc-table tbody tr:hover{background:#f1f5f9}
        .cpc-table td.cpc-hot{font-weight:800;color:#166534;background:#dcfce7}
        .cpc-table td.cpc-na{color:#94a3b8;font-style:italic}
        .cpc-table td.cpc-net{font-weight:700}
        .cpc-table td.cpc-net.cpc-hot{background:#fef9c3;color:#854d0e}
        .cpc-legend{margin:.65rem 0 0;font-size:.75rem;color:#64748b;display:flex;flex-wrap:wrap;gap:.5rem 1.1rem}
        .cpc-legend strong{color:#334155}
        .cpc-swatch{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:.3rem;vertical-align:middle;background:#dcfce7;border:1px solid #86efac}
        </style>';
    }
}

if (!function_exists('bricks_competitors_pace_card_render')) {
    /**
     * @param array<int,object> $runners
     * @param object|null $race
     */
    function bricks_competitors_pace_card_render(array $runners, $race = null) {
        $course = $race->course ?? ($runners[0]->course ?? '');
        $time = $race->scheduled_time ?? ($runners[0]->Time ?? '');
        $title = $race->race_title ?? ($runners[0]->race_title ?? '');

        $style_cols = ['leader', 'closeup', 'chaser', 'heldup', 'midi', 'lagger'];

        ob_start();
        ?>
        <section class="cpc" aria-label="Competitors pace card">
            <header class="cpc-head">
                <span class="cpc-eyebrow">Competitors Pace Card</span>
                <h2 class="cpc-title">Running Style Classification</h2>
                <p class="cpc-sub">
                    <?php echo esc_html(trim($course . ($time ? ' · ' . $time : '') . ($title ? ' · ' . $title : ''))); ?>
                    — career rates from historic in-race comments
                </p>
            </header>

            <div class="cpc-wrap">
                <table class="cpc-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th class="cpc-name">Name</th>
                            <th>Forecast</th>
                            <th>Av Btn</th>
                            <th>Gender</th>
                            <th>Days</th>
                            <th>Weight</th>
                            <th>Age</th>
                            <th>Jockey</th>
                            <th>Trainer</th>
                            <th>Runs</th>
                            <th>Leader</th>
                            <th>Closeup</th>
                            <th>Chaser</th>
                            <th>Heldup</th>
                            <th>Midi</th>
                            <th>Lagger</th>
                            <th>Net Leader</th>
                            <th>Stall</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($runners as $r): ?>
                            <?php
                            $runs = intval($r->style_runs ?? 0);
                            $forecast = trim((string) ($r->forecast_price ?? ''));
                            if ($forecast === '' && isset($r->forecast_price_decimal) && is_numeric($r->forecast_price_decimal)) {
                                $forecast = number_format((float) $r->forecast_price_decimal, 2);
                            }
                            $avg_btn = $r->style_avg_btn;
                            $net = $r->style_net_leader_score;
                            $net_class = 'cpc-net ' . bricks_running_style_rate_cell_class($net, $runs);
                            // Net leader can be negative; highlight when strongly positive (>= 0.40).
                            ?>
                            <tr>
                                <td><?php echo esc_html((string) ($r->cloth_number ?: '—')); ?></td>
                                <td class="cpc-name"><?php echo esc_html((string) ($r->name ?? '')); ?></td>
                                <td><?php echo esc_html($forecast !== '' ? $forecast : '—'); ?></td>
                                <td><?php echo $avg_btn !== null ? esc_html(number_format((float) $avg_btn, 2)) : '—'; ?></td>
                                <td><?php echo esc_html((string) ($r->gender ?: '—')); ?></td>
                                <td><?php echo isset($r->days_since_ran) && $r->days_since_ran !== '' && $r->days_since_ran !== null ? esc_html((string) intval($r->days_since_ran)) : '—'; ?></td>
                                <td><?php echo esc_html(bricks_running_style_format_weight($r->weight_pounds ?? null)); ?></td>
                                <td><?php echo esc_html((string) ($r->age ?: '—')); ?></td>
                                <td><?php echo esc_html((string) ($r->jockey_name ?: '—')); ?></td>
                                <td><?php echo esc_html((string) ($r->trainer_name ?: '—')); ?></td>
                                <td><?php echo $runs > 0 ? esc_html((string) $runs) : '<span class="cpc-na">n/a</span>'; ?></td>
                                <?php foreach ($style_cols as $col): ?>
                                    <?php
                                    $prop = 'style_' . $col;
                                    $val = $r->{$prop} ?? null;
                                    $cls = bricks_running_style_rate_cell_class($val, $runs);
                                    ?>
                                    <td class="<?php echo esc_attr($cls); ?>"><?php echo esc_html(bricks_running_style_format_rate($val, $runs)); ?></td>
                                <?php endforeach; ?>
                                <td class="<?php echo esc_attr(trim($net_class)); ?>"><?php echo esc_html(bricks_running_style_format_rate($net, $runs)); ?></td>
                                <td><?php echo intval($r->stall_number ?? 0) > 0 ? esc_html((string) intval($r->stall_number)) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="cpc-legend">
                <span><span class="cpc-swatch"></span> Highlighted cells are ≥ 0.40</span>
                <span><strong>Net Leader</strong> = (leader + closeup) − (heldup + midi + lagger)</span>
                <span>Withdrawn / non-runners excluded from career runs</span>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('bricks_competitors_pace_card_shortcode')) {
    function bricks_competitors_pace_card_shortcode($atts = []) {
        $atts = shortcode_atts([
            'race_id' => '',
        ], $atts, 'competitors_pace_card');

        $race_id = 0;
        if (function_exists('bricks_pace_map_resolve_race_id')) {
            $race_id = bricks_pace_map_resolve_race_id($atts);
        } else {
            $race_id = intval($atts['race_id'] ?: get_query_var('race_id'));
        }

        if ($race_id <= 0) {
            return '<div class="pace-map-error" style="padding:1rem;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;color:#991b1b;">Competitors Pace Card: a <code>race_id</code> is required.</div>';
        }

        $race = null;
        if (function_exists('bricks_pace_map_resolve_race')) {
            $resolved = bricks_pace_map_resolve_race($race_id);
            $race = $resolved['race'] ?? null;
        }

        $runners = bricks_competitors_pace_card_get_runners($race_id);
        if (empty($runners)) {
            return '<div class="pace-map-error" style="padding:1rem;border:1px solid #e2e8f0;background:#f8fafc;border-radius:8px;color:#475569;">Competitors Pace Card: no runners found for this race.</div>';
        }

        return bricks_competitors_pace_card_styles() . bricks_competitors_pace_card_render($runners, $race);
    }
}
add_shortcode('competitors_pace_card', 'bricks_competitors_pace_card_shortcode');

<?php
/**
 * Permanent Proven Winners case-study archive (/proven-winners/, alias /results/).
 *
 * Masonry grid of Points Engine win picks that won, with per-strategy ROI.
 * Bricks: [proven_winners_archive]
 */

if (!function_exists('bricks_proven_winners_url')) {
    function bricks_proven_winners_url() {
        return home_url('/proven-winners/');
    }
}

if (!function_exists('bricks_proven_winners_is_request')) {
    function bricks_proven_winners_is_request() {
        if (get_query_var('proven_winners_page')) {
            return true;
        }
        return (bool) preg_match('#/(?:proven-winners|results)(?:/|$)#i', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    }
}

if (!function_exists('bricks_proven_winners_parse_sp_decimal')) {
    function bricks_proven_winners_parse_sp_decimal($sp_raw) {
        if (function_exists('bricks_points_parse_decimal_odds')) {
            $d = bricks_points_parse_decimal_odds(null, (string) $sp_raw);
            if ($d !== null && floatval($d) > 1) {
                return floatval($d);
            }
        }
        $s = trim((string) $sp_raw);
        if ($s === '') {
            return null;
        }
        if (is_numeric($s) && floatval($s) > 1) {
            return floatval($s);
        }
        if (preg_match('#^(\d+)\s*/\s*(\d+)$#', $s, $m)) {
            $den = intval($m[2]);
            if ($den > 0) {
                return round((intval($m[1]) / $den) + 1, 2);
            }
        }
        return null;
    }
}

if (!function_exists('bricks_proven_winners_compute_single_race_roi')) {
    /**
     * Per-strategy profit (1pt units) for one race using published picks.
     *
     * @return array<string, array{profit:float, hit:bool, horse:string, sp:string}>|null
     */
    function bricks_proven_winners_compute_single_race_roi($race_id, $meeting_date) {
        if (
            !function_exists('bricks_points_backtest_fetch_rows')
            || !function_exists('bricks_points_backtest_score_race')
            || !function_exists('bricks_points_published_picks_get')
            || !function_exists('bricks_points_picks_from_published_snapshot')
        ) {
            return null;
        }

        $race_id = intval($race_id);
        if ($race_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $meeting_date)) {
            return null;
        }

        static $cache = [];
        $key = $race_id . '|' . $meeting_date;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $rows = bricks_points_backtest_fetch_rows($meeting_date, $meeting_date, '');
        $race_rows = [];
        foreach ((array) $rows as $row) {
            if (intval($row->race_id ?? 0) === $race_id) {
                $race_rows[] = $row;
            }
        }
        if (count($race_rows) < 2) {
            $cache[$key] = null;
            return null;
        }

        $snapshot = bricks_points_published_picks_get($race_id, $meeting_date);
        if (!$snapshot || empty($snapshot['win_horse'])) {
            $cache[$key] = null;
            return null;
        }

        $scored = bricks_points_backtest_score_race($race_rows);
        $published = bricks_points_picks_from_published_snapshot($snapshot, $scored);
        $picks = ['winner' => $published['winner'], 'place' => $published['place']];
        $ew_simple = $published['ew_simple'];
        $ew_edge = $published['ew_edge'];
        $place_terms = function_exists('bricks_points_place_terms_count')
            ? bricks_points_place_terms_count(count($race_rows))
            : 3;

        $pick_odds = function ($pick) {
            if (!$pick) {
                return null;
            }
            $settle = $pick['settlement_odds_decimal'] ?? null;
            if ($settle !== null && floatval($settle) > 1) {
                return floatval($settle);
            }
            $pre = $pick['odds_decimal'] ?? null;
            return ($pre !== null && floatval($pre) > 1) ? floatval($pre) : null;
        };

        $fmt_sp = function ($pick) use ($pick_odds) {
            if (!$pick) {
                return '';
            }
            if (!empty($pick['odds_fractional'])) {
                return (string) $pick['odds_fractional'];
            }
            $odds = $pick_odds($pick);
            return $odds !== null ? (string) $odds : '';
        };

        $out = [];

        $win = $picks['winner'] ?? null;
        $win_odds = $pick_odds($win);
        $win_hit = $win && function_exists('bricks_points_finish_is_win')
            && bricks_points_finish_is_win($win['finish_position'] ?? '');
        $out['win'] = [
            'horse' => $win['horse_name'] ?? '',
            'sp' => $fmt_sp($win),
            'profit' => ($win_hit && $win_odds !== null) ? round($win_odds - 1.0, 2) : -1.0,
            'hit' => (bool) $win_hit,
        ];

        $place_profit = 0.0;
        $place_hits = 0;
        $place_bets = 0;
        if (!empty($picks['place'])) {
            foreach (array_slice($picks['place'], 0, 3) as $pp) {
                $odds = $pick_odds($pp);
                if ($odds === null) {
                    continue;
                }
                $place_bets++;
                $placed = function_exists('bricks_points_finish_is_placed')
                    && bricks_points_finish_is_placed($pp['finish_position'] ?? '', $place_terms);
                if ($placed) {
                    $place_hits++;
                }
                $place_profit += $placed ? (($odds - 1.0) * 0.25) : -1.0;
            }
        }
        $out['place'] = [
            'horse' => isset($picks['place'][0]['horse_name']) ? $picks['place'][0]['horse_name'] : '',
            'sp' => isset($picks['place'][0]) ? $fmt_sp($picks['place'][0]) : '',
            'profit' => round($place_profit, 2),
            'hit' => $place_hits > 0,
            'bets' => $place_bets,
        ];

        $ew_calc = function ($pick) use ($pick_odds, $place_terms) {
            $odds = $pick_odds($pick);
            if (!$pick || $odds === null) {
                return ['horse' => '', 'sp' => '', 'profit' => 0.0, 'hit' => false];
            }
            $is_win = function_exists('bricks_points_finish_is_win')
                && bricks_points_finish_is_win($pick['finish_position'] ?? '');
            $placed = function_exists('bricks_points_finish_is_placed')
                && bricks_points_finish_is_placed($pick['finish_position'] ?? '', $place_terms);
            $profit = ($is_win ? ($odds - 1.0) : -1.0) + ($placed ? (($odds - 1.0) * 0.25) : -1.0);
            return [
                'horse' => $pick['horse_name'] ?? '',
                'sp' => !empty($pick['odds_fractional']) ? (string) $pick['odds_fractional'] : (string) $odds,
                'profit' => round($profit, 2),
                'hit' => $is_win || $placed,
            ];
        };

        $out['ew_simple'] = $ew_calc($ew_simple);
        $out['ew_edge'] = $ew_calc($ew_edge);

        $cache[$key] = $out;
        return $out;
    }
}

if (!function_exists('bricks_proven_winners_fetch_db_cases')) {
    /**
     * Published Points Engine win picks that actually won.
     *
     * @return array<int, array<string, mixed>>
     */
    function bricks_proven_winners_fetch_db_cases($limit = 60, $min_sp_decimal = 0.0) {
        global $wpdb;

        $limit = max(1, min(120, intval($limit)));
        $min_sp_decimal = floatval($min_sp_decimal);

        if (!function_exists('bricks_points_published_picks_table_name')) {
            return [];
        }

        $picks_table = bricks_points_published_picks_table_name();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $picks_table)) !== $picks_table) {
            return [];
        }

        $races_table = 'historic_races_beta';
        $runners_table = 'historic_runners_beta';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $races_table)) !== $races_table) {
            return [];
        }
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $runners_table)) !== $runners_table) {
            return [];
        }

        $runner_cols = $wpdb->get_col("SHOW COLUMNS FROM `$runners_table`");
        $name_col = in_array('name', $runner_cols, true) ? 'name' : 'horse_name';
        $sp_col = in_array('starting_price', $runner_cols, true) ? 'starting_price' : '';
        $race_cols = $wpdb->get_col("SHOW COLUMNS FROM `$races_table`");
        $title_col = in_array('race_title', $race_cols, true) ? 'race_title' : '';
        $time_col = in_array('scheduled_time', $race_cols, true) ? 'scheduled_time' : '';

        $winner_sql = "(
            ru.finish_position = 1 OR ru.finish_position = '1'
            OR CAST(ru.finish_position AS UNSIGNED) = 1
            OR LOWER(TRIM(ru.finish_position)) IN ('1st', 'first')
        )";

        $sp_select = $sp_col !== '' ? ", ru.`" . esc_sql($sp_col) . "` AS starting_price" : ", '' AS starting_price";
        $title_select = $title_col !== '' ? ", r.`" . esc_sql($title_col) . "` AS race_title" : ", '' AS race_title";
        $time_select = $time_col !== '' ? ", r.`" . esc_sql($time_col) . "` AS scheduled_time" : ", '' AS scheduled_time";

        $sql = "SELECT pp.meeting_date, pp.win_horse, r.race_id, r.course
                       $title_select
                       $time_select
                       $sp_select
                FROM `$picks_table` pp
                INNER JOIN `$races_table` r ON r.race_id = pp.race_id AND r.meeting_date = pp.meeting_date
                INNER JOIN `$runners_table` ru ON ru.race_id = pp.race_id
                WHERE $winner_sql
                  AND LOWER(TRIM(ru.`" . esc_sql($name_col) . "`)) = LOWER(TRIM(pp.win_horse))
                ORDER BY pp.meeting_date DESC, r.race_id DESC
                LIMIT %d";

        $wpdb->suppress_errors(true);
        $rows = (array) $wpdb->get_results($wpdb->prepare($sql, $limit * 3));
        $wpdb->suppress_errors(false);

        $cases = [];
        foreach ($rows as $row) {
            $sp_decimal = bricks_proven_winners_parse_sp_decimal($row->starting_price ?? '');
            if ($min_sp_decimal > 0 && ($sp_decimal === null || $sp_decimal < $min_sp_decimal)) {
                continue;
            }

            $race_id = intval($row->race_id ?? 0);
            $meeting_date = (string) ($row->meeting_date ?? '');
            if ($race_id <= 0 || $meeting_date === '') {
                continue;
            }

            $strategies = bricks_proven_winners_compute_single_race_roi($race_id, $meeting_date);
            $course = function_exists('bricks_track_format_display_name')
                ? bricks_track_format_display_name($row->course ?? '')
                : str_replace('_', ' ', (string) ($row->course ?? ''));

            $race_url = function_exists('bricks_race_url') ? bricks_race_url($race_id) : home_url('/race/' . $race_id . '/');

            $cases[] = [
                'id' => $race_id . '-' . $meeting_date,
                'race_id' => $race_id,
                'meeting_date' => $meeting_date,
                'horse' => (string) ($row->win_horse ?? ''),
                'sp' => (string) ($row->starting_price ?? ''),
                'sp_decimal' => $sp_decimal,
                'course' => $course,
                'race_title' => (string) ($row->race_title ?? ''),
                'race_time' => (string) ($row->scheduled_time ?? ''),
                'race_url' => $race_url,
                'strategies' => $strategies,
                'image_url' => '',
                'excerpt' => '',
                'is_featured' => ($sp_decimal !== null && $sp_decimal >= 10.0),
                'source' => 'database',
            ];

            if (count($cases) >= $limit) {
                break;
            }
        }

        return $cases;
    }
}

if (!function_exists('bricks_proven_winners_manual_cases')) {
    function bricks_proven_winners_manual_cases() {
        $stored = get_option('bricks_proven_winners_manual_cases', []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return (array) apply_filters('bricks_proven_winners_manual_cases', $stored);
    }
}

if (!function_exists('bricks_proven_winners_get_cases')) {
    function bricks_proven_winners_get_cases($limit = 48, $min_sp_decimal = 0.0) {
        $limit = max(1, min(120, intval($limit)));
        $cache_key = 'bricks_proven_winners_v1_' . $limit . '_' . floatval($min_sp_decimal);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $manual = bricks_proven_winners_manual_cases();
        $db = bricks_proven_winners_fetch_db_cases($limit, $min_sp_decimal);

        $seen = [];
        $merged = [];

        foreach ($manual as $case) {
            if (!is_array($case)) {
                continue;
            }
            $id = $case['id'] ?? ($case['horse'] ?? '') . ($case['meeting_date'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $case['source'] = 'manual';
            $case['is_featured'] = !empty($case['is_featured']);
            $merged[] = $case;
        }

        foreach ($db as $case) {
            $id = $case['id'] ?? '';
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $merged[] = $case;
        }

        usort($merged, function ($a, $b) {
            $da = (string) ($a['meeting_date'] ?? '');
            $db = (string) ($b['meeting_date'] ?? '');
            return strcmp($db, $da);
        });

        $merged = array_slice($merged, 0, $limit);
        set_transient($cache_key, $merged, 15 * MINUTE_IN_SECONDS);
        return $merged;
    }
}

if (!function_exists('bricks_proven_winners_summary_stats')) {
    /**
     * Published-picks backtest summary for the archive banner.
     *
     * @return array{from_date:string,to_date:string,days:int,summary:array<string,array<string,mixed>>}
     */
    function bricks_proven_winners_summary_stats($days = 365) {
        $empty = [
            'from_date' => '',
            'to_date' => '',
            'days' => max(30, intval($days)),
            'summary' => [],
        ];
        if (!function_exists('bricks_points_backtest_calculate')) {
            return $empty;
        }
        $days = max(30, intval($days));
        $yesterday = wp_date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
        $from = wp_date('Y-m-d', strtotime('-' . $days . ' days', strtotime($yesterday)));
        $result = bricks_points_backtest_calculate($from, $yesterday, '', 'published');
        return [
            'from_date' => $from,
            'to_date' => $yesterday,
            'days' => $days,
            'summary' => $result['summary'] ?? [],
        ];
    }
}

if (!function_exists('bricks_proven_winners_strategy_labels')) {
    function bricks_proven_winners_strategy_labels() {
        return [
            'win' => 'Win pick',
            'place' => 'Place shortlist',
            'ew_simple' => 'EW simple',
            'ew_edge' => 'EW edge',
        ];
    }
}

if (!function_exists('bricks_proven_winners_best_strategy_key')) {
    /**
     * Strategy with highest pts profit on this card (ties favour win > ew_edge > ew_simple > place).
     */
    function bricks_proven_winners_best_strategy_key($strategies) {
        if (!is_array($strategies) || empty($strategies)) {
            return '';
        }
        $order = ['win', 'ew_edge', 'ew_simple', 'place'];
        $best_key = '';
        $best_profit = -INF;
        foreach ($order as $key) {
            if (empty($strategies[$key])) {
                continue;
            }
            $profit = floatval($strategies[$key]['profit'] ?? 0);
            if ($profit > $best_profit) {
                $best_profit = $profit;
                $best_key = $key;
            }
        }
        return $best_key;
    }
}

if (!function_exists('bricks_proven_winners_format_stat_context')) {
    function bricks_proven_winners_format_stat_context($bets, $from_date, $to_date) {
        $bets = max(0, intval($bets));
        $parts = ['n=' . number_format($bets)];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to_date)) {
            $from_label = wp_date('j M Y', strtotime($from_date));
            $to_label = wp_date('j M Y', strtotime($to_date));
            $parts[] = $from_label . ' – ' . $to_label;
        }
        return implode(' · ', $parts);
    }
}

if (!function_exists('bricks_proven_winners_collect_courses')) {
    /**
     * @param array<int, array<string, mixed>> $cases
     * @return array<int, string>
     */
    function bricks_proven_winners_collect_courses(array $cases) {
        $courses = [];
        foreach ($cases as $case) {
            $course = trim((string) ($case['course'] ?? ''));
            if ($course !== '') {
                $courses[$course] = $course;
            }
        }
        natcasesort($courses);
        return array_values($courses);
    }
}

if (!function_exists('bricks_proven_winners_enqueue_styles')) {
    function bricks_proven_winners_enqueue_styles() {
        $css = '
        .proven-winners-page{--pw-green:#16a34a;--pw-green-soft:#ecfdf5}
        .pw-hero{margin-bottom:1.5rem}
        .pw-title{margin:0 0 .5rem;font-size:clamp(1.75rem,3vw,2.25rem);line-height:1.2}
        .pw-lead{margin:0;color:#475569;font-size:1.05rem;line-height:1.6;max-width:720px}
        .pw-explainer{margin:.75rem 0 0;padding:.75rem .9rem;max-width:720px;font-size:.88rem;line-height:1.55;color:#475569;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px}
        .pw-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin:1.25rem 0 1.75rem}
        .pw-stat{padding:.85rem 1rem;border:1px solid #e2e8f0;border-radius:10px;background:#fff}
        .pw-stat-label{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
        .pw-stat-value{display:block;margin-top:.25rem;font-size:1.25rem;font-weight:800;color:#111827}
        .pw-stat-value.is-pos{color:#15803d}
        .pw-stat-value.is-neg{color:#b91c1c}
        .pw-stat-meta{display:block;margin-top:.35rem;font-size:.72rem;line-height:1.4;color:#94a3b8}
        .pw-toolbar{display:flex;flex-direction:column;gap:.75rem;margin:0 0 1.25rem}
        .pw-search{width:100%;max-width:360px;padding:.55rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.9rem}
        .pw-toolbar-row{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem}
        .pw-filters{display:flex;flex-wrap:wrap;gap:.5rem}
        .pw-chip{padding:.4rem .8rem;border:1px solid #e2e8f0;border-radius:999px;background:#fff;font-size:.85rem;font-weight:600;cursor:pointer}
        .pw-chip.is-active{background:var(--pw-green);border-color:var(--pw-green);color:#fff}
        .pw-select{padding:.4rem .65rem;border:1px solid #e2e8f0;border-radius:8px;background:#fff;font-size:.85rem;font-weight:600;color:#334155;cursor:pointer}
        .pw-results-meta{font-size:.8rem;color:#64748b;margin:0 0 .75rem}
        .pw-masonry{column-count:3;column-gap:1.25rem}
        .pw-card{break-inside:avoid;display:block;margin:0 0 1.25rem;padding:1rem 1.1rem;border:1px solid #e2e8f0;border-radius:12px;background:#fff;text-decoration:none;color:inherit;transition:border-color .2s,box-shadow .2s,transform .2s}
        .pw-card:hover,.pw-card:focus-visible{border-color:var(--pw-green);box-shadow:0 6px 20px rgba(15,23,42,.08);transform:translateY(-2px);outline:none}
        .pw-card.is-featured{border-color:#86efac;background:linear-gradient(180deg,#f0fdf4 0%,#fff 40%)}
        .pw-card.is-hidden{display:none!important}
        .pw-card-media{display:block;width:100%;border-radius:8px;margin-bottom:.75rem;overflow:hidden;background:#f1f5f9}
        .pw-card-media img{display:block;width:100%;height:auto}
        .pw-card-badge{display:inline-block;margin-bottom:.5rem;padding:.2rem .55rem;border-radius:999px;background:var(--pw-green);color:#fff;font-size:.7rem;font-weight:800;text-transform:uppercase}
        .pw-card-horse{margin:0 0 .25rem;font-size:1.15rem;font-weight:800;color:#111827}
        .pw-card-meta{margin:0 0 .75rem;font-size:.85rem;color:#64748b;line-height:1.45}
        .pw-card-sp-wrap{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem;margin-bottom:.75rem}
        .pw-card-sp{display:inline-block;padding:.25rem .6rem;border-radius:6px;background:#111827;color:#fff;font-weight:800;font-size:.9rem}
        .pw-card-sp-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
        .pw-roi-headline{display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.5rem;padding:.65rem .75rem;border-radius:10px;background:#f0fdf4;border:1px solid #bbf7d0}
        .pw-roi-headline.is-loss{background:#fef2f2;border-color:#fecaca}
        .pw-roi-headline strong{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
        .pw-roi-headline span{font-size:1.15rem;font-weight:800;color:#15803d}
        .pw-roi-headline.is-loss span{color:#b91c1c}
        .pw-roi-grid{display:grid;grid-template-columns:1fr 1fr;gap:.35rem}
        .pw-roi{padding:.35rem .45rem;border-radius:6px;background:#f8fafc;font-size:.68rem}
        .pw-roi strong{display:block;font-size:.62rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.03em}
        .pw-roi span{font-weight:700;font-size:.72rem}
        .pw-roi.is-win span{color:#15803d}
        .pw-roi.is-loss span{color:#b91c1c}
        .pw-empty{padding:2rem;text-align:center;color:#64748b;border:1px dashed #e2e8f0;border-radius:12px}
        .pw-load-more-wrap{text-align:center;margin:1rem 0 2rem}
        .pw-load-more{padding:.55rem 1.1rem;border:1px solid #e2e8f0;border-radius:8px;background:#fff;font-size:.9rem;font-weight:700;cursor:pointer}
        .pw-load-more:hover{border-color:var(--pw-green);color:#15803d}
        .pw-load-more:disabled{opacity:.5;cursor:not-allowed}
        @media (max-width:1024px){.pw-masonry{column-count:2}}
        @media (max-width:640px){.pw-masonry{column-count:1}.pw-search{max-width:none}}
        ';
        wp_register_style('bricks-proven-winners', false);
        wp_enqueue_style('bricks-proven-winners');
        wp_add_inline_style('bricks-proven-winners', $css);
    }
}

if (!function_exists('bricks_proven_winners_render_strategy_roi')) {
    function bricks_proven_winners_render_strategy_roi($strategies) {
        if (!is_array($strategies) || empty($strategies)) {
            return '<p style="margin:0;font-size:.8rem;color:#94a3b8;">Strategy ROI pending settlement data.</p>';
        }

        $labels = bricks_proven_winners_strategy_labels();
        $best_key = bricks_proven_winners_best_strategy_key($strategies);

        ob_start();

        if ($best_key !== '' && !empty($strategies[$best_key])) {
            $best = $strategies[$best_key];
            $best_profit = floatval($best['profit'] ?? 0);
            $headline_cls = $best_profit >= 0 ? '' : ' is-loss';
            $sign = $best_profit >= 0 ? '+' : '';
            echo '<div class="pw-roi-headline' . esc_attr($headline_cls) . '">';
            echo '<strong>' . esc_html(($labels[$best_key] ?? $best_key) . ' · best result') . '</strong>';
            echo '<span>' . esc_html($sign . number_format($best_profit, 2)) . ' pts</span>';
            echo '</div>';
        }

        echo '<div class="pw-roi-grid">';
        foreach ($labels as $key => $label) {
            if (empty($strategies[$key]) || $key === $best_key) {
                continue;
            }
            $s = $strategies[$key];
            $profit = floatval($s['profit'] ?? 0);
            $cls = $profit >= 0 ? 'is-win' : 'is-loss';
            $sign = $profit >= 0 ? '+' : '';
            echo '<div class="pw-roi ' . esc_attr($cls) . '">';
            echo '<strong>' . esc_html($label) . '</strong>';
            echo '<span>' . esc_html($sign . number_format($profit, 2)) . ' pts</span>';
            echo '</div>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}

if (!function_exists('bricks_proven_winners_archive_shortcode')) {
    function bricks_proven_winners_archive_shortcode($atts = []) {
        $atts = shortcode_atts([
            'limit' => '120',
            'min_sp' => '0',
            'stats_days' => '365',
            'per_page' => '24',
        ], $atts, 'proven_winners_archive');

        bricks_proven_winners_enqueue_styles();
        bricks_proven_winners_enqueue_scripts();

        $limit = max(1, min(120, intval($atts['limit'])));
        $per_page = max(6, min(48, intval($atts['per_page'])));
        $min_sp = floatval($atts['min_sp']);
        $cases = bricks_proven_winners_get_cases($limit, $min_sp);
        $stats_bundle = bricks_proven_winners_summary_stats(intval($atts['stats_days']));
        $summary = $stats_bundle['summary'] ?? [];
        $stats_from = (string) ($stats_bundle['from_date'] ?? '');
        $stats_to = (string) ($stats_bundle['to_date'] ?? '');
        $courses = bricks_proven_winners_collect_courses($cases);

        $years = [];
        foreach ($cases as $case) {
            $md = (string) ($case['meeting_date'] ?? '');
            if (preg_match('/^(\d{4})/', $md, $m)) {
                $years[$m[1]] = $m[1];
            }
        }
        rsort($years);

        ob_start();
        ?>
        <div
            class="proven-winners-page"
            id="proven-winners-archive"
            data-pw-per-page="<?php echo esc_attr((string) $per_page); ?>"
        >
            <header class="pw-hero">
                <h1 class="pw-title">Proven Winners Archive</h1>
                <p class="pw-lead">
                    Documented Points Engine winners with published pre-race picks, settlement odds, and per-race strategy returns.
                </p>
                <p class="pw-explainer">
                    Each card below is a published win pick that won. The summary ROI figures above are calculated across <em>every</em> published pick in the period—including races that lost—so a negative percentage does not contradict this archive; it reflects full-period staking, not winner-only results.
                </p>
            </header>

            <?php if (!empty($summary)): ?>
            <div class="pw-stats" aria-label="Published picks performance summary">
                <?php
                $stat_labels = [
                    'win' => 'Win pick ROI',
                    'place' => 'Place ROI',
                    'ew_simple' => 'EW simple ROI',
                    'ew_edge' => 'EW edge ROI',
                ];
                foreach ($stat_labels as $key => $label):
                    $row = $summary[$key] ?? null;
                    if (!$row) {
                        continue;
                    }
                    $roi = floatval($row['roi_pct'] ?? 0);
                    $cls = $roi >= 0 ? 'is-pos' : 'is-neg';
                    $context = bricks_proven_winners_format_stat_context(
                        intval($row['bets'] ?? 0),
                        $stats_from,
                        $stats_to
                    );
                    ?>
                    <div class="pw-stat">
                        <span class="pw-stat-label"><?php echo esc_html($label); ?></span>
                        <span class="pw-stat-value <?php echo esc_attr($cls); ?>"><?php echo esc_html(number_format($roi, 1)); ?>%</span>
                        <span class="pw-stat-meta"><?php echo esc_html($context); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="pw-toolbar">
                <input
                    type="search"
                    class="pw-search"
                    id="pw-search"
                    placeholder="Search horse name…"
                    aria-label="Search horse name"
                    autocomplete="off"
                />
                <div class="pw-toolbar-row">
                    <div class="pw-filters" role="tablist" aria-label="Filter winners">
                        <button type="button" class="pw-chip is-active" data-pw-filter="all">All winners</button>
                        <button type="button" class="pw-chip" data-pw-filter="featured">Big prices (10/1+)</button>
                    </div>
                    <select class="pw-select" id="pw-sort" aria-label="Sort results">
                        <option value="recent">Most recent</option>
                        <option value="roi-desc">ROI high–low</option>
                        <option value="price-desc">Price high–low</option>
                    </select>
                    <select class="pw-select" id="pw-track" aria-label="Filter by track">
                        <option value="">All tracks</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo esc_attr($course); ?>"><?php echo esc_html($course); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="pw-select" id="pw-date" aria-label="Filter by date">
                        <option value="">All dates</option>
                        <option value="90">Last 90 days</option>
                        <option value="180">Last 180 days</option>
                        <option value="365">Last 365 days</option>
                        <?php foreach ($years as $year): ?>
                            <option value="year-<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <p class="pw-results-meta" id="pw-results-meta" aria-live="polite">
                <?php
                if (!empty($cases)) {
                    $total = count($cases);
                    $shown = min($per_page, $total);
                    if ($shown < $total) {
                        echo esc_html(sprintf('Showing %d of %d winners', $shown, $total));
                    } else {
                        echo esc_html($total . ($total === 1 ? ' winner' : ' winners'));
                    }
                }
                ?>
            </p>

            <?php if (empty($cases)): ?>
                <div class="pw-empty">No proven winners logged yet. Published win picks that land will appear here automatically.</div>
            <?php else: ?>
                <div class="pw-masonry" id="pw-masonry">
                    <?php foreach ($cases as $case_index => $case): ?>
                        <?php
                        $is_featured = !empty($case['is_featured']);
                        $sp_decimal = $case['sp_decimal'] ?? bricks_proven_winners_parse_sp_decimal($case['sp'] ?? '');
                        if ($sp_decimal === null) {
                            $sp_decimal = 0.0;
                        }
                        $strategies = is_array($case['strategies'] ?? null) ? $case['strategies'] : [];
                        $best_key = bricks_proven_winners_best_strategy_key($strategies);
                        $best_profit = ($best_key !== '' && !empty($strategies[$best_key]))
                            ? floatval($strategies[$best_key]['profit'] ?? 0)
                            : 0.0;
                        $card_url = !empty($case['race_url']) ? $case['race_url'] : '#';
                        $meeting_date = (string) ($case['meeting_date'] ?? '');
                        $date_label = $meeting_date !== ''
                            ? wp_date('j M Y', strtotime($meeting_date))
                            : '';
                        $course = (string) ($case['course'] ?? '');
                        $horse = (string) ($case['horse'] ?? '');
                        ?>
                        <a
                            href="<?php echo esc_url($card_url); ?>"
                            class="pw-card<?php echo $is_featured ? ' is-featured' : ''; ?><?php echo ($case_index >= $per_page) ? ' is-hidden' : ''; ?>"
                            data-pw-featured="<?php echo $is_featured ? '1' : '0'; ?>"
                            data-pw-horse="<?php echo esc_attr(strtolower($horse)); ?>"
                            data-pw-course="<?php echo esc_attr($course); ?>"
                            data-pw-date="<?php echo esc_attr($meeting_date); ?>"
                            data-pw-sp="<?php echo esc_attr((string) $sp_decimal); ?>"
                            data-pw-best-roi="<?php echo esc_attr((string) $best_profit); ?>"
                        >
                            <?php if (!empty($case['image_url'])): ?>
                                <span class="pw-card-media">
                                    <img src="<?php echo esc_url($case['image_url']); ?>" alt="" loading="lazy" />
                                </span>
                            <?php endif; ?>
                            <?php if ($is_featured): ?>
                                <span class="pw-card-badge">Big price winner</span>
                            <?php endif; ?>
                            <h2 class="pw-card-horse"><?php echo esc_html($horse); ?></h2>
                            <p class="pw-card-meta">
                                <?php echo esc_html($course); ?>
                                <?php if ($date_label !== ''): ?> · <?php echo esc_html($date_label); ?><?php endif; ?>
                                <?php if (!empty($case['race_title'])): ?><br><?php echo esc_html($case['race_title']); ?><?php endif; ?>
                            </p>
                            <?php if (!empty($case['sp'])): ?>
                                <div class="pw-card-sp-wrap">
                                    <span class="pw-card-sp-label" title="Starting price at settlement">Settlement odds</span>
                                    <span class="pw-card-sp"><?php echo esc_html($case['sp']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php echo bricks_proven_winners_render_strategy_roi($strategies); ?>
                            <?php if (!empty($case['excerpt'])): ?>
                                <p style="margin:.75rem 0 0;font-size:.8rem;color:#475569;"><?php echo esc_html($case['excerpt']); ?></p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="pw-load-more-wrap" id="pw-load-more-wrap"<?php echo (count($cases) <= $per_page) ? ' hidden' : ''; ?>>
                    <button type="button" class="pw-load-more" id="pw-load-more">Load more</button>
                </div>
                <div class="pw-empty" id="pw-no-results" hidden>No winners match your filters.</div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('proven_winners_archive', 'bricks_proven_winners_archive_shortcode');

if (!function_exists('bricks_proven_winners_enqueue_scripts')) {
    function bricks_proven_winners_enqueue_scripts() {
        if (wp_script_is('proven-winners-archive', 'enqueued')) {
            return;
        }
        $js = get_stylesheet_directory() . '/proven-winners.js';
        if (!file_exists($js)) {
            return;
        }
        wp_enqueue_script(
            'proven-winners-archive',
            get_stylesheet_directory_uri() . '/proven-winners.js',
            [],
            filemtime($js),
            true
        );
    }
}

if (!function_exists('bricks_proven_winners_maybe_enqueue_scripts')) {
    function bricks_proven_winners_maybe_enqueue_scripts() {
        if (
            get_query_var('proven_winners_page')
            || (function_exists('bricks_current_post_has_shortcode') && bricks_current_post_has_shortcode(['proven_winners_archive']))
        ) {
            bricks_proven_winners_enqueue_styles();
            bricks_proven_winners_enqueue_scripts();
        }
    }
}
add_action('wp_enqueue_scripts', 'bricks_proven_winners_maybe_enqueue_scripts', 26);

if (!function_exists('bricks_add_proven_winners_rewrite_rules')) {
    function bricks_add_proven_winners_rewrite_rules() {
        add_rewrite_tag('%proven_winners_page%', '([0-9]+)');
        add_rewrite_rule('^proven-winners/?$', 'index.php?proven_winners_page=1', 'top');
        add_rewrite_rule('^results/?$', 'index.php?proven_winners_page=1', 'top');
    }
}
add_action('init', 'bricks_add_proven_winners_rewrite_rules', 20);

if (!function_exists('bricks_add_proven_winners_query_vars')) {
    function bricks_add_proven_winners_query_vars($vars) {
        $vars[] = 'proven_winners_page';
        return $vars;
    }
}
add_filter('query_vars', 'bricks_add_proven_winners_query_vars');

if (!function_exists('bricks_proven_winners_customize_virtual_post')) {
    function bricks_proven_winners_customize_virtual_post() {
        if (!bricks_proven_winners_is_request()) {
            return;
        }

        global $post, $wp_query;
        $post = new WP_Post((object) [
            'ID' => 0,
            'post_title' => 'Proven Winners Archive',
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'proven-winners',
        ]);
        $wp_query->post = $post;
        $wp_query->posts = [$post];
        $wp_query->post_count = 1;
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        setup_postdata($post);
    }
}
add_action('wp', 'bricks_proven_winners_customize_virtual_post', 10);

if (!function_exists('bricks_proven_winners_render_main_content')) {
    function bricks_proven_winners_render_main_content() {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        $content_template_id = 0;
        if (function_exists('bricks_proven_winners_get_bricks_content_template_id')) {
            $content_template_id = bricks_proven_winners_get_bricks_content_template_id();
        }

        if ($content_template_id > 0 && class_exists('\Bricks\Frontend')) {
            \Bricks\Frontend::render_content(get_the_ID(), 'content');
            return;
        }

        echo do_shortcode('[proven_winners_archive]');
    }
}

if (!function_exists('bricks_proven_winners_get_bricks_content_template_id')) {
    function bricks_proven_winners_get_bricks_content_template_id() {
        if (defined('BRICKS_PROVEN_WINNERS_TEMPLATE_CONTENT')) {
            return max(0, intval(constant('BRICKS_PROVEN_WINNERS_TEMPLATE_CONTENT')));
        }
        return max(0, intval(get_option('bricks_proven_winners_tpl_content', 0)));
    }
}

if (!function_exists('bricks_proven_winners_filter_active_templates')) {
    function bricks_proven_winners_filter_active_templates($active_templates, $post_id, $content_type) {
        if (!bricks_proven_winners_is_request() || !is_array($active_templates)) {
            return $active_templates;
        }
        $tpl = bricks_proven_winners_get_bricks_content_template_id();
        if ($tpl > 0 && get_post_status($tpl) === 'publish') {
            $active_templates['content'] = $tpl;
        }
        return $active_templates;
    }
}
add_filter('bricks/active_templates', 'bricks_proven_winners_filter_active_templates', 22, 3);

if (!function_exists('bricks_proven_winners_template')) {
    function bricks_proven_winners_template($template) {
        if (is_admin() || !get_query_var('proven_winners_page')) {
            return $template;
        }
        $custom = get_stylesheet_directory() . '/proven-winners.php';
        if (file_exists($custom)) {
            return $custom;
        }
        return $template;
    }
}
add_filter('template_include', 'bricks_proven_winners_template');

if (!function_exists('bricks_flush_proven_winners_rewrite_rules_if_needed')) {
    function bricks_flush_proven_winners_rewrite_rules_if_needed() {
        if (get_option('bricks_proven_winners_rewrite_flushed') !== '1') {
            flush_rewrite_rules();
            update_option('bricks_proven_winners_rewrite_flushed', '1');
        }
    }
}
add_action('init', 'bricks_flush_proven_winners_rewrite_rules_if_needed', 999);

if (!function_exists('bricks_proven_winners_bump_cache')) {
    function bricks_proven_winners_bump_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bricks_proven_winners_%' OR option_name LIKE '_transient_timeout_bricks_proven_winners_%'");
    }
}
add_action('bricks_daily_filter_cache_flush', 'bricks_proven_winners_bump_cache');

if (!function_exists('bricks_proven_winners_register_settings_page')) {
    function bricks_proven_winners_register_settings_page() {
        add_options_page(
            'Proven Winners',
            'Proven Winners',
            'manage_options',
            'bricks-proven-winners',
            'bricks_proven_winners_render_settings_page'
        );
    }
}
add_action('admin_menu', 'bricks_proven_winners_register_settings_page');

if (!function_exists('bricks_proven_winners_render_settings_page')) {
    function bricks_proven_winners_render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_POST['bricks_proven_winners_tpl_content']) && check_admin_referer('bricks_proven_winners_settings')) {
            update_option('bricks_proven_winners_tpl_content', absint($_POST['bricks_proven_winners_tpl_content']));
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        $tpl = bricks_proven_winners_get_bricks_content_template_id();
        ?>
        <div class="wrap">
            <h1>Proven Winners Archive</h1>
            <p>Virtual URLs: <code>/proven-winners/</code> and <code>/results/</code>. Bricks shortcode: <code>[proven_winners_archive]</code></p>
            <form method="post">
                <?php wp_nonce_field('bricks_proven_winners_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="bricks_proven_winners_tpl_content">Bricks content template ID</label></th>
                        <td>
                            <input type="number" name="bricks_proven_winners_tpl_content" id="bricks_proven_winners_tpl_content" value="<?php echo esc_attr($tpl); ?>" min="0" class="small-text" />
                            <p class="description">Optional. Template should include <code>[proven_winners_archive]</code> inside a masonry-friendly section.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save'); ?>
            </form>
            <h2>Manual featured case (code)</h2>
            <pre style="background:#f6f7f7;padding:12px;max-width:760px;overflow:auto;">add_filter('bricks_proven_winners_manual_cases', function ($cases) {
    $cases[] = [
        'id' => 'angel-ang-2025',
        'horse' => 'Angel Ang',
        'sp' => '33/1',
        'sp_decimal' => 34.0,
        'course' => 'Example Course',
        'meeting_date' => '2025-06-01',
        'race_title' => 'Handicap Hurdle',
        'race_url' => home_url('/race/.../'),
        'image_url' => 'https://yoursite.com/wp-content/uploads/angel-ang-finish.jpg',
        'excerpt' => 'Points Engine win pick landed at 33/1.',
        'is_featured' => true,
        'strategies' => [
            'win' => ['profit' => 33.0, 'hit' => true],
            'place' => ['profit' => 2.5, 'hit' => true],
            'ew_simple' => ['profit' => 30.0, 'hit' => true],
            'ew_edge' => ['profit' => 28.0, 'hit' => true],
        ],
    ];
    return $cases;
});</pre>
        </div>
        <?php
    }
}

if (!function_exists('bricks_add_proven_winners_menu_item')) {
    function bricks_add_proven_winners_menu_item($items, $args) {
        if (is_admin()) {
            return $items;
        }

        $archive_url = function_exists('bricks_proven_winners_url')
            ? bricks_proven_winners_url()
            : home_url('/proven-winners/');

        if (strpos($items, $archive_url) !== false || strpos($items, '/proven-winners') !== false) {
            return $items;
        }

        $current_url = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $active_class = (
            strpos($current_url, '/proven-winners') !== false
            || preg_match('#/results(?:/|$)#i', $current_url)
        ) ? ' current-menu-item current_page_item' : '';

        $items .= '<li class="menu-item menu-item-type-custom menu-item-proven-winners' . esc_attr($active_class) . '">'
            . '<a href="' . esc_url($archive_url) . '">Proven Winners</a>'
            . '</li>';

        return $items;
    }
}
add_filter('wp_nav_menu_items', 'bricks_add_proven_winners_menu_item', 21, 2);

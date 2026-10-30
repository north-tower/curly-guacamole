<?php
/**
 * Race detail page performance helpers (caching, lazy-load hooks).
 */

if (!function_exists('bricks_normalize_sire_name_key')) {
    function bricks_normalize_sire_name_key($name) {
        $name = is_string($name) ? $name : '';
        // Remove trailing country code in parentheses, e.g., "Galileo (IRE)" -> "Galileo"
        $name = preg_replace('/\s*\([A-Z]{2,3}\)\s*$/', '', $name);
        // Collapse multiple spaces and lowercase for key normalization
        $name = strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        return $name;
    }
}

if (!function_exists('bricks_extract_sire_name')) {
    function bricks_extract_sire_name($row) {
        if (!is_object($row)) {
            return '';
        }
        if (isset($row->sire_name) && $row->sire_name !== '') {
            return trim((string) $row->sire_name);
        }
        if (isset($row->sire) && $row->sire !== '') {
            return trim((string) $row->sire);
        }
        return '';
    }
}

if (!function_exists('bricks_lin5_quality')) {
    function bricks_lin5_quality($runs) {
        $runs = intval($runs);
        if ($runs >= 100) {
            return ['label' => 'High', 'class' => 'lin5-quality-high'];
        }
        if ($runs >= 30) {
            return ['label' => 'Med', 'class' => 'lin5-quality-med'];
        }
        if ($runs >= 1) {
            return ['label' => 'Low', 'class' => 'lin5-quality-low'];
        }
        return ['label' => 'N/A', 'class' => 'lin5-quality-na'];
    }
}

if (!function_exists('bricks_race_detail_resolve_context')) {
    /**
     * Resolve race row + table set (beta vs advance/tomorrow) with per-request cache.
     *
     * @return array<string, mixed>|null
     */
    function bricks_race_detail_resolve_context($race_id) {
        global $wpdb;

        $race_id = intval($race_id);
        if ($race_id <= 0) {
            return null;
        }

        static $request_cache = [];
        if (array_key_exists($race_id, $request_cache)) {
            return $request_cache[$race_id];
        }

        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $races_table = 'advance_daily_races_beta';
        $runners_table = 'advance_daily_runners_beta';
        $speed_table = 'speed&performance_table';

        $race = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$races_table` WHERE race_id = %d",
            $race_id
        ));

        if (!$race) {
            $races_table = 'advance_daily_races';
            $runners_table = 'advance_daily_runners';
            $speed_table = 'adv_speed&performance_table';
            $race = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM `$races_table` WHERE race_id = %d",
                $race_id
            ));
        } elseif (isset($race->meeting_date) && $race->meeting_date === $tomorrow) {
            $races_table = 'advance_daily_races';
            $runners_table = 'advance_daily_runners';
            $speed_table = 'adv_speed&performance_table';
            $advance_race = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM `$races_table` WHERE race_id = %d",
                $race_id
            ));
            if ($advance_race) {
                $race = $advance_race;
            }
        }

        if (!$race) {
            $request_cache[$race_id] = null;
            return null;
        }

        $request_cache[$race_id] = [
            'race' => $race,
            'races_table' => $races_table,
            'runners_table' => $runners_table,
            'speed_table' => $speed_table,
        ];
        return $request_cache[$race_id];
    }
}

if (!function_exists('bricks_race_detail_meeting_races_cached')) {
    function bricks_race_detail_meeting_races_cached($races_table, $race_date) {
        global $wpdb;

        $race_date = (string) $race_date;
        $cache_key = 'bricks_meeting_races_' . md5($races_table . '|' . $race_date);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT race_id, course, scheduled_time, race_title, class
             FROM `$races_table`
             WHERE meeting_date = %s
             ORDER BY course ASC, scheduled_time ASC",
            $race_date
        ));

        if (!is_array($rows)) {
            $rows = [];
        }

        set_transient($cache_key, $rows, 15 * MINUTE_IN_SECONDS);
        return $rows;
    }
}

if (!function_exists('bricks_race_detail_lin5_code')) {
    function bricks_race_detail_lin5_code($is_national_hunt) {
        return $is_national_hunt ? 'jumps' : 'flat';
    }
}

if (!function_exists('bricks_race_detail_lin5_race_type_filter')) {
    /**
     * WHERE fragment limiting history to the flat or jumps code. Expects `hracb` / `dracb` aliases.
     */
    function bricks_race_detail_lin5_race_type_filter($code = 'flat') {
        $race_type = 'LOWER(COALESCE(dracb.race_type, hracb.race_type))';
        $jumps_terms = ['%hurdle%', '%chase%', '%nh%', '%n_h_flat%', '%national hunt%'];
        $sql = "COALESCE(dracb.race_type, hracb.race_type) IS NOT NULL
          AND COALESCE(dracb.race_type, hracb.race_type) != ''";
        if ($code === 'jumps') {
            $likes = [];
            foreach ($jumps_terms as $term) {
                $likes[] = "$race_type LIKE '$term'";
            }
            return $sql . "\n          AND (" . implode(' OR ', $likes) . ')';
        }
        foreach ($jumps_terms as $term) {
            $sql .= "\n          AND $race_type NOT LIKE '$term'";
        }
        return $sql;
    }
}

if (!function_exists('bricks_race_detail_sire_baseline_prb_cached')) {
    function bricks_race_detail_sire_baseline_prb_cached($from_date, $to_date, $code = 'flat') {
        global $wpdb;

        $code = ($code === 'jumps') ? 'jumps' : 'flat';
        $hash = md5($code . '|' . $from_date . '|' . $to_date);
        $cache_key = 'bricks_sire_baseline_' . $hash;
        $cached = get_transient($cache_key);
        if ($cached !== false && is_numeric($cached)) {
            return floatval($cached);
        }

        $race_type_filter = bricks_race_detail_lin5_race_type_filter($code);
        $season_filter = ($code === 'flat') ? 'AND MONTH(hracb.meeting_date) BETWEEN 3 AND 10' : '';
        $baseline_sql = "
            SELECT ROUND(AVG((rf.field_size - hrunb.finish_position) / (rf.field_size - 1)) * 100, 1) AS baseline_prb
            FROM historic_runners_beta hrunb
            INNER JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
            LEFT JOIN daily_races_beta dracb ON dracb.race_id = hrunb.race_id
            INNER JOIN (
                SELECT race_id, COUNT(*) AS field_size
                FROM historic_runners_beta
                WHERE finish_position IS NOT NULL AND finish_position > 0
                GROUP BY race_id
                HAVING COUNT(*) > 1
            ) rf ON rf.race_id = hrunb.race_id
            WHERE hracb.meeting_date BETWEEN %s AND %s
              $season_filter
              AND $race_type_filter";

        $stale_option = 'bricks_sire_baseline_stale_' . $hash;
        $stale = get_option($stale_option);
        $refresh_lock = $cache_key . '_refreshing';
        if ($stale !== false && is_numeric($stale) && !get_transient($refresh_lock)) {
            set_transient($refresh_lock, 1, 10 * MINUTE_IN_SECONDS);
            register_shutdown_function(static function () use ($wpdb, $baseline_sql, $from_date, $to_date, $cache_key, $stale_option, $refresh_lock) {
                $baseline_prb = $wpdb->get_var($wpdb->prepare($baseline_sql, $from_date, $to_date));
                if ($baseline_prb !== null && is_numeric($baseline_prb)) {
                    $val = floatval($baseline_prb);
                    set_transient($cache_key, $val, DAY_IN_SECONDS);
                    update_option($stale_option, $val, false);
                }
                delete_transient($refresh_lock);
            });
            return floatval($stale);
        }

        $baseline_prb = $wpdb->get_var($wpdb->prepare($baseline_sql, $from_date, $to_date));
        $baseline_prb = ($baseline_prb !== null) ? floatval($baseline_prb) : 50.0;

        set_transient($cache_key, $baseline_prb, DAY_IN_SECONDS);
        update_option($stale_option, $baseline_prb, false);
        return $baseline_prb;
    }
}

if (!function_exists('bricks_race_detail_speed_ratings_for_race')) {
    /**
     * @return array{0: object[], 1: array<string, object>, 2: array<string, object>}
     */
    function bricks_race_detail_speed_ratings_for_race($race_id, $speed_table, $runners_table, $meeting_date, $race_show_premium_data, $advanced_going = null) {
        global $wpdb;

        $race_id = intval($race_id);
        $speed_ratings = [];
        $speed_ratings_lookup = [];
        $speed_ratings_by_runner_id = [];

        if (!$race_show_premium_data && !empty($advanced_going)) {
            return [$speed_ratings, $speed_ratings_lookup, $speed_ratings_by_runner_id];
        }

        $speed_ratings = $wpdb->get_results($wpdb->prepare(
            "SELECT sp.*, r.name as horse_name
             FROM `$speed_table` sp
             LEFT JOIN $runners_table r ON sp.race_id = r.race_id AND sp.runner_id = r.runner_id
             WHERE sp.race_id = %d",
            $race_id
        ));

        if (!$speed_ratings || count($speed_ratings) === 0) {
            $date_dmy = function_exists('convert_date_format')
                ? convert_date_format($meeting_date, 'd-m-Y')
                : $meeting_date;
            $date_ymd = function_exists('convert_date_format')
                ? convert_date_format($meeting_date, 'Y-m-d')
                : $meeting_date;
            $speed_ratings = $wpdb->get_results($wpdb->prepare(
                "SELECT sp.*, r.name as horse_name
                 FROM `$speed_table` sp
                 LEFT JOIN $runners_table r ON sp.race_id = r.race_id AND sp.runner_id = r.runner_id
                 WHERE sp.race_id = %d AND sp.Date IN (%s, %s)",
                $race_id,
                $date_dmy,
                $date_ymd
            ));
        }

        if ($speed_ratings) {
            foreach ($speed_ratings as $rating) {
                $horse_name = ($rating->horse_name ?? '') !== '' ? $rating->horse_name : ($rating->name ?? '');
                if ($horse_name) {
                    $speed_ratings_lookup[$horse_name] = $rating;
                }
                if (isset($rating->runner_id) && $rating->runner_id !== null && $rating->runner_id !== '') {
                    $speed_ratings_by_runner_id[(string) $rating->runner_id] = $rating;
                }
            }
        }

        return [$speed_ratings, $speed_ratings_lookup, $speed_ratings_by_runner_id];
    }
}

if (!function_exists('bricks_race_detail_collect_sire_names')) {
    /**
     * @param object[] $runners
     * @param object[] $speed_ratings
     * @return string[]
     */
    function bricks_race_detail_collect_sire_names(array $runners, array $speed_ratings) {
        if (!function_exists('bricks_extract_sire_name')) {
            return [];
        }

        $sire_names = [];
        foreach ($speed_ratings as $rating) {
            $raw = bricks_extract_sire_name($rating);
            if ($raw !== '') {
                $sire_names[] = $raw;
            }
        }
        foreach ($runners as $runner_item) {
            $raw = bricks_extract_sire_name($runner_item);
            if ($raw !== '') {
                $sire_names[] = $raw;
            }
        }
        return array_values(array_unique(array_filter($sire_names)));
    }
}

if (!function_exists('bricks_race_detail_pedigree_rows_payload')) {
    /**
     * Lin5 + dosage enrichment for runner cards. Lin5 uses flat or jumps sire history to match the race.
     *
     * @param object[] $runners
     * @return array<int, array<string, mixed>>
     */
    function bricks_race_detail_pedigree_rows_payload($race_id, array $runners, array $speed_ratings_lookup, $is_national_hunt) {
        if (empty($runners)) {
            return [];
        }

        $lin5_code = bricks_race_detail_lin5_code($is_national_hunt);
        $sire_backtest_to = date('Y-m-d', strtotime('-1 day'));
        $sire_backtest_from = date('Y-m-d', strtotime('-5 years', strtotime($sire_backtest_to)));

        $speed_ratings = [];
        foreach ($speed_ratings_lookup as $rating) {
            $speed_ratings[] = $rating;
        }
        $sire_names = bricks_race_detail_collect_sire_names($runners, $speed_ratings);
        $sire_5y_lookup = [];
        if (!empty($sire_names)) {
            $baseline_prb = bricks_race_detail_sire_baseline_prb_cached($sire_backtest_from, $sire_backtest_to, $lin5_code);
            $sire_5y_lookup = bricks_race_detail_lin5_lookup_cached(
                $sire_names,
                $sire_backtest_from,
                $sire_backtest_to,
                $baseline_prb,
                30.0,
                $lin5_code
            );
        }

        $dosage_lookup = [];
        if (function_exists('bricks_dosage_metrics_for_runners')) {
            $dosage_cache_key = 'bricks_dosage_race_' . intval($race_id);
            $dosage_cached = get_transient($dosage_cache_key);
            if (is_array($dosage_cached)) {
                $dosage_lookup = $dosage_cached;
            } else {
                $dosage_lookup = bricks_dosage_metrics_for_runners($runners);
                if (is_array($dosage_lookup)) {
                    set_transient($dosage_cache_key, $dosage_lookup, DAY_IN_SECONDS);
                }
            }
        }

        $out = [];
        foreach ($runners as $index => $runner) {
            $runner_id = isset($runner->runner_id) ? intval($runner->runner_id) : 0;
            $horse_name = isset($runner->name) ? (string) $runner->name : '';
            $speed_data = ($horse_name !== '' && isset($speed_ratings_lookup[$horse_name]))
                ? $speed_ratings_lookup[$horse_name]
                : null;

            $sire_name = $speed_data ? bricks_extract_sire_name($speed_data) : '';
            if ($sire_name === '') {
                $sire_name = bricks_extract_sire_name($runner);
            }

            $sire_5y_pct = null;
            $sire_5y_runs = 0;
            $sire_5y_display = '-';
            $sire_5y_title = 'Lineage 5Y PRB unavailable for this runner.';
            $sire_5y_quality = function_exists('bricks_lin5_quality') ? bricks_lin5_quality(0) : ['label' => 'N/A', 'class' => 'lin5-quality-na'];

            if ($sire_name !== '' && function_exists('bricks_normalize_sire_name_key')) {
                $entry = $sire_5y_lookup[$sire_name]
                    ?? $sire_5y_lookup[strtolower($sire_name)]
                    ?? $sire_5y_lookup[bricks_normalize_sire_name_key($sire_name)]
                    ?? null;
                if (is_array($entry)) {
                    $sire_5y_pct = isset($entry['prb_pct']) ? floatval($entry['prb_pct']) : 0.0;
                    $sire_5y_runs = isset($entry['runs']) ? intval($entry['runs']) : 0;
                    $ctx_used = isset($entry['context']) ? (string) $entry['context'] : '';
                    $raw_prb_used = isset($entry['raw_prb_pct']) ? $entry['raw_prb_pct'] : null;
                    $baseline_prb_used = isset($entry['baseline_prb_pct']) ? $entry['baseline_prb_pct'] : null;
                } else {
                    $sire_5y_pct = 0.0;
                    $ctx_used = 'No data';
                    $raw_prb_used = null;
                    $baseline_prb_used = null;
                }
                $sire_5y_display = number_format($sire_5y_pct, 1) . '%';
                $sire_5y_quality = bricks_lin5_quality($sire_5y_runs);
                $sire_5y_title = 'Lineage 5Y (internal): adjusted sire PRB% (percentage of rivals beaten) in locked window ('
                    . $sire_backtest_from . ' to ' . $sire_backtest_to . '). '
                    . (!empty($ctx_used) ? 'Context: ' . $ctx_used . '. ' : '')
                    . 'Runs: ' . number_format($sire_5y_runs) . '.'
                    . (($raw_prb_used !== null) ? ' Raw PRB: ' . number_format(floatval($raw_prb_used), 1) . '%.' : '')
                    . (($baseline_prb_used !== null) ? ' Baseline PRB: ' . number_format(floatval($baseline_prb_used), 1) . '%.' : '')
                    . ' Signal quality: ' . $sire_5y_quality['label'] . '.';
            }

            $dosage = [
                'display' => 'DI: — | CD: —',
                'di' => null,
                'cd' => null,
                'di_is_infinite' => false,
                'tooltip' => 'Dosage unavailable for this runner.',
            ];
            if (function_exists('bricks_dosage_metrics_for_runner_row')) {
                $dosage = bricks_dosage_metrics_for_runner_row($dosage_lookup, $runner, $index);
            }
            $dosage_display = isset($dosage['display']) ? (string) $dosage['display'] : 'DI: — | CD: —';
            $dosage_tooltip = isset($dosage['tooltip']) ? (string) $dosage['tooltip'] : '';
            $dosage_kv = $dosage_display;
            if (!empty($dosage['di_is_infinite'])) {
                $cd_part = (isset($dosage['cd']) && is_numeric($dosage['cd'])) ? number_format((float) $dosage['cd'], 2) : '—';
                $dosage_kv = 'Inf / ' . $cd_part;
            } elseif (isset($dosage['di']) && is_numeric($dosage['di']) && isset($dosage['cd']) && is_numeric($dosage['cd'])) {
                $dosage_kv = number_format((float) $dosage['di'], 2) . ' / ' . number_format((float) $dosage['cd'], 2);
            }
            if (!empty($dosage['di_is_infinite'])) {
                $dosage_sort_value = 9999;
            } elseif (isset($dosage['di']) && is_numeric($dosage['di'])) {
                $dosage_sort_value = floatval($dosage['di']);
            } else {
                $dosage_sort_value = -9999;
            }

            $hi_class = ($sire_5y_pct !== null && $sire_5y_pct >= 14) ? ' lin5-cell__pct--hi' : '';
            $lin5_html = '<span class="lin5-cell">'
                . '<span class="lin5-cell__pct' . esc_attr($hi_class) . '">' . esc_html($sire_5y_display) . '</span>'
                . '<span class="lin5-quality-badge ' . esc_attr($sire_5y_quality['class']) . '">' . esc_html($sire_5y_quality['label']) . '</span>'
                . '</span>';

            $key = $runner_id > 0 ? $runner_id : ('idx_' . $index);
            $out[$key] = [
                'runner_id' => $runner_id,
                'lin5_html' => $lin5_html,
                'lin5_title' => $sire_5y_title,
                'dosage_kv' => $dosage_kv,
                'dosage_title' => $dosage_tooltip,
                'data_sire5y' => ($sire_5y_pct !== null) ? $sire_5y_pct : -9999,
                'data_dosage_di' => $dosage_sort_value,
            ];
        }

        return $out;
    }
}

if (!function_exists('bricks_ajax_load_race_pedigree_enrichment')) {
    function bricks_ajax_load_race_pedigree_enrichment() {
        $race_id = isset($_POST['race_id']) ? intval($_POST['race_id']) : 0;
        if ($race_id <= 0) {
            wp_send_json_error(['message' => 'Invalid race'], 400);
        }
        if (!function_exists('bricks_race_detail_can_view_premium') || !bricks_race_detail_can_view_premium()) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        $ctx = bricks_race_detail_resolve_context($race_id);
        if (!$ctx || empty($ctx['race'])) {
            wp_send_json_error(['message' => 'Race not found'], 404);
        }

        global $wpdb;
        $race = $ctx['race'];
        $runners_table = $ctx['runners_table'];
        $speed_table = $ctx['speed_table'];
        $runners = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $runners_table WHERE race_id = %d",
            $race_id
        ));
        if (!is_array($runners)) {
            $runners = [];
        }

        $is_national_hunt = false;
        if (!empty($race->race_type)) {
            $race_type_lower = strtolower((string) $race->race_type);
            $is_national_hunt = (
                strpos($race_type_lower, 'hurdle') !== false
                || strpos($race_type_lower, 'chase') !== false
                || strpos($race_type_lower, 'n_h_flat') !== false
                || strpos($race_type_lower, 'nh_flat') !== false
                || strpos($race_type_lower, 'national hunt') !== false
            );
        }

        list(, $speed_ratings_lookup,) = bricks_race_detail_speed_ratings_for_race(
            $race_id,
            $speed_table,
            $runners_table,
            $race->meeting_date,
            true,
            $race->advanced_going ?? null
        );

        wp_send_json_success([
            'rows' => bricks_race_detail_pedigree_rows_payload($race_id, $runners, $speed_ratings_lookup, $is_national_hunt),
        ]);
    }
}
add_action('wp_ajax_load_race_pedigree_enrichment', 'bricks_ajax_load_race_pedigree_enrichment');
add_action('wp_ajax_nopriv_load_race_pedigree_enrichment', 'bricks_ajax_load_race_pedigree_enrichment');

if (!function_exists('bricks_race_detail_lin5_sql_sire_names')) {
    /**
     * One canonical DB sire name per normalized key (drops duplicate lowercase aliases).
     *
     * @return string[]
     */
    function bricks_race_detail_lin5_sql_sire_names(array $sire_names) {
        $by_norm = [];
        foreach ($sire_names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $norm = function_exists('bricks_normalize_sire_name_key')
                ? bricks_normalize_sire_name_key($name)
                : strtolower($name);
            if ($norm === '') {
                continue;
            }
            if (!isset($by_norm[$norm]) || strlen($name) > strlen($by_norm[$norm])) {
                $by_norm[$norm] = $name;
            }
        }
        return array_values($by_norm);
    }
}

if (!function_exists('bricks_race_detail_lin5_merge_entry')) {
    /**
     * @param array<string, array> $lookup
     */
    function bricks_race_detail_lin5_merge_entry(array &$lookup, $canonical_sire_name, array $by_sire) {
        $sire_key = trim((string) $canonical_sire_name);
        if ($sire_key === '') {
            return;
        }
        $sire_key_lower = strtolower($sire_key);
        $lookup[$sire_key] = $by_sire;
        $lookup[$sire_key_lower] = $by_sire;
        if (function_exists('bricks_normalize_sire_name_key')) {
            $sire_key_norm = bricks_normalize_sire_name_key($sire_key);
            if ($sire_key_norm !== $sire_key_lower) {
                $lookup[$sire_key_norm] = $by_sire;
            }
        }
    }
}

if (!function_exists('bricks_race_detail_lin5_sire_transient_key')) {
    function bricks_race_detail_lin5_sire_transient_key($sire_name, $from_date, $to_date, $code = 'flat') {
        $norm = function_exists('bricks_normalize_sire_name_key')
            ? bricks_normalize_sire_name_key($sire_name)
            : strtolower(trim((string) $sire_name));
        return 'bricks_lin5_sire_v3_' . md5($code . '|' . $from_date . '|' . $to_date . '|' . $norm);
    }
}

if (!function_exists('bricks_race_detail_lin5_rows_to_lookup')) {
    /**
     * @return array<string, array>
     */
    function bricks_race_detail_lin5_rows_to_lookup($rows, $baseline_prb, $shrink_k, $code = 'flat') {
        $sire_5y_lookup = [];
        if ($code === 'jumps') {
            $level_defs = [
                4 => ['runs' => 'lvl4_runs', 'prb' => 'lvl4_raw_prb', 'context' => 'All Jumps'],
            ];
        } else {
            $level_defs = [
                4 => ['runs' => 'lvl4_runs', 'prb' => 'lvl4_raw_prb', 'context' => 'All Flat'],
                3 => ['runs' => 'lvl3_runs', 'prb' => 'lvl3_raw_prb', 'context' => 'Mar-Oct, any dist'],
                2 => ['runs' => 'lvl2_runs', 'prb' => 'lvl2_raw_prb', 'context' => 'Mar-Jun, any dist'],
                1 => ['runs' => 'lvl1_runs', 'prb' => 'lvl1_raw_prb', 'context' => 'Mar-Jun, 5-6f'],
            ];
        }

        if (empty($rows)) {
            return $sire_5y_lookup;
        }

        foreach ($rows as $row) {
            $sire_key = trim((string) ($row->sire_name ?? ''));
            if ($sire_key === '') {
                continue;
            }

            $by_sire = null;
            foreach ($level_defs as $def) {
                $runs = isset($row->{$def['runs']}) ? intval($row->{$def['runs']}) : 0;
                if ($runs <= 0) {
                    continue;
                }
                $candidate = [
                    'runs' => $runs,
                    'raw_prb_pct' => round(floatval($row->{$def['prb']}), 1),
                    'context' => $def['context'],
                ];
                if ($by_sire === null || $runs > $by_sire['runs']) {
                    $by_sire = $candidate;
                }
            }

            if ($by_sire === null) {
                continue;
            }

            $runs = intval($by_sire['runs']);
            $raw_prb = floatval($by_sire['raw_prb_pct']);
            $adj_prb = round((($runs * $raw_prb) + ($shrink_k * $baseline_prb)) / ($runs + $shrink_k), 1);
            $by_sire['prb_pct'] = $adj_prb;
            $by_sire['raw_prb_pct'] = round($raw_prb, 1);
            $by_sire['baseline_prb_pct'] = round($baseline_prb, 1);
            $by_sire['shrink_k'] = $shrink_k;

            bricks_race_detail_lin5_merge_entry($sire_5y_lookup, $sire_key, $by_sire);
        }

        return $sire_5y_lookup;
    }
}

if (!function_exists('bricks_race_detail_lin5_lookup_cached')) {
    /**
     * Sire Lin5 PRB lookup — single combined query instead of four level queries on cache miss.
     *
     * @return array<string, array{runs:int, raw_prb_pct:float, context:string}>
     */
    function bricks_race_detail_lin5_lookup_cached(array $sire_names, $from_date, $to_date, $baseline_prb, $shrink_k = 30.0, $code = 'flat') {
        global $wpdb;

        $sire_names = array_values(array_unique(array_filter(array_map('strval', $sire_names))));
        if (empty($sire_names)) {
            return [];
        }

        $code = ($code === 'jumps') ? 'jumps' : 'flat';
        $from_date = (string) $from_date;
        $to_date = (string) $to_date;
        $baseline_prb = floatval($baseline_prb);
        $shrink_k = floatval($shrink_k);
        $sql_sire_names = bricks_race_detail_lin5_sql_sire_names($sire_names);
        if (empty($sql_sire_names)) {
            return [];
        }

        $lin5_cache_key = 'bricks_lin5_v3_' . md5(wp_json_encode([
            'code' => $code,
            'from' => $from_date,
            'to' => $to_date,
            'sire_names' => $sql_sire_names,
        ]));
        $lin5_cached = get_transient($lin5_cache_key);
        if (is_array($lin5_cached) && isset($lin5_cached['lookup']) && is_array($lin5_cached['lookup'])) {
            return $lin5_cached['lookup'];
        }

        $sire_5y_lookup = [];
        $missing_sires = [];
        foreach ($sql_sire_names as $sql_sire_name) {
            $sire_transient = get_transient(bricks_race_detail_lin5_sire_transient_key($sql_sire_name, $from_date, $to_date, $code));
            if (is_array($sire_transient) && isset($sire_transient['entry']) && is_array($sire_transient['entry'])) {
                bricks_race_detail_lin5_merge_entry($sire_5y_lookup, $sql_sire_name, $sire_transient['entry']);
            } else {
                $missing_sires[] = $sql_sire_name;
            }
        }

        if (empty($missing_sires)) {
            set_transient($lin5_cache_key, ['lookup' => $sire_5y_lookup], DAY_IN_SECONDS);
            return $sire_5y_lookup;
        }

        $sire_names = $missing_sires;
        $sire_placeholders = implode(',', array_fill(0, count($sire_names), '%s'));
        $race_type_filter = bricks_race_detail_lin5_race_type_filter($code);

        $prb_expr = '((rf.field_size - hrunb.finish_position) / (rf.field_size - 1)) * 100';
        $combined_sql = "
            SELECT hrunb.sire_name AS sire_name,
                   SUM(CASE WHEN MONTH(hracb.meeting_date) BETWEEN 3 AND 6 AND hracb.distance_yards BETWEEN 1100 AND 1320 THEN 1 ELSE 0 END) AS lvl1_runs,
                   AVG(CASE WHEN MONTH(hracb.meeting_date) BETWEEN 3 AND 6 AND hracb.distance_yards BETWEEN 1100 AND 1320 THEN $prb_expr ELSE NULL END) AS lvl1_raw_prb,
                   SUM(CASE WHEN MONTH(hracb.meeting_date) BETWEEN 3 AND 6 THEN 1 ELSE 0 END) AS lvl2_runs,
                   AVG(CASE WHEN MONTH(hracb.meeting_date) BETWEEN 3 AND 6 THEN $prb_expr ELSE NULL END) AS lvl2_raw_prb,
                   SUM(CASE WHEN MONTH(hracb.meeting_date) BETWEEN 3 AND 10 THEN 1 ELSE 0 END) AS lvl3_runs,
                   AVG(CASE WHEN MONTH(hracb.meeting_date) BETWEEN 3 AND 10 THEN $prb_expr ELSE NULL END) AS lvl3_raw_prb,
                   COUNT(*) AS lvl4_runs,
                   AVG($prb_expr) AS lvl4_raw_prb
            FROM historic_runners_beta hrunb
            INNER JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
            LEFT JOIN daily_races_beta dracb ON dracb.race_id = hrunb.race_id
            INNER JOIN (
                SELECT race_id, COUNT(*) AS field_size
                FROM historic_runners_beta
                WHERE finish_position IS NOT NULL AND finish_position > 0
                GROUP BY race_id
                HAVING COUNT(*) > 1
            ) rf ON rf.race_id = hrunb.race_id
            WHERE hrunb.sire_name IN ($sire_placeholders)
              AND hrunb.sire_name IS NOT NULL
              AND hrunb.sire_name != ''
              AND hrunb.finish_position IS NOT NULL
              AND hrunb.finish_position > 0
              AND rf.field_size > 1
              AND hracb.meeting_date BETWEEN %s AND %s
              AND $race_type_filter
            GROUP BY hrunb.sire_name";

        $params = array_merge($sire_names, [$from_date, $to_date]);
        $rows = $wpdb->get_results($wpdb->prepare($combined_sql, ...$params));

        $fresh_lookup = bricks_race_detail_lin5_rows_to_lookup($rows, $baseline_prb, $shrink_k, $code);
        foreach ($missing_sires as $sql_sire_name) {
            $norm = function_exists('bricks_normalize_sire_name_key')
                ? bricks_normalize_sire_name_key($sql_sire_name)
                : strtolower($sql_sire_name);
            $entry = null;
            if (isset($fresh_lookup[$sql_sire_name])) {
                $entry = $fresh_lookup[$sql_sire_name];
            } elseif ($norm !== '' && isset($fresh_lookup[$norm])) {
                $entry = $fresh_lookup[$norm];
            } elseif (isset($fresh_lookup[strtolower($sql_sire_name)])) {
                $entry = $fresh_lookup[strtolower($sql_sire_name)];
            }
            if (!is_array($entry)) {
                continue;
            }
            set_transient(
                bricks_race_detail_lin5_sire_transient_key($sql_sire_name, $from_date, $to_date, $code),
                ['entry' => $entry],
                DAY_IN_SECONDS
            );
            bricks_race_detail_lin5_merge_entry($sire_5y_lookup, $sql_sire_name, $entry);
        }

        set_transient($lin5_cache_key, ['lookup' => $sire_5y_lookup], DAY_IN_SECONDS);
        return $sire_5y_lookup;
    }
}

if (!function_exists('bricks_race_detail_enqueue_lazy_charts')) {
    function bricks_race_detail_enqueue_lazy_charts($race_id) {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        add_action('wp_footer', function () use ($race_id) {
            $init_name = 'initRaceDetailCharts_' . intval($race_id);
            ?>
            <script>
            (function () {
                var chartLoaderStarted = false;
                function loadChartLibrary(cb) {
                    if (typeof Chart !== 'undefined') {
                        cb();
                        return;
                    }
                    if (chartLoaderStarted) {
                        var wait = setInterval(function () {
                            if (typeof Chart !== 'undefined') {
                                clearInterval(wait);
                                cb();
                            }
                        }, 50);
                        return;
                    }
                    chartLoaderStarted = true;
                    var s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                    s.async = true;
                    s.onload = cb;
                    document.head.appendChild(s);
                }
                var chartsBooted = false;
                function bootCharts() {
                    if (chartsBooted) {
                        return;
                    }
                    chartsBooted = true;
                    loadChartLibrary(function () {
                        if (typeof window.<?php echo esc_js($init_name); ?> === 'function') {
                            window.<?php echo esc_js($init_name); ?>();
                        }
                    });
                }
                window.bricksBootRaceCharts_<?php echo intval($race_id); ?> = bootCharts;
            })();
            </script>
            <?php
        }, 99);
    }
}

<?php
/**
 * Race detail page performance helpers (caching, lazy-load hooks).
 */

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

if (!function_exists('bricks_race_detail_sire_baseline_prb_cached')) {
    function bricks_race_detail_sire_baseline_prb_cached($from_date, $to_date) {
        global $wpdb;

        $cache_key = 'bricks_sire_baseline_' . md5($from_date . '|' . $to_date);
        $cached = get_transient($cache_key);
        if ($cached !== false && is_numeric($cached)) {
            return floatval($cached);
        }

        $sire_flat_filter = "
              COALESCE(dracb.race_type, hracb.race_type) IS NOT NULL
          AND COALESCE(dracb.race_type, hracb.race_type) != ''
          AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%hurdle%'
          AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%chase%'
          AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%nh%'
          AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%national hunt%'";

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
              AND MONTH(hracb.meeting_date) BETWEEN 3 AND 10
              AND $sire_flat_filter";

        $baseline_prb = $wpdb->get_var($wpdb->prepare($baseline_sql, $from_date, $to_date));
        $baseline_prb = ($baseline_prb !== null) ? floatval($baseline_prb) : 50.0;

        set_transient($cache_key, $baseline_prb, DAY_IN_SECONDS);
        return $baseline_prb;
    }
}

if (!function_exists('bricks_race_detail_lin5_lookup_cached')) {
    /**
     * Sire Lin5 PRB lookup — single combined query instead of four level queries on cache miss.
     *
     * @return array<string, array{runs:int, raw_prb_pct:float, context:string}>
     */
    function bricks_race_detail_lin5_lookup_cached(array $sire_names, $from_date, $to_date, $baseline_prb, $shrink_k = 30.0) {
        global $wpdb;

        $sire_names = array_values(array_unique(array_filter(array_map('strval', $sire_names))));
        if (empty($sire_names)) {
            return [];
        }

        $from_date = (string) $from_date;
        $to_date = (string) $to_date;
        $baseline_prb = floatval($baseline_prb);
        $shrink_k = floatval($shrink_k);

        $lin5_cache_key = 'bricks_lin5_' . md5(wp_json_encode([
            'from' => $from_date,
            'to' => $to_date,
            'sire_names' => $sire_names,
        ]));
        $lin5_cached = get_transient($lin5_cache_key);
        if (is_array($lin5_cached) && isset($lin5_cached['lookup']) && is_array($lin5_cached['lookup'])) {
            return $lin5_cached['lookup'];
        }

        $sire_placeholders = implode(',', array_fill(0, count($sire_names), '%s'));
        $sire_flat_filter = "
              COALESCE(dracb.race_type, hracb.race_type) IS NOT NULL
          AND COALESCE(dracb.race_type, hracb.race_type) != ''
          AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%hurdle%'
          AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%chase%'
          AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%nh%'
          AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%national hunt%'";

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
              AND $sire_flat_filter
            GROUP BY hrunb.sire_name";

        $params = array_merge($sire_names, [$from_date, $to_date]);
        $rows = $wpdb->get_results($wpdb->prepare($combined_sql, ...$params));

        $sire_5y_lookup = [];
        $level_defs = [
            4 => ['runs' => 'lvl4_runs', 'prb' => 'lvl4_raw_prb', 'context' => 'All Flat'],
            3 => ['runs' => 'lvl3_runs', 'prb' => 'lvl3_raw_prb', 'context' => 'Mar-Oct, any dist'],
            2 => ['runs' => 'lvl2_runs', 'prb' => 'lvl2_raw_prb', 'context' => 'Mar-Jun, any dist'],
            1 => ['runs' => 'lvl1_runs', 'prb' => 'lvl1_raw_prb', 'context' => 'Mar-Jun, 5-6f'],
        ];

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $sire_key = trim((string) ($row->sire_name ?? ''));
                if ($sire_key === '') {
                    continue;
                }

                $by_sire = null;
                foreach ($level_defs as $level_num => $def) {
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

                $sire_key_lower = strtolower($sire_key);
                $sire_5y_lookup[$sire_key] = $by_sire;
                $sire_5y_lookup[$sire_key_lower] = $by_sire;
                if (function_exists('bricks_normalize_sire_name_key')) {
                    $sire_key_norm = bricks_normalize_sire_name_key($sire_key);
                    if ($sire_key_norm !== $sire_key_lower) {
                        $sire_5y_lookup[$sire_key_norm] = $by_sire;
                    }
                }
            }
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
                function bootCharts() {
                    loadChartLibrary(function () {
                        if (typeof window.<?php echo esc_js($init_name); ?> === 'function') {
                            window.<?php echo esc_js($init_name); ?>();
                        }
                    });
                }
                var anchor = document.getElementById('race-detail-charts-<?php echo intval($race_id); ?>');
                if (!anchor) {
                    return;
                }
                if ('IntersectionObserver' in window) {
                    var observer = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                bootCharts();
                                observer.disconnect();
                            }
                        });
                    }, { rootMargin: '300px 0px' });
                    observer.observe(anchor);
                } else {
                    bootCharts();
                }
            })();
            </script>
            <?php
        }, 99);
    }
}

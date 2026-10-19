<?php
/**
 * Trainer Cycles (hot vs cold seasonal win-rate deltas).
 *
 * Phase 1: data layer only — no public routes until Phase 2+.
 */

if (!defined('FHOR_TC_MIN_MONTH_RUNNERS')) {
    define('FHOR_TC_MIN_MONTH_RUNNERS', 50);
}
if (!defined('FHOR_TC_MACRO_MIN_LIFETIME_RUNNERS')) {
    define('FHOR_TC_MACRO_MIN_LIFETIME_RUNNERS', 200);
}
if (!defined('FHOR_TC_MACRO_TOP_N')) {
    define('FHOR_TC_MACRO_TOP_N', 48);
}
if (!defined('FHOR_TC_SPOTLIGHT_DELTA_PTS')) {
    define('FHOR_TC_SPOTLIGHT_DELTA_PTS', 3.0);
}
if (!defined('FHOR_TC_SPOTLIGHT_MIN_MONTHS')) {
    define('FHOR_TC_SPOTLIGHT_MIN_MONTHS', 2);
}

if (!function_exists('fhor_tc_config')) {
    /**
     * @return array<string, int|float>
     */
    function fhor_tc_config() {
        return [
            'min_month_runners' => (int) FHOR_TC_MIN_MONTH_RUNNERS,
            'macro_min_lifetime_runners' => (int) FHOR_TC_MACRO_MIN_LIFETIME_RUNNERS,
            'macro_top_n' => (int) FHOR_TC_MACRO_TOP_N,
            'spotlight_delta_pts' => (float) FHOR_TC_SPOTLIGHT_DELTA_PTS,
            'spotlight_min_months' => (int) FHOR_TC_SPOTLIGHT_MIN_MONTHS,
            'cache_ver' => (int) get_option('fhor_tc_cache_ver', 1),
        ];
    }
}

if (!function_exists('fhor_tc_normalize_code')) {
    /**
     * @return string jumps|flat
     */
    function fhor_tc_normalize_code($code) {
        $code = strtolower(trim((string) $code));
        return $code === 'flat' ? 'flat' : 'jumps';
    }
}

if (!function_exists('fhor_tc_tables_ok')) {
    function fhor_tc_tables_ok() {
        if (!function_exists('fhor_sb_table_exists')) {
            return false;
        }
        return fhor_sb_table_exists('historic_runners_beta')
            && fhor_sb_table_exists('historic_races_beta');
    }
}

if (!function_exists('fhor_tc_code_race_types')) {
    /**
     * Race-type tokens for fhor_sb_apply_race_type_where().
     *
     * @return array<int, string>
     */
    function fhor_tc_code_race_types($code) {
        $code = fhor_tc_normalize_code($code);
        if ($code === 'flat') {
            return ['flat'];
        }
        return ['hurdle', 'chase', 'nh flat'];
    }
}

if (!function_exists('fhor_tc_spotlight_month_windows')) {
    /**
     * Calendar month indexes (1–12) per spotlight kind and code.
     *
     * @return array<string, array<int, int>>
     */
    function fhor_tc_spotlight_month_windows($code) {
        $code = fhor_tc_normalize_code($code);
        $early_cold = $code === 'flat' ? [4, 5, 6] : [10, 11, 12];
        return [
            'early_birds' => $early_cold,
            'cold_start' => $early_cold,
            'late_surge' => [11, 12],
        ];
    }
}

if (!function_exists('fhor_tc_sql_from_where')) {
    /**
     * Shared FROM + WHERE fragments for historic trainer aggregates.
     *
     * Returns an empty array when historic tables are missing or code is invalid.
     *
     * @return array{
     *   from: string,
     *   where: array<int, string>,
     *   params: array<int, mixed>,
     *   code: string
     * }|array{}
     */
    function fhor_tc_sql_from_where($code) {
        if (!fhor_tc_tables_ok() || !function_exists('fhor_sb_apply_race_type_where')) {
            return [];
        }

        $code = fhor_tc_normalize_code($code);
        $race_types = fhor_tc_code_race_types($code);
        if ($race_types === []) {
            return [];
        }

        $hr = 'historic_runners_beta';
        $ha = 'historic_races_beta';

        $from = "FROM `$hr` hrunb\n            INNER JOIN `$ha` hracb ON hracb.race_id = hrunb.race_id";
        if (fhor_sb_table_exists('non_runners')) {
            $from .= "\n            LEFT JOIN non_runners nr ON nr.race_id = hrunb.race_id AND nr.runner_id = hrunb.runner_id";
        }

        $where = [
            'hrunb.trainer_name IS NOT NULL',
            "hrunb.trainer_name != ''",
            "hrunb.finish_position REGEXP '^[0-9]+$'",
        ];
        $params = [];

        if (fhor_sb_table_exists('non_runners')) {
            $where[] = 'nr.runner_id IS NULL';
        }

        fhor_sb_apply_race_type_where('hracb.race_type', $race_types, $where, $params);

        if (defined('FHOR_TC_DATE_FROM') && FHOR_TC_DATE_FROM !== '') {
            $where[] = 'hracb.meeting_date >= %s';
            $params[] = (string) FHOR_TC_DATE_FROM;
        }

        return [
            'from' => $from,
            'where' => $where,
            'params' => $params,
            'code' => $code,
        ];
    }
}

if (!function_exists('fhor_tc_sql_wins_sum_expr')) {
    function fhor_tc_sql_wins_sum_expr() {
        return 'SUM(CASE WHEN CAST(hrunb.finish_position AS UNSIGNED) = 1 THEN 1 ELSE 0 END)';
    }
}

if (!function_exists('fhor_tc_strike_pct')) {
    /**
     * Win % for resulted runs (one decimal), or null when runs is zero.
     */
    function fhor_tc_strike_pct($wins, $runs) {
        $runs = (int) $runs;
        $wins = (int) $wins;
        if ($runs <= 0) {
            return null;
        }
        return round(($wins / $runs) * 100, 1);
    }
}

if (!function_exists('fhor_tc_query_macro_baselines')) {
    /**
     * Query A — lifetime baselines for the macro trainer pool.
     *
     * @return array<int, array{trainer_name: string, runs: int, wins: int, baseline_pct: float}>
     */
    function fhor_tc_query_macro_baselines($code) {
        global $wpdb;

        $frag = fhor_tc_sql_from_where($code);
        if ($frag === []) {
            return [];
        }

        $cfg = fhor_tc_config();
        $macro_min = (int) $cfg['macro_min_lifetime_runners'];
        $top_n = (int) $cfg['macro_top_n'];
        if ($top_n <= 0) {
            return [];
        }

        $where_sql = implode("\n              AND ", $frag['where']);
        $wins_sum = fhor_tc_sql_wins_sum_expr();

        $sql = "
            SELECT hrunb.trainer_name AS trainer_name,
                   COUNT(*) AS runs,
                   {$wins_sum} AS wins
            {$frag['from']}
            WHERE {$where_sql}
            GROUP BY hrunb.trainer_name
            HAVING runs >= %d
            ORDER BY runs DESC
            LIMIT %d";

        $params = array_merge($frag['params'], [$macro_min, $top_n]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row->trainer_name ?? ''));
            $runs = (int) ($row->runs ?? 0);
            $wins = (int) ($row->wins ?? 0);
            if ($name === '' || $runs <= 0) {
                continue;
            }
            $baseline = fhor_tc_strike_pct($wins, $runs);
            if ($baseline === null) {
                continue;
            }
            $out[] = [
                'trainer_name' => $name,
                'runs' => $runs,
                'wins' => $wins,
                'baseline_pct' => $baseline,
            ];
        }

        return $out;
    }
}

if (!function_exists('fhor_tc_query_monthly_buckets')) {
    /**
     * Query B — calendar-month buckets (all years pooled) for named trainers.
     *
     * @param array<int, string> $trainer_names
     * @return array<int, array{trainer_name: string, cal_month: int, runs: int, wins: int}>
     */
    function fhor_tc_query_monthly_buckets($code, array $trainer_names) {
        global $wpdb;

        $trainer_names = array_values(array_unique(array_filter(array_map('trim', $trainer_names))));
        if ($trainer_names === []) {
            return [];
        }

        $frag = fhor_tc_sql_from_where($code);
        if ($frag === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($trainer_names), '%s'));
        $where_sql = implode("\n              AND ", $frag['where']);
        $wins_sum = fhor_tc_sql_wins_sum_expr();

        $sql = "
            SELECT hrunb.trainer_name AS trainer_name,
                   MONTH(hracb.meeting_date) AS cal_month,
                   COUNT(*) AS runs,
                   {$wins_sum} AS wins
            {$frag['from']}
            WHERE {$where_sql}
              AND hrunb.trainer_name IN ({$placeholders})
            GROUP BY hrunb.trainer_name, MONTH(hracb.meeting_date)";

        $params = array_merge($frag['params'], $trainer_names);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row->trainer_name ?? ''));
            $cal_month = (int) ($row->cal_month ?? 0);
            if ($name === '' || $cal_month < 1 || $cal_month > 12) {
                continue;
            }
            $out[] = [
                'trainer_name' => $name,
                'cal_month' => $cal_month,
                'runs' => (int) ($row->runs ?? 0),
                'wins' => (int) ($row->wins ?? 0),
            ];
        }

        return $out;
    }
}

if (!function_exists('fhor_tc_group_monthly_buckets')) {
    /**
     * @param array<int, array{trainer_name: string, cal_month: int, runs: int, wins: int}> $bucket_rows
     * @return array<string, array<int, array{runs: int, wins: int}>>
     */
    function fhor_tc_group_monthly_buckets(array $bucket_rows) {
        $grouped = [];
        foreach ($bucket_rows as $row) {
            $name = $row['trainer_name'] ?? '';
            $cal_month = (int) ($row['cal_month'] ?? 0);
            if ($name === '' || $cal_month < 1 || $cal_month > 12) {
                continue;
            }
            if (!isset($grouped[$name])) {
                $grouped[$name] = [];
            }
            $grouped[$name][$cal_month] = [
                'runs' => (int) ($row['runs'] ?? 0),
                'wins' => (int) ($row['wins'] ?? 0),
            ];
        }
        return $grouped;
    }
}

if (!function_exists('fhor_tc_build_twelve_month_rows')) {
    /**
     * Build all 12 calendar months with strike % and eligibility flags.
     *
     * @param array<int, array{runs: int, wins: int}> $buckets_by_cal_month
     * @return array<int, array{cal_month: int, runs: int, wins: int, strike_pct: float|null, eligible: bool}>
     */
    function fhor_tc_build_twelve_month_rows(array $buckets_by_cal_month, $min_month_runners = null) {
        if ($min_month_runners === null) {
            $min_month_runners = (int) fhor_tc_config()['min_month_runners'];
        } else {
            $min_month_runners = (int) $min_month_runners;
        }

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $bucket = $buckets_by_cal_month[$m] ?? ['runs' => 0, 'wins' => 0];
            $runs = (int) ($bucket['runs'] ?? 0);
            $wins = (int) ($bucket['wins'] ?? 0);
            $months[] = [
                'cal_month' => $m,
                'runs' => $runs,
                'wins' => $wins,
                'strike_pct' => fhor_tc_strike_pct($wins, $runs),
                'eligible' => $runs >= $min_month_runners,
            ];
        }

        return $months;
    }
}

if (!function_exists('fhor_tc_merge_trainer_monthly')) {
    /**
     * Attach Query B monthly rows to a Query A baseline row.
     *
     * @param array{trainer_name: string, runs: int, wins: int, baseline_pct: float} $baseline
     * @param array<int, array{runs: int, wins: int}> $buckets_by_cal_month
     * @return array{
     *   trainer_name: string,
     *   runs: int,
     *   wins: int,
     *   baseline_pct: float,
     *   months: array<int, array{cal_month: int, runs: int, wins: int, strike_pct: float|null, eligible: bool}>
     * }
     */
    function fhor_tc_merge_trainer_monthly(array $baseline, array $buckets_by_cal_month, $min_month_runners = null) {
        return [
            'trainer_name' => (string) $baseline['trainer_name'],
            'runs' => (int) $baseline['runs'],
            'wins' => (int) $baseline['wins'],
            'baseline_pct' => (float) $baseline['baseline_pct'],
            'months' => fhor_tc_build_twelve_month_rows($buckets_by_cal_month, $min_month_runners),
        ];
    }
}

if (!function_exists('fhor_tc_query_macro_trainers_with_months')) {
    /**
     * Query A + Query B + 12-month merge for the macro pool (no deltas/cache yet).
     *
     * @return array<int, array{
     *   trainer_name: string,
     *   runs: int,
     *   wins: int,
     *   baseline_pct: float,
     *   months: array<int, array{cal_month: int, runs: int, wins: int, strike_pct: float|null, eligible: bool}>
     * }>
     */
    function fhor_tc_query_macro_trainers_with_months($code) {
        $baselines = fhor_tc_query_macro_baselines($code);
        if ($baselines === []) {
            return [];
        }

        $names = array_map(static function ($row) {
            return $row['trainer_name'];
        }, $baselines);

        $bucket_rows = fhor_tc_query_monthly_buckets($code, $names);
        $grouped = fhor_tc_group_monthly_buckets($bucket_rows);

        $merged = [];
        foreach ($baselines as $baseline) {
            $name = $baseline['trainer_name'];
            $merged[] = fhor_tc_merge_trainer_monthly($baseline, $grouped[$name] ?? []);
        }

        return $merged;
    }
}

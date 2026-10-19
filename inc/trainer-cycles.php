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
if (!defined('FHOR_TC_MACRO_CACHE_TTL')) {
    define('FHOR_TC_MACRO_CACHE_TTL', 6 * HOUR_IN_SECONDS);
}
if (!defined('FHOR_TC_PROFILE_CACHE_TTL')) {
    define('FHOR_TC_PROFILE_CACHE_TTL', 6 * HOUR_IN_SECONDS);
}
if (!defined('FHOR_TC_SLUG_INDEX_MIN_RUNS')) {
    define('FHOR_TC_SLUG_INDEX_MIN_RUNS', 50);
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

if (!function_exists('fhor_tc_query_trainer_baseline')) {
    /**
     * Lifetime baseline for one trainer (no macro-pool HAVING/LIMIT).
     *
     * @return array{trainer_name: string, runs: int, wins: int, baseline_pct: float}|null
     */
    function fhor_tc_query_trainer_baseline($code, $trainer_name) {
        global $wpdb;

        $name = trim((string) $trainer_name);
        if ($name === '') {
            return null;
        }

        $frag = fhor_tc_sql_from_where($code);
        if ($frag === []) {
            return null;
        }

        $where = $frag['where'];
        $where[] = 'hrunb.trainer_name = %s';
        $where_sql = implode("\n              AND ", $where);
        $wins_sum = fhor_tc_sql_wins_sum_expr();

        $sql = "
            SELECT hrunb.trainer_name AS trainer_name,
                   COUNT(*) AS runs,
                   {$wins_sum} AS wins
            {$frag['from']}
            WHERE {$where_sql}
            GROUP BY hrunb.trainer_name";

        $params = array_merge($frag['params'], [$name]);
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$params));
        if (!$row) {
            return null;
        }

        $resolved = trim((string) ($row->trainer_name ?? ''));
        $runs = (int) ($row->runs ?? 0);
        $wins = (int) ($row->wins ?? 0);
        if ($resolved === '' || $runs <= 0) {
            return null;
        }

        $baseline = fhor_tc_strike_pct($wins, $runs);
        if ($baseline === null) {
            return null;
        }

        return [
            'trainer_name' => $resolved,
            'runs' => $runs,
            'wins' => $wins,
            'baseline_pct' => $baseline,
        ];
    }
}

if (!function_exists('fhor_tc_query_trainer_with_months')) {
    /**
     * Baseline + monthly merge for a single trainer.
     *
     * @return array{
     *   trainer_name: string,
     *   runs: int,
     *   wins: int,
     *   baseline_pct: float,
     *   months: array<int, array{cal_month: int, runs: int, wins: int, strike_pct: float|null, eligible: bool}>
     * }|null
     */
    function fhor_tc_query_trainer_with_months($code, $trainer_name) {
        $baseline = fhor_tc_query_trainer_baseline($code, $trainer_name);
        if ($baseline === null) {
            return null;
        }

        $bucket_rows = fhor_tc_query_monthly_buckets($code, [$baseline['trainer_name']]);
        $grouped = fhor_tc_group_monthly_buckets($bucket_rows);

        return fhor_tc_merge_trainer_monthly($baseline, $grouped[$baseline['trainer_name']] ?? []);
    }
}

if (!function_exists('fhor_tc_reference_cal_month')) {
    /**
     * Calendar month (1–12) for “current month” metrics; override in tests.
     */
    function fhor_tc_reference_cal_month($month = null) {
        if ($month !== null) {
            $month = (int) $month;
            if ($month >= 1 && $month <= 12) {
                return $month;
            }
        }
        if (function_exists('current_time')) {
            return (int) current_time('n');
        }
        return (int) gmdate('n');
    }
}

if (!function_exists('fhor_tc_delta_pts')) {
    /**
     * Month win % minus lifetime baseline (percentage points, one decimal).
     */
    function fhor_tc_delta_pts($strike_pct, $baseline_pct) {
        if ($strike_pct === null) {
            return null;
        }
        return round(((float) $strike_pct) - ((float) $baseline_pct), 1);
    }
}

if (!function_exists('fhor_tc_months_with_deltas')) {
    /**
     * @param array<int, array{cal_month: int, runs: int, wins: int, strike_pct: float|null, eligible: bool}> $months
     * @return array<int, array{cal_month: int, runs: int, wins: int, strike_pct: float|null, eligible: bool, delta: float|null}>
     */
    function fhor_tc_months_with_deltas(array $months, $baseline_pct) {
        $out = [];
        foreach ($months as $row) {
            $eligible = !empty($row['eligible']);
            $strike = $row['strike_pct'] ?? null;
            $delta = null;
            if ($eligible && $strike !== null) {
                $delta = fhor_tc_delta_pts($strike, $baseline_pct);
            }
            $out[] = [
                'cal_month' => (int) ($row['cal_month'] ?? 0),
                'runs' => (int) ($row['runs'] ?? 0),
                'wins' => (int) ($row['wins'] ?? 0),
                'strike_pct' => $strike === null ? null : (float) $strike,
                'eligible' => $eligible,
                'delta' => $delta,
            ];
        }
        return $out;
    }
}

if (!function_exists('fhor_tc_volatility_gap_pts')) {
    /**
     * Eligible months only: max strike % − min strike % (one decimal).
     */
    function fhor_tc_volatility_gap_pts(array $months) {
        $strikes = [];
        foreach ($months as $row) {
            if (empty($row['eligible'])) {
                continue;
            }
            $strike = $row['strike_pct'] ?? null;
            if ($strike === null) {
                continue;
            }
            $strikes[] = (float) $strike;
        }
        if ($strikes === []) {
            return null;
        }
        return round(max($strikes) - min($strikes), 1);
    }
}

if (!function_exists('fhor_tc_best_worst_eligible_months')) {
    /**
     * Best / worst eligible months by delta vs baseline.
     *
     * @param array<int, array{cal_month: int, eligible: bool, strike_pct: float|null, delta?: float|null}> $months
     * @return array{
     *   best: array{cal_month: int, delta: float, strike_pct: float}|null,
     *   worst: array{cal_month: int, delta: float, strike_pct: float}|null
     * }
     */
    function fhor_tc_best_worst_eligible_months(array $months, $baseline_pct) {
        $best = null;
        $worst = null;

        foreach ($months as $row) {
            if (empty($row['eligible'])) {
                continue;
            }
            $strike = $row['strike_pct'] ?? null;
            if ($strike === null) {
                continue;
            }
            $delta = array_key_exists('delta', $row)
                ? $row['delta']
                : fhor_tc_delta_pts($strike, $baseline_pct);
            if ($delta === null) {
                continue;
            }
            $delta = (float) $delta;
            $cal_month = (int) ($row['cal_month'] ?? 0);
            if ($cal_month < 1 || $cal_month > 12) {
                continue;
            }

            $candidate = [
                'cal_month' => $cal_month,
                'delta' => $delta,
                'strike_pct' => (float) $strike,
            ];

            if ($best === null || $delta > $best['delta']) {
                $best = $candidate;
            }
            if ($worst === null || $delta < $worst['delta']) {
                $worst = $candidate;
            }
        }

        return [
            'best' => $best,
            'worst' => $worst,
        ];
    }
}

if (!function_exists('fhor_tc_current_month_delta')) {
    /**
     * Delta for the reference calendar month when that month is eligible.
     *
     * @param array<int, array{cal_month: int, eligible: bool, delta?: float|null}> $months
     */
    function fhor_tc_current_month_delta(array $months, $reference_month = null) {
        $ref = fhor_tc_reference_cal_month($reference_month);
        foreach ($months as $row) {
            if ((int) ($row['cal_month'] ?? 0) !== $ref) {
                continue;
            }
            if (empty($row['eligible'])) {
                return null;
            }
            return array_key_exists('delta', $row) ? $row['delta'] : null;
        }
        return null;
    }
}

if (!function_exists('fhor_tc_spotlight_window_qualifies')) {
    /**
     * @param array<int, array{cal_month: int, eligible: bool, strike_pct: float|null, delta?: float|null}> $months
     * @param array<int, int> $window_months
     * @param 'hot'|'cold' $direction
     */
    function fhor_tc_spotlight_window_qualifies(array $months, array $window_months, $baseline_pct, $direction) {
        $cfg = fhor_tc_config();
        $threshold = (float) $cfg['spotlight_delta_pts'];
        $min_months = (int) $cfg['spotlight_min_months'];
        if ($min_months <= 0 || $window_months === []) {
            return false;
        }

        $window_lookup = array_fill_keys(array_map('intval', $window_months), true);
        $deltas = [];

        foreach ($months as $row) {
            if (empty($row['eligible'])) {
                continue;
            }
            $cal_month = (int) ($row['cal_month'] ?? 0);
            if (!isset($window_lookup[$cal_month])) {
                continue;
            }
            if (array_key_exists('delta', $row) && $row['delta'] !== null) {
                $deltas[] = (float) $row['delta'];
                continue;
            }
            $strike = $row['strike_pct'] ?? null;
            if ($strike === null) {
                continue;
            }
            $deltas[] = (float) fhor_tc_delta_pts($strike, $baseline_pct);
        }

        if (count($deltas) < $min_months) {
            return false;
        }

        $avg = round(array_sum($deltas) / count($deltas), 1);
        if ($direction === 'hot') {
            return $avg >= $threshold;
        }
        return $avg <= -$threshold;
    }
}

if (!function_exists('fhor_tc_trainer_spotlights')) {
    /**
     * @param array<int, array{cal_month: int, eligible: bool, strike_pct: float|null, delta?: float|null}> $months
     * @return array{early_birds: bool, cold_start: bool, late_surge: bool}
     */
    function fhor_tc_trainer_spotlights($code, array $months, $baseline_pct) {
        $windows = fhor_tc_spotlight_month_windows($code);
        return [
            'early_birds' => fhor_tc_spotlight_window_qualifies(
                $months,
                $windows['early_birds'] ?? [],
                $baseline_pct,
                'hot'
            ),
            'cold_start' => fhor_tc_spotlight_window_qualifies(
                $months,
                $windows['cold_start'] ?? [],
                $baseline_pct,
                'cold'
            ),
            'late_surge' => fhor_tc_spotlight_window_qualifies(
                $months,
                $windows['late_surge'] ?? [],
                $baseline_pct,
                'hot'
            ),
        ];
    }
}

if (!function_exists('fhor_tc_apply_trainer_metrics')) {
    /**
     * Phase 1.3 — deltas, best/worst, volatility, spotlight flags on a merged trainer row.
     *
     * @param array{
     *   trainer_name: string,
     *   runs: int,
     *   wins: int,
     *   baseline_pct: float,
     *   months: array<int, array{cal_month: int, runs: int, wins: int, strike_pct: float|null, eligible: bool}>
     * } $trainer
     * @return array{
     *   trainer_name: string,
     *   runs: int,
     *   wins: int,
     *   baseline_pct: float,
     *   months: array<int, array{cal_month: int, runs: int, wins: int, strike_pct: float|null, eligible: bool, delta: float|null}>,
     *   current_month_delta: float|null,
     *   best_month: array{cal_month: int, delta: float, strike_pct: float}|null,
     *   worst_month: array{cal_month: int, delta: float, strike_pct: float}|null,
     *   gap_pts: float|null,
     *   spotlights: array{early_birds: bool, cold_start: bool, late_surge: bool}
     * }
     */
    function fhor_tc_apply_trainer_metrics(array $trainer, $code, $reference_month = null) {
        $baseline_pct = (float) ($trainer['baseline_pct'] ?? 0);
        $months = fhor_tc_months_with_deltas($trainer['months'] ?? [], $baseline_pct);
        $extremes = fhor_tc_best_worst_eligible_months($months, $baseline_pct);

        return [
            'trainer_name' => (string) ($trainer['trainer_name'] ?? ''),
            'runs' => (int) ($trainer['runs'] ?? 0),
            'wins' => (int) ($trainer['wins'] ?? 0),
            'baseline_pct' => $baseline_pct,
            'months' => $months,
            'current_month_delta' => fhor_tc_current_month_delta($months, $reference_month),
            'best_month' => $extremes['best'],
            'worst_month' => $extremes['worst'],
            'gap_pts' => fhor_tc_volatility_gap_pts($months),
            'spotlights' => fhor_tc_trainer_spotlights($code, $months, $baseline_pct),
        ];
    }
}

if (!function_exists('fhor_tc_apply_metrics_to_trainers')) {
    /**
     * @param array<int, array{trainer_name: string, runs: int, wins: int, baseline_pct: float, months: array}> $trainers
     * @return array<int, array>
     */
    function fhor_tc_apply_metrics_to_trainers(array $trainers, $code, $reference_month = null) {
        $out = [];
        foreach ($trainers as $trainer) {
            if (!is_array($trainer)) {
                continue;
            }
            $out[] = fhor_tc_apply_trainer_metrics($trainer, $code, $reference_month);
        }
        return $out;
    }
}

if (!function_exists('fhor_tc_cache_key_parts')) {
    /**
     * Shared tunables embedded in transient keys (plus optional date floor).
     *
     * @return array<int, int|float|string>
     */
    function fhor_tc_cache_key_parts($reference_month = null) {
        $cfg = fhor_tc_config();
        $ref = $reference_month !== null
            ? (int) $reference_month
            : fhor_tc_reference_cal_month();

        $parts = [
            $ref,
            (int) $cfg['min_month_runners'],
            (int) $cfg['macro_min_lifetime_runners'],
            (int) $cfg['macro_top_n'],
            (float) $cfg['spotlight_delta_pts'],
            (int) $cfg['spotlight_min_months'],
            (int) $cfg['cache_ver'],
        ];

        if (defined('FHOR_TC_DATE_FROM') && FHOR_TC_DATE_FROM !== '') {
            $parts[] = (string) FHOR_TC_DATE_FROM;
        }

        return $parts;
    }
}

if (!function_exists('fhor_tc_transient_key')) {
    function fhor_tc_transient_key(array $parts) {
        if (function_exists('bricks_cache_key')) {
            return bricks_cache_key('trainer_cycles', $parts);
        }
        return 'fhor_tc_' . md5(implode('|', array_map('strval', $parts)));
    }
}

if (!function_exists('fhor_tc_macro_cache_key')) {
    function fhor_tc_macro_cache_key($code, $reference_month = null) {
        $parts = array_merge(
            ['macro', fhor_tc_normalize_code($code)],
            fhor_tc_cache_key_parts($reference_month)
        );
        return fhor_tc_transient_key($parts);
    }
}

if (!function_exists('fhor_tc_profile_cache_key')) {
    function fhor_tc_profile_cache_key($trainer_name, $code, $reference_month = null) {
        $name = strtolower(trim((string) $trainer_name));
        $parts = array_merge(
            ['profile', $name, fhor_tc_normalize_code($code)],
            fhor_tc_cache_key_parts($reference_month)
        );
        return fhor_tc_transient_key($parts);
    }
}

if (!function_exists('fhor_tc_profile_cache_get')) {
    /**
     * @return array<string, mixed>|null
     */
    function fhor_tc_profile_cache_get($trainer_name, $code, $reference_month = null) {
        if (!function_exists('get_transient')) {
            return null;
        }
        $cached = get_transient(fhor_tc_profile_cache_key($trainer_name, $code, $reference_month));
        return is_array($cached) ? $cached : null;
    }
}

if (!function_exists('fhor_tc_profile_cache_set')) {
    /**
     * @param array<string, mixed> $payload
     */
    function fhor_tc_profile_cache_set($trainer_name, $code, array $payload, $reference_month = null) {
        if (!function_exists('set_transient')) {
            return;
        }
        set_transient(
            fhor_tc_profile_cache_key($trainer_name, $code, $reference_month),
            $payload,
            (int) FHOR_TC_PROFILE_CACHE_TTL
        );
    }
}

if (!function_exists('fhor_tc_bump_cache_ver')) {
    function fhor_tc_bump_cache_ver() {
        $next = (int) get_option('fhor_tc_cache_ver', 1) + 1;
        update_option('fhor_tc_cache_ver', $next, false);
        return $next;
    }
}

if (!function_exists('fhor_tc_trainer_slug')) {
    function fhor_tc_trainer_slug($trainer_name) {
        $name = trim((string) $trainer_name);
        if ($name === '') {
            return '';
        }
        if (function_exists('sanitize_title')) {
            return sanitize_title($name);
        }
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    }
}

if (!function_exists('fhor_tc_trainer_url')) {
    function fhor_tc_trainer_url($trainer_name, $code = null) {
        $slug = fhor_tc_trainer_slug($trainer_name);
        if ($slug === '') {
            return '';
        }
        $path = '/trainers/' . $slug . '/';
        if (!function_exists('home_url')) {
            return $path;
        }
        $url = home_url($path);
        if ($code !== null && fhor_tc_normalize_code($code) === 'flat') {
            $url = add_query_arg('code', 'flat', $url);
        }
        return $url;
    }
}

if (!function_exists('fhor_tc_slug_index_cache_key')) {
    function fhor_tc_slug_index_cache_key() {
        $parts = ['slug_index', (int) fhor_tc_config()['cache_ver']];
        if (defined('FHOR_TC_DATE_FROM') && FHOR_TC_DATE_FROM !== '') {
            $parts[] = (string) FHOR_TC_DATE_FROM;
        }
        $parts[] = (int) FHOR_TC_SLUG_INDEX_MIN_RUNS;
        return fhor_tc_transient_key($parts);
    }
}

if (!function_exists('fhor_tc_build_trainer_slug_index')) {
    /**
     * slug => canonical trainer_name (highest lifetime runs wins slug collisions).
     *
     * @return array<string, string>
     */
    function fhor_tc_build_trainer_slug_index() {
        global $wpdb;

        if (!fhor_tc_tables_ok()) {
            return [];
        }

        $min_runs = (int) FHOR_TC_SLUG_INDEX_MIN_RUNS;
        if ($min_runs <= 0) {
            return [];
        }

        $runs_by_name = [];
        foreach (['jumps', 'flat'] as $code) {
            $frag = fhor_tc_sql_from_where($code);
            if ($frag === []) {
                continue;
            }
            $where_sql = implode("\n              AND ", $frag['where']);
            $sql = "
                SELECT hrunb.trainer_name AS trainer_name,
                       COUNT(*) AS runs
                {$frag['from']}
                WHERE {$where_sql}
                GROUP BY hrunb.trainer_name
                HAVING runs >= %d";
            $params = array_merge($frag['params'], [$min_runs]);
            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $name = trim((string) ($row->trainer_name ?? ''));
                $runs = (int) ($row->runs ?? 0);
                if ($name === '' || $runs <= 0) {
                    continue;
                }
                if (!isset($runs_by_name[$name]) || $runs > $runs_by_name[$name]) {
                    $runs_by_name[$name] = $runs;
                }
            }
        }

        $index = [];
        foreach ($runs_by_name as $name => $runs) {
            $slug = fhor_tc_trainer_slug($name);
            if ($slug === '') {
                continue;
            }
            if (!isset($index[$slug])) {
                $index[$slug] = $name;
                continue;
            }
            $existing = $index[$slug];
            $existing_runs = (int) ($runs_by_name[$existing] ?? 0);
            if ($runs_by_name[$name] > $existing_runs) {
                $index[$slug] = $name;
            }
        }

        return $index;
    }
}

if (!function_exists('fhor_tc_get_trainer_slug_index')) {
    /**
     * @return array<string, string>
     */
    function fhor_tc_get_trainer_slug_index($force_refresh = false) {
        if (!$force_refresh && function_exists('get_transient')) {
            $cached = get_transient(fhor_tc_slug_index_cache_key());
            if (is_array($cached)) {
                return $cached;
            }
        }

        $index = fhor_tc_build_trainer_slug_index();
        if (function_exists('set_transient')) {
            set_transient(fhor_tc_slug_index_cache_key(), $index, (int) FHOR_TC_MACRO_CACHE_TTL);
        }
        return $index;
    }
}

if (!function_exists('fhor_tc_resolve_trainer_from_slug')) {
    /**
     * Resolve a public slug back to the historic trainer_name string.
     */
    function fhor_tc_resolve_trainer_from_slug($slug) {
        $slug = function_exists('sanitize_title')
            ? sanitize_title((string) $slug)
            : strtolower(trim((string) $slug));
        if ($slug === '') {
            return null;
        }

        $index = fhor_tc_get_trainer_slug_index();
        if (isset($index[$slug])) {
            return $index[$slug];
        }

        return null;
    }
}

if (!function_exists('fhor_tc_build_macro_trainers_with_metrics')) {
    /**
     * Query A + B merge + Phase 1.3 metrics (uncached).
     *
     * @return array<int, array>
     */
    function fhor_tc_build_macro_trainers_with_metrics($code, $reference_month = null) {
        $merged = fhor_tc_query_macro_trainers_with_months($code);
        return fhor_tc_apply_metrics_to_trainers($merged, $code, $reference_month);
    }
}

if (!function_exists('fhor_tc_query_macro_trainers_with_metrics')) {
    /**
     * Macro pool with metrics; served from transients when available.
     *
     * @return array<int, array>
     */
    function fhor_tc_query_macro_trainers_with_metrics($code, $reference_month = null) {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            return fhor_tc_build_macro_trainers_with_metrics($code, $reference_month);
        }

        $cache_key = fhor_tc_macro_cache_key($code, $reference_month);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $data = fhor_tc_build_macro_trainers_with_metrics($code, $reference_month);
        set_transient($cache_key, $data, (int) FHOR_TC_MACRO_CACHE_TTL);
        return $data;
    }
}

if (!function_exists('fhor_tc_elapsed_ms')) {
    function fhor_tc_elapsed_ms($started_at) {
        return round((microtime(true) - (float) $started_at) * 1000, 1);
    }
}

if (!function_exists('fhor_tc_response_meta')) {
    /**
     * @return array{cache: string, ms: float}
     */
    function fhor_tc_response_meta($started_at, $cache_status) {
        return [
            'cache' => (string) $cache_status,
            'ms' => fhor_tc_elapsed_ms($started_at),
        ];
    }
}

if (!function_exists('fhor_tc_trainer_public_fields')) {
    /**
     * Trainer metrics row plus slug/url for JSON consumers.
     *
     * @param array<string, mixed> $trainer
     * @return array<string, mixed>
     */
    function fhor_tc_trainer_public_fields(array $trainer, $code, $reference_month = null) {
        $name = (string) ($trainer['trainer_name'] ?? '');
        $code = fhor_tc_normalize_code($code);

        return array_merge($trainer, [
            'code' => $code,
            'reference_month' => fhor_tc_reference_cal_month($reference_month),
            'slug' => fhor_tc_trainer_slug($name),
            'url' => fhor_tc_trainer_url($name, $code),
        ]);
    }
}

if (!function_exists('fhor_tc_build_profile_data')) {
    /**
     * Uncached profile payload (no ok/meta wrapper).
     *
     * @return array<string, mixed>|null
     */
    function fhor_tc_build_profile_data($trainer_name, $code, $reference_month = null) {
        if (!fhor_tc_tables_ok()) {
            return null;
        }

        $merged = fhor_tc_query_trainer_with_months($code, $trainer_name);
        if ($merged === null) {
            return null;
        }

        $trainer = fhor_tc_apply_trainer_metrics($merged, $code, $reference_month);

        return fhor_tc_trainer_public_fields($trainer, $code, $reference_month);
    }
}

if (!function_exists('fhor_tc_get_profile_data')) {
    /**
     * Single-trainer seasonal cycle payload for profile AJAX / SSR bootstrap.
     *
     * @return array<string, mixed>
     */
    function fhor_tc_get_profile_data($trainer_name, $code, $reference_month = null) {
        $started = microtime(true);
        $code = fhor_tc_normalize_code($code);
        $ref = fhor_tc_reference_cal_month($reference_month);
        $name = trim((string) $trainer_name);

        if ($name === '') {
            return [
                'ok' => false,
                'error' => 'invalid_trainer',
                'code' => $code,
                'reference_month' => $ref,
                'meta' => fhor_tc_response_meta($started, 'bypass'),
            ];
        }

        if (!fhor_tc_tables_ok()) {
            return [
                'ok' => false,
                'error' => 'unavailable',
                'code' => $code,
                'reference_month' => $ref,
                'meta' => fhor_tc_response_meta($started, 'bypass'),
            ];
        }

        $cache_status = 'miss';
        $body = null;

        if (function_exists('get_transient')) {
            $body = fhor_tc_profile_cache_get($name, $code, $reference_month);
            if (is_array($body)) {
                $cache_status = 'hit';
            }
        } else {
            $cache_status = 'bypass';
        }

        if (!is_array($body)) {
            $body = fhor_tc_build_profile_data($name, $code, $reference_month);
            if ($body === null) {
                return [
                    'ok' => false,
                    'error' => 'not_found',
                    'code' => $code,
                    'reference_month' => $ref,
                    'meta' => fhor_tc_response_meta($started, $cache_status),
                ];
            }
            fhor_tc_profile_cache_set($name, $code, $body, $reference_month);
        }

        return array_merge(['ok' => true], $body, [
            'meta' => fhor_tc_response_meta($started, $cache_status),
        ]);
    }
}

if (!function_exists('fhor_tc_get_macro_pool')) {
    /**
     * Macro trainer pool with metrics for dashboard SSR / AJAX.
     *
     * @return array<string, mixed>
     */
    function fhor_tc_get_macro_pool($code, $reference_month = null) {
        $started = microtime(true);
        $code = fhor_tc_normalize_code($code);
        $ref = fhor_tc_reference_cal_month($reference_month);

        if (!fhor_tc_tables_ok()) {
            return [
                'ok' => false,
                'error' => 'unavailable',
                'code' => $code,
                'reference_month' => $ref,
                'trainers' => [],
                'meta' => array_merge(
                    fhor_tc_response_meta($started, 'bypass'),
                    ['count' => 0]
                ),
            ];
        }

        $cache_status = function_exists('get_transient') ? 'miss' : 'bypass';
        $trainers = null;

        if (function_exists('get_transient')) {
            $cached = get_transient(fhor_tc_macro_cache_key($code, $reference_month));
            if (is_array($cached)) {
                $cache_status = 'hit';
                $trainers = $cached;
            }
        }

        if (!is_array($trainers)) {
            $trainers = fhor_tc_query_macro_trainers_with_metrics($code, $reference_month);
        }

        return [
            'ok' => true,
            'code' => $code,
            'reference_month' => $ref,
            'trainers' => $trainers,
            'meta' => array_merge(
                fhor_tc_response_meta($started, $cache_status),
                ['count' => count($trainers)]
            ),
        ];
    }
}

if (!function_exists('fhor_tc_is_trainer_profile_request')) {
    function fhor_tc_is_trainer_profile_request() {
        $slug = get_query_var('fhor_trainer_slug');
        if (is_string($slug) && $slug !== '') {
            return true;
        }
        $path = (string) (wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
        return (bool) preg_match('#/trainers/[^/]+/?$#i', $path);
    }
}

if (!function_exists('fhor_tc_profile_request_slug')) {
    function fhor_tc_profile_request_slug() {
        $slug = get_query_var('fhor_trainer_slug');
        if (is_string($slug) && $slug !== '') {
            return sanitize_title($slug);
        }
        $path = (string) (wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
        if (preg_match('#/trainers/([^/]+)/?$#i', $path, $matches)) {
            return sanitize_title($matches[1]);
        }
        return '';
    }
}

if (!function_exists('fhor_tc_profile_request_code')) {
    /**
     * Jumps default; flat when ?code=flat.
     */
    function fhor_tc_profile_request_code() {
        $raw = isset($_GET['code']) ? (string) wp_unslash($_GET['code']) : '';
        return fhor_tc_normalize_code($raw !== '' ? $raw : 'jumps');
    }
}

if (!function_exists('fhor_tc_profile_request_context')) {
    /**
     * Populated during template_redirect for the profile route.
     *
     * @return array{slug: string, trainer_name: string, code: string}|null
     */
    function fhor_tc_profile_request_context() {
        static $ctx = null;
        static $loaded = false;
        if ($loaded) {
            return $ctx;
        }
        $loaded = true;
        if (!fhor_tc_is_trainer_profile_request()) {
            return null;
        }
        $slug = fhor_tc_profile_request_slug();
        if ($slug === '') {
            return null;
        }
        $name = fhor_tc_resolve_trainer_from_slug($slug);
        if ($name === null || $name === '') {
            return null;
        }
        $ctx = [
            'slug' => $slug,
            'trainer_name' => $name,
            'code' => fhor_tc_profile_request_code(),
        ];
        return $ctx;
    }
}

if (!function_exists('fhor_tc_trainer_cycles_profile_shortcode')) {
    /**
     * Phase 2.2 replaces this stub with the full SSR shell.
     */
    function fhor_tc_trainer_cycles_profile_shortcode($atts = []) {
        $ctx = fhor_tc_profile_request_context();
        if ($ctx === null) {
            return '';
        }
        ob_start();
        ?>
        <div
            class="fhor-tc-profile fhor-tc-profile--routing-stub"
            data-trainer-slug="<?php echo esc_attr($ctx['slug']); ?>"
            data-code="<?php echo esc_attr($ctx['code']); ?>"
        >
            <p class="fhor-tc-profile__title"><?php echo esc_html($ctx['trainer_name']); ?></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
add_shortcode('trainer_cycles_profile', 'fhor_tc_trainer_cycles_profile_shortcode');

if (!function_exists('fhor_tc_add_rewrite')) {
    function fhor_tc_add_rewrite() {
        add_rewrite_tag('%fhor_trainer_slug%', '([^/]+)');
        add_rewrite_rule(
            '^trainers/([^/]+)/?$',
            'index.php?fhor_trainer_slug=$matches[1]',
            'top'
        );
    }
}
add_action('init', 'fhor_tc_add_rewrite', 20);

if (!function_exists('fhor_tc_query_vars')) {
    function fhor_tc_query_vars($vars) {
        $vars[] = 'fhor_trainer_slug';
        return $vars;
    }
}
add_filter('query_vars', 'fhor_tc_query_vars');

if (!function_exists('fhor_tc_template_redirect')) {
    function fhor_tc_template_redirect() {
        if (is_admin() || !fhor_tc_is_trainer_profile_request()) {
            return;
        }

        $slug = fhor_tc_profile_request_slug();
        if ($slug === '') {
            return;
        }

        $trainer_name = fhor_tc_resolve_trainer_from_slug($slug);
        if ($trainer_name === null || $trainer_name === '') {
            status_header(404);
            nocache_headers();
            $GLOBALS['wp_query']->set_404();
            return;
        }

        status_header(200);
        nocache_headers();

        ob_start();
        get_header();
        $header = ob_get_clean();

        ob_start();
        get_footer();
        $footer = ob_get_clean();

        echo $header;
        echo '<main class="main-content fhor-tc-profile-page"><div class="content-container">';
        echo do_shortcode('[trainer_cycles_profile]');
        echo '</div></main>';
        echo $footer;
        exit;
    }
}
add_action('template_redirect', 'fhor_tc_template_redirect', 2);

if (!function_exists('fhor_tc_flush_rewrites')) {
    function fhor_tc_flush_rewrites() {
        if (get_option('fhor_tc_rewrite_flushed') !== '1') {
            flush_rewrite_rules(false);
            update_option('fhor_tc_rewrite_flushed', '1', false);
        }
    }
}
add_action('init', 'fhor_tc_flush_rewrites', 999);

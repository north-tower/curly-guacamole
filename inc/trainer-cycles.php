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

if (!function_exists('fhor_tc_summary_yards_below_baseline')) {
    /**
     * Macro pool trainers whose lifetime baseline sits below a monthly strike rate.
     *
     * @param array<int, array<string, mixed>> $pool_trainers
     */
    function fhor_tc_summary_yards_below_baseline(array $pool_trainers, $strike_pct, $exclude_name = '') {
        $exclude = strtolower(trim((string) $exclude_name));
        $strike = (float) $strike_pct;
        $count = 0;

        foreach ($pool_trainers as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = strtolower(trim((string) ($row['trainer_name'] ?? '')));
            if ($name === '' || ($exclude !== '' && $name === $exclude)) {
                continue;
            }
            $baseline = $row['baseline_pct'] ?? null;
            if ($baseline === null) {
                continue;
            }
            if ((float) $baseline < $strike) {
                $count++;
            }
        }

        return $count;
    }
}

if (!function_exists('fhor_tc_build_summary')) {
    /**
     * Automated one-line profile summary (Phase 2.5).
     *
     * @param array<string, mixed> $trainer_row Metrics row (best/worst/gap_pts, etc.)
     * @param array<int, array<string, mixed>> $pool_trainers Macro pool rows for the same code
     * @return array{kind: string, text: string}
     */
    function fhor_tc_build_summary(array $trainer_row, array $pool_trainers) {
        $name = trim((string) ($trainer_row['trainer_name'] ?? ''));
        if ($name === '') {
            return [
                'kind' => 'none',
                'text' => '',
            ];
        }

        $cfg = fhor_tc_config();
        $spotlight = (float) $cfg['spotlight_delta_pts'];
        $best = is_array($trainer_row['best_month'] ?? null) ? $trainer_row['best_month'] : null;
        $worst = is_array($trainer_row['worst_month'] ?? null) ? $trainer_row['worst_month'] : null;
        $gap_pts = $trainer_row['gap_pts'] ?? null;

        $candidates = [];

        if ($best !== null) {
            $delta = (float) ($best['delta'] ?? 0);
            $month = fhor_tc_cal_month_name((int) ($best['cal_month'] ?? 0));
            if ($month !== '' && $delta >= $spotlight) {
                $candidates[] = [
                    'kind' => 'peak_heat',
                    'score' => $delta,
                    'text' => sprintf(
                        '%s runs %+.1f pts above their own average in %s—their strongest seasonal peak.',
                        $name,
                        $delta,
                        $month
                    ),
                ];
            }
        }

        if ($worst !== null) {
            $strike = $worst['strike_pct'] ?? null;
            $month = fhor_tc_cal_month_name((int) ($worst['cal_month'] ?? 0));
            if ($strike !== null && $month !== '') {
                $yards_below = fhor_tc_summary_yards_below_baseline($pool_trainers, $strike, $name);
                if ($yards_below > 0) {
                    $candidates[] = [
                        'kind' => 'high_floor',
                        'score' => (float) $yards_below,
                        'text' => sprintf(
                            '%s\'s weakest month (%s, %s) is still above the overall baseline of %s other yards in this code.',
                            $name,
                            $month,
                            fhor_tc_format_pct($strike),
                            number_format_i18n($yards_below)
                        ),
                    ];
                }
            }
        }

        if ($gap_pts !== null) {
            $gap = (float) $gap_pts;
            if ($gap <= $spotlight) {
                $candidates[] = [
                    'kind' => 'steady',
                    'score' => max(0.1, $spotlight - $gap + 1),
                    'text' => sprintf(
                        '%s is a Flatliner: only %.1f pts between best and worst eligible months.',
                        $name,
                        $gap
                    ),
                ];
            }
        }

        if ($candidates !== []) {
            usort($candidates, static function ($left, $right) {
                return ($right['score'] ?? 0) <=> ($left['score'] ?? 0);
            });
            $winner = $candidates[0];
            return [
                'kind' => (string) ($winner['kind'] ?? 'none'),
                'text' => (string) ($winner['text'] ?? ''),
            ];
        }

        if ($best !== null) {
            $delta = (float) ($best['delta'] ?? 0);
            $month = fhor_tc_cal_month_name((int) ($best['cal_month'] ?? 0));
            if ($month !== '') {
                return [
                    'kind' => 'peak_heat',
                    'text' => sprintf(
                        '%s runs %+.1f pts above their own average in %s—their strongest seasonal peak.',
                        $name,
                        $delta,
                        $month
                    ),
                ];
            }
        }

        if ($worst !== null) {
            $strike = $worst['strike_pct'] ?? null;
            $month = fhor_tc_cal_month_name((int) ($worst['cal_month'] ?? 0));
            if ($strike !== null && $month !== '') {
                $yards_below = fhor_tc_summary_yards_below_baseline($pool_trainers, $strike, $name);
                if ($yards_below > 0) {
                    return [
                        'kind' => 'high_floor',
                        'text' => sprintf(
                            '%s\'s weakest month (%s, %s) is still above the overall baseline of %s other yards in this code.',
                            $name,
                            $month,
                            fhor_tc_format_pct($strike),
                            number_format_i18n($yards_below)
                        ),
                    ];
                }
            }
        }

        if ($gap_pts !== null) {
            return [
                'kind' => 'steady',
                'text' => sprintf(
                    '%s is a Flatliner: only %.1f pts between best and worst eligible months.',
                    $name,
                    (float) $gap_pts
                ),
            ];
        }

        $baseline = $trainer_row['baseline_pct'] ?? null;
        return [
            'kind' => 'baseline',
            'text' => sprintf(
                '%s has a %s lifetime win rate in this code.',
                $name,
                fhor_tc_format_pct($baseline)
            ),
        ];
    }
}

if (!function_exists('fhor_tc_profile_summary')) {
    /**
     * @param array<string, mixed> $profile fhor_tc_get_profile_data() success payload
     * @return array{kind: string, text: string}
     */
    function fhor_tc_profile_summary(array $profile) {
        $code = fhor_tc_normalize_code($profile['code'] ?? 'jumps');
        $ref = $profile['reference_month'] ?? null;
        $pool = fhor_tc_query_macro_trainers_with_metrics($code, $ref);
        return fhor_tc_build_summary($profile, $pool);
    }
}

if (!function_exists('fhor_tc_render_summary_block')) {
    /**
     * @param array{kind?: string, text?: string} $summary
     * @param array<string, mixed> $options standalone (wrap for shortcode-only embed)
     */
    function fhor_tc_render_summary_block(array $summary, array $options = []) {
        $text = trim((string) ($summary['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        $kind = preg_replace('/[^a-z0-9_-]/', '', (string) ($summary['kind'] ?? 'none'));
        if ($kind === '') {
            $kind = 'none';
        }

        $standalone = !empty($options['standalone']);
        $class = 'track-advice-box fhor-tc-profile-summary fhor-tc-profile-summary--' . $kind;
        if ($standalone) {
            $class .= ' fhor-tc-profile-summary--standalone';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>" data-fhor-tc-summary-kind="<?php echo esc_attr($kind); ?>">
            <strong><?php esc_html_e('At a glance', 'bricks'); ?></strong>
            <p class="fhor-tc-profile-summary__text" data-fhor-tc-ui="summary"><?php echo esc_html($text); ?></p>
        </div>
        <?php
        return (string) ob_get_clean();
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

if (!function_exists('fhor_tc_code_label')) {
    function fhor_tc_code_label($code) {
        return fhor_tc_normalize_code($code) === 'flat' ? 'Flat' : 'Jumps';
    }
}

if (!function_exists('fhor_tc_profile_available_codes')) {
    /**
     * Codes where this trainer has enough lifetime resulted runs for a macro-style profile.
     *
     * @return array<int, string>
     */
    function fhor_tc_profile_available_codes($trainer_name) {
        $name = trim((string) $trainer_name);
        if ($name === '' || !fhor_tc_tables_ok()) {
            return [];
        }

        $macro_min = (int) fhor_tc_config()['macro_min_lifetime_runners'];
        $available = [];
        foreach (['jumps', 'flat'] as $code) {
            $baseline = fhor_tc_query_trainer_baseline($code, $name);
            if ($baseline === null) {
                continue;
            }
            if ((int) ($baseline['runs'] ?? 0) >= $macro_min) {
                $available[] = $code;
            }
        }

        return $available;
    }
}

if (!function_exists('fhor_tc_profile_best_worst_lines')) {
    /**
     * @param array<string, mixed> $profile
     * @return array{best: string, worst: string, best_class: string, worst_class: string}
     */
    function fhor_tc_profile_best_worst_lines(array $profile) {
        $best = is_array($profile['best_month'] ?? null) ? $profile['best_month'] : null;
        $worst = is_array($profile['worst_month'] ?? null) ? $profile['worst_month'] : null;

        $best_line = '—';
        $best_class = 'fhor-tc-delta--neutral';
        if ($best !== null) {
            $best_line = fhor_tc_cal_month_name((int) ($best['cal_month'] ?? 0))
                . ' (' . fhor_tc_format_delta_pts($best['delta'] ?? null) . ')';
            $best_class = fhor_tc_delta_tone_class($best['delta'] ?? null);
        }

        $worst_line = '—';
        $worst_class = 'fhor-tc-delta--neutral';
        if ($worst !== null) {
            $worst_line = fhor_tc_cal_month_name((int) ($worst['cal_month'] ?? 0))
                . ' (' . fhor_tc_format_delta_pts($worst['delta'] ?? null) . ')';
            $worst_class = fhor_tc_delta_tone_class($worst['delta'] ?? null);
        }

        return [
            'best' => $best_line,
            'worst' => $worst_line,
            'best_class' => $best_class,
            'worst_class' => $worst_class,
        ];
    }
}

if (!function_exists('fhor_tc_profile_ui_snapshot')) {
    /**
     * Pre-formatted strings for profile AJAX / client DOM swaps (Phase 2.4).
     *
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    function fhor_tc_profile_ui_snapshot(array $profile) {
        $code = fhor_tc_normalize_code($profile['code'] ?? 'jumps');
        $code_label = fhor_tc_code_label($code);
        $name = (string) ($profile['trainer_name'] ?? '');
        $ref_month = (int) ($profile['reference_month'] ?? fhor_tc_reference_cal_month());
        $current_delta = $profile['current_month_delta'] ?? null;
        $gap_pts = $profile['gap_pts'] ?? null;
        $extremes = fhor_tc_profile_best_worst_lines($profile);
        $summary = fhor_tc_profile_summary($profile);

        return [
            'code' => $code,
            'codeLabel' => $code_label,
            'codeLabelLower' => strtolower($code_label),
            'trainerName' => $name,
            'heroMeta' => $code_label . ' · seasonal cycle',
            'baselinePct' => fhor_tc_format_pct($profile['baseline_pct'] ?? null),
            'runs' => number_format_i18n((int) ($profile['runs'] ?? 0)),
            'wins' => number_format_i18n((int) ($profile['wins'] ?? 0)),
            'referenceMonthLabel' => fhor_tc_cal_month_name($ref_month),
            'currentMonthDelta' => fhor_tc_format_delta_pts($current_delta),
            'currentMonthDeltaClass' => fhor_tc_delta_tone_class($current_delta),
            'gapPts' => $gap_pts === null ? '—' : number_format((float) $gap_pts, 1) . ' pts',
            'bestMonth' => $extremes['best'],
            'worstMonth' => $extremes['worst'],
            'bestMonthClass' => $extremes['best_class'],
            'worstMonthClass' => $extremes['worst_class'],
            'profileUrl' => fhor_tc_trainer_url($name, $code),
            'chartAriaLabel' => sprintf('%s seasonal win-rate deltas chart', $name),
            'summary' => (string) ($summary['text'] ?? ''),
            'summaryKind' => (string) ($summary['kind'] ?? 'none'),
        ];
    }
}

if (!function_exists('fhor_tc_cal_month_name')) {
    function fhor_tc_cal_month_name($cal_month) {
        $cal_month = (int) $cal_month;
        if ($cal_month < 1 || $cal_month > 12) {
            return '';
        }
        if (function_exists('wp_date')) {
            return (string) wp_date('F', mktime(12, 0, 0, $cal_month, 1, 2000));
        }
        return (string) date('F', mktime(12, 0, 0, $cal_month, 1, 2000));
    }
}

if (!function_exists('fhor_tc_cal_month_short')) {
    function fhor_tc_cal_month_short($cal_month) {
        $cal_month = (int) $cal_month;
        if ($cal_month < 1 || $cal_month > 12) {
            return '';
        }
        if (function_exists('wp_date')) {
            return (string) wp_date('M', mktime(12, 0, 0, $cal_month, 1, 2000));
        }
        return (string) date('M', mktime(12, 0, 0, $cal_month, 1, 2000));
    }
}

if (!function_exists('fhor_tc_build_profile_client_bootstrap')) {
    /**
     * Chart.js payload for profile / embed widgets (Phase 2.3).
     *
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    function fhor_tc_build_profile_client_bootstrap(array $profile, $instance_id) {
        $cfg = fhor_tc_config();
        $months_out = [];
        foreach ($profile['months'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $months_out[] = [
                'cal_month' => (int) ($row['cal_month'] ?? 0),
                'runs' => (int) ($row['runs'] ?? 0),
                'wins' => (int) ($row['wins'] ?? 0),
                'strike_pct' => $row['strike_pct'] !== null && $row['strike_pct'] !== ''
                    ? (float) $row['strike_pct']
                    : null,
                'eligible' => !empty($row['eligible']),
                'delta' => isset($row['delta']) && $row['delta'] !== null && $row['delta'] !== ''
                    ? (float) $row['delta']
                    : null,
            ];
        }

        $month_labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $month_labels[] = fhor_tc_cal_month_short($m);
        }

        $instance_id = preg_replace('/[^a-z0-9_-]/i', '', (string) $instance_id);
        if ($instance_id === '') {
            $instance_id = 'fhor-tc-1';
        }

        $trainer_name = (string) ($profile['trainer_name'] ?? '');

        return [
            'instanceId' => $instance_id,
            'canvasId' => 'fhor-tc-profile-chart-' . $instance_id,
            'trainerName' => $trainer_name,
            'trainerSlug' => (string) ($profile['slug'] ?? fhor_tc_trainer_slug($trainer_name)),
            'code' => fhor_tc_normalize_code($profile['code'] ?? 'jumps'),
            'availableCodes' => fhor_tc_profile_available_codes($trainer_name),
            'baselinePct' => (float) ($profile['baseline_pct'] ?? 0),
            'minMonthRunners' => (int) $cfg['min_month_runners'],
            'referenceMonth' => (int) ($profile['reference_month'] ?? fhor_tc_reference_cal_month()),
            'monthLabels' => $month_labels,
            'months' => $months_out,
        ];
    }
}

if (!function_exists('fhor_tc_register_profile_bootstrap')) {
    /**
     * Queue inline window.fhorTcProfile bootstrap for wp_footer (Phase 2.3).
     *
     * @param array<string, mixed> $bootstrap
     */
    function fhor_tc_register_profile_bootstrap(array $bootstrap) {
        if (!isset($GLOBALS['fhor_tc_profile_bootstraps']) || !is_array($GLOBALS['fhor_tc_profile_bootstraps'])) {
            $GLOBALS['fhor_tc_profile_bootstraps'] = [];
        }
        $GLOBALS['fhor_tc_profile_bootstraps'][] = $bootstrap;
        if (!has_action('wp_footer', 'fhor_tc_print_profile_bootstrap_script')) {
            add_action('wp_footer', 'fhor_tc_print_profile_bootstrap_script', 20);
        }
    }
}

if (!function_exists('fhor_tc_print_profile_bootstrap_script')) {
    function fhor_tc_print_profile_bootstrap_script() {
        $bootstraps = $GLOBALS['fhor_tc_profile_bootstraps'] ?? [];
        if (!is_array($bootstraps) || $bootstraps === []) {
            return;
        }
        $json = wp_json_encode($bootstraps);
        if ($json === false) {
            return;
        }
        echo '<script id="fhor-tc-profile-bootstrap">';
        echo 'window.fhorTcProfileBootstraps=' . $json . ';';
        echo 'window.fhorTcProfile=window.fhorTcProfileBootstraps[0]||null;';
        echo '</script>';
    }
}

if (!function_exists('fhor_tc_dashboard_url')) {
    function fhor_tc_dashboard_url($code = null) {
        $path = '/trainer-cycles/';
        if (!function_exists('home_url')) {
            $url = $path;
        } else {
            $url = home_url($path);
        }
        if ($code !== null && fhor_tc_normalize_code($code) === 'flat') {
            if (function_exists('add_query_arg')) {
                $url = add_query_arg('code', 'flat', $url);
            } else {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . 'code=flat';
            }
        }
        return $url;
    }
}

if (!function_exists('fhor_tc_is_dashboard_request')) {
    function fhor_tc_is_dashboard_request() {
        if ((string) get_query_var('fhor_trainer_cycles') === '1') {
            return true;
        }
        $path = (string) (wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
        return (bool) preg_match('#/trainer-cycles/?$#i', $path);
    }
}

if (!function_exists('fhor_tc_dashboard_request_code')) {
    /**
     * Jumps default; flat when ?code=flat (Phase 3.5 wires AJAX toggle).
     */
    function fhor_tc_dashboard_request_code() {
        $raw = isset($_GET['code']) ? (string) wp_unslash($_GET['code']) : '';
        return fhor_tc_normalize_code($raw !== '' ? $raw : 'jumps');
    }
}

if (!function_exists('fhor_tc_dashboard_available_codes')) {
    /**
     * @return array<int, string>
     */
    function fhor_tc_dashboard_available_codes() {
        if (!fhor_tc_tables_ok()) {
            return ['jumps'];
        }
        $codes = [];
        foreach (['jumps', 'flat'] as $code) {
            $baselines = fhor_tc_query_macro_baselines($code);
            if ($baselines !== []) {
                $codes[] = $code;
            }
        }
        return $codes === [] ? ['jumps'] : $codes;
    }
}

if (!function_exists('fhor_tc_trainer_row_for_dashboard_json')) {
    /**
     * Slim trainer row for dashboard bootstrap / AJAX (Phase 3.1+).
     *
     * @param array<string, mixed> $trainer
     * @return array<string, mixed>
     */
    function fhor_tc_trainer_row_for_dashboard_json(array $trainer, $code, $reference_month = null) {
        $name = (string) ($trainer['trainer_name'] ?? '');
        $code = fhor_tc_normalize_code($code);
        $ref_month = fhor_tc_reference_cal_month($reference_month);
        $months_out = [];
        foreach ($trainer['months'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $months_out[] = [
                'cal_month' => (int) ($row['cal_month'] ?? 0),
                'delta' => isset($row['delta']) && $row['delta'] !== null && $row['delta'] !== ''
                    ? (float) $row['delta']
                    : null,
                'eligible' => !empty($row['eligible']),
                'runs' => (int) ($row['runs'] ?? 0),
            ];
        }

        $ref_eligible = false;
        foreach ($months_out as $month_row) {
            if ((int) ($month_row['cal_month'] ?? 0) === $ref_month) {
                $ref_eligible = !empty($month_row['eligible']);
                break;
            }
        }

        $spotlights = $trainer['spotlights'] ?? [];
        if (!is_array($spotlights)) {
            $spotlights = [];
        }

        return [
            'trainerName' => $name,
            'slug' => fhor_tc_trainer_slug($name),
            'url' => fhor_tc_trainer_url($name, $code),
            'baselinePct' => (float) ($trainer['baseline_pct'] ?? 0),
            'runs' => (int) ($trainer['runs'] ?? 0),
            'currentMonthDelta' => isset($trainer['current_month_delta']) && $trainer['current_month_delta'] !== null && $trainer['current_month_delta'] !== ''
                ? (float) $trainer['current_month_delta']
                : null,
            'currentMonthEligible' => $ref_eligible,
            'gapPts' => isset($trainer['gap_pts']) && $trainer['gap_pts'] !== null && $trainer['gap_pts'] !== ''
                ? (float) $trainer['gap_pts']
                : null,
            'spotlights' => [
                'early_birds' => !empty($spotlights['early_birds']),
                'cold_start' => !empty($spotlights['cold_start']),
                'late_surge' => !empty($spotlights['late_surge']),
            ],
            'months' => $months_out,
        ];
    }
}

if (!function_exists('fhor_tc_build_dashboard_client_bootstrap')) {
    /**
     * @param array<string, mixed> $pool fhor_tc_get_macro_pool() payload
     * @return array<string, mixed>
     */
    function fhor_tc_build_dashboard_client_bootstrap(array $pool) {
        $code = fhor_tc_normalize_code($pool['code'] ?? 'jumps');
        $ref = (int) ($pool['reference_month'] ?? fhor_tc_reference_cal_month());
        $trainers_out = [];
        foreach ($pool['trainers'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $trainers_out[] = fhor_tc_trainer_row_for_dashboard_json($row, $code, $ref);
        }

        return [
            'ok' => !empty($pool['ok']),
            'code' => $code,
            'availableCodes' => fhor_tc_dashboard_available_codes(),
            'referenceMonth' => $ref,
            'referenceMonthLabel' => fhor_tc_cal_month_name($ref),
            'trainerCount' => count($trainers_out),
            'minMonthRunners' => (int) fhor_tc_config()['min_month_runners'],
            'trainers' => $trainers_out,
            'meta' => is_array($pool['meta'] ?? null) ? $pool['meta'] : [],
        ];
    }
}

if (!function_exists('fhor_tc_register_dashboard_bootstrap')) {
    /**
     * @param array<string, mixed> $bootstrap
     */
    function fhor_tc_register_dashboard_bootstrap(array $bootstrap) {
        $GLOBALS['fhor_tc_dashboard_bootstrap'] = $bootstrap;
        if (!has_action('wp_footer', 'fhor_tc_print_dashboard_bootstrap_script')) {
            add_action('wp_footer', 'fhor_tc_print_dashboard_bootstrap_script', 21);
        }
    }
}

if (!function_exists('fhor_tc_print_dashboard_bootstrap_script')) {
    function fhor_tc_print_dashboard_bootstrap_script() {
        $bootstrap = $GLOBALS['fhor_tc_dashboard_bootstrap'] ?? null;
        if (!is_array($bootstrap)) {
            return;
        }
        $json = wp_json_encode($bootstrap);
        if ($json === false) {
            return;
        }
        echo '<script id="fhor-tc-dashboard-bootstrap">';
        echo 'window.fhorTcDashboard=' . $json . ';';
        echo '</script>';
    }
}

if (!function_exists('fhor_tc_render_dashboard_unavailable')) {
    function fhor_tc_render_dashboard_unavailable($message) {
        return '<div class="fhor-tc-dashboard-error" style="padding:16px;color:#991b1b;background:#fee2e2;border-radius:8px;">'
            . esc_html($message)
            . '</div>';
    }
}

if (!function_exists('fhor_tc_render_dashboard_list_skeleton')) {
    function fhor_tc_render_dashboard_list_skeleton($rows = 5) {
        $rows = max(1, min(8, (int) $rows));
        ob_start();
        ?>
        <ul class="fhor-tc-dashboard-list fhor-tc-dashboard-list--skeleton" aria-hidden="true">
            <?php for ($i = 0; $i < $rows; $i++) : ?>
            <li class="fhor-tc-dashboard-skeleton-row">
                <span class="fhor-tc-dashboard-skeleton-line fhor-tc-dashboard-skeleton-line--name"></span>
                <span class="fhor-tc-dashboard-skeleton-line fhor-tc-dashboard-skeleton-line--spark"></span>
                <span class="fhor-tc-dashboard-skeleton-line fhor-tc-dashboard-skeleton-line--delta"></span>
            </li>
            <?php endfor; ?>
        </ul>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('fhor_tc_render_dashboard_shell')) {
    /**
     * Macro dashboard SSR shell (Phase 3.1). Lists populate in Phase 3.2+.
     *
     * @param array<string, mixed> $pool fhor_tc_get_macro_pool() success payload
     */
    function fhor_tc_render_dashboard_shell(array $pool) {
        $bootstrap = fhor_tc_build_dashboard_client_bootstrap($pool);
        fhor_tc_register_dashboard_bootstrap($bootstrap);
        $bootstrap_json = wp_json_encode($bootstrap);
        if ($bootstrap_json === false) {
            $bootstrap_json = '{}';
        }

        $code = fhor_tc_normalize_code($pool['code'] ?? 'jumps');
        $code_label = fhor_tc_code_label($code);
        $ref_month = (int) ($pool['reference_month'] ?? fhor_tc_reference_cal_month());
        $ref_label = fhor_tc_cal_month_name($ref_month);
        $trainer_count = (int) ($bootstrap['trainerCount'] ?? 0);
        $available_codes = fhor_tc_dashboard_available_codes();
        $show_code_toggle = count($available_codes) >= 2;
        $cfg = fhor_tc_config();
        $min_month = (int) $cfg['min_month_runners'];

        ob_start();
        ?>
        <div
            class="fhor-race-premium fhor-tc-dashboard is-bootstrapping"
            data-fhor-tc-dashboard="<?php echo esc_attr($bootstrap_json); ?>"
            data-code="<?php echo esc_attr($code); ?>"
        >
            <div class="race-detail-page-shell fhor-tc-dashboard-shell">
                <div class="race-header-card fhor-tc-dashboard-hero">
                    <div class="race-header-top">
                        <div class="fhor-tc-profile-hero__meta-row">
                            <div class="race-meta-time" data-fhor-tc-ui="heroMeta">
                                <?php echo esc_html($code_label); ?> · macro yard cycles
                            </div>
                            <?php if ($show_code_toggle) : ?>
                            <div
                                class="fhor-tc-code-toggle fhor-tc-code-toggle--dashboard"
                                role="tablist"
                                aria-label="<?php esc_attr_e('Race code', 'bricks'); ?>"
                                data-fhor-tc-dashboard-code-toggle
                            >
                                <?php foreach (['jumps', 'flat'] as $toggle_code) :
                                    if (!in_array($toggle_code, $available_codes, true)) {
                                        continue;
                                    }
                                    $is_active = $toggle_code === $code;
                                    ?>
                                <button
                                    type="button"
                                    class="fhor-tc-code-toggle__btn<?php echo $is_active ? ' is-active' : ''; ?>"
                                    role="tab"
                                    data-code="<?php echo esc_attr($toggle_code); ?>"
                                    aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                                    <?php echo $is_active ? ' tabindex="0"' : ' tabindex="-1"'; ?>
                                    disabled
                                ><?php echo esc_html(fhor_tc_code_label($toggle_code)); ?></button>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <h1 class="race-title"><?php esc_html_e('Trainer cycles', 'bricks'); ?></h1>
                        <p class="runners-subtitle fhor-tc-dashboard-hero__lede">
                            Discover which yards run hot or cold in each calendar month — win-rate deltas vs lifetime baseline across the macro pool.
                        </p>
                        <div class="race-details-grid">
                            <div class="race-detail-item">
                                <span class="race-detail-label">Reference month</span>
                                <span class="race-detail-value" data-fhor-tc-ui="referenceMonthLabel"><?php echo esc_html($ref_label); ?></span>
                            </div>
                            <div class="race-detail-item">
                                <span class="race-detail-label">Yards in pool</span>
                                <span class="race-detail-value" data-fhor-tc-ui="trainerCount"><?php echo esc_html(number_format_i18n($trainer_count)); ?></span>
                            </div>
                            <div class="race-detail-item">
                                <span class="race-detail-label">Eligible month rule</span>
                                <span class="race-detail-value">≥ <?php echo esc_html(number_format_i18n($min_month)); ?> runs</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fhor-tc-dashboard-sections" data-fhor-tc-dashboard-sections aria-busy="true">
                    <section class="runners-card fhor-tc-dashboard-panel fhor-tc-dashboard-panel--hot-cold" aria-labelledby="fhor-tc-hot-cold-heading">
                        <div class="runners-header">
                            <h2 class="runners-title" id="fhor-tc-hot-cold-heading"><span>🔥</span> Hot &amp; cold this month</h2>
                            <p class="runners-subtitle">Top yards by <span data-fhor-tc-ui="referenceMonthLabel"><?php echo esc_html($ref_label); ?></span> delta vs lifetime baseline (min <?php echo esc_html(number_format_i18n($min_month)); ?> runs).</p>
                        </div>
                        <div class="fhor-tc-dashboard-hot-cold-grid">
                            <div class="fhor-tc-dashboard-col fhor-tc-dashboard-col--hot">
                                <h3 class="fhor-tc-dashboard-col__title">Hot yards</h3>
                                <div data-fhor-tc-dashboard-region="hot">
                                    <?php echo fhor_tc_render_dashboard_list_skeleton(); ?>
                                </div>
                            </div>
                            <div class="fhor-tc-dashboard-col fhor-tc-dashboard-col--cold">
                                <h3 class="fhor-tc-dashboard-col__title">Cold yards</h3>
                                <div data-fhor-tc-dashboard-region="cold">
                                    <?php echo fhor_tc_render_dashboard_list_skeleton(); ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="runners-card fhor-tc-dashboard-panel fhor-tc-dashboard-panel--spotlights" aria-labelledby="fhor-tc-spotlights-heading">
                        <div class="runners-header">
                            <h2 class="runners-title" id="fhor-tc-spotlights-heading"><span>✨</span> Pattern spotlights</h2>
                            <p class="runners-subtitle">Early Birds, Late Surge, and Cold Start yards (Phase 3.3).</p>
                        </div>
                        <div class="fhor-tc-dashboard-spotlight-grid">
                            <?php
                            $spotlight_labels = [
                                'early_birds' => 'Early Birds',
                                'late_surge' => 'Late Surge',
                                'cold_start' => 'Cold Start',
                            ];
                            foreach ($spotlight_labels as $spot_key => $spot_label) :
                                ?>
                            <div class="fhor-tc-dashboard-spotlight" data-fhor-tc-dashboard-region="<?php echo esc_attr($spot_key); ?>">
                                <h3 class="fhor-tc-dashboard-spotlight__title"><?php echo esc_html($spot_label); ?></h3>
                                <?php echo fhor_tc_render_dashboard_list_skeleton(4); ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="runners-card fhor-tc-dashboard-panel fhor-tc-dashboard-panel--volatility" aria-labelledby="fhor-tc-volatility-heading">
                        <div class="runners-header">
                            <h2 class="runners-title" id="fhor-tc-volatility-heading"><span>📊</span> Flatliners &amp; Wavemakers</h2>
                            <p class="runners-subtitle">Volatility range rail (Phase 3.4).</p>
                        </div>
                        <div class="fhor-tc-dashboard-volatility-skeleton" data-fhor-tc-dashboard-region="volatility">
                            <?php echo fhor_tc_render_dashboard_list_skeleton(6); ?>
                        </div>
                    </section>
                </div>

                <div class="track-advice-box fhor-tc-dashboard-rules">
                    <strong>How to read this dashboard</strong>
                    <ul class="fhor-tc-profile-rules__list">
                        <li><strong>Macro pool</strong> = top <?php echo esc_html(number_format_i18n((int) $cfg['macro_top_n'])); ?> <?php echo esc_html(strtolower($code_label)); ?> trainers by lifetime resulted runs (minimum <?php echo esc_html(number_format_i18n((int) $cfg['macro_min_lifetime_runners'])); ?>).</li>
                        <li><strong>Delta</strong> = calendar-month win % minus that yard’s lifetime win % (percentage points).</li>
                        <li>Lists and spotlights hydrate from the embedded dataset; interactive Jumps / Flat switching lands in Phase 3.5.</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('fhor_tc_format_pct')) {
    function fhor_tc_format_pct($value) {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, 1) . '%';
    }
}

if (!function_exists('fhor_tc_format_delta_pts')) {
    function fhor_tc_format_delta_pts($delta) {
        if ($delta === null || $delta === '') {
            return '—';
        }
        $delta = (float) $delta;
        $sign = $delta > 0 ? '+' : '';
        return $sign . number_format($delta, 1) . ' pts';
    }
}

if (!function_exists('fhor_tc_delta_tone_class')) {
    function fhor_tc_delta_tone_class($delta) {
        if ($delta === null || $delta === '') {
            return 'fhor-tc-delta--neutral';
        }
        $delta = (float) $delta;
        if ($delta > 0) {
            return 'fhor-tc-delta--hot';
        }
        if ($delta < 0) {
            return 'fhor-tc-delta--cold';
        }
        return 'fhor-tc-delta--neutral';
    }
}

if (!function_exists('fhor_tc_render_profile_unavailable')) {
    function fhor_tc_render_profile_unavailable($message) {
        return '<div class="fhor-tc-profile-error" style="padding:16px;color:#991b1b;background:#fee2e2;border-radius:8px;">'
            . esc_html($message)
            . '</div>';
    }
}

if (!function_exists('fhor_tc_render_profile_shell')) {
    /**
     * SSR trainer profile markup (stats + chart canvas; Chart.js init in trainer-cycles.js).
     *
     * @param array<string, mixed> $profile fhor_tc_get_profile_data() success payload
     * @param array<string, mixed> $options embed, show_back_link, instance_id
     */
    function fhor_tc_render_profile_shell(array $profile, array $options = []) {
        $embed = !empty($options['embed']);
        $show_back = array_key_exists('show_back_link', $options)
            ? (bool) $options['show_back_link']
            : !$embed;

        $instance_id = isset($options['instance_id'])
            ? (string) $options['instance_id']
            : (function_exists('wp_unique_id') ? wp_unique_id('fhor-tc-') : 'fhor-tc-1');

        $bootstrap = fhor_tc_build_profile_client_bootstrap($profile, $instance_id);
        fhor_tc_register_profile_bootstrap($bootstrap);
        $bootstrap_json = wp_json_encode($bootstrap);
        if ($bootstrap_json === false) {
            $bootstrap_json = '{}';
        }

        $cfg = fhor_tc_config();
        $min_month = (int) $cfg['min_month_runners'];
        $code = fhor_tc_normalize_code($profile['code'] ?? 'jumps');
        $code_label = fhor_tc_code_label($code);
        $name = (string) ($profile['trainer_name'] ?? '');
        $slug = (string) ($profile['slug'] ?? fhor_tc_trainer_slug($name));
        $profile_url = (string) ($profile['url'] ?? fhor_tc_trainer_url($name, $code));
        $ref_month = (int) ($profile['reference_month'] ?? fhor_tc_reference_cal_month());
        $ref_label = fhor_tc_cal_month_name($ref_month);
        $canvas_id = (string) ($bootstrap['canvasId'] ?? 'fhor-tc-profile-chart');

        $baseline = $profile['baseline_pct'] ?? null;
        $runs = (int) ($profile['runs'] ?? 0);
        $wins = (int) ($profile['wins'] ?? 0);
        $current_delta = $profile['current_month_delta'] ?? null;
        $gap_pts = $profile['gap_pts'] ?? null;

        $extremes = fhor_tc_profile_best_worst_lines($profile);
        $best_line = $extremes['best'];
        $worst_line = $extremes['worst'];
        $summary = fhor_tc_profile_summary($profile);
        $available_codes = fhor_tc_profile_available_codes($name);
        $show_code_toggle = count($available_codes) >= 2;

        $dashboard_url = fhor_tc_dashboard_url();
        $shell_class = 'race-detail-page-shell fhor-tc-profile-shell';
        if ($embed) {
            $shell_class .= ' fhor-tc-profile-shell--embed';
        }
        $root_class = 'fhor-race-premium fhor-tc-profile';
        if ($embed) {
            $root_class .= ' fhor-tc-profile--embed';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($shell_class); ?>">
            <div
                class="<?php echo esc_attr($root_class); ?>"
                id="fhor-tc-profile-root-<?php echo esc_attr($instance_id); ?>"
                data-trainer-slug="<?php echo esc_attr($slug); ?>"
                data-trainer-name="<?php echo esc_attr($name); ?>"
                data-code="<?php echo esc_attr($code); ?>"
                data-fhor-tc-profile="<?php echo esc_attr($bootstrap_json); ?>"
            >
                <?php if ($show_back) : ?>
                <div class="back-button-container">
                    <a href="<?php echo esc_url($dashboard_url); ?>" class="back-button">← Trainer cycles</a>
                </div>
                <?php elseif ($profile_url !== '') : ?>
                <div class="back-button-container fhor-tc-profile-embed-link">
                    <a href="<?php echo esc_url($profile_url); ?>" class="back-button" data-fhor-tc-ui-link="profileUrl">Open full profile →</a>
                </div>
                <?php endif; ?>

                <div class="race-header-card fhor-tc-profile-hero">
                    <div class="race-header-top">
                        <div class="fhor-tc-profile-hero__meta-row">
                            <div class="race-meta-time" data-fhor-tc-ui="heroMeta"><?php echo esc_html($code_label); ?> · seasonal cycle</div>
                            <?php if ($show_code_toggle) : ?>
                            <div
                                class="fhor-tc-code-toggle"
                                role="tablist"
                                aria-label="<?php esc_attr_e('Race code', 'bricks'); ?>"
                                data-fhor-tc-code-toggle
                            >
                                <?php foreach (['jumps', 'flat'] as $toggle_code) :
                                    if (!in_array($toggle_code, $available_codes, true)) {
                                        continue;
                                    }
                                    $is_active = $toggle_code === $code;
                                    ?>
                                <button
                                    type="button"
                                    class="fhor-tc-code-toggle__btn<?php echo $is_active ? ' is-active' : ''; ?>"
                                    role="tab"
                                    data-code="<?php echo esc_attr($toggle_code); ?>"
                                    aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                                    <?php echo $is_active ? ' tabindex="0"' : ' tabindex="-1"'; ?>
                                ><?php echo esc_html(fhor_tc_code_label($toggle_code)); ?></button>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <h1 class="race-title"><?php echo esc_html($name); ?></h1>
                        <div class="race-details-grid">
                            <div class="race-detail-item">
                                <span class="race-detail-label">Lifetime win rate</span>
                                <span class="race-detail-value" data-fhor-tc-ui="baselinePct"><?php echo esc_html(fhor_tc_format_pct($baseline)); ?></span>
                            </div>
                            <div class="race-detail-item">
                                <span class="race-detail-label">Resulted runs</span>
                                <span class="race-detail-value" data-fhor-tc-ui="runs"><?php echo esc_html(number_format_i18n($runs)); ?></span>
                            </div>
                            <div class="race-detail-item">
                                <span class="race-detail-label">Wins</span>
                                <span class="race-detail-value" data-fhor-tc-ui="wins"><?php echo esc_html(number_format_i18n($wins)); ?></span>
                            </div>
                            <div class="race-detail-item">
                                <span class="race-detail-label"><span data-fhor-tc-ui="referenceMonthLabel"><?php echo esc_html($ref_label); ?></span> vs baseline</span>
                                <span class="race-detail-value <?php echo esc_attr(fhor_tc_delta_tone_class($current_delta)); ?>" data-fhor-tc-ui="currentMonthDelta">
                                    <?php echo esc_html(fhor_tc_format_delta_pts($current_delta)); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="race-header-bottom">
                        <div class="track-advice-box fhor-tc-profile-hero__copy">
                            <strong>Hot vs cold months</strong>
                            Win-rate deltas pool every <span data-fhor-tc-ui="codeLabelLower"><?php echo esc_html(strtolower($code_label)); ?></span> runner by calendar month (all years). Months with fewer than <?php echo esc_html((string) $min_month); ?> resulted runs are excluded from deltas and the chart.
                        </div>
                    </div>
                </div>

                <?php echo fhor_tc_render_summary_block($summary); ?>

                <div class="runners-card fhor-tc-profile-stats-card">
                    <div class="runners-header">
                        <h2 class="runners-title"><span>📈</span> Seasonal stats</h2>
                        <p class="runners-subtitle">Lifetime baseline compared to each eligible calendar month.</p>
                    </div>
                    <div class="race-details-grid fhor-tc-profile-stats-grid">
                        <div class="race-detail-item">
                            <span class="race-detail-label">Baseline win %</span>
                            <span class="race-detail-value" data-fhor-tc-ui="baselinePct"><?php echo esc_html(fhor_tc_format_pct($baseline)); ?></span>
                        </div>
                        <div class="race-detail-item">
                            <span class="race-detail-label">Volatility gap</span>
                            <span class="race-detail-value" data-fhor-tc-ui="gapPts"><?php echo esc_html($gap_pts === null ? '—' : number_format((float) $gap_pts, 1) . ' pts'); ?></span>
                        </div>
                        <div class="race-detail-item">
                            <span class="race-detail-label">Hottest month</span>
                            <span class="race-detail-value <?php echo esc_attr($extremes['best_class']); ?>" data-fhor-tc-ui="bestMonth">
                                <?php echo esc_html($best_line); ?>
                            </span>
                        </div>
                        <div class="race-detail-item">
                            <span class="race-detail-label">Coldest month</span>
                            <span class="race-detail-value <?php echo esc_attr($extremes['worst_class']); ?>" data-fhor-tc-ui="worstMonth">
                                <?php echo esc_html($worst_line); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="runners-card fhor-tc-profile-chart-card">
                    <div class="runners-header">
                        <h2 class="runners-title"><span>🌡️</span> Monthly delta wave</h2>
                        <p class="runners-subtitle">Percentage-point change vs lifetime baseline (eligible months only).</p>
                    </div>
                    <div class="fhor-tc-profile-chart-wrap" aria-hidden="false">
                        <canvas
                            id="<?php echo esc_attr($canvas_id); ?>"
                            class="fhor-tc-profile-chart"
                            role="img"
                            aria-label="<?php echo esc_attr(sprintf('%s seasonal win-rate deltas chart', $name)); ?>"
                            data-fhor-tc-ui="chartAriaLabel"
                            height="280"
                        ></canvas>
                    </div>
                    <p class="fhor-tc-chart-legend">
                        <span class="fhor-tc-chart-legend__swatch fhor-tc-chart-legend__swatch--hot"></span> Hot delta
                        <span class="fhor-tc-chart-legend__swatch fhor-tc-chart-legend__swatch--cold"></span> Cold delta
                        <span class="fhor-tc-chart-legend__swatch fhor-tc-chart-legend__swatch--small"></span> Small sample
                    </p>
                </div>

                <div class="track-advice-box fhor-tc-profile-rules">
                    <strong>How to read this page</strong>
                    <ul class="fhor-tc-profile-rules__list">
                        <li><strong>Delta</strong> = that month’s win % minus this trainer’s lifetime <span data-fhor-tc-ui="codeLabelLower"><?php echo esc_html(strtolower($code_label)); ?></span> win % (points, not ratio).</li>
                        <li><strong>Eligible months</strong> need at least <?php echo esc_html((string) $min_month); ?> resulted runs; smaller samples are omitted so the wave is not skewed.</li>
                        <li><strong>Volatility gap</strong> is the spread between the best and worst eligible monthly win rates.</li>
                        <li>Non-runners and non-numeric finishes are excluded, matching System Builder historic filters.</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('fhor_tc_trainer_cycles_profile_shortcode')) {
    function fhor_tc_trainer_cycles_profile_shortcode($atts = []) {
        $ctx = fhor_tc_profile_request_context();
        if ($ctx === null) {
            return '';
        }

        $profile = fhor_tc_get_profile_data($ctx['trainer_name'], $ctx['code']);
        if (empty($profile['ok'])) {
            $error = (string) ($profile['error'] ?? 'unavailable');
            if ($error === 'not_found') {
                return fhor_tc_render_profile_unavailable('No historic results found for this trainer in the selected code.');
            }
            if ($error === 'invalid_trainer') {
                return '';
            }
            return fhor_tc_render_profile_unavailable('Trainer cycle data is temporarily unavailable. Please try again later.');
        }

        return fhor_tc_render_profile_shell($profile);
    }
}
add_shortcode('trainer_cycles_profile', 'fhor_tc_trainer_cycles_profile_shortcode');

if (!function_exists('fhor_tc_trainer_cycles_embed_shortcode')) {
    /**
     * Embed the same profile widget on any Bricks page: [trainer_cycles trainer="" code="jumps"].
     */
    function fhor_tc_trainer_cycles_embed_shortcode($atts = []) {
        $atts = shortcode_atts(
            [
                'trainer' => '',
                'code' => 'jumps',
            ],
            $atts,
            'trainer_cycles'
        );

        $name = trim((string) $atts['trainer']);
        if ($name === '') {
            return '';
        }

        $code = fhor_tc_normalize_code($atts['code']);
        $profile = fhor_tc_get_profile_data($name, $code);
        if (empty($profile['ok'])) {
            $error = (string) ($profile['error'] ?? 'unavailable');
            if ($error === 'not_found') {
                return fhor_tc_render_profile_unavailable('No historic results found for this trainer in the selected code.');
            }
            if ($error === 'invalid_trainer') {
                return '';
            }
            return fhor_tc_render_profile_unavailable('Trainer cycle data is temporarily unavailable. Please try again later.');
        }

        return fhor_tc_render_profile_shell($profile, ['embed' => true]);
    }
}
add_shortcode('trainer_cycles', 'fhor_tc_trainer_cycles_embed_shortcode');

if (!function_exists('fhor_tc_trainer_cycles_summary_shortcode')) {
    /**
     * One-line seasonal summary for Bricks embeds: [trainer_cycles_summary trainer="" code="jumps"].
     */
    function fhor_tc_trainer_cycles_summary_shortcode($atts = []) {
        $atts = shortcode_atts(
            [
                'trainer' => '',
                'code' => 'jumps',
            ],
            $atts,
            'trainer_cycles_summary'
        );

        $name = trim((string) $atts['trainer']);
        if ($name === '') {
            return '';
        }

        $code = fhor_tc_normalize_code($atts['code']);
        $profile = fhor_tc_get_profile_data($name, $code);
        if (empty($profile['ok'])) {
            $error = (string) ($profile['error'] ?? 'unavailable');
            if ($error === 'not_found') {
                return fhor_tc_render_profile_unavailable('No historic results found for this trainer in the selected code.');
            }
            if ($error === 'invalid_trainer') {
                return '';
            }
            return fhor_tc_render_profile_unavailable('Trainer cycle data is temporarily unavailable. Please try again later.');
        }

        $summary = fhor_tc_profile_summary($profile);

        return fhor_tc_render_summary_block($summary, ['standalone' => true]);
    }
}
add_shortcode('trainer_cycles_summary', 'fhor_tc_trainer_cycles_summary_shortcode');

if (!function_exists('fhor_tc_trainer_cycles_dashboard_shortcode')) {
    /**
     * Macro dashboard: [trainer_cycles_dashboard] or /trainer-cycles/ route.
     */
    function fhor_tc_trainer_cycles_dashboard_shortcode($atts = []) {
        $atts = shortcode_atts(
            [
                'code' => '',
            ],
            $atts,
            'trainer_cycles_dashboard'
        );

        $code = trim((string) $atts['code']) !== ''
            ? fhor_tc_normalize_code($atts['code'])
            : fhor_tc_dashboard_request_code();

        $pool = fhor_tc_get_macro_pool($code);
        if (empty($pool['ok'])) {
            return fhor_tc_render_dashboard_unavailable(
                'Trainer cycle dashboard is temporarily unavailable. Please try again later.'
            );
        }

        return fhor_tc_render_dashboard_shell($pool);
    }
}
add_shortcode('trainer_cycles_dashboard', 'fhor_tc_trainer_cycles_dashboard_shortcode');

if (!function_exists('fhor_tc_add_rewrite')) {
    function fhor_tc_add_rewrite() {
        add_rewrite_tag('%fhor_trainer_slug%', '([^/]+)');
        add_rewrite_tag('%fhor_trainer_cycles%', '([0-9]+)');
        add_rewrite_rule(
            '^trainers/([^/]+)/?$',
            'index.php?fhor_trainer_slug=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^trainer-cycles/?$',
            'index.php?fhor_trainer_cycles=1',
            'top'
        );
    }
}
add_action('init', 'fhor_tc_add_rewrite', 20);

if (!function_exists('fhor_tc_query_vars')) {
    function fhor_tc_query_vars($vars) {
        $vars[] = 'fhor_trainer_slug';
        $vars[] = 'fhor_trainer_cycles';
        return $vars;
    }
}
add_filter('query_vars', 'fhor_tc_query_vars');

if (!function_exists('fhor_tc_template_redirect')) {
    function fhor_tc_template_redirect() {
        if (is_admin()) {
            return;
        }

        if (fhor_tc_is_dashboard_request()) {
            status_header(200);
            nocache_headers();

            ob_start();
            get_header();
            $header = ob_get_clean();

            ob_start();
            get_footer();
            $footer = ob_get_clean();

            echo $header;
            echo '<main class="main-content fhor-tc-dashboard-page"><div class="content-container">';
            echo do_shortcode('[trainer_cycles_dashboard]');
            echo '</div></main>';
            echo $footer;
            exit;
        }

        if (!fhor_tc_is_trainer_profile_request()) {
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
        if (get_option('fhor_tc_rewrite_flushed') !== '2') {
            flush_rewrite_rules(false);
            update_option('fhor_tc_rewrite_flushed', '2', false);
        }
    }
}
add_action('init', 'fhor_tc_flush_rewrites', 999);

if (!function_exists('fhor_tc_document_title')) {
    function fhor_tc_document_title($title) {
        if (fhor_tc_is_dashboard_request()) {
            return 'Trainer Cycles | Fhorsite';
        }
        if (fhor_tc_is_trainer_profile_request()) {
            $slug = fhor_tc_profile_request_slug();
            if ($slug !== '') {
                $name = fhor_tc_resolve_trainer_from_slug($slug);
                if (is_string($name) && $name !== '') {
                    return $name . ' — Trainer Cycles | Fhorsite';
                }
            }
        }
        return $title;
    }
}
add_filter('pre_get_document_title', 'fhor_tc_document_title', 28);

if (!function_exists('fhor_tc_ajax_profile')) {
    /**
     * Public profile JSON for Jumps / Flat toggle (Phase 2.4).
     */
    function fhor_tc_ajax_profile() {
        check_ajax_referer('fhor_tc_profile', 'nonce');

        $name = isset($_POST['trainer']) ? trim(sanitize_text_field(wp_unslash($_POST['trainer']))) : '';
        $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
        if ($name === '' && $slug !== '') {
            $resolved = fhor_tc_resolve_trainer_from_slug($slug);
            if (is_string($resolved) && $resolved !== '') {
                $name = $resolved;
            }
        }

        $code = isset($_POST['code']) ? fhor_tc_normalize_code(wp_unslash($_POST['code'])) : 'jumps';
        $instance_id = isset($_POST['instance_id'])
            ? preg_replace('/[^a-z0-9_-]/i', '', (string) wp_unslash($_POST['instance_id']))
            : '';

        if ($name === '') {
            wp_send_json_error([
                'ok' => false,
                'error' => 'invalid_trainer',
            ], 400);
        }

        $available = fhor_tc_profile_available_codes($name);
        if ($available !== [] && !in_array($code, $available, true)) {
            wp_send_json_error([
                'ok' => false,
                'error' => 'invalid_code',
                'availableCodes' => $available,
            ], 400);
        }

        $profile = fhor_tc_get_profile_data($name, $code);
        if (empty($profile['ok'])) {
            $error = (string) ($profile['error'] ?? 'unavailable');
            $status = $error === 'not_found' ? 404 : 503;
            wp_send_json_error($profile, $status);
        }

        if ($instance_id === '') {
            $instance_id = 'fhor-tc-1';
        }

        wp_send_json_success([
            'bootstrap' => fhor_tc_build_profile_client_bootstrap($profile, $instance_id),
            'ui' => fhor_tc_profile_ui_snapshot($profile),
            'meta' => $profile['meta'] ?? [],
        ]);
    }
}
add_action('wp_ajax_fhor_tc_profile', 'fhor_tc_ajax_profile');
add_action('wp_ajax_nopriv_fhor_tc_profile', 'fhor_tc_ajax_profile');

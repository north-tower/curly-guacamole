<?php
/**
 * Trainer Cycles unit checks (no WordPress DB).
 *
 * Run from repo root:
 *   php tests/trainer-cycles-test.php
 */

$failures = 0;

function tc_assert($label, $cond) {
    global $failures;
    if ($cond) {
        echo "ok  $label\n";
        return;
    }
    $failures++;
    echo "FAIL $label\n";
}

function tc_eq($label, $expected, $actual, $eps = 0.01) {
    $pass = is_numeric($expected) && is_numeric($actual)
        ? abs((float) $expected - (float) $actual) < $eps
        : $expected === $actual;
    tc_assert(
        $label . ' (expected ' . json_encode($expected) . ', got ' . json_encode($actual) . ')',
        $pass
    );
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return $default;
    }
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number) {
        return number_format((float) $number);
    }
}

require dirname(__DIR__) . '/inc/trainer-cycles.php';

tc_eq('normalize code flat', 'flat', fhor_tc_normalize_code('FLAT'));
tc_eq('normalize code jumps default', 'jumps', fhor_tc_normalize_code(''));

tc_eq('delta pts', 5.0, fhor_tc_delta_pts(15.0, 10.0));
tc_assert('delta null when no strike', fhor_tc_delta_pts(null, 10.0) === null);

$merged = fhor_tc_merge_trainer_monthly(
    ['trainer_name' => 'Fixture Trainer', 'runs' => 1000, 'wins' => 100, 'baseline_pct' => 10.0],
    [
        3 => ['runs' => 60, 'wins' => 12],
        6 => ['runs' => 40, 'wins' => 2],
    ],
    50
);
tc_eq('merge month count', 12, count($merged['months']));

$march = null;
foreach ($merged['months'] as $row) {
    if ((int) $row['cal_month'] === 3) {
        $march = $row;
        break;
    }
}
tc_assert('march row found', is_array($march));
tc_eq('march eligible', true, $march['eligible']);
tc_eq('march strike', 20.0, $march['strike_pct']);

$june = null;
foreach ($merged['months'] as $row) {
    if ((int) $row['cal_month'] === 6) {
        $june = $row;
        break;
    }
}
tc_eq('june ineligible', false, $june['eligible']);

$with_deltas = fhor_tc_months_with_deltas($merged['months'], 10.0);
$march_delta = null;
$june_delta = null;
foreach ($with_deltas as $row) {
    if ((int) $row['cal_month'] === 3) {
        $march_delta = $row['delta'];
    }
    if ((int) $row['cal_month'] === 6) {
        $june_delta = $row['delta'];
    }
}
tc_eq('march delta', 10.0, $march_delta);
tc_assert('june delta null', $june_delta === null);

$gap = fhor_tc_volatility_gap_pts($with_deltas);
tc_eq('volatility gap eligible only', 10.0, $gap);

$trainer_a = fhor_tc_apply_trainer_metrics([
    'trainer_name' => 'A',
    'runs' => 500,
    'wins' => 50,
    'baseline_pct' => 10.0,
    'months' => fhor_tc_build_twelve_month_rows([
        1 => ['runs' => 100, 'wins' => 10],
        2 => ['runs' => 100, 'wins' => 20],
    ]),
], 'jumps', 1);

$trainer_b = fhor_tc_apply_trainer_metrics([
    'trainer_name' => 'B',
    'runs' => 500,
    'wins' => 50,
    'baseline_pct' => 10.0,
    'months' => fhor_tc_build_twelve_month_rows([
        1 => ['runs' => 100, 'wins' => 11],
        2 => ['runs' => 100, 'wins' => 13],
    ]),
], 'jumps', 1);

tc_eq('trainer A gap', 10.0, $trainer_a['gap_pts']);
tc_eq('trainer B gap', 2.0, $trainer_b['gap_pts']);

$vol_sorted = [$trainer_a, $trainer_b];
usort($vol_sorted, static function ($left, $right) {
    return ($right['gap_pts'] ?? 0) <=> ($left['gap_pts'] ?? 0);
});
tc_eq('volatility sort wavemaker first', 'A', $vol_sorted[0]['trainer_name']);

$spotlight_months = fhor_tc_build_twelve_month_rows([
    10 => ['runs' => 80, 'wins' => 16],
    11 => ['runs' => 80, 'wins' => 16],
    12 => ['runs' => 80, 'wins' => 16],
]);
$spotlight_trainer = fhor_tc_apply_trainer_metrics([
    'trainer_name' => 'Hot Yard',
    'runs' => 800,
    'wins' => 80,
    'baseline_pct' => 10.0,
    'months' => $spotlight_months,
], 'jumps', 10);
tc_eq('early birds qualifies', true, $spotlight_trainer['spotlights']['early_birds']);

$macro = fhor_tc_get_macro_pool('jumps');
tc_eq('macro unavailable ok', false, $macro['ok']);
tc_eq('macro unavailable error', 'unavailable', $macro['error']);
tc_assert('macro trainers array', is_array($macro['trainers']));

tc_eq('dashboard url path', '/trainer-cycles/', fhor_tc_dashboard_url());

tc_eq('race profile code nh', 'jumps', fhor_tc_race_profile_code(true));
tc_eq('race profile code flat', 'flat', fhor_tc_race_profile_code(false));
tc_assert('trainer link contains slug', strpos(fhor_tc_trainer_link_html('Paul Nicholls', 'jumps'), '/trainers/') !== false);
tc_assert('trainer link flat query', strpos(fhor_tc_trainer_link_html('Paul Nicholls', 'flat'), 'code=flat') !== false);

$fixture_pool = [
    'ok' => true,
    'code' => 'jumps',
    'reference_month' => 10,
    'trainers' => [$spotlight_trainer],
    'meta' => ['cache' => 'bypass', 'ms' => 0.1, 'count' => 1],
];
$dash_boot = fhor_tc_build_dashboard_client_bootstrap($fixture_pool);
tc_eq('dashboard bootstrap ok', true, $dash_boot['ok']);
tc_eq('dashboard bootstrap code', 'jumps', $dash_boot['code']);
tc_eq('dashboard bootstrap trainer count', 1, $dash_boot['trainerCount']);
tc_eq('dashboard bootstrap trainer name', 'Hot Yard', $dash_boot['trainers'][0]['trainerName']);
tc_assert('dashboard bootstrap has month deltas', isset($dash_boot['trainers'][0]['months'][9]['delta']));
tc_assert('dashboard bootstrap spotlight windows', is_array($dash_boot['spotlightWindows'] ?? null));
tc_eq('dashboard bootstrap early birds flag', true, $dash_boot['trainers'][0]['spotlights']['early_birds']);
tc_assert('dashboard bootstrap month labels', is_array($dash_boot['monthLabels'] ?? null) && count($dash_boot['monthLabels']) === 12);
tc_assert('dashboard bootstrap best month strike', isset($dash_boot['trainers'][0]['bestMonth']['strikePct']));
tc_assert('dashboard bootstrap worst month strike', isset($dash_boot['trainers'][0]['worstMonth']['strikePct']));

$dash_ui = fhor_tc_dashboard_ui_snapshot($fixture_pool);
tc_eq('dashboard ui code', 'jumps', $dash_ui['code']);
tc_assert('dashboard ui hero meta', strpos($dash_ui['heroMeta'], 'macro yard cycles') !== false);
tc_assert('dashboard ui spotlight hints', is_array($dash_ui['spotlightHints'] ?? null));
tc_assert('dashboard ui early birds hint', strpos($dash_ui['spotlightHints']['early_birds'], 'Oct') !== false);

fhor_tc_sql_timing_reset();
$fake_start = microtime(true) - 0.02;
fhor_tc_sql_timing_record('macro_baselines', $fake_start);
$sql_snap = fhor_tc_sql_timing_snapshot();
tc_assert('sql snapshot has sql_ms', isset($sql_snap['sql_ms']) && $sql_snap['sql_ms'] > 0);
tc_assert('sql snapshot has macro_baselines', isset($sql_snap['sql']['macro_baselines']));

$meta_with_sql = fhor_tc_response_meta(microtime(true), 'miss', $sql_snap);
tc_assert('response meta merges sql', isset($meta_with_sql['sql_ms']) && $meta_with_sql['cache'] === 'miss');

tc_eq('default macro top_n tuned', 40, (int) FHOR_TC_MACRO_TOP_N);
tc_eq('default macro cache ttl 12h', 12 * HOUR_IN_SECONDS, (int) FHOR_TC_MACRO_CACHE_TTL);

$profile = fhor_tc_get_profile_data('', 'jumps');
tc_eq('profile invalid ok', false, $profile['ok']);
tc_eq('profile invalid error', 'invalid_trainer', $profile['error']);

$profile_nf = fhor_tc_get_profile_data('Nobody Here', 'flat');
tc_eq('profile not found ok', false, $profile_nf['ok']);
tc_eq('profile not found error', 'not_found', $profile_nf['error']);

$pool = [
    ['trainer_name' => 'Peer A', 'baseline_pct' => 8.0],
    ['trainer_name' => 'Peer B', 'baseline_pct' => 12.0],
    ['trainer_name' => 'Peer C', 'baseline_pct' => 15.0],
];

tc_eq('summary yards below strike', 2, fhor_tc_summary_yards_below_baseline($pool, 13.0, 'Subject'));

$peak_summary = fhor_tc_build_summary([
    'trainer_name' => 'Peak Yard',
    'best_month' => ['cal_month' => 4, 'delta' => 5.0, 'strike_pct' => 15.0],
    'worst_month' => ['cal_month' => 9, 'delta' => 1.0, 'strike_pct' => 11.0],
    'gap_pts' => 4.0,
], $pool);
tc_eq('summary peak heat kind', 'peak_heat', $peak_summary['kind']);
tc_assert('summary peak heat mentions April', strpos($peak_summary['text'], 'April') !== false);

$floor_summary = fhor_tc_build_summary([
    'trainer_name' => 'Floor Yard',
    'best_month' => ['cal_month' => 5, 'delta' => 2.0, 'strike_pct' => 12.0],
    'worst_month' => ['cal_month' => 9, 'delta' => 0.5, 'strike_pct' => 13.0],
    'gap_pts' => 6.0,
], $pool);
tc_eq('summary high floor kind', 'high_floor', $floor_summary['kind']);
tc_assert('summary high floor counts peers', strpos($floor_summary['text'], '2 other yards') !== false);

$flat_summary = fhor_tc_build_summary([
    'trainer_name' => 'Flat Yard',
    'best_month' => ['cal_month' => 1, 'delta' => 1.5, 'strike_pct' => 11.5],
    'worst_month' => ['cal_month' => 7, 'delta' => 0.5, 'strike_pct' => 10.5],
    'gap_pts' => 1.0,
], $pool);
tc_eq('summary steady kind', 'steady', $flat_summary['kind']);
tc_assert('summary steady mentions Flatliner', strpos($flat_summary['text'], 'Flatliner') !== false);

if ($failures > 0) {
    echo "\n{$failures} failure(s)\n";
    exit(1);
}

echo "\nAll trainer-cycles tests passed.\n";
exit(0);

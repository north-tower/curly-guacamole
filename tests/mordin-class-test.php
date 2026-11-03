<?php
/**
 * Mordin class module unit checks (no WordPress DB).
 *
 * Run from repo root:
 *   php tests/mordin-class-test.php
 */

$failures = 0;

function mc_assert($label, $cond) {
    global $failures;
    if ($cond) {
        echo "ok  $label\n";
        return;
    }
    $failures++;
    echo "FAIL $label\n";
}

function mc_eq($label, $expected, $actual) {
    mc_assert(
        $label . ' (expected ' . json_encode($expected) . ', got ' . json_encode($actual) . ')',
        $expected === $actual
    );
}

require dirname(__DIR__) . '/inc/mordin-class.php';

$s = fhor_mordin_stamina(2200, 1, 5.5, false);
mc_eq('10f G1 DI 5.5 exceeded', true, $s['exceeded']);
mc_eq('10f G1 DI 5.5 not target', false, $s['target']);
$s = fhor_mordin_stamina(2420, 4, null, true);
mc_eq('Listed 11f infinite DI exceeded', true, $s['exceeded']);
$s = fhor_mordin_stamina(2640, 2, 3.3, false);
mc_eq('12f G2 DI 3.30 target', true, $s['target']);
mc_eq('12f G2 DI 3.30 not exceeded', false, $s['exceeded']);
$s = fhor_mordin_stamina(1980, 1, 9.0, false);
mc_eq('9f race does not apply', false, $s['applies']);
mc_eq('9f race never exceeded', false, $s['exceeded']);
$s = fhor_mordin_stamina(2640, 7, 9.0, false);
mc_eq('Class 2 handicap does not apply', false, $s['applies']);

$labels = function (array $badges) {
    return array_map(function ($b) {
        return $b['label'];
    }, $badges);
};
$none = ['applies' => false, 'exceeded' => false, 'target' => false];
mc_eq('no flags no badges', [], fhor_mordin_badges([], $none));
mc_eq('task10: mordin flags alone no racecard badges', [], $labels(fhor_mordin_badges([
    'high_value_maiden_placing' => '1',
    'meets_class_requirement' => '1',
    'outclassed_handicapper' => '1',
    'debut_penalty' => '1',
], ['applies' => true, 'exceeded' => true, 'target' => true])));
mc_eq('foreign form warn', ['Foreign form'], $labels(fhor_mordin_badges(['foreign_unquantified_runs' => '2'], $none)));

mc_eq('not_outclassed rejects flagged', false, fhor_mordin_row_passes((object) ['mrf_outclassed_handicapper' => '1'], ['not_outclassed']));
mc_eq('not_outclassed keeps unflagged', true, fhor_mordin_row_passes((object) ['mrf_outclassed_handicapper' => '0'], ['not_outclassed']));
mc_eq('not_outclassed keeps missing data', true, fhor_mordin_row_passes((object) [], ['not_outclassed']));
mc_eq('class_ok needs data', false, fhor_mordin_row_passes((object) [], ['class_ok']));
mc_eq('class_ok passes', true, fhor_mordin_row_passes((object) ['mrf_meets_class_requirement' => '1'], ['class_ok']));
mc_eq('debut_pocket salisbury', true, fhor_mordin_row_passes((object) ['mrf_salisbury_lambourn_pocket' => '1'], ['debut_pocket']));
mc_eq('debut_pocket none', false, fhor_mordin_row_passes((object) ['mrf_yarmouth_newmarket_pocket' => '0'], ['debut_pocket']));
mc_eq('stamina_ok rejects DI 6 at 12f G1', false, fhor_mordin_row_passes((object) [
    'distance_yards' => 2640, 'mrf_race_tier_rank' => '1', '_dosage_di' => 6.0, '_dosage_di_infinite' => false,
], ['stamina_ok']));
mc_eq('dosage_target passes when rule not applicable', true, fhor_mordin_row_passes((object) [
    'distance_yards' => 1320, 'mrf_race_tier_rank' => '1', '_dosage_di' => 6.0,
], ['dosage_target']));
mc_eq('dosage_target rejects DI 4 at 12f G1', false, fhor_mordin_row_passes((object) [
    'distance_yards' => 2640, 'mrf_race_tier_rank' => '1', '_dosage_di' => 4.0,
], ['dosage_target']));
mc_eq('unknown rule ignored', true, fhor_mordin_row_passes((object) [], ['bogus']));
mc_eq('rule keys', 9, count(fhor_mordin_rule_keys()));

echo $failures === 0 ? "\nAll Mordin class checks passed.\n" : "\n$failures Mordin class check(s) failed.\n";
exit($failures === 0 ? 0 : 1);

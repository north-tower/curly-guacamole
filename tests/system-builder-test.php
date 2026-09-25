<?php
/**
 * System Builder unit checks (no WordPress DB).
 *
 * Run from repo root:
 *   php tests/system-builder-test.php
 *   node tests/system-builder-filters-test.mjs
 *
 * Dosage engine only (also safe on production via WP-CLI):
 *   wp eval 'var_export(bricks_dosage_self_test());'
 */

$failures = 0;

function sb_test_assert($label, $cond) {
    global $failures;
    if ($cond) {
        echo "ok  $label\n";
        return;
    }
    $failures++;
    echo "FAIL $label\n";
}

function sb_test_eq($label, $expected, $actual) {
    sb_test_assert(
        $label . ' (expected ' . json_encode($expected) . ', got ' . json_encode($actual) . ')',
        $expected === $actual
    );
}

require dirname(__DIR__) . '/inc/pedigree-dosage.php';

$dosage_self = bricks_dosage_self_test();
sb_test_assert('bricks_dosage_self_test passed', !empty($dosage_self['passed']));
if (empty($dosage_self['passed'])) {
    foreach ($dosage_self['results'] ?? [] as $row) {
        if (empty($row['ok'])) {
            echo "  dosage: {$row['name']} expected {$row['expected']} got {$row['actual']}\n";
        }
    }
}

/**
 * Mirrors fhor_sb_row_passes_runner_filters dosage block in inc/system-builder.php.
 */
function sb_test_dosage_passes(array $row, array $filters) {
    $num = function ($val) {
        return is_numeric($val) ? (float) $val : null;
    };
    $between = function ($value, $min, $max) use ($num) {
        if ($min === '' && $max === '') {
            return true;
        }
        if ($value === null) {
            return false;
        }
        if ($min !== '' && $value < (float) $min) {
            return false;
        }
        if ($max !== '' && $value > (float) $max) {
            return false;
        }
        return true;
    };

    $di_min = $filters['di_min'] ?? '';
    $di_max = $filters['di_max'] ?? '';
    if ($di_min !== '' || $di_max !== '') {
        if (!empty($row['_dosage_di_infinite'])) {
            if ($di_max !== '') {
                return false;
            }
        } elseif (!$between($num($row['_dosage_di'] ?? null), $di_min, $di_max)) {
            return false;
        }
    }
    if (!$between($num($row['_dosage_cd'] ?? null), $filters['cd_min'] ?? '', $filters['cd_max'] ?? '')) {
        return false;
    }
    return true;
}

/**
 * Mirrors numeric filter sanitization for di/cd in fhor_sb_sanitize_filters.
 */
function sb_test_sanitize_dosage_num($raw) {
    $out = ['di_min' => '', 'di_max' => '', 'cd_min' => '', 'cd_max' => ''];
    foreach (array_keys($out) as $key) {
        if (isset($raw[$key]) && $raw[$key] !== '' && is_numeric($raw[$key])) {
            $out[$key] = (string) floatval($raw[$key]);
        }
    }
    return $out;
}

$sprinter = ['di_min' => '2', 'di_max' => '', 'cd_min' => '0.5', 'cd_max' => ''];
sb_test_assert('sprinter DI 2.50 passes', sb_test_dosage_passes(
    ['_dosage_di' => 2.5, '_dosage_cd' => 0.55, '_dosage_di_infinite' => false],
    $sprinter
));
sb_test_assert('sprinter DI 1.90 fails', !sb_test_dosage_passes(
    ['_dosage_di' => 1.9, '_dosage_cd' => 0.9, '_dosage_di_infinite' => false],
    $sprinter
));
sb_test_assert('sprinter CD 0.40 fails', !sb_test_dosage_passes(
    ['_dosage_di' => 2.2, '_dosage_cd' => 0.4, '_dosage_di_infinite' => false],
    $sprinter
));

$stamina = ['di_min' => '', 'di_max' => '1.4', 'cd_min' => '', 'cd_max' => '0.5'];
sb_test_assert('stamina DI 1.20 CD 0.40 passes', sb_test_dosage_passes(
    ['_dosage_di' => 1.2, '_dosage_cd' => 0.4, '_dosage_di_infinite' => false],
    $stamina
));
sb_test_assert('stamina DI 1.50 fails', !sb_test_dosage_passes(
    ['_dosage_di' => 1.5, '_dosage_cd' => 0.3, '_dosage_di_infinite' => false],
    $stamina
));
sb_test_assert('stamina Inf DI fails when di_max set', !sb_test_dosage_passes(
    ['_dosage_di' => null, '_dosage_cd' => 0.9, '_dosage_di_infinite' => true],
    $stamina
));
sb_test_assert('speed Inf DI passes when only di_min set', sb_test_dosage_passes(
    ['_dosage_di' => null, '_dosage_cd' => 0.9, '_dosage_di_infinite' => true],
    ['di_min' => '2', 'di_max' => '', 'cd_min' => '', 'cd_max' => '']
));
sb_test_assert('missing DI fails when di_min set', !sb_test_dosage_passes(
    ['_dosage_di' => null, '_dosage_cd' => 0.5, '_dosage_di_infinite' => false],
    ['di_min' => '1', 'di_max' => '', 'cd_min' => '', 'cd_max' => '']
));

$san = sb_test_sanitize_dosage_num(['cd_min' => '0', 'cd_max' => '0.5', 'di_min' => '2.00']);
sb_test_eq('sanitize keeps cd_min 0', '0', $san['cd_min']);
sb_test_eq('sanitize di_min 2.00', '2', $san['di_min']);

if ($failures > 0) {
    echo "\n$failures failure(s)\n";
    exit(1);
}

echo "\nAll system-builder unit checks passed.\n";

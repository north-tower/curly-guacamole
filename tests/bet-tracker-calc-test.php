<?php
/**
 * Settlement and staking checks. Run: php tests/bet-tracker-calc-test.php
 */

require dirname(__DIR__) . '/inc/bet-tracker-calc.php';

$failures = 0;

function bt_assert($label, $cond) {
    global $failures;
    if ($cond) {
        echo "ok  $label\n";
        return;
    }
    $failures++;
    echo "FAIL $label\n";
}

function bt_eq($label, $expected, $actual, $eps = 0.001) {
    $pass = is_numeric($expected) && is_numeric($actual)
        ? abs((float) $expected - (float) $actual) < $eps
        : $expected === $actual;
    bt_assert($label . ' (expected ' . json_encode($expected) . ', got ' . json_encode($actual) . ')', $pass);
}

bt_eq('5/2', 3.5, fhor_bt_parse_odds('5/2'));
bt_eq('3.5', 3.5, fhor_bt_parse_odds('3.5'));
bt_eq('evens', 2.0, fhor_bt_parse_odds('evens'));
bt_eq('10-1', 11.0, fhor_bt_parse_odds('10-1'));
bt_eq('4/1', 5.0, fhor_bt_parse_odds('4/1'));
bt_assert('reject 0.5', fhor_bt_parse_odds('0.5') === null);
bt_assert('reject 1', fhor_bt_parse_odds('1') === null);

bt_eq('lines patent', 7, fhor_bt_line_count('patent', 3, false));
bt_eq('lines patent ew', 14, fhor_bt_line_count('patent', 3, true));
bt_eq('lines trixie', 4, fhor_bt_line_count('trixie', 3, false));
bt_eq('lines yankee', 11, fhor_bt_line_count('yankee', 4, false));
bt_eq('lines lucky15', 15, fhor_bt_line_count('lucky15', 4, false));
bt_eq('lines canadian', 26, fhor_bt_line_count('canadian', 5, false));
bt_eq('lines lucky31', 31, fhor_bt_line_count('lucky31', 5, false));
bt_eq('lines heinz', 57, fhor_bt_line_count('heinz', 6, false));
bt_eq('lines lucky63', 63, fhor_bt_line_count('lucky63', 6, false));
bt_eq('lines single ew', 2, fhor_bt_line_count('single', 1, true));

$stakes = fhor_bt_line_stakes(5, 7);
bt_eq('penny split count', 7, count($stakes));
bt_eq('penny split sum', 5.0, array_sum($stakes));

$flat = [
    'mode' => 'flat',
    'starting_bankroll' => 100,
    'percentage' => 5,
    'point_value' => 5,
    'points_per_bet' => 1,
];

$ew_win = fhor_bt_settle_bet([
    'bet_type' => 'single',
    'each_way' => true,
    'ew_fraction' => 0.25,
    'legs' => [['odds' => 5.0, 'result' => 'won']],
], 10);
bt_eq('ew win profit', 25, $ew_win['profit']);
bt_eq('ew win result', 'won', $ew_win['result']);

$ew_place = fhor_bt_settle_bet([
    'bet_type' => 'single',
    'each_way' => true,
    'ew_fraction' => 0.25,
    'legs' => [['odds' => 5.0, 'result' => 'placed']],
], 10);
bt_eq('ew place profit', 0, $ew_place['profit']);
bt_eq('ew place result', 'placed', $ew_place['result']);
bt_eq('ew place returns', 10, $ew_place['returns_amount']);

$ew_lose = fhor_bt_settle_bet([
    'bet_type' => 'single',
    'each_way' => true,
    'ew_fraction' => 0.25,
    'legs' => [['odds' => 5.0, 'result' => 'lost']],
], 10);
bt_eq('ew lose profit', -10, $ew_lose['profit']);

$win_only_placed = fhor_bt_settle_bet([
    'bet_type' => 'single',
    'each_way' => false,
    'legs' => [['odds' => 5.0, 'result' => 'placed']],
], 5);
bt_eq('win bet placed is a loss', 'lost', $win_only_placed['result']);
bt_eq('win bet placed profit', -5, $win_only_placed['profit']);

$nr = fhor_bt_settle_bet([
    'bet_type' => 'double',
    'each_way' => false,
    'legs' => [
        ['odds' => 3.0, 'result' => 'won'],
        ['odds' => 4.0, 'result' => 'void'],
    ],
], 5);
bt_eq('nr double becomes single profit', 10, $nr['profit']);
bt_eq('nr double result', 'won', $nr['result']);

$trixie = fhor_bt_settle_bet([
    'bet_type' => 'trixie',
    'each_way' => false,
    'legs' => [
        ['odds' => 2.0, 'result' => 'won'],
        ['odds' => 2.0, 'result' => 'won'],
        ['odds' => 2.0, 'result' => 'won'],
    ],
], 4);
bt_eq('trixie lines', 4, $trixie['lines']);
bt_eq('trixie profit', 16, $trixie['profit']);

$pct = [
    'mode' => 'percentage',
    'starting_bankroll' => 100,
    'percentage' => 5,
    'point_value' => 1,
    'points_per_bet' => 1,
];

$book = [
    [
        'id' => 1,
        'placed_at' => '2026-09-01 14:00:00',
        'bet_type' => 'single',
        'each_way' => false,
        'ew_fraction' => 0.25,
        'stake_mode' => 'auto',
        'manual_total' => 0,
        'legs' => [['odds' => 3.0, 'result' => 'won']],
    ],
    [
        'id' => 2,
        'placed_at' => '2026-09-01 16:00:00',
        'bet_type' => 'single',
        'each_way' => false,
        'ew_fraction' => 0.25,
        'stake_mode' => 'auto',
        'manual_total' => 0,
        'legs' => [['odds' => 2.0, 'result' => 'lost']],
    ],
    [
        'id' => 3,
        'placed_at' => '2026-09-02 15:00:00',
        'bet_type' => 'single',
        'each_way' => false,
        'ew_fraction' => 0.25,
        'stake_mode' => 'auto',
        'manual_total' => 0,
        'legs' => [['odds' => 4.0, 'result' => 'lost']],
    ],
];

$proj = fhor_bt_project($book, $pct, 'percentage', true, '2026-09-02');
bt_eq('same day first stake', 5, $proj['bets'][0]['total_stake']);
bt_eq('same day second stake stays locked', 5, $proj['bets'][1]['total_stake']);
bt_eq('day 1 profit', 5, $proj['days']['2026-09-01']['profit']);
bt_eq('day 1 close', 105, $proj['days']['2026-09-01']['closing']);
bt_eq('day 2 stake is 5% of 105', 5.25, $proj['bets'][2]['total_stake']);
bt_eq('day 2 close', 99.75, $proj['bankroll']);
bt_eq('today budget', 5.25, $proj['today_budget']);

$pending = $book;
$pending[0]['legs'][0]['result'] = 'pending';
$pending[1]['legs'][0]['result'] = 'pending';
$pend = fhor_bt_project($pending, $pct, 'percentage', true, '2026-09-02');
bt_eq('pending does not change next day stake', 5, $pend['bets'][2]['total_stake']);
bt_eq('pending bankroll stays at start', 95, $pend['bankroll']);

$back = $book;
array_unshift($back, [
    'id' => 9,
    'placed_at' => '2026-08-31 13:00:00',
    'bet_type' => 'single',
    'each_way' => false,
    'ew_fraction' => 0.25,
    'stake_mode' => 'auto',
    'manual_total' => 0,
    'legs' => [['odds' => 2.0, 'result' => 'lost']],
]);
$replay = fhor_bt_project($back, $pct, 'percentage', true, '2026-09-02');
bt_eq('backdated stake', 5, $replay['bets'][0]['total_stake']);
bt_eq('forward stake shrinks', 4.75, $replay['bets'][1]['total_stake']);
bt_eq('same-day partner also shrinks', 4.75, $replay['bets'][2]['total_stake']);

$manual = $book;
$manual[2]['stake_mode'] = 'manual';
$manual[2]['manual_total'] = 10;
$man = fhor_bt_project($manual, $pct, 'percentage', true, '2026-09-02');
bt_eq('manual stake kept', 10, $man['bets'][2]['total_stake']);
bt_eq('manual loss hits bank', 95, $man['bankroll']);

$cmp = fhor_bt_compare($book, $pct, '2026-09-02');
$shadow_sum = 0.0;
$actual_sum = 0.0;
foreach ($cmp['bets'] as $bet) {
    $actual_sum += $bet['profit'];
    $shadow_sum += $bet['shadow_profit'];
}
bt_eq('actual dynamic profit', -0.25, $actual_sum);
bt_eq('shadow flat profit', 0, $shadow_sum);
bt_eq('shadow label', 'Flat 1-pt staking', $cmp['shadow_label']);

$quote = fhor_bt_quote($book, $pct, '2026-09-02 15:30', 'lucky15', false, 4, '2026-09-02');
bt_eq('quote lucky 15 still the day budget', 5.25, $quote['total']);
bt_eq('quote lines', 15, $quote['lines']);

echo $failures === 0 ? "\nAll checks passed.\n" : "\n$failures failed.\n";
exit($failures === 0 ? 0 : 1);

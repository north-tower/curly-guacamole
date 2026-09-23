<?php
/**
 * Pure bet-settlement and staking maths for the Fhorsite Bet Tracker.
 *
 * No WordPress calls. Two staking systems share one bet list:
 *
 * Flat: each line costs (point value × points per bet). Each-way and
 * full-covers therefore cost more because they contain more lines.
 *
 * Dynamic percentage: each bet, whatever its shape, risks one locked
 * stake for that calendar day. The stake is opening bankroll × percent.
 * Every bet on the same day uses that figure. The next day opens on the
 * previous day's settled closing bankroll. Pending bets do not move the
 * bankroll until every line is settled. A bet inserted on an earlier date
 * is replayed from the start, so later automatic stakes change with it.
 */

if (!function_exists('fhor_bt_bet_types')) {
    function fhor_bt_bet_types() {
        return [
            'single' => ['label' => 'Single', 'min' => 1, 'max' => 1, 'folds' => [1]],
            'double' => ['label' => 'Double', 'min' => 2, 'max' => 2, 'folds' => [2]],
            'treble' => ['label' => 'Treble', 'min' => 3, 'max' => 3, 'folds' => [3]],
            'acca' => ['label' => 'Accumulator (4-fold+)', 'min' => 4, 'max' => 8, 'folds' => 'full'],
            'trixie' => ['label' => 'Trixie', 'min' => 3, 'max' => 3, 'folds' => [2, 3]],
            'patent' => ['label' => 'Patent', 'min' => 3, 'max' => 3, 'folds' => [1, 2, 3]],
            'yankee' => ['label' => 'Yankee', 'min' => 4, 'max' => 4, 'folds' => [2, 3, 4]],
            'lucky15' => ['label' => 'Lucky 15', 'min' => 4, 'max' => 4, 'folds' => [1, 2, 3, 4]],
            'canadian' => ['label' => 'Canadian (Super Yankee)', 'min' => 5, 'max' => 5, 'folds' => [2, 3, 4, 5]],
            'lucky31' => ['label' => 'Lucky 31', 'min' => 5, 'max' => 5, 'folds' => [1, 2, 3, 4, 5]],
            'heinz' => ['label' => 'Heinz', 'min' => 6, 'max' => 6, 'folds' => [2, 3, 4, 5, 6]],
            'lucky63' => ['label' => 'Lucky 63', 'min' => 6, 'max' => 6, 'folds' => [1, 2, 3, 4, 5, 6]],
        ];
    }
}

if (!function_exists('fhor_bt_normalize_settings')) {
    function fhor_bt_normalize_settings($settings) {
        $settings = is_array($settings) ? $settings : [];
        $mode = (isset($settings['mode']) && $settings['mode'] === 'flat') ? 'flat' : 'percentage';
        $bank = isset($settings['starting_bankroll']) ? (float) $settings['starting_bankroll'] : 100.0;
        if ($bank < 0) {
            $bank = 0.0;
        }
        if ($bank > 10000000) {
            $bank = 10000000.0;
        }
        $pct = isset($settings['percentage']) ? (float) $settings['percentage'] : 5.0;
        if ($pct <= 0) {
            $pct = 5.0;
        }
        if ($pct > 100) {
            $pct = 100.0;
        }
        $point_value = isset($settings['point_value']) ? (float) $settings['point_value'] : 1.0;
        if ($point_value <= 0) {
            $point_value = 1.0;
        }
        if ($point_value > 100000) {
            $point_value = 100000.0;
        }
        $points = isset($settings['points_per_bet']) ? (float) $settings['points_per_bet'] : 1.0;
        if ($points <= 0) {
            $points = 1.0;
        }
        if ($points > 1000) {
            $points = 1000.0;
        }
        return [
            'mode' => $mode,
            'starting_bankroll' => round($bank, 2),
            'percentage' => round($pct, 2),
            'point_value' => round($point_value, 2),
            'points_per_bet' => round($points, 2),
        ];
    }
}

if (!function_exists('fhor_bt_trim_num')) {
    function fhor_bt_trim_num($n) {
        $s = number_format((float) $n, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
    }
}

if (!function_exists('fhor_bt_mode_label')) {
    function fhor_bt_mode_label($settings, $mode) {
        $settings = fhor_bt_normalize_settings($settings);
        if ($mode === 'flat') {
            return 'Flat ' . fhor_bt_trim_num($settings['points_per_bet']) . '-pt staking';
        }
        return 'Dynamic ' . fhor_bt_trim_num($settings['percentage']) . '%';
    }
}

if (!function_exists('fhor_bt_parse_odds')) {
    /**
     * Decimal ("3.5", "2") or fractional ("5/2", "10-1", "evens").
     * Returns decimal odds greater than 1, or null.
     */
    function fhor_bt_parse_odds($raw) {
        $raw = strtolower(trim((string) $raw));
        $raw = str_replace([' ', '–', '—'], ['', '-', '-'], $raw);
        if ($raw === '') {
            return null;
        }
        if ($raw === 'evs' || $raw === 'evens' || $raw === 'even') {
            return 2.0;
        }
        if (preg_match('/^(\d+(?:\.\d+)?)[\/\-](\d+(?:\.\d+)?)$/', $raw, $m)) {
            $den = (float) $m[2];
            if ($den <= 0) {
                return null;
            }
            $decimal = 1 + ((float) $m[1] / $den);
            return ($decimal > 1 && $decimal <= 5001) ? round($decimal, 4) : null;
        }
        if (!is_numeric($raw)) {
            return null;
        }
        $decimal = (float) $raw;
        if ($decimal <= 1 || $decimal > 5001) {
            return null;
        }
        return round($decimal, 4);
    }
}

if (!function_exists('fhor_bt_parse_datetime')) {
    function fhor_bt_parse_datetime($raw) {
        $raw = trim(str_replace('T', ' ', (string) $raw));
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2})(?::(\d{2}))?)?$/', $raw, $m)) {
            return '';
        }
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        $h = (isset($m[4]) && $m[4] !== '') ? (int) $m[4] : 12;
        $i = (isset($m[5]) && $m[5] !== '') ? (int) $m[5] : 0;
        $s = (isset($m[6]) && $m[6] !== '') ? (int) $m[6] : 0;
        if (!checkdate($mo, $d, $y) || $h > 23 || $i > 59 || $s > 59) {
            return '';
        }
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, $i, $s);
    }
}

if (!function_exists('fhor_bt_norm_result')) {
    function fhor_bt_norm_result($result) {
        $result = strtolower(trim((string) $result));
        if (in_array($result, ['pending', 'won', 'lost', 'void', 'placed'], true)) {
            return $result;
        }
        return 'pending';
    }
}

if (!function_exists('fhor_bt_ew_fraction')) {
    function fhor_bt_ew_fraction($raw) {
        $key = strtolower(trim((string) $raw));
        if ($key === '1/5' || $key === '0.2' || $key === '0.20' || $key === '0.2000') {
            return 0.2;
        }
        if ($key === '1/3' || $key === '0.3333' || $key === '0.333333') {
            return 1 / 3;
        }
        return 0.25;
    }
}

if (!function_exists('fhor_bt_ew_key')) {
    function fhor_bt_ew_key($fraction) {
        $fraction = (float) $fraction;
        if (abs($fraction - 0.2) < 0.001) {
            return '1/5';
        }
        if (abs($fraction - (1 / 3)) < 0.01) {
            return '1/3';
        }
        return '1/4';
    }
}

if (!function_exists('fhor_bt_nck')) {
    function fhor_bt_nck($n, $k) {
        $n = (int) $n;
        $k = (int) $k;
        if ($k < 0 || $n < 0 || $k > $n) {
            return 0;
        }
        if ($k === 0 || $k === $n) {
            return 1;
        }
        $k = min($k, $n - $k);
        $c = 1;
        for ($i = 0; $i < $k; $i++) {
            $c = ($c * ($n - $i)) / ($i + 1);
        }
        return (int) round($c);
    }
}

if (!function_exists('fhor_bt_folds')) {
    function fhor_bt_folds($type, $leg_count) {
        $types = fhor_bt_bet_types();
        if (!isset($types[$type])) {
            return [];
        }
        $folds = $types[$type]['folds'];
        $leg_count = (int) $leg_count;
        if ($folds === 'full') {
            return $leg_count > 0 ? [$leg_count] : [];
        }
        $out = [];
        foreach ($folds as $k) {
            if ((int) $k <= $leg_count) {
                $out[] = (int) $k;
            }
        }
        return $out;
    }
}

if (!function_exists('fhor_bt_line_count')) {
    function fhor_bt_line_count($type, $leg_count, $each_way) {
        $n = 0;
        foreach (fhor_bt_folds($type, $leg_count) as $k) {
            $n += fhor_bt_nck((int) $leg_count, (int) $k);
        }
        if ($each_way) {
            $n *= 2;
        }
        return $n;
    }
}

if (!function_exists('fhor_bt_combinations')) {
    function fhor_bt_combinations(array $items, $k) {
        $k = (int) $k;
        $items = array_values($items);
        $n = count($items);
        if ($k <= 0 || $k > $n) {
            return [];
        }
        $out = [];
        $choose = function ($start, array $chosen) use (&$choose, &$out, $items, $k, $n) {
            if (count($chosen) === $k) {
                $out[] = $chosen;
                return;
            }
            $need = $k - count($chosen);
            $last = $n - $need;
            for ($i = $start; $i <= $last; $i++) {
                $next = $chosen;
                $next[] = $items[$i];
                $choose($i + 1, $next);
            }
        };
        $choose(0, []);
        return $out;
    }
}

if (!function_exists('fhor_bt_line_stakes')) {
    /**
     * Split a total outlay across lines in whole pence.
     * Earlier lines absorb any remainder so the stakes sum to the total.
     */
    function fhor_bt_line_stakes($total, $lines) {
        $lines = max(1, (int) $lines);
        $cents = (int) round(((float) $total) * 100);
        if ($cents < 0) {
            $cents = 0;
        }
        $base = intdiv($cents, $lines);
        $rem = $cents - ($base * $lines);
        $out = [];
        for ($i = 0; $i < $lines; $i++) {
            $out[] = ($base + ($i < $rem ? 1 : 0)) / 100;
        }
        return $out;
    }
}

if (!function_exists('fhor_bt_settle_line')) {
    function fhor_bt_settle_line(array $legs, $stake, $place_book, $fraction) {
        $stake = round((float) $stake, 2);
        $active = [];
        foreach ($legs as $leg) {
            $result = fhor_bt_norm_result(isset($leg['result']) ? $leg['result'] : 'pending');
            if ($result === 'void') {
                continue;
            }
            $active[] = [
                'odds' => isset($leg['odds']) ? (float) $leg['odds'] : 0.0,
                'result' => $result,
            ];
        }
        if (!$active) {
            return ['status' => 'void', 'returns' => $stake, 'stake' => $stake, 'place_book' => (bool) $place_book];
        }
        foreach ($active as $leg) {
            if ($place_book) {
                if ($leg['result'] === 'lost') {
                    return ['status' => 'lost', 'returns' => 0.0, 'stake' => $stake, 'place_book' => true];
                }
            } elseif ($leg['result'] !== 'won' && $leg['result'] !== 'pending') {
                return ['status' => 'lost', 'returns' => 0.0, 'stake' => $stake, 'place_book' => false];
            }
        }
        foreach ($active as $leg) {
            if ($leg['result'] === 'pending') {
                return ['status' => 'pending', 'returns' => 0.0, 'stake' => $stake, 'place_book' => (bool) $place_book];
            }
        }
        $mult = 1.0;
        foreach ($active as $leg) {
            $odds = $leg['odds'] > 1 ? $leg['odds'] : 1.0;
            if ($place_book) {
                $mult *= (1 + (($odds - 1) * (float) $fraction));
            } else {
                $mult *= $odds;
            }
        }
        return [
            'status' => 'won',
            'returns' => round($stake * $mult, 2),
            'stake' => $stake,
            'place_book' => (bool) $place_book,
        ];
    }
}

if (!function_exists('fhor_bt_settle_bet')) {
    function fhor_bt_settle_bet(array $bet, $total_stake) {
        $type = isset($bet['bet_type']) ? (string) $bet['bet_type'] : 'single';
        $legs = isset($bet['legs']) && is_array($bet['legs']) ? array_values($bet['legs']) : [];
        $each_way = !empty($bet['each_way']);
        $fraction = isset($bet['ew_fraction']) ? (float) $bet['ew_fraction'] : 0.25;
        if ($fraction <= 0 || $fraction > 1) {
            $fraction = 0.25;
        }
        $combos = [];
        foreach (fhor_bt_folds($type, count($legs)) as $k) {
            foreach (fhor_bt_combinations($legs, $k) as $combo) {
                $combos[] = ['legs' => $combo, 'place_book' => false];
                if ($each_way) {
                    $combos[] = ['legs' => $combo, 'place_book' => true];
                }
            }
        }
        $line_count = count($combos);
        if ($line_count < 1) {
            return [
                'result' => 'void',
                'lines' => 0,
                'total_stake' => 0.0,
                'unit_stake' => 0.0,
                'returns_amount' => 0.0,
                'profit' => 0.0,
            ];
        }
        $total_stake = round(max(0, (float) $total_stake), 2);
        $stakes = fhor_bt_line_stakes($total_stake, $line_count);
        $returns = 0.0;
        $any_pending = false;
        $any_win = false;
        $any_place = false;
        $all_void = true;
        foreach ($combos as $i => $combo) {
            $settled = fhor_bt_settle_line($combo['legs'], $stakes[$i], $combo['place_book'], $fraction);
            if ($settled['status'] === 'pending') {
                $any_pending = true;
                $all_void = false;
                continue;
            }
            if ($settled['status'] !== 'void') {
                $all_void = false;
            }
            $returns += $settled['returns'];
            if ($settled['status'] === 'won' && !empty($settled['place_book'])) {
                $any_place = true;
            } elseif ($settled['status'] === 'won') {
                $any_win = true;
            }
        }
        $returns = round($returns, 2);
        if ($any_pending) {
            $result = 'pending';
            $profit = 0.0;
            $book_returns = 0.0;
        } elseif ($all_void) {
            $result = 'void';
            $book_returns = $total_stake;
            $profit = 0.0;
        } else {
            $book_returns = $returns;
            $profit = round($book_returns - $total_stake, 2);
            if ($any_win) {
                $result = 'won';
            } elseif ($any_place) {
                $result = 'placed';
            } else {
                $result = 'lost';
            }
        }
        return [
            'result' => $result,
            'lines' => $line_count,
            'total_stake' => $total_stake,
            'unit_stake' => $stakes[0],
            'returns_amount' => $book_returns,
            'profit' => $profit,
        ];
    }
}

if (!function_exists('fhor_bt_flat_line_stake')) {
    function fhor_bt_flat_line_stake(array $settings) {
        return round(max(0, $settings['point_value'] * $settings['points_per_bet']), 2);
    }
}

if (!function_exists('fhor_bt_percentage_budget')) {
    function fhor_bt_percentage_budget($opening, array $settings) {
        $opening = max(0, (float) $opening);
        return round($opening * ($settings['percentage'] / 100), 2);
    }
}

if (!function_exists('fhor_bt_total_for_bet')) {
    function fhor_bt_total_for_bet($opening, array $settings, $mode, $lines, $stake_mode, $manual_total, $respect_manual) {
        $lines = max(1, (int) $lines);
        if ($respect_manual && $stake_mode === 'manual') {
            return round(max(0, (float) $manual_total), 2);
        }
        if ($mode === 'flat') {
            return round(fhor_bt_flat_line_stake($settings) * $lines, 2);
        }
        return fhor_bt_percentage_budget($opening, $settings);
    }
}

if (!function_exists('fhor_bt_project')) {
    /**
     * Replay bets in date order and stamp each one with the stake that
     * system would have used, plus settled returns.
     */
    function fhor_bt_project(array $bets, array $settings, $mode = null, $respect_manual = true, $today = null) {
        $settings = fhor_bt_normalize_settings($settings);
        if ($mode !== 'flat' && $mode !== 'percentage') {
            $mode = $settings['mode'];
        }
        $today = $today ? substr((string) $today, 0, 10) : date('Y-m-d');
        $sorted = array_values($bets);
        usort($sorted, function ($a, $b) {
            $c = strcmp((string) ($a['placed_at'] ?? ''), (string) ($b['placed_at'] ?? ''));
            if ($c !== 0) {
                return $c;
            }
            return (int) ($a['id'] ?? 0) - (int) ($b['id'] ?? 0);
        });

        $groups = [];
        foreach ($sorted as $bet) {
            $placed = (string) ($bet['placed_at'] ?? '');
            $date = strlen($placed) >= 10 ? substr($placed, 0, 10) : $today;
            if (!isset($groups[$date])) {
                $groups[$date] = [];
            }
            $groups[$date][] = $bet;
        }

        $opening = $settings['starting_bankroll'];
        $days = [];
        $out = [];
        foreach ($groups as $date => $day_bets) {
            $day_profit = 0.0;
            foreach ($day_bets as $bet) {
                $leg_count = isset($bet['legs']) && is_array($bet['legs']) ? count($bet['legs']) : 0;
                $lines = fhor_bt_line_count(
                    isset($bet['bet_type']) ? $bet['bet_type'] : 'single',
                    $leg_count,
                    !empty($bet['each_way'])
                );
                $stake_mode = (isset($bet['stake_mode']) && $bet['stake_mode'] === 'manual') ? 'manual' : 'auto';
                $total = fhor_bt_total_for_bet(
                    $opening,
                    $settings,
                    $mode,
                    $lines,
                    $stake_mode,
                    isset($bet['manual_total']) ? $bet['manual_total'] : 0,
                    $respect_manual
                );
                $settled = fhor_bt_settle_bet($bet, $total);
                $bet['stake_mode'] = $stake_mode;
                $bet['lines'] = $settled['lines'];
                $bet['total_stake'] = $settled['total_stake'];
                $bet['unit_stake'] = $settled['unit_stake'];
                $bet['returns_amount'] = $settled['returns_amount'];
                $bet['profit'] = $settled['profit'];
                $bet['result'] = $settled['result'];
                if ($settled['result'] !== 'pending') {
                    $day_profit += $settled['profit'];
                }
                $out[] = $bet;
            }
            $day_profit = round($day_profit, 2);
            $closing = round($opening + $day_profit, 2);
            $days[$date] = [
                'opening' => round($opening, 2),
                'profit' => $day_profit,
                'closing' => $closing,
            ];
            $opening = $closing;
        }

        $bankroll = round($opening, 2);
        $today_opening = $settings['starting_bankroll'];
        if (isset($days[$today])) {
            $today_opening = $days[$today]['opening'];
        } else {
            foreach ($days as $date => $meta) {
                if ($date < $today) {
                    $today_opening = $meta['closing'];
                }
            }
        }

        return [
            'bets' => $out,
            'days' => $days,
            'bankroll' => $bankroll,
            'today' => $today,
            'today_opening' => round($today_opening, 2),
            'today_budget' => fhor_bt_percentage_budget($today_opening, $settings),
            'flat_line' => fhor_bt_flat_line_stake($settings),
            'mode' => $mode,
            'settings' => $settings,
        ];
    }
}

if (!function_exists('fhor_bt_opening_on')) {
    function fhor_bt_opening_on(array $project, $date) {
        $date = substr((string) $date, 0, 10);
        $settings = $project['settings'];
        if (isset($project['days'][$date])) {
            return (float) $project['days'][$date]['opening'];
        }
        $opening = (float) $settings['starting_bankroll'];
        foreach ($project['days'] as $day => $meta) {
            if ($day < $date) {
                $opening = (float) $meta['closing'];
            }
        }
        return round($opening, 2);
    }
}

if (!function_exists('fhor_bt_quote')) {
    function fhor_bt_quote(array $bets, array $settings, $date, $type, $each_way, $leg_count, $today = null) {
        $settings = fhor_bt_normalize_settings($settings);
        $placed = fhor_bt_parse_datetime($date);
        $day = $placed !== '' ? substr($placed, 0, 10) : substr((string) $date, 0, 10);
        $project = fhor_bt_project($bets, $settings, $settings['mode'], true, $today);
        $opening = fhor_bt_opening_on($project, $day);
        $types = fhor_bt_bet_types();
        $leg_count = (int) $leg_count;
        $valid = isset($types[$type]) && $leg_count >= $types[$type]['min'] && $leg_count <= $types[$type]['max'];
        $lines = $valid ? fhor_bt_line_count($type, $leg_count, $each_way) : 0;
        $total = 0.0;
        if ($lines > 0) {
            $total = fhor_bt_total_for_bet($opening, $settings, $settings['mode'], $lines, 'auto', 0, false);
        }
        $per_line = ($lines > 0) ? fhor_bt_line_stakes($total, $lines)[0] : 0.0;
        return [
            'date' => $day,
            'opening' => $opening,
            'lines' => $lines,
            'total' => $total,
            'per_line' => $per_line,
            'valid' => $valid,
            'mode' => $settings['mode'],
            'label' => fhor_bt_mode_label($settings, $settings['mode']),
            'flat_line' => $project['flat_line'],
            'today_budget' => $project['today_budget'],
            'today_opening' => $project['today_opening'],
        ];
    }
}

if (!function_exists('fhor_bt_compare')) {
    function fhor_bt_compare(array $bets, array $settings, $today = null) {
        $settings = fhor_bt_normalize_settings($settings);
        $actual_mode = $settings['mode'];
        $shadow_mode = $actual_mode === 'flat' ? 'percentage' : 'flat';
        $actual = fhor_bt_project($bets, $settings, $actual_mode, true, $today);
        $shadow = fhor_bt_project($bets, $settings, $shadow_mode, false, $today);
        $shadow_by_id = [];
        foreach ($shadow['bets'] as $bet) {
            $shadow_by_id[(string) ($bet['id'] ?? '')] = $bet;
        }
        foreach ($actual['bets'] as $i => $bet) {
            $other = isset($shadow_by_id[(string) ($bet['id'] ?? '')]) ? $shadow_by_id[(string) $bet['id']] : null;
            $actual['bets'][$i]['shadow_total_stake'] = $other ? $other['total_stake'] : 0.0;
            $actual['bets'][$i]['shadow_profit'] = $other ? $other['profit'] : 0.0;
            $actual['bets'][$i]['shadow_returns'] = $other ? $other['returns_amount'] : 0.0;
            $actual['bets'][$i]['shadow_result'] = $other ? $other['result'] : 'pending';
        }
        return [
            'bets' => $actual['bets'],
            'bankroll' => $actual['bankroll'],
            'today' => $actual['today'],
            'today_opening' => $actual['today_opening'],
            'today_budget' => $actual['today_budget'],
            'flat_line' => $actual['flat_line'],
            'actual_mode' => $actual_mode,
            'shadow_mode' => $shadow_mode,
            'actual_label' => fhor_bt_mode_label($settings, $actual_mode),
            'shadow_label' => fhor_bt_mode_label($settings, $shadow_mode),
            'settings' => $settings,
        ];
    }
}

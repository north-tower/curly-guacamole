<?php
/**
 * Task 10 Mordin backtest batch — shared by CLI and /mordin-backtest/ admin page.
 */

if (!function_exists('mordin_bt_scenarios')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function mordin_bt_scenarios() {
        $common = [
            'days' => 1825,
            'bet' => 'win',
            'pick_mode' => 'all',
            'race_type' => ['Flat'],
        ];

        $flat_class1 = array_merge($common, ['class' => '1']);
        $flat_class1_10f = array_merge($flat_class1, ['dist_f_min' => '10']);

        return [
            [
                'key' => 'not_outclassed',
                'rule' => 'not_outclassed',
                'label' => 'Not outclassed (handicaps)',
                'polarity' => 'negative',
                'min_removed' => 300,
                'base' => array_merge($common, ['handicap' => 'yes', 'age_min' => '3']),
            ],
            [
                'key' => 'class_ok',
                'rule' => 'class_ok',
                'label' => 'Won at this class or higher',
                'polarity' => 'positive',
                'min_removed' => 0,
                'base' => $flat_class1,
            ],
            [
                'key' => 'not_juv_eliminated',
                'rule' => 'not_juv_eliminated',
                'label' => 'No nursery/claimer/seller form (2yo Group)',
                'polarity' => 'negative',
                'min_removed' => 50,
                'base' => array_merge($flat_class1, ['age_range' => '2']),
            ],
            [
                'key' => 'not_northern',
                'rule' => 'not_northern',
                'label' => 'No northern minor form (2yo G1)',
                'polarity' => 'negative',
                'min_removed' => 50,
                'base' => array_merge($flat_class1, ['age_range' => '2']),
            ],
            [
                'key' => 'clear_2nd_age2',
                'rule' => 'clear_2nd',
                'label' => 'Clear 2nd (maidens, age band 2)',
                'polarity' => 'positive',
                'min_removed' => 0,
                'base' => array_merge($common, ['handicap' => 'no', 'age_range' => '2']),
            ],
            [
                'key' => 'clear_2nd_age3',
                'rule' => 'clear_2nd',
                'label' => 'Clear 2nd (maidens, age band 3)',
                'polarity' => 'positive',
                'min_removed' => 0,
                'base' => array_merge($common, ['handicap' => 'no', 'age_range' => '3']),
            ],
            [
                'key' => 'debut_pocket_yarmouth',
                'rule' => 'debut_pocket',
                'label' => 'Debut pocket (Yarmouth)',
                'polarity' => 'positive',
                'min_removed' => 0,
                'base' => array_merge($common, ['age_range' => '2', 'course' => 'Yarmouth']),
            ],
            [
                'key' => 'debut_pocket_salisbury',
                'rule' => 'debut_pocket',
                'label' => 'Debut pocket (Salisbury)',
                'polarity' => 'positive',
                'min_removed' => 0,
                'base' => array_merge($common, ['age_range' => '2', 'course' => 'Salisbury']),
            ],
            [
                'key' => 'group_trainer',
                'rule' => 'group_trainer',
                'label' => 'Group trainer (15%+)',
                'polarity' => 'positive',
                'min_removed' => 0,
                'base' => $flat_class1,
            ],
            [
                'key' => 'stamina_ok',
                'rule' => 'stamina_ok',
                'label' => 'No stamina doubt (10f+ Class 1)',
                'polarity' => 'negative',
                'min_removed' => 300,
                'base' => $flat_class1_10f,
            ],
            [
                'key' => 'dosage_target',
                'rule' => 'dosage_target',
                'label' => 'DI ≤ 3.30 (10f+ Class 1)',
                'polarity' => 'positive',
                'min_removed' => 0,
                'base' => $flat_class1_10f,
            ],
        ];
    }
}

if (!function_exists('mordin_bt_run')) {
    /**
     * @param array<string, mixed> $base
     * @param list<string> $mordin
     * @return array<string, mixed>
     */
    function mordin_bt_run(array $base, array $mordin) {
        $raw = $base;
        $raw['mordin'] = $mordin;
        $filters = fhor_sb_sanitize_filters($raw);
        $result = fhor_sb_run_backtest($filters);
        $isp = $result['isp'] ?? $result;
        return [
            'from' => $result['from'] ?? '',
            'to' => $result['to'] ?? '',
            'bets' => (int) ($isp['bets'] ?? $result['bets'] ?? 0),
            'wins' => (int) ($isp['wins'] ?? $result['wins'] ?? 0),
            'profit' => (float) ($isp['profit'] ?? $result['profit'] ?? 0),
            'roi' => round((float) ($isp['roi'] ?? $result['roi'] ?? 0), 1),
            'win_rate' => round((float) ($isp['win_rate'] ?? $result['win_rate'] ?? 0), 1),
            'capped' => !empty($result['capped']),
            'zero_hint' => (string) ($result['zero_hint'] ?? ''),
            'runners_scanned' => (int) ($result['runners_scanned'] ?? 0),
            'qualifiers' => (int) ($result['qualifiers'] ?? 0),
            'days' => (int) ($filters['days'] ?? 0),
        ];
    }
}

if (!function_exists('mordin_bt_removed_stats')) {
    /**
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $with
     * @return array{removed_bets:int,removed_roi:?float,gap:?float}
     */
    function mordin_bt_removed_stats(array $baseline, array $with) {
        $removed_bets = $baseline['bets'] - $with['bets'];
        $removed_profit = $baseline['profit'] - $with['profit'];
        $removed_roi = $removed_bets > 0 ? round($removed_profit / $removed_bets * 100, 1) : null;
        $gap = $removed_roi !== null ? round($with['roi'] - $removed_roi, 1) : null;
        return [
            'removed_bets' => $removed_bets,
            'removed_roi' => $removed_roi,
            'gap' => $gap,
        ];
    }
}

if (!function_exists('mordin_bt_verdict')) {
    /**
     * @param array<string, mixed> $scenario
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $with
     * @return array{verdict:string,badge:string,reason:string}
     */
    function mordin_bt_verdict(array $scenario, array $baseline, array $with) {
        $polarity = $scenario['polarity'];
        $min_removed = (int) $scenario['min_removed'];
        $removed = mordin_bt_removed_stats($baseline, $with);

        if ($polarity === 'positive') {
            if ($with['bets'] < 100) {
                return [
                    'verdict' => 'Drop badge',
                    'badge' => 'SB filter only',
                    'reason' => "With-rule bets {$with['bets']} (< 100)",
                ];
            }
            $delta = round($with['roi'] - $baseline['roi'], 1);
            if ($delta >= 10) {
                return [
                    'verdict' => 'Keep badge',
                    'badge' => 'Keep',
                    'reason' => "ROI +{$delta} pts vs baseline",
                ];
            }
            return [
                'verdict' => 'Drop badge',
                'badge' => 'SB filter only',
                'reason' => "ROI +{$delta} pts vs baseline (need +10)",
            ];
        }

        if ($removed['removed_bets'] < $min_removed) {
            return [
                'verdict' => 'Drop badge',
                'badge' => 'SB filter only',
                'reason' => "Removed {$removed['removed_bets']} bets (< {$min_removed})",
            ];
        }
        if ($removed['gap'] === null || $removed['gap'] < 10) {
            $gap = $removed['gap'] === null ? 'n/a' : (string) $removed['gap'];
            return [
                'verdict' => 'Drop badge',
                'badge' => 'SB filter only',
                'reason' => "Removed vs remainder gap {$gap} (need ≥10)",
            ];
        }
        return [
            'verdict' => 'Keep badge',
            'badge' => 'Keep',
            'reason' => "Removed ROI {$removed['removed_roi']}% vs remainder {$with['roi']}% (gap {$removed['gap']})",
        ];
    }
}

if (!function_exists('mordin_bt_preflight')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function mordin_bt_preflight() {
        if (!function_exists('fhor_sb_run_backtest')) {
            return ['ok' => false, 'message' => 'System Builder backtest engine is not loaded.'];
        }
        if (!function_exists('fhor_sb_table_exists') || !fhor_sb_table_exists('mordin_runner_flags')) {
            return ['ok' => false, 'message' => 'Table mordin_runner_flags is missing. Deploy Task 4 and run mordin_runner_flags_UPDATE().'];
        }
        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('mordin_bt_run_batch')) {
    /**
     * Run all Task 10 scenarios (baseline + with rule each).
     *
     * @param int $days Lookback days (capped by fhor_sb_max_lookback_days for current user).
     * @return array<string, mixed>
     */
    function mordin_bt_run_batch($days = 1825) {
        $preflight = mordin_bt_preflight();
        if (!$preflight['ok']) {
            return ['ok' => false, 'error' => $preflight['message']];
        }

        $max_days = fhor_sb_max_lookback_days();
        $days = max(7, min(1825, (int) $days));
        if ($days > $max_days) {
            $days = $max_days;
        }

        $started = gmdate('c');
        $rows_out = [];
        $scenarios = mordin_bt_scenarios();
        $progress = [];

        foreach ($scenarios as $i => $scenario) {
            $base = $scenario['base'];
            $base['days'] = $days;
            $rule = $scenario['rule'];

            $baseline = mordin_bt_run($base, []);
            $with = mordin_bt_run($base, [$rule]);
            $removed = mordin_bt_removed_stats($baseline, $with);
            $verdict = mordin_bt_verdict($scenario, $baseline, $with);

            $rows_out[] = [
                'scenario' => [
                    'key' => $scenario['key'],
                    'rule' => $scenario['rule'],
                    'label' => $scenario['label'],
                    'polarity' => $scenario['polarity'],
                ],
                'baseline' => $baseline,
                'with' => $with,
                'removed' => $removed,
                'verdict' => $verdict,
            ];
            $progress[] = [
                'index' => $i + 1,
                'total' => count($scenarios),
                'key' => $scenario['key'],
                'done' => true,
            ];
        }

        $meta = [
            'started' => $started,
            'finished' => gmdate('c'),
            'days' => $days,
            'max_lookback' => $max_days,
            'premium' => fhor_sb_is_premium(),
            'user_id' => get_current_user_id(),
            'from' => $rows_out[0]['baseline']['from'] ?? '',
            'to' => $rows_out[0]['baseline']['to'] ?? '',
        ];

        $markdown = mordin_bt_build_markdown($rows_out, $meta);
        $notes_table = mordin_bt_build_notes_table_markdown($rows_out);

        return [
            'ok' => true,
            'meta' => $meta,
            'rows' => $rows_out,
            'progress' => $progress,
            'markdown' => $markdown,
            'notes_table_markdown' => $notes_table,
        ];
    }
}

if (!function_exists('mordin_bt_build_notes_table_markdown')) {
    /**
     * @param array<int, array<string, mixed>> $rows_out
     */
    function mordin_bt_build_notes_table_markdown(array $rows_out) {
        $lines = [
            '| Rule | Bets | Strike % | ROI % | Baseline ROI % | Verdict |',
            '| --- | ---: | ---: | ---: | ---: | --- |',
        ];
        foreach ($rows_out as $row) {
            $s = $row['scenario'];
            $w = $row['with'];
            $b = $row['baseline'];
            $v = $row['verdict'];
            $rule_label = $s['rule'];
            if ($s['key'] !== $s['rule']) {
                $rule_label = $s['rule'] . ' · ' . $s['label'];
            }
            $lines[] = sprintf(
                '| %s | %d | %.1f | %.1f | %.1f | %s |',
                $rule_label,
                $w['bets'],
                $w['win_rate'],
                $w['roi'],
                $b['roi'],
                $v['verdict']
            );
        }
        return implode("\n", $lines);
    }
}

if (!function_exists('mordin_bt_build_markdown')) {
    /**
     * @param array<int, array<string, mixed>> $rows_out
     * @param array<string, mixed> $meta
     */
    function mordin_bt_build_markdown(array $rows_out, array $meta) {
        $md = [];
        $md[] = '# Mordin Task 10 backtest report';
        $md[] = '';
        $md[] = '- **Generated:** ' . ($meta['finished'] ?? gmdate('c'));
        $md[] = '- **Lookback days:** ' . ($meta['days'] ?? '') . ' (' . ($meta['from'] ?? '') . ' → ' . ($meta['to'] ?? '') . ')';
        $md[] = '- **Premium lookback:** ' . (!empty($meta['premium']) ? 'yes' : 'no') . ' (max ' . ($meta['max_lookback'] ?? '') . ' days)';
        $md[] = '- **Engine:** `fhor_sb_run_backtest()` / Industry SP';
        $md[] = '';
        $md[] = '## Verdict table (paste into `mordin_class_NOTES.md`)';
        $md[] = '';
        $md[] = mordin_bt_build_notes_table_markdown($rows_out);
        $md[] = '';
        $md[] = '## Detail';
        $md[] = '';

        foreach ($rows_out as $row) {
            $s = $row['scenario'];
            $b = $row['baseline'];
            $w = $row['with'];
            $r = $row['removed'];
            $v = $row['verdict'];
            $md[] = '### ' . $s['key'] . ' — ' . $s['label'];
            $md[] = '';
            $md[] = '- Polarity: **' . $s['polarity'] . '** · Mordin rule `' . $s['rule'] . '`';
            $md[] = '- Baseline: **' . $b['bets'] . '** bets, **' . $b['win_rate'] . '%** strike, **' . $b['roi'] . '%** ROI';
            $md[] = '- With rule: **' . $w['bets'] . '** bets, **' . $w['win_rate'] . '%** strike, **' . $w['roi'] . '%** ROI';
            if ($s['polarity'] === 'negative') {
                $roi_rem = $r['removed_roi'] === null ? 'n/a' : $r['removed_roi'] . '%';
                $md[] = '- Removed: **' . $r['removed_bets'] . '** bets, ROI **' . $roi_rem . '**, gap **' . ($r['gap'] === null ? 'n/a' : $r['gap']) . '**';
            }
            if (!empty($b['capped']) || !empty($w['capped'])) {
                $md[] = '- **Warning:** scan cap hit — tighten filters or shorten lookback.';
            }
            $hint = trim(($w['zero_hint'] ?? '') . ' ' . ($b['zero_hint'] ?? ''));
            if ($hint !== '') {
                $md[] = '- Note: ' . $hint;
            }
            $md[] = '- **Badge:** ' . $v['verdict'] . ' — ' . $v['reason'];
            $md[] = '';
        }

        return implode("\n", $md) . "\n";
    }
}

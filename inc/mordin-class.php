<?php
/**
 * Mordin class analysis: reads mordin_runner_flags (built by mordin_runner_flags_UPDATE.sql),
 * applies the Dosage stamina ceiling, and exposes racecard badges and System Builder rules.
 */

if (!function_exists('fhor_mordin_rule_keys')) {
    function fhor_mordin_rule_keys() {
        return [
            'class_ok', 'not_outclassed', 'not_juv_eliminated', 'not_northern', 'clear_2nd',
            'debut_pocket', 'group_trainer', 'stamina_ok', 'dosage_target',
        ];
    }
}

if (!function_exists('fhor_mordin_sb_columns')) {
    function fhor_mordin_sb_columns() {
        return [
            'meets_class_requirement', 'outclassed_handicapper', 'eliminate_juvenile', 'downgrade_northern_form',
            'high_value_maiden_placing', 'yarmouth_newmarket_pocket', 'salisbury_lambourn_pocket',
            'trainer_group_elite', 'race_tier_rank',
        ];
    }
}

if (!function_exists('fhor_mordin_flags_for_race')) {
    function fhor_mordin_flags_for_race($race_id) {
        global $wpdb;
        $race_id = intval($race_id);
        if ($race_id <= 0 || !isset($wpdb)) {
            return [];
        }
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'mordin_runner_flags')) !== 'mordin_runner_flags') {
            return [];
        }
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM mordin_runner_flags WHERE race_id = %d', $race_id),
            ARRAY_A
        );
        $out = [];
        foreach ((array) $rows as $row) {
            $out[intval($row['runner_id'])] = $row;
        }
        return $out;
    }
}

if (!function_exists('fhor_mordin_stamina')) {
    /**
     * Class 1 (tier rank 1-4) races of 10f+ only.
     *
     * @return array{applies:bool,exceeded:bool,target:bool}
     */
    function fhor_mordin_stamina($distance_yards, $tier_rank, $di, $di_infinite) {
        $applies = is_numeric($distance_yards) && (int) $distance_yards >= 2200
            && is_numeric($tier_rank) && (int) $tier_rank >= 1 && (int) $tier_rank <= 4;
        if (!$applies) {
            return ['applies' => false, 'exceeded' => false, 'target' => false];
        }
        $di_num = is_numeric($di) ? (float) $di : null;
        return [
            'applies' => true,
            'exceeded' => !empty($di_infinite) || ($di_num !== null && $di_num > 5.0),
            'target' => empty($di_infinite) && $di_num !== null && $di_num <= 3.30,
        ];
    }
}

if (!function_exists('fhor_mordin_badges')) {
    function fhor_mordin_badges($flags, array $stamina) {
        if (is_object($flags)) {
            $flags = get_object_vars($flags);
        }
        $flags = is_array($flags) ? $flags : [];
        $on = function ($key) use ($flags) {
            return isset($flags[$key]) && (string) $flags[$key] === '1';
        };

        $badges = [];
        if ($on('high_value_maiden_placing')) {
            $badges[] = ['label' => 'Clear 2nd', 'tone' => 'pos', 'title' => 'Finished 2nd last time, 5+ lengths clear of the 3rd.'];
        }
        if ($on('meets_class_requirement')) {
            $badges[] = ['label' => 'Class proven', 'tone' => 'pos', 'title' => 'Has won at this class tier or higher.'];
        }
        if ($on('yarmouth_newmarket_pocket')) {
            $badges[] = ['label' => 'Yarmouth debut', 'tone' => 'pos', 'title' => 'Newmarket-trained 2yo debuting at Yarmouth (high strike-rate pocket).'];
        }
        if ($on('salisbury_lambourn_pocket')) {
            $badges[] = ['label' => 'Salisbury debut', 'tone' => 'pos', 'title' => 'Lambourn-trained 2yo debuting at Salisbury (above-average strike-rate pocket).'];
        }
        if ($on('trainer_group_elite')) {
            $rate = isset($flags['trainer_group_strike_rate']) ? (string) $flags['trainer_group_strike_rate'] : '';
            $badges[] = ['label' => 'Group trainer', 'tone' => 'pos', 'title' => 'Trainer Group/Listed strike rate ' . $rate . '% over 5 years.'];
        }
        if (!empty($stamina['target'])) {
            $badges[] = ['label' => 'DI ≤ 3.30', 'tone' => 'pos', 'title' => 'Dosage Index within the stamina target for a 10f+ Class 1 race.'];
        }
        if ($on('outclassed_handicapper')) {
            $badges[] = ['label' => 'Outclassed', 'tone' => 'neg', 'title' => 'No top-3 or within-3-lengths run at this class or higher this season.'];
        }
        if ($on('eliminate_juvenile')) {
            $badges[] = ['label' => 'Low-grade 2yo form', 'tone' => 'neg', 'title' => 'Has run in a nursery, claimer or seller.'];
        }
        if ($on('downgrade_northern_form')) {
            $badges[] = ['label' => 'Northern form', 'tone' => 'neg', 'title' => 'Has run in a non-Pattern race at a northern track.'];
        }
        if (!empty($stamina['exceeded'])) {
            $badges[] = ['label' => 'Stamina doubt', 'tone' => 'neg', 'title' => 'Dosage Index above 5.00 in a 10f+ Class 1 race.'];
        }
        if ($on('debut_penalty')) {
            $badges[] = ['label' => 'Debut', 'tone' => 'neg', 'title' => 'Juvenile debutant outside the Yarmouth/Salisbury pockets.'];
        }
        if (isset($flags['foreign_unquantified_runs']) && (int) $flags['foreign_unquantified_runs'] > 0) {
            $badges[] = ['label' => 'Foreign form', 'tone' => 'warn', 'title' => 'Has form abroad that cannot be converted to a UK class.'];
        }
        return $badges;
    }
}

if (!function_exists('fhor_mordin_row_passes')) {
    function fhor_mordin_row_passes($row, array $wanted) {
        if (!$wanted) {
            return true;
        }
        $get = function ($key) use ($row) {
            return is_object($row) ? ($row->{$key} ?? null) : ($row[$key] ?? null);
        };
        $is1 = function ($key) use ($get) {
            return (string) $get($key) === '1';
        };
        foreach ($wanted as $rule) {
            switch ($rule) {
                case 'class_ok':
                    if (!$is1('mrf_meets_class_requirement')) {
                        return false;
                    }
                    break;
                case 'not_outclassed':
                    if ($is1('mrf_outclassed_handicapper')) {
                        return false;
                    }
                    break;
                case 'not_juv_eliminated':
                    if ($is1('mrf_eliminate_juvenile')) {
                        return false;
                    }
                    break;
                case 'not_northern':
                    if ($is1('mrf_downgrade_northern_form')) {
                        return false;
                    }
                    break;
                case 'clear_2nd':
                    if (!$is1('mrf_high_value_maiden_placing')) {
                        return false;
                    }
                    break;
                case 'debut_pocket':
                    if (!$is1('mrf_yarmouth_newmarket_pocket') && !$is1('mrf_salisbury_lambourn_pocket')) {
                        return false;
                    }
                    break;
                case 'group_trainer':
                    if (!$is1('mrf_trainer_group_elite')) {
                        return false;
                    }
                    break;
                case 'stamina_ok':
                case 'dosage_target':
                    $s = fhor_mordin_stamina($get('distance_yards'), $get('mrf_race_tier_rank'), $get('_dosage_di'), $get('_dosage_di_infinite'));
                    if ($rule === 'stamina_ok' && $s['exceeded']) {
                        return false;
                    }
                    if ($rule === 'dosage_target' && $s['applies'] && !$s['target']) {
                        return false;
                    }
                    break;
            }
        }
        return true;
    }
}

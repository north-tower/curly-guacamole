<?php
/**
 * Fhorsite System Builder & Alerts Engine.
 *
 * Subscribers filter historic runners on proprietary metrics, measure ROI,
 * save setups, and receive today's qualifier alerts.
 *
 * URL: /system-builder/   Shortcode: [system_builder]
 */

if (!function_exists('fhor_sb_url')) {
    function fhor_sb_url() {
        return home_url('/system-builder/');
    }
}

if (!function_exists('fhor_sb_is_request')) {
    function fhor_sb_is_request() {
        if (get_query_var('fhor_system_builder')) {
            return true;
        }
        return (bool) preg_match('#/system-builder(?:/|$)#i', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    }
}

if (!function_exists('fhor_sb_user_can_access')) {
    function fhor_sb_user_can_access($user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        return $user_id > 0;
    }
}

if (!function_exists('fhor_sb_is_premium')) {
    function fhor_sb_is_premium($user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        return function_exists('fhor_user_is_paid_member') && fhor_user_is_paid_member($user_id);
    }
}

if (!function_exists('fhor_sb_max_lookback_days')) {
    function fhor_sb_max_lookback_days($user_id = 0) {
        return fhor_sb_is_premium($user_id) ? 1825 : 365;
    }
}

if (!function_exists('fhor_sb_max_saved')) {
    function fhor_sb_max_saved($user_id = 0) {
        return fhor_sb_is_premium($user_id) ? 200 : 1;
    }
}

if (!function_exists('fhor_sb_can_email_alerts')) {
    function fhor_sb_can_email_alerts($user_id = 0) {
        return fhor_sb_is_premium($user_id);
    }
}

if (!function_exists('fhor_sb_qualifiers_url')) {
    function fhor_sb_qualifiers_url() {
        return home_url('/my-qualifiers/');
    }
}

if (!function_exists('fhor_sb_is_qualifiers_request')) {
    function fhor_sb_is_qualifiers_request() {
        if (get_query_var('fhor_my_qualifiers')) {
            return true;
        }
        return (bool) preg_match('#/my-qualifiers(?:/|$)#i', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    }
}

if (!function_exists('fhor_sb_meta_key')) {
    function fhor_sb_meta_key() {
        return 'fhor_system_builder_saved';
    }
}

if (!function_exists('fhor_sb_systems_table')) {
    function fhor_sb_systems_table() {
        global $wpdb;
        static $name = null;
        if ($name !== null) {
            return $name;
        }
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'saved_systems')) === 'saved_systems') {
            $name = 'saved_systems';
        } else {
            $name = $wpdb->prefix . 'saved_systems';
        }
        return $name;
    }
}

if (!function_exists('fhor_sb_install_table')) {
    function fhor_sb_install_table() {
        if (get_option('fhor_sb_saved_systems_db') === '1') {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'saved_systems';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            system_name varchar(190) NOT NULL DEFAULT '',
            filters longtext NOT NULL,
            is_alert_enabled tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY alerts (is_alert_enabled, user_id)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('fhor_sb_saved_systems_db', '1');
    }
}
add_action('init', 'fhor_sb_install_table', 6);

if (!function_exists('fhor_sb_row_to_system')) {
    function fhor_sb_row_to_system($row) {
        $filters = json_decode((string) ($row->filters ?? ''), true);
        return [
            'id' => (string) intval($row->id ?? 0),
            'name' => (string) ($row->system_name ?? ''),
            'filters' => is_array($filters) ? $filters : [],
            'alerts' => !empty($row->is_alert_enabled),
            'saved_at' => (string) ($row->updated_at ?? $row->created_at ?? ''),
        ];
    }
}

if (!function_exists('fhor_sb_col')) {
    function fhor_sb_col($table, array $candidates, $fallback = '') {
        foreach ($candidates as $col) {
            if (function_exists('bricks_points_table_has_column') && bricks_points_table_has_column($table, $col)) {
                return $col;
            }
        }
        return $fallback;
    }
}

if (!function_exists('fhor_sb_sql_optional')) {
    function fhor_sb_sql_optional($table, $prefix, array $candidates, $as, $if_missing = 'NULL') {
        $col = fhor_sb_col($table, $candidates);
        return $col ? "$prefix.`$col` AS `$as`" : "$if_missing AS `$as`";
    }
}

if (!function_exists('fhor_sb_last_form_pos')) {
    function fhor_sb_last_form_pos($form) {
        $form = strtoupper(preg_replace('/[^0-9FPU]/i', '', (string) $form));
        if ($form === '') {
            return null;
        }
        $ch = $form[0];
        if (ctype_digit($ch)) {
            return intval($ch);
        }
        return 99;
    }
}

if (!function_exists('fhor_sb_needs_pace')) {
    function fhor_sb_needs_pace(array $filters) {
        return ($filters['pace_zone'] ?? '') !== ''
            || ($filters['style'] ?? '') !== ''
            || ($filters['pms_min'] ?? '') !== ''
            || in_array('lone_leader', $filters['flags'] ?? [], true)
            || in_array('swooper', $filters['flags'] ?? [], true);
    }
}

if (!function_exists('fhor_sb_table_exists')) {
    function fhor_sb_table_exists($table) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}

if (!function_exists('fhor_sb_speed_table_for_date')) {
    function fhor_sb_speed_table_for_date($date) {
        $today = function_exists('bricks_daily_archive_today') ? bricks_daily_archive_today() : wp_date('Y-m-d');
        $tomorrow = function_exists('bricks_daily_archive_tomorrow')
            ? bricks_daily_archive_tomorrow()
            : wp_date('Y-m-d', strtotime('+1 day'));
        if ($date === $tomorrow && fhor_sb_table_exists('adv_speed&performance_table')) {
            return 'adv_speed&performance_table';
        }
        if ($date >= $today && fhor_sb_table_exists('speed&performance_table')) {
            return 'speed&performance_table';
        }
        if (fhor_sb_table_exists('speed&performance_table')) {
            return 'speed&performance_table';
        }
        return '';
    }
}

if (!function_exists('fhor_sb_sanitize_filters')) {
    function fhor_sb_sanitize_filters($raw) {
        if (is_string($raw)) {
            $decoded = json_decode(wp_unslash($raw), true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }

        $out = [
            'from' => '',
            'to' => '',
            'days' => 90,
            'bet' => 'win',
            'pick_mode' => 'all',
            'country' => [],
            'race_type' => [],
            'going' => [],
            'course' => '',
            'class' => '',
            'track_type' => '',
            'handicap' => '',
            'age_range' => '',
            'dist_f_min' => '',
            'dist_f_max' => '',
            'field_min' => '',
            'field_max' => '',
            'fsr_min' => '',
            'fsr_max' => '',
            'fsr_rank_max' => '',
            'sr_min' => '',
            'sr_max' => '',
            'sr_rank_max' => '',
            'or_min' => '',
            'or_max' => '',
            'or_diff_min' => '',
            'or_diff_max' => '',
            'cls_min' => '',
            'cls_max' => '',
            'dslr_min' => '',
            'dslr_max' => '',
            'db_min' => '',
            'db_max' => '',
            'tnr_min' => '',
            'comb_min' => '',
            'win_strike_min' => '',
            'place_strike_min' => '',
            'odds_min' => '',
            'odds_max' => '',
            'sp_min' => '',
            'sp_max' => '',
            'fsrr_min' => '',
            'sr_lto_min' => '',
            'age_min' => '',
            'age_max' => '',
            'stall_min' => '',
            'stall_max' => '',
            'pts_rank_max' => '',
            'di_min' => '',
            'di_max' => '',
            'cd_min' => '',
            'cd_max' => '',
            'pace_zone' => '',
            'style' => '',
            'pms_min' => '',
            'trainer' => '',
            'jockey' => '',
            'flags' => [],
        ];

        $text = ['course', 'class', 'track_type', 'handicap', 'age_range', 'from', 'to', 'trainer', 'jockey'];
        foreach ($text as $key) {
            if (isset($raw[$key])) {
                $out[$key] = sanitize_text_field((string) $raw[$key]);
            }
        }
        foreach (['days'] as $key) {
            if (isset($raw[$key]) && is_numeric($raw[$key])) {
                $out[$key] = max(7, min(fhor_sb_max_lookback_days(), intval($raw[$key])));
            }
        }
        $bet = strtolower((string) ($raw['bet'] ?? 'win'));
        $out['bet'] = in_array($bet, ['win', 'place', 'ew'], true) ? $bet : 'win';
        $pick = strtolower((string) ($raw['pick_mode'] ?? 'all'));
        $out['pick_mode'] = in_array($pick, ['all', 'top_fsr', 'top_sr', 'top_pts'], true) ? $pick : 'all';
        $style = strtolower((string) ($raw['style'] ?? ''));
        $out['style'] = in_array($style, ['leader', 'prominent', 'heldup'], true) ? $style : '';
        $zone = (string) ($raw['pace_zone'] ?? '');
        $out['pace_zone'] = in_array($zone, ['1', '2', '3', '4'], true) ? $zone : '';

        $lists = ['country', 'race_type', 'going', 'flags'];
        foreach ($lists as $key) {
            $vals = $raw[$key] ?? [];
            if (!is_array($vals)) {
                $vals = $vals === '' ? [] : [$vals];
            }
            $out[$key] = array_values(array_filter(array_map('sanitize_text_field', $vals), function ($v) {
                return $v !== '';
            }));
        }
        $allowed_flags = ['course', 'distance', 'cd', 'going', 'lbf', 'last_win', 'last_placed', 'lone_leader', 'swooper'];
        $out['flags'] = array_values(array_intersect($out['flags'], $allowed_flags));

        $nums = [
            'dist_f_min', 'dist_f_max', 'field_min', 'field_max',
            'fsr_min', 'fsr_max', 'fsr_rank_max', 'sr_min', 'sr_max', 'sr_rank_max',
            'or_min', 'or_max', 'or_diff_min', 'or_diff_max', 'cls_min', 'cls_max',
            'dslr_min', 'dslr_max', 'db_min', 'db_max', 'tnr_min', 'comb_min',
            'win_strike_min', 'place_strike_min', 'odds_min', 'odds_max', 'sp_min', 'sp_max',
            'fsrr_min', 'sr_lto_min', 'age_min', 'age_max', 'stall_min', 'stall_max',
            'pts_rank_max', 'pms_min', 'di_min', 'di_max', 'cd_min', 'cd_max',
        ];
        foreach ($nums as $key) {
            if (isset($raw[$key]) && $raw[$key] !== '' && is_numeric($raw[$key])) {
                $out[$key] = (string) floatval($raw[$key]);
            }
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $out['from'])) {
            $out['from'] = '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $out['to'])) {
            $out['to'] = '';
        }
        return $out;
    }
}

if (!function_exists('fhor_sb_resolve_dates')) {
    function fhor_sb_resolve_dates(array $filters) {
        $yesterday = wp_date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
        $to = $filters['to'] !== '' ? $filters['to'] : $yesterday;
        if ($to > $yesterday) {
            $to = $yesterday;
        }
        if ($filters['from'] !== '') {
            $from = $filters['from'];
        } else {
            $days = max(7, intval($filters['days'] ?: 90));
            $from = wp_date('Y-m-d', strtotime('-' . $days . ' days', strtotime($to)));
        }
        $oldest = wp_date('Y-m-d', strtotime('-' . fhor_sb_max_lookback_days() . ' days', strtotime($to)));
        if ($from < $oldest) {
            $from = $oldest;
        }
        if ($from > $to) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }
        return [$from, $to];
    }
}

if (!function_exists('fhor_sb_odds_decimal')) {
    function fhor_sb_odds_decimal($row) {
        $spd = $row->starting_price_decimal ?? null;
        $sp = $row->starting_price ?? '';
        $fc = $row->forecast_price_decimal ?? null;
        if (function_exists('bricks_points_settlement_odds_decimal')) {
            return bricks_points_settlement_odds_decimal($fc, $sp, $spd);
        }
        if (is_numeric($spd) && floatval($spd) > 1) {
            return floatval($spd);
        }
        if (function_exists('bricks_points_parse_decimal_odds')) {
            return bricks_points_parse_decimal_odds($fc, $sp);
        }
        return null;
    }
}

if (!function_exists('fhor_sb_bsp_decimal')) {
    function fhor_sb_bsp_decimal($row) {
        foreach (['betfair_sp_decimal', 'bsp_decimal', 'bf_sp_decimal'] as $key) {
            $val = $row->{$key} ?? null;
            if (is_numeric($val) && floatval($val) > 1) {
                return floatval($val);
            }
        }
        foreach (['betfair_sp', 'betfair_starting_price', 'bsp', 'BSP', 'bf_sp', 'bfsp', 'exchange_sp'] as $key) {
            $val = $row->{$key} ?? null;
            if ($val === null || $val === '') {
                continue;
            }
            if (is_numeric($val) && floatval($val) > 1) {
                return floatval($val);
            }
            if (function_exists('bricks_points_parse_decimal_odds')) {
                $parsed = bricks_points_parse_decimal_odds(null, $val);
                if ($parsed !== null && $parsed > 1) {
                    return $parsed;
                }
            }
        }
        return null;
    }
}

if (!function_exists('fhor_sb_numeric_where')) {
    function fhor_sb_numeric_where($expr, $min, $max, array &$where, array &$params, $types = 'f') {
        if ($min !== '' && is_numeric($min)) {
            $where[] = "$expr >= %$types";
            $params[] = $types === 'd' ? intval($min) : floatval($min);
        }
        if ($max !== '' && is_numeric($max)) {
            $where[] = "$expr <= %$types";
            $params[] = $types === 'd' ? intval($max) : floatval($max);
        }
    }
}

if (!function_exists('fhor_sb_in_where')) {
    function fhor_sb_in_where($expr, array $values, array &$where, array &$params) {
        $values = array_values(array_filter($values, function ($v) {
            return $v !== '';
        }));
        if (empty($values)) {
            return;
        }
        $ph = implode(',', array_fill(0, count($values), '%s'));
        $where[] = "$expr IN ($ph)";
        foreach ($values as $v) {
            $params[] = $v;
        }
    }
}

if (!function_exists('fhor_sb_fetch_historic')) {
    /**
     * @return array<int, object>
     */
    function fhor_sb_fetch_historic(array $filters) {
        global $wpdb;

        if (!fhor_sb_table_exists('historic_runners_beta') || !fhor_sb_table_exists('historic_races_beta')) {
            return [];
        }

        list($from, $to) = fhor_sb_resolve_dates($filters);
        $hr = 'historic_runners_beta';
        $ha = 'historic_races_beta';

        $name_col = fhor_sb_col($hr, ['name', 'horse_name'], 'name');
        $country_col = fhor_sb_col($ha, ['country']);
        $going_col = fhor_sb_col($ha, ['advanced_going', 'going']);
        $track_col = fhor_sb_col($ha, ['track_type']);
        $class_col = fhor_sb_col($ha, ['class']);
        $hcap_col = fhor_sb_col($ha, ['handicap', 'HCap']);
        $dist_col = fhor_sb_col($ha, ['distance_yards']);
        $time_col = fhor_sb_col($ha, ['scheduled_time']);
        $title_col = fhor_sb_col($ha, ['race_title', 'race_abbrev_name']);
        $age_col = fhor_sb_col($ha, ['age_range']);
        $prize_col = fhor_sb_col($ha, ['prize_pos_1']);

        $select = [
            "hrunb.race_id AS race_id",
            "hrunb.runner_id AS runner_id",
            "hrunb.`$name_col` AS horse_name",
            "hracb.meeting_date AS meeting_date",
            "hracb.course AS course",
            "hracb.race_type AS race_type",
            "hrunb.finish_position AS finish_position",
            "hrunb.starting_price AS starting_price",
        ];
        $country_sql = $country_col ? "hracb.`$country_col` AS country" : "'' AS country";
        $going_sql = $going_col ? "hracb.`$going_col` AS going" : "'' AS going";
        $select[] = $track_col ? "hracb.`$track_col` AS track_type" : "'' AS track_type";
        $select[] = $class_col ? "hracb.`$class_col` AS class" : "'' AS class";
        $select[] = $hcap_col ? "hracb.`$hcap_col` AS handicap" : "'' AS handicap";
        $select[] = $dist_col ? "hracb.`$dist_col` AS distance_yards" : "NULL AS distance_yards";
        $select[] = $time_col ? "hracb.`$time_col` AS scheduled_time" : "'' AS scheduled_time";
        $select[] = $title_col ? "hracb.`$title_col` AS race_title" : "'' AS race_title";
        $select[] = $age_col ? "hracb.`$age_col` AS age_range" : "'' AS age_range";
        $select[] = $prize_col ? "hracb.`$prize_col` AS prize_pos_1" : "NULL AS prize_pos_1";
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['starting_price_decimal'], 'starting_price_decimal');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['betfair_sp_decimal', 'bsp_decimal', 'bf_sp_decimal'], 'betfair_sp_decimal');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['betfair_sp', 'betfair_starting_price', 'bsp', 'BSP', 'bf_sp', 'bfsp', 'exchange_sp'], 'betfair_sp', "''");
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['official_rating'], 'official_rating');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['days_since_ran'], 'days_since_ran');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['draw_bias_pct'], 'draw_bias_pct');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['class_diff'], 'class_diff');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['official_rating_diff'], 'official_rating_diff');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['course_winner'], 'course_winner', '0');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['distance_winner'], 'distance_winner', '0');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['candd_winner'], 'candd_winner', '0');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['going_prev_wins'], 'going_prev_wins', '0');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['beaten_favourite'], 'beaten_favourite', '0');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['TnrWinPct14d'], 'tnr_win_pct');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['TnrJkyPlacePct'], 'comb_pct');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['prev_runner_win_strike'], 'win_strike');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['prev_runner_place_strike'], 'place_strike');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['forecast_price_decimal'], 'forecast_price_decimal');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['speed_rating'], 'speed_rating');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['wt_speed_rating'], 'wt_speed_rating');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['trainer_name'], 'trainer_name', "''");
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['jockey_name'], 'jockey_name', "''");
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['form_figures'], 'form_figures', "''");
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['age'], 'age');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['stall_number'], 'stall_number');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['fhorsite_rating_reliability'], 'fsrr');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['SR_LTO', 'sr_lto'], 'sr_lto');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['SR_2'], 'sr_2');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['SR_3'], 'sr_3');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['sire_id'], 'sire_id', '0');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['sire_name', 'sire'], 'sire_name', "''");
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['dam_id'], 'dam_id', '0');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['dam_name', 'dam'], 'dam_name', "''");
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['dam_sire_id'], 'dam_sire_id', '0');
        $select[] = fhor_sb_sql_optional($hr, 'hrunb', ['dam_sire_name', 'damsire', 'damsire_name'], 'dam_sire_name', "''");

        $dch = 'daily_comment_history';
        $dch_exists = fhor_sb_table_exists($dch);
        if ($dch_exists && !$country_col && fhor_sb_col($dch, ['country'])) {
            $country_sql = 'dch.country AS country';
        }
        if ($dch_exists && !$going_col && fhor_sb_col($dch, ['going'])) {
            $going_sql = 'dch.going AS going';
        }
        $select[] = $country_sql;
        $select[] = $going_sql;

        $joins = "FROM `$hr` hrunb
            INNER JOIN `$ha` hracb ON hracb.race_id = hrunb.race_id";
        if ($dch_exists) {
            $joins .= " LEFT JOIN `$dch` dch ON dch.race_id = hrunb.race_id AND dch.runner_id = hrunb.runner_id";
        }
        if (fhor_sb_table_exists('non_runners')) {
            $joins .= ' LEFT JOIN non_runners nr ON nr.race_id = hrunb.race_id AND nr.runner_id = hrunb.runner_id';
        }

        if (fhor_sb_table_exists('backtest_cr_data')) {
            $joins .= ' LEFT JOIN backtest_cr_data bcr ON bcr.race_id = hrunb.race_id AND bcr.runner_id = hrunb.runner_id';
            $select[] = 'bcr.CR AS fsr';
        } elseif (fhor_sb_col($hr, ['fhorsite_rating'])) {
            $select[] = 'hrunb.fhorsite_rating AS fsr';
        } else {
            $select[] = 'NULL AS fsr';
        }

        $where = [
            'hracb.meeting_date BETWEEN %s AND %s',
            "hrunb.`$name_col` IS NOT NULL",
            "hrunb.`$name_col` != ''",
        ];
        if (fhor_sb_table_exists('non_runners')) {
            $where[] = 'nr.runner_id IS NULL';
        }
        $params = [$from, $to];

        if ($filters['course'] !== '') {
            $where[] = 'hracb.course LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['course']) . '%';
        }
        fhor_sb_apply_country_where(
            'hracb.course',
            $country_col ? "hracb.`$country_col`" : '',
            $filters['country'],
            $where,
            $params
        );
        fhor_sb_apply_race_type_where('hracb.race_type', $filters['race_type'], $where, $params);
        if ($class_col && $filters['class'] !== '') {
            $where[] = "hracb.`$class_col` LIKE %s";
            $params[] = '%' . $wpdb->esc_like($filters['class']) . '%';
        }
        if ($track_col && $filters['track_type'] !== '') {
            $where[] = "hracb.`$track_col` LIKE %s";
            $params[] = '%' . $wpdb->esc_like($filters['track_type']) . '%';
        }
        if ($hcap_col && $filters['handicap'] !== '') {
            fhor_sb_apply_handicap_where("hracb.`$hcap_col`", $filters['handicap'], $where, $params);
        }
        if ($age_col && $filters['age_range'] !== '') {
            $where[] = "hracb.`$age_col` LIKE %s";
            $params[] = '%' . $wpdb->esc_like($filters['age_range']) . '%';
        }
        if ($going_col && !empty($filters['going'])) {
            $likes = [];
            foreach ($filters['going'] as $g) {
                $likes[] = "hracb.`$going_col` LIKE %s";
                $params[] = '%' . $wpdb->esc_like($g) . '%';
            }
            if ($likes) {
                $where[] = '(' . implode(' OR ', $likes) . ')';
            }
        }
        if ($dist_col) {
            if ($filters['dist_f_min'] !== '') {
                $where[] = "hracb.`$dist_col` >= %d";
                $params[] = intval(floatval($filters['dist_f_min']) * 220);
            }
            if ($filters['dist_f_max'] !== '') {
                $where[] = "hracb.`$dist_col` <= %d";
                $params[] = intval(floatval($filters['dist_f_max']) * 220);
            }
        }
        if ($filters['trainer'] !== '' && fhor_sb_col($hr, ['trainer_name'])) {
            $where[] = 'hrunb.trainer_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['trainer']) . '%';
        }
        if ($filters['jockey'] !== '' && fhor_sb_col($hr, ['jockey_name'])) {
            $where[] = 'hrunb.jockey_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['jockey']) . '%';
        }

        $limit = fhor_sb_is_premium() ? 28000 : 12000;
        $sql = 'SELECT ' . implode(', ', $select) . " $joins WHERE " . implode(' AND ', $where)
            . ' ORDER BY hracb.meeting_date DESC, hrunb.race_id DESC LIMIT ' . intval($limit);
        $prepared = $wpdb->prepare($sql, $params);
        $wpdb->suppress_errors(true);
        $rows = $prepared ? $wpdb->get_results($prepared) : [];
        $wpdb->suppress_errors(false);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('fhor_sb_fetch_live')) {
    function fhor_sb_fetch_live(array $filters, $date = '') {
        global $wpdb;
        $date = $date !== '' ? $date : (function_exists('bricks_daily_archive_today')
            ? bricks_daily_archive_today()
            : wp_date('Y-m-d'));
        $sp_table = fhor_sb_speed_table_for_date($date);
        if ($sp_table === '' || !fhor_sb_table_exists($sp_table)) {
            return [];
        }

        $date_col = fhor_sb_col($sp_table, ['Date', 'meeting_date'], 'Date');
        $name_col = fhor_sb_col($sp_table, ['name', 'horse_name'], 'name');
        $dmy = wp_date('d-m-Y', strtotime($date));

        $select = [
            'sp.race_id AS race_id',
            'sp.runner_id AS runner_id',
            "sp.`$name_col` AS horse_name",
            'sp.course AS course',
            'sp.race_type AS race_type',
            "sp.`$date_col` AS meeting_date",
        ];
        $optional = [
            'scheduled_time' => 'scheduled_time',
            'Time' => 'scheduled_time',
            'race_title' => 'race_title',
            'country' => 'country',
            'class' => 'class',
            'track_type' => 'track_type',
            'handicap' => 'handicap',
            'advanced_going' => 'going',
            'age_range' => 'age_range',
            'distance_yards' => 'distance_yards',
            'fhorsite_rating' => 'fsr',
            'fhorsite_rating_reliability' => 'fsrr',
            'speed_rating' => 'speed_rating',
            'wt_speed_rating' => 'wt_speed_rating',
            'SR_LTO' => 'sr_lto',
            'SR_2' => 'sr_2',
            'SR_3' => 'sr_3',
            'official_rating' => 'official_rating',
            'official_rating_diff' => 'official_rating_diff',
            'class_diff' => 'class_diff',
            'days_since_ran' => 'days_since_ran',
            'draw_bias_pct' => 'draw_bias_pct',
            'course_winner' => 'course_winner',
            'distance_winner' => 'distance_winner',
            'candd_winner' => 'candd_winner',
            'going_prev_wins' => 'going_prev_wins',
            'beaten_favourite' => 'beaten_favourite',
            'TnrWinPct14d' => 'tnr_win_pct',
            'TnrJkyPlacePct' => 'comb_pct',
            'prev_runner_win_strike' => 'win_strike',
            'prev_runner_place_strike' => 'place_strike',
            'forecast_price_decimal' => 'forecast_price_decimal',
            'forecast_price' => 'forecast_price',
            'starting_price' => 'starting_price',
            'trainer_name' => 'trainer_name',
            'jockey_name' => 'jockey_name',
            'form_figures' => 'form_figures',
            'age' => 'age',
            'stall_number' => 'stall_number',
            'sire_id' => 'sire_id',
            'sire_name' => 'sire_name',
            'sire' => 'sire_name',
            'dam_id' => 'dam_id',
            'dam_name' => 'dam_name',
            'dam' => 'dam_name',
            'dam_sire_id' => 'dam_sire_id',
            'dam_sire_name' => 'dam_sire_name',
            'damsire' => 'dam_sire_name',
            'prize_pos_1' => 'prize_pos_1',
            'going' => 'going',
        ];
        $used_alias = [];
        foreach ($optional as $col => $alias) {
            if (isset($used_alias[$alias])) {
                continue;
            }
            if (fhor_sb_col($sp_table, [$col])) {
                $select[] = "sp.`$col` AS `$alias`";
                $used_alias[$alias] = true;
            }
        }
        if (empty($used_alias['fsr'])) {
            $select[] = 'NULL AS fsr';
        }
        if (empty($used_alias['going'])) {
            $select[] = "'' AS going";
        }
        $select[] = 'NULL AS finish_position';
        if (empty($used_alias['starting_price'])) {
            $select[] = "'' AS starting_price";
        }
        $select[] = 'NULL AS starting_price_decimal';

        $from = "`$sp_table` sp";
        if (fhor_sb_table_exists('non_runners')) {
            $from .= ' LEFT JOIN non_runners nr ON nr.race_id = sp.race_id AND nr.runner_id = sp.runner_id';
        }

        $where = ['(sp.`' . esc_sql($date_col) . '` = %s OR sp.`' . esc_sql($date_col) . '` = %s)'];
        $params = [$date, $dmy];
        if (fhor_sb_table_exists('non_runners')) {
            $where[] = 'nr.runner_id IS NULL';
        }
        if ($filters['course'] !== '') {
            $where[] = 'sp.course LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['course']) . '%';
        }
        fhor_sb_apply_race_type_where('sp.race_type', $filters['race_type'], $where, $params);
        fhor_sb_apply_country_where(
            'sp.course',
            !empty($used_alias['country']) ? 'sp.country' : '',
            $filters['country'],
            $where,
            $params
        );
        if (!empty($used_alias['handicap']) && $filters['handicap'] !== '') {
            fhor_sb_apply_handicap_where('sp.handicap', $filters['handicap'], $where, $params);
        }
        if ($filters['class'] !== '' && !empty($used_alias['class'])) {
            $where[] = 'sp.class LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['class']) . '%';
        }
        if ($filters['track_type'] !== '' && !empty($used_alias['track_type'])) {
            $where[] = 'sp.track_type LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['track_type']) . '%';
        }
        if ($filters['trainer'] !== '' && !empty($used_alias['trainer_name'])) {
            $where[] = 'sp.trainer_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['trainer']) . '%';
        }
        if ($filters['jockey'] !== '' && !empty($used_alias['jockey_name'])) {
            $where[] = 'sp.jockey_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['jockey']) . '%';
        }

        $sql = 'SELECT ' . implode(', ', $select) . " FROM $from WHERE " . implode(' AND ', $where) . ' LIMIT 4000';
        $prepared = $wpdb->prepare($sql, $params);
        $wpdb->suppress_errors(true);
        $rows = $prepared ? $wpdb->get_results($prepared) : [];
        $wpdb->suppress_errors(false);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('fhor_sb_country_courses')) {
    function fhor_sb_country_courses() {
        return [
            'ireland' => [
                'ballinrobe', 'bellewstown', 'clonmel', 'cork', 'curragh', 'down royal', 'downpatrick',
                'dundalk', 'fairyhouse', 'galway', 'gowran', 'kilbeggan', 'killarney', 'laytown',
                'leopardstown', 'limerick', 'listowel', 'mallow', 'naas', 'navan', 'phoenix park',
                'powerstown', 'punchestown', 'roscommon', 'sligo', 'thurles', 'tipperary', 'tramore', 'wexford',
            ],
            'scotland' => ['ayr', 'hamilton', 'kelso', 'musselburgh', 'perth'],
            'wales' => ['bangor on dee', 'bangor', 'chepstow', 'ffos las'],
            'england' => [
                'aintree', 'ascot', 'bath', 'beverley', 'brighton', 'carlisle', 'cartmel', 'catterick',
                'chelmsford', 'cheltenham', 'chester', 'doncaster', 'epsom', 'exeter', 'fakenham',
                'folkestone', 'fontwell', 'goodwood', 'great leighs', 'haydock', 'hereford', 'hexham',
                'huntingdon', 'kempton', 'leicester', 'lingfield', 'ludlow', 'market rasen', 'newbury',
                'newcastle', 'newmarket', 'newton abbot', 'nottingham', 'plumpton', 'pontefract', 'redcar',
                'ripon', 'salisbury', 'sandown', 'sedgefield', 'southwell', 'stratford', 'taunton',
                'thirsk', 'towcester', 'uttoxeter', 'warwick', 'wetherby', 'wincanton', 'windsor',
                'wolverhampton', 'worcester', 'yarmouth', 'york',
            ],
        ];
    }
}

if (!function_exists('fhor_sb_normalize_course_name')) {
    function fhor_sb_normalize_course_name($course) {
        $c = strtolower((string) $course);
        $c = str_replace(['_', '-'], ' ', $c);
        $c = preg_replace('/[^a-z0-9 ]+/', ' ', $c);
        $c = preg_replace('/\s+/', ' ', trim((string) $c));
        $c = preg_replace('/^the\s+/', '', $c);
        $c = preg_replace('/\s+(park|bridge|downs|city|aw)$/', '', (string) $c);
        return trim((string) $c);
    }
}

if (!function_exists('fhor_sb_country_bucket')) {
    function fhor_sb_country_bucket($country) {
        $c = strtolower(trim((string) $country));
        if ($c === '') {
            return '';
        }
        if (preg_match('/ireland|\beire\b|\bire\b|\birl\b/', $c)) {
            return 'ireland';
        }
        if (preg_match('/scotland|\bsco\b/', $c)) {
            return 'scotland';
        }
        if (preg_match('/wales|cymru|\bwal\b/', $c)) {
            return 'wales';
        }
        if (preg_match('/england|\beng\b/', $c)) {
            return 'england';
        }
        return '';
    }
}

if (!function_exists('fhor_sb_course_country')) {
    function fhor_sb_course_country($course) {
        $key = fhor_sb_normalize_course_name($course);
        if ($key === '') {
            return '';
        }
        $flat = [];
        foreach (fhor_sb_country_courses() as $bucket => $names) {
            foreach ($names as $name) {
                $flat[$name] = $bucket;
            }
        }
        if (isset($flat[$key])) {
            return $flat[$key];
        }
        $names = array_keys($flat);
        usort($names, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });
        foreach ($names as $name) {
            if (preg_match('/(?:^| )' . preg_quote($name, '/') . '(?: |$)/', $key)) {
                return $flat[$name];
            }
        }
        return '';
    }
}

if (!function_exists('fhor_sb_meeting_country')) {
    function fhor_sb_meeting_country($course, $country = '') {
        $from_course = fhor_sb_course_country($course);
        if ($from_course !== '') {
            return $from_course;
        }
        return fhor_sb_country_bucket($country);
    }
}

if (!function_exists('fhor_sb_country_matches')) {
    function fhor_sb_country_matches($course, $country, array $selected) {
        $wanted = [];
        foreach ($selected as $item) {
            $bucket = fhor_sb_country_bucket($item);
            if ($bucket !== '') {
                $wanted[$bucket] = true;
            }
        }
        if (!$wanted) {
            return true;
        }
        $bucket = fhor_sb_meeting_country($course, $country);
        return $bucket !== '' && isset($wanted[$bucket]);
    }
}

if (!function_exists('fhor_sb_apply_country_where')) {
    function fhor_sb_apply_country_where($course_expr, $country_expr, array $selected, array &$where, array &$params) {
        $wanted = [];
        foreach ($selected as $item) {
            $bucket = fhor_sb_country_bucket($item);
            if ($bucket !== '') {
                $wanted[$bucket] = true;
            }
        }
        if (!$wanted || $course_expr === '') {
            return;
        }
        global $wpdb;
        $exclude = [];
        foreach (fhor_sb_country_courses() as $bucket => $names) {
            if (!isset($wanted[$bucket])) {
                foreach ($names as $name) {
                    $exclude[] = $name;
                }
            }
        }
        foreach ($exclude as $name) {
            $like = function_exists('esc_like') && isset($wpdb) ? $wpdb->esc_like($name) : $name;
            $where[] = 'LOWER(' . $course_expr . ') NOT LIKE %s';
            $params[] = '%' . $like . '%';
        }
        if ($country_expr === '') {
            return;
        }
        $blocked = [];
        $labels = [
            'ireland' => ['ireland', 'eire', 'ire', 'irl', 'northern ireland', 'republic of ireland'],
            'scotland' => ['scotland', 'sco'],
            'wales' => ['wales', 'wal', 'cymru'],
            'england' => ['england', 'eng'],
        ];
        foreach ($labels as $bucket => $words) {
            if (!isset($wanted[$bucket])) {
                foreach ($words as $word) {
                    $blocked[] = $word;
                }
            }
        }
        if (!$blocked) {
            return;
        }
        $ph = implode(',', array_fill(0, count($blocked), '%s'));
        $where[] = '(' . $country_expr . " IS NULL OR TRIM(" . $country_expr . ") = '' OR LOWER(TRIM(" . $country_expr . ")) NOT IN ($ph))";
        foreach ($blocked as $word) {
            $params[] = $word;
        }
    }
}

if (!function_exists('fhor_sb_handicap_state')) {
    function fhor_sb_handicap_state($value) {
        if ($value === null) {
            return null;
        }
        $v = strtolower(trim((string) $value));
        if ($v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return intval($v) === 1;
        }
        if (preg_match('/non[-\s]?handicap/', $v) || preg_match('/^(n|no|non|false)$/', $v)) {
            return false;
        }
        if (preg_match('/^(y|yes|true|hcap|handicap)$/', $v) || strpos($v, 'handicap') !== false || strpos($v, 'hcap') !== false) {
            return true;
        }
        return null;
    }
}

if (!function_exists('fhor_sb_apply_handicap_where')) {
    function fhor_sb_apply_handicap_where($expr, $mode, array &$where, array &$params) {
        $mode = strtolower((string) $mode);
        if ($mode === 'yes') {
            $where[] = "(
                $expr IN (1,'1','Y','Yes','y','yes','HCap','Handicap')
                OR (
                    LOWER(CAST($expr AS CHAR)) LIKE %s
                    AND LOWER(CAST($expr AS CHAR)) NOT LIKE %s
                )
            )";
            $params[] = '%handicap%';
            $params[] = '%non%';
            return;
        }
        if ($mode === 'no') {
            $where[] = "$expr IN (0,'0','N','No','n','no','Non-Handicap','Non Handicap','Non-handicap')";
        }
    }
}

if (!function_exists('fhor_sb_race_type_matches')) {
    function fhor_sb_race_type_matches($race_type, array $wanted) {
        $wanted = array_values(array_filter($wanted, function ($v) {
            return $v !== '';
        }));
        if (!$wanted) {
            return true;
        }
        $t = strtolower(trim((string) $race_type));
        if ($t === '') {
            return false;
        }
        foreach ($wanted as $type) {
            $type = strtolower((string) $type);
            if ($type === 'nh flat') {
                if (strpos($t, 'nh flat') !== false || strpos($t, 'national hunt flat') !== false || strpos($t, 'bumper') !== false) {
                    return true;
                }
            } elseif ($type === 'flat') {
                if (strpos($t, 'flat') !== false && strpos($t, 'nh') === false && strpos($t, 'national hunt') === false && strpos($t, 'bumper') === false) {
                    return true;
                }
            } elseif ($type === 'hurdle') {
                if (strpos($t, 'hurdle') !== false) {
                    return true;
                }
            } elseif ($type === 'chase') {
                if (strpos($t, 'chase') !== false || strpos($t, 'steeple') !== false) {
                    return true;
                }
            } elseif (strpos($t, $type) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('fhor_sb_apply_race_type_where')) {
    function fhor_sb_apply_race_type_where($expr, array $types, array &$where, array &$params) {
        $parts = [];
        foreach ($types as $type) {
            $type = strtolower((string) $type);
            if ($type === 'hurdle') {
                $parts[] = "$expr LIKE %s";
                $params[] = '%Hurdle%';
            } elseif ($type === 'chase') {
                $parts[] = "($expr LIKE %s OR $expr LIKE %s)";
                $params[] = '%Chase%';
                $params[] = '%Steeple%';
            } elseif ($type === 'nh flat') {
                $parts[] = "($expr LIKE %s OR $expr LIKE %s OR $expr LIKE %s)";
                $params[] = '%NH Flat%';
                $params[] = '%National Hunt Flat%';
                $params[] = '%Bumper%';
            } elseif ($type === 'flat') {
                $parts[] = "($expr LIKE %s AND $expr NOT LIKE %s AND $expr NOT LIKE %s AND $expr NOT LIKE %s)";
                $params[] = '%Flat%';
                $params[] = '%NH%';
                $params[] = '%National Hunt%';
                $params[] = '%Bumper%';
            }
        }
        if ($parts) {
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
    }
}

if (!function_exists('fhor_sb_speed_figure')) {
    function fhor_sb_speed_figure($row) {
        foreach (['wt_speed_rating', 'speed_rating', 'sr_lto', 'SR_LTO'] as $key) {
            if (isset($row->{$key}) && is_numeric($row->{$key})) {
                return floatval($row->{$key});
            }
        }
        return null;
    }
}

if (!function_exists('fhor_sb_row_passes_runner_filters')) {
    function fhor_sb_row_passes_runner_filters($row, array $filters) {
        $num = function ($val) {
            return is_numeric($val) ? floatval($val) : null;
        };
        $between = function ($value, $min, $max) use ($num) {
            if ($min === '' && $max === '') {
                return true;
            }
            if ($value === null) {
                return false;
            }
            if ($min !== '' && $value < floatval($min)) {
                return false;
            }
            if ($max !== '' && $value > floatval($max)) {
                return false;
            }
            return true;
        };

        $fsr = $num($row->fsr ?? null);
        $sr = $num($row->wt_speed_rating ?? $row->speed_rating ?? $row->sr_lto ?? null);

        if (!fhor_sb_country_matches($row->course ?? '', $row->country ?? '', $filters['country'] ?? [])) {
            return false;
        }
        if (!fhor_sb_race_type_matches($row->race_type ?? '', $filters['race_type'] ?? [])) {
            return false;
        }
        if ($filters['class'] !== '' && stripos((string) ($row->class ?? ''), $filters['class']) === false) {
            return false;
        }
        if ($filters['track_type'] !== '' && stripos((string) ($row->track_type ?? ''), $filters['track_type']) === false) {
            return false;
        }
        if ($filters['age_range'] !== '' && stripos((string) ($row->age_range ?? ''), $filters['age_range']) === false) {
            return false;
        }
        if (!empty($filters['going'])) {
            $ok = false;
            foreach ($filters['going'] as $g) {
                if ($g !== '' && stripos((string) ($row->going ?? ''), $g) !== false) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return false;
            }
        }
        if (($filters['handicap'] ?? '') === 'yes' || ($filters['handicap'] ?? '') === 'no') {
            $handicap = fhor_sb_handicap_state($row->handicap ?? null);
            $want_handicap = $filters['handicap'] === 'yes';
            if ($handicap === null || $handicap !== $want_handicap) {
                return false;
            }
        }
        if ($filters['trainer'] !== '' && stripos((string) ($row->trainer_name ?? ''), $filters['trainer']) === false) {
            return false;
        }
        if ($filters['jockey'] !== '' && stripos((string) ($row->jockey_name ?? ''), $filters['jockey']) === false) {
            return false;
        }
        $yards = $num($row->distance_yards ?? null);
        if ($yards !== null) {
            $f = $yards / 220.0;
            if ($filters['dist_f_min'] !== '' && $f < floatval($filters['dist_f_min'])) {
                return false;
            }
            if ($filters['dist_f_max'] !== '' && $f > floatval($filters['dist_f_max'])) {
                return false;
            }
        }

        if (!$between($fsr, $filters['fsr_min'], $filters['fsr_max'])) {
            return false;
        }
        if (!$between($sr, $filters['sr_min'], $filters['sr_max'])) {
            return false;
        }
        if ($filters['fsrr_min'] !== '' && ($num($row->fsrr ?? null) === null || $num($row->fsrr) < floatval($filters['fsrr_min']))) {
            return false;
        }
        if ($filters['sr_lto_min'] !== '' && ($num($row->sr_lto ?? $row->SR_LTO ?? null) === null || $num($row->sr_lto ?? $row->SR_LTO) < floatval($filters['sr_lto_min']))) {
            return false;
        }
        if (!$between($num($row->age ?? null), $filters['age_min'], $filters['age_max'])) {
            return false;
        }
        if (!$between($num($row->stall_number ?? null), $filters['stall_min'], $filters['stall_max'])) {
            return false;
        }
        if (!$between($num($row->official_rating ?? null), $filters['or_min'], $filters['or_max'])) {
            return false;
        }
        if (!$between($num($row->official_rating_diff ?? null), $filters['or_diff_min'], $filters['or_diff_max'])) {
            return false;
        }
        if (!$between($num($row->class_diff ?? null), $filters['cls_min'], $filters['cls_max'])) {
            return false;
        }
        if (!$between($num($row->days_since_ran ?? null), $filters['dslr_min'], $filters['dslr_max'])) {
            return false;
        }
        if (!$between($num($row->draw_bias_pct ?? null), $filters['db_min'], $filters['db_max'])) {
            return false;
        }
        if ($filters['tnr_min'] !== '' && ($num($row->tnr_win_pct ?? null) === null || $num($row->tnr_win_pct) < floatval($filters['tnr_min']))) {
            return false;
        }
        if ($filters['comb_min'] !== '' && ($num($row->comb_pct ?? null) === null || $num($row->comb_pct) < floatval($filters['comb_min']))) {
            return false;
        }
        if ($filters['win_strike_min'] !== '' && ($num($row->win_strike ?? null) === null || $num($row->win_strike) < floatval($filters['win_strike_min']))) {
            return false;
        }
        if ($filters['place_strike_min'] !== '' && ($num($row->place_strike ?? null) === null || $num($row->place_strike) < floatval($filters['place_strike_min']))) {
            return false;
        }

        $fc = $num($row->forecast_price_decimal ?? null);
        if ($fc === null && !empty($row->forecast_price) && function_exists('bricks_points_parse_decimal_odds')) {
            $fc = bricks_points_parse_decimal_odds(null, $row->forecast_price);
        }
        if (!$between($fc, $filters['odds_min'], $filters['odds_max'])) {
            return false;
        }
        $sp = fhor_sb_odds_decimal($row);
        $has_result = function_exists('bricks_points_finish_has_result')
            ? bricks_points_finish_has_result($row->finish_position ?? '')
            : (trim((string) ($row->finish_position ?? '')) !== '');
        if ($has_result && !$between($sp, $filters['sp_min'], $filters['sp_max'])) {
            return false;
        }

        $flags = $filters['flags'];
        $flag_map = [
            'course' => 'course_winner',
            'distance' => 'distance_winner',
            'cd' => 'candd_winner',
            'going' => 'going_prev_wins',
            'lbf' => 'beaten_favourite',
        ];
        foreach ($flag_map as $flag => $col) {
            if (in_array($flag, $flags, true) && intval($row->{$col} ?? 0) <= 0) {
                return false;
            }
        }
        $last_pos = fhor_sb_last_form_pos($row->form_figures ?? '');
        if (in_array('last_win', $flags, true) && $last_pos !== 1) {
            return false;
        }
        if (in_array('last_placed', $flags, true) && ($last_pos === null || $last_pos > 3)) {
            return false;
        }
        $has_pace = isset($row->pms) || isset($row->zone);
        if ($has_pace) {
            if (in_array('lone_leader', $flags, true) && empty($row->_pace_lone_leader)) {
                return false;
            }
            if (in_array('swooper', $flags, true) && empty($row->_pace_swooper)) {
                return false;
            }
            if ($filters['pace_zone'] !== '' && intval($row->zone ?? 0) !== intval($filters['pace_zone'])) {
                return false;
            }
            if ($filters['pms_min'] !== '' && ($num($row->pms ?? null) === null || $num($row->pms) < floatval($filters['pms_min']))) {
                return false;
            }
            if ($filters['style'] === 'leader' && ($num($row->style_net_leader_score ?? null) === null || $num($row->style_net_leader_score) <= 0.15)) {
                return false;
            }
            if ($filters['style'] === 'prominent' && ($num($row->style_closeup ?? null) === null || $num($row->style_closeup) < 0.35)) {
                return false;
            }
            if ($filters['style'] === 'heldup' && ($num($row->style_net_leader_score ?? null) === null || $num($row->style_net_leader_score) >= -0.15)) {
                return false;
            }
        }

        $field = intval($row->_field_size ?? 0);
        if (($filters['field_min'] ?? '') !== '' || ($filters['field_max'] ?? '') !== '') {
            if ($field < 1) {
                return false;
            }
            if (($filters['field_min'] ?? '') !== '' && $field < intval($filters['field_min'])) {
                return false;
            }
            if (($filters['field_max'] ?? '') !== '' && $field > intval($filters['field_max'])) {
                return false;
            }
        }
        if ($filters['fsr_rank_max'] !== '' && (intval($row->_fsr_rank ?? 999) > intval($filters['fsr_rank_max']) || intval($row->_fsr_rank ?? 0) < 1)) {
            return false;
        }
        if ($filters['sr_rank_max'] !== '' && (intval($row->_sr_rank ?? 999) > intval($filters['sr_rank_max']) || intval($row->_sr_rank ?? 0) < 1)) {
            return false;
        }
        if ($filters['pts_rank_max'] !== '' && (intval($row->_pts_rank ?? 999) > intval($filters['pts_rank_max']) || intval($row->_pts_rank ?? 0) < 1)) {
            return false;
        }
        $di_min = $filters['di_min'] ?? '';
        $di_max = $filters['di_max'] ?? '';
        if ($di_min !== '' || $di_max !== '') {
            if (!empty($row->_dosage_di_infinite)) {
                if ($di_max !== '') {
                    return false;
                }
            } elseif (!$between($num($row->_dosage_di ?? null), $di_min, $di_max)) {
                return false;
            }
        }
        if (!$between($num($row->_dosage_cd ?? null), $filters['cd_min'] ?? '', $filters['cd_max'] ?? '')) {
            return false;
        }
        return true;
    }
}

if (!function_exists('fhor_sb_speed_object_from_row')) {
    function fhor_sb_speed_object_from_row($row) {
        $o = new stdClass();
        $o->fhorsite_rating = $row->fsr ?? null;
        $o->fhorsite_rating_reliability = $row->fsrr ?? null;
        $o->SR_LTO = $row->sr_lto ?? $row->SR_LTO ?? null;
        $o->SR_2 = $row->sr_2 ?? null;
        $o->SR_3 = $row->sr_3 ?? null;
        $o->draw_bias_pct = $row->draw_bias_pct ?? null;
        $o->TnrWinPct14d = $row->tnr_win_pct ?? null;
        $o->TnrJkyPlacePct = $row->comb_pct ?? null;
        $o->days_since_ran = $row->days_since_ran ?? null;
        $o->class_diff = $row->class_diff ?? null;
        $o->official_rating_diff = $row->official_rating_diff ?? null;
        $o->course_winner = $row->course_winner ?? 0;
        $o->distance_winner = $row->distance_winner ?? 0;
        $o->candd_winner = $row->candd_winner ?? 0;
        $o->going_prev_wins = $row->going_prev_wins ?? 0;
        $o->beaten_favourite = $row->beaten_favourite ?? 0;
        $o->form_figures = $row->form_figures ?? '';
        return $o;
    }
}

if (!function_exists('fhor_sb_attach_pace')) {
    function fhor_sb_attach_pace(array $rows) {
        if (empty($rows) || !function_exists('bricks_pace_map_compute')) {
            return $rows;
        }
        $by_race = [];
        foreach ($rows as $row) {
            $rid = intval($row->race_id ?? 0);
            if ($rid <= 0) {
                continue;
            }
            if (empty($row->name)) {
                $row->name = $row->horse_name ?? '';
            }
            if (!isset($row->fhorsite_rating)) {
                $row->fhorsite_rating = $row->fsr ?? null;
            }
            if (!isset($row->SR_LTO)) {
                $row->SR_LTO = $row->sr_lto ?? null;
            }
            $by_race[$rid][] = $row;
        }
        $out = [];
        foreach ($by_race as $race_rows) {
            $computed = bricks_pace_map_compute($race_rows);
            $runners = $computed['runners'] ?? $race_rows;
            $lone = [];
            $swoop = [];
            foreach ($computed['alerts'] ?? [] as $alert) {
                $type = (string) ($alert['type'] ?? '');
                foreach ($alert['horses'] ?? [] as $horse) {
                    $hid = intval($horse->runner_id ?? 0);
                    if ($type === 'lone_leader') {
                        $lone[$hid] = true;
                    }
                    if ($type === 'swooper') {
                        $swoop[$hid] = true;
                    }
                }
            }
            foreach ($runners as $runner) {
                $hid = intval($runner->runner_id ?? 0);
                $runner->_pace_lone_leader = !empty($lone[$hid]);
                $runner->_pace_swooper = !empty($swoop[$hid]);
                $out[] = $runner;
            }
        }
        return $out;
    }
}

if (!function_exists('fhor_sb_needs_dosage')) {
    function fhor_sb_needs_dosage(array $filters) {
        foreach (['di_min', 'di_max', 'cd_min', 'cd_max'] as $key) {
            if (($filters[$key] ?? '') !== '') {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('fhor_sb_attach_dosage')) {
    function fhor_sb_attach_dosage(array $rows) {
        if (empty($rows) || !function_exists('bricks_dosage_metrics_for_runners')) {
            return $rows;
        }
        static $cache = [];

        $missing = [];
        foreach ($rows as $row) {
            $id = intval($row->runner_id ?? 0);
            if ($id <= 0 || isset($cache['id:' . $id])) {
                continue;
            }
            if (trim((string) ($row->sire_name ?? $row->sire ?? '')) === '') {
                $missing[$id] = $id;
            }
        }
        if ($missing && function_exists('bricks_dosage_fetch_horses_by_ids_or_names')) {
            $by_id = [];
            foreach (array_chunk(array_values($missing), 300) as $chunk) {
                $found = bricks_dosage_fetch_horses_by_ids_or_names($chunk, []);
                if (!is_array($found)) {
                    continue;
                }
                foreach ($found as $horse) {
                    $hid = intval($horse['runner_id'] ?? 0);
                    if ($hid > 0 && !isset($by_id[$hid])) {
                        $by_id[$hid] = $horse;
                    }
                }
            }
            foreach ($rows as $row) {
                $id = intval($row->runner_id ?? 0);
                if ($id <= 0 || !isset($by_id[$id])) {
                    continue;
                }
                $horse = $by_id[$id];
                foreach (['sire_id', 'sire_name', 'dam_id', 'dam_name', 'dam_sire_id', 'dam_sire_name'] as $field) {
                    if (!isset($row->{$field}) || $row->{$field} === '' || $row->{$field} === null) {
                        $row->{$field} = $horse[$field];
                    }
                }
            }
        }

        $pending = [];
        $seen = [];
        foreach ($rows as $row) {
            $id = intval($row->runner_id ?? 0);
            $key = $id > 0 ? ('id:' . $id) : '';
            if ($key === '' || isset($cache[$key]) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if (empty($row->name)) {
                $row->name = $row->horse_name ?? '';
            }
            $pending[] = $row;
        }
        foreach (array_chunk($pending, 150) as $chunk) {
            $lookup = bricks_dosage_metrics_for_runners($chunk);
            if (!is_array($lookup)) {
                $lookup = [];
            }
            foreach ($chunk as $idx => $row) {
                $metrics = function_exists('bricks_dosage_metrics_for_runner_row')
                    ? bricks_dosage_metrics_for_runner_row($lookup, $row, $idx)
                    : [];
                $inf = !empty($metrics['di_is_infinite']);
                $di = isset($metrics['di']) && is_numeric($metrics['di']) && !$inf ? (float) $metrics['di'] : null;
                $cd = isset($metrics['cd']) && is_numeric($metrics['cd']) ? (float) $metrics['cd'] : null;
                $id = intval($row->runner_id ?? 0);
                if ($id > 0) {
                    $cache['id:' . $id] = ['di' => $di, 'cd' => $cd, 'inf' => $inf];
                }
            }
        }

        foreach ($rows as $row) {
            $id = intval($row->runner_id ?? 0);
            $hit = ($id > 0 && isset($cache['id:' . $id]))
                ? $cache['id:' . $id]
                : ['di' => null, 'cd' => null, 'inf' => false];
            $row->_dosage_di = $hit['di'];
            $row->_dosage_cd = $hit['cd'];
            $row->_dosage_di_infinite = !empty($hit['inf']);
        }
        return $rows;
    }
}

if (!function_exists('fhor_sb_dosage_di_cd_from_row')) {
    /**
     * @param object $row
     * @return array{di:?string,cd:?string}
     */
    function fhor_sb_dosage_di_cd_from_row($row) {
        return [
            'di' => !empty($row->_dosage_di_infinite)
                ? 'Inf'
                : ((isset($row->_dosage_di) && is_numeric($row->_dosage_di))
                    ? number_format((float) $row->_dosage_di, 2, '.', '')
                    : null),
            'cd' => (isset($row->_dosage_cd) && is_numeric($row->_dosage_cd))
                ? number_format((float) $row->_dosage_cd, 2, '.', '')
                : null,
        ];
    }
}

if (!function_exists('fhor_sb_annotate_and_filter')) {
    function fhor_sb_annotate_and_filter(array $rows, array $filters) {
        if (fhor_sb_needs_dosage($filters)) {
            $rows = fhor_sb_attach_dosage($rows);
        }
        $by_race = [];
        foreach ($rows as $row) {
            $rid = intval($row->race_id ?? 0);
            if ($rid <= 0) {
                continue;
            }
            if (!isset($by_race[$rid])) {
                $by_race[$rid] = [];
            }
            $by_race[$rid][] = $row;
        }

        $qualified = [];
        foreach ($by_race as $race_rows) {
            $field = count($race_rows);
            $fsr_sorted = $race_rows;
            usort($fsr_sorted, function ($a, $b) {
                return floatval($b->fsr ?? -9999) <=> floatval($a->fsr ?? -9999);
            });
            $sr_sorted = $race_rows;
            usort($sr_sorted, function ($a, $b) {
                $sa = floatval($a->wt_speed_rating ?? $a->speed_rating ?? $a->sr_lto ?? -9999);
                $sb = floatval($b->wt_speed_rating ?? $b->speed_rating ?? $b->sr_lto ?? -9999);
                return $sb <=> $sa;
            });
            $pts_sorted = $race_rows;
            foreach ($pts_sorted as $r) {
                if (function_exists('bricks_points_score_runner')) {
                    $is_flat = (stripos((string) ($r->race_type ?? ''), 'flat') !== false);
                    $scored = bricks_points_score_runner($r, fhor_sb_speed_object_from_row($r), ['is_flat' => $is_flat]);
                    $r->_pts = floatval($scored['score'] ?? 0);
                } else {
                    $r->_pts = 0.0;
                }
            }
            usort($pts_sorted, function ($a, $b) {
                return floatval($b->_pts ?? 0) <=> floatval($a->_pts ?? 0);
            });
            $fsr_rank = [];
            $sr_rank = [];
            $pts_rank = [];
            foreach ($fsr_sorted as $i => $r) {
                $fsr_rank[intval($r->runner_id)] = $i + 1;
            }
            foreach ($sr_sorted as $i => $r) {
                $sr_rank[intval($r->runner_id)] = $i + 1;
            }
            foreach ($pts_sorted as $i => $r) {
                $pts_rank[intval($r->runner_id)] = $i + 1;
            }

            $kept = [];
            foreach ($race_rows as $row) {
                $row->_field_size = $field;
                $row->_fsr_rank = $fsr_rank[intval($row->runner_id)] ?? 999;
                $row->_sr_rank = $sr_rank[intval($row->runner_id)] ?? 999;
                $row->_pts_rank = $pts_rank[intval($row->runner_id)] ?? 999;
                $row->_pts = floatval($row->_pts ?? 0);
                if (fhor_sb_row_passes_runner_filters($row, $filters)) {
                    $kept[] = $row;
                }
            }
            if ($filters['pick_mode'] === 'top_fsr' && count($kept) > 1) {
                usort($kept, function ($a, $b) {
                    return floatval($b->fsr ?? -9999) <=> floatval($a->fsr ?? -9999);
                });
                $kept = [reset($kept)];
            } elseif ($filters['pick_mode'] === 'top_sr' && count($kept) > 1) {
                $kept = array_values(array_filter($kept, function ($row) {
                    return fhor_sb_speed_figure($row) !== null;
                }));
                usort($kept, function ($a, $b) {
                    return fhor_sb_speed_figure($b) <=> fhor_sb_speed_figure($a);
                });
                $kept = $kept ? [reset($kept)] : [];
            } elseif ($filters['pick_mode'] === 'top_pts' && count($kept) > 1) {
                usort($kept, function ($a, $b) {
                    return floatval($b->_pts ?? 0) <=> floatval($a->_pts ?? 0);
                });
                $kept = [reset($kept)];
            }
            foreach ($kept as $row) {
                $qualified[] = $row;
            }
        }
        return $qualified;
    }
}

if (!function_exists('fhor_sb_settle_at_odds')) {
    function fhor_sb_settle_at_odds($row, $bet, $odds) {
        $fp = $row->finish_position ?? '';
        $field = intval($row->_field_size ?? 8);
        $terms = function_exists('bricks_points_place_terms_count')
            ? bricks_points_place_terms_count($field)
            : 3;
        $win = function_exists('bricks_points_finish_is_win') && bricks_points_finish_is_win($fp);
        $placed = function_exists('bricks_points_finish_is_placed') && bricks_points_finish_is_placed($fp, $terms);
        if ($odds === null || $odds <= 1) {
            return null;
        }
        $profit = 0.0;
        $stake = 1.0;
        if ($bet === 'win') {
            $profit = $win ? ($odds - 1) : -1;
        } elseif ($bet === 'place') {
            $profit = $placed ? (($odds - 1) * 0.25) : -1;
        } else {
            $stake = 2.0;
            $profit = ($win ? ($odds - 1) : -1) + ($placed ? (($odds - 1) * 0.25) : -1);
        }
        return [
            'stake' => $stake,
            'profit' => round($profit, 2),
            'win' => $win,
            'placed' => $placed,
            'odds' => $odds,
        ];
    }
}

if (!function_exists('fhor_sb_settle_row')) {
    function fhor_sb_settle_row($row, $bet) {
        return fhor_sb_settle_at_odds($row, $bet, fhor_sb_odds_decimal($row));
    }
}

if (!function_exists('fhor_sb_empty_book')) {
    function fhor_sb_empty_book() {
        return [
            'bets' => 0,
            'wins' => 0,
            'places' => 0,
            'stake' => 0.0,
            'profit' => 0.0,
            'roi' => 0.0,
            'win_rate' => 0.0,
            'place_rate' => 0.0,
        ];
    }
}

if (!function_exists('fhor_sb_add_to_book')) {
    function fhor_sb_add_to_book(array &$book, $settle) {
        if ($settle === null) {
            return;
        }
        $book['bets']++;
        $book['stake'] += $settle['stake'];
        $book['profit'] += $settle['profit'];
        if (!empty($settle['win'])) {
            $book['wins']++;
        }
        if (!empty($settle['placed'])) {
            $book['places']++;
        }
    }
}

if (!function_exists('fhor_sb_finalise_book')) {
    function fhor_sb_finalise_book(array $book) {
        $book['stake'] = round($book['stake'], 2);
        $book['profit'] = round($book['profit'], 2);
        $book['roi'] = $book['stake'] > 0 ? round(($book['profit'] / $book['stake']) * 100, 1) : 0.0;
        $book['win_rate'] = $book['bets'] > 0 ? round(($book['wins'] / $book['bets']) * 100, 1) : 0.0;
        $book['place_rate'] = $book['bets'] > 0 ? round(($book['places'] / $book['bets']) * 100, 1) : 0.0;
        return $book;
    }
}

if (!function_exists('fhor_sb_run_backtest')) {
    function fhor_sb_run_backtest(array $filters) {
        list($from, $to) = fhor_sb_resolve_dates($filters);
        $rows = fhor_sb_fetch_historic($filters);
        $qualified = fhor_sb_annotate_and_filter($rows, $filters);
        $isp = fhor_sb_empty_book();
        $bsp = fhor_sb_empty_book();
        $samples = [];
        foreach ($qualified as $row) {
            $isp_settle = fhor_sb_settle_at_odds($row, $filters['bet'], fhor_sb_odds_decimal($row));
            $bsp_settle = fhor_sb_settle_at_odds($row, $filters['bet'], fhor_sb_bsp_decimal($row));
            fhor_sb_add_to_book($isp, $isp_settle);
            fhor_sb_add_to_book($bsp, $bsp_settle);
            if ($isp_settle === null) {
                continue;
            }
            if (count($samples) < 20) {
                $bsp_odds = fhor_sb_bsp_decimal($row);
                $meeting = fhor_sb_meeting_country($row->course ?? '', $row->country ?? '');
                $meeting_labels = [
                    'england' => 'England',
                    'ireland' => 'Ireland',
                    'scotland' => 'Scotland',
                    'wales' => 'Wales',
                ];
                $dosage = fhor_sb_dosage_di_cd_from_row($row);
                $samples[] = [
                    'horse' => (string) ($row->horse_name ?? ''),
                    'course' => (string) ($row->course ?? ''),
                    'country' => $meeting_labels[$meeting] ?? '',
                    'field' => intval($row->_field_size ?? 0),
                    'date' => (string) ($row->meeting_date ?? ''),
                    'sp' => (string) ($row->starting_price ?? ''),
                    'isp' => $isp_settle['odds'],
                    'bsp' => $bsp_odds,
                    'fsr' => is_numeric($row->fsr ?? null) ? round(floatval($row->fsr), 1) : null,
                    'di' => $dosage['di'],
                    'cd' => $dosage['cd'],
                    'pos' => (string) ($row->finish_position ?? ''),
                    'profit' => $isp_settle['profit'],
                    'race_id' => intval($row->race_id ?? 0),
                ];
            }
        }
        $isp = fhor_sb_finalise_book($isp);
        $bsp = fhor_sb_finalise_book($bsp);
        return [
            'from' => $from,
            'to' => $to,
            'runners_scanned' => count($rows),
            'qualifiers' => count($qualified),
            'capped' => count($rows) >= (fhor_sb_is_premium() ? 28000 : 12000),
            'bets' => $isp['bets'],
            'wins' => $isp['wins'],
            'places' => $isp['places'],
            'stake' => $isp['stake'],
            'profit' => $isp['profit'],
            'roi' => $isp['roi'],
            'win_rate' => $isp['win_rate'],
            'place_rate' => $isp['place_rate'],
            'isp' => $isp,
            'bsp' => $bsp,
            'has_bsp' => $bsp['bets'] > 0,
            'samples' => $samples,
            'pace_live_only' => fhor_sb_needs_pace($filters),
            'dosage_filtered' => fhor_sb_needs_dosage($filters),
        ];
    }
}

if (!function_exists('fhor_sb_today_qualifiers')) {
    function fhor_sb_today_qualifiers(array $filters, $date = '') {
        $date = $date !== '' ? $date : (function_exists('bricks_daily_archive_today')
            ? bricks_daily_archive_today()
            : wp_date('Y-m-d'));
        $rows = fhor_sb_fetch_live($filters, $date);
        if (fhor_sb_needs_pace($filters)) {
            $rows = fhor_sb_attach_pace($rows);
        }
        $qualified = fhor_sb_annotate_and_filter($rows, $filters);
        if (!empty($qualified) && !fhor_sb_needs_dosage($filters)) {
            $qualified = fhor_sb_attach_dosage($qualified);
        }
        usort($qualified, function ($a, $b) {
            $ta = (string) ($a->scheduled_time ?? '');
            $tb = (string) ($b->scheduled_time ?? '');
            if ($ta !== $tb) {
                return strcmp($ta, $tb);
            }
            return strcmp((string) ($a->course ?? ''), (string) ($b->course ?? ''));
        });
        $out = [];
        foreach (array_slice($qualified, 0, 120) as $row) {
            $fc = $row->forecast_price_decimal ?? null;
            if (($fc === null || $fc === '') && !empty($row->forecast_price) && function_exists('bricks_points_parse_decimal_odds')) {
                $fc = bricks_points_parse_decimal_odds(null, $row->forecast_price);
            }
            $dosage_out = fhor_sb_dosage_di_cd_from_row($row);
            $out[] = [
                'horse' => (string) ($row->horse_name ?? ''),
                'course' => function_exists('bricks_track_format_display_name')
                    ? bricks_track_format_display_name($row->course ?? '')
                    : str_replace('_', ' ', (string) ($row->course ?? '')),
                'time' => (string) ($row->scheduled_time ?? $row->Time ?? ''),
                'race_title' => (string) ($row->race_title ?? ''),
                'fsr' => is_numeric($row->fsr ?? null) ? round(floatval($row->fsr), 1) : null,
                'fsr_rank' => intval($row->_fsr_rank ?? 0),
                'pts' => isset($row->_pts) ? round(floatval($row->_pts), 1) : null,
                'pace_zone' => isset($row->zone) ? intval($row->zone) : null,
                'forecast' => $row->forecast_price ?? ($fc ? (string) $fc : ''),
                'di' => $dosage_out['di'],
                'cd' => $dosage_out['cd'],
                'race_id' => intval($row->race_id ?? 0),
                'race_url' => function_exists('bricks_race_url') ? bricks_race_url(intval($row->race_id ?? 0)) : '',
            ];
        }
        return ['date' => $date, 'count' => count($qualified), 'rows' => $out];
    }
}

if (!function_exists('fhor_sb_bust_qualifier_cache')) {
    function fhor_sb_bust_qualifier_cache($user_id) {
        $today = function_exists('bricks_daily_archive_today') ? bricks_daily_archive_today() : wp_date('Y-m-d');
        delete_transient('fhor_sb_q_' . intval($user_id) . '_' . $today);
    }
}

if (!function_exists('fhor_sb_maybe_migrate_meta')) {
    function fhor_sb_maybe_migrate_meta($user_id) {
        $user_id = intval($user_id);
        if ($user_id <= 0 || get_user_meta($user_id, 'fhor_sb_meta_migrated', true) === '1') {
            return;
        }
        $legacy = get_user_meta($user_id, fhor_sb_meta_key(), true);
        if (is_array($legacy) && !empty($legacy)) {
            global $wpdb;
            $table = fhor_sb_systems_table();
            $now = current_time('mysql');
            foreach ($legacy as $sys) {
                if (!is_array($sys)) {
                    continue;
                }
                $wpdb->insert($table, [
                    'user_id' => $user_id,
                    'system_name' => sanitize_text_field((string) ($sys['name'] ?? 'System')),
                    'filters' => wp_json_encode(is_array($sys['filters'] ?? null) ? $sys['filters'] : []),
                    'is_alert_enabled' => !empty($sys['alerts']) ? 1 : 0,
                    'created_at' => sanitize_text_field((string) ($sys['saved_at'] ?? $now)),
                    'updated_at' => $now,
                ]);
            }
        }
        update_user_meta($user_id, 'fhor_sb_meta_migrated', '1');
    }
}

if (!function_exists('fhor_sb_get_saved')) {
    function fhor_sb_get_saved($user_id = 0) {
        global $wpdb;
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if ($user_id <= 0) {
            return [];
        }
        fhor_sb_install_table();
        fhor_sb_maybe_migrate_meta($user_id);
        $table = fhor_sb_systems_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `$table` WHERE user_id = %d ORDER BY updated_at DESC, id DESC",
            $user_id
        ));
        $out = [];
        foreach ((array) $rows as $row) {
            $out[] = fhor_sb_row_to_system($row);
        }
        return $out;
    }
}

if (!function_exists('fhor_sb_insert_system')) {
    function fhor_sb_insert_system($user_id, $name, array $filters, $alerts) {
        global $wpdb;
        $user_id = intval($user_id);
        $max = fhor_sb_max_saved($user_id);
        $existing = fhor_sb_get_saved($user_id);
        if (count($existing) >= $max) {
            return new WP_Error('limit', $max === 1
                ? 'Free accounts can save 1 system. Upgrade to Premium for unlimited saves.'
                : 'Saved system limit reached.');
        }
        if ($alerts && !fhor_sb_can_email_alerts($user_id)) {
            $alerts = false;
        }
        $now = current_time('mysql');
        $ok = $wpdb->insert(fhor_sb_systems_table(), [
            'user_id' => $user_id,
            'system_name' => $name,
            'filters' => wp_json_encode($filters),
            'is_alert_enabled' => $alerts ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if (!$ok) {
            return new WP_Error('db', 'Could not save this system.');
        }
        fhor_sb_bust_qualifier_cache($user_id);
        return (string) $wpdb->insert_id;
    }
}

if (!function_exists('fhor_sb_delete_system')) {
    function fhor_sb_delete_system($user_id, $id) {
        global $wpdb;
        $wpdb->delete(fhor_sb_systems_table(), [
            'id' => intval($id),
            'user_id' => intval($user_id),
        ]);
        fhor_sb_bust_qualifier_cache($user_id);
    }
}

if (!function_exists('fhor_sb_toggle_system_alerts')) {
    function fhor_sb_toggle_system_alerts($user_id, $id, $on) {
        global $wpdb;
        if ($on && !fhor_sb_can_email_alerts($user_id)) {
            return new WP_Error('tier', 'Daily email alerts are included with Premium membership.');
        }
        $wpdb->update(
            fhor_sb_systems_table(),
            [
                'is_alert_enabled' => $on ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => intval($id), 'user_id' => intval($user_id)]
        );
        fhor_sb_bust_qualifier_cache($user_id);
        return true;
    }
}

if (!function_exists('fhor_sb_alert_user_ids')) {
    function fhor_sb_alert_user_ids() {
        global $wpdb;
        fhor_sb_install_table();
        $table = fhor_sb_systems_table();
        $ids = $wpdb->get_col("SELECT DISTINCT user_id FROM `$table` WHERE is_alert_enabled = 1");
        return array_map('intval', is_array($ids) ? $ids : []);
    }
}

if (!function_exists('fhor_sb_require_access_ajax')) {
    function fhor_sb_require_access_ajax() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Log in to use System Builder.'], 401);
        }
        if (!fhor_sb_user_can_access()) {
            wp_send_json_error(['message' => 'System Builder is available to Fhorsite members.'], 403);
        }
        check_ajax_referer('fhor_system_builder', 'nonce');
    }
}

if (!function_exists('fhor_sb_ajax_backtest')) {
    function fhor_sb_ajax_backtest() {
        fhor_sb_require_access_ajax();
        $filters = fhor_sb_sanitize_filters($_POST['filters'] ?? []);
        wp_send_json_success(fhor_sb_run_backtest($filters));
    }
}
add_action('wp_ajax_fhor_sb_backtest', 'fhor_sb_ajax_backtest');

if (!function_exists('fhor_sb_ajax_qualifiers')) {
    function fhor_sb_ajax_qualifiers() {
        fhor_sb_require_access_ajax();
        $filters = fhor_sb_sanitize_filters($_POST['filters'] ?? []);
        wp_send_json_success([
            'today' => fhor_sb_today_qualifiers($filters),
            'tomorrow' => fhor_sb_today_qualifiers($filters, function_exists('bricks_daily_archive_tomorrow')
                ? bricks_daily_archive_tomorrow()
                : wp_date('Y-m-d', strtotime('+1 day'))),
        ]);
    }
}
add_action('wp_ajax_fhor_sb_qualifiers', 'fhor_sb_ajax_qualifiers');

if (!function_exists('fhor_sb_ajax_save')) {
    function fhor_sb_ajax_save() {
        fhor_sb_require_access_ajax();
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        if ($name === '') {
            $name = 'System ' . wp_date('j M H:i');
        }
        $filters = fhor_sb_sanitize_filters($_POST['filters'] ?? []);
        $alerts = !empty($_POST['alerts']);
        $id = fhor_sb_insert_system(get_current_user_id(), $name, $filters, $alerts);
        if (is_wp_error($id)) {
            wp_send_json_error(['message' => $id->get_error_message()], 400);
        }
        wp_send_json_success(['id' => $id, 'systems' => fhor_sb_get_saved()]);
    }
}
add_action('wp_ajax_fhor_sb_save', 'fhor_sb_ajax_save');

if (!function_exists('fhor_sb_ajax_delete')) {
    function fhor_sb_ajax_delete() {
        fhor_sb_require_access_ajax();
        $id = intval($_POST['id'] ?? 0);
        fhor_sb_delete_system(get_current_user_id(), $id);
        wp_send_json_success(['systems' => fhor_sb_get_saved()]);
    }
}
add_action('wp_ajax_fhor_sb_delete', 'fhor_sb_ajax_delete');

if (!function_exists('fhor_sb_ajax_toggle_alerts')) {
    function fhor_sb_ajax_toggle_alerts() {
        fhor_sb_require_access_ajax();
        $id = intval($_POST['id'] ?? 0);
        $on = !empty($_POST['alerts']);
        $result = fhor_sb_toggle_system_alerts(get_current_user_id(), $id, $on);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 403);
        }
        wp_send_json_success(['systems' => fhor_sb_get_saved()]);
    }
}
add_action('wp_ajax_fhor_sb_toggle_alerts', 'fhor_sb_ajax_toggle_alerts');

if (!function_exists('fhor_sb_ajax_list')) {
    function fhor_sb_ajax_list() {
        fhor_sb_require_access_ajax();
        wp_send_json_success(['systems' => fhor_sb_get_saved()]);
    }
}
add_action('wp_ajax_fhor_sb_list', 'fhor_sb_ajax_list');

if (!function_exists('fhor_sb_send_daily_alert_emails')) {
    function fhor_sb_send_daily_alert_emails() {
        $tomorrow = function_exists('bricks_daily_archive_tomorrow')
            ? bricks_daily_archive_tomorrow()
            : wp_date('Y-m-d', strtotime('+1 day'));
        foreach (fhor_sb_alert_user_ids() as $user_id) {
            if (!fhor_sb_can_email_alerts($user_id)) {
                continue;
            }
            $user = get_userdata($user_id);
            if (!$user || !is_email($user->user_email)) {
                continue;
            }
            $systems = fhor_sb_get_saved($user_id);
            $blocks = [];
            foreach ($systems as $sys) {
                if (empty($sys['alerts']) || empty($sys['filters'])) {
                    continue;
                }
                $q = fhor_sb_today_qualifiers($sys['filters'], $tomorrow);
                if (empty($q['rows'])) {
                    continue;
                }
                $lines = [($sys['name'] ?? 'System') . ' — ' . intval($q['count']) . ' qualifier(s)'];
                foreach (array_slice($q['rows'], 0, 12) as $row) {
                    $dosage = '';
                    if (($row['di'] ?? null) !== null || ($row['cd'] ?? null) !== null) {
                        $dosage = '  DI ' . ($row['di'] ?? '–') . ' CD ' . ($row['cd'] ?? '–');
                    }
                    $lines[] = trim(($row['time'] ?? '') . ' ' . ($row['course'] ?? '') . ' · ' . ($row['horse'] ?? '') . '  FSr ' . ($row['fsr'] ?? '–') . $dosage);
                }
                $blocks[] = implode("\n", $lines);
            }
            if (empty($blocks)) {
                continue;
            }
            $body = "Tomorrow's System Builder qualifiers on Fhorsite (" . $tomorrow . "):\n\n"
                . implode("\n\n", $blocks)
                . "\n\nDashboard: " . fhor_sb_qualifiers_url()
                . "\nBuilder: " . fhor_sb_url();
            wp_mail(
                $user->user_email,
                'Fhorsite alerts — ' . $tomorrow,
                $body
            );
        }
    }
}
add_action('fhor_sb_daily_alerts', 'fhor_sb_send_daily_alert_emails');

if (!function_exists('fhor_sb_schedule_cron')) {
    function fhor_sb_schedule_cron() {
        if (get_option('fhor_sb_cron_ver') !== '3') {
            wp_clear_scheduled_hook('fhor_sb_daily_alerts');
            update_option('fhor_sb_cron_ver', '3');
        }
        if (!wp_next_scheduled('fhor_sb_daily_alerts')) {
            $tz = wp_timezone_string();
            $ts = strtotime('today 20:30:00 ' . $tz);
            if ($ts === false || $ts < time()) {
                $ts = strtotime('tomorrow 20:30:00 ' . $tz);
            }
            if ($ts) {
                wp_schedule_event($ts, 'daily', 'fhor_sb_daily_alerts');
            }
        }
    }
}
add_action('init', 'fhor_sb_schedule_cron', 40);

if (!function_exists('fhor_sb_enqueue')) {
    function fhor_sb_enqueue() {
        $qa = function_exists('fhor_sb_qa_is_active') && fhor_sb_qa_is_active();
        if (!$qa && !fhor_sb_is_request() && !(function_exists('bricks_current_post_has_shortcode') && bricks_current_post_has_shortcode(['system_builder', 'system_builder_qa']))) {
            return;
        }
        $js = get_stylesheet_directory() . '/system-builder.js';
        if (file_exists($js)) {
            wp_enqueue_script('fhor-system-builder', get_stylesheet_directory_uri() . '/system-builder.js', [], filemtime($js), true);
            wp_localize_script('fhor-system-builder', 'fhorSb', [
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fhor_system_builder'),
                'canSave' => is_user_logged_in() ? 1 : 0,
                'premium' => fhor_sb_is_premium() ? 1 : 0,
                'canAlert' => fhor_sb_can_email_alerts() ? 1 : 0,
                'maxSaved' => fhor_sb_max_saved(),
                'qualifiersUrl' => fhor_sb_qualifiers_url(),
            ]);
        }
    }
}
add_action('wp_enqueue_scripts', 'fhor_sb_enqueue', 28);

if (!function_exists('fhor_sb_shortcode')) {
    function fhor_sb_shortcode() {
        if (!is_user_logged_in()) {
            $login = wp_login_url(fhor_sb_url());
            return '<div class="sb-gate"><h1>System Builder</h1><p>Log in to filter historic Fhorsite metrics, measure ROI, and save a system.</p><p><a class="sb-btn sb-btn-primary" href="' . esc_url($login) . '">Log in</a></p></div>';
        }

        $premium = fhor_sb_is_premium();
        $signup = function_exists('fhor_get_membership_signup_url') ? fhor_get_membership_signup_url() : home_url('/register/');
        $saved = fhor_sb_get_saved();
        $max_days = fhor_sb_max_lookback_days();
        ob_start();
        ?>
        <style>
        .sb-page{--sb-green:#16a34a;--sb-ink:#0f172a;box-sizing:border-box;max-width:1320px;margin:0 auto;color:var(--sb-ink);padding-bottom:2rem}
        .sb-page *{box-sizing:border-box}
        .sb-hero{margin:0 0 1.15rem;padding:.15rem 0 .2rem}
        .sb-title{margin:0 0 .35rem;font-size:clamp(1.55rem,2.6vw,2.05rem);letter-spacing:-.02em}
        .sb-lead{margin:0;color:#475569;max-width:46rem;line-height:1.55}
        .sb-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.92fr);gap:1.35rem;align-items:start}
        .sb-panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1rem 1.05rem 1.1rem;box-shadow:0 1px 4px rgba(15,23,42,.05)}
        .sb-panel h2{margin:0 0 .7rem;font-size:1.02rem}
        .sb-form{min-width:0}
        .sb-group{margin:0 0 .65rem;padding:.7rem .8rem .8rem;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}
        .sb-group[open]{background:#fff}
        .sb-group summary{cursor:pointer;font-size:.72rem;letter-spacing:.05em;text-transform:uppercase;color:#475569;font-weight:800;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:.5rem}
        .sb-group summary::-webkit-details-marker{display:none}
        .sb-group summary::after{content:'+';font-size:1rem;line-height:1;color:#94a3b8}
        .sb-group[open] summary::after{content:'–'}
        .sb-group .sb-grid,.sb-group .sb-note,.sb-group .sb-checks{margin-top:.65rem}
        .sb-grid{display:grid;grid-template-columns:1fr 1fr;gap:.55rem .6rem}
        .sb-field{display:flex;flex-direction:column;gap:.25rem;min-width:0}
        .sb-field.span2{grid-column:1 / -1}
        .sb-field label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
        .sb-field input,.sb-field select{width:100%;max-width:100%;padding:.42rem .5rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.85rem;background:#fff}
        .sb-page select{width:100%}
        .sb-checks{display:flex;flex-wrap:wrap;gap:.3rem .65rem}
        .sb-checks label{font-size:.82rem;font-weight:600;color:#334155;display:flex;gap:.3rem;align-items:center}
        .sb-actions{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
        .sb-actions-sticky{position:sticky;bottom:0;z-index:3;margin:.85rem -1.05rem -1.1rem;padding:.75rem 1.05rem;background:linear-gradient(180deg,rgba(255,255,255,.7),#fff 28%);border-top:1px solid #e2e8f0;border-radius:0 0 14px 14px}
        .sb-btn{display:inline-flex;align-items:center;justify-content:center;padding:.55rem .9rem;border-radius:8px;border:1px solid #e2e8f0;background:#fff;font-weight:700;cursor:pointer;font-size:.88rem;color:#0f172a;text-decoration:none}
        .sb-btn-primary{background:var(--sb-green);border-color:var(--sb-green);color:#fff}
        .sb-btn:disabled{opacity:.55;cursor:wait}
        .sb-results-col{position:sticky;top:calc(var(--wp-admin--admin-bar-height,32px) + 10px);display:flex;flex-direction:column;gap:1rem;min-width:0;max-height:calc(100vh - 72px);overflow:auto;padding-bottom:.25rem}
        .sb-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.6rem;margin-bottom:1rem}
        .sb-stat{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.7rem .8rem}
        .sb-stat span{display:block;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#64748b}
        .sb-stat b{font-size:1.15rem}
        .sb-stat b.is-pos{color:#15803d}
        .sb-stat b.is-neg{color:#b91c1c}
        .sb-book{margin:0 0 .85rem}
        .sb-book h3{margin:0 0 .45rem;font-size:.78rem;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
        .sb-tier{display:inline-flex;align-items:center;gap:.4rem;margin:.45rem 0 0;padding:.28rem .6rem;border-radius:999px;font-size:.75rem;font-weight:700;background:#ecfdf5;color:#047857}
        .sb-tier.is-free{background:#fff7ed;color:#c2410c}
        .sb-tabs{display:flex;gap:.4rem;margin:0 0 .7rem}
        .sb-tab{border:1px solid #e2e8f0;background:#fff;border-radius:999px;padding:.3rem .7rem;font-size:.8rem;font-weight:700;cursor:pointer}
        .sb-tab.is-on{background:#0f172a;border-color:#0f172a;color:#fff}
        .sb-table{width:100%;border-collapse:collapse;font-size:.82rem}
        .sb-table th{text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;padding:.4rem;border-bottom:1px solid #e2e8f0}
        .sb-table td{padding:.45rem .4rem;border-bottom:1px solid #f1f5f9}
        .sb-table a{color:#15803d;font-weight:700;text-decoration:none}
        .sb-saved{display:flex;flex-direction:column;gap:.5rem}
        .sb-saved-item{display:flex;flex-wrap:wrap;gap:.45rem;align-items:center;padding:.6rem .7rem;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}
        .sb-saved-item b{flex:1}
        .sb-saved-item .is-on{border-color:#16a34a;color:#15803d;background:#f0fdf4}
        .sb-empty{color:#64748b;font-size:.9rem;padding:.85rem .9rem;margin:0;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;line-height:1.45}
        .sb-note{font-size:.78rem;color:#64748b;margin:.4rem 0 0;line-height:1.45}
        .sb-gate{max-width:640px;margin:2rem auto;padding:1.5rem;background:#fff;border:1px solid #e2e8f0;border-radius:14px}
        @media (max-width:980px){
          .sb-layout{grid-template-columns:1fr}
          .sb-results-col{position:static;max-height:none;overflow:visible}
          .sb-stats{grid-template-columns:1fr 1fr}
          .sb-actions-sticky{position:static;margin:.75rem 0 0;padding:.75rem 0 0;border-radius:0}
        }
        </style>
        <div class="sb-page" id="fhor-system-builder">
            <header class="sb-hero">
                <h1 class="sb-title">System Builder</h1>
                <p class="sb-lead">Chain Fhorsite ratings, ranks, and race rules. Run the system on historic data (Industry SP and Betfair SP), save it, and review daily qualifiers.</p>
                <?php if ($premium): ?>
                    <p class="sb-tier">Premium · 5-year history · unlimited saves · email alerts</p>
                <?php else: ?>
                    <p class="sb-tier is-free">Free · 1-year history · 1 saved system · dashboard qualifiers only. <a href="<?php echo esc_url($signup); ?>">Upgrade for alerts</a></p>
                <?php endif; ?>
            </header>
            <div class="sb-layout">
                <form class="sb-panel sb-form" id="sb-form">
                    <h2>Rules</h2>
                    <details class="sb-group" open>
                        <summary>Sample &amp; staking</summary>
                        <div class="sb-grid">
                            <div class="sb-field"><label for="sb-days">Lookback</label>
                                <select id="sb-days" name="days">
                                    <option value="7">Last 7 days</option>
                                    <option value="14">Last 14 days</option>
                                    <option value="30">Last 30 days</option>
                                    <option value="90" selected>Last 90 days</option>
                                    <option value="180">Last 6 months</option>
                                    <option value="365">Last 12 months</option>
                                    <?php if ($max_days > 365): ?>
                                        <option value="730">Last 2 years</option>
                                        <option value="1095">Last 3 years</option>
                                        <option value="1825">Last 5 years</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="sb-field"><label for="sb-bet">Bet type</label>
                                <select id="sb-bet" name="bet">
                                    <option value="win">Win (1pt)</option>
                                    <option value="place">Place (1pt, 1/4 odds)</option>
                                    <option value="ew">Each-way (1pt win + 1pt place)</option>
                                </select>
                            </div>
                            <div class="sb-field span2"><label for="sb-pick">If several qualify in a race</label>
                                <select id="sb-pick" name="pick_mode">
                                    <option value="all">Back them all</option>
                                    <option value="top_fsr">Highest Fhorsite rating only</option>
                                    <option value="top_sr">Highest speed figure only</option>
                                    <option value="top_pts">Highest Points Engine score only</option>
                                </select>
                            </div>
                        </div>
                    </details>
                    <details class="sb-group" open>
                        <summary>Race</summary>
                        <div class="sb-grid">
                            <div class="sb-field span2"><label>Country</label>
                                <div class="sb-checks">
                                    <label><input type="checkbox" name="country[]" value="England"> England</label>
                                    <label><input type="checkbox" name="country[]" value="Ireland"> Ireland</label>
                                    <label><input type="checkbox" name="country[]" value="Scotland"> Scotland</label>
                                    <label><input type="checkbox" name="country[]" value="Wales"> Wales</label>
                                </div>
                            </div>
                            <div class="sb-field span2"><label>Race type</label>
                                <div class="sb-checks">
                                    <label><input type="checkbox" name="race_type[]" value="Flat"> Flat</label>
                                    <label><input type="checkbox" name="race_type[]" value="Hurdle"> Hurdle</label>
                                    <label><input type="checkbox" name="race_type[]" value="Chase"> Chase</label>
                                    <label><input type="checkbox" name="race_type[]" value="NH Flat"> NH Flat</label>
                                </div>
                            </div>
                            <div class="sb-field"><label for="sb-course">Course contains</label><input id="sb-course" name="course" type="text" placeholder="e.g. York"></div>
                            <div class="sb-field"><label for="sb-class">Class</label><input id="sb-class" name="class" type="text" placeholder="e.g. 4"></div>
                            <div class="sb-field"><label for="sb-track">Track type</label>
                                <select id="sb-track" name="track_type">
                                    <option value="">Any</option>
                                    <option value="Turf">Turf</option>
                                    <option value="AW">All-Weather</option>
                                </select>
                            </div>
                            <div class="sb-field"><label for="sb-hcap">Handicap</label>
                                <select id="sb-hcap" name="handicap">
                                    <option value="">Any</option>
                                    <option value="yes">Handicaps</option>
                                    <option value="no">Non-handicaps</option>
                                </select>
                            </div>
                            <div class="sb-field"><label for="sb-age">Age band</label><input id="sb-age" name="age_range" type="text" placeholder="e.g. 3yo+"></div>
                            <div class="sb-field"><label>Going contains</label>
                                <div class="sb-checks">
                                    <label><input type="checkbox" name="going[]" value="Firm"> Firm</label>
                                    <label><input type="checkbox" name="going[]" value="Good"> Good</label>
                                    <label><input type="checkbox" name="going[]" value="Soft"> Soft</label>
                                    <label><input type="checkbox" name="going[]" value="Heavy"> Heavy</label>
                                    <label><input type="checkbox" name="going[]" value="Standard"> Standard</label>
                                </div>
                            </div>
                            <div class="sb-field"><label for="sb-dfmin">Min furlongs</label><input id="sb-dfmin" name="dist_f_min" type="number" min="0" step="0.5"></div>
                            <div class="sb-field"><label for="sb-dfmax">Max furlongs</label><input id="sb-dfmax" name="dist_f_max" type="number" min="0" step="0.5"></div>
                            <div class="sb-field"><label for="sb-fmin">Min field</label><input id="sb-fmin" name="field_min" type="number" min="2"></div>
                            <div class="sb-field"><label for="sb-fmax">Max field</label><input id="sb-fmax" name="field_max" type="number" min="2"></div>
                        </div>
                    </details>
                    <details class="sb-group" open>
                        <summary>Ratings &amp; ranks</summary>
                        <div class="sb-grid">
                            <div class="sb-field"><label for="sb-fsrmin">FSr min</label><input id="sb-fsrmin" name="fsr_min" type="number" step="0.1"></div>
                            <div class="sb-field"><label for="sb-fsrmax">FSr max</label><input id="sb-fsrmax" name="fsr_max" type="number" step="0.1"></div>
                            <div class="sb-field"><label for="sb-fsrrank">FSr rank ≤</label>
                                <select id="sb-fsrrank" name="fsr_rank_max">
                                    <option value="">Any</option>
                                    <option value="1">Top-rated</option>
                                    <option value="2">Top 2</option>
                                    <option value="3">Top 3</option>
                                </select>
                            </div>
                            <div class="sb-field"><label for="sb-ptsrank">Pts rank ≤</label>
                                <select id="sb-ptsrank" name="pts_rank_max">
                                    <option value="">Any</option>
                                    <option value="1">Top-rated</option>
                                    <option value="2">Top 2</option>
                                    <option value="3">Top 3</option>
                                </select>
                            </div>
                            <div class="sb-field"><label for="sb-srrank">Speed rank ≤</label>
                                <select id="sb-srrank" name="sr_rank_max">
                                    <option value="">Any</option>
                                    <option value="1">Top-rated</option>
                                    <option value="2">Top 2</option>
                                    <option value="3">Top 3</option>
                                </select>
                            </div>
                            <div class="sb-field"><label for="sb-srmin">Speed fig min</label><input id="sb-srmin" name="sr_min" type="number"></div>
                            <div class="sb-field"><label for="sb-srmax">Speed fig max</label><input id="sb-srmax" name="sr_max" type="number"></div>
                            <div class="sb-field"><label for="sb-fsrrmin">FSRr min</label><input id="sb-fsrrmin" name="fsrr_min" type="number" step="0.1" placeholder="reliability"></div>
                            <div class="sb-field"><label for="sb-srlto">SR LTO min</label><input id="sb-srlto" name="sr_lto_min" type="number"></div>
                            <div class="sb-field"><label for="sb-ormin">OR min</label><input id="sb-ormin" name="or_min" type="number"></div>
                            <div class="sb-field"><label for="sb-ormax">OR max</label><input id="sb-ormax" name="or_max" type="number"></div>
                            <div class="sb-field"><label for="sb-ordmin">OR+/- min</label><input id="sb-ordmin" name="or_diff_min" type="number" step="0.1"></div>
                            <div class="sb-field"><label for="sb-ordmax">OR+/- max</label><input id="sb-ordmax" name="or_diff_max" type="number" step="0.1"></div>
                            <div class="sb-field"><label for="sb-clsmin">Class diff min</label><input id="sb-clsmin" name="cls_min" type="number" step="0.1"></div>
                            <div class="sb-field"><label for="sb-clsmax">Class diff max</label><input id="sb-clsmax" name="cls_max" type="number" step="0.1"></div>
                        </div>
                    </details>
                    <details class="sb-group">
                        <summary>Dosage (DI / CD)</summary>
                        <div class="sb-grid">
                            <div class="sb-field"><label for="sb-dimin">DI min</label><input id="sb-dimin" name="di_min" type="number" step="0.01" placeholder="e.g. 1.20"></div>
                            <div class="sb-field"><label for="sb-dimax">DI max</label><input id="sb-dimax" name="di_max" type="number" step="0.01" placeholder="e.g. 3.00"></div>
                            <div class="sb-field"><label for="sb-cdmin">CD min</label><input id="sb-cdmin" name="cd_min" type="number" step="0.01" placeholder="e.g. 0.00"></div>
                            <div class="sb-field"><label for="sb-cdmax">CD max</label><input id="sb-cdmax" name="cd_max" type="number" step="0.01" placeholder="e.g. 0.80"></div>
                        </div>
                        <p class="sb-dosage-presets" style="margin-top:.65rem;display:flex;flex-wrap:wrap;gap:.45rem;">
                            <button type="button" class="sb-btn sb-dosage-preset" data-preset="sprinter">Sprint speed (≤6f)</button>
                            <button type="button" class="sb-btn sb-dosage-preset" data-preset="stamina">Staying stamina (≥12f)</button>
                        </p>
                        <p class="sb-note">Dosage Index and Center of Distribution use the same 4-generation Chefs-de-Race male line as the race card. Higher DI/CD means more speed influence; lower values suit stamina types. Set a minimum, a maximum, or both. A horse with no dosage figure is left out once a level is set. The rule applies to historic results and to today’s qualifiers. Qualifier lists always show DI/CD for review. A long lookback takes longer, because each horse is scored from its pedigree.</p>
                    </details>
                    <details class="sb-group">
                        <summary>Draw, fitness, connections</summary>
                        <div class="sb-grid">
                            <div class="sb-field"><label for="sb-dbmin">Draw bias % min</label><input id="sb-dbmin" name="db_min" type="number" step="0.1"></div>
                            <div class="sb-field"><label for="sb-dbmax">Draw bias % max</label><input id="sb-dbmax" name="db_max" type="number" step="0.1"></div>
                            <div class="sb-field"><label for="sb-dslrmin">DSLR min</label><input id="sb-dslrmin" name="dslr_min" type="number"></div>
                            <div class="sb-field"><label for="sb-dslrmax">DSLR max</label><input id="sb-dslrmax" name="dslr_max" type="number"></div>
                            <div class="sb-field"><label for="sb-tnr">Trainer 14d win% min</label><input id="sb-tnr" name="tnr_min" type="number" step="0.1"></div>
                            <div class="sb-field"><label for="sb-comb">Trainer/jockey comb min</label><input id="sb-comb" name="comb_min" type="number" step="0.1"></div>
                            <div class="sb-field"><label for="sb-winp">Horse win% min</label><input id="sb-winp" name="win_strike_min" type="number" step="0.1"></div>
                            <div class="sb-field"><label for="sb-plp">Horse place% min</label><input id="sb-plp" name="place_strike_min" type="number" step="0.1"></div>
                            <div class="sb-field"><label for="sb-agehmin">Horse age min</label><input id="sb-agehmin" name="age_min" type="number" min="2"></div>
                            <div class="sb-field"><label for="sb-agehmax">Horse age max</label><input id="sb-agehmax" name="age_max" type="number" min="2"></div>
                            <div class="sb-field"><label for="sb-stallmin">Stall min</label><input id="sb-stallmin" name="stall_min" type="number" min="1"></div>
                            <div class="sb-field"><label for="sb-stallmax">Stall max</label><input id="sb-stallmax" name="stall_max" type="number" min="1"></div>
                            <div class="sb-field"><label for="sb-tnrname">Trainer contains</label><input id="sb-tnrname" name="trainer" type="text" placeholder="e.g. Haggas"></div>
                            <div class="sb-field"><label for="sb-jky">Jockey contains</label><input id="sb-jky" name="jockey" type="text" placeholder="e.g. Moore"></div>
                        </div>
                    </details>
                    <details class="sb-group">
                        <summary>Pace &amp; running style</summary>
                        <div class="sb-grid">
                            <div class="sb-field"><label for="sb-zone">Pace zone</label>
                                <select id="sb-zone" name="pace_zone">
                                    <option value="">Any</option>
                                    <option value="1">1 · Pace setters</option>
                                    <option value="2">2 · Prominent</option>
                                    <option value="3">3 · Midfield</option>
                                    <option value="4">4 · Rear chasers</option>
                                </select>
                            </div>
                            <div class="sb-field"><label for="sb-style">Career style</label>
                                <select id="sb-style" name="style">
                                    <option value="">Any</option>
                                    <option value="leader">Leaders</option>
                                    <option value="prominent">Prominent / handy</option>
                                    <option value="heldup">Held up</option>
                                </select>
                            </div>
                            <div class="sb-field span2"><label for="sb-pms">PMS min</label><input id="sb-pms" name="pms_min" type="number" min="0" max="100" placeholder="Pace Mapping Score"></div>
                        </div>
                        <p class="sb-note">Pace zone, PMS, and scenario flags (lone leader / swooper) apply to today’s card and email alerts. Historic ROI uses ratings, ranks, and the other rules above.</p>
                    </details>
                    <details class="sb-group">
                        <summary>Market &amp; flags</summary>
                        <div class="sb-grid">
                            <div class="sb-field"><label for="sb-odmin">Forecast odds min</label><input id="sb-odmin" name="odds_min" type="number" min="1" step="0.1" placeholder="decimal"></div>
                            <div class="sb-field"><label for="sb-odmax">Forecast odds max</label><input id="sb-odmax" name="odds_max" type="number" min="1" step="0.1"></div>
                            <div class="sb-field"><label for="sb-spmin">Settled SP min</label><input id="sb-spmin" name="sp_min" type="number" min="1" step="0.1"></div>
                            <div class="sb-field"><label for="sb-spmax">Settled SP max</label><input id="sb-spmax" name="sp_max" type="number" min="1" step="0.1"></div>
                            <div class="sb-field span2"><label>Must have</label>
                                <div class="sb-checks">
                                    <label><input type="checkbox" name="flags[]" value="course"> Course winner</label>
                                    <label><input type="checkbox" name="flags[]" value="distance"> Distance winner</label>
                                    <label><input type="checkbox" name="flags[]" value="cd"> C&amp;D winner</label>
                                    <label><input type="checkbox" name="flags[]" value="going"> Going winner</label>
                                    <label><input type="checkbox" name="flags[]" value="lbf"> Beaten favourite</label>
                                    <label><input type="checkbox" name="flags[]" value="last_win"> Won last time</label>
                                    <label><input type="checkbox" name="flags[]" value="last_placed"> Placed last time</label>
                                    <label><input type="checkbox" name="flags[]" value="lone_leader"> Lone leader (today)</label>
                                    <label><input type="checkbox" name="flags[]" value="swooper"> Swooper (today)</label>
                                </div>
                            </div>
                        </div>
                        <p class="sb-note">Forecast odds are the pre-race price used for live alerts. Settled SP is for historic research only. FSr uses point-in-time backtest comment ratings where available. Points rank uses the same engine as the race card.</p>
                    </details>
                    <div class="sb-actions sb-actions-sticky">
                        <button type="submit" class="sb-btn sb-btn-primary" id="sb-run">Run System</button>
                        <button type="button" class="sb-btn" id="sb-today">Find qualifiers</button>
                        <input type="text" id="sb-name" placeholder="Name this system" style="flex:1;min-width:140px;padding:.5rem .65rem;border:1px solid #e2e8f0;border-radius:8px;">
                        <?php if ($premium): ?>
                            <button type="button" class="sb-btn sb-btn-primary" id="sb-save-alerts">Save System &amp; Enable Alerts</button>
                            <button type="button" class="sb-btn" id="sb-save">Save without email</button>
                        <?php else: ?>
                            <button type="button" class="sb-btn" id="sb-save">Save system</button>
                        <?php endif; ?>
                        <input type="checkbox" id="sb-alerts" hidden <?php echo $premium ? '' : 'disabled'; ?>>
                    </div>
                </form>
                <div class="sb-results-col">
                    <div class="sb-panel" id="sb-results">
                        <h2>Results</h2>
                        <p class="sb-empty" id="sb-results-empty">Set your rules and click Run System. Stats use a flat 1-unit stake (2 units each-way) and show Industry SP and Betfair SP separately. The table lists the last 20 historic qualifiers.</p>
                        <div id="sb-results-body" hidden>
                            <div class="sb-book">
                                <h3>Industry SP</h3>
                                <div class="sb-stats">
                                    <div class="sb-stat"><span>ROI</span><b id="sb-roi">–</b></div>
                                    <div class="sb-stat"><span>P/L</span><b id="sb-profit">–</b></div>
                                    <div class="sb-stat"><span>Bets</span><b id="sb-bets">–</b></div>
                                    <div class="sb-stat"><span>Win rate</span><b id="sb-wr">–</b></div>
                                </div>
                            </div>
                            <div class="sb-book" id="sb-bsp-book">
                                <h3>Betfair SP</h3>
                                <div class="sb-stats">
                                    <div class="sb-stat"><span>ROI</span><b id="sb-bsp-roi">–</b></div>
                                    <div class="sb-stat"><span>P/L</span><b id="sb-bsp-profit">–</b></div>
                                    <div class="sb-stat"><span>Bets</span><b id="sb-bsp-bets">–</b></div>
                                    <div class="sb-stat"><span>Win rate</span><b id="sb-bsp-wr">–</b></div>
                                </div>
                            </div>
                            <p class="sb-note" id="sb-range"></p>
                            <p class="sb-note" id="sb-pace-note" hidden></p>
                            <p class="sb-note" id="sb-dosage-note" hidden></p>
                            <div style="overflow-x:auto;margin-top:.75rem;">
                                <table class="sb-table" id="sb-sample-table">
                                    <thead><tr><th>Horse</th><th>Race</th><th id="sb-sample-di-th" hidden>DI</th><th id="sb-sample-cd-th" hidden>CD</th><th>ISP</th><th>BSP</th><th>Pos</th><th>P/L</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="sb-panel" id="sb-today-panel">
                        <h2>Daily qualifiers</h2>
                        <p class="sb-note" style="margin:0 0 .6rem;"><a href="<?php echo esc_url(fhor_sb_qualifiers_url()); ?>">Open My Daily Qualifiers</a></p>
                        <div class="sb-tabs" id="sb-q-tabs">
                            <button type="button" class="sb-tab is-on" data-day="tomorrow">Tomorrow</button>
                            <button type="button" class="sb-tab" data-day="today">Today</button>
                        </div>
                        <p class="sb-empty" id="sb-today-empty">Run “Find qualifiers” to map these rules onto the live card (tomorrow after declarations, plus today).</p>
                        <div id="sb-today-body" hidden>
                            <p class="sb-note" id="sb-today-meta"></p>
                            <div style="overflow-x:auto;">
                                <table class="sb-table" id="sb-today-table">
                                    <thead><tr><th>Time</th><th>Horse</th><th>Course</th><th>FSr</th><th>Rank</th><th>Pts</th><th>DI</th><th>CD</th><th>Price</th><th></th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="sb-panel" id="sb-saved-panel">
                        <h2>Saved systems</h2>
                        <div class="sb-saved" id="sb-saved">
                            <?php if (empty($saved)): ?>
                                <p class="sb-empty">No saved systems yet.</p>
                            <?php else: ?>
                                <?php foreach ($saved as $sys): ?>
                                    <div class="sb-saved-item" data-id="<?php echo esc_attr($sys['id']); ?>">
                                        <b><?php echo esc_html($sys['name']); ?></b>
                                        <?php if (!empty($sys['alerts'])): ?>
                                            <button type="button" class="sb-btn sb-alert is-on">Alerts on</button>
                                        <?php else: ?>
                                            <button type="button" class="sb-btn sb-alert">Alerts off</button>
                                        <?php endif; ?>
                                        <button type="button" class="sb-btn sb-load">Load</button>
                                        <button type="button" class="sb-btn sb-del">Delete</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script type="application/json" id="sb-saved-json"><?php echo wp_json_encode($saved); ?></script>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('system_builder', 'fhor_sb_shortcode');

if (!function_exists('fhor_sb_user_dashboard_qualifiers')) {
    function fhor_sb_user_dashboard_qualifiers($user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        $today = function_exists('bricks_daily_archive_today') ? bricks_daily_archive_today() : wp_date('Y-m-d');
        $tomorrow = function_exists('bricks_daily_archive_tomorrow')
            ? bricks_daily_archive_tomorrow()
            : wp_date('Y-m-d', strtotime('+1 day'));
        $cache_key = 'fhor_sb_q_' . $user_id . '_' . $today;
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }
        $systems = fhor_sb_get_saved($user_id);
        $out = ['today' => $today, 'tomorrow' => $tomorrow, 'systems' => []];
        foreach ($systems as $sys) {
            $filters = is_array($sys['filters'] ?? null) ? $sys['filters'] : [];
            $out['systems'][] = [
                'id' => $sys['id'],
                'name' => $sys['name'],
                'alerts' => !empty($sys['alerts']),
                'today' => fhor_sb_today_qualifiers($filters, $today),
                'tomorrow' => fhor_sb_today_qualifiers($filters, $tomorrow),
            ];
        }
        set_transient($cache_key, $out, 20 * MINUTE_IN_SECONDS);
        return $out;
    }
}

if (!function_exists('fhor_sb_qualifiers_shortcode')) {
    function fhor_sb_qualifiers_shortcode() {
        if (!is_user_logged_in()) {
            return '<div class="sb-gate"><h1>My Daily Qualifiers</h1><p>Log in to see horses that match your saved systems.</p></div>';
        }
        $data = fhor_sb_user_dashboard_qualifiers();
        $signup = function_exists('fhor_get_membership_signup_url') ? fhor_get_membership_signup_url() : home_url('/register/');
        ob_start();
        ?>
        <style>
        .sb-page{box-sizing:border-box;max-width:1180px;margin:0 auto;color:#0f172a;padding-bottom:2rem}
        .sb-title{margin:0 0 .35rem;font-size:clamp(1.55rem,2.6vw,2.05rem)}
        .sb-lead{margin:0;color:#475569;line-height:1.55}
        .sb-hero{margin:0 0 1.15rem}
        .sb-panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1rem 1.05rem;margin-bottom:1rem}
        .sb-table{width:100%;border-collapse:collapse;font-size:.82rem}
        .sb-table th{text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;padding:.4rem;border-bottom:1px solid #e2e8f0}
        .sb-table td{padding:.45rem .4rem;border-bottom:1px solid #f1f5f9}
        .sb-table a{color:#15803d;font-weight:700;text-decoration:none}
        .sb-empty{color:#64748b;font-size:.9rem;padding:.85rem .9rem;margin:0;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc}
        .sb-note{font-size:.78rem;color:#64748b;margin:.4rem 0 0;line-height:1.45}
        .sb-gate{max-width:640px;margin:2rem auto;padding:1.5rem;background:#fff;border:1px solid #e2e8f0;border-radius:14px}
        </style>
        <div class="sb-page">
            <header class="sb-hero">
                <h1 class="sb-title">My Daily Qualifiers</h1>
                <p class="sb-lead">Horses on today’s and tomorrow’s cards that match your saved systems. Email digest is a Premium feature after declarations (20:30).</p>
                <p class="sb-note"><a href="<?php echo esc_url(fhor_sb_url()); ?>">Open System Builder</a><?php if (!fhor_sb_is_premium()): ?> · <a href="<?php echo esc_url($signup); ?>">Upgrade for email alerts</a><?php endif; ?></p>
            </header>
            <?php if (empty($data['systems'])): ?>
                <p class="sb-empty">No saved systems yet. Build one, save it, then qualifiers appear here.</p>
            <?php else: ?>
                <?php foreach ($data['systems'] as $sys): ?>
                    <div class="sb-panel" style="margin-bottom:1rem;">
                        <h2><?php echo esc_html($sys['name']); ?><?php echo !empty($sys['alerts']) ? ' · alerts on' : ''; ?></h2>
                        <?php foreach (['tomorrow' => 'Tomorrow', 'today' => 'Today'] as $key => $label): ?>
                            <?php $block = $sys[$key]; ?>
                            <h3 style="font-size:.85rem;margin:1rem 0 .4rem;"><?php echo esc_html($label); ?> · <?php echo esc_html($block['date'] ?? ''); ?> · <?php echo intval($block['count'] ?? 0); ?> qualifier(s)</h3>
                            <?php if (empty($block['rows'])): ?>
                                <p class="sb-empty">No matches.</p>
                            <?php else: ?>
                                <div style="overflow-x:auto;">
                                    <table class="sb-table">
                                        <thead><tr><th>Time</th><th>Horse</th><th>Course</th><th>FSr</th><th>Rank</th><th>Pts</th><th>DI</th><th>CD</th><th>Price</th><th></th></tr></thead>
                                        <tbody>
                                        <?php foreach ($block['rows'] as $row): ?>
                                            <tr>
                                                <td><?php echo esc_html($row['time'] ?? ''); ?></td>
                                                <td><?php if (!empty($row['race_url'])): ?><a href="<?php echo esc_url($row['race_url']); ?>"><?php echo esc_html($row['horse']); ?></a><?php else: echo esc_html($row['horse']); endif; ?></td>
                                                <td><?php echo esc_html($row['course'] ?? ''); ?></td>
                                                <td><?php echo $row['fsr'] === null ? '–' : esc_html((string) $row['fsr']); ?></td>
                                                <td><?php echo esc_html((string) ($row['fsr_rank'] ?? '–')); ?></td>
                                                <td><?php echo $row['pts'] === null ? '–' : esc_html((string) $row['pts']); ?></td>
                                                <td><?php echo ($row['di'] ?? null) === null ? '–' : esc_html((string) $row['di']); ?></td>
                                                <td><?php echo ($row['cd'] ?? null) === null ? '–' : esc_html((string) $row['cd']); ?></td>
                                                <td><?php echo esc_html($row['forecast'] ?? ''); ?></td>
                                                <td><?php
                                                if (function_exists('fhor_bt_log_button_html')) {
                                                    echo fhor_bt_log_button_html([
                                                        'horse' => $row['horse'] ?? '',
                                                        'course' => $row['course'] ?? '',
                                                        'time' => $row['time'] ?? '',
                                                        'date' => $block['date'] ?? '',
                                                        'odds' => $row['forecast'] ?? '',
                                                        'system' => $sys['name'] ?? '',
                                                        'system_id' => $sys['id'] ?? '',
                                                    ]);
                                                }
                                                ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('system_builder_qualifiers', 'fhor_sb_qualifiers_shortcode');

if (!function_exists('fhor_sb_add_rewrite')) {
    function fhor_sb_add_rewrite() {
        add_rewrite_tag('%fhor_system_builder%', '([0-9]+)');
        add_rewrite_tag('%fhor_my_qualifiers%', '([0-9]+)');
        add_rewrite_rule('^system-builder/?$', 'index.php?fhor_system_builder=1', 'top');
        add_rewrite_rule('^my-qualifiers/?$', 'index.php?fhor_my_qualifiers=1', 'top');
    }
}
add_action('init', 'fhor_sb_add_rewrite', 20);

if (!function_exists('fhor_sb_query_vars')) {
    function fhor_sb_query_vars($vars) {
        $vars[] = 'fhor_system_builder';
        $vars[] = 'fhor_my_qualifiers';
        return $vars;
    }
}
add_filter('query_vars', 'fhor_sb_query_vars');

if (!function_exists('fhor_sb_template_redirect')) {
    function fhor_sb_template_redirect() {
        if (is_admin()) {
            return;
        }
        if (fhor_sb_is_qualifiers_request()) {
            status_header(200);
            nocache_headers();
            get_header();
            echo '<main id="brx-content" class="sb-page-shell"><div style="padding:0 4px;">';
            echo do_shortcode('[system_builder_qualifiers]');
            echo '</div></main>';
            get_footer();
            exit;
        }
        if (!fhor_sb_is_request()) {
            return;
        }
        status_header(200);
        nocache_headers();
        get_header();
        echo '<main id="brx-content" class="sb-page-shell"><div style="padding:0 4px;">';
        echo do_shortcode('[system_builder]');
        echo '</div></main>';
        get_footer();
        exit;
    }
}
add_action('template_redirect', 'fhor_sb_template_redirect', 2);

if (!function_exists('fhor_sb_flush_rewrites')) {
    function fhor_sb_flush_rewrites() {
        if (get_option('fhor_sb_rewrite_flushed') !== '2') {
            flush_rewrite_rules();
            update_option('fhor_sb_rewrite_flushed', '2');
        }
    }
}
add_action('init', 'fhor_sb_flush_rewrites', 999);

if (!function_exists('fhor_sb_document_title')) {
    function fhor_sb_document_title($title) {
        if (fhor_sb_is_qualifiers_request()) {
            return 'My Daily Qualifiers | Fhorsite';
        }
        if (fhor_sb_is_request()) {
            return 'System Builder | Fhorsite';
        }
        return $title;
    }
}
add_filter('pre_get_document_title', 'fhor_sb_document_title', 30);

if (!function_exists('fhor_sb_robots')) {
    function fhor_sb_robots($robots) {
        if (fhor_sb_is_request() || fhor_sb_is_qualifiers_request()) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }
        return $robots;
    }
}
add_filter('wp_robots', 'fhor_sb_robots');

if (!function_exists('fhor_sb_menu_item')) {
    function fhor_sb_menu_item($items, $args) {
        if (is_admin() || !is_user_logged_in() || !fhor_sb_user_can_access()) {
            return $items;
        }
        if (strpos($items, '/system-builder') === false) {
            $active = fhor_sb_is_request() ? ' current-menu-item current_page_item' : '';
            $items .= '<li class="menu-item menu-item-type-custom menu-item-system-builder' . esc_attr($active) . '">'
                . '<a href="' . esc_url(fhor_sb_url()) . '">System Builder</a></li>';
        }
        if (strpos($items, '/my-qualifiers') === false) {
            $active = fhor_sb_is_qualifiers_request() ? ' current-menu-item current_page_item' : '';
            $items .= '<li class="menu-item menu-item-type-custom menu-item-my-qualifiers' . esc_attr($active) . '">'
                . '<a href="' . esc_url(fhor_sb_qualifiers_url()) . '">My Qualifiers</a></li>';
        }
        return $items;
    }
}
add_filter('wp_nav_menu_items', 'fhor_sb_menu_item', 22, 2);

<?php
/**
 * Fhorsite Premium Bet Tracker.
 *
 * URL: /bet-tracker/   Shortcode: [bet_tracker]
 * Paid members log singles and full-cover bets, with flat points or a
 * next-day rolling percentage stake, and a shadow line for the other method.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!empty($_SERVER['REQUEST_URI']) && preg_match('#/bet-tracker(?:/|$)#i', (string) $_SERVER['REQUEST_URI'])) {
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    if (!defined('DONOTCACHEDB')) {
        define('DONOTCACHEDB', true);
    }
}

require_once __DIR__ . '/bet-tracker-calc.php';

if (!function_exists('fhor_bt_url')) {
    function fhor_bt_url() {
        return home_url('/bet-tracker/');
    }
}

if (!function_exists('fhor_bt_is_request')) {
    function fhor_bt_is_request() {
        if (get_query_var('fhor_bet_tracker')) {
            return true;
        }
        return (bool) preg_match('#/bet-tracker(?:/|$)#i', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    }
}

if (!function_exists('fhor_bt_is_premium')) {
    function fhor_bt_is_premium($user_id = 0) {
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

if (!function_exists('fhor_bt_bets_table')) {
    function fhor_bt_bets_table() {
        global $wpdb;
        return $wpdb->prefix . 'user_bets';
    }
}

if (!function_exists('fhor_bt_legs_table')) {
    function fhor_bt_legs_table() {
        global $wpdb;
        return $wpdb->prefix . 'user_bet_legs';
    }
}

if (!function_exists('fhor_bt_tables_exist')) {
    function fhor_bt_tables_exist() {
        global $wpdb;
        $bets = fhor_bt_bets_table();
        $legs = fhor_bt_legs_table();
        $found_bets = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $bets));
        $found_legs = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legs));
        return $found_bets === $bets && $found_legs === $legs;
    }
}

if (!function_exists('fhor_bt_db_schema_version')) {
    function fhor_bt_db_schema_version() {
        return '2';
    }
}

if (!function_exists('fhor_bt_maybe_upgrade_schema')) {
    function fhor_bt_maybe_upgrade_schema() {
        global $wpdb;
        $bets = fhor_bt_bets_table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $bets)) !== $bets) {
            return;
        }
        $has_lines = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$bets}` LIKE %s", 'lines'));
        if ($has_lines) {
            $wpdb->query("ALTER TABLE `{$bets}` CHANGE `lines` `line_count` smallint(5) unsigned NOT NULL DEFAULT 1");
        }
    }
}

if (!function_exists('fhor_bt_create_tables')) {
    function fhor_bt_create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $bets = fhor_bt_bets_table();
        $legs = fhor_bt_legs_table();
        $wpdb->query("CREATE TABLE IF NOT EXISTS `{$bets}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `placed_at` datetime NOT NULL,
            `bet_type` varchar(32) NOT NULL DEFAULT '',
            `each_way` tinyint(1) NOT NULL DEFAULT 0,
            `ew_fraction` decimal(10,6) NOT NULL DEFAULT 0.250000,
            `stake_mode` varchar(16) NOT NULL DEFAULT 'auto',
            `manual_total` decimal(12,2) NOT NULL DEFAULT 0.00,
            `total_stake` decimal(12,2) NOT NULL DEFAULT 0.00,
            `unit_stake` decimal(12,2) NOT NULL DEFAULT 0.00,
            `returns_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
            `profit` decimal(12,2) NOT NULL DEFAULT 0.00,
            `result` varchar(16) NOT NULL DEFAULT 'pending',
            `line_count` smallint(5) unsigned NOT NULL DEFAULT 1,
            `system_name` varchar(190) NOT NULL DEFAULT '',
            `system_id` varchar(64) NOT NULL DEFAULT '',
            `course` varchar(190) NOT NULL DEFAULT '',
            `selection_label` varchar(255) NOT NULL DEFAULT '',
            `odds_display` varchar(190) NOT NULL DEFAULT '',
            `note` varchar(500) NOT NULL DEFAULT '',
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_placed` (`user_id`, `placed_at`)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS `{$legs}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `bet_id` bigint(20) unsigned NOT NULL,
            `user_id` bigint(20) unsigned NOT NULL,
            `leg_index` smallint(5) unsigned NOT NULL DEFAULT 0,
            `horse_name` varchar(190) NOT NULL DEFAULT '',
            `course` varchar(190) NOT NULL DEFAULT '',
            `odds_input` varchar(32) NOT NULL DEFAULT '',
            `odds_decimal` decimal(10,4) NOT NULL DEFAULT 0.0000,
            `result` varchar(16) NOT NULL DEFAULT 'pending',
            PRIMARY KEY (`id`),
            KEY `bet_id` (`bet_id`),
            KEY `user_id` (`user_id`)
        ) {$charset}");
    }
}

if (!function_exists('fhor_bt_install_tables')) {
    function fhor_bt_install_tables() {
        $expected = fhor_bt_db_schema_version();
        $current = get_option('fhor_bt_db_version');
        if ($current === $expected && fhor_bt_tables_exist()) {
            return;
        }
        if ($current === $expected && !fhor_bt_tables_exist()) {
            delete_option('fhor_bt_db_version');
            fhor_bt_debug_log('install repair', ['reason' => 'missing_tables']);
        }
        if ($current === '1' && fhor_bt_tables_exist()) {
            fhor_bt_maybe_upgrade_schema();
            update_option('fhor_bt_db_version', $expected);
            return;
        }
        fhor_bt_create_tables();
        fhor_bt_maybe_upgrade_schema();
        if (fhor_bt_tables_exist()) {
            update_option('fhor_bt_db_version', $expected);
        } else {
            delete_option('fhor_bt_db_version');
            global $wpdb;
            fhor_bt_debug_log('install failed', ['db_error' => $wpdb->last_error]);
        }
    }
}
add_action('init', 'fhor_bt_install_tables', 6);

if (!function_exists('fhor_bt_settings_key')) {
    function fhor_bt_settings_key() {
        return 'fhor_bet_tracker_settings';
    }
}

if (!function_exists('fhor_bt_get_settings')) {
    function fhor_bt_get_settings($user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        $stored = $user_id ? get_user_meta($user_id, fhor_bt_settings_key(), true) : [];
        return fhor_bt_normalize_settings(is_array($stored) ? $stored : []);
    }
}

if (!function_exists('fhor_bt_today')) {
    function fhor_bt_today() {
        return function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
    }
}

if (!function_exists('fhor_bt_load_bets')) {
    function fhor_bt_load_bets($user_id) {
        global $wpdb;
        fhor_bt_install_tables();
        $user_id = intval($user_id);
        $bets_table = fhor_bt_bets_table();
        $legs_table = fhor_bt_legs_table();
        $wpdb->suppress_errors(true);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $bets_table WHERE user_id = %d ORDER BY placed_at ASC, id ASC", $user_id));
        $leg_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $legs_table WHERE user_id = %d ORDER BY bet_id ASC, leg_index ASC", $user_id));
        if (($rows === null || $leg_rows === null) && $wpdb->last_error) {
            delete_option('fhor_bt_db_version');
            fhor_bt_install_tables();
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $bets_table WHERE user_id = %d ORDER BY placed_at ASC, id ASC", $user_id));
            $leg_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $legs_table WHERE user_id = %d ORDER BY bet_id ASC, leg_index ASC", $user_id));
        }
        $wpdb->suppress_errors(false);
        $by_bet = [];
        foreach ((array) $leg_rows as $leg) {
            $bid = (int) $leg->bet_id;
            if (!isset($by_bet[$bid])) {
                $by_bet[$bid] = [];
            }
            $by_bet[$bid][] = [
                'horse' => (string) $leg->horse_name,
                'course' => (string) $leg->course,
                'odds_input' => (string) $leg->odds_input,
                'odds' => (float) $leg->odds_decimal,
                'result' => (string) $leg->result,
            ];
        }
        $out = [];
        foreach ((array) $rows as $row) {
            $id = (int) $row->id;
            $out[] = [
                'id' => $id,
                'placed_at' => (string) $row->placed_at,
                'bet_type' => (string) $row->bet_type,
                'each_way' => (int) $row->each_way === 1,
                'ew_fraction' => (float) $row->ew_fraction,
                'stake_mode' => (string) $row->stake_mode === 'manual' ? 'manual' : 'auto',
                'manual_total' => (float) $row->manual_total,
                'system_name' => (string) $row->system_name,
                'system_id' => (string) $row->system_id,
                'course' => (string) $row->course,
                'selection_label' => (string) $row->selection_label,
                'odds_display' => (string) $row->odds_display,
                'note' => (string) $row->note,
                'legs' => isset($by_bet[$id]) ? $by_bet[$id] : [],
            ];
        }
        return $out;
    }
}

if (!function_exists('fhor_bt_annotate_bets')) {
    function fhor_bt_annotate_bets(array $bets) {
        $types = fhor_bt_bet_types();
        foreach ($bets as $i => $bet) {
            $label = isset($types[$bet['bet_type']]['label']) ? $types[$bet['bet_type']]['label'] : $bet['bet_type'];
            if (!empty($bet['each_way'])) {
                $label .= ' EW';
            }
            $bets[$i]['type_label'] = $label;
            $bets[$i]['ew_key'] = fhor_bt_ew_key(isset($bet['ew_fraction']) ? $bet['ew_fraction'] : 0.25);
            $bets[$i]['date'] = substr((string) $bet['placed_at'], 0, 10);
            foreach (['total_stake', 'unit_stake', 'returns_amount', 'profit', 'manual_total', 'shadow_total_stake', 'shadow_profit', 'shadow_returns'] as $key) {
                if (isset($bets[$i][$key])) {
                    $bets[$i][$key] = round((float) $bets[$i][$key], 2);
                }
            }
        }
        return $bets;
    }
}

if (!function_exists('fhor_bt_persist_projection')) {
    function fhor_bt_persist_projection($user_id, array $bets) {
        global $wpdb;
        $table = fhor_bt_bets_table();
        $now = current_time('mysql');
        foreach ($bets as $bet) {
            $wpdb->update(
                $table,
                [
                    'total_stake' => round((float) $bet['total_stake'], 2),
                    'unit_stake' => round((float) $bet['unit_stake'], 2),
                    'returns_amount' => round((float) $bet['returns_amount'], 2),
                    'profit' => round((float) $bet['profit'], 2),
                    'result' => $bet['result'],
                    'line_count' => (int) $bet['lines'],
                    'updated_at' => $now,
                ],
                [
                    'id' => (int) $bet['id'],
                    'user_id' => (int) $user_id,
                ],
                ['%f', '%f', '%f', '%f', '%s', '%d', '%s'],
                ['%d', '%d']
            );
        }
    }
}

if (!function_exists('fhor_bt_recalculate')) {
    function fhor_bt_recalculate($user_id) {
        $user_id = intval($user_id);
        $settings = fhor_bt_get_settings($user_id);
        $today = fhor_bt_today();
        $cmp = fhor_bt_compare(fhor_bt_load_bets($user_id), $settings, $today);
        fhor_bt_persist_projection($user_id, $cmp['bets']);
        $stake_label = $settings['mode'] === 'flat'
            ? fhor_bt_trim_num($cmp['flat_line'])
            : fhor_bt_trim_num($cmp['today_budget']);
        update_user_meta($user_id, 'fhor_bt_ledger', [
            'as_of' => $today,
            'bankroll' => $cmp['bankroll'],
            'today_opening' => $cmp['today_opening'],
            'today_stake' => $settings['mode'] === 'flat' ? $cmp['flat_line'] : $cmp['today_budget'],
            'mode' => $settings['mode'],
            'settled_at' => current_time('mysql'),
            'summary' => $stake_label,
        ]);
        $cmp['bets'] = fhor_bt_annotate_bets($cmp['bets']);
        try {
            $demo = fhor_bt_compare(fhor_bt_demo_bets(), $settings, '2026-09-17');
            $demo['bets'] = fhor_bt_annotate_bets($demo['bets']);
            $cmp['demo'] = $demo;
        } catch (Throwable $e) {
            $cmp['demo'] = null;
        }
        return $cmp;
    }
}

if (!function_exists('fhor_bt_parse_bet_input')) {
    function fhor_bt_parse_bet_input($raw) {
        if (!is_array($raw)) {
            return new WP_Error('invalid', 'Bet details were missing.');
        }
        $types = fhor_bt_bet_types();
        $type = sanitize_key(isset($raw['bet_type']) ? $raw['bet_type'] : '');
        if (!isset($types[$type])) {
            return new WP_Error('type', 'Choose a bet type.');
        }
        $placed = fhor_bt_parse_datetime(isset($raw['placed_at']) ? $raw['placed_at'] : '');
        if ($placed === '') {
            return new WP_Error('date', 'Enter a valid date and time. Past dates are allowed.');
        }
        $each_way = !empty($raw['each_way']);
        $fraction = fhor_bt_ew_fraction(isset($raw['ew_terms']) ? $raw['ew_terms'] : '1/4');
        $legs_in = (isset($raw['legs']) && is_array($raw['legs'])) ? array_values($raw['legs']) : [];
        $min = (int) $types[$type]['min'];
        $max = (int) $types[$type]['max'];
        $count = count($legs_in);
        if ($count < $min || $count > $max) {
            $need = $min === $max ? (string) $min : ($min . ' to ' . $max);
            return new WP_Error('legs', 'This bet needs ' . $need . ' selections.');
        }
        $legs = [];
        $horses = [];
        $courses = [];
        $odds_bits = [];
        foreach ($legs_in as $leg) {
            if (!is_array($leg)) {
                return new WP_Error('legs', 'Selection details were incomplete.');
            }
            $horse = substr(sanitize_text_field(isset($leg['horse']) ? $leg['horse'] : ''), 0, 190);
            $course = substr(sanitize_text_field(isset($leg['course']) ? $leg['course'] : ''), 0, 190);
            $odds_input = sanitize_text_field(isset($leg['odds']) ? $leg['odds'] : '');
            $odds = fhor_bt_parse_odds($odds_input);
            if ($horse === '') {
                return new WP_Error('horse', 'Each selection needs a horse name.');
            }
            if ($odds === null) {
                return new WP_Error('odds', 'Enter odds as a decimal (3.50) or a fraction (5/2).');
            }
            $legs[] = [
                'horse' => $horse,
                'course' => $course,
                'odds_input' => substr($odds_input, 0, 32),
                'odds' => $odds,
                'result' => fhor_bt_norm_result(isset($leg['result']) ? $leg['result'] : 'pending'),
            ];
            $horses[] = $horse;
            if ($course !== '') {
                $courses[] = $course;
            }
            $odds_bits[] = substr($odds_input, 0, 32);
        }
        $unique_courses = array_values(array_unique($courses));
        if (count($unique_courses) === 1) {
            $course_label = $unique_courses[0];
        } elseif (count($unique_courses) > 1) {
            $course_label = 'Multiple';
        } else {
            $course_label = '';
        }
        $stake_mode = (isset($raw['stake_mode']) && $raw['stake_mode'] === 'manual') ? 'manual' : 'auto';
        $manual = round((float) (isset($raw['manual_total']) ? $raw['manual_total'] : 0), 2);
        if ($stake_mode === 'manual' && ($manual <= 0 || $manual > 1000000)) {
            return new WP_Error('stake', 'Enter a stake greater than zero.');
        }
        $system = sanitize_text_field(isset($raw['system_name']) ? $raw['system_name'] : '');
        $system_id = sanitize_text_field(isset($raw['system_id']) ? $raw['system_id'] : '');
        $note = sanitize_textarea_field(isset($raw['note']) ? $raw['note'] : '');
        $selection = implode(' / ', $horses);
        if (strlen($selection) > 255) {
            $selection = substr($selection, 0, 252) . '...';
        }
        return [
            'placed_at' => $placed,
            'bet_type' => $type,
            'each_way' => $each_way ? 1 : 0,
            'ew_fraction' => $fraction,
            'stake_mode' => $stake_mode,
            'manual_total' => $stake_mode === 'manual' ? $manual : 0,
            'system_name' => substr($system, 0, 190),
            'system_id' => substr($system_id, 0, 64),
            'course' => substr($course_label, 0, 190),
            'selection_label' => $selection,
            'odds_display' => substr(implode(' · ', $odds_bits), 0, 190),
            'note' => substr($note, 0, 500),
            'legs' => $legs,
        ];
    }
}

if (!function_exists('fhor_bt_insert_legs')) {
    function fhor_bt_insert_legs($bet_id, $user_id, array $legs) {
        global $wpdb;
        $table = fhor_bt_legs_table();
        foreach ($legs as $index => $leg) {
            $ok = $wpdb->insert(
                $table,
                [
                    'bet_id' => (int) $bet_id,
                    'user_id' => (int) $user_id,
                    'leg_index' => (int) $index,
                    'horse_name' => $leg['horse'],
                    'course' => $leg['course'],
                    'odds_input' => $leg['odds_input'],
                    'odds_decimal' => $leg['odds'],
                    'result' => $leg['result'],
                ],
                ['%d', '%d', '%d', '%s', '%s', '%s', '%f', '%s']
            );
            if (!$ok) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('fhor_bt_debug_log')) {
    function fhor_bt_debug_log($event, array $context = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        $line = '[fhor-bt] ' . $event;
        if ($context) {
            $line .= ' ' . wp_json_encode($context);
        }
        error_log($line);
    }
}

if (!function_exists('fhor_bt_guard')) {
    function fhor_bt_guard($action = '') {
        if (!is_user_logged_in()) {
            fhor_bt_debug_log('guard denied', ['reason' => 'not_logged_in', 'action' => $action]);
            wp_send_json_error(['message' => 'Please log in.'], 401);
        }
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'fhor_bet_tracker')) {
            fhor_bt_debug_log('guard denied', [
                'reason' => 'bad_nonce',
                'action' => $action,
                'user_id' => get_current_user_id(),
                'nonce_len' => strlen($nonce),
            ]);
            wp_send_json_error(['message' => 'Your session needs a refresh.', 'code' => 'nonce'], 403);
        }
        if (!fhor_bt_is_premium()) {
            fhor_bt_debug_log('guard denied', ['reason' => 'not_premium', 'action' => $action, 'user_id' => get_current_user_id()]);
            wp_send_json_error(['message' => 'Bet Tracker is included with Fhorsite Premium.'], 403);
        }
    }
}

if (!function_exists('fhor_bt_ajax_nonce')) {
    function fhor_bt_ajax_nonce() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Please log in.'], 401);
        }
        nocache_headers();
        wp_send_json_success(['nonce' => wp_create_nonce('fhor_bet_tracker')]);
    }
}
add_action('wp_ajax_fhor_bt_nonce', 'fhor_bt_ajax_nonce');

if (!function_exists('fhor_bt_ajax_denied')) {
    function fhor_bt_ajax_denied() {
        wp_send_json_error(['message' => 'Please log in.'], 401);
    }
}

if (!function_exists('fhor_bt_send_book')) {
    function fhor_bt_send_book($user_id) {
        try {
            wp_send_json_success(fhor_bt_recalculate($user_id));
        } catch (Throwable $e) {
            fhor_bt_debug_log('send_book failed', [
                'user_id' => (int) $user_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            wp_send_json_error(['message' => 'The tracker could not load your book.'], 500);
        }
    }
}

if (!function_exists('fhor_bt_ajax_bootstrap')) {
    function fhor_bt_ajax_bootstrap() {
        fhor_bt_guard('fhor_bt_bootstrap');
        fhor_bt_send_book(get_current_user_id());
    }
}
add_action('wp_ajax_fhor_bt_bootstrap', 'fhor_bt_ajax_bootstrap');
add_action('wp_ajax_nopriv_fhor_bt_bootstrap', 'fhor_bt_ajax_denied');

if (!function_exists('fhor_bt_ajax_save_settings')) {
    function fhor_bt_ajax_save_settings() {
        fhor_bt_guard('fhor_bt_save_settings');
        $settings = fhor_bt_normalize_settings([
            'mode' => isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : 'percentage',
            'starting_bankroll' => isset($_POST['starting_bankroll']) ? wp_unslash($_POST['starting_bankroll']) : 100,
            'percentage' => isset($_POST['percentage']) ? wp_unslash($_POST['percentage']) : 5,
            'point_value' => isset($_POST['point_value']) ? wp_unslash($_POST['point_value']) : 1,
            'points_per_bet' => isset($_POST['points_per_bet']) ? wp_unslash($_POST['points_per_bet']) : 1,
        ]);
        update_user_meta(get_current_user_id(), fhor_bt_settings_key(), $settings);
        fhor_bt_send_book(get_current_user_id());
    }
}
add_action('wp_ajax_fhor_bt_save_settings', 'fhor_bt_ajax_save_settings');
add_action('wp_ajax_nopriv_fhor_bt_save_settings', 'fhor_bt_ajax_denied');

if (!function_exists('fhor_bt_ajax_quote')) {
    function fhor_bt_ajax_quote() {
        fhor_bt_guard('fhor_bt_quote');
        $user_id = get_current_user_id();
        $bets = fhor_bt_load_bets($user_id);
        $exclude = isset($_POST['exclude_id']) ? (int) $_POST['exclude_id'] : 0;
        if ($exclude > 0) {
            $bets = array_values(array_filter($bets, function ($bet) use ($exclude) {
                return (int) $bet['id'] !== $exclude;
            }));
        }
        $quote = fhor_bt_quote(
            $bets,
            fhor_bt_get_settings($user_id),
            isset($_POST['placed_at']) ? wp_unslash($_POST['placed_at']) : '',
            sanitize_key(isset($_POST['bet_type']) ? wp_unslash($_POST['bet_type']) : 'single'),
            !empty($_POST['each_way']),
            isset($_POST['leg_count']) ? (int) $_POST['leg_count'] : 1,
            fhor_bt_today()
        );
        wp_send_json_success($quote);
    }
}
add_action('wp_ajax_fhor_bt_quote', 'fhor_bt_ajax_quote');
add_action('wp_ajax_nopriv_fhor_bt_quote', 'fhor_bt_ajax_denied');

if (!function_exists('fhor_bt_ajax_save_bet')) {
    function fhor_bt_ajax_save_bet() {
        fhor_bt_guard('fhor_bt_save_bet');
        fhor_bt_install_tables();
        if (!fhor_bt_tables_exist()) {
            wp_send_json_error(['message' => 'Bet Tracker storage is not set up on this site yet. Try again in a moment or contact support.'], 500);
        }
        global $wpdb;
        $user_id = get_current_user_id();
        $bet_json = isset($_POST['bet']) ? wp_unslash($_POST['bet']) : '';
        $raw = $bet_json !== '' ? json_decode($bet_json, true) : null;
        if ($bet_json !== '' && $raw === null && json_last_error() !== JSON_ERROR_NONE) {
            fhor_bt_debug_log('save_bet bad json', [
                'user_id' => $user_id,
                'json_error' => json_last_error_msg(),
                'bet_len' => strlen($bet_json),
            ]);
        }
        fhor_bt_debug_log('save_bet start', [
            'user_id' => $user_id,
            'bet_id' => is_array($raw) && isset($raw['id']) ? (int) $raw['id'] : 0,
            'bet_type' => is_array($raw) && isset($raw['bet_type']) ? sanitize_key($raw['bet_type']) : '',
            'leg_count' => is_array($raw) && isset($raw['legs']) && is_array($raw['legs']) ? count($raw['legs']) : 0,
        ]);
        $parsed = fhor_bt_parse_bet_input($raw);
        if (is_wp_error($parsed)) {
            fhor_bt_debug_log('save_bet validation failed', [
                'user_id' => $user_id,
                'code' => $parsed->get_error_code(),
                'message' => $parsed->get_error_message(),
            ]);
            wp_send_json_error(['message' => $parsed->get_error_message()], 400);
        }
        $bet_id = isset($raw['id']) ? (int) $raw['id'] : 0;
        $table = fhor_bt_bets_table();
        $now = current_time('mysql');
        $row = [
            'placed_at' => $parsed['placed_at'],
            'bet_type' => $parsed['bet_type'],
            'each_way' => $parsed['each_way'],
            'ew_fraction' => $parsed['ew_fraction'],
            'stake_mode' => $parsed['stake_mode'],
            'manual_total' => $parsed['manual_total'],
            'system_name' => $parsed['system_name'],
            'system_id' => $parsed['system_id'],
            'course' => $parsed['course'],
            'selection_label' => $parsed['selection_label'],
            'odds_display' => $parsed['odds_display'],
            'note' => $parsed['note'],
            'updated_at' => $now,
        ];
        $formats = ['%s', '%s', '%d', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
        if ($bet_id > 0) {
            $owned = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id = %d AND user_id = %d", $bet_id, $user_id));
            if (!$owned) {
                wp_send_json_error(['message' => 'That bet was not found.'], 404);
            }
            $updated = $wpdb->update($table, $row, ['id' => $bet_id, 'user_id' => $user_id], $formats, ['%d', '%d']);
            if ($updated === false) {
                fhor_bt_debug_log('save_bet update failed', ['user_id' => $user_id, 'bet_id' => $bet_id, 'db_error' => $wpdb->last_error]);
                wp_send_json_error(['message' => 'Could not update that bet.'], 500);
            }
            $wpdb->delete(fhor_bt_legs_table(), ['bet_id' => $bet_id, 'user_id' => $user_id], ['%d', '%d']);
        } else {
            $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d", $user_id));
            if ($count >= 5000) {
                wp_send_json_error(['message' => 'The bet book is full (5,000 entries).'], 400);
            }
            $ok = $wpdb->insert(
                $table,
                [
                    'user_id' => $user_id,
                    'placed_at' => $parsed['placed_at'],
                    'bet_type' => $parsed['bet_type'],
                    'each_way' => $parsed['each_way'],
                    'ew_fraction' => $parsed['ew_fraction'],
                    'stake_mode' => $parsed['stake_mode'],
                    'manual_total' => $parsed['manual_total'],
                    'total_stake' => 0,
                    'unit_stake' => 0,
                    'returns_amount' => 0,
                    'profit' => 0,
                    'result' => 'pending',
                    'line_count' => 1,
                    'system_name' => $parsed['system_name'],
                    'system_id' => $parsed['system_id'],
                    'course' => $parsed['course'],
                    'selection_label' => $parsed['selection_label'],
                    'odds_display' => $parsed['odds_display'],
                    'note' => $parsed['note'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    '%d', '%s', '%s', '%d', '%f', '%s', '%f',
                    '%f', '%f', '%f', '%f', '%s', '%d',
                    '%s', '%s', '%s', '%s', '%s', '%s',
                    '%s', '%s',
                ]
            );
            if (!$ok) {
                fhor_bt_debug_log('save_bet insert failed', ['user_id' => $user_id, 'db_error' => $wpdb->last_error]);
                wp_send_json_error(['message' => 'Could not save that bet.'], 500);
            }
            $bet_id = (int) $wpdb->insert_id;
        }
        if (!fhor_bt_insert_legs($bet_id, $user_id, $parsed['legs'])) {
            fhor_bt_debug_log('save_bet legs failed', ['user_id' => $user_id, 'bet_id' => $bet_id, 'db_error' => $wpdb->last_error]);
            if (!isset($owned)) {
                $wpdb->delete(fhor_bt_legs_table(), ['bet_id' => $bet_id, 'user_id' => $user_id], ['%d', '%d']);
                $wpdb->delete($table, ['id' => $bet_id, 'user_id' => $user_id], ['%d', '%d']);
            }
            wp_send_json_error(['message' => 'A selection could not be stored.'], 500);
        }
        fhor_bt_send_book($user_id);
    }
}
add_action('wp_ajax_fhor_bt_save_bet', 'fhor_bt_ajax_save_bet');
add_action('wp_ajax_nopriv_fhor_bt_save_bet', 'fhor_bt_ajax_denied');

if (!function_exists('fhor_bt_ajax_delete_bet')) {
    function fhor_bt_ajax_delete_bet() {
        fhor_bt_guard('fhor_bt_delete_bet');
        global $wpdb;
        $user_id = get_current_user_id();
        $bet_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $table = fhor_bt_bets_table();
        $owned = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id = %d AND user_id = %d", $bet_id, $user_id));
        if (!$owned) {
            wp_send_json_error(['message' => 'That bet was not found.'], 404);
        }
        $wpdb->delete(fhor_bt_legs_table(), ['bet_id' => $bet_id, 'user_id' => $user_id], ['%d', '%d']);
        $wpdb->delete($table, ['id' => $bet_id, 'user_id' => $user_id], ['%d', '%d']);
        fhor_bt_send_book($user_id);
    }
}
add_action('wp_ajax_fhor_bt_delete_bet', 'fhor_bt_ajax_delete_bet');
add_action('wp_ajax_nopriv_fhor_bt_delete_bet', 'fhor_bt_ajax_denied');

if (!function_exists('fhor_bt_daily_settle')) {
    function fhor_bt_daily_settle() {
        global $wpdb;
        fhor_bt_install_tables();
        $table = fhor_bt_bets_table();
        $ids = $wpdb->get_col("SELECT DISTINCT user_id FROM $table");
        foreach ((array) $ids as $user_id) {
            fhor_bt_recalculate((int) $user_id);
        }
    }
}
add_action('fhor_bt_daily_settle', 'fhor_bt_daily_settle');

if (!function_exists('fhor_bt_schedule_cron')) {
    function fhor_bt_schedule_cron() {
        if (get_option('fhor_bt_cron_ver') !== '1') {
            wp_clear_scheduled_hook('fhor_bt_daily_settle');
            update_option('fhor_bt_cron_ver', '1');
        }
        if (!wp_next_scheduled('fhor_bt_daily_settle')) {
            $tz = function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC';
            $ts = strtotime('today 06:15:00 ' . $tz);
            if ($ts === false || $ts < time()) {
                $ts = strtotime('tomorrow 06:15:00 ' . $tz);
            }
            if ($ts) {
                wp_schedule_event($ts, 'daily', 'fhor_bt_daily_settle');
            }
        }
    }
}
add_action('init', 'fhor_bt_schedule_cron', 40);

if (!function_exists('fhor_bt_should_enqueue')) {
    function fhor_bt_should_enqueue() {
        if (fhor_bt_is_request()) {
            return true;
        }
        if (function_exists('fhor_sb_is_request') && fhor_sb_is_request()) {
            return true;
        }
        if (function_exists('fhor_sb_is_qualifiers_request') && fhor_sb_is_qualifiers_request()) {
            return true;
        }
        if (function_exists('bricks_request_uri_contains') && bricks_request_uri_contains(['/bet-tracker', '/my-qualifiers', '/system-builder'])) {
            return true;
        }
        if (function_exists('bricks_current_post_has_shortcode') && bricks_current_post_has_shortcode(['bet_tracker', 'system_builder', 'system_builder_qualifiers'])) {
            return true;
        }
        return false;
    }
}

if (!function_exists('fhor_bt_enqueue')) {
    function fhor_bt_enqueue() {
        if (!fhor_bt_should_enqueue()) {
            return;
        }
        $GLOBALS['fhor_bt_assets'] = true;
        $deps = [];
        if (fhor_bt_is_premium() && fhor_bt_is_request()) {
            wp_enqueue_script(
                'fhor-chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js',
                [],
                '4.4.6',
                true
            );
            $deps[] = 'fhor-chartjs';
        }
        $js = get_stylesheet_directory() . '/bet-tracker.js';
        if (!file_exists($js)) {
            return;
        }
        wp_enqueue_script('fhor-bet-tracker', get_stylesheet_directory_uri() . '/bet-tracker.js', $deps, filemtime($js), true);
        $prefill = null;
        if (fhor_bt_is_request() && !empty($_GET['horse'])) {
            $prefill = [
                'horse' => sanitize_text_field(wp_unslash($_GET['horse'])),
                'course' => isset($_GET['course']) ? sanitize_text_field(wp_unslash($_GET['course'])) : '',
                'date' => isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : '',
                'time' => isset($_GET['time']) ? sanitize_text_field(wp_unslash($_GET['time'])) : '',
                'system' => isset($_GET['system']) ? sanitize_text_field(wp_unslash($_GET['system'])) : '',
                'system_id' => isset($_GET['system_id']) ? sanitize_text_field(wp_unslash($_GET['system_id'])) : '',
                'odds' => isset($_GET['odds']) ? sanitize_text_field(wp_unslash($_GET['odds'])) : '',
            ];
        }
        wp_localize_script('fhor-bet-tracker', 'fhorBt', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fhor_bet_tracker'),
            'debug' => (defined('WP_DEBUG') && WP_DEBUG) ? '1' : '0',
            'premium' => fhor_bt_is_premium() ? '1' : '0',
            'url' => fhor_bt_url(),
            'signup' => function_exists('fhor_get_membership_signup_url') ? fhor_get_membership_signup_url() : home_url('/register/'),
            'now' => current_time('Y-m-d') . 'T' . current_time('H:i'),
            'prefill' => $prefill,
        ]);
    }
}
add_action('wp_enqueue_scripts', 'fhor_bt_enqueue', 29);

if (!function_exists('fhor_bt_styles')) {
    function fhor_bt_styles() {
        return '<style>
        .bt-page{box-sizing:border-box;max-width:1180px;margin:0 auto;color:#0f172a;padding:0 0 2.5rem}
        .bt-page,.bt-gate,.bt-modal{box-sizing:border-box}
        .bt-page *,.bt-page *::before,.bt-page *::after,.bt-gate *,.bt-gate *::before,.bt-gate *::after,.bt-modal *,.bt-modal *::before,.bt-modal *::after{box-sizing:border-box}
        .bt-hero{display:flex;flex-wrap:wrap;gap:.8rem 1rem;align-items:flex-end;justify-content:space-between;margin:0 0 1rem}
        .bt-title{margin:0 0 .3rem;font-size:clamp(1.55rem,2.6vw,2.05rem)}
        .bt-lead{margin:0;color:#475569;line-height:1.5;max-width:46rem}
        .bt-panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1rem 1.05rem;margin:0 0 1rem}
        .bt-panel h2{margin:0 0 .7rem;font-size:1rem}
        .bt-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.6rem;margin:0 0 1rem}
        .bt-stat{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:.7rem .75rem}
        .bt-stat span{display:block;font-size:.68rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
        .bt-stat b{display:block;margin-top:.2rem;font-size:1.05rem}
        .bt-stat em{display:block;margin-top:.15rem;font-style:normal;font-size:.75rem;color:#64748b}
        .bt-pos{color:#15803d}.bt-neg{color:#b91c1c}
        .bt-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem .75rem}
        .bt-field{display:flex;flex-direction:column;gap:.25rem;min-width:0}
        .bt-field.span2{grid-column:span 2}
        .bt-field label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
        .bt-field input,.bt-field select,.bt-field textarea{width:100%;padding:.45rem .55rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.88rem;background:#fff;color:#0f172a}
        .bt-actions{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
        .bt-btn{display:inline-flex;align-items:center;justify-content:center;padding:.5rem .85rem;border-radius:8px;border:1px solid #e2e8f0;background:#fff;font-weight:700;cursor:pointer;font-size:.86rem;color:#0f172a;text-decoration:none}
        .bt-btn-primary{background:#15803d;border-color:#15803d;color:#fff}
        .bt-btn:disabled{opacity:.55;cursor:wait}
        .bt-note{font-size:.78rem;color:#64748b;margin:.55rem 0 0;line-height:1.45}
        .bt-filters{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin:0 0 .8rem}
        .bt-tab{border:1px solid #e2e8f0;background:#fff;border-radius:999px;padding:.32rem .7rem;font-size:.8rem;font-weight:700;cursor:pointer;color:#0f172a}
        .bt-tab.is-on{background:#0f172a;border-color:#0f172a;color:#fff}
        .bt-chart-grid{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:1rem;align-items:stretch}
        .bt-chart-wrap{position:relative;height:300px}
        .bt-whatif{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:.9rem .95rem}
        .bt-whatif h3{margin:0 0 .4rem;font-size:.72rem;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
        .bt-whatif p{margin:0;line-height:1.45;font-size:.92rem}
        .bt-table-wrap{overflow-x:auto}
        .bt-table{width:100%;border-collapse:collapse;font-size:.82rem}
        .bt-table th{text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;padding:.4rem;border-bottom:1px solid #e2e8f0;white-space:nowrap}
        .bt-table td{padding:.5rem .4rem;border-bottom:1px solid #f1f5f9;vertical-align:top}
        .bt-badge{display:inline-block;border-radius:999px;padding:.1rem .45rem;font-size:.72rem;font-weight:700;background:#f1f5f9;color:#334155}
        .bt-badge.is-won{background:#dcfce7;color:#166534}
        .bt-badge.is-placed{background:#e0f2fe;color:#075985}
        .bt-badge.is-lost{background:#fee2e2;color:#991b1b}
        .bt-badge.is-void{background:#f1f5f9;color:#64748b}
        .bt-badge.is-pending{background:#fef3c7;color:#92400e}
        .bt-icon{border:0;background:transparent;color:#15803d;font-weight:700;cursor:pointer;padding:0 .25rem;font-size:.78rem}
        .bt-icon.is-danger{color:#b91c1c}
        .bt-empty{color:#64748b;font-size:.9rem;padding:.85rem .9rem;margin:0;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc}
        .bt-banner{margin:0 0 .8rem;padding:.7rem .8rem;border-radius:10px;background:#fef2f2;color:#991b1b;font-size:.86rem}
        .bt-sample{display:flex;flex-wrap:wrap;gap:.75rem 1rem;align-items:center;justify-content:space-between;margin:0 0 .9rem;padding:.9rem 1rem;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a}
        .bt-sample[hidden]{display:none}
        .bt-sample.is-mine{background:#f8fafc;border-color:#e2e8f0;color:#334155}
        .bt-sample strong{display:block;margin:0 0 .25rem;font-size:.98rem}
        .bt-sample p{margin:0;max-width:46rem;font-size:.88rem;line-height:1.5}
        .bt-gate{max-width:860px;margin:1.5rem auto;padding:0 0 2rem}
        .bt-preview{margin-top:1rem;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#fff}
        .bt-preview-bar{display:flex;justify-content:space-between;gap:.5rem;padding:.7rem .9rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.75rem;font-weight:700;color:#64748b}
        .bt-preview svg{display:block;width:100%;height:auto}
        .bt-modal{position:fixed;inset:0;z-index:100000;display:none;align-items:flex-start;justify-content:center;padding:4vh 1rem 2rem;background:rgba(15,23,42,.45)}
        .bt-modal.is-open{display:flex}
        .bt-dialog{width:min(760px,100%);max-height:calc(100vh - 6vh);overflow:auto;background:#fff;border-radius:16px;padding:1.1rem 1.15rem 1.2rem;box-shadow:0 20px 50px rgba(15,23,42,.2)}
        .bt-dialog h2{margin:0 0 .8rem;font-size:1.15rem}
        .bt-legs{display:flex;flex-direction:column;gap:.45rem;margin:.2rem 0 .7rem}
        .bt-leg{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr) minmax(0,.8fr) minmax(0,.9fr) auto;gap:.4rem}
        .bt-leg input,.bt-leg select{width:100%;min-width:0;padding:.45rem .55rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.88rem;background:#fff;color:#0f172a}
        .bt-check{display:flex;align-items:center;gap:.4rem;font-size:.86rem;font-weight:600}
        .fhor-bt-log{white-space:nowrap;font-size:.75rem;padding:.32rem .55rem}
        .bt-filters select{max-width:100%}
        .bt-chart-wrap{min-width:0}
        @media (max-width:900px){
          .bt-page{padding-left:.85rem;padding-right:.85rem}
          .bt-stats{grid-template-columns:1fr 1fr}
          .bt-grid{grid-template-columns:1fr 1fr}
          .bt-chart-grid{grid-template-columns:minmax(0,1fr)}
          .bt-leg{grid-template-columns:1fr 1fr}
          .bt-leg .bt-leg-horse,.bt-leg .bt-leg-course{grid-column:1 / -1}
          .bt-preview-bar{flex-direction:column}
        }
        @media (max-width:640px){
          .bt-hero{align-items:stretch}
          .bt-hero .bt-btn,.bt-sample .bt-btn,.bt-actions .bt-btn{width:100%}
          .bt-grid{grid-template-columns:1fr}
          .bt-field.span2{grid-column:auto}
          .bt-panel{padding:.85rem .8rem}
          .bt-chart-wrap{height:220px}
          .bt-filters{gap:.5rem}
          .bt-tab{padding:.45rem .75rem}
          .bt-filters select{flex:1 1 100%}
          .bt-field input,.bt-field select,.bt-field textarea,.bt-leg input,.bt-leg select{font-size:16px}
          .bt-page{overflow-x:hidden}
          .bt-modal{padding:0;align-items:stretch}
          .bt-dialog{width:100%;max-height:100vh;max-height:100dvh;border-radius:0;padding:1rem .9rem calc(1.1rem + env(safe-area-inset-bottom))}
          .bt-table td[data-label=""]::before{content:none}
          .bt-table thead{display:none}
          .bt-table,.bt-table tbody,.bt-table tr,.bt-table td{display:block;width:100%}
          .bt-table tr{margin:0 0 .7rem;padding:.45rem .7rem .55rem;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
          .bt-table td{display:flex;justify-content:space-between;align-items:baseline;gap:.75rem;padding:.32rem 0;border-bottom:0}
          .bt-table td::before{content:attr(data-label);flex:0 0 auto;font-size:.68rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
          .bt-table td > span{min-width:0;text-align:right}
          .bt-table td.bt-td-actions{justify-content:flex-end}
          .bt-table td.bt-td-actions::before{margin-right:auto}
        }
        </style>';
    }
}

if (!function_exists('fhor_bt_print_styles')) {
    function fhor_bt_print_styles() {
        if (empty($GLOBALS['fhor_bt_assets'])) {
            return;
        }
        echo fhor_bt_styles();
    }
}
add_action('wp_head', 'fhor_bt_print_styles', 30);

if (!function_exists('fhor_bt_modal_html')) {
    function fhor_bt_modal_html() {
        $types = fhor_bt_bet_types();
        ob_start();
        ?>
        <div class="bt-modal" id="fhor-bt-modal" hidden>
            <div class="bt-dialog" role="dialog" aria-modal="true" aria-labelledby="bt-modal-title">
                <h2 id="bt-modal-title">Log bet</h2>
                <p class="bt-banner" id="bt-modal-error" hidden></p>
                <form id="bt-form">
                    <input type="hidden" id="bt-id" value="">
                    <input type="hidden" id="bt-system-id" value="">
                    <div class="bt-grid">
                        <div class="bt-field"><label for="bt-when">Date &amp; time</label><input id="bt-when" type="datetime-local" required></div>
                        <div class="bt-field"><label for="bt-type">Bet type</label>
                            <select id="bt-type">
                                <?php foreach ($types as $key => $meta): ?>
                                    <option value="<?php echo esc_attr($key); ?>" data-min="<?php echo (int) $meta['min']; ?>" data-max="<?php echo (int) $meta['max']; ?>"><?php echo esc_html($meta['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="bt-field"><label for="bt-system">System</label><input id="bt-system" type="text" maxlength="190" placeholder="Optional tag"></div>
                        <div class="bt-field" id="bt-ew-wrap"><label for="bt-ew-terms">Place terms</label>
                            <select id="bt-ew-terms">
                                <option value="1/4">1/4 odds</option>
                                <option value="1/5">1/5 odds</option>
                                <option value="1/3">1/3 odds</option>
                            </select>
                        </div>
                    </div>
                    <p class="bt-check" style="margin:.7rem 0 .4rem"><label><input type="checkbox" id="bt-ew"> Each-way (win + place)</label></p>
                    <div id="bt-legs" class="bt-legs"></div>
                    <p class="bt-actions" style="margin:0 0 .7rem"><button type="button" class="bt-btn" id="bt-add-leg">Add selection</button></p>
                    <div class="bt-grid">
                        <div class="bt-field"><label for="bt-stake">Total stake (£)</label><input id="bt-stake" type="number" min="0.01" step="0.01" inputmode="decimal"></div>
                        <div class="bt-field" style="justify-content:flex-end"><label class="bt-check"><input type="checkbox" id="bt-stake-auto" checked> Use recommended stake</label></div>
                        <div class="bt-field span2"><label for="bt-note">Note</label><input id="bt-note" type="text" maxlength="500" placeholder="Optional"></div>
                    </div>
                    <p class="bt-note" id="bt-stake-hint"></p>
                    <p class="bt-note">A multiple stays pending until every selection has a result. Placed means the horse was placed: an each-way place part wins, a win-only bet loses. Changing a past date rebuilds later percentage stakes.</p>
                    <div class="bt-actions" style="margin-top:.85rem">
                        <button type="submit" class="bt-btn bt-btn-primary" id="bt-save">Save bet</button>
                        <button type="button" class="bt-btn" id="bt-cancel">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('fhor_bt_print_modal')) {
    function fhor_bt_print_modal() {
        if (empty($GLOBALS['fhor_bt_assets'])) {
            return;
        }
        echo fhor_bt_modal_html();
    }
}
add_action('wp_footer', 'fhor_bt_print_modal', 5);

if (!function_exists('fhor_bt_gate_html')) {
    function fhor_bt_gate_html() {
        $signup = function_exists('fhor_get_membership_signup_url') ? fhor_get_membership_signup_url() : home_url('/register/');
        $login = wp_login_url(fhor_bt_url());
        ob_start();
        ?>
        <div class="bt-gate">
            <header class="bt-hero">
                <div>
                    <h1 class="bt-title">Bet Tracker</h1>
                    <p class="bt-lead">A Premium book for your wagers. Log singles through to Heinz and Lucky 63, stake in flat points or a rolling percentage, and see what the other method would have returned.</p>
                </div>
            </header>
            <div class="bt-panel">
                <h2>Premium members</h2>
                <p class="bt-lead">The tracker, the performance chart, and one-click logging from My Daily Qualifiers are included with a paid membership.</p>
                <div class="bt-actions" style="margin-top:.9rem">
                    <?php if (!is_user_logged_in()): ?>
                        <a class="bt-btn bt-btn-primary" href="<?php echo esc_url($login); ?>">Log in</a>
                    <?php endif; ?>
                    <a class="bt-btn<?php echo is_user_logged_in() ? ' bt-btn-primary' : ''; ?>" href="<?php echo esc_url($signup); ?>">Upgrade</a>
                </div>
            </div>
            <div class="bt-preview" aria-hidden="true">
                <div class="bt-preview-bar"><span>Preview · cumulative profit</span><span>Your staking · Flat shadow</span></div>
                <svg viewBox="0 0 640 220" role="img">
                    <rect width="640" height="220" fill="#ffffff"></rect>
                    <path d="M40 170 C 120 160, 160 150, 220 120 S 320 90, 400 100 S 500 60, 600 48" fill="none" stroke="#2563eb" stroke-width="3"></path>
                    <path d="M40 170 C 120 166, 180 150, 250 140 S 360 130, 450 120 S 520 110, 600 104" fill="none" stroke="#94a3b8" stroke-width="3" stroke-dasharray="6 5"></path>
                    <text x="40" y="196" fill="#94a3b8" font-size="12" font-family="sans-serif">Last week · month · year · all-time</text>
                </svg>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('fhor_bt_app_html')) {
    function fhor_bt_app_html() {
        $settings = fhor_bt_get_settings();
        ob_start();
        ?>
        <div class="bt-page" id="fhor-bet-tracker">
            <header class="bt-hero">
                <div>
                    <h1 class="bt-title">Bet Tracker</h1>
                    <p class="bt-lead">Log a wager, including one from an earlier date. Percentage stakes lock each morning from the previous close, and a backdated bet rebuilds every later stake.</p>
                </div>
                <button type="button" class="bt-btn bt-btn-primary" id="bt-add">Add bet</button>
            </header>
            <p class="bt-banner" id="bt-banner" hidden></p>
            <div class="bt-sample" id="bt-demo" hidden>
                <div>
                    <strong id="bt-demo-title">This is a sample book</strong>
                    <p id="bt-demo-copy">The chart and history below are an example. Add a bet and the sample is replaced by your own book.</p>
                </div>
                <button type="button" class="bt-btn" id="bt-demo-toggle">Hide sample</button>
            </div>
            <div class="bt-stats" id="bt-stats">
                <div class="bt-stat"><span>Bankroll</span><b id="bt-bankroll-stat">—</b><em id="bt-bankroll-sub">Starting balance</em></div>
                <div class="bt-stat"><span>Today’s stake</span><b id="bt-today-stat">—</b><em id="bt-today-sub">Locked for today</em></div>
                <div class="bt-stat"><span>Profit</span><b id="bt-view-profit">—</b><em>In this view</em></div>
                <div class="bt-stat"><span>Staked</span><b id="bt-view-staked">—</b><em>Settled bets</em></div>
                <div class="bt-stat"><span>Yield</span><b id="bt-view-yield">—</b><em>Profit / stakes</em></div>
                <div class="bt-stat"><span>Strike rate</span><b id="bt-view-strike">—</b><em id="bt-view-count">Won or placed</em></div>
            </div>
            <section class="bt-panel">
                <div class="bt-filters" id="bt-ranges">
                    <button type="button" class="bt-tab" data-range="7">Last Week</button>
                    <button type="button" class="bt-tab" data-range="30">Last Month</button>
                    <button type="button" class="bt-tab" data-range="365">Last Year</button>
                    <button type="button" class="bt-tab is-on" data-range="all">All-Time</button>
                    <button type="button" class="bt-btn" id="bt-demo-peek" hidden>Sample book</button>
                    <select id="bt-system-filter" aria-label="Filter by system"><option value="">All systems</option></select>
                </div>
                <div class="bt-chart-grid">
                    <div class="bt-chart-wrap"><canvas id="bt-chart"></canvas></div>
                    <aside class="bt-whatif" id="bt-whatif"><h3>What-if</h3><p>Loading your book…</p></aside>
                </div>
            </section>
            <section class="bt-panel">
                <h2>Staking setup</h2>
                <form id="bt-settings-form">
                    <div class="bt-grid">
                        <div class="bt-field"><label for="bt-mode">Method</label>
                            <select id="bt-mode" name="mode">
                                <option value="percentage" <?php selected($settings['mode'], 'percentage'); ?>>Dynamic percentage</option>
                                <option value="flat" <?php selected($settings['mode'], 'flat'); ?>>Flat / points</option>
                            </select>
                        </div>
                        <div class="bt-field"><label for="bt-bankroll">Starting bankroll (£)</label><input id="bt-bankroll" name="starting_bankroll" type="number" min="0" step="0.01" value="<?php echo esc_attr($settings['starting_bankroll']); ?>"></div>
                        <div class="bt-field"><label for="bt-percentage">Percentage</label><input id="bt-percentage" name="percentage" type="number" min="0.1" max="100" step="0.1" value="<?php echo esc_attr($settings['percentage']); ?>"></div>
                        <div class="bt-field"><label for="bt-point-value">1 point equals (£)</label><input id="bt-point-value" name="point_value" type="number" min="0.01" step="0.01" value="<?php echo esc_attr($settings['point_value']); ?>"></div>
                        <div class="bt-field"><label for="bt-points">Points per line</label><input id="bt-points" name="points_per_bet" type="number" min="0.01" step="0.01" value="<?php echo esc_attr($settings['points_per_bet']); ?>"></div>
                    </div>
                    <div class="bt-actions" style="margin-top:.75rem">
                        <button type="submit" class="bt-btn bt-btn-primary" id="bt-save-settings">Save staking</button>
                    </div>
                    <p class="bt-note" id="bt-settings-note">Dynamic mode risks the same total stake on every bet that day: morning bankroll × your percentage. Flat mode prices each line at your point size, so each-way and full-covers cost more. Saving rebuilds automatic stakes. A stake you typed yourself stays as you entered it.</p>
                </form>
            </section>
            <section class="bt-panel">
                <h2 id="bt-history-title">History</h2>
                <p class="bt-empty" id="bt-empty">Loading bets…</p>
                <div class="bt-table-wrap" id="bt-table-wrap" hidden>
                    <table class="bt-table">
                        <thead>
                            <tr>
                                <th>Date</th><th>Course</th><th>Selection</th><th>Type</th><th>Odds</th><th>Stake</th><th>Result</th><th>P/L</th><th>System</th><th id="bt-extra-head"></th>
                            </tr>
                        </thead>
                        <tbody id="bt-table-body"></tbody>
                    </table>
                </div>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('fhor_bt_shortcode')) {
    function fhor_bt_shortcode() {
        if (!fhor_bt_is_premium()) {
            return fhor_bt_gate_html();
        }
        return fhor_bt_app_html();
    }
}
add_shortcode('bet_tracker', 'fhor_bt_shortcode');

if (!function_exists('fhor_bt_log_button_html')) {
    function fhor_bt_log_button_html($args) {
        $args = is_array($args) ? $args : [];
        return '<button type="button" class="sb-btn fhor-bt-log"'
            . ' data-horse="' . esc_attr(isset($args['horse']) ? $args['horse'] : '') . '"'
            . ' data-course="' . esc_attr(isset($args['course']) ? $args['course'] : '') . '"'
            . ' data-time="' . esc_attr(isset($args['time']) ? $args['time'] : '') . '"'
            . ' data-date="' . esc_attr(isset($args['date']) ? $args['date'] : '') . '"'
            . ' data-odds="' . esc_attr(isset($args['odds']) ? $args['odds'] : '') . '"'
            . ' data-system="' . esc_attr(isset($args['system']) ? $args['system'] : '') . '"'
            . ' data-system-id="' . esc_attr(isset($args['system_id']) ? $args['system_id'] : '') . '"'
            . '>⚡ Log Bet</button>';
    }
}

if (!function_exists('fhor_bt_add_rewrite')) {
    function fhor_bt_add_rewrite() {
        add_rewrite_tag('%fhor_bet_tracker%', '([0-9]+)');
        add_rewrite_rule('^bet-tracker/?$', 'index.php?fhor_bet_tracker=1', 'top');
    }
}
add_action('init', 'fhor_bt_add_rewrite', 20);

if (!function_exists('fhor_bt_query_vars')) {
    function fhor_bt_query_vars($vars) {
        $vars[] = 'fhor_bet_tracker';
        return $vars;
    }
}
add_filter('query_vars', 'fhor_bt_query_vars');

if (!function_exists('fhor_bt_template_redirect')) {
    function fhor_bt_template_redirect() {
        if (is_admin() || !fhor_bt_is_request()) {
            return;
        }
        status_header(200);
        nocache_headers();
        get_header();
        echo '<main id="brx-content" class="bt-page-shell"><div style="padding:0 4px;">';
        echo do_shortcode('[bet_tracker]');
        echo '</div></main>';
        get_footer();
        exit;
    }
}
add_action('template_redirect', 'fhor_bt_template_redirect', 2);

if (!function_exists('fhor_bt_flush_rewrites')) {
    function fhor_bt_flush_rewrites() {
        if (get_option('fhor_bt_rewrite_flushed') !== '1') {
            flush_rewrite_rules(false);
            update_option('fhor_bt_rewrite_flushed', '1');
        }
    }
}
add_action('init', 'fhor_bt_flush_rewrites', 999);

if (!function_exists('fhor_bt_document_title')) {
    function fhor_bt_document_title($title) {
        if (fhor_bt_is_request()) {
            return 'Bet Tracker | Fhorsite';
        }
        return $title;
    }
}
add_filter('pre_get_document_title', 'fhor_bt_document_title', 30);

if (!function_exists('fhor_bt_robots')) {
    function fhor_bt_robots($robots) {
        if (fhor_bt_is_request()) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }
        return $robots;
    }
}
add_filter('wp_robots', 'fhor_bt_robots');

if (!function_exists('fhor_bt_menu_item')) {
    function fhor_bt_menu_item($items, $args) {
        if (is_admin() || !is_user_logged_in()) {
            return $items;
        }
        if (strpos($items, '/bet-tracker') !== false) {
            return $items;
        }
        $active = fhor_bt_is_request() ? ' current-menu-item current_page_item' : '';
        $items .= '<li class="menu-item menu-item-type-custom menu-item-bet-tracker' . esc_attr($active) . '">'
            . '<a href="' . esc_url(fhor_bt_url()) . '">Bet Tracker</a></li>';
        return $items;
    }
}
add_filter('wp_nav_menu_items', 'fhor_bt_menu_item', 23, 2);

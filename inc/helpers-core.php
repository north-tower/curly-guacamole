<?php
/**
 * Core helper utilities for Bricks child functionality.
 */

if (!function_exists('convert_date_format')) {
    function convert_date_format($date, $target_format = 'd-m-Y') {
        if (empty($date)) {
            return date($target_format);
        }

        if ($target_format === 'd-m-Y' && preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
            return $date;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return date($target_format, strtotime($date));
        }

        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date($target_format, $timestamp);
        }

        return date($target_format);
    }
}

if (!function_exists('bricks_debug_enabled')) {
    function bricks_debug_enabled() {
        return defined('BRICKS_CHILD_DEBUG') && BRICKS_CHILD_DEBUG;
    }
}

if (!function_exists('bricks_debug_log')) {
    function bricks_debug_log($message) {
        if (bricks_debug_enabled()) {
            error_log($message);
        }
    }
}

if (!function_exists('bricks_id_codec_params')) {
    function bricks_id_codec_params($entity_type) {
        $maps = [
            'race' => ['prefix' => 'r', 'multiplier' => 37, 'offset' => 9011],
            'runner' => ['prefix' => 'h', 'multiplier' => 43, 'offset' => 12013],
            'race_comment' => ['prefix' => 'c', 'multiplier' => 41, 'offset' => 10009],
        ];
        return $maps[$entity_type] ?? null;
    }
}

if (!function_exists('bricks_encode_entity_id')) {
    function bricks_encode_entity_id($id, $entity_type) {
        $params = bricks_id_codec_params($entity_type);
        $id = intval($id);
        if (!$params || $id <= 0) {
            return '';
        }

        $encoded_num = ($id * $params['multiplier']) + $params['offset'];
        $base36 = strtolower(base_convert((string) $encoded_num, 10, 36));
        return $params['prefix'] . '_' . $base36;
    }
}

if (!function_exists('bricks_decode_entity_id')) {
    function bricks_decode_entity_id($raw_value, $entity_type) {
        $params = bricks_id_codec_params($entity_type);
        if (!$params || $raw_value === null || $raw_value === '') {
            return 0;
        }

        if (is_numeric($raw_value)) {
            return intval($raw_value);
        }

        $value = strtolower(trim((string) $raw_value));
        $expected_prefix = $params['prefix'] . '_';
        if (strpos($value, $expected_prefix) !== 0) {
            return 0;
        }

        $payload = substr($value, strlen($expected_prefix));
        if ($payload === '' || !preg_match('/^[a-z0-9]+$/', $payload)) {
            return 0;
        }

        $decoded = intval(base_convert($payload, 36, 10));
        $adjusted = $decoded - $params['offset'];
        if ($adjusted <= 0 || ($adjusted % $params['multiplier']) !== 0) {
            return 0;
        }

        return intval($adjusted / $params['multiplier']);
    }
}

if (!function_exists('bricks_race_url')) {
    function bricks_race_url($race_id) {
        $token = bricks_encode_entity_id($race_id, 'race');
        if ($token !== '') {
            return home_url('/race/' . $token . '/');
        }
        return home_url('/race/' . intval($race_id) . '/');
    }
}

if (!function_exists('bricks_user_can_access_points_backtest')) {
    function bricks_user_can_access_points_backtest() {
        return is_user_logged_in() && current_user_can('manage_options');
    }
}

if (!function_exists('bricks_horse_history_url')) {
    function bricks_horse_history_url($runner_id) {
        $token = bricks_encode_entity_id($runner_id, 'runner');
        if ($token !== '') {
            return home_url('/horse-history/' . $token . '/');
        }
        return home_url('/horse-history/' . intval($runner_id) . '/');
    }
}

if (!function_exists('bricks_race_comment_url')) {
    function bricks_race_comment_url($race_id) {
        $token = bricks_encode_entity_id($race_id, 'race_comment');
        if ($token !== '') {
            return home_url('/race-comments/' . $token . '/');
        }
        return home_url('/race-comments/' . intval($race_id) . '/');
    }
}

if (!function_exists('bricks_cache_namespace_version')) {
    function bricks_cache_namespace_version($namespace) {
        $namespace = sanitize_key((string) $namespace);
        if ($namespace === '') {
            return 1;
        }

        $option_key = 'bricks_cache_ver_' . $namespace;
        $version = intval(get_option($option_key, 1));
        return $version > 0 ? $version : 1;
    }
}

if (!function_exists('bricks_bump_cache_namespace_version')) {
    function bricks_bump_cache_namespace_version($namespace) {
        $namespace = sanitize_key((string) $namespace);
        if ($namespace === '') {
            return 1;
        }

        $option_key = 'bricks_cache_ver_' . $namespace;
        $next_version = bricks_cache_namespace_version($namespace) + 1;
        update_option($option_key, $next_version, false);
        return $next_version;
    }
}

if (!function_exists('bricks_cache_key')) {
    function bricks_cache_key($namespace, array $parts = []) {
        $namespace = sanitize_key((string) $namespace);
        if ($namespace === '') {
            $namespace = 'default';
        }

        $version = bricks_cache_namespace_version($namespace);
        $payload = implode('|', array_map('strval', $parts));
        return $namespace . '_v' . $version . '_' . md5($payload);
    }
}

if (!function_exists('bricks_flush_filter_option_caches')) {
    function bricks_flush_filter_option_caches() {
        bricks_bump_cache_namespace_version('race_filters');
        bricks_bump_cache_namespace_version('speed_filters');
        bricks_bump_cache_namespace_version('horse_filters');
        bricks_bump_cache_namespace_version('sire_filters');
        bricks_bump_cache_namespace_version('yesterday_winners');
    }
}

if (!function_exists('bricks_get_filter_cache_versions')) {
    function bricks_get_filter_cache_versions() {
        return [
            'race_filters' => bricks_cache_namespace_version('race_filters'),
            'speed_filters' => bricks_cache_namespace_version('speed_filters'),
            'horse_filters' => bricks_cache_namespace_version('horse_filters'),
            'sire_filters' => bricks_cache_namespace_version('sire_filters'),
            'yesterday_winners' => bricks_cache_namespace_version('yesterday_winners'),
        ];
    }
}

if (!function_exists('bricks_ajax_flush_filter_caches')) {
    function bricks_ajax_flush_filter_caches() {
        check_ajax_referer('bricks_flush_filter_caches', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
            return;
        }

        bricks_flush_filter_option_caches();

        wp_send_json_success([
            'message' => 'Filter caches flushed',
            'versions' => bricks_get_filter_cache_versions(),
            'flushed_at_gmt' => gmdate('c'),
        ]);
    }
}
add_action('wp_ajax_bricks_flush_filter_caches', 'bricks_ajax_flush_filter_caches');

if (!function_exists('bricks_next_daily_8am_gmt_timestamp')) {
    function bricks_next_daily_8am_gmt_timestamp() {
        $now = time();
        $next = strtotime(gmdate('Y-m-d') . ' 08:00:00 UTC');
        if ($next <= $now) {
            $next = strtotime('+1 day', $next);
        }
        return $next;
    }
}

if (!function_exists('bricks_schedule_daily_filter_cache_flush')) {
    function bricks_schedule_daily_filter_cache_flush() {
        if (wp_next_scheduled('bricks_daily_filter_cache_flush')) {
            return;
        }

        wp_schedule_event(
            bricks_next_daily_8am_gmt_timestamp(),
            'daily',
            'bricks_daily_filter_cache_flush'
        );
    }
}
add_action('init', 'bricks_schedule_daily_filter_cache_flush', 20);

if (!function_exists('bricks_run_daily_filter_cache_flush')) {
    function bricks_run_daily_filter_cache_flush() {
        bricks_flush_filter_option_caches();
    }
}
add_action('bricks_daily_filter_cache_flush', 'bricks_run_daily_filter_cache_flush');

if (!function_exists('fhor_is_race_wednesday_open_day')) {
    /**
     * Wednesdays (UK time): logged-in users get full race detail access without paid membership.
     */
    function fhor_is_race_wednesday_open_day() {
        try {
            $race_tz = new DateTimeZone('Europe/London');
        } catch (Exception $e) {
            $race_tz = wp_timezone();
        }

        return (new DateTimeImmutable('now', $race_tz))->format('N') === '3';
    }
}

if (!function_exists('fhor_brm_user_has_active_level')) {
    /**
     * Check Bricks Members level assignment via plugin APIs and brm_user_level_assignments table.
     */
    function fhor_brm_user_has_active_level($user_id, $level_id) {
        global $wpdb;

        $user_id = intval($user_id);
        $level_id = intval($level_id);
        if ($user_id <= 0 || $level_id <= 0) {
            return false;
        }

        $api_result = apply_filters('fhor_brm_user_has_active_level', null, $user_id, $level_id);
        if (is_bool($api_result)) {
            return $api_result;
        }

        if (function_exists('brm_user_has_level') && brm_user_has_level($user_id, $level_id)) {
            return true;
        }
        if (function_exists('brm_has_user_level') && brm_has_user_level($user_id, $level_id)) {
            return true;
        }
        if (function_exists('brm_user_has_levels')) {
            $has = brm_user_has_levels($user_id, [$level_id]);
            if ($has) {
                return true;
            }
        }
        if (class_exists('BRM_User_Levels') && method_exists('BRM_User_Levels', 'user_has_level')) {
            if (BRM_User_Levels::user_has_level($user_id, $level_id)) {
                return true;
            }
        }

        $table = $wpdb->prefix . 'brm_user_level_assignments';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return false;
        }

        $assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `$table` WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (empty($assignments)) {
            return false;
        }

        foreach ($assignments as $row) {
            if (!is_array($row)) {
                continue;
            }

            $row_level_id = 0;
            if (isset($row['level_id']) && is_numeric($row['level_id'])) {
                $row_level_id = intval($row['level_id']);
            } elseif (isset($row['user_level_id']) && is_numeric($row['user_level_id'])) {
                $row_level_id = intval($row['user_level_id']);
            }

            if ($row_level_id !== $level_id) {
                continue;
            }

            if (array_key_exists('removed_at', $row)) {
                $removed_at = trim((string) $row['removed_at']);
                if ($removed_at !== '' && $removed_at !== '0000-00-00 00:00:00') {
                    continue;
                }
            }
            if (array_key_exists('revoked_at', $row)) {
                $revoked_at = trim((string) $row['revoked_at']);
                if ($revoked_at !== '' && $revoked_at !== '0000-00-00 00:00:00') {
                    continue;
                }
            }
            if (array_key_exists('is_active', $row) && (string) $row['is_active'] === '0') {
                continue;
            }
            if (array_key_exists('status', $row)) {
                $status = strtolower(trim((string) $row['status']));
                if (in_array($status, ['removed', 'revoked', 'inactive', 'expired', 'cancelled', 'canceled'], true)) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }
}

if (!function_exists('fhor_user_is_paid_member')) {
    /**
     * True when the user has the Fhorsite Member Bricks Members level (ID 5).
     */
    function fhor_user_is_paid_member($user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }

        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $override = apply_filters('fhor_bricks_members_paid_access', null, $user_id);
        if (is_bool($override)) {
            return $override;
        }

        $allowed_level_ids = [5];
        if (defined('FHOR_PAID_MEMBER_LEVEL_IDS')) {
            $allowed_level_ids = array_map('intval', (array) FHOR_PAID_MEMBER_LEVEL_IDS);
        } else {
            $option_ids = get_option('fhor_paid_member_level_ids', $allowed_level_ids);
            $allowed_level_ids = array_map('intval', (array) $option_ids);
        }
        $allowed_level_ids = array_values(array_filter($allowed_level_ids, function ($v) {
            return $v > 0;
        }));

        foreach ($allowed_level_ids as $level_id) {
            if (fhor_brm_user_has_active_level($user_id, $level_id)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('fhor_can_view_race_detail_page')) {
    /**
     * Full race detail page: must be logged in; Fhorsite Member on Mon–Tue/Thu–Sun;
     * any logged-in account on Wednesdays (UK time).
     */
    function fhor_can_view_race_detail_page($user_id = 0) {
        if (function_exists('bricks_seo_is_search_crawler') && bricks_seo_is_search_crawler()) {
            return true;
        }

        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }

        if (fhor_is_race_wednesday_open_day()) {
            return true;
        }

        return fhor_user_is_paid_member($user_id);
    }
}

if (!function_exists('fhor_can_view_race_premium_content')) {
    /**
     * Premium race content uses the same rules as the race detail page.
     */
    function fhor_can_view_race_premium_content($user_id = 0) {
        return fhor_can_view_race_detail_page($user_id);
    }
}

if (!function_exists('fhor_race_detail_access_gate_html')) {
    function fhor_race_detail_access_gate_html($race_id, $race = null) {
        $race_id = intval($race_id);
        $race_url = function_exists('bricks_race_url') && $race_id > 0
            ? bricks_race_url($race_id)
            : home_url(isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/');

        $title = ($race && !empty($race->race_title)) ? (string) $race->race_title : 'Race ratings';
        $course = ($race && !empty($race->course)) ? (string) $race->course : '';
        $signup_url = function_exists('fhor_get_membership_signup_url') ? fhor_get_membership_signup_url() : '';
        $is_wednesday = fhor_is_race_wednesday_open_day();

        ob_start();
        ?>
        <div style="max-width:640px;margin:48px auto 32px;padding:0 20px;font-family:inherit;">
            <div style="text-align:center;padding:32px 24px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,0.06);">
                <div style="font-size:40px;margin-bottom:12px;">🔒</div>
                <h1 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#111827;">Members-only race page</h1>
                <?php if ($course !== ''): ?>
                <p style="margin:0 0 6px;font-size:15px;color:#374151;font-weight:600;"><?php echo esc_html($title); ?></p>
                <p style="margin:0 0 16px;font-size:13px;color:#64748b;"><?php echo esc_html($course); ?></p>
                <?php endif; ?>
                <p style="margin:0 0 20px;color:#6b7280;font-size:14px;line-height:1.5;">
                    <?php if (!is_user_logged_in()): ?>
                        Please log in to view full Fhorsite ratings and Points Engine picks for this race.
                        <?php if ($is_wednesday): ?>
                        On <strong>Wednesdays</strong>, any logged-in account can view race pages for free.
                        <?php else: ?>
                        An active <strong>Fhorsite Member</strong> subscription is required on other days.
                        <?php endif; ?>
                    <?php else: ?>
                        Full race ratings are available to <strong>Fhorsite Members</strong>.
                        On <strong>Wednesdays</strong>, any logged-in account can view race pages for free.
                    <?php endif; ?>
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">
                    <?php if (!is_user_logged_in()): ?>
                    <a href="<?php echo esc_url(wp_login_url($race_url)); ?>" style="display:inline-block;padding:10px 18px;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;text-decoration:none;">Log in</a>
                    <?php if ($signup_url !== ''): ?>
                    <a href="<?php echo esc_url($signup_url); ?>" style="display:inline-block;padding:10px 18px;border-radius:8px;background:#0f766e;color:#fff;font-weight:700;text-decoration:none;">Register</a>
                    <?php endif; ?>
                    <?php elseif (!$is_wednesday && $signup_url !== ''): ?>
                    <a href="<?php echo esc_url($signup_url); ?>" style="display:inline-block;padding:10px 18px;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;text-decoration:none;">Become a Fhorsite Member</a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(home_url('/daily/')); ?>" style="display:inline-block;padding:10px 18px;border-radius:8px;background:#f3f4f6;color:#374151;font-weight:600;text-decoration:none;">← Daily races</a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

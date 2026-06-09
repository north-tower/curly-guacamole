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

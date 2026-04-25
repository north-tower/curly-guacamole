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

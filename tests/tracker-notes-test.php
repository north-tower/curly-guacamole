<?php
/**
 * Tracker note kind/source validation. Run: php tests/tracker-notes-test.php
 */

$failures = 0;

function tn_assert($label, $cond) {
    global $failures;
    if ($cond) {
        echo "ok  $label\n";
        return;
    }
    $failures++;
    echo "FAIL $label\n";
}

function tn_eq($label, $expected, $actual) {
    tn_assert(
        $label . ' (expected ' . json_encode($expected) . ', got ' . json_encode($actual) . ')',
        $expected === $actual
    );
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim((string) $str);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp) {
        return date($format, $timestamp);
    }
}

if (!function_exists('nl2br')) {
    function nl2br($string) {
        return str_replace(["\r\n", "\r", "\n"], '<br />', (string) $string);
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = null) {
        $words = preg_split('/\s+/', trim((string) $text));
        if (count($words) <= $num_words) {
            return implode(' ', $words);
        }
        return implode(' ', array_slice($words, 0, $num_words)) . ($more ?? '…');
    }
}

require dirname(__DIR__) . '/inc/tracker.php';

tn_eq('default kind', 'note', fhor_tracker_sanitize_kind(''));
tn_eq('trainer quote kind', 'trainer_quote', fhor_tracker_sanitize_kind('trainer_quote'));
tn_eq('invalid kind', 'note', fhor_tracker_sanitize_kind('bogus'));

tn_eq('source cleared for note', '', fhor_tracker_sanitize_source('TV', 'note'));
tn_eq('valid source', 'TV', fhor_tracker_sanitize_source('TV', 'trainer_quote'));
tn_eq('invalid source', '', fhor_tracker_sanitize_source('Twitter', 'trainer_quote'));

$legacy = ['note' => 'Hello', 'created_at' => '2026-01-01 12:00:00'];
tn_eq('legacy entry kind', 'note', fhor_tracker_get_entry_kind($legacy));

$sorted = bricks_tracker_sort_notes_newest([
    ['created_at' => '2026-01-01 10:00:00', 'note' => 'a'],
    ['created_at' => '2026-01-02 10:00:00', 'note' => 'b'],
]);
tn_eq('newest first', 'b', $sorted[0]['note']);

$pick = bricks_tracker_pick_latest_for_context([
    ['race_id' => '1', 'race_date' => '2026-01-01', 'note' => 'old'],
    ['race_id' => '2', 'race_date' => '2026-01-02', 'note' => 'match'],
], ['race_id' => '2', 'race_date' => '2026-01-01']);
tn_eq('pick by race_id', 'match', $pick['note']);

$html = fhor_tracker_render_note_item_html([
    'id' => 'tn_test',
    'kind' => 'trainer_quote',
    'note' => 'Runs well',
    'trainer_name' => 'A Trainer',
    'source' => 'TV',
    'created_at' => '2026-01-01 12:00:00',
], 'Test Horse', 'inline');
tn_assert('quote html italic class', strpos($html, 'fhor-tracker-note-body--quote') !== false);
tn_assert('quote html trainer footer', strpos($html, 'A Trainer') !== false);

exit($failures > 0 ? 1 : 0);

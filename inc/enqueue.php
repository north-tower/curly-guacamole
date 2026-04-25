<?php
/**
 * Frontend enqueue and localization hooks.
 */

function bricks_race_table_enqueue_scripts() {
    // Only skip loading on specific horse pages, not on the daily races page
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    if ((get_query_var('horse_name') || get_query_var('runner_id')) &&
        strpos($current_url, '/daily') === false) {
        return;
    }

    // Enqueue JavaScript inline if file doesn't exist
    wp_enqueue_script('jquery');

    // FORCE inline JavaScript for debugging
    add_action('wp_footer', 'bricks_race_table_inline_js', 999); // High priority

    wp_localize_script('jquery', 'race_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'default_date' => date('Y-m-d'),
        'version' => time()
    ]);
}
add_action('wp_enqueue_scripts', 'bricks_race_table_enqueue_scripts');

function bricks_speed_performance_enqueue_scripts() {
    if (get_query_var('horse_name') || get_query_var('runner_id')) {
        return;
    }

    // Enqueue Chart.js from CDN
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);

    wp_enqueue_script('jquery');

    // Check if external JS file exists
    $js_file = get_stylesheet_directory() . '/speed-performance.js';
    if (file_exists($js_file)) {
        wp_enqueue_script('speed-performance-ajax', get_stylesheet_directory_uri() . '/speed-performance.js', ['jquery', 'chartjs'], '1.0.3', true);
    } else {
        add_action('wp_footer', 'bricks_speed_performance_inline_js');
    }

    wp_localize_script('jquery', 'speed_performance_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'default_date' => date('d-m-Y'),
        'debug' => WP_DEBUG,
        'is_logged_in' => is_user_logged_in(),
        'tracker_nonce' => wp_create_nonce('bricks_tracker_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'bricks_speed_performance_enqueue_scripts');

function horse_history_enqueue_scripts() {
    // Only load on horse history pages
    if (get_query_var('horse_name') || get_query_var('runner_id')) {
        wp_enqueue_script('jquery');
    }
}
add_action('wp_enqueue_scripts', 'horse_history_enqueue_scripts');

function bricks_tracker_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'bricks_tracker_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'is_logged_in' => is_user_logged_in(),
        'tracker_nonce' => wp_create_nonce('bricks_tracker_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'bricks_tracker_enqueue_scripts', 30);

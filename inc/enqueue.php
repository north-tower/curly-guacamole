<?php
/**
 * Frontend enqueue and localization hooks.
 */

if (!function_exists('bricks_request_uri_contains')) {
    function bricks_request_uri_contains($needles) {
        $uri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && strpos($uri, strtolower((string) $needle)) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('bricks_current_post_has_shortcode')) {
    function bricks_current_post_has_shortcode($tags) {
        if (!is_singular()) {
            return false;
        }

        $post = get_post();
        if (!$post || empty($post->post_content)) {
            return false;
        }

        foreach ((array) $tags as $tag) {
            if ($tag !== '' && has_shortcode($post->post_content, $tag)) {
                return true;
            }
        }

        return false;
    }
}

function bricks_race_table_enqueue_scripts() {
    $is_daily_route = bricks_request_uri_contains(['/daily', '/racecourses', '/tracks']);
    $has_race_shortcode = bricks_current_post_has_shortcode(['race_table', 'race_table_full', 'racecourse_guide', 'racecourse_guide_card']);
    $is_track_route = get_query_var('track_slug') || get_query_var('tracks_index')
        || get_query_var('racecourses_index') || get_query_var('racecourses_region');
    if (!$is_daily_route && !$has_race_shortcode && !$is_track_route) {
        return;
    }

    wp_enqueue_script('jquery');

    $race_js_file = get_stylesheet_directory() . '/race-table.js';
    if (file_exists($race_js_file)) {
        wp_enqueue_script(
            'race-table-ajax',
            get_stylesheet_directory_uri() . '/race-table.js',
            ['jquery'],
            filemtime($race_js_file),
            true
        );
    } else {
        add_action('wp_footer', 'bricks_race_table_inline_js', 999);
    }

    $localize_handle = wp_script_is('race-table-ajax', 'enqueued') ? 'race-table-ajax' : 'jquery';
    wp_localize_script($localize_handle, 'race_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'default_date' => date('Y-m-d'),
        'version' => time()
    ]);
}
add_action('wp_enqueue_scripts', 'bricks_race_table_enqueue_scripts');

function bricks_speed_performance_enqueue_scripts() {
    $is_speed_route = bricks_request_uri_contains(['/speed']);
    $has_speed_shortcode = bricks_current_post_has_shortcode(['speed_performance_table']);
    if (!$is_speed_route && !$has_speed_shortcode) {
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

function bricks_sire_insights_enqueue_scripts() {
    $is_sire_route = bricks_request_uri_contains(['/sire-insights', '/daily-sire-insights']);
    $has_sire_shortcode = bricks_current_post_has_shortcode(['daily_sires_insights']);
    if (!$is_sire_route && !$has_sire_shortcode) {
        return;
    }

    wp_enqueue_script('jquery');

    $js_file = get_stylesheet_directory() . '/sire-insights.js';
    if (file_exists($js_file)) {
        wp_enqueue_script(
            'sire-insights-ajax',
            get_stylesheet_directory_uri() . '/sire-insights.js',
            ['jquery'],
            filemtime($js_file),
            true
        );
    }

    $localize_handle = wp_script_is('sire-insights-ajax', 'enqueued') ? 'sire-insights-ajax' : 'jquery';
    $default_date = function_exists('bricks_sire_insights_get_latest_date') ? bricks_sire_insights_get_latest_date() : '';
    if ($default_date === '') {
        $default_date = wp_date('Y-m-d', current_time('timestamp'));
    }
    wp_localize_script($localize_handle, 'sire_insights_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'default_date' => $default_date,
    ]);
}
add_action('wp_enqueue_scripts', 'bricks_sire_insights_enqueue_scripts');

function horse_history_enqueue_scripts() {
    // Only load on horse history pages
    if (get_query_var('horse_name') || get_query_var('runner_id')) {
        wp_enqueue_script('jquery');
    }
}
add_action('wp_enqueue_scripts', 'horse_history_enqueue_scripts');

function bricks_tracker_enqueue_scripts() {
    $needs_tracker_assets =
        get_query_var('race_id') ||
        get_query_var('runner_id') ||
        get_query_var('horse_name') ||
        get_query_var('race_comment_id') ||
        get_query_var('my_tracker_page') ||
        get_query_var('my_points_backtest') ||
        get_query_var('my_today_picks_page') ||
        get_query_var('track_slug') ||
        get_query_var('tracks_index') ||
        get_query_var('racecourses_index') ||
        get_query_var('racecourses_region') ||
        get_query_var('festivals_index') ||
        get_query_var('festival_slug') ||
        bricks_request_uri_contains(['/my-tracker', '/points-backtest', '/today-picks', '/race/', '/horse-history/', '/race-comments/', '/tracks', '/racecourses', '/festivals']) ||
        bricks_current_post_has_shortcode([
            'my_tracker_dashboard',
            'race_table',
            'race_table_full',
            'racecourse_guide',
            'racecourse_guide_static',
            'racecourse_guide_card',
            'racecourse_index',
            'racing_festivals_index',
            'racing_festival_hub',
            'speed_performance_table',
            'horse_history',
            'race_comment_history',
            'race_detail',
            'points_backtest',
        ]);

    if (!$needs_tracker_assets) {
        return;
    }

    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'bricks_tracker_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'is_logged_in' => is_user_logged_in(),
        'tracker_nonce' => wp_create_nonce('bricks_tracker_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'bricks_tracker_enqueue_scripts', 30);

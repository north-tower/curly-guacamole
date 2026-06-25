<?php
/**
 * Rewrite rules, query vars, and virtual template routing.
 */

function bricks_my_tracker_rewrite_rules() {
    add_rewrite_rule('my-tracker/?$', 'index.php?my_tracker_page=1', 'top');
    add_rewrite_rule('points-backtest/?$', 'index.php?my_points_backtest=1', 'top');
    add_rewrite_rule('admin-pnl/?$', 'index.php?my_admin_pnl_page=1', 'top');
    add_rewrite_rule('today-picks/?$', 'index.php?my_today_picks_page=1', 'top');
}
add_action('init', 'bricks_my_tracker_rewrite_rules', 10);

function bricks_my_tracker_query_vars($vars) {
    $vars[] = 'my_tracker_page';
    $vars[] = 'my_points_backtest';
    $vars[] = 'my_admin_pnl_page';
    $vars[] = 'my_today_picks_page';
    return $vars;
}
add_filter('query_vars', 'bricks_my_tracker_query_vars');

function bricks_my_tracker_template_redirect() {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_tracker_uri = strpos($request_uri, '/my-tracker') !== false;
    $is_backtest_uri = strpos($request_uri, '/points-backtest') !== false;
    $is_admin_pnl_uri = strpos($request_uri, '/admin-pnl') !== false;
    $is_today_picks_uri = strpos($request_uri, '/today-picks') !== false;
    $is_tracker_qv = (bool) get_query_var('my_tracker_page');
    $is_backtest_qv = (bool) get_query_var('my_points_backtest');
    $is_admin_pnl_qv = (bool) get_query_var('my_admin_pnl_page');
    $is_today_picks_qv = (bool) get_query_var('my_today_picks_page');

    if (
        (!$is_tracker_qv && !$is_backtest_qv && !$is_admin_pnl_qv && !$is_today_picks_qv
            && !$is_tracker_uri && !$is_backtest_uri && !$is_admin_pnl_uri && !$is_today_picks_uri)
        || is_admin()
    ) {
        return;
    }

    // Points backtest + Admin P&L + Today's picks: same theme shell as My Tracker (header/footer). Admins only.
    if ($is_backtest_qv || $is_backtest_uri || $is_admin_pnl_qv || $is_admin_pnl_uri || $is_today_picks_qv || $is_today_picks_uri) {
        if (!bricks_user_can_access_points_backtest()) {
            if ($is_admin_pnl_qv || $is_admin_pnl_uri) {
                $page_title = 'Admin P&amp;L';
            } elseif ($is_today_picks_qv || $is_today_picks_uri) {
                $page_title = 'Today\'s Picks';
            } else {
                $page_title = 'Points Backtest';
            }
            status_header(403);
            nocache_headers();
            ob_start();
            get_header();
            $header = ob_get_clean();
            ob_start();
            get_footer();
            $footer = ob_get_clean();
            echo $header;
            echo '<main class="main-content"><div class="content-container">';
            echo '<div style="max-width:760px;margin:24px auto;padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">';
            echo '<h1 style="margin:0 0 8px;font-size:24px;color:#111827;">' . $page_title . '</h1>';
            echo '<p style="margin:0;color:#6b7280;">This page is available to site administrators only.</p>';
            echo '</div></div></main>';
            echo $footer;
            exit;
        }

        if ($is_admin_pnl_qv || $is_admin_pnl_uri) {
            $shortcode = '[admin_pnl_dashboard]';
        } elseif ($is_today_picks_qv || $is_today_picks_uri) {
            $shortcode = '[points_today_picks]';
        } else {
            $shortcode = '[points_backtest]';
        }
        status_header(200);
        nocache_headers();
        ob_start();
        get_header();
        $header = ob_get_clean();
        ob_start();
        get_footer();
        $footer = ob_get_clean();
        echo $header;
        echo '<main class="main-content"><div class="content-container">';
        echo do_shortcode($shortcode);
        echo '</div></main>';
        echo $footer;
        exit;
    }

    status_header(200);
    nocache_headers();

    ob_start();
    get_header();
    $header = ob_get_clean();

    ob_start();
    get_footer();
    $footer = ob_get_clean();

    echo $header;
    echo '<main class="main-content"><div class="content-container">';
    echo do_shortcode('[my_tracker_dashboard]');
    echo '</div></main>';
    echo $footer;
    exit;
}
add_action('template_redirect', 'bricks_my_tracker_template_redirect', 1);

function bricks_flush_my_tracker_rewrite_rules_if_needed() {
    if (get_option('my_tracker_rewrite_rules_flushed') !== '6') {
        flush_rewrite_rules();
        update_option('my_tracker_rewrite_rules_flushed', '6');
    }
}
add_action('init', 'bricks_flush_my_tracker_rewrite_rules_if_needed', 999);

function bricks_add_race_detail_rewrite_rules() {
    add_rewrite_tag('%race_id%', '([A-Za-z0-9_-]+)');
    add_rewrite_rule(
        '^race/([A-Za-z0-9_-]+)/?$',
        'index.php?race_id=$matches[1]',
        'top'
    );
}
add_action('init', 'bricks_add_race_detail_rewrite_rules', 20);

function bricks_add_race_detail_query_vars($vars) {
    $vars[] = 'race_id';
    return $vars;
}
add_filter('query_vars', 'bricks_add_race_detail_query_vars');

function bricks_race_detail_template($template) {
    if (is_admin()) {
        return $template;
    }
    if (get_query_var('race_id')) {
        $custom = get_stylesheet_directory() . '/race-detail.php';
        if (file_exists($custom)) {
            return $custom;
        }
    }
    return $template;
}
add_filter('template_include', 'bricks_race_detail_template');

function bricks_flush_rewrite_rules_if_needed() {
    if (get_option('bricks_race_detail_rewrite_flushed') !== '2') {
        flush_rewrite_rules();
        update_option('bricks_race_detail_rewrite_flushed', '2');
    }
}
add_action('init', 'bricks_flush_rewrite_rules_if_needed', 999);

/**
 * Ensure a valid global $post exists on custom virtual pages.
 * This prevents plugins from crashing when get_the_ID() returns false.
 */
function bricks_setup_virtual_page_post() {
    if (is_admin()) {
        return;
    }

    // Only act on our custom virtual pages.
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_virtual = get_query_var('race_id')
        || get_query_var('runner_id')
        || get_query_var('horse_name')
        || get_query_var('race_comment_id')
        || get_query_var('my_tracker_page')
        || get_query_var('my_points_backtest')
        || get_query_var('my_admin_pnl_page')
        || get_query_var('my_today_picks_page')
        || get_query_var('track_slug')
        || get_query_var('tracks_index')
        || get_query_var('racecourses_index')
        || get_query_var('racecourses_region')
        || get_query_var('proven_winners_page')
        || get_query_var('festivals_index')
        || get_query_var('festival_slug')
        || (function_exists('bricks_proven_winners_is_request') && bricks_proven_winners_is_request())
        || (function_exists('bricks_festival_is_request') && bricks_festival_is_request())
        || (strpos($request_uri, '/my-tracker') !== false)
        || (strpos($request_uri, '/points-backtest') !== false)
        || (strpos($request_uri, '/admin-pnl') !== false)
        || (strpos($request_uri, '/today-picks') !== false)
        || (strpos($request_uri, '/tracks') !== false);
    if (!$is_virtual) {
        return;
    }

    global $post, $wp_query;

    if ($post && $post->ID) {
        return;
    }

    $page_id = (int) get_option('page_on_front');

    if (!$page_id) {
        $any_page = get_posts([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields'      => 'ids',
        ]);
        if (!empty($any_page)) {
            $page_id = (int) $any_page[0];
        }
    }

    if ($page_id) {
        $post = get_post($page_id);
    }

    if (!$post) {
        $post = new WP_Post((object) [
            'ID'           => 0,
            'post_title'   => 'Virtual Page',
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
    }

    $wp_query->post = $post;
    $wp_query->posts = [$post];
    $wp_query->post_count = 1;
    $wp_query->is_404 = false;
    $wp_query->is_page = true;
    $wp_query->is_singular = true;
    setup_postdata($post);

    // Prevent WordPress from redirecting to the real page's permalink.
    remove_action('template_redirect', 'redirect_canonical');
}
add_action('wp', 'bricks_setup_virtual_page_post', 1);

function add_horse_history_rewrite_rules() {
    // Tokenized runner_id route first.
    add_rewrite_rule(
        'horse-history/(h_[A-Za-z0-9]+)/?$',
        'index.php?runner_id=$matches[1]',
        'top'
    );
    // Legacy numeric runner_id route.
    add_rewrite_rule(
        'horse-history/([0-9]+)/?$',
        'index.php?runner_id=$matches[1]',
        'top'
    );
    // Back-compat: name-based route.
    add_rewrite_rule(
        'horse-history/([^/]+)/?$',
        'index.php?horse_name=$matches[1]',
        'top'
    );
}
add_action('init', 'add_horse_history_rewrite_rules', 10);

function add_horse_history_query_vars($vars) {
    $vars[] = 'horse_name';
    $vars[] = 'runner_id';
    return $vars;
}
add_filter('query_vars', 'add_horse_history_query_vars');

function horse_history_template($template) {
    if (get_query_var('runner_id') || get_query_var('horse_name')) {
        if (is_admin()) {
            return $template;
        }

        $custom_template = get_stylesheet_directory() . '/horse-history.php';

        if (!file_exists($custom_template)) {
            $fallback_template = get_template_directory() . '/page.php';
            if (!file_exists($fallback_template)) {
                $fallback_template = get_template_directory() . '/index.php';
            }

            if (file_exists($fallback_template)) {
                return $fallback_template;
            }
        } else {
            return $custom_template;
        }
    }
    return $template;
}
add_filter('template_include', 'horse_history_template');

function flush_horse_history_rewrite_rules_if_needed() {
    if (get_option('horse_history_rewrite_rules_flushed') !== '2') {
        flush_rewrite_rules();
        update_option('horse_history_rewrite_rules_flushed', '2');
    }
}
add_action('init', 'flush_horse_history_rewrite_rules_if_needed', 999);

function add_race_comment_rewrite_rules() {
    add_rewrite_rule(
        'race-comments/([A-Za-z0-9_-]+)/?$',
        'index.php?race_comment_id=$matches[1]',
        'top'
    );
}
add_action('init', 'add_race_comment_rewrite_rules', 10);

function add_race_comment_query_vars($vars) {
    $vars[] = 'race_comment_id';
    return $vars;
}
add_filter('query_vars', 'add_race_comment_query_vars');

function race_comment_template($template) {
    if (get_query_var('race_comment_id')) {
        if (is_admin()) {
            return $template;
        }

        $custom_template = get_stylesheet_directory() . '/race-comment.php';

        if (!file_exists($custom_template)) {
            $fallback_template = get_template_directory() . '/page.php';
            if (!file_exists($fallback_template)) {
                $fallback_template = get_template_directory() . '/index.php';
            }

            if (file_exists($fallback_template)) {
                return $fallback_template;
            }
        } else {
            return $custom_template;
        }
    }
    return $template;
}
add_filter('template_include', 'race_comment_template');

function flush_race_comment_rewrite_rules_if_needed() {
    if (get_option('race_comment_rewrite_rules_flushed') !== '2') {
        flush_rewrite_rules();
        update_option('race_comment_rewrite_rules_flushed', '2');
    }
}
add_action('init', 'flush_race_comment_rewrite_rules_if_needed', 999);

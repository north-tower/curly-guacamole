<?php
/**
 * Historical daily race-card archives: /daily/archive/YYYY-MM-DD/
 *
 * Live /daily/ stays the current dashboard. Past meeting days get a stable,
 * self-canonical URL so indexed keywords do not 404 when the hub rolls over.
 */

if (!function_exists('bricks_daily_archive_today')) {
    function bricks_daily_archive_today() {
        return wp_date('Y-m-d', current_time('timestamp'));
    }
}

if (!function_exists('bricks_daily_archive_tomorrow')) {
    function bricks_daily_archive_tomorrow() {
        return wp_date('Y-m-d', strtotime('+1 day', current_time('timestamp')));
    }
}

if (!function_exists('bricks_daily_archive_normalize_date')) {
    /**
     * @return string|null Y-m-d or null if invalid.
     */
    function bricks_daily_archive_normalize_date($date) {
        $date = trim((string) $date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            return null;
        }
        return $date;
    }
}

if (!function_exists('bricks_daily_archive_url')) {
    function bricks_daily_archive_url($date) {
        $date = bricks_daily_archive_normalize_date($date);
        if ($date === null) {
            return home_url('/daily/');
        }
        return home_url('/daily/archive/' . $date . '/');
    }
}

if (!function_exists('bricks_daily_archive_resolve_date_from_request')) {
    function bricks_daily_archive_resolve_date_from_request() {
        $qv = get_query_var('daily_archive_date');
        if (is_string($qv) && $qv !== '') {
            return bricks_daily_archive_normalize_date($qv);
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (preg_match('#/daily/archive/(\d{4}-\d{2}-\d{2})/?#', $uri, $m)) {
            return bricks_daily_archive_normalize_date($m[1]);
        }
        return null;
    }
}

if (!function_exists('bricks_daily_archive_is_request')) {
    function bricks_daily_archive_is_request() {
        return bricks_daily_archive_resolve_date_from_request() !== null;
    }
}

if (!function_exists('bricks_race_tables_for_date')) {
    /**
     * Pick races/runners tables for a meeting date.
     *
     * @return array{races:string,runners:string,source:string}
     */
    function bricks_race_tables_for_date($date) {
        global $wpdb;

        $date = bricks_daily_archive_normalize_date($date) ?: bricks_daily_archive_today();
        $today = bricks_daily_archive_today();
        $tomorrow = bricks_daily_archive_tomorrow();

        if ($date === $tomorrow) {
            return [
                'races' => 'advance_daily_races',
                'runners' => 'advance_daily_runners',
                'source' => 'advance',
            ];
        }

        $beta_races = 'advance_daily_races_beta';
        $beta_runners = 'advance_daily_runners_beta';
        $historic_races = 'historic_races_beta';
        $historic_runners = 'historic_runners_beta';

        $beta_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $beta_races)) === $beta_races;
        $historic_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $historic_races)) === $historic_races;

        $beta_count = 0;
        if ($beta_exists) {
            $beta_count = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `$beta_races` WHERE meeting_date = %s",
                $date
            )));
        }

        if ($beta_count > 0 || $date >= $today) {
            return [
                'races' => $beta_races,
                'runners' => $beta_runners,
                'source' => 'beta',
            ];
        }

        if ($historic_exists) {
            $historic_count = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `$historic_races` WHERE meeting_date = %s",
                $date
            )));
            if ($historic_count > 0) {
                return [
                    'races' => $historic_races,
                    'runners' => $historic_runners,
                    'source' => 'historic',
                ];
            }
        }

        // Fall back to beta so empty days still query a consistent schema.
        return [
            'races' => $beta_races,
            'runners' => $beta_runners,
            'source' => 'beta',
        ];
    }
}

if (!function_exists('bricks_daily_archive_date_has_races')) {
    function bricks_daily_archive_date_has_races($date) {
        global $wpdb;

        $date = bricks_daily_archive_normalize_date($date);
        if ($date === null) {
            return false;
        }

        $tables = bricks_race_tables_for_date($date);
        $races = $tables['races'];
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $races)) !== $races) {
            return false;
        }

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$races` WHERE meeting_date = %s",
            $date
        ))) > 0;
    }
}

if (!function_exists('bricks_add_daily_archive_rewrite_rules')) {
    function bricks_add_daily_archive_rewrite_rules() {
        add_rewrite_tag('%daily_archive_date%', '([0-9]{4}-[0-9]{2}-[0-9]{2})');
        add_rewrite_rule(
            '^daily/archive/([0-9]{4}-[0-9]{2}-[0-9]{2})/?$',
            'index.php?daily_archive_date=$matches[1]',
            'top'
        );
    }
}
add_action('init', 'bricks_add_daily_archive_rewrite_rules', 20);

if (!function_exists('bricks_add_daily_archive_query_vars')) {
    function bricks_add_daily_archive_query_vars($vars) {
        $vars[] = 'daily_archive_date';
        return $vars;
    }
}
add_filter('query_vars', 'bricks_add_daily_archive_query_vars');

if (!function_exists('bricks_flush_daily_archive_rewrite_rules_if_needed')) {
    function bricks_flush_daily_archive_rewrite_rules_if_needed() {
        if (get_option('bricks_daily_archive_rewrite_flushed') !== '1') {
            flush_rewrite_rules(false);
            update_option('bricks_daily_archive_rewrite_flushed', '1', false);
        }
    }
}
add_action('init', 'bricks_flush_daily_archive_rewrite_rules_if_needed', 999);

if (!function_exists('bricks_daily_archive_template_redirect')) {
    function bricks_daily_archive_template_redirect() {
        if (is_admin()) {
            return;
        }

        $date = bricks_daily_archive_resolve_date_from_request();
        if ($date === null) {
            return;
        }

        $today = bricks_daily_archive_today();
        $tomorrow = bricks_daily_archive_tomorrow();

        // Live hub owns today (and tomorrow lives on /daily/ tabs).
        if ($date === $today || $date === $tomorrow) {
            wp_safe_redirect(home_url('/daily/'), 301);
            exit;
        }

        if ($date > $tomorrow) {
            status_header(404);
            nocache_headers();
            $GLOBALS['wp_query']->set_404();
            return;
        }

        if (!bricks_daily_archive_date_has_races($date)) {
            status_header(404);
            nocache_headers();
            $GLOBALS['wp_query']->set_404();
            return;
        }

        status_header(200);
        // Archives are immutable for a given day; allow public caching.
        header('Cache-Control: public, max-age=3600');

        global $post, $wp_query;
        $title = function_exists('bricks_seo_build_daily_archive_meta_title')
            ? bricks_seo_build_daily_archive_meta_title($date)
            : sprintf('Horse Racing Speed Ratings Archive – %s', $date);

        $post = new WP_Post((object) [
            'ID' => -920260731,
            'post_author' => 1,
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'daily-archive-' . $date,
            'guid' => bricks_daily_archive_url($date),
            'filter' => 'raw',
        ]);
        $wp_query->post = $post;
        $wp_query->posts = [$post];
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        $wp_query->is_home = false;
        $wp_query->is_front_page = false;
        setup_postdata($post);

        ob_start();
        get_header();
        $header = ob_get_clean();
        ob_start();
        get_footer();
        $footer = ob_get_clean();

        echo $header;
        echo '<main id="brx-content" class="main-content daily-archive-page">';
        echo '<div class="content-container" style="max-width:1400px;margin:0 auto;padding:16px 12px 40px;box-sizing:border-box;">';
        echo do_shortcode('[race_table date="' . esc_attr($date) . '" archive="1"]');
        echo '</div></main>';
        echo $footer;
        exit;
    }
}
add_action('template_redirect', 'bricks_daily_archive_template_redirect', 2);

if (!function_exists('bricks_daily_archive_self_canonical')) {
    function bricks_daily_archive_self_canonical($url = '') {
        if (!bricks_daily_archive_is_request()) {
            return $url;
        }
        $date = bricks_daily_archive_resolve_date_from_request();
        return $date ? bricks_daily_archive_url($date) : $url;
    }
}
add_filter('slim_seo_canonical_url', 'bricks_daily_archive_self_canonical', 20);
add_filter('get_canonical_url', 'bricks_daily_archive_self_canonical', 20);
add_filter('wpseo_canonical', 'bricks_daily_archive_self_canonical', 20);

if (!function_exists('bricks_daily_archive_output_canonical_link')) {
    function bricks_daily_archive_output_canonical_link() {
        if (!bricks_daily_archive_is_request()) {
            return;
        }
        $date = bricks_daily_archive_resolve_date_from_request();
        if (!$date) {
            return;
        }
        $canonical = bricks_daily_archive_url($date);
        echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
        echo '<meta name="robots" content="index,follow" />' . "\n";
    }
}
add_action('wp_head', 'bricks_daily_archive_output_canonical_link', 1);

if (!function_exists('bricks_seo_build_daily_archive_meta_title')) {
    function bricks_seo_build_daily_archive_meta_title($date = '') {
        $date = bricks_daily_archive_normalize_date($date) ?: bricks_daily_archive_resolve_date_from_request();
        if (!$date) {
            return function_exists('bricks_seo_build_daily_meta_title')
                ? bricks_seo_build_daily_meta_title()
                : 'Daily Horse Racing Speed Ratings & Form';
        }
        $display = wp_date('d/m/Y', strtotime($date . ' 12:00:00'));
        return sprintf('Daily Horse Racing Speed Ratings & Form – %s', $display);
    }
}

if (!function_exists('bricks_seo_build_daily_archive_meta_description')) {
    function bricks_seo_build_daily_archive_meta_description($date = '') {
        $date = bricks_daily_archive_normalize_date($date) ?: bricks_daily_archive_resolve_date_from_request();
        if (!$date) {
            return function_exists('bricks_seo_build_daily_meta_description')
                ? bricks_seo_build_daily_meta_description()
                : '';
        }
        $friendly = wp_date('l, j F Y', strtotime($date . ' 12:00:00'));
        return sprintf(
            'Archived UK and Irish race cards for %s — turf speed ratings, All-Weather (AW) speed figures, runners, and Fhorsite form for that meeting day.',
            $friendly
        );
    }
}

if (!function_exists('bricks_seo_build_daily_archive_h1')) {
    function bricks_seo_build_daily_archive_h1($date = '') {
        $date = bricks_daily_archive_normalize_date($date) ?: bricks_daily_archive_resolve_date_from_request();
        if (!$date) {
            return function_exists('bricks_seo_build_daily_h1')
                ? bricks_seo_build_daily_h1()
                : "Today's Horse Racing Ratings";
        }
        $friendly = wp_date('l, d F Y', strtotime($date . ' 12:00:00'));
        return sprintf('Horse Racing Ratings Archive: %s', $friendly);
    }
}

if (!function_exists('bricks_daily_archive_filter_meta_title')) {
    function bricks_daily_archive_filter_meta_title($title) {
        if (!bricks_daily_archive_is_request()) {
            return $title;
        }
        return bricks_seo_build_daily_archive_meta_title();
    }
}
add_filter('slim_seo_meta_title', 'bricks_daily_archive_filter_meta_title', 30);
add_filter('pre_get_document_title', 'bricks_daily_archive_filter_meta_title', 30);

if (!function_exists('bricks_daily_archive_filter_meta_description')) {
    function bricks_daily_archive_filter_meta_description($description) {
        if (!bricks_daily_archive_is_request()) {
            return $description;
        }
        return bricks_seo_build_daily_archive_meta_description();
    }
}
add_filter('slim_seo_meta_description', 'bricks_daily_archive_filter_meta_description', 30);

if (!function_exists('bricks_daily_archive_output_dataset_json_ld')) {
    function bricks_daily_archive_output_dataset_json_ld() {
        static $done = false;
        if ($done || !bricks_daily_archive_is_request()) {
            return;
        }
        $date = bricks_daily_archive_resolve_date_from_request();
        if (!$date) {
            return;
        }
        $done = true;

        $url = bricks_daily_archive_url($date);
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Dataset',
            '@id' => $url . '#dataset',
            'name' => bricks_seo_build_daily_archive_meta_title($date),
            'description' => bricks_seo_build_daily_archive_meta_description($date),
            'url' => $url,
            'temporalCoverage' => $date,
            'dateModified' => $date . 'T23:59:59' . wp_date('P', current_time('timestamp')),
            'isAccessibleForFree' => true,
            'creator' => function_exists('bricks_seo_get_organization_schema')
                ? bricks_seo_get_organization_schema()
                : ['@type' => 'Organization', 'name' => get_bloginfo('name'), 'url' => home_url('/')],
        ];

        if (function_exists('bricks_seo_print_json_ld')) {
            bricks_seo_print_json_ld($schema);
        } else {
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
        }
    }
}
add_action('wp_head', 'bricks_daily_archive_output_dataset_json_ld', 8);

if (!function_exists('bricks_daily_archive_nav_html')) {
    /**
     * Prev/next day + back to live hub.
     */
    function bricks_daily_archive_nav_html($date) {
        $date = bricks_daily_archive_normalize_date($date);
        if (!$date) {
            return '';
        }

        $prev = wp_date('Y-m-d', strtotime($date . ' -1 day'));
        $next = wp_date('Y-m-d', strtotime($date . ' +1 day'));
        $today = bricks_daily_archive_today();

        $prev_url = bricks_daily_archive_date_has_races($prev) ? bricks_daily_archive_url($prev) : '';
        $next_url = '';
        if ($next < $today && bricks_daily_archive_date_has_races($next)) {
            $next_url = bricks_daily_archive_url($next);
        } elseif ($next === $today) {
            $next_url = home_url('/daily/');
        }

        ob_start();
        ?>
        <nav class="daily-archive-nav" aria-label="Archive day navigation" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin:0 0 16px;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <?php if ($prev_url !== ''): ?>
                    <a href="<?php echo esc_url($prev_url); ?>" style="font-weight:700;color:#2563eb;text-decoration:none;">← <?php echo esc_html(wp_date('j M Y', strtotime($prev))); ?></a>
                <?php endif; ?>
                <?php if ($next_url !== ''): ?>
                    <a href="<?php echo esc_url($next_url); ?>" style="font-weight:700;color:#2563eb;text-decoration:none;"><?php echo esc_html($next === $today ? 'Live Daily Cards' : wp_date('j M Y', strtotime($next))); ?> →</a>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url(home_url('/daily/')); ?>" style="font-weight:700;color:#0f766e;text-decoration:none;">View live Daily Race Cards</a>
        </nav>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('bricks_daily_live_yesterday_archive_link_html')) {
    /**
     * Upward/historical link from live /daily/ to yesterday's archive.
     */
    function bricks_daily_live_yesterday_archive_link_html() {
        if (bricks_daily_archive_is_request()) {
            return '';
        }
        if (!function_exists('bricks_seo_is_dashboard_request') || !bricks_seo_is_dashboard_request('daily')) {
            return '';
        }

        $yesterday = wp_date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
        if (!bricks_daily_archive_date_has_races($yesterday)) {
            return '';
        }

        $label = wp_date('l, j F Y', strtotime($yesterday . ' 12:00:00'));
        $url = bricks_daily_archive_url($yesterday);

        return '<p class="daily-archive-yesterday-link" style="margin:0 0 12px;font-size:13px;color:#64748b;">'
            . 'Looking for yesterday\'s cards? '
            . '<a href="' . esc_url($url) . '" style="color:#2563eb;font-weight:700;text-decoration:underline;">'
            . 'Open the ' . esc_html($label) . ' archive'
            . '</a>.</p>';
    }
}

if (!function_exists('bricks_daily_archive_bump_caches_on_rollover')) {
    /**
     * When the morning data refresh runs, invalidate live table caches so
     * /daily/ shows the new day and archive URLs serve from durable tables.
     */
    function bricks_daily_archive_bump_caches_on_rollover() {
        if (function_exists('bricks_bump_cache_namespace_version')) {
            bricks_bump_cache_namespace_version('race_filters');
            bricks_bump_cache_namespace_version('race_table');
        }
    }
}
add_action('bricks_daily_filter_cache_flush', 'bricks_daily_archive_bump_caches_on_rollover', 20);

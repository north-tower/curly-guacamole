<?php
/**
 * Race page SEO: Slim SEO titles, document title fallback, Google paywall schema.
 *
 * Race URLs are virtual (/race/{id}/), not post/race CPT — see inc/rewrites.php.
 */

if (!function_exists('bricks_seo_resolve_race_id_from_request')) {
    function bricks_seo_resolve_race_id_from_request() {
        $race_id = 0;
        if (function_exists('bricks_decode_entity_id')) {
            $race_id = bricks_decode_entity_id(get_query_var('race_id'), 'race');
        }
        if (!$race_id && !empty($_SERVER['REQUEST_URI'])) {
            if (preg_match('#/race/([A-Za-z0-9_-]+)/?#', $_SERVER['REQUEST_URI'], $m)) {
                if (function_exists('bricks_decode_entity_id')) {
                    $race_id = bricks_decode_entity_id($m[1], 'race');
                } elseif (ctype_digit($m[1])) {
                    $race_id = intval($m[1]);
                }
            }
        }
        return $race_id > 0 ? intval($race_id) : 0;
    }
}

if (!function_exists('bricks_seo_is_race_detail_request')) {
    function bricks_seo_is_race_detail_request() {
        return bricks_seo_resolve_race_id_from_request() > 0;
    }
}

if (!function_exists('bricks_seo_get_race_row')) {
    /**
     * @return object|null Race row from advance_daily_races(_beta).
     */
    function bricks_seo_get_race_row($race_id = 0) {
        global $wpdb;

        if ($race_id <= 0) {
            $race_id = bricks_seo_resolve_race_id_from_request();
        }
        if ($race_id <= 0) {
            return null;
        }

        static $cache = [];
        if (array_key_exists($race_id, $cache)) {
            return $cache[$race_id];
        }

        $probe = $wpdb->get_row($wpdb->prepare(
            'SELECT race_id, meeting_date FROM advance_daily_races_beta WHERE race_id = %d',
            $race_id
        ));
        if (!$probe) {
            $probe = $wpdb->get_row($wpdb->prepare(
                'SELECT race_id, meeting_date FROM advance_daily_races WHERE race_id = %d',
                $race_id
            ));
        }
        if (!$probe) {
            $cache[$race_id] = null;
            return null;
        }

        $tomorrow = wp_date('Y-m-d', strtotime('+1 day', current_time('timestamp')));
        $table = ($probe->meeting_date === $tomorrow) ? 'advance_daily_races' : 'advance_daily_races_beta';
        $race = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$table` WHERE race_id = %d",
            $race_id
        ));

        $cache[$race_id] = $race ?: null;
        return $cache[$race_id];
    }
}

if (!function_exists('bricks_seo_format_course_name')) {
    /**
     * DB course keys often use underscores (Newton_Abbot).
     */
    function bricks_seo_format_course_name($course) {
        $course = trim((string) $course);
        if ($course === '') {
            return '';
        }
        $course = str_replace('_', ' ', $course);
        $course = preg_replace('/\s+/', ' ', $course);
        return $course;
    }
}

if (!function_exists('bricks_seo_build_meta_title')) {
    function bricks_seo_build_meta_title() {
        $race = bricks_seo_get_race_row();
        if (!$race) {
            return '';
        }

        $race_title = trim((string) ($race->race_title ?? ''));
        $course = bricks_seo_format_course_name($race->course ?? '');
        $meeting_date = (string) ($race->meeting_date ?? '');
        $formatted_date = $meeting_date !== '' ? wp_date('j F Y', strtotime($meeting_date)) : '';

        if ($race_title !== '' && $course !== '' && $formatted_date !== '') {
            $headline = sprintf('%s (%s, %s)', $race_title, $course, $formatted_date);
        } elseif ($race_title !== '' && $formatted_date !== '') {
            $headline = sprintf('%s (%s)', $race_title, $formatted_date);
        } elseif ($race_title !== '') {
            $headline = $race_title;
        } else {
            $headline = 'Race ratings';
        }

        return $headline . ' | Speed & Fhorsite Ratings';
    }
}

if (!function_exists('bricks_seo_build_meta_description')) {
    function bricks_seo_build_meta_description() {
        $race = bricks_seo_get_race_row();
        if (!$race) {
            return '';
        }

        $time = '';
        if (!empty($race->scheduled_time)) {
            $time = wp_date('H:i', strtotime((string) $race->scheduled_time));
        }

        return sprintf(
            '%s at %s%s — Fhorsite and speed ratings, Points Engine picks, and full runner analysis for this race card.',
            trim((string) ($race->race_title ?? 'Race')),
            bricks_seo_format_course_name($race->course ?? 'course'),
            $time !== '' ? ' (' . $time . ')' : ''
        );
    }
}

if (!function_exists('bricks_seo_race_start_date_iso')) {
    function bricks_seo_race_start_date_iso($race) {
        if (!$race || empty($race->meeting_date)) {
            return '';
        }
        $date = (string) $race->meeting_date;
        $raw_time = !empty($race->scheduled_time) ? trim((string) $race->scheduled_time) : '';

        if ($raw_time !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw_time)) {
            $ts = strtotime($raw_time);
        } elseif ($raw_time !== '') {
            $ts = strtotime($date . ' ' . $raw_time);
        } else {
            $ts = strtotime($date . ' 12:00:00');
        }
        if ($ts === false) {
            return '';
        }
        return wp_date('c', $ts);
    }
}

if (!function_exists('bricks_seo_is_search_crawler')) {
    function bricks_seo_is_search_crawler() {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower((string) $_SERVER['HTTP_USER_AGENT']) : '';
        if ($ua === '') {
            return false;
        }
        $bots = ['googlebot', 'google-inspectiontool', 'bingbot', 'applebot', 'duckduckbot'];
        foreach ($bots as $bot) {
            if (strpos($ua, $bot) !== false) {
                return true;
            }
        }
        return (bool) apply_filters('bricks_seo_is_search_crawler', false, $ua);
    }
}

if (!function_exists('bricks_race_detail_can_view_premium')) {
    function bricks_race_detail_can_view_premium() {
        if (is_user_logged_in()) {
            return true;
        }
        if (bricks_seo_is_search_crawler()) {
            return true;
        }
        return (bool) apply_filters('bricks_race_detail_can_view_premium', false);
    }
}

if (!function_exists('bricks_seo_output_paywall_json_ld')) {
    function bricks_seo_output_paywall_json_ld() {
        static $done = false;
        if ($done) {
            return;
        }
        if (!bricks_seo_is_race_detail_request()) {
            return;
        }

        $race_id = bricks_seo_resolve_race_id_from_request();
        $race = bricks_seo_get_race_row($race_id);
        if (!$race) {
            return;
        }

        $done = true;

        $race_title = trim((string) ($race->race_title ?? 'Horse race'));
        $course = bricks_seo_format_course_name($race->course ?? '');
        $event_name = $course !== '' ? $course . ' — ' . $race_title : $race_title;

        $url = function_exists('bricks_race_url') ? bricks_race_url($race_id) : home_url('/race/' . $race_id . '/');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => $event_name,
            'url' => $url,
            'isAccessibleForFree' => true,
            'hasPart' => [
                '@type' => 'WebPageElement',
                'isAccessibleForFree' => false,
                'cssSelector' => '.premium-ratings-container',
            ],
        ];

        $start = bricks_seo_race_start_date_iso($race);
        if ($start !== '') {
            $schema['startDate'] = $start;
        }
        if ($course !== '') {
            $schema['location'] = [
                '@type' => 'Place',
                'name' => $course,
            ];
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }
}
add_action('wp_head', 'bricks_seo_output_paywall_json_ld', 5);

if (!function_exists('bricks_seo_filter_meta_title')) {
    function bricks_seo_filter_meta_title($title) {
        if (!bricks_seo_is_race_detail_request()) {
            return $title;
        }
        $built = bricks_seo_build_meta_title();
        return $built !== '' ? $built : $title;
    }
}
add_filter('slim_seo_meta_title', 'bricks_seo_filter_meta_title', 20);

if (!function_exists('bricks_seo_filter_meta_description')) {
    function bricks_seo_filter_meta_description($description) {
        if (!bricks_seo_is_race_detail_request()) {
            return $description;
        }
        $built = bricks_seo_build_meta_description();
        return $built !== '' ? $built : $description;
    }
}
add_filter('slim_seo_meta_description', 'bricks_seo_filter_meta_description', 20);

if (!function_exists('bricks_seo_filter_document_title')) {
    function bricks_seo_filter_document_title($title) {
        if (!bricks_seo_is_race_detail_request()) {
            return $title;
        }
        $built = bricks_seo_build_meta_title();
        return $built !== '' ? $built : $title;
    }
}
add_filter('pre_get_document_title', 'bricks_seo_filter_document_title', 20);

if (!function_exists('bricks_seo_filter_schema_graph')) {
    function bricks_seo_filter_schema_graph($graph) {
        if (!bricks_seo_is_race_detail_request() || !is_array($graph)) {
            return $graph;
        }

        $race_id = bricks_seo_resolve_race_id_from_request();
        $race = bricks_seo_get_race_row($race_id);
        if (!$race) {
            return $graph;
        }

        $url = function_exists('bricks_race_url') ? bricks_race_url($race_id) : home_url('/race/' . $race_id . '/');
        $title = bricks_seo_build_meta_title();
        $description = bricks_seo_build_meta_description();

        foreach ($graph as $index => $node) {
            if (!is_array($node)) {
                continue;
            }
            $type = $node['@type'] ?? '';
            if ($type !== 'WebPage' && $type !== 'CollectionPage') {
                continue;
            }

            $graph[$index]['@type'] = 'WebPage';
            $graph[$index]['url'] = $url;
            $graph[$index]['@id'] = $url . '#webpage';
            if ($title !== '') {
                $graph[$index]['name'] = $title;
            }
            if ($description !== '') {
                $graph[$index]['description'] = $description;
            }
            break;
        }

        return $graph;
    }
}
add_filter('slim_seo_schema_graph', 'bricks_seo_filter_schema_graph', 20);

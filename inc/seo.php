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
            $headline = 'Race card ratings';
        }

        $is_aw = function_exists('bricks_seo_race_is_all_weather')
            ? bricks_seo_race_is_all_weather($race)
            : false;
        $suffix = function_exists('bricks_seo_surface_speed_phrase')
            ? bricks_seo_surface_speed_phrase($is_aw)
            : 'Speed & Fhorsite Ratings';

        return $headline . ' | ' . $suffix;
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

        $is_aw = function_exists('bricks_seo_race_is_all_weather')
            ? bricks_seo_race_is_all_weather($race)
            : false;
        $ratings_phrase = function_exists('bricks_seo_surface_speed_phrase_short')
            ? bricks_seo_surface_speed_phrase_short($is_aw)
            : 'speed ratings';

        $extras = $is_aw
            ? 'AW ratings, draw bias, and Points Engine Nap of the Day-style win pick'
            : 'turf speed ratings, draw bias, and Points Engine Nap of the Day-style win pick';

        return sprintf(
            '%s at %s%s — %s, %s, and full runner analysis for this UK & Irish race card.',
            trim((string) ($race->race_title ?? 'Race')),
            bricks_seo_format_course_name($race->course ?? 'racecourse'),
            $time !== '' ? ' (' . $time . ')' : '',
            $ratings_phrase,
            $extras
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

// -----------------------------------------------------------------------------
// Racecourse guide (/racecourses/{slug}/) SEO
// -----------------------------------------------------------------------------

if (!function_exists('bricks_seo_is_racecourses_directory_request')) {
    function bricks_seo_is_racecourses_directory_request() {
        if (function_exists('bricks_track_is_directory_index_request') && bricks_track_is_directory_index_request()) {
            return 'index';
        }
        if (function_exists('bricks_track_is_region_hub_request') && bricks_track_is_region_hub_request()) {
            return 'region';
        }
        return '';
    }
}

if (!function_exists('bricks_seo_resolve_track_context')) {
    function bricks_seo_resolve_track_context() {
        if (!function_exists('bricks_track_resolve_context')) {
            return null;
        }
        return bricks_track_resolve_context([]);
    }
}

if (!function_exists('bricks_seo_is_track_guide_request')) {
    function bricks_seo_is_track_guide_request() {
        if (bricks_seo_is_racecourses_directory_request() !== '') {
            return true;
        }
        $slug = sanitize_title((string) get_query_var('track_slug'));
        if ($slug !== '') {
            return (bool) bricks_seo_resolve_track_context();
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (preg_match('#/racecourses/([a-z0-9-]+)/?#i', $uri, $m)) {
            $candidate = sanitize_title($m[1]);
            if (function_exists('bricks_track_get_region_definition') && bricks_track_get_region_definition($candidate)) {
                return true;
            }
            return (bool) (function_exists('bricks_track_resolve_slug') ? bricks_track_resolve_slug($candidate) : null);
        }
        if (preg_match('#/tracks/([a-z0-9-]+)/?#i', $uri, $m)) {
            return (bool) (function_exists('bricks_track_resolve_slug') ? bricks_track_resolve_slug(sanitize_title($m[1])) : null);
        }
        return (bool) get_query_var('tracks_index');
    }
}

if (!function_exists('bricks_seo_build_track_meta_title')) {
    function bricks_seo_build_track_meta_title() {
        $directory_type = bricks_seo_is_racecourses_directory_request();
        if ($directory_type === 'index') {
            return 'Racecourses Directory | UK Flat, National Hunt & Irish Racing | Fhorsite';
        }
        if ($directory_type === 'region') {
            $region = sanitize_title((string) get_query_var('racecourses_region'));
            $def = function_exists('bricks_track_get_region_definition')
                ? bricks_track_get_region_definition($region)
                : null;
            if ($def) {
                return sprintf('%s Racecourses Directory | Fhorsite', $def['label']);
            }
        }
        if (get_query_var('tracks_index')) {
            return 'Racecourses Directory | UK Flat, National Hunt & Irish Racing | Fhorsite';
        }

        $context = bricks_seo_resolve_track_context();
        if (!$context) {
            return '';
        }

        $display = $context['display'];
        $is_aw = function_exists('bricks_seo_course_is_all_weather')
            ? bricks_seo_course_is_all_weather($context['course'])
            : false;

        if ($is_aw) {
            return sprintf(
                '%s Racecourse Guide — All-Weather Speed Figures, AW Ratings & Today\'s Card | Fhorsite',
                $display
            );
        }

        return sprintf(
            '%s Racecourse Guide — Turf Speed Ratings, Draw Bias & Today\'s Card | Fhorsite',
            $display
        );
    }
}

if (!function_exists('bricks_seo_build_track_meta_description')) {
    function bricks_seo_build_track_meta_description() {
        $directory_type = bricks_seo_is_racecourses_directory_request();
        if ($directory_type === 'index') {
            return 'Structured racecourses directory for UK Flat & All-Weather, UK National Hunt, and Irish racing — speed ratings, AW figures, draw bias, and live race cards.';
        }
        if ($directory_type === 'region') {
            $region = sanitize_title((string) get_query_var('racecourses_region'));
            $def = function_exists('bricks_track_get_region_definition')
                ? bricks_track_get_region_definition($region)
                : null;
            if ($def) {
                return $def['description'];
            }
        }
        if (get_query_var('tracks_index')) {
            return 'Structured racecourses directory for UK Flat & All-Weather, UK National Hunt, and Irish racing — speed ratings, AW figures, draw bias, and live race cards.';
        }

        $context = bricks_seo_resolve_track_context();
        if (!$context) {
            return '';
        }

        $display = $context['display'];
        $has_today = function_exists('bricks_track_has_meeting_today') && bricks_track_has_meeting_today($context['course']);
        $is_aw = function_exists('bricks_seo_course_is_all_weather')
            ? bricks_seo_course_is_all_weather($context['course'])
            : false;

        $ratings_phrase = $is_aw
            ? 'All-Weather speed figures and AW ratings'
            : 'turf speed ratings and draw bias';

        return sprintf(
            '%s racecourse guide — %s, racecourse specs, straight-course notes, and Fhorsite Points Engine winners at %s%s.',
            $display,
            $ratings_phrase,
            $display,
            $has_today ? ', plus today\'s live UK & Irish race card' : ''
        );
    }
}

if (!function_exists('bricks_seo_output_track_json_ld')) {
    function bricks_seo_output_track_json_ld() {
        static $done = false;
        if ($done || !bricks_seo_is_track_guide_request()) {
            return;
        }
        $done = true;

        $directory_type = bricks_seo_is_racecourses_directory_request();
        $schemas = [];

        if ($directory_type === 'index') {
            $url = function_exists('bricks_track_directory_url') ? bricks_track_directory_url() : home_url('/racecourses/');
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                '@id' => $url . '#collection',
                'name' => 'Racecourses Directory',
                'description' => bricks_seo_build_track_meta_description(),
                'url' => $url,
            ];
            if (function_exists('bricks_track_region_definitions')) {
                $items = [];
                $pos = 1;
                foreach (bricks_track_region_definitions() as $region_key => $def) {
                    $items[] = [
                        '@type' => 'ListItem',
                        'position' => $pos++,
                        'name' => $def['label'],
                        'url' => function_exists('bricks_track_region_url')
                            ? bricks_track_region_url($region_key)
                            : home_url('/racecourses/' . $region_key . '/'),
                    ];
                }
                $schemas[0]['mainEntity'] = [
                    '@type' => 'ItemList',
                    'name' => 'Racecourses by region',
                    'itemListElement' => $items,
                ];
            }
            bricks_seo_print_json_ld($schemas);
            return;
        }

        if ($directory_type === 'region') {
            $region = sanitize_title((string) get_query_var('racecourses_region'));
            $def = function_exists('bricks_track_get_region_definition')
                ? bricks_track_get_region_definition($region)
                : null;
            if (!$def) {
                return;
            }
            $url = function_exists('bricks_track_region_url')
                ? bricks_track_region_url($region)
                : home_url('/racecourses/' . $region . '/');
            $dir_url = function_exists('bricks_track_directory_url') ? bricks_track_directory_url() : home_url('/racecourses/');

            bricks_seo_print_json_ld([
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    '@id' => $url . '#collection',
                    'name' => $def['label'] . ' racecourses',
                    'description' => $def['description'],
                    'url' => $url,
                    'isPartOf' => [
                        '@type' => 'CollectionPage',
                        '@id' => $dir_url . '#collection',
                        'name' => 'Racecourses Directory',
                        'url' => $dir_url,
                    ],
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Racecourses directory',
                            'item' => $dir_url,
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => $def['label'],
                            'item' => $url,
                        ],
                    ],
                ],
            ]);
            return;
        }

        $context = bricks_seo_resolve_track_context();
        if (!$context) {
            return;
        }

        $url = function_exists('bricks_track_url') ? bricks_track_url($context['slug']) : home_url('/racecourses/' . $context['slug'] . '/');
        $dir_url = function_exists('bricks_track_directory_url') ? bricks_track_directory_url() : home_url('/racecourses/');
        $region = function_exists('bricks_track_infer_course_region')
            ? bricks_track_infer_course_region($context['course'], $context['country'] ?? '')
            : '';
        $region_def = function_exists('bricks_track_get_region_definition')
            ? bricks_track_get_region_definition($region)
            : null;
        $region_url = $region_def && function_exists('bricks_track_region_url')
            ? bricks_track_region_url($region)
            : '';

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsActivityLocation',
            'name' => $context['display'],
            'url' => $url,
            'description' => bricks_seo_build_track_meta_description(),
        ];

        if (!empty($context['country'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'addressCountry' => $context['country'],
            ];
        }

        $breadcrumbs = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Racecourses directory',
                'item' => $dir_url,
            ],
        ];
        if ($region_url !== '' && $region_def) {
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $region_def['label'],
                'item' => $region_url,
            ];
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $context['display'],
                'item' => $url,
            ];
        } else {
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $context['display'],
                'item' => $url,
            ];
        }

        bricks_seo_print_json_ld([
            $schema,
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $breadcrumbs,
            ],
        ]);
    }
}
add_action('wp_head', 'bricks_seo_output_track_json_ld', 6);

if (!function_exists('bricks_seo_filter_track_meta_title')) {
    function bricks_seo_filter_track_meta_title($title) {
        if (!bricks_seo_is_track_guide_request()) {
            return $title;
        }
        $built = bricks_seo_build_track_meta_title();
        return $built !== '' ? $built : $title;
    }
}
add_filter('slim_seo_meta_title', 'bricks_seo_filter_track_meta_title', 25);

if (!function_exists('bricks_seo_filter_track_meta_description')) {
    function bricks_seo_filter_track_meta_description($description) {
        if (!bricks_seo_is_track_guide_request()) {
            return $description;
        }
        $built = bricks_seo_build_track_meta_description();
        return $built !== '' ? $built : $description;
    }
}
add_filter('slim_seo_meta_description', 'bricks_seo_filter_track_meta_description', 25);

if (!function_exists('bricks_seo_filter_track_document_title')) {
    function bricks_seo_filter_track_document_title($title) {
        if (!bricks_seo_is_track_guide_request()) {
            return $title;
        }
        $built = bricks_seo_build_track_meta_title();
        return $built !== '' ? $built : $title;
    }
}
add_filter('pre_get_document_title', 'bricks_seo_filter_track_document_title', 25);

// -----------------------------------------------------------------------------
// Proven Winners archive SEO
// -----------------------------------------------------------------------------

if (!function_exists('bricks_seo_is_proven_winners_request')) {
    function bricks_seo_is_proven_winners_request() {
        return function_exists('bricks_proven_winners_is_request') && bricks_proven_winners_is_request();
    }
}

if (!function_exists('bricks_seo_build_proven_winners_meta_title')) {
    function bricks_seo_build_proven_winners_meta_title() {
        return 'Proven Winners Archive | UK & Irish Nap of the Day Case Studies | Fhorsite';
    }
}

if (!function_exists('bricks_seo_build_proven_winners_meta_description')) {
    function bricks_seo_build_proven_winners_meta_description() {
        return 'Permanent UK and Irish archive of Fhorsite Points Engine winners with published Nap of the Day-style win picks, settlement odds, and ROI across Win, Place, and Each-Way strategies.';
    }
}

if (!function_exists('bricks_seo_output_proven_winners_json_ld')) {
    function bricks_seo_output_proven_winners_json_ld() {
        static $done = false;
        if ($done || !bricks_seo_is_proven_winners_request()) {
            return;
        }
        $done = true;

        $url = function_exists('bricks_proven_winners_url') ? bricks_proven_winners_url() : home_url('/proven-winners/');

        $list_items = [];
        if (function_exists('bricks_proven_winners_get_cases')) {
            foreach (bricks_proven_winners_get_cases(24, 0.0) as $case) {
                $horse = trim((string) ($case['horse'] ?? ''));
                if ($horse === '') {
                    continue;
                }
                $item_url = !empty($case['race_url']) ? (string) $case['race_url'] : $url;
                $name = $horse;
                if (!empty($case['sp'])) {
                    $name .= ' (' . $case['sp'] . ')';
                }
                $list_items[] = [
                    '@type' => 'ListItem',
                    'position' => count($list_items) + 1,
                    'name' => $name,
                    'url' => $item_url,
                ];
            }
        }

        $collection = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            '@id' => $url . '#collection',
            'name' => 'Proven Winners Archive',
            'description' => bricks_seo_build_proven_winners_meta_description(),
            'url' => $url,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
            ],
        ];

        if (!empty($list_items)) {
            $collection['mainEntity'] = [
                '@type' => 'ItemList',
                '@id' => $url . '#itemlist',
                'name' => 'Points Engine proven winners',
                'numberOfItems' => count($list_items),
                'itemListElement' => $list_items,
            ];
        }

        bricks_seo_print_json_ld($collection);
    }
}
add_action('wp_head', 'bricks_seo_output_proven_winners_json_ld', 6);

add_filter('slim_seo_meta_title', function ($title) {
    if (!bricks_seo_is_proven_winners_request()) {
        return $title;
    }
    return bricks_seo_build_proven_winners_meta_title();
}, 26);

add_filter('slim_seo_meta_description', function ($description) {
    if (!bricks_seo_is_proven_winners_request()) {
        return $description;
    }
    return bricks_seo_build_proven_winners_meta_description();
}, 26);

add_filter('pre_get_document_title', function ($title) {
    if (!bricks_seo_is_proven_winners_request()) {
        return $title;
    }
    return bricks_seo_build_proven_winners_meta_title();
}, 26);

// -----------------------------------------------------------------------------
// Dataset + Article structured data (rich snippets)
// -----------------------------------------------------------------------------

if (!function_exists('bricks_seo_print_json_ld')) {
    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $schema
     */
    function bricks_seo_print_json_ld($schema) {
        if (empty($schema)) {
            return;
        }
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }
}

if (!function_exists('bricks_seo_get_organization_schema')) {
    function bricks_seo_get_organization_schema() {
        return [
            '@type' => 'Organization',
            '@id' => home_url('/') . '#organization',
            'name' => get_bloginfo('name'),
            'url' => home_url('/'),
        ];
    }
}

if (!function_exists('bricks_seo_get_race_runner_count')) {
    function bricks_seo_get_race_runner_count($race_id, $meeting_date = '') {
        global $wpdb;

        $race_id = intval($race_id);
        if ($race_id <= 0) {
            return 0;
        }

        $tomorrow = wp_date('Y-m-d', strtotime('+1 day', current_time('timestamp')));
        $meeting_date = (string) $meeting_date;
        $runners_table = ($meeting_date === $tomorrow) ? 'advance_daily_runners' : 'advance_daily_runners_beta';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $runners_table)) !== $runners_table) {
            return 0;
        }

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$runners_table` WHERE race_id = %d",
            $race_id
        )));
    }
}

if (!function_exists('bricks_seo_rating_variables_measured')) {
    /**
     * @return array<int, array<string, string>>
     */
    function bricks_seo_rating_variables_measured() {
        return [
            ['@type' => 'PropertyValue', 'name' => 'Fhorsite Rating', 'unitText' => 'index', 'description' => 'Composite Fhorsite speed rating (FSr) for UK & Irish racing'],
            ['@type' => 'PropertyValue', 'name' => 'Speed Rating', 'unitText' => 'index', 'description' => 'Adjusted turf or All-Weather speed figure'],
            ['@type' => 'PropertyValue', 'name' => 'Draw Bias', 'unitText' => 'percent', 'description' => 'Historic stall win share at course/distance'],
            ['@type' => 'PropertyValue', 'name' => 'Points Engine Score', 'unitText' => 'index', 'description' => 'Model points score for win/place/EW picks'],
            ['@type' => 'PropertyValue', 'name' => 'Trainer RTF', 'unitText' => 'percent', 'description' => 'Trainer run-to-form percentage'],
            ['@type' => 'PropertyValue', 'name' => 'Rivals Beaten', 'unitText' => 'percent', 'description' => 'Percentage of rivals beaten by trainer'],
        ];
    }
}

if (!function_exists('bricks_seo_build_race_dataset_schema')) {
    function bricks_seo_build_race_dataset_schema($race_id = 0, $race = null) {
        if (!$race) {
            $race = bricks_seo_get_race_row($race_id);
        }
        if (!$race) {
            return null;
        }

        $race_id = intval($race->race_id ?? $race_id);
        $race_title = trim((string) ($race->race_title ?? 'Race'));
        $course = bricks_seo_format_course_name($race->course ?? '');
        $meeting_date = (string) ($race->meeting_date ?? '');
        $url = function_exists('bricks_race_url') ? bricks_race_url($race_id) : home_url('/race/' . $race_id . '/');
        $runner_count = bricks_seo_get_race_runner_count($race_id, $meeting_date);

        $name = $course !== ''
            ? sprintf('Fhorsite ratings dataset — %s at %s', $race_title, $course)
            : sprintf('Fhorsite ratings dataset — %s', $race_title);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Dataset',
            '@id' => $url . '#dataset',
            'name' => $name,
            'description' => bricks_seo_build_meta_description(),
            'url' => $url,
            'creator' => bricks_seo_get_organization_schema(),
            'publisher' => bricks_seo_get_organization_schema(),
            'isAccessibleForFree' => true,
            'keywords' => array_values(array_filter([
                'UK horse racing',
                'Irish horse racing',
                'speed ratings',
                'speed figures',
                'All-Weather speed figures',
                'AW ratings',
                $course,
                $race->race_type ?? '',
            ])),
            'variableMeasured' => bricks_seo_rating_variables_measured(),
            'distribution' => [
                '@type' => 'DataDownload',
                'encodingFormat' => 'text/html',
                'contentUrl' => $url,
            ],
        ];

        if ($meeting_date !== '') {
            $schema['temporalCoverage'] = $meeting_date;
            $schema['dateModified'] = wp_date('c', strtotime($meeting_date . ' 12:00:00'));
        }
        if ($course !== '') {
            $schema['spatialCoverage'] = [
                '@type' => 'Place',
                'name' => $course,
            ];
        }
        if ($runner_count > 0) {
            $schema['size'] = (string) $runner_count . ' runners';
        }

        return $schema;
    }
}

if (!function_exists('bricks_seo_is_ratings_dashboard_request')) {
    function bricks_seo_is_ratings_dashboard_request() {
        if (bricks_seo_is_race_detail_request()) {
            return 'race';
        }
        if (function_exists('bricks_seo_is_track_guide_request') && bricks_seo_is_track_guide_request()) {
            if (get_query_var('racecourses_index') || get_query_var('tracks_index')) {
                return 'tracks_index';
            }
            if (get_query_var('racecourses_region')) {
                return 'tracks_region';
            }
            return 'track';
        }
        if (function_exists('bricks_request_uri_contains')) {
            if (bricks_request_uri_contains(['/daily'])) {
                return 'daily';
            }
            if (bricks_request_uri_contains(['/speed'])) {
                return 'speed';
            }
        }
        if (function_exists('bricks_proven_winners_is_request') && bricks_proven_winners_is_request()) {
            return 'proven_winners';
        }
        return '';
    }
}

if (!function_exists('bricks_seo_build_dashboard_dataset_schema')) {
    function bricks_seo_build_dashboard_dataset_schema($dashboard_type) {
        $org = bricks_seo_get_organization_schema();
        $variables = bricks_seo_rating_variables_measured();

        switch ($dashboard_type) {
            case 'daily':
                $url = home_url('/daily/');
                return [
                    '@context' => 'https://schema.org',
                    '@type' => 'Dataset',
                    '@id' => $url . '#dataset',
                    'name' => 'UK & Irish race cards — turf & All-Weather ratings dashboard',
                    'description' => function_exists('bricks_seo_build_daily_meta_description')
                        ? bricks_seo_build_daily_meta_description()
                        : 'Live UK and Irish race card table with course, class, runners, and Fhorsite race ratings for today and tomorrow.',
                    'url' => $url,
                    'creator' => $org,
                    'publisher' => $org,
                    'temporalCoverage' => wp_date('Y-m-d', current_time('timestamp')) . '/' . wp_date('Y-m-d', strtotime('+1 day', current_time('timestamp'))),
                    'variableMeasured' => $variables,
                    'distribution' => [
                        '@type' => 'DataDownload',
                        'encodingFormat' => 'text/html',
                        'contentUrl' => $url,
                    ],
                ];

            case 'speed':
                $url = home_url('/speed/');
                return [
                    '@context' => 'https://schema.org',
                    '@type' => 'Dataset',
                    '@id' => $url . '#dataset',
                    'name' => 'UK & Irish speed figures & All-Weather AW ratings dashboard',
                    'description' => function_exists('bricks_seo_build_speed_meta_description')
                        ? bricks_seo_build_speed_meta_description()
                        : 'Searchable UK and Irish speed figures and AW ratings across meetings, racecourses, and race types.',
                    'url' => $url,
                    'creator' => $org,
                    'publisher' => $org,
                    'variableMeasured' => $variables,
                    'distribution' => [
                        '@type' => 'DataDownload',
                        'encodingFormat' => 'text/html',
                        'contentUrl' => $url,
                    ],
                ];

            case 'tracks_index':
                $url = function_exists('bricks_track_directory_url') ? bricks_track_directory_url() : home_url('/racecourses/');
                return [
                    '@context' => 'https://schema.org',
                    '@type' => 'Dataset',
                    '@id' => $url . '#dataset',
                    'name' => 'Racecourses directory — UK Flat, National Hunt & Irish racing',
                    'description' => bricks_seo_build_track_meta_description(),
                    'url' => $url,
                    'creator' => $org,
                    'publisher' => $org,
                    'variableMeasured' => [
                        ['@type' => 'PropertyValue', 'name' => 'Draw Bias', 'unitText' => 'percent'],
                        ['@type' => 'PropertyValue', 'name' => 'Straight Track Length', 'unitText' => 'distance'],
                        ['@type' => 'PropertyValue', 'name' => 'Fhorsite Rating', 'unitText' => 'index'],
                    ],
                    'distribution' => [
                        '@type' => 'DataDownload',
                        'encodingFormat' => 'text/html',
                        'contentUrl' => $url,
                    ],
                ];

            case 'tracks_region':
                $region = sanitize_title((string) get_query_var('racecourses_region'));
                $def = function_exists('bricks_track_get_region_definition')
                    ? bricks_track_get_region_definition($region)
                    : null;
                if (!$def) {
                    return null;
                }
                $url = function_exists('bricks_track_region_url')
                    ? bricks_track_region_url($region)
                    : home_url('/racecourses/' . $region . '/');
                return [
                    '@context' => 'https://schema.org',
                    '@type' => 'Dataset',
                    '@id' => $url . '#dataset',
                    'name' => $def['label'] . ' racecourses directory',
                    'description' => $def['description'],
                    'url' => $url,
                    'creator' => $org,
                    'publisher' => $org,
                    'isPartOf' => function_exists('bricks_track_directory_url')
                        ? bricks_track_directory_url() . '#dataset'
                        : home_url('/racecourses/') . '#dataset',
                    'distribution' => [
                        '@type' => 'DataDownload',
                        'encodingFormat' => 'text/html',
                        'contentUrl' => $url,
                    ],
                ];

            case 'track':
                $context = bricks_seo_resolve_track_context();
                if (!$context) {
                    return null;
                }
                $url = function_exists('bricks_track_url') ? bricks_track_url($context['slug']) : home_url('/tracks/' . $context['slug'] . '/');
                $is_aw = function_exists('bricks_seo_course_is_all_weather')
                    ? bricks_seo_course_is_all_weather($context['course'])
                    : false;
                $dataset_name = $is_aw
                    ? $context['display'] . ' All-Weather speed figures & AW ratings dataset'
                    : $context['display'] . ' turf speed ratings & draw bias dataset';
                return [
                    '@context' => 'https://schema.org',
                    '@type' => 'Dataset',
                    '@id' => $url . '#dataset',
                    'name' => $dataset_name,
                    'description' => bricks_seo_build_track_meta_description(),
                    'url' => $url,
                    'creator' => $org,
                    'publisher' => $org,
                    'keywords' => $is_aw
                        ? ['All-Weather speed figures', 'AW ratings', 'draw bias', 'UK horse racing']
                        : ['turf speed ratings', 'draw bias', 'racecourse guide', 'UK horse racing'],
                    'spatialCoverage' => [
                        '@type' => 'Place',
                        'name' => $context['display'],
                    ],
                    'variableMeasured' => [
                        ['@type' => 'PropertyValue', 'name' => 'Draw Bias', 'unitText' => 'percent'],
                        ['@type' => 'PropertyValue', 'name' => 'Fhorsite Rating', 'unitText' => 'index'],
                        ['@type' => 'PropertyValue', 'name' => 'Speed Rating', 'unitText' => 'index'],
                    ],
                    'distribution' => [
                        '@type' => 'DataDownload',
                        'encodingFormat' => 'text/html',
                        'contentUrl' => $url,
                    ],
                ];

            case 'proven_winners':
                $url = function_exists('bricks_proven_winners_url') ? bricks_proven_winners_url() : home_url('/proven-winners/');
                return [
                    '@context' => 'https://schema.org',
                    '@type' => 'Dataset',
                    '@id' => $url . '#dataset',
                    'name' => 'Fhorsite Proven Winners — UK & Irish Nap of the Day case study archive',
                    'description' => function_exists('bricks_seo_build_proven_winners_meta_description')
                        ? bricks_seo_build_proven_winners_meta_description()
                        : 'Archive of published Points Engine win picks that won, with settlement odds and ROI by strategy.',
                    'url' => $url,
                    'creator' => $org,
                    'publisher' => $org,
                    'keywords' => ['Nap of the Day', 'UK horse racing', 'Irish horse racing', 'Points Engine', 'speed ratings'],
                    'variableMeasured' => [
                        ['@type' => 'PropertyValue', 'name' => 'Win pick ROI', 'unitText' => 'points'],
                        ['@type' => 'PropertyValue', 'name' => 'Place ROI', 'unitText' => 'points'],
                        ['@type' => 'PropertyValue', 'name' => 'EW Simple ROI', 'unitText' => 'points'],
                        ['@type' => 'PropertyValue', 'name' => 'EW Edge ROI', 'unitText' => 'points'],
                    ],
                    'distribution' => [
                        '@type' => 'DataDownload',
                        'encodingFormat' => 'text/html',
                        'contentUrl' => $url,
                    ],
                ];

            default:
                return null;
        }
    }
}

if (!function_exists('bricks_seo_output_dataset_json_ld')) {
    function bricks_seo_output_dataset_json_ld() {
        static $done = false;
        if ($done) {
            return;
        }

        $dashboard_type = bricks_seo_is_ratings_dashboard_request();
        if ($dashboard_type === '') {
            return;
        }

        $schema = null;
        if ($dashboard_type === 'race') {
            $schema = bricks_seo_build_race_dataset_schema();
        } else {
            $schema = bricks_seo_build_dashboard_dataset_schema($dashboard_type);
        }

        if (!$schema) {
            return;
        }

        $done = true;
        bricks_seo_print_json_ld($schema);
    }
}
add_action('wp_head', 'bricks_seo_output_dataset_json_ld', 8);

if (!function_exists('bricks_seo_post_is_case_study')) {
    function bricks_seo_post_is_case_study($post_id = 0) {
        $post_id = $post_id > 0 ? $post_id : get_the_ID();
        if ($post_id <= 0) {
            return false;
        }

        $slugs = (array) apply_filters('bricks_seo_case_study_category_slugs', [
            'case-study',
            'case-studies',
            'case_study',
        ]);

        foreach ($slugs as $slug) {
            if (has_category($slug, $post_id)) {
                return true;
            }
        }

        return (bool) get_post_meta($post_id, 'bricks_is_case_study', true);
    }
}

if (!function_exists('bricks_seo_enhance_article_schema_graph')) {
    /**
     * Strengthen Article / BlogPosting nodes from Slim SEO for blogs and case studies.
     */
    function bricks_seo_enhance_article_schema_graph($graph) {
        if (!is_singular(['post', 'page']) || !is_array($graph)) {
            return $graph;
        }

        $post_id = get_the_ID();
        if ($post_id <= 0) {
            return $graph;
        }

        $post = get_post($post_id);
        if (!$post) {
            return $graph;
        }

        $is_case_study = bricks_seo_post_is_case_study($post_id);
        $categories = get_the_category($post_id);
        $section_names = [];
        foreach ((array) $categories as $cat) {
            if (!empty($cat->name)) {
                $section_names[] = $cat->name;
            }
        }

        $author_name = get_the_author_meta('display_name', (int) $post->post_author);
        $featured_url = get_the_post_thumbnail_url($post_id, 'full');
        $permalink = get_permalink($post_id);

        $article_types = ['Article', 'NewsArticle', 'BlogPosting', 'ScholarlyArticle'];
        $found = false;

        foreach ($graph as $index => $node) {
            if (!is_array($node)) {
                continue;
            }

            $type = $node['@type'] ?? '';
            $types = is_array($type) ? $type : [$type];
            $is_article = (bool) array_intersect($article_types, $types);
            if (!$is_article) {
                continue;
            }

            $found = true;

            if ($is_case_study) {
                $graph[$index]['@type'] = 'Article';
                $graph[$index]['genre'] = 'Case study';
            } elseif (empty($types[0]) || $types[0] === 'WebPage') {
                $graph[$index]['@type'] = 'BlogPosting';
            }

            if ($permalink) {
                $graph[$index]['url'] = $permalink;
                $graph[$index]['mainEntityOfPage'] = $permalink;
            }

            if (!empty($section_names)) {
                $graph[$index]['articleSection'] = implode(', ', $section_names);
            }

            $graph[$index]['about'] = function_exists('bricks_seo_regional_article_about_terms')
                ? bricks_seo_regional_article_about_terms($post_id)
                : [
                    ['@type' => 'Thing', 'name' => 'UK horse racing'],
                    ['@type' => 'Thing', 'name' => 'Irish horse racing'],
                    ['@type' => 'Thing', 'name' => 'Speed ratings'],
                ];

            if ($is_case_study) {
                $graph[$index]['about'][] = ['@type' => 'Thing', 'name' => 'Betting analysis'];
            }

            if (function_exists('bricks_seo_regional_keywords')) {
                $graph[$index]['keywords'] = implode(', ', array_slice(bricks_seo_regional_keywords(), 0, 8));
            }

            if ($author_name !== '' && empty($graph[$index]['author'])) {
                $graph[$index]['author'] = [
                    '@type' => 'Person',
                    'name' => $author_name,
                ];
            }

            if ($featured_url && empty($graph[$index]['image'])) {
                $graph[$index]['image'] = [$featured_url];
            }

            if (empty($graph[$index]['publisher'])) {
                $graph[$index]['publisher'] = bricks_seo_get_organization_schema();
            }

            if (empty($graph[$index]['datePublished']) && !empty($post->post_date_gmt)) {
                $graph[$index]['datePublished'] = mysql2date('c', $post->post_date_gmt, false);
            }
            if (empty($graph[$index]['dateModified']) && !empty($post->post_modified_gmt)) {
                $graph[$index]['dateModified'] = mysql2date('c', $post->post_modified_gmt, false);
            }

            if ($is_case_study && empty($graph[$index]['headline'])) {
                $graph[$index]['headline'] = get_the_title($post_id);
            }
        }

        // Slim SEO may not emit Article on some templates — add one if missing for posts.
        if (!$found && $post->post_type === 'post' && $permalink) {
            $article = [
                '@type' => $is_case_study ? 'Article' : 'BlogPosting',
                '@id' => $permalink . '#article',
                'headline' => get_the_title($post_id),
                'description' => has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(wp_strip_all_tags($post->post_content), 40),
                'url' => $permalink,
                'mainEntityOfPage' => $permalink,
                'datePublished' => mysql2date('c', $post->post_date_gmt, false),
                'dateModified' => mysql2date('c', $post->post_modified_gmt, false),
                'author' => [
                    '@type' => 'Person',
                    'name' => $author_name !== '' ? $author_name : get_bloginfo('name'),
                ],
                'publisher' => bricks_seo_get_organization_schema(),
                'about' => function_exists('bricks_seo_regional_article_about_terms')
                    ? bricks_seo_regional_article_about_terms($post_id)
                    : [
                        ['@type' => 'Thing', 'name' => 'UK horse racing'],
                        ['@type' => 'Thing', 'name' => 'Irish horse racing'],
                    ],
            ];
            if ($featured_url) {
                $article['image'] = [$featured_url];
            }
            if (!empty($section_names)) {
                $article['articleSection'] = implode(', ', $section_names);
            }
            if ($is_case_study) {
                $article['genre'] = 'Case study';
            }
            $graph[] = $article;
        }

        return $graph;
    }
}
add_filter('slim_seo_schema_graph', 'bricks_seo_enhance_article_schema_graph', 35);

if (!function_exists('bricks_seo_output_fallback_article_json_ld')) {
    /**
     * Fallback Article JSON-LD when Slim SEO graph filter is unavailable.
     */
    function bricks_seo_output_fallback_article_json_ld() {
        if (!is_singular('post') || class_exists('SlimSEO\\Container')) {
            return;
        }

        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $graph = bricks_seo_enhance_article_schema_graph([]);
        if (empty($graph)) {
            return;
        }

        bricks_seo_print_json_ld([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ]);
    }
}
add_action('wp_head', 'bricks_seo_output_fallback_article_json_ld', 12);


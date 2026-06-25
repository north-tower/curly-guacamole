<?php
/**
 * Permanent racecourse guide landing pages (/racecourses/{slug}/).
 *
 * Structured directory with regional hubs:
 *   /racecourses/uk-flat/   — UK Flat & All-Weather
 *   /racecourses/uk-jumps/  — UK National Hunt
 *   /racecourses/ireland/   — Irish Racing
 *
 * Legacy /tracks/* URLs 301 to /racecourses/*.
 */

if (!function_exists('bricks_track_format_display_name')) {
    function bricks_track_format_display_name($course) {
        $course = trim((string) $course);
        if ($course === '') {
            return '';
        }
        $course = str_replace('_', ' ', $course);
        $course = preg_replace('/\s+/', ' ', $course);
        return $course;
    }
}

if (!function_exists('bricks_track_course_to_slug')) {
    function bricks_track_course_to_slug($course) {
        $label = bricks_track_format_display_name($course);
        if ($label === '') {
            return '';
        }
        return sanitize_title($label);
    }
}

if (!function_exists('bricks_track_slug_aliases')) {
    /**
     * Optional SEO-friendly slug overrides (slug => DB course name).
     */
    function bricks_track_slug_aliases() {
        return (array) apply_filters('bricks_track_slug_aliases', [
            'wolverhampton-aw' => 'Wolverhampton (AW)',
            'wolverhampton' => 'Wolverhampton',
            'chester' => 'Chester',
            'newmarket' => 'Newmarket',
            'ascot' => 'Ascot',
            'york' => 'York',
            'goodwood' => 'Goodwood',
            'kempton' => 'Kempton',
            'lingfield' => 'Lingfield',
            'southwell' => 'Southwell',
            'newcastle' => 'Newcastle',
            'doncaster' => 'Doncaster',
            'haydock' => 'Haydock',
            'epsom' => 'Epsom',
            'sandown' => 'Sandown',
            'curragh' => 'Curragh',
            'leopardstown' => 'Leopardstown',
            'punchestown' => 'Punchestown',
            'galway' => 'Galway',
            'cheltenham' => 'Cheltenham',
            'aintree' => 'Aintree',
        ]);
    }
}

if (!function_exists('bricks_track_directory_base')) {
    function bricks_track_directory_base() {
        return 'racecourses';
    }
}

if (!function_exists('bricks_track_region_definitions')) {
    /**
     * Regional hub slugs for the racecourses directory.
     *
     * @return array<string, array{slug:string,label:string,description:string}>
     */
    function bricks_track_region_definitions() {
        return (array) apply_filters('bricks_track_region_definitions', [
            'uk-flat' => [
                'slug' => 'uk-flat',
                'label' => 'UK Flat & All-Weather',
                'description' => 'UK turf and All-Weather (AW) racecourses — flat speed ratings, AW speed figures, and draw bias guides.',
            ],
            'uk-jumps' => [
                'slug' => 'uk-jumps',
                'label' => 'UK National Hunt',
                'description' => 'UK jumps racecourses — Cheltenham, Aintree, and National Hunt venues with course specs and Fhorsite ratings.',
            ],
            'ireland' => [
                'slug' => 'ireland',
                'label' => 'Irish Racing',
                'description' => 'Irish racecourses — Leopardstown, Punchestown, Curragh, Galway, and full Irish racing guides.',
            ],
        ]);
    }
}

if (!function_exists('bricks_track_reserved_region_slugs')) {
    function bricks_track_reserved_region_slugs() {
        return array_keys(bricks_track_region_definitions());
    }
}

if (!function_exists('bricks_track_course_region_overrides')) {
    /**
     * Manual primary region when a course hosts mixed disciplines.
     *
     * @return array<string, string>
     */
    function bricks_track_course_region_overrides() {
        return (array) apply_filters('bricks_track_course_region_overrides', [
            'Cheltenham' => 'uk-jumps',
            'Aintree' => 'uk-jumps',
            'Cartmel' => 'uk-jumps',
            'Warwick' => 'uk-jumps',
            'Wincanton' => 'uk-jumps',
            'Stratford' => 'uk-jumps',
            'Huntingdon' => 'uk-jumps',
            'Ludlow' => 'uk-jumps',
            'Market Rasen' => 'uk-jumps',
            'Newton Abbot' => 'uk-jumps',
            'Plumpton' => 'uk-jumps',
            'Fontwell' => 'uk-jumps',
            'Kelso' => 'uk-jumps',
            'Sedgefield' => 'uk-jumps',
            'Taunton' => 'uk-jumps',
            'Uttoxeter' => 'uk-jumps',
            'Fakenham' => 'uk-jumps',
            'Hereford' => 'uk-jumps',
            'Bangor-on-Dee' => 'uk-jumps',
            'Hexham' => 'uk-jumps',
            'Perth' => 'uk-jumps',
            'Wolverhampton' => 'uk-flat',
            'Wolverhampton (AW)' => 'uk-flat',
            'Kempton' => 'uk-flat',
            'Lingfield' => 'uk-flat',
            'Southwell' => 'uk-flat',
            'Chelmsford' => 'uk-flat',
            'Newcastle' => 'uk-flat',
            'Leopardstown' => 'ireland',
            'Punchestown' => 'ireland',
            'Curragh' => 'ireland',
            'Galway' => 'ireland',
            'Fairyhouse' => 'ireland',
            'Navan' => 'ireland',
            'Naas' => 'ireland',
            'Gowran Park' => 'ireland',
            'Limerick' => 'ireland',
            'Killarney' => 'ireland',
            'Downpatrick' => 'ireland',
            'Down Royal' => 'ireland',
            'Roscommon' => 'ireland',
            'Sligo' => 'ireland',
            'Tipperary' => 'ireland',
            'Tramore' => 'ireland',
            'Wexford' => 'ireland',
        ]);
    }
}

if (!function_exists('bricks_track_infer_course_region')) {
    /**
     * Primary directory region for a racecourse.
     */
    function bricks_track_infer_course_region($course, $country = '') {
        $course = trim((string) $course);
        $overrides = bricks_track_course_region_overrides();
        if ($course !== '' && isset($overrides[$course])) {
            return $overrides[$course];
        }

        $bucket = bricks_track_normalize_country_bucket($country);
        if ($bucket === 'ireland') {
            return 'ireland';
        }

        $has_jumps = false;
        $has_flat = false;
        $has_aw = false;

        foreach (bricks_track_get_features_rows($course) as $row) {
            $race_type = strtolower((string) ($row->race_type ?? ''));
            $track_type = (string) ($row->track_type ?? '');

            if (
                strpos($race_type, 'hurdle') !== false
                || strpos($race_type, 'chase') !== false
                || strpos($race_type, 'national hunt') !== false
                || strpos($race_type, 'n_h') !== false
                || $race_type === 'nh flat'
            ) {
                $has_jumps = true;
            }
            if (strpos($race_type, 'flat') !== false) {
                $has_flat = true;
            }
            if (function_exists('bricks_seo_is_all_weather_surface') && bricks_seo_is_all_weather_surface($track_type)) {
                $has_aw = true;
            }
        }

        if ($has_jumps && !$has_flat && !$has_aw) {
            return 'uk-jumps';
        }
        if ($has_flat || $has_aw) {
            return 'uk-flat';
        }
        if ($has_jumps) {
            return 'uk-jumps';
        }

        return 'uk-flat';
    }
}

if (!function_exists('bricks_track_get_region_definition')) {
    function bricks_track_get_region_definition($region_slug) {
        $region_slug = sanitize_title((string) $region_slug);
        $defs = bricks_track_region_definitions();
        return $defs[$region_slug] ?? null;
    }
}

if (!function_exists('bricks_track_directory_url')) {
    function bricks_track_directory_url() {
        return home_url('/' . bricks_track_directory_base() . '/');
    }
}

if (!function_exists('bricks_track_region_url')) {
    function bricks_track_region_url($region_slug) {
        $region_slug = sanitize_title((string) $region_slug);
        if ($region_slug === '' || !bricks_track_get_region_definition($region_slug)) {
            return bricks_track_directory_url();
        }
        return home_url('/' . bricks_track_directory_base() . '/' . $region_slug . '/');
    }
}

if (!function_exists('bricks_track_build_registry')) {
    /**
     * @return array<string, array{course:string, slug:string, display:string, country:string}>
     */
    function bricks_track_build_registry() {
        global $wpdb;

        $courses = [];
        $tables = ['course_features', 'advance_daily_races_beta', 'advance_daily_races'];
        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                continue;
            }
            $has_country = !empty($wpdb->get_col("SHOW COLUMNS FROM `$table` LIKE 'country'"));
            $sql = $has_country
                ? "SELECT DISTINCT course, country FROM `$table` WHERE course IS NOT NULL AND course != '' ORDER BY course"
                : "SELECT DISTINCT course, '' AS country FROM `$table` WHERE course IS NOT NULL AND course != '' ORDER BY course";
            $rows = $wpdb->get_results($sql);
            foreach ((array) $rows as $row) {
                $course = trim((string) ($row->course ?? ''));
                if ($course === '') {
                    continue;
                }
                if (!isset($courses[$course])) {
                    $courses[$course] = [
                        'course' => $course,
                        'display' => bricks_track_format_display_name($course),
                        'country' => trim((string) ($row->country ?? '')),
                    ];
                } elseif ($courses[$course]['country'] === '' && !empty($row->country)) {
                    $courses[$course]['country'] = trim((string) $row->country);
                }
            }
        }

        foreach (bricks_track_slug_aliases() as $slug => $course) {
            $course = trim((string) $course);
            if ($course === '') {
                continue;
            }
            if (!isset($courses[$course])) {
                $courses[$course] = [
                    'course' => $course,
                    'display' => bricks_track_format_display_name($course),
                    'country' => '',
                ];
            }
        }

        $registry = [];
        foreach ($courses as $course => $meta) {
            $slug = bricks_track_course_to_slug($course);
            if ($slug === '') {
                continue;
            }
            if (!isset($registry[$slug])) {
                $meta['slug'] = $slug;
                $registry[$slug] = $meta;
            }
        }

        foreach (bricks_track_slug_aliases() as $slug => $course) {
            $course = trim((string) $course);
            $slug = sanitize_title((string) $slug);
            if ($slug === '' || $course === '') {
                continue;
            }
            $display = bricks_track_format_display_name($course);
            $country = isset($registry[bricks_track_course_to_slug($course)]['country'])
                ? $registry[bricks_track_course_to_slug($course)]['country']
                : '';
            $registry[$slug] = [
                'course' => $course,
                'slug' => $slug,
                'display' => $display,
                'country' => $country,
            ];
        }

        uasort($registry, function ($a, $b) {
            return strcasecmp($a['display'], $b['display']);
        });

        return $registry;
    }
}

if (!function_exists('bricks_track_get_registry')) {
    function bricks_track_get_registry($force_refresh = false) {
        $cache_key = 'bricks_track_registry_v1';
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $registry = bricks_track_build_registry();
        set_transient($cache_key, $registry, DAY_IN_SECONDS);
        return $registry;
    }
}

if (!function_exists('bricks_track_resolve_slug')) {
    /**
     * @return array{course:string, slug:string, display:string, country:string}|null
     */
    function bricks_track_resolve_slug($slug) {
        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            return null;
        }

        $aliases = bricks_track_slug_aliases();
        if (isset($aliases[$slug])) {
            $course = trim((string) $aliases[$slug]);
            if ($course !== '') {
                $registry = bricks_track_get_registry();
                $canonical_slug = bricks_track_course_to_slug($course);
                $country = $registry[$canonical_slug]['country'] ?? ($registry[$slug]['country'] ?? '');
                return [
                    'course' => $course,
                    'slug' => $slug,
                    'display' => bricks_track_format_display_name($course),
                    'country' => $country,
                ];
            }
        }

        $registry = bricks_track_get_registry();
        return $registry[$slug] ?? null;
    }
}

if (!function_exists('bricks_track_resolve_context')) {
    /**
     * Resolve course from shortcode atts, query var, or request URI.
     *
     * @return array{course:string, slug:string, display:string, country:string}|null
     */
    function bricks_track_resolve_context($atts = []) {
        $course = isset($atts['course']) ? trim((string) $atts['course']) : '';
        $slug = isset($atts['slug']) ? sanitize_title((string) $atts['slug']) : '';

        if ($slug === '') {
            $slug = sanitize_title((string) get_query_var('track_slug'));
        }
        if ($slug === '' && !empty($_SERVER['REQUEST_URI'])) {
            $uri = (string) $_SERVER['REQUEST_URI'];
            if (preg_match('#/' . preg_quote(bricks_track_directory_base(), '#') . '/([a-z0-9-]+)/?#i', $uri, $m)) {
                $slug = sanitize_title($m[1]);
            } elseif (preg_match('#/tracks/([a-z0-9-]+)/?#i', $uri, $m)) {
                $slug = sanitize_title($m[1]);
            }
        }

        if ($slug !== '' && in_array($slug, bricks_track_reserved_region_slugs(), true)) {
            return null;
        }

        if ($slug !== '' && $slug !== 'index') {
            $resolved = bricks_track_resolve_slug($slug);
            if ($resolved) {
                return $resolved;
            }
        }

        if ($course !== '') {
            $display = bricks_track_format_display_name($course);
            return [
                'course' => $course,
                'slug' => bricks_track_course_to_slug($course),
                'display' => $display,
                'country' => '',
            ];
        }

        return null;
    }
}

if (!function_exists('bricks_track_url')) {
    function bricks_track_url($slug_or_course) {
        $value = trim((string) $slug_or_course);
        if ($value === '') {
            return bricks_track_directory_url();
        }

        if (strpos($value, ' ') !== false || strpos($value, '_') !== false) {
            $value = bricks_track_course_to_slug($value);
        }

        return home_url('/' . bricks_track_directory_base() . '/' . sanitize_title($value) . '/');
    }
}

if (!function_exists('bricks_track_get_features_rows')) {
    function bricks_track_get_features_rows($course) {
        global $wpdb;

        $course = trim((string) $course);
        if ($course === '') {
            return [];
        }

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'course_features'));
        if ($exists !== 'course_features') {
            return [];
        }

        $cols = $wpdb->get_col("SHOW COLUMNS FROM course_features");
        $select = ['course', 'country', 'race_type', 'race_code', 'profile', 'general_features', 'specific_features', 'direction'];
        if (in_array('straight_track_up_to', $cols, true)) {
            $select[] = 'straight_track_up_to';
        }
        if (in_array('track_type', $cols, true)) {
            $select[] = 'track_type';
        }

        $sql = 'SELECT ' . implode(', ', array_map(function ($c) {
            return '`' . esc_sql($c) . '`';
        }, $select)) . ' FROM course_features WHERE course = %s ORDER BY race_type';

        return (array) $wpdb->get_results($wpdb->prepare($sql, $course));
    }
}

if (!function_exists('bricks_track_get_draw_bias_summary')) {
    /**
     * Top draw-bias stalls by race type / track surface at this course.
     */
    function bricks_track_get_draw_bias_summary($course, $limit = 8) {
        global $wpdb;

        $course = trim((string) $course);
        if ($course === '') {
            return [];
        }

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'draw_bias'));
        if ($exists !== 'draw_bias') {
            return [];
        }

        $limit = max(1, min(20, intval($limit)));

        $sql = "SELECT race_type, track_type, stall_number,
                       ROUND(AVG(win_percent_by_stall), 1) AS avg_win_pct,
                       SUM(n) AS sample_size
                FROM draw_bias
                WHERE course = %s
                GROUP BY race_type, track_type, stall_number
                HAVING sample_size >= 20
                ORDER BY avg_win_pct DESC
                LIMIT %d";

        return (array) $wpdb->get_results($wpdb->prepare($sql, $course, $limit));
    }
}

if (!function_exists('bricks_track_normalize_horse_name')) {
    function bricks_track_normalize_horse_name($name) {
        $name = strtolower(trim((string) $name));
        $name = preg_replace('/\s+/', ' ', $name);
        return $name;
    }
}

if (!function_exists('bricks_track_get_venue_winners')) {
    /**
     * Rolling log of Points Engine win picks that won at this course.
     */
    function bricks_track_get_venue_winners($course, $limit = 12) {
        global $wpdb;

        $course = trim((string) $course);
        if ($course === '') {
            return [];
        }

        $limit = max(1, min(30, intval($limit)));

        if (!function_exists('bricks_points_published_picks_table_name')) {
            return [];
        }

        $picks_table = bricks_points_published_picks_table_name();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $picks_table)) !== $picks_table) {
            return [];
        }

        $races_table = 'historic_races_beta';
        $runners_table = 'historic_runners_beta';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $races_table)) !== $races_table) {
            return [];
        }
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $runners_table)) !== $runners_table) {
            return [];
        }

        $runner_cols = $wpdb->get_col("SHOW COLUMNS FROM `$runners_table`");
        $name_col = in_array('name', $runner_cols, true) ? 'name' : (in_array('horse_name', $runner_cols, true) ? 'horse_name' : 'name');
        $sp_col = in_array('starting_price', $runner_cols, true) ? 'starting_price' : '';
        $race_cols = $wpdb->get_col("SHOW COLUMNS FROM `$races_table`");
        $time_col = in_array('scheduled_time', $race_cols, true) ? 'scheduled_time' : '';

        $race_title_select = "'' AS race_title";
        if (function_exists('bricks_points_table_has_column')) {
            if (bricks_points_table_has_column($races_table, 'race_title')) {
                $race_title_select = 'r.race_title AS race_title';
            } elseif (bricks_points_table_has_column($races_table, 'title')) {
                $race_title_select = 'r.title AS race_title';
            }
        } elseif (in_array('race_title', $race_cols, true)) {
            $race_title_select = 'r.race_title AS race_title';
        } elseif (in_array('title', $race_cols, true)) {
            $race_title_select = 'r.title AS race_title';
        }

        $winner_sql = "(
            ru.finish_position = 1
            OR ru.finish_position = '1'
            OR CAST(ru.finish_position AS UNSIGNED) = 1
            OR LOWER(TRIM(ru.finish_position)) IN ('1st', 'first')
        )";

        $sp_select = $sp_col !== '' ? ", ru.`" . esc_sql($sp_col) . "` AS starting_price" : ", '' AS starting_price";
        $time_select = $time_col !== '' ? ", r.`" . esc_sql($time_col) . "` AS race_time" : ", '' AS race_time";

        $sql = "SELECT pp.meeting_date, pp.win_horse, r.race_id, $race_title_select
                       $sp_select
                       $time_select
                FROM `$picks_table` pp
                INNER JOIN `$races_table` r ON r.race_id = pp.race_id AND r.meeting_date = pp.meeting_date
                INNER JOIN `$runners_table` ru ON ru.race_id = pp.race_id
                WHERE r.course = %s
                  AND $winner_sql
                  AND LOWER(TRIM(ru.`" . esc_sql($name_col) . "`)) = LOWER(TRIM(pp.win_horse))
                ORDER BY pp.meeting_date DESC, r.race_id DESC
                LIMIT %d";

        $wpdb->suppress_errors(true);
        $rows = (array) $wpdb->get_results($wpdb->prepare($sql, $course, $limit));
        $wpdb->suppress_errors(false);

        return $rows;
    }
}

if (!function_exists('bricks_track_has_meeting_today')) {
    function bricks_track_has_meeting_today($course) {
        global $wpdb;

        $course = trim((string) $course);
        if ($course === '') {
            return false;
        }

        $live = bricks_track_get_live_courses_today();
        return in_array($course, $live, true);
    }
}

if (!function_exists('bricks_track_get_live_courses_today')) {
    /**
     * Courses with at least one race in advance_daily_races_beta for today.
     *
     * @return string[]
     */
    function bricks_track_get_live_courses_today() {
        global $wpdb;

        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $today = wp_date('Y-m-d', current_time('timestamp'));
        $table = 'advance_daily_races_beta';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            $cache = [];
            return $cache;
        }

        $courses = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT course FROM `$table` WHERE meeting_date = %s AND course IS NOT NULL AND course != ''",
            $today
        ));

        $cache = array_values(array_filter(array_map('strval', (array) $courses)));
        return $cache;
    }
}

if (!function_exists('bricks_track_normalize_country_bucket')) {
    /**
     * Map DB country string to filter chip key.
     *
     * @return string all|england|ireland|scotland|wales|uae|other
     */
    function bricks_track_normalize_country_bucket($country) {
        $c = strtolower(trim((string) $country));
        if ($c === '') {
            return 'other';
        }

        if (strpos($c, 'england') !== false || $c === 'eng') {
            return 'england';
        }
        if (strpos($c, 'ireland') !== false || strpos($c, 'eire') !== false || $c === 'ire') {
            return 'ireland';
        }
        if (strpos($c, 'scotland') !== false || $c === 'sco') {
            return 'scotland';
        }
        if (strpos($c, 'wales') !== false || $c === 'wal') {
            return 'wales';
        }
        if (strpos($c, 'uae') !== false || strpos($c, 'dubai') !== false || strpos($c, 'emirates') !== false) {
            return 'uae';
        }

        return 'other';
    }
}

if (!function_exists('bricks_track_prepare_index_entries')) {
    /**
     * @param array $registry
     * @return array<int, array<string, mixed>>
     */
    function bricks_track_prepare_index_entries($registry) {
        $live_courses = bricks_track_get_live_courses_today();
        $entries = [];

        foreach ($registry as $entry) {
            $display = $entry['display'] ?? '';
            $letter = strtoupper(substr($display, 0, 1));
            if (!preg_match('/^[A-Z]$/', $letter)) {
                $letter = '#';
            }

            $country_raw = trim((string) ($entry['country'] ?? ''));
            $region = bricks_track_infer_course_region($entry['course'] ?? '', $country_raw);
            $region_def = bricks_track_get_region_definition($region);
            $entries[] = [
                'course' => $entry['course'] ?? '',
                'slug' => $entry['slug'] ?? '',
                'display' => $display,
                'country' => $country_raw,
                'country_bucket' => bricks_track_normalize_country_bucket($country_raw),
                'region' => $region,
                'region_label' => $region_def['label'] ?? '',
                'hasLiveCard' => in_array($entry['course'] ?? '', $live_courses, true),
                'letter' => $letter,
                'search_name' => strtolower($display),
            ];
        }

        usort($entries, function ($a, $b) {
            return strcasecmp($a['display'], $b['display']);
        });

        return $entries;
    }
}

if (!function_exists('bricks_track_group_index_by_letter')) {
    function bricks_track_group_index_by_letter(array $entries) {
        $groups = [];
        foreach ($entries as $entry) {
            $letter = $entry['letter'];
            if (!isset($groups[$letter])) {
                $groups[$letter] = [];
            }
            $groups[$letter][] = $entry;
        }
        ksort($groups);
        if (isset($groups['#'])) {
            $hash = $groups['#'];
            unset($groups['#']);
            $groups['#'] = $hash;
        }
        return $groups;
    }
}

if (!function_exists('bricks_track_enqueue_index_scripts')) {
    function bricks_track_enqueue_index_scripts() {
        if (wp_script_is('racecourse-index', 'enqueued') || wp_script_is('racecourse-index', 'done')) {
            return;
        }

        $js_file = get_stylesheet_directory() . '/racecourse-index.js';
        if (!file_exists($js_file)) {
            return;
        }

        wp_enqueue_script(
            'racecourse-index',
            get_stylesheet_directory_uri() . '/racecourse-index.js',
            [],
            filemtime($js_file),
            true
        );
    }
}

if (!function_exists('bricks_track_maybe_enqueue_index_scripts')) {
    function bricks_track_maybe_enqueue_index_scripts() {
        $is_index = (bool) get_query_var('tracks_index');
        $has_shortcode = function_exists('bricks_current_post_has_shortcode')
            && bricks_current_post_has_shortcode(['racecourse_index']);

        if ($is_index || bricks_track_is_directory_index_request() || bricks_track_is_region_hub_request() || $has_shortcode) {
            bricks_track_enqueue_index_scripts();
        }
    }
}
add_action('wp_enqueue_scripts', 'bricks_track_maybe_enqueue_index_scripts', 25);

if (!function_exists('bricks_track_render_static_section')) {
    function bricks_track_render_static_section($context, $atts = []) {
        if (!$context) {
            return '<div class="racecourse-guide-error">Racecourse not found.</div>';
        }

        $course = $context['course'];
        $display = $context['display'];
        $country = $context['country'];
        $winners_limit = isset($atts['winners']) ? intval($atts['winners']) : 12;

        $features = bricks_track_get_features_rows($course);
        $draw_bias = bricks_track_get_draw_bias_summary($course);
        $winners = bricks_track_get_venue_winners($course, $winners_limit);
        $has_today = bricks_track_has_meeting_today($course);
        $is_aw = function_exists('bricks_seo_course_is_all_weather')
            ? bricks_seo_course_is_all_weather($course)
            : false;
        $ratings_phrase = function_exists('bricks_seo_surface_speed_phrase_short')
            ? bricks_seo_surface_speed_phrase_short($is_aw)
            : 'speed ratings';
        $region = bricks_track_infer_course_region($course, $country);
        $region_def = bricks_track_get_region_definition($region);

        ob_start();
        ?>
        <article class="racecourse-guide-static" itemscope itemtype="https://schema.org/SportsActivityLocation">
            <meta itemprop="name" content="<?php echo esc_attr($display); ?>" />
            <nav class="rcg-index-breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo esc_url(bricks_track_directory_url()); ?>">Racecourses directory</a>
                <?php if ($region_def): ?>
                    <span aria-hidden="true"> › </span>
                    <a href="<?php echo esc_url(bricks_track_region_url($region)); ?>"><?php echo esc_html($region_def['label']); ?></a>
                <?php endif; ?>
                <span aria-hidden="true"> › </span>
                <span><?php echo esc_html($display); ?></span>
            </nav>
            <header class="racecourse-guide-hero">
                <h1 class="racecourse-guide-title">
                    <?php echo esc_html($display); ?> Racecourse Guide
                </h1>
                <p class="racecourse-guide-lead">
                    <?php if ($is_aw): ?>
                        All-Weather speed figures, AW ratings, draw bias, and Fhorsite Points Engine history
                    <?php else: ?>
                        Turf speed ratings, draw bias, straight-course notes, and Fhorsite Points Engine history
                    <?php endif; ?>
                    for <strong><?php echo esc_html($display); ?></strong><?php echo $country !== '' ? ' (' . esc_html($country) . ')' : ''; ?>.
                    <?php if ($has_today): ?>
                        There is a meeting at <?php echo esc_html($display); ?> today — see the live UK &amp; Irish race card below.
                    <?php endif; ?>
                </p>
            </header>

            <?php if (!empty($features)): ?>
            <section class="racecourse-guide-section" aria-labelledby="rcg-specs-<?php echo esc_attr($context['slug']); ?>">
                <h2 id="rcg-specs-<?php echo esc_attr($context['slug']); ?>">Racecourse specifications</h2>
                <div class="racecourse-guide-spec-grid">
                    <?php foreach ($features as $row): ?>
                    <div class="racecourse-guide-spec-card">
                        <h3><?php echo esc_html(trim(($row->race_type ?? '') . ($row->race_code ? ' · ' . $row->race_code : ''))); ?></h3>
                        <?php if (!empty($row->profile)): ?>
                            <p><strong>Profile:</strong> <?php echo esc_html($row->profile); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($row->direction)): ?>
                            <p><strong>Direction:</strong> <?php echo esc_html($row->direction); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($row->straight_track_up_to)): ?>
                            <p><strong>Straight track (up to):</strong> <?php echo esc_html($row->straight_track_up_to); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($row->general_features)): ?>
                            <p><strong>General:</strong> <?php echo esc_html($row->general_features); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($row->specific_features)): ?>
                            <p><strong>Specific:</strong> <?php echo esc_html($row->specific_features); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($draw_bias)): ?>
            <section class="racecourse-guide-section" aria-labelledby="rcg-draw-<?php echo esc_attr($context['slug']); ?>">
                <h2 id="rcg-draw-<?php echo esc_attr($context['slug']); ?>">Historic draw bias</h2>
                <p class="racecourse-guide-note">Stalls with the strongest win share (min. 20 runners in sample). Useful for <?php echo esc_html($display); ?> <?php echo esc_html($ratings_phrase); ?> and draw-bias searches.</p>
                <div class="racecourse-guide-table-wrap">
                    <table class="racecourse-guide-table">
                        <thead>
                            <tr>
                                <th>Race type</th>
                                <th>Surface</th>
                                <th>Stall</th>
                                <th>Win %</th>
                                <th>Sample</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($draw_bias as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->race_type ?? ''); ?></td>
                                <td><?php echo esc_html($row->track_type ?? ''); ?></td>
                                <td><?php echo esc_html($row->stall_number ?? ''); ?></td>
                                <td><?php echo esc_html($row->avg_win_pct ?? ''); ?>%</td>
                                <td><?php echo esc_html($row->sample_size ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <section class="racecourse-guide-section" aria-labelledby="rcg-winners-<?php echo esc_attr($context['slug']); ?>">
                <h2 id="rcg-winners-<?php echo esc_attr($context['slug']); ?>">Fhorsite winners at <?php echo esc_html($display); ?></h2>
                <?php if (!empty($winners)): ?>
                <div class="racecourse-guide-table-wrap">
                    <table class="racecourse-guide-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Horse</th>
                                <th>Race</th>
                                <th>SP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($winners as $row): ?>
                            <tr>
                                <td><?php echo esc_html(wp_date('j M Y', strtotime((string) $row->meeting_date))); ?></td>
                                <td><?php echo esc_html($row->win_horse ?? ''); ?></td>
                                <td><?php echo esc_html($row->race_title ?? ''); ?></td>
                                <td><?php echo esc_html($row->starting_price ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="racecourse-guide-note">No published Points Engine winners logged for this venue yet.</p>
                <?php endif; ?>
            </section>
        </article>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('bricks_track_enqueue_styles')) {
    function bricks_track_enqueue_styles() {
        $css = '
        .racecourse-guide-static{margin:0 0 2rem}
        .racecourse-guide-hero{margin-bottom:1.5rem}
        .racecourse-guide-title{margin:0 0 .5rem;font-size:clamp(1.75rem,3vw,2.25rem);line-height:1.2}
        .racecourse-guide-lead{margin:0;color:#475569;font-size:1.05rem;line-height:1.6}
        .racecourse-guide-section{margin:2rem 0}
        .racecourse-guide-section h2{margin:0 0 .75rem;font-size:1.35rem}
        .racecourse-guide-note{margin:0 0 1rem;color:#64748b;font-size:.95rem}
        .racecourse-guide-spec-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem}
        .racecourse-guide-spec-card{padding:1rem;border:1px solid #e2e8f0;border-radius:10px;background:#fff}
        .racecourse-guide-spec-card h3{margin:0 0 .5rem;font-size:1rem}
        .racecourse-guide-spec-card p{margin:.35rem 0;font-size:.92rem;color:#334155}
        .racecourse-guide-table-wrap{overflow-x:auto}
        .racecourse-guide-table{width:100%;border-collapse:collapse;font-size:.9rem}
        .racecourse-guide-table th,.racecourse-guide-table td{padding:.65rem .75rem;border-bottom:1px solid #e2e8f0;text-align:left}
        .racecourse-guide-table th{background:#f8fafc;font-weight:600}
        .racecourse-guide-card{margin-top:2rem;padding-top:1.5rem;border-top:2px solid #e2e8f0}
        .racecourse-guide-card h2{margin:0 0 1rem;font-size:1.35rem}
        .racecourse-guide-error{padding:1rem;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;color:#991b1b}
        .racecourse-guide-index-page{--rcg-brand-green:#16a34a;--rcg-brand-green-soft:#ecfdf5;position:relative}
        .rcg-index-toolbar{margin:1.25rem 0 1.5rem;display:flex;flex-direction:column;gap:.75rem}
        .rcg-index-search{width:100%;max-width:420px;padding:.7rem 1rem;border:1px solid #e2e8f0;border-radius:8px;font-size:1rem;color:#1e293b;background:#fff;transition:border-color .2s,box-shadow .2s}
        .rcg-index-search:focus{outline:none;border-color:var(--rcg-brand-green);box-shadow:0 0 0 3px rgba(22,163,74,.15)}
        .rcg-index-filters{display:flex;flex-wrap:wrap;gap:.5rem}
        .rcg-index-chip{padding:.45rem .85rem;border:1px solid #e2e8f0;border-radius:999px;background:#fff;color:#334155;font-size:.875rem;font-weight:600;cursor:pointer;transition:all .2s ease}
        .rcg-index-chip:hover,.rcg-index-chip:focus-visible{border-color:var(--rcg-brand-green);color:#14532d;outline:none}
        .rcg-index-chip.is-active{background:var(--rcg-brand-green);border-color:var(--rcg-brand-green);color:#fff}
        .rcg-index-results-count{margin:0;font-size:.875rem;color:#64748b}
        .rcg-index-layout{display:flex;align-items:flex-start;gap:1.5rem;position:relative}
        .rcg-index-main{flex:1;min-width:0}
        .rcg-index-letter-section{margin:0 0 1.75rem;scroll-margin-top:6rem}
        .rcg-index-letter-section.is-hidden{display:none}
        .rcg-index-letter-heading{margin:0 0 .65rem;font-size:.8rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8}
        .rcg-index-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem}
        .rcg-index-card{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.75rem 1rem;border:1px solid #e2e8f0;border-radius:8px;text-decoration:none;color:#1e293b;font-weight:600;background:#fff;transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease;position:relative}
        .rcg-index-card:hover,.rcg-index-card:focus-visible{border-color:var(--rcg-brand-green);box-shadow:0 4px 14px rgba(15,23,42,.08);transform:translateY(-1px);outline:none}
        .rcg-index-card.is-hidden{display:none!important}
        .rcg-index-card-body{display:flex;flex-direction:column;gap:.15rem;min-width:0}
        .rcg-index-card-title{display:block;font-weight:600;color:#1e293b}
        .rcg-index-card-country{display:block;font-weight:400;font-size:.85rem;color:#64748b}
        .rcg-index-card-live{display:inline-flex;align-items:center;gap:.35rem;font-size:.7rem;font-weight:700;color:#15803d;white-space:nowrap;flex-shrink:0}
        .rcg-index-live-dot{width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 2px rgba(34,197,94,.25);animation:rcg-live-pulse 2s ease-in-out infinite}
        @keyframes rcg-live-pulse{0%,100%{opacity:1}50%{opacity:.55}}
        .rcg-index-card-chevron{font-size:1.25rem;line-height:1;color:#94a3b8;opacity:0;transform:translateX(-4px);transition:opacity .2s ease,transform .2s ease,color .2s ease;flex-shrink:0}
        .rcg-index-card:hover .rcg-index-card-chevron,.rcg-index-card:focus-visible .rcg-index-card-chevron{opacity:1;transform:translateX(0);color:var(--rcg-brand-green)}
        .rcg-index-az{position:sticky;top:5.5rem;display:flex;flex-direction:column;gap:.2rem;padding:.35rem;border:1px solid #e2e8f0;border-radius:8px;background:#fff;max-height:calc(100vh - 7rem);overflow-y:auto;flex-shrink:0}
        .rcg-index-az a{display:block;padding:.15rem .45rem;font-size:.72rem;font-weight:700;color:#64748b;text-decoration:none;text-align:center;border-radius:4px;line-height:1.3}
        .rcg-index-az a:hover,.rcg-index-az a:focus-visible{background:var(--rcg-brand-green-soft);color:var(--rcg-brand-green);outline:none}
        .rcg-index-az a.is-disabled{opacity:.3;pointer-events:none}
        .rcg-index-empty{margin:2rem 0;padding:1.5rem;text-align:center;color:#64748b;border:1px dashed #e2e8f0;border-radius:10px}
        .rcg-index-breadcrumb{margin:0 0 1rem;font-size:.875rem;color:#64748b}
        .rcg-index-breadcrumb a{color:#15803d;text-decoration:none;font-weight:600}
        .rcg-index-breadcrumb a:hover{text-decoration:underline}
        .rcg-region-hub-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin:0 0 2rem}
        .rcg-region-hub-card{display:flex;flex-direction:column;gap:.35rem;padding:1rem 1.1rem;border:1px solid #e2e8f0;border-radius:12px;background:#fff;text-decoration:none;color:inherit;transition:border-color .2s,box-shadow .2s,transform .2s}
        .rcg-region-hub-card:hover,.rcg-region-hub-card:focus-visible{border-color:var(--rcg-brand-green);box-shadow:0 6px 18px rgba(15,23,42,.08);transform:translateY(-2px);outline:none}
        .rcg-region-hub-card strong{font-size:1rem;color:#111827}
        .rcg-region-hub-card span{font-size:.85rem;line-height:1.45;color:#64748b}
        .rcg-region-hub-card em{font-style:normal;font-size:.75rem;font-weight:700;color:#15803d;margin-top:.25rem}
        .rcg-index-card-region{display:block;font-size:.72rem;font-weight:700;color:#15803d;margin-top:.1rem}
        @media (max-width:767px){
            .rcg-index-toolbar--sticky-mobile{position:sticky;top:0;z-index:20;background:#fff;padding:.75rem 0;margin-top:.5rem;border-bottom:1px solid #e2e8f0}
            .rcg-index-search{max-width:none}
            .rcg-index-az{display:none}
            .rcg-index-letter-section{scroll-margin-top:7.5rem}
        }
        @media (min-width:768px){
            .rcg-index-toolbar--sticky-mobile{position:static;border-bottom:none;padding-bottom:0}
        }
        ';
        wp_register_style('bricks-racecourse-guide', false);
        wp_enqueue_style('bricks-racecourse-guide');
        wp_add_inline_style('bricks-racecourse-guide', $css);
    }
}

if (!function_exists('bricks_racecourse_guide_static_shortcode')) {
    function bricks_racecourse_guide_static_shortcode($atts = []) {
        $atts = shortcode_atts([
            'slug' => '',
            'course' => '',
            'winners' => '12',
        ], $atts, 'racecourse_guide_static');

        bricks_track_enqueue_styles();
        $context = bricks_track_resolve_context($atts);
        return bricks_track_render_static_section($context, $atts);
    }
}
add_shortcode('racecourse_guide_static', 'bricks_racecourse_guide_static_shortcode');

if (!function_exists('bricks_racecourse_guide_card_shortcode')) {
    function bricks_racecourse_guide_card_shortcode($atts = []) {
        $atts = shortcode_atts([
            'slug' => '',
            'course' => '',
            'lock_course' => '1',
            'hide_filters' => '0',
        ], $atts, 'racecourse_guide_card');

        bricks_track_enqueue_styles();
        $context = bricks_track_resolve_context($atts);
        if (!$context) {
            return '<div class="racecourse-guide-error">Racecourse not found.</div>';
        }

        $lock = $atts['lock_course'] === '1' || $atts['lock_course'] === 'true';
        $hide = $atts['hide_filters'] === '1' || $atts['hide_filters'] === 'true';

        $race_table_atts = [
            'course' => $context['course'],
            'lock_course' => $lock ? '1' : '0',
            'hide_course_filter' => ($lock || $hide) ? '1' : '0',
        ];

        ob_start();
        ?>
        <section class="racecourse-guide-card" aria-labelledby="rcg-card-<?php echo esc_attr($context['slug']); ?>">
            <h2 id="rcg-card-<?php echo esc_attr($context['slug']); ?>">
                <?php echo esc_html($context['display']); ?> — today's card
            </h2>
            <?php echo do_shortcode('[race_table course="' . esc_attr($context['course']) . '" lock_course="' . esc_attr($race_table_atts['lock_course']) . '" hide_course_filter="' . esc_attr($race_table_atts['hide_course_filter']) . '"]'); ?>
        </section>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('racecourse_guide_card', 'bricks_racecourse_guide_card_shortcode');

if (!function_exists('bricks_racecourse_guide_shortcode')) {
    function bricks_racecourse_guide_shortcode($atts = []) {
        $atts = shortcode_atts([
            'slug' => '',
            'course' => '',
        ], $atts, 'racecourse_guide');

        bricks_track_enqueue_styles();
        $context = bricks_track_resolve_context($atts);
        if (!$context) {
            return '<div class="racecourse-guide-error">Racecourse not found.</div>';
        }

        return bricks_track_render_static_section($context, $atts)
            . do_shortcode('[racecourse_guide_card course="' . esc_attr($context['course']) . '" slug="' . esc_attr($context['slug']) . '"]');
    }
}
add_shortcode('racecourse_guide', 'bricks_racecourse_guide_shortcode');

if (!function_exists('bricks_racecourse_index_shortcode')) {
    function bricks_racecourse_index_shortcode($atts = []) {
        $atts = shortcode_atts([
            'region' => '',
        ], $atts, 'racecourse_index');

        bricks_track_enqueue_styles();
        bricks_track_enqueue_index_scripts();

        $all_entries = bricks_track_prepare_index_entries(bricks_track_get_registry());
        $region_filter = sanitize_title((string) $atts['region']);
        if ($region_filter === '' && get_query_var('racecourses_region')) {
            $region_filter = sanitize_title((string) get_query_var('racecourses_region'));
        }

        $region_def = $region_filter !== '' ? bricks_track_get_region_definition($region_filter) : null;
        $entries = $all_entries;
        if ($region_def) {
            $entries = array_values(array_filter($all_entries, function ($entry) use ($region_filter) {
                return ($entry['region'] ?? '') === $region_filter;
            }));
        }

        $groups = bricks_track_group_index_by_letter($entries);
        $letters = array_keys($groups);
        $region_defs = bricks_track_region_definitions();

        $country_chips = [
            'all' => 'All',
            'england' => 'England',
            'ireland' => 'Ireland',
            'scotland' => 'Scotland',
            'wales' => 'Wales',
            'uae' => 'UAE',
        ];

        $region_counts = [];
        foreach ($all_entries as $entry) {
            $r = $entry['region'] ?? '';
            if ($r === '') {
                continue;
            }
            $region_counts[$r] = ($region_counts[$r] ?? 0) + 1;
        }

        ob_start();
        ?>
        <div
            class="racecourse-guide-index-page"
            <?php if ($region_filter !== ''): ?>data-rcg-region="<?php echo esc_attr($region_filter); ?>"<?php endif; ?>
        >
            <?php if ($region_def): ?>
                <nav class="rcg-index-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?php echo esc_url(bricks_track_directory_url()); ?>">Racecourses directory</a>
                    <span aria-hidden="true"> › </span>
                    <span><?php echo esc_html($region_def['label']); ?></span>
                </nav>
            <?php endif; ?>

            <header class="racecourse-guide-hero">
                <h1 class="racecourse-guide-title">
                    <?php if ($region_def): ?>
                        <?php echo esc_html($region_def['label']); ?> racecourses
                    <?php else: ?>
                        Racecourses directory
                    <?php endif; ?>
                </h1>
                <p class="racecourse-guide-lead">
                    <?php if ($region_def): ?>
                        <?php echo esc_html($region_def['description']); ?>
                    <?php else: ?>
                        Browse UK and Irish racecourses by region — UK Flat &amp; All-Weather, UK National Hunt, and Irish racing — with turf speed ratings, AW speed figures, draw bias, and live race cards.
                    <?php endif; ?>
                </p>
            </header>

            <?php if (!$region_def): ?>
            <section class="rcg-region-hub-grid" aria-label="Browse racecourses by region">
                <?php foreach ($region_defs as $region_key => $def): ?>
                    <a class="rcg-region-hub-card" href="<?php echo esc_url(bricks_track_region_url($region_key)); ?>">
                        <strong><?php echo esc_html($def['label']); ?></strong>
                        <span><?php echo esc_html($def['description']); ?></span>
                        <em><?php echo esc_html(number_format($region_counts[$region_key] ?? 0)); ?> racecourses</em>
                    </a>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

            <div class="rcg-index-toolbar rcg-index-toolbar--sticky-mobile">
                <div class="rcg-index-search-wrap">
                    <label class="screen-reader-text" for="rcg-index-search">Search racecourses</label>
                    <input
                        type="search"
                        id="rcg-index-search"
                        class="rcg-index-search"
                        placeholder="Search racecourses…"
                        autocomplete="off"
                        spellcheck="false"
                    />
                </div>
                <div class="rcg-index-filters" role="tablist" aria-label="Filter by country">
                    <?php foreach ($country_chips as $key => $label): ?>
                        <button
                            type="button"
                            class="rcg-index-chip<?php echo $key === 'all' ? ' is-active' : ''; ?>"
                            role="tab"
                            data-filter="<?php echo esc_attr($key); ?>"
                            data-filter-type="country"
                            aria-selected="<?php echo $key === 'all' ? 'true' : 'false'; ?>"
                        ><?php echo esc_html($label); ?></button>
                    <?php endforeach; ?>
                </div>
                <?php if (!$region_def): ?>
                <div class="rcg-index-filters" role="tablist" aria-label="Filter by region">
                    <button type="button" class="rcg-index-chip is-active" data-filter="all" data-filter-type="region" aria-selected="true">All regions</button>
                    <?php foreach ($region_defs as $region_key => $def): ?>
                        <button
                            type="button"
                            class="rcg-index-chip"
                            role="tab"
                            data-filter="<?php echo esc_attr($region_key); ?>"
                            data-filter-type="region"
                            aria-selected="false"
                        ><?php echo esc_html($def['label']); ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <p class="rcg-index-results-count" aria-live="polite"><?php echo esc_html(count($entries)); ?> racecourses</p>
            </div>

            <div class="rcg-index-layout">
                <div class="rcg-index-main">
                    <div class="rcg-index-grid" id="rcg-index-grid">
                        <?php foreach ($groups as $letter => $letter_entries): ?>
                            <section
                                class="rcg-index-letter-section"
                                id="rcg-letter-<?php echo esc_attr(strtolower($letter)); ?>"
                                data-letter="<?php echo esc_attr($letter); ?>"
                                aria-labelledby="rcg-heading-<?php echo esc_attr(strtolower($letter)); ?>"
                            >
                                <h2 class="rcg-index-letter-heading" id="rcg-heading-<?php echo esc_attr(strtolower($letter)); ?>">
                                    <?php echo esc_html($letter); ?>
                                </h2>
                                <div class="rcg-index-cards">
                                    <?php foreach ($letter_entries as $entry): ?>
                                        <a
                                            href="<?php echo esc_url(bricks_track_url($entry['slug'])); ?>"
                                            class="rcg-index-card"
                                            data-name="<?php echo esc_attr($entry['search_name']); ?>"
                                            data-country="<?php echo esc_attr($entry['country_bucket']); ?>"
                                            data-region="<?php echo esc_attr($entry['region'] ?? ''); ?>"
                                            data-live="<?php echo $entry['hasLiveCard'] ? '1' : '0'; ?>"
                                        >
                                            <span class="rcg-index-card-body">
                                                <span class="rcg-index-card-title"><?php echo esc_html($entry['display']); ?></span>
                                                <?php if ($entry['country'] !== ''): ?>
                                                    <span class="rcg-index-card-country"><?php echo esc_html($entry['country']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!$region_def && !empty($entry['region_label'])): ?>
                                                    <span class="rcg-index-card-region"><?php echo esc_html($entry['region_label']); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if ($entry['hasLiveCard']): ?>
                                                <span class="rcg-index-card-live">
                                                    <span class="rcg-index-live-dot" aria-hidden="true"></span>
                                                    Live today
                                                </span>
                                            <?php endif; ?>
                                            <span class="rcg-index-card-chevron" aria-hidden="true">›</span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                    <p class="rcg-index-empty" hidden>No racecourses match your search or filters.</p>
                </div>

                <?php if (!empty($letters)): ?>
                <nav class="rcg-index-az" aria-label="Jump to letter">
                    <?php foreach ($letters as $letter): ?>
                        <a
                            href="#rcg-letter-<?php echo esc_attr(strtolower($letter)); ?>"
                            data-letter="<?php echo esc_attr($letter); ?>"
                        ><?php echo esc_html($letter); ?></a>
                    <?php endforeach; ?>
                </nav>
                <?php endif; ?>
            </div>

            <?php if (function_exists('bricks_festival_url')): ?>
            <section class="racecourse-guide-section" style="margin-top:2rem;">
                <h2>Racing festival hubs</h2>
                <p class="racecourse-guide-lead" style="margin-bottom:.75rem;">Seasonal guides for Cheltenham, Grand National, Royal Ascot, and Galway.</p>
                <a href="<?php echo esc_url(bricks_festival_url()); ?>" style="display:inline-block;padding:.55rem 1rem;border:1px solid #e2e8f0;border-radius:8px;font-weight:700;text-decoration:none;color:#334155;">Browse festival hubs</a>
            </section>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('racecourse_index', 'bricks_racecourse_index_shortcode');

if (!function_exists('bricks_track_is_request')) {
    function bricks_track_is_request() {
        if (
            get_query_var('track_slug')
            || get_query_var('tracks_index')
            || get_query_var('racecourses_index')
            || get_query_var('racecourses_region')
        ) {
            return true;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        return (bool) preg_match('#/(?:racecourses|tracks)(?:/|$)#i', $uri);
    }
}

if (!function_exists('bricks_track_is_directory_index_request')) {
    function bricks_track_is_directory_index_request() {
        return (bool) (get_query_var('racecourses_index') || get_query_var('tracks_index'));
    }
}

if (!function_exists('bricks_track_is_region_hub_request')) {
    function bricks_track_is_region_hub_request() {
        $region = sanitize_title((string) get_query_var('racecourses_region'));
        return $region !== '' && bricks_track_get_region_definition($region) !== null;
    }
}

// -----------------------------------------------------------------------------
// Bricks Builder: header / footer / content templates for /tracks/*
// -----------------------------------------------------------------------------

if (!function_exists('bricks_track_bricks_is_available')) {
    function bricks_track_bricks_is_available() {
        return defined('BRICKS_VERSION') || class_exists('\Bricks\Frontend');
    }
}

if (!function_exists('bricks_track_get_bricks_template_option')) {
    function bricks_track_get_bricks_template_option($key) {
        $constants = [
            'header' => 'BRICKS_TRACK_TEMPLATE_HEADER',
            'footer' => 'BRICKS_TRACK_TEMPLATE_FOOTER',
            'content' => 'BRICKS_TRACK_TEMPLATE_CONTENT',
            'index' => 'BRICKS_TRACK_TEMPLATE_INDEX',
        ];

        if (isset($constants[$key]) && defined($constants[$key])) {
            return max(0, intval(constant($constants[$key])));
        }

        $option_keys = [
            'header' => 'bricks_track_tpl_header',
            'footer' => 'bricks_track_tpl_footer',
            'content' => 'bricks_track_tpl_content',
            'index' => 'bricks_track_tpl_index',
        ];

        if (!isset($option_keys[$key])) {
            return 0;
        }

        return max(0, intval(get_option($option_keys[$key], 0)));
    }
}

if (!function_exists('bricks_track_get_bricks_templates')) {
    /**
     * @return array{header:int,footer:int,content:int,index:int}
     */
    function bricks_track_get_bricks_templates() {
        $templates = [
            'header' => bricks_track_get_bricks_template_option('header'),
            'footer' => bricks_track_get_bricks_template_option('footer'),
            'content' => bricks_track_get_bricks_template_option('content'),
            'index' => bricks_track_get_bricks_template_option('index'),
        ];

        return (array) apply_filters('bricks_track_bricks_templates', $templates);
    }
}

if (!function_exists('bricks_track_is_bricks_template')) {
    function bricks_track_is_bricks_template($template_id) {
        $template_id = intval($template_id);
        if ($template_id <= 0) {
            return false;
        }

        $post = get_post($template_id);
        return $post && $post->post_type === 'bricks_template' && $post->post_status === 'publish';
    }
}

if (!function_exists('bricks_track_get_active_content_template_id')) {
    function bricks_track_get_active_content_template_id() {
        $templates = bricks_track_get_bricks_templates();

        if (bricks_track_is_directory_index_request() || bricks_track_is_region_hub_request()) {
            if (bricks_track_is_bricks_template($templates['index'])) {
                return intval($templates['index']);
            }
            if (bricks_track_is_bricks_template($templates['content'])) {
                return intval($templates['content']);
            }
            return 0;
        }

        if (get_query_var('track_slug') && bricks_track_is_bricks_template($templates['content'])) {
            return intval($templates['content']);
        }

        return 0;
    }
}

if (!function_exists('bricks_track_filter_active_templates')) {
    function bricks_track_filter_active_templates($active_templates, $post_id, $content_type) {
        if (!bricks_track_is_request() || !is_array($active_templates)) {
            return $active_templates;
        }

        if (function_exists('bricks_is_builder_main') && bricks_is_builder_main()) {
            return $active_templates;
        }

        $templates = bricks_track_get_bricks_templates();

        if (bricks_track_is_bricks_template($templates['header'])) {
            $active_templates['header'] = intval($templates['header']);
        }
        if (bricks_track_is_bricks_template($templates['footer'])) {
            $active_templates['footer'] = intval($templates['footer']);
        }

        $content_id = bricks_track_get_active_content_template_id();
        if ($content_id > 0) {
            $active_templates['content'] = $content_id;
        }

        return $active_templates;
    }
}
add_filter('bricks/active_templates', 'bricks_track_filter_active_templates', 20, 3);

if (!function_exists('bricks_track_customize_virtual_post')) {
    /**
     * Replace the borrowed homepage $post with an empty stub so Bricks does not
     * also render the front-page layout on /tracks/* URLs.
     */
    function bricks_track_customize_virtual_post() {
        if (!bricks_track_is_request()) {
            return;
        }

        global $post, $wp_query;

        if (bricks_track_is_directory_index_request()) {
            $title = 'Racecourses Directory';
            $slug = bricks_track_directory_base();
        } elseif (bricks_track_is_region_hub_request()) {
            $region = sanitize_title((string) get_query_var('racecourses_region'));
            $def = bricks_track_get_region_definition($region);
            $title = $def ? ($def['label'] . ' Racecourses') : 'Racecourses Directory';
            $slug = $region;
        } elseif (get_query_var('tracks_index')) {
            $title = 'Racecourses Directory';
            $slug = bricks_track_directory_base();
        } else {
            $slug = sanitize_title((string) get_query_var('track_slug'));
            if ($slug === '') {
                return;
            }
            $context = bricks_track_resolve_slug($slug);
            if ($context) {
                $title = $context['display'] . ' Racecourse Guide';
            } else {
                $title = ucwords(str_replace('-', ' ', $slug)) . ' Racecourse Guide';
            }
        }

        $post = new WP_Post((object) [
            'ID' => 0,
            'post_title' => $title,
            'post_content' => '',
            'post_excerpt' => '',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => $slug,
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ]);

        $wp_query->post = $post;
        $wp_query->posts = [$post];
        $wp_query->post_count = 1;
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        setup_postdata($post);
    }
}
add_action('wp', 'bricks_track_customize_virtual_post', 10);

if (!function_exists('bricks_track_render_fallback_content')) {
    function bricks_track_render_fallback_content() {
        static $rendered = false;
        if ($rendered) {
            return '';
        }
        $rendered = true;

        $is_index = bricks_track_is_directory_index_request();
        $region = sanitize_title((string) get_query_var('racecourses_region'));
        $slug = sanitize_title((string) get_query_var('track_slug'));

        if ($is_index) {
            return do_shortcode('[racecourse_index]');
        }

        if ($region !== '' && bricks_track_get_region_definition($region)) {
            return do_shortcode('[racecourse_index region="' . esc_attr($region) . '"]');
        }

        if (get_query_var('tracks_index')) {
            return do_shortcode('[racecourse_index]');
        }

        if ($slug !== '') {
            $resolved = bricks_track_resolve_slug($slug);
            if ($resolved) {
                return do_shortcode('[racecourse_guide slug="' . esc_attr($slug) . '"]');
            }

            ob_start();
            ?>
            <div style="max-width:720px;margin:60px auto;padding:24px;text-align:center;">
                <div style="font-size:48px;margin-bottom:16px;">🏇</div>
                <h1 style="margin:0 0 12px;color:#334155;">Racecourse guide not found</h1>
                <p style="color:#64748b;margin:0 0 24px;">We don't have a guide for <strong><?php echo esc_html($slug); ?></strong> yet.</p>
                <a href="<?php echo esc_url(bricks_track_directory_url()); ?>" style="display:inline-block;padding:12px 24px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">
                    Browse racecourses directory
                </a>
            </div>
            <?php
            return ob_get_clean();
        }

        return '';
    }
}

if (!function_exists('bricks_track_render_main_content')) {
    /**
     * Single render path for /tracks/* body content.
     * Bricks content template (shortcodes) OR PHP shortcode fallback — never both.
     */
    function bricks_track_render_main_content() {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        $content_template_id = bricks_track_get_active_content_template_id();

        if ($content_template_id > 0 && class_exists('\Bricks\Frontend')) {
            \Bricks\Frontend::render_content(get_the_ID(), 'content');
            return;
        }

        echo bricks_track_render_fallback_content();
    }
}

if (!function_exists('bricks_track_list_bricks_templates')) {
    /**
     * @return WP_Post[]
     */
    function bricks_track_list_bricks_templates() {
        if (!post_type_exists('bricks_template')) {
            return [];
        }

        return get_posts([
            'post_type' => 'bricks_template',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
    }
}

if (!function_exists('bricks_track_template_type_label')) {
    function bricks_track_template_type_label($template_id) {
        $type = get_post_meta(intval($template_id), '_bricks_template_type', true);
        return $type !== '' ? (string) $type : 'unknown';
    }
}

if (!function_exists('bricks_track_register_settings_page')) {
    function bricks_track_register_settings_page() {
        add_options_page(
            'Racecourse Guides',
            'Racecourse Guides',
            'manage_options',
            'bricks-track-guides',
            'bricks_track_render_settings_page'
        );
    }
}
add_action('admin_menu', 'bricks_track_register_settings_page');

if (!function_exists('bricks_track_register_settings')) {
    function bricks_track_register_settings() {
        register_setting('bricks_track_guides', 'bricks_track_tpl_header', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]);
        register_setting('bricks_track_guides', 'bricks_track_tpl_footer', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]);
        register_setting('bricks_track_guides', 'bricks_track_tpl_content', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]);
        register_setting('bricks_track_guides', 'bricks_track_tpl_index', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]);
    }
}
add_action('admin_init', 'bricks_track_register_settings');

if (!function_exists('bricks_track_render_template_select')) {
    function bricks_track_render_template_select($name, $selected_id, $preferred_types = []) {
        $templates = bricks_track_list_bricks_templates();
        $selected_id = intval($selected_id);

        echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
        echo '<option value="0">' . esc_html__('— Site default —', 'bricks') . '</option>';

        foreach ($templates as $template) {
            $type = bricks_track_template_type_label($template->ID);
            $label = $template->post_title . ' (#' . $template->ID . ', ' . $type . ')';
            printf(
                '<option value="%d" %s>%s</option>',
                intval($template->ID),
                selected($selected_id, $template->ID, false),
                esc_html($label)
            );
        }

        echo '</select>';

        if (!empty($preferred_types)) {
            echo '<p class="description">Recommended type: <code>' . esc_html(implode('</code> or <code>', $preferred_types)) . '</code></p>';
        }
    }
}

if (!function_exists('bricks_track_render_settings_page')) {
    function bricks_track_render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $templates = bricks_track_get_bricks_templates();
        ?>
        <div class="wrap">
            <h1>Racecourse Guide — Bricks Templates</h1>
            <p>Assign Bricks templates for <code>/racecourses/</code> URLs. Leave header/footer blank to use your site-wide Bricks header and footer.</p>

            <?php if (!bricks_track_bricks_is_available()): ?>
                <div class="notice notice-warning"><p>Bricks Builder does not appear to be active. These settings apply once Bricks is installed.</p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('bricks_track_guides'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bricks_track_tpl_header">Header template</label></th>
                        <td>
                            <?php bricks_track_render_template_select('bricks_track_tpl_header', $templates['header'], ['header']); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bricks_track_tpl_footer">Footer template</label></th>
                        <td>
                            <?php bricks_track_render_template_select('bricks_track_tpl_footer', $templates['footer'], ['footer']); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bricks_track_tpl_content">Track content template</label></th>
                        <td>
                            <?php bricks_track_render_template_select('bricks_track_tpl_content', $templates['content'], ['content', 'section']); ?>
                            <p class="description">Used on <code>/racecourses/wolverhampton/</code> etc. Add <strong>one</strong> Shortcode element with <code>[racecourse_guide_static]</code> and <code>[racecourse_guide_card]</code> — or a single <code>[racecourse_guide]</code>. Do not also add a Post Content element (causes duplicates).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bricks_track_tpl_index">Index content template</label></th>
                        <td>
                            <?php bricks_track_render_template_select('bricks_track_tpl_index', $templates['index'], ['content', 'section']); ?>
                            <p class="description">Used on <code>/racecourses/</code> and regional hubs (<code>/racecourses/uk-flat/</code> etc.). Add Shortcode: <code>[racecourse_index]</code> — region hubs auto-filter when the Bricks template is shared. Falls back to the track content template if unset.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save templates'); ?>
            </form>

            <h2>Code override (optional)</h2>
            <p>You can also set template IDs via constants or filter:</p>
            <pre style="background:#f6f7f7;padding:12px;border:1px solid #dcdcde;max-width:760px;overflow:auto;">define('BRICKS_TRACK_TEMPLATE_HEADER', 123);
define('BRICKS_TRACK_TEMPLATE_FOOTER', 124);
define('BRICKS_TRACK_TEMPLATE_CONTENT', 125);
define('BRICKS_TRACK_TEMPLATE_INDEX', 126);

add_filter('bricks_track_bricks_templates', function ($t) {
    $t['content'] = 125;
    return $t;
});</pre>
        </div>
        <?php
    }
}

if (!function_exists('bricks_add_track_rewrite_rules')) {
    function bricks_add_track_rewrite_rules() {
        $base = bricks_track_directory_base();
        $regions = implode('|', array_map('preg_quote', bricks_track_reserved_region_slugs()));

        add_rewrite_tag('%track_slug%', '([a-z0-9-]+)');
        add_rewrite_tag('%tracks_index%', '([0-9]+)');
        add_rewrite_tag('%racecourses_index%', '([0-9]+)');
        add_rewrite_tag('%racecourses_region%', '([a-z0-9-]+)');

        add_rewrite_rule('^' . $base . '/?$', 'index.php?racecourses_index=1', 'top');
        add_rewrite_rule('^' . $base . '/(' . $regions . ')/?$', 'index.php?racecourses_region=$matches[1]', 'top');
        add_rewrite_rule('^' . $base . '/([a-z0-9-]+)/?$', 'index.php?track_slug=$matches[1]', 'top');

        // Legacy aliases (301 redirect to /racecourses/ on template_redirect).
        add_rewrite_rule('^tracks/?$', 'index.php?tracks_index=1', 'top');
        add_rewrite_rule('^tracks/([a-z0-9-]+)/?$', 'index.php?track_slug=$matches[1]', 'top');
    }
}
add_action('init', 'bricks_add_track_rewrite_rules', 20);

if (!function_exists('bricks_add_track_query_vars')) {
    function bricks_add_track_query_vars($vars) {
        $vars[] = 'track_slug';
        $vars[] = 'tracks_index';
        $vars[] = 'racecourses_index';
        $vars[] = 'racecourses_region';
        return $vars;
    }
}
add_filter('query_vars', 'bricks_add_track_query_vars');

if (!function_exists('bricks_track_legacy_url_redirect')) {
    function bricks_track_legacy_url_redirect() {
        if (is_admin()) {
            return;
        }

        $path = (string) (wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
        if ($path === '' || !preg_match('#^/tracks(?:/([^/]+)/?)?$#i', $path, $m)) {
            return;
        }

        $target = bricks_track_directory_url();
        if (!empty($m[1])) {
            $target = bricks_track_url(sanitize_title($m[1]));
        }

        wp_safe_redirect($target, 301);
        exit;
    }
}
add_action('template_redirect', 'bricks_track_legacy_url_redirect', 1);

if (!function_exists('bricks_track_template')) {
    function bricks_track_template($template) {
        if (is_admin()) {
            return $template;
        }
        if (
            get_query_var('track_slug')
            || get_query_var('tracks_index')
            || get_query_var('racecourses_index')
            || get_query_var('racecourses_region')
        ) {
            $custom = get_stylesheet_directory() . '/track-guide.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }
        return $template;
    }
}
add_filter('template_include', 'bricks_track_template');

if (!function_exists('bricks_flush_track_rewrite_rules_if_needed')) {
    function bricks_flush_track_rewrite_rules_if_needed() {
        if (get_option('bricks_track_rewrite_flushed') !== '2') {
            flush_rewrite_rules();
            update_option('bricks_track_rewrite_flushed', '2');
        }
    }
}
add_action('init', 'bricks_flush_track_rewrite_rules_if_needed', 999);

if (!function_exists('bricks_track_bump_registry_on_data_refresh')) {
    function bricks_track_bump_registry_on_data_refresh() {
        delete_transient('bricks_track_registry_v1');
    }
}
add_action('bricks_daily_filter_cache_flush', 'bricks_track_bump_registry_on_data_refresh');

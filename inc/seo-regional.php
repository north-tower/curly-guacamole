<?php
/**
 * UK & Ireland regional racing phrases for SEO titles, meta, schema, and on-page copy.
 *
 * Targets punter search terms (All-Weather speed figures, AW ratings, Nap of the Day)
 * and avoids US-centric wording (dirt tracks, handicapping systems).
 */

if (!function_exists('bricks_seo_regional_keywords')) {
    /**
     * @return array<int, string>
     */
    function bricks_seo_regional_keywords() {
        return (array) apply_filters('bricks_seo_regional_keywords', [
            'UK horse racing',
            'Irish horse racing',
            'speed ratings',
            'speed figures',
            'All-Weather speed figures',
            'AW ratings',
            'draw bias',
            'racecourse guide',
            'Nap of the Day',
            'race card',
            'going',
        ]);
    }
}

if (!function_exists('bricks_seo_is_all_weather_surface')) {
    function bricks_seo_is_all_weather_surface($track_type) {
        $t = strtolower(trim((string) $track_type));
        if ($t === '' || $t === 'turf') {
            return false;
        }
        if (preg_match('/all[\s_-]*weather|(^|\s)aw(\s|$)/i', $t)) {
            return true;
        }
        return in_array($t, [
            'polytrack',
            'tapeta',
            'fibresand',
            'fiber sand',
            'synthetic',
            'equitrack',
        ], true);
    }
}

if (!function_exists('bricks_seo_course_is_all_weather')) {
    function bricks_seo_course_is_all_weather($course) {
        $course = trim((string) $course);
        if ($course === '') {
            return false;
        }

        static $cache = [];
        if (array_key_exists($course, $cache)) {
            return $cache[$course];
        }

        $is_aw = false;

        if (function_exists('bricks_track_get_features_rows')) {
            foreach (bricks_track_get_features_rows($course) as $row) {
                if (bricks_seo_is_all_weather_surface($row->track_type ?? '')) {
                    $is_aw = true;
                    break;
                }
            }
        }

        if (!$is_aw && function_exists('bricks_track_get_draw_bias_summary')) {
            foreach (bricks_track_get_draw_bias_summary($course, 20) as $row) {
                if (bricks_seo_is_all_weather_surface($row->track_type ?? '')) {
                    $is_aw = true;
                    break;
                }
            }
        }

        $cache[$course] = $is_aw;
        return $is_aw;
    }
}

if (!function_exists('bricks_seo_race_is_all_weather')) {
    function bricks_seo_race_is_all_weather($race) {
        if (!$race) {
            return false;
        }
        if (!empty($race->track_type) && bricks_seo_is_all_weather_surface($race->track_type)) {
            return true;
        }
        $race_type = strtolower(trim((string) ($race->race_type ?? '')));
        if ($race_type !== '' && strpos($race_type, 'all weather') !== false) {
            return true;
        }
        return bricks_seo_course_is_all_weather($race->course ?? '');
    }
}

if (!function_exists('bricks_seo_surface_speed_phrase')) {
    /**
     * Primary surface-specific ratings phrase for titles and headings.
     */
    function bricks_seo_surface_speed_phrase($is_all_weather) {
        return $is_all_weather
            ? 'All-Weather Speed Figures & AW Ratings'
            : 'Turf Speed Ratings & Fhorsite Ratings';
    }
}

if (!function_exists('bricks_seo_surface_speed_phrase_short')) {
    function bricks_seo_surface_speed_phrase_short($is_all_weather) {
        return $is_all_weather ? 'All-Weather speed figures' : 'turf speed ratings';
    }
}

if (!function_exists('bricks_seo_is_dashboard_request')) {
    function bricks_seo_is_dashboard_request($slug) {
        if (!function_exists('bricks_request_uri_contains')) {
            return false;
        }
        return bricks_request_uri_contains(['/' . trim($slug, '/')]);
    }
}

if (!function_exists('bricks_seo_build_daily_meta_title')) {
    function bricks_seo_build_daily_meta_title() {
        return 'UK & Irish Race Cards Today | Turf & All-Weather Ratings | Fhorsite';
    }
}

if (!function_exists('bricks_seo_build_daily_meta_description')) {
    function bricks_seo_build_daily_meta_description() {
        return 'Today\'s UK and Irish race cards with course, class, runners, and links to turf speed ratings and All-Weather (AW) speed figures for every race.';
    }
}

if (!function_exists('bricks_seo_build_speed_meta_title')) {
    function bricks_seo_build_speed_meta_title() {
        return 'UK & Irish Speed Figures | Turf & All-Weather AW Ratings | Fhorsite';
    }
}

if (!function_exists('bricks_seo_build_speed_meta_description')) {
    function bricks_seo_build_speed_meta_description() {
        return 'Searchable UK and Irish speed figures and AW ratings across meetings, racecourses, trainers, and race types — turf and All-Weather cards.';
    }
}

if (!function_exists('bricks_seo_post_is_racing_content')) {
    function bricks_seo_post_is_racing_content($post_id = 0) {
        $post_id = $post_id > 0 ? $post_id : get_the_ID();
        if ($post_id <= 0) {
            return false;
        }

        $slugs = (array) apply_filters('bricks_seo_racing_category_slugs', [
            'horse-racing',
            'racing',
            'racing-tips',
            'tips',
            'nap',
            'nap-of-the-day',
            'all-weather',
            'aw-racing',
            'speed-ratings',
            'cheltenham-festival',
            'grand-national',
            'royal-ascot',
            'galway-festival',
            'racing-festivals',
            'case-study',
            'case-studies',
        ]);

        foreach ($slugs as $slug) {
            if (has_category($slug, $post_id)) {
                return true;
            }
        }

        if (function_exists('bricks_seo_post_is_case_study') && bricks_seo_post_is_case_study($post_id)) {
            return true;
        }

        return (bool) get_post_meta($post_id, 'bricks_is_racing_content', true);
    }
}

if (!function_exists('bricks_seo_post_mentions_all_weather')) {
    function bricks_seo_post_mentions_all_weather($post_id = 0) {
        $post_id = $post_id > 0 ? $post_id : get_the_ID();
        if ($post_id <= 0) {
            return false;
        }

        $haystack = strtolower(
            get_the_title($post_id) . ' '
            . wp_strip_all_tags((string) get_post_field('post_content', $post_id))
            . ' '
            . wp_strip_all_tags((string) get_post_field('post_excerpt', $post_id))
        );

        return (bool) preg_match(
            '/\b(all[\s-]?weather|aw ratings?|polytrack|tapeta|fibresand|synthetic)\b/i',
            $haystack
        );
    }
}

if (!function_exists('bricks_seo_post_mentions_nap')) {
    function bricks_seo_post_mentions_nap($post_id = 0) {
        $post_id = $post_id > 0 ? $post_id : get_the_ID();
        if ($post_id <= 0) {
            return false;
        }

        if (has_category(['nap', 'nap-of-the-day'], $post_id)) {
            return true;
        }

        $haystack = strtolower(
            get_the_title($post_id) . ' '
            . wp_strip_all_tags((string) get_post_field('post_content', $post_id))
        );

        return (bool) preg_match('/\bnap of the day\b/i', $haystack);
    }
}

if (!function_exists('bricks_seo_enhance_regional_post_meta_title')) {
    function bricks_seo_enhance_regional_post_meta_title($title, $post_id = 0) {
        $post_id = $post_id > 0 ? $post_id : get_the_ID();
        if ($post_id <= 0 || !bricks_seo_post_is_racing_content($post_id)) {
            return $title;
        }

        $title = trim((string) $title);
        if ($title === '') {
            return $title;
        }

        $lower = strtolower($title);
        if (
            strpos($lower, 'uk') !== false
            || strpos($lower, 'irish') !== false
            || strpos($lower, 'ireland') !== false
            || strpos($lower, 'all-weather') !== false
            || strpos($lower, 'aw ratings') !== false
            || strpos($lower, 'nap of the day') !== false
        ) {
            return $title;
        }

        $suffix_parts = ['UK & Irish Racing'];
        if (bricks_seo_post_mentions_all_weather($post_id)) {
            $suffix_parts[] = 'All-Weather';
        }
        if (bricks_seo_post_mentions_nap($post_id)) {
            $suffix_parts[] = 'Nap of the Day';
        }

        return $title . ' | ' . implode(' · ', $suffix_parts);
    }
}

if (!function_exists('bricks_seo_enhance_regional_post_meta_description')) {
    function bricks_seo_enhance_regional_post_meta_description($description, $post_id = 0) {
        $post_id = $post_id > 0 ? $post_id : get_the_ID();
        if ($post_id <= 0 || !bricks_seo_post_is_racing_content($post_id)) {
            return $description;
        }

        $description = trim((string) $description);
        $extras = [];

        if (bricks_seo_post_mentions_all_weather($post_id)
            && stripos($description, 'all-weather') === false
            && stripos($description, 'aw ratings') === false
        ) {
            $extras[] = 'All-Weather speed figures and AW ratings';
        }

        if (bricks_seo_post_mentions_nap($post_id) && stripos($description, 'nap of the day') === false) {
            $extras[] = 'Nap of the Day analysis';
        }

        if (stripos($description, 'racecourse') === false && stripos($description, 'draw bias') === false) {
            $extras[] = 'UK & Irish racecourse speed ratings and draw bias';
        }

        if (empty($extras)) {
            return $description;
        }

        $tail = implode('. ', $extras) . '.';
        return $description !== '' ? rtrim($description, '.') . '. ' . $tail : $tail;
    }
}

if (!function_exists('bricks_seo_regional_article_about_terms')) {
    /**
     * @return array<int, array<string, string>>
     */
    function bricks_seo_regional_article_about_terms($post_id = 0) {
        $terms = [
            ['@type' => 'Thing', 'name' => 'UK horse racing'],
            ['@type' => 'Thing', 'name' => 'Irish horse racing'],
            ['@type' => 'Thing', 'name' => 'Speed ratings'],
        ];

        if (bricks_seo_post_mentions_all_weather($post_id)) {
            $terms[] = ['@type' => 'Thing', 'name' => 'All-Weather speed figures'];
            $terms[] = ['@type' => 'Thing', 'name' => 'AW ratings'];
        }

        if (bricks_seo_post_mentions_nap($post_id)) {
            $terms[] = ['@type' => 'Thing', 'name' => 'Nap of the Day'];
        }

        return $terms;
    }
}

if (!function_exists('bricks_seo_register_regional_meta_filters')) {
    function bricks_seo_register_regional_meta_filters() {
        add_filter('slim_seo_meta_title', function ($title) {
            if (bricks_seo_is_dashboard_request('daily')) {
                return bricks_seo_build_daily_meta_title();
            }
            if (bricks_seo_is_dashboard_request('speed')) {
                return bricks_seo_build_speed_meta_title();
            }
            if (is_singular(['post', 'page'])) {
                return bricks_seo_enhance_regional_post_meta_title($title, get_the_ID());
            }
            return $title;
        }, 28);

        add_filter('slim_seo_meta_description', function ($description) {
            if (bricks_seo_is_dashboard_request('daily')) {
                return bricks_seo_build_daily_meta_description();
            }
            if (bricks_seo_is_dashboard_request('speed')) {
                return bricks_seo_build_speed_meta_description();
            }
            if (is_singular(['post', 'page'])) {
                return bricks_seo_enhance_regional_post_meta_description($description, get_the_ID());
            }
            return $description;
        }, 28);

        add_filter('pre_get_document_title', function ($title) {
            if (bricks_seo_is_dashboard_request('daily')) {
                return bricks_seo_build_daily_meta_title();
            }
            if (bricks_seo_is_dashboard_request('speed')) {
                return bricks_seo_build_speed_meta_title();
            }
            if (is_singular(['post', 'page'])) {
                return bricks_seo_enhance_regional_post_meta_title($title, get_the_ID());
            }
            return $title;
        }, 28);
    }
}
add_action('init', 'bricks_seo_register_regional_meta_filters');

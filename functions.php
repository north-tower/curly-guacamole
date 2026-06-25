<?php 
/**
 * Register/enqueue custom scripts and styles hghg
 */
add_action( 'wp_enqueue_scripts', function() {
	// Enqueue your files on the canvas & frontend, not the builder panel. Otherwise custom CSS might affect builder)
	if ( ! bricks_is_builder_main() ) {
		wp_enqueue_style( 'bricks-child', get_stylesheet_uri(), ['bricks-frontend'], filemtime( get_stylesheet_directory() . '/style.css' ) );
	}
} );

/**
 * Register custom elements
 */
add_action( 'init', function() {
  $element_files = [
    __DIR__ . '/elements/title.php',
  ];

  foreach ( $element_files as $file ) {
    \Bricks\Elements::register_element( $file );
  }
}, 11 );

/**
 * Add text strings to builder
 */
add_filter( 'bricks/builder/i18n', function( $i18n ) {
  // For element category 'custom'
  $i18n['custom'] = esc_html__( 'Custom', 'bricks' );

  return $i18n;
} );

require_once __DIR__ . '/inc/helpers-core.php';
require_once __DIR__ . '/inc/enqueue.php';
require_once __DIR__ . '/inc/rewrites.php';
require_once __DIR__ . '/inc/seo.php';
require_once __DIR__ . '/inc/seo-regional.php';
require_once __DIR__ . '/inc/tracker.php';
require_once __DIR__ . '/inc/race-table.php';
require_once __DIR__ . '/inc/speed-performance.php';
require_once __DIR__ . '/inc/horse-history.php';
require_once __DIR__ . '/inc/race-comments.php';
require_once __DIR__ . '/inc/sire-insights.php';
require_once __DIR__ . '/inc/yesterday-winners.php';
require_once __DIR__ . '/inc/racecourse-guides.php';
require_once __DIR__ . '/inc/racing-festivals.php';
require_once __DIR__ . '/inc/proven-winners.php';
require_once __DIR__ . '/inc/admin-pnl.php';
require_once __DIR__ . '/inc/points-published-picks.php';
require_once __DIR__ . '/inc/points-today-picks.php';


/**
 * Bricks Builder - Race Table Standalone Code
 * PART 1: Add this to your child theme's functions.php or a code snippets plugin
 */



// Header Meta Box Callback
// Header Meta Box Callback






// Get active landing page content


// Get active header content



// Shortcode for header content
// Shortcode for header content





// Enqueue WordPress media scripts for admin















/**
 * Bricks Builder - Race Detail Page
 * PART 1: Add this to your child theme's functions.php or code snippets plugin
 */

// ==============================================
// HELPER FUNCTION
// ==============================================

if (!function_exists('get_last_year_winner')) {
    function get_last_year_winner($course, $race_title, $current_date) {
        global $wpdb;
        
        $current_year = date('Y', strtotime($current_date));
        $last_year = $current_year - 1;
        
        // Use daily_comment_history which has finish_position for past results
        $similar_races = $wpdb->get_results($wpdb->prepare(
            "SELECT name as winner_name, starting_price, course, race_title, meeting_date
             FROM daily_comment_history
             WHERE course = %s 
             AND meeting_date LIKE %s
             AND (race_title LIKE %s OR race_title LIKE %s)
             AND finish_position = 1
             ORDER BY meeting_date DESC
             LIMIT 1",
            $course,
            $last_year . '%',
            '%' . substr($race_title, 0, 10) . '%',
            '%' . substr($race_title, -10) . '%'
        ));
        
        if (!empty($similar_races)) {
            $race = $similar_races[0];
            return [
                'winner_name' => $race->winner_name ?: 'Unknown',
                'odds' => $race->starting_price ?: 'N/A',
                'trainer_name' => 'N/A'
            ];
        }
        
        return false;
    }
}

if (!function_exists('get_course_features')) {
    function get_course_features($course_name) {
        global $wpdb;
        
        if (empty($course_name)) {
            return null;
        }
        
        // Get course features from course_features table
        $course_features = $wpdb->get_row($wpdb->prepare(
            "SELECT race_code, profile, general_features, specific_features, direction
             FROM course_features 
             WHERE course = %s 
             LIMIT 1",
            $course_name
        ));
        
        return $course_features;
    }
}


// ==============================================
// RACE DETAIL SHORTCODE
// ==============================================

if (!function_exists('bricks_parse_foaling_month')) {
    function bricks_parse_foaling_month($foaling_date) {
        if (empty($foaling_date) || $foaling_date === '0000-00-00') {
            return null;
        }

        $ts = strtotime($foaling_date);
        if ($ts === false) {
            return null;
        }

        $month = intval(date('n', $ts));
        return ($month >= 1 && $month <= 12) ? $month : null;
    }
}

if (!function_exists('bricks_is_2yo_flat_race_for_maturity')) {
    function bricks_is_2yo_flat_race_for_maturity($race, $is_national_hunt) {
        if (!$race || $is_national_hunt) {
            return false;
        }

        $age_range = isset($race->age_range) ? trim((string) $race->age_range) : '';
        if ($age_range === '') {
            return false;
        }

        // Match values like "2", "2yo", "2yo+", "2 yrs" etc.
        return (bool) preg_match('/^2(\D|$)/i', $age_range);
    }
}

if (!function_exists('bricks_calculate_maturity_edge')) {
    function bricks_calculate_maturity_edge($foaling_date, $race_date) {
        $birth_month = bricks_parse_foaling_month($foaling_date);
        if ($birth_month === null) {
            return null;
        }

        $race_ts = strtotime($race_date);
        if ($race_ts === false) {
            return null;
        }

        $race_month = intval(date('n', $race_ts));
        $month_names = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        // Non-linear baseline from the brief: Feb/Mar strongest, Jan slightly behind them.
        $base_score_by_month = [
            1 => 2, 2 => 4, 3 => 4, 4 => 2,
            5 => 0, 6 => -2, 7 => -3, 8 => -4,
            9 => -5, 10 => -6, 11 => -6, 12 => -6,
        ];
        $score = $base_score_by_month[$birth_month];

        // Season-phase adjustment: stronger effect in spring; persistent but softer in late season.
        if ($race_month >= 3 && $race_month <= 6) {
            if (in_array($birth_month, [2, 3], true)) {
                $score += 1;
            } elseif ($birth_month >= 5) {
                $score -= 1;
            }
        } elseif ($race_month >= 7 && $race_month <= 10) {
            if ($birth_month === 1 || $birth_month === 4) {
                $score -= 1;
            } elseif ($birth_month >= 5) {
                $score -= 2;
            }
        }

        // Clamp for predictable display bands.
        $score = max(-8, min(5, $score));

        $label = 'Neutral';
        $css_class = 'neutral';
        if ($score >= 4) {
            $label = 'Strong Edge';
            $css_class = 'strong';
        } elseif ($score >= 2) {
            $label = 'Edge';
            $css_class = 'positive';
        } elseif ($score <= -4) {
            $label = 'High Risk';
            $css_class = 'negative';
        } elseif ($score <= -1) {
            $label = 'Risk';
            $css_class = 'caution';
        }

        $backtest_to = date('Y-m-d', strtotime('-1 day'));
        $backtest_from = date('Y-m-d', strtotime('-5 years', strtotime($backtest_to)));
        $tooltip = sprintf(
            'Maturity Edge model (2YO Flat): DOB %s, race month %s. Methodology follows the locked backtest window (%s to %s), 2YO Flat focus, and Mar-Oct season scope. Non-linear signal: Feb/Mar strongest, Jan slightly lower, later foals generally disadvantaged.',
            $month_names[$birth_month],
            $month_names[$race_month],
            $backtest_from,
            $backtest_to
        );

        return [
            'score' => $score,
            'label' => $label,
            'class' => $css_class,
            'birth_month' => $birth_month,
            'tooltip' => $tooltip,
        ];
    }
}

if (!function_exists('bricks_points_default_weights')) {
    function bricks_points_default_weights() {
        return [
            'base' => 50.0,
            'fsr_scale' => 0.35,
            'fsrr_scale' => 0.08,
            'sr_recent_scale' => 0.12,
            'draw_bias_scale' => 0.10,
            'trainer_14d_scale' => 0.18,
            'trainer_course_scale' => 0.20,
            'comb_scale' => 0.10,
            'days_since_ran_penalty' => 0.08,
            'class_diff_penalty' => 0.30,
            'or_diff_bonus' => 0.15,
            'cdg_bonus' => 1.2,
            'lbf_bonus' => 0.8,
            'maturity_edge_scale' => 0.70
        ];
    }
}

if (!function_exists('bricks_points_parse_decimal_odds')) {
    function bricks_points_parse_decimal_odds($forecast_decimal, $forecast_fractional = '') {
        if ($forecast_decimal !== null && $forecast_decimal !== '' && is_numeric($forecast_decimal)) {
            $decimal = floatval($forecast_decimal);
            return $decimal > 1 ? $decimal : null;
        }

        $fractional = trim((string) $forecast_fractional);
        if ($fractional === '') {
            return null;
        }

        if (function_exists('convert_fractional_to_decimal')) {
            $converted = convert_fractional_to_decimal($fractional);
            if ($converted !== null && is_numeric($converted) && floatval($converted) > 1) {
                return floatval($converted);
            }
        }

        if (strpos($fractional, '/') !== false) {
            $parts = explode('/', $fractional);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]) && floatval($parts[1]) > 0) {
                return (floatval($parts[0]) / floatval($parts[1])) + 1;
            }
        }

        return null;
    }
}

if (!function_exists('bricks_points_market_implied_rank')) {
    function bricks_points_market_implied_rank($odds_decimal) {
        if ($odds_decimal === null || !is_numeric($odds_decimal) || floatval($odds_decimal) <= 1) {
            return 0;
        }
        return 1 / floatval($odds_decimal);
    }
}

if (!function_exists('bricks_points_score_runner')) {
    function bricks_points_score_runner($runner, $speed_data, $race_context = []) {
        $w = bricks_points_default_weights();
        $score = $w['base'];
        $reasons = [];

        $fsr = ($speed_data && isset($speed_data->fhorsite_rating) && is_numeric($speed_data->fhorsite_rating)) ? floatval($speed_data->fhorsite_rating) : null;
        if ($fsr !== null) {
            $delta = ($fsr - 70.0) * $w['fsr_scale'];
            $score += $delta;
            if ($delta >= 2.0) $reasons[] = 'Strong FSr';
        }

        $fsrr = ($speed_data && isset($speed_data->fhorsite_rating_reliability) && is_numeric($speed_data->fhorsite_rating_reliability)) ? floatval($speed_data->fhorsite_rating_reliability) : null;
        if ($fsrr !== null) {
            $delta = ($fsrr - 50.0) * $w['fsrr_scale'];
            $score += $delta;
            if ($delta >= 1.5) $reasons[] = 'Reliable rating';
        }

        $sr_recent = [];
        foreach (['SR_LTO', 'SR_2', 'SR_3'] as $k) {
            if ($speed_data && isset($speed_data->{$k}) && is_numeric($speed_data->{$k})) {
                $sr_recent[] = floatval($speed_data->{$k});
            }
        }
        if (!empty($sr_recent)) {
            $avg_sr = array_sum($sr_recent) / count($sr_recent);
            $delta = ($avg_sr - 70.0) * $w['sr_recent_scale'];
            $score += $delta;
            if ($delta >= 1.5) $reasons[] = 'Solid recent speed';
        }

        $draw_bias_pct = ($speed_data && isset($speed_data->draw_bias_pct) && is_numeric($speed_data->draw_bias_pct)) ? floatval($speed_data->draw_bias_pct) : null;
        if (!empty($race_context['is_flat']) && $draw_bias_pct !== null) {
            $delta = ($draw_bias_pct - 10.0) * $w['draw_bias_scale'];
            $score += $delta;
            if ($delta >= 1.0) $reasons[] = 'Positive draw bias';
        }

        $trainer_14d = null;
        if ($speed_data && isset($speed_data->TnrWinPct14d) && is_numeric($speed_data->TnrWinPct14d)) {
            $trainer_14d = floatval($speed_data->TnrWinPct14d);
        } elseif (isset($race_context['rft_pct']) && is_numeric($race_context['rft_pct'])) {
            $trainer_14d = floatval($race_context['rft_pct']);
        }
        if ($trainer_14d !== null) {
            $delta = ($trainer_14d - 12.0) * $w['trainer_14d_scale'];
            $score += $delta;
            if ($delta >= 1.0) $reasons[] = 'Trainer in form';
        }

        if (isset($race_context['trainer_course_pct']) && is_numeric($race_context['trainer_course_pct'])) {
            $tc = floatval($race_context['trainer_course_pct']);
            $delta = ($tc - 10.0) * $w['trainer_course_scale'];
            $score += $delta;
            if ($delta >= 1.0) $reasons[] = 'Trainer course record';
        }

        if ($speed_data && isset($speed_data->TnrJkyPlacePct) && is_numeric($speed_data->TnrJkyPlacePct)) {
            $comb = floatval($speed_data->TnrJkyPlacePct);
            $delta = ($comb - 50.0) * $w['comb_scale'];
            $score += $delta;
        }

        if ($speed_data && isset($speed_data->days_since_ran) && is_numeric($speed_data->days_since_ran)) {
            $days = floatval($speed_data->days_since_ran);
            $score -= max(0.0, $days - 35.0) * $w['days_since_ran_penalty'];
        }

        if ($speed_data && isset($speed_data->class_diff) && is_numeric($speed_data->class_diff)) {
            $class_diff = floatval($speed_data->class_diff);
            $score -= max(0.0, $class_diff) * $w['class_diff_penalty'];
            $score += max(0.0, -$class_diff) * ($w['class_diff_penalty'] * 0.6);
        }

        if ($speed_data && isset($speed_data->official_rating_diff) && is_numeric($speed_data->official_rating_diff)) {
            $or_diff = floatval($speed_data->official_rating_diff);
            $score += $or_diff * $w['or_diff_bonus'];
        }

        if ($speed_data) {
            $cdg_count = 0;
            foreach (['candd_winner', 'course_winner', 'distance_winner', 'going_prev_wins'] as $k) {
                if (isset($speed_data->{$k}) && is_numeric($speed_data->{$k})) {
                    $cdg_count += max(0, intval($speed_data->{$k}));
                }
            }
            if ($cdg_count > 0) {
                $score += min(3.0, $cdg_count * $w['cdg_bonus']);
                $reasons[] = 'Course/Distance profile';
            }
        }

        if ($speed_data && isset($speed_data->beaten_favourite) && is_numeric($speed_data->beaten_favourite) && intval($speed_data->beaten_favourite) > 0) {
            $score += $w['lbf_bonus'];
        }

        if (isset($race_context['maturity_edge_score']) && is_numeric($race_context['maturity_edge_score'])) {
            $score += floatval($race_context['maturity_edge_score']) * $w['maturity_edge_scale'];
        }

        $score = max(0.0, min(100.0, round($score, 1)));

        return [
            'score' => $score,
            'reasons' => array_values(array_unique(array_slice($reasons, 0, 3)))
        ];
    }
}

if (!function_exists('bricks_points_pick_winner_place')) {
    function bricks_points_pick_winner_place($scored_runners) {
        if (empty($scored_runners)) {
            return ['winner' => null, 'place' => []];
        }

        $eligible = array_values(array_filter($scored_runners, function($r) {
            return empty($r['is_non_runner']);
        }));
        usort($eligible, function($a, $b) {
            return ($b['model_score'] ?? 0) <=> ($a['model_score'] ?? 0);
        });

        return [
            'winner' => $eligible[0] ?? null,
            'place' => array_slice($eligible, 0, 3)
        ];
    }
}

if (!function_exists('bricks_points_pick_each_way_simple')) {
    function bricks_points_pick_each_way_simple($scored_runners) {
        $eligible = array_values(array_filter($scored_runners, function($r) {
            return empty($r['is_non_runner']) && isset($r['odds_fractional']) && isset($r['odds_decimal']) &&
                $r['odds_decimal'] !== null && floatval($r['odds_decimal']) >= 6.0;
        }));
        usort($eligible, function($a, $b) {
            return ($b['model_score'] ?? 0) <=> ($a['model_score'] ?? 0);
        });
        return $eligible[0] ?? null;
    }
}

if (!function_exists('bricks_points_pick_each_way_edge')) {
    function bricks_points_pick_each_way_edge($scored_runners) {
        $eligible = array_values(array_filter($scored_runners, function($r) {
            if (!empty($r['is_non_runner'])) return false;
            if (!isset($r['odds_decimal']) || $r['odds_decimal'] === null || floatval($r['odds_decimal']) < 6.0) return false;
            if (!isset($r['edge_score']) || floatval($r['edge_score']) < 6.0) return false;
            if (!isset($r['model_rank']) || intval($r['model_rank']) <= 0 || intval($r['model_rank']) > 5) return false;
            if (!isset($r['market_rank']) || intval($r['market_rank']) <= 0) return false;
            return (intval($r['market_rank']) - intval($r['model_rank'])) >= 2;
        }));
        usort($eligible, function($a, $b) {
            return ($b['edge_score'] ?? 0) <=> ($a['edge_score'] ?? 0);
        });
        return $eligible[0] ?? null;
    }
}

if (!function_exists('bricks_points_place_terms_count')) {
    function bricks_points_place_terms_count($field_size) {
        $field_size = intval($field_size);
        if ($field_size >= 16) return 4;
        if ($field_size >= 8) return 3;
        if ($field_size >= 5) return 2;
        return 1;
    }
}

if (!function_exists('bricks_points_table_has_column')) {
    function bricks_points_table_has_column($table_name, $column_name) {
        global $wpdb;
        static $cache = [];
        $key = $table_name . '::' . $column_name;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table_name` LIKE %s", $column_name));
        $cache[$key] = !empty($col);
        return $cache[$key];
    }
}

if (!function_exists('bricks_points_backtest_latest_historic_meeting_date')) {
    function bricks_points_backtest_latest_historic_meeting_date() {
        global $wpdb;
        if ($wpdb->get_var("SHOW TABLES LIKE 'historic_races_beta'") !== 'historic_races_beta') {
            return '';
        }
        return (string) $wpdb->get_var('SELECT MAX(meeting_date) FROM `historic_races_beta`');
    }
}

if (!function_exists('bricks_points_meeting_dates_in_range')) {
    /**
     * @return string[]
     */
    function bricks_points_meeting_dates_in_range($from_date, $to_date) {
        $from_ts = strtotime($from_date);
        $to_ts = strtotime($to_date);
        if ($from_ts === false || $to_ts === false || $from_ts > $to_ts) {
            return [];
        }
        $dates = [];
        for ($ts = $from_ts; $ts <= $to_ts; $ts += DAY_IN_SECONDS) {
            $dates[] = wp_date('Y-m-d', $ts);
        }
        return $dates;
    }
}

if (!function_exists('bricks_points_backtest_fetch_dch_rows_for_dates')) {
    /**
     * Recent meeting cards often land in daily_comment_history before historic ETL.
     *
     * @param string[] $meeting_dates Y-m-d list
     * @return object[]
     */
    function bricks_points_backtest_fetch_dch_rows_for_dates(array $meeting_dates, $race_type_filter = '') {
        global $wpdb;

        $meeting_dates = array_values(array_filter(array_unique(array_map('strval', $meeting_dates)), function($d) {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        }));
        if (empty($meeting_dates)) {
            return [];
        }

        $dch_table = 'daily_comment_history';
        if ($wpdb->get_var("SHOW TABLES LIKE '$dch_table'") !== $dch_table) {
            return [];
        }

        $select_parts = [
            'dch.race_id AS race_id',
            'dch.runner_id AS runner_id',
            'dch.meeting_date AS meeting_date',
            'dch.course AS course',
            'dch.race_type AS race_type',
            'dch.name AS horse_name',
            "'' AS trainer_name",
            'dch.finish_position AS finish_position',
            'dch.starting_price AS starting_price',
            'NULL AS starting_price_decimal',
        ];

        if (bricks_points_table_has_column($dch_table, 'scheduled_time')) {
            $select_parts[] = 'dch.scheduled_time AS scheduled_time';
        } else {
            $select_parts[] = 'NULL AS scheduled_time';
        }
        if (bricks_points_table_has_column($dch_table, 'race_title')) {
            $select_parts[] = 'dch.race_title AS race_title';
        } else {
            $select_parts[] = "'' AS race_title";
        }

        $non_runners_exists = $wpdb->get_var("SHOW TABLES LIKE 'non_runners'") === 'non_runners';
        $non_runner_join = '';
        if ($non_runners_exists) {
            $non_runner_join = ' LEFT JOIN non_runners nr ON nr.race_id = dch.race_id AND nr.runner_id = dch.runner_id ';
            $select_parts[] = 'CASE WHEN nr.runner_id IS NOT NULL THEN 1 ELSE 0 END AS is_non_runner';
        } else {
            $select_parts[] = '0 AS is_non_runner';
        }

        $select_parts[] = 'dch.speed_rating AS dch_speed_rating';
        $select_parts[] = 'dch.wt_speed_rating AS dch_wt_speed_rating';
        $select_parts[] = 'NULL AS fhorsite_rating';
        $select_parts[] = 'NULL AS fhorsite_rating_reliability';
        $select_parts[] = 'NULL AS SR_LTO';
        $select_parts[] = 'NULL AS SR_2';
        $select_parts[] = 'NULL AS SR_3';
        $select_parts[] = 'NULL AS sp_forecast_price_decimal';

        $optional_cols = [
            'speed_rating' => 'speed_rating',
            'wt_speed_rating' => 'wt_speed_rating',
            'days_since_ran' => 'days_since_ran',
            'draw_bias_pct' => 'draw_bias_pct',
            'class_diff' => 'class_diff',
            'official_rating_diff' => 'official_rating_diff',
            'course_winner' => 'course_winner',
            'distance_winner' => 'distance_winner',
            'candd_winner' => 'candd_winner',
            'going_prev_wins' => 'going_prev_wins',
            'beaten_favourite' => 'beaten_favourite',
            'TnrWinPct14d' => 'TnrWinPct14d',
            'TnrJkyPlacePct' => 'TnrJkyPlacePct',
            'forecast_price_decimal' => 'forecast_price_decimal',
        ];
        foreach ($optional_cols as $alias => $column_name) {
            if (bricks_points_table_has_column($dch_table, $column_name)) {
                $select_parts[] = "dch.`$column_name` AS `$alias`";
            } else {
                $select_parts[] = "NULL AS `$alias`";
            }
        }

        $placeholders = implode(', ', array_fill(0, count($meeting_dates), '%s'));
        $sql = 'SELECT ' . implode(",\n", $select_parts) . "
            FROM `$dch_table` dch
            $non_runner_join
            WHERE dch.meeting_date IN ($placeholders)
              AND dch.name IS NOT NULL
              AND dch.name != ''";

        $params = $meeting_dates;
        if ($race_type_filter !== '') {
            $sql .= ' AND dch.race_type = %s';
            $params[] = $race_type_filter;
        }
        $sql .= ' ORDER BY dch.meeting_date ASC, dch.race_id ASC';

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('bricks_points_backtest_fetch_rows')) {
    function bricks_points_backtest_fetch_rows($from_date, $to_date, $race_type_filter = '', &$fetch_meta = null) {
        global $wpdb;

        $fetch_meta = [
            'historic_row_count' => 0,
            'dch_dates' => [],
            'dch_row_count' => 0,
        ];

        $historic_runners = 'historic_runners_beta';
        $historic_races = 'historic_races_beta';
        $historic_ok = (
            $wpdb->get_var("SHOW TABLES LIKE '$historic_runners'") === $historic_runners
            && $wpdb->get_var("SHOW TABLES LIKE '$historic_races'") === $historic_races
        );
        $rows = [];
        if (!$historic_ok) {
            $dch_only = bricks_points_backtest_fetch_dch_rows_for_dates(
                bricks_points_meeting_dates_in_range($from_date, $to_date),
                $race_type_filter
            );
            $fetch_meta['dch_dates'] = bricks_points_meeting_dates_in_range($from_date, $to_date);
            $fetch_meta['dch_row_count'] = count($dch_only);
            return $dch_only;
        }

        // IMPORTANT: Backtest must be deterministic for a historical date.
        // Use only immutable historic tables for core race identity fields.
        $race_type_expr = "hracb.race_type";

        $dch_exists = $wpdb->get_var("SHOW TABLES LIKE 'daily_comment_history'") === 'daily_comment_history';
        $finish_expr = $dch_exists
            ? "COALESCE(NULLIF(TRIM(hrunb.finish_position), ''), CAST(dch.finish_position AS CHAR)) AS finish_position"
            : 'hrunb.finish_position AS finish_position';

        $select_parts = [
            "hrunb.race_id AS race_id",
            "hrunb.runner_id AS runner_id",
            "hracb.meeting_date AS meeting_date",
            "hracb.course AS course",
            "$race_type_expr AS race_type",
            "hrunb.name AS horse_name",
            "hrunb.trainer_name AS trainer_name",
            $finish_expr,
            "hrunb.starting_price AS starting_price"
        ];

        if (bricks_points_table_has_column($historic_runners, 'starting_price_decimal')) {
            $select_parts[] = 'hrunb.starting_price_decimal AS starting_price_decimal';
        } else {
            $select_parts[] = 'NULL AS starting_price_decimal';
        }
        if (bricks_points_table_has_column($historic_races, 'scheduled_time')) {
            $select_parts[] = 'hracb.scheduled_time AS scheduled_time';
        } else {
            $select_parts[] = 'NULL AS scheduled_time';
        }
        if (bricks_points_table_has_column($historic_races, 'race_title')) {
            $select_parts[] = 'hracb.race_title AS race_title';
        } else {
            $select_parts[] = "'' AS race_title";
        }

        // Match race-card eligibility: exclude non-runners (pulled/NR) using the dedicated lookup table.
        $non_runners_exists = $wpdb->get_var("SHOW TABLES LIKE 'non_runners'") === 'non_runners';
        $non_runner_join = '';
        if ($non_runners_exists) {
            $non_runner_join = " LEFT JOIN non_runners nr ON nr.race_id = hrunb.race_id AND nr.runner_id = hrunb.runner_id ";
            $select_parts[] = "CASE WHEN nr.runner_id IS NOT NULL THEN 1 ELSE 0 END AS is_non_runner";
        } else {
            $select_parts[] = "0 AS is_non_runner";
        }

        // Deterministic historical inputs for scoring:
        // - Use `daily_comment_history` for speed ratings per meeting_date (historical table).
        // - Avoid `speed&performance_table` (mutable / derived).
        $dch_join = '';
        if ($dch_exists) {
            $dch_join = " LEFT JOIN daily_comment_history dch
                          ON dch.race_id = hrunb.race_id
                         AND dch.runner_id = hrunb.runner_id
                         AND dch.meeting_date = hracb.meeting_date ";
            $select_parts[] = 'dch.speed_rating AS dch_speed_rating';
            $select_parts[] = 'dch.wt_speed_rating AS dch_wt_speed_rating';
        } else {
            $select_parts[] = 'NULL AS dch_speed_rating';
            $select_parts[] = 'NULL AS dch_wt_speed_rating';
        }

        // Point-in-time Fhorsite (comment rating) from backtest_cr_data when the DB pipeline has been run.
        $bcr_join = '';
        if ($wpdb->get_var("SHOW TABLES LIKE 'backtest_cr_data'") === 'backtest_cr_data') {
            $bcr_join = ' LEFT JOIN backtest_cr_data bcr ON bcr.race_id = hrunb.race_id AND bcr.runner_id = hrunb.runner_id ';
            $select_parts[] = 'bcr.CR AS fhorsite_rating';
            $select_parts[] = 'NULL AS fhorsite_rating_reliability';
        } else {
            $select_parts[] = 'NULL AS fhorsite_rating';
            $select_parts[] = 'NULL AS fhorsite_rating_reliability';
        }
        $select_parts[] = 'NULL AS SR_LTO';
        $select_parts[] = 'NULL AS SR_2';
        $select_parts[] = 'NULL AS SR_3';
        $select_parts[] = 'NULL AS sp_forecast_price_decimal';

        $optional_cols = [
            'speed_rating' => 'speed_rating',
            'wt_speed_rating' => 'wt_speed_rating',
            'days_since_ran' => 'days_since_ran',
            'draw_bias_pct' => 'draw_bias_pct',
            'class_diff' => 'class_diff',
            'official_rating_diff' => 'official_rating_diff',
            'course_winner' => 'course_winner',
            'distance_winner' => 'distance_winner',
            'candd_winner' => 'candd_winner',
            'going_prev_wins' => 'going_prev_wins',
            'beaten_favourite' => 'beaten_favourite',
            'TnrWinPct14d' => 'TnrWinPct14d',
            'TnrJkyPlacePct' => 'TnrJkyPlacePct',
            'forecast_price_decimal' => 'forecast_price_decimal'
        ];

        foreach ($optional_cols as $alias => $column_name) {
            if (bricks_points_table_has_column($historic_runners, $column_name)) {
                $select_parts[] = "hrunb.`$column_name` AS `$alias`";
            } else {
                $select_parts[] = "NULL AS `$alias`";
            }
        }

        $sql = "SELECT " . implode(",\n", $select_parts) . "
            FROM `$historic_runners` hrunb
            INNER JOIN `$historic_races` hracb ON hracb.race_id = hrunb.race_id
            $non_runner_join
            $dch_join
            $bcr_join
            WHERE hracb.meeting_date BETWEEN %s AND %s
              AND hrunb.name IS NOT NULL
              AND hrunb.name != ''";

        $params = [$from_date, $to_date];
        if ($race_type_filter !== '') {
            $sql .= " AND $race_type_expr = %s";
            $params[] = $race_type_filter;
        }
        $sql .= " ORDER BY hracb.meeting_date ASC, hrunb.race_id ASC";

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        if (!is_array($rows)) {
            $rows = [];
        }
        $fetch_meta['historic_row_count'] = count($rows);

        $dates_in_range = bricks_points_meeting_dates_in_range($from_date, $to_date);
        $dates_with_historic = [];
        foreach ($rows as $row) {
            if (!empty($row->meeting_date)) {
                $dates_with_historic[(string) $row->meeting_date] = true;
            }
        }
        $missing_dates = array_values(array_filter($dates_in_range, function($d) use ($dates_with_historic) {
            return empty($dates_with_historic[$d]);
        }));
        if (!empty($missing_dates)) {
            $dch_rows = bricks_points_backtest_fetch_dch_rows_for_dates($missing_dates, $race_type_filter);
            if (!empty($dch_rows)) {
                $fetch_meta['dch_dates'] = $missing_dates;
                $fetch_meta['dch_row_count'] = count($dch_rows);
                $rows = array_merge($rows, $dch_rows);
                usort($rows, function($a, $b) {
                    $dc = strcmp((string) ($a->meeting_date ?? ''), (string) ($b->meeting_date ?? ''));
                    if ($dc !== 0) {
                        return $dc;
                    }
                    return intval($a->race_id ?? 0) <=> intval($b->race_id ?? 0);
                });
            }
        }

        return $rows;
    }
}

if (!function_exists('bricks_points_backtest_calculate')) {
  /**
   * @param string $backtest_mode 'model' (recompute picks) or 'published' (saved race-card picks)
   */
    function bricks_points_backtest_calculate($from_date, $to_date, $race_type_filter = '', $backtest_mode = 'model') {
        $backtest_mode = ($backtest_mode === 'published') ? 'published' : 'model';
        $fetch_meta = [];
        $rows = bricks_points_backtest_fetch_rows($from_date, $to_date, $race_type_filter, $fetch_meta);
        if (empty($rows)) {
            return [
                'summary' => [],
                'sample_rows' => [],
                'race_count' => 0,
                'runner_count' => 0,
                'unsettled_win_bets' => 0,
                'missing_published_snapshot' => 0,
                'backtest_mode' => $backtest_mode,
                'fetch_meta' => $fetch_meta,
                'latest_historic_date' => bricks_points_backtest_latest_historic_meeting_date(),
            ];
        }

        $day_span = max(1, (int) floor((strtotime($to_date) - strtotime($from_date)) / 86400) + 1);
        $sample_limit = ($day_span <= 1) ? 9999 : (($day_span <= 7) ? 500 : 120);

        $by_race = [];
        foreach ($rows as $row) {
            $rid = isset($row->race_id) ? intval($row->race_id) : 0;
            if ($rid <= 0) continue;
            if (!isset($by_race[$rid])) $by_race[$rid] = [];
            $by_race[$rid][] = $row;
        }

        $stats = [
            'win' => ['bets' => 0, 'profit' => 0.0, 'hits' => 0],
            'place' => ['bets' => 0, 'profit' => 0.0, 'hits' => 0],
            'ew_simple' => ['bets' => 0, 'profit' => 0.0, 'hits' => 0],
            'ew_edge' => ['bets' => 0, 'profit' => 0.0, 'hits' => 0]
        ];
        $sample_rows = [];
        $unsettled_win_bets = 0;
        $missing_published_snapshot = 0;

        foreach ($by_race as $race_id => $race_rows) {
            $field_size = count($race_rows);
            if ($field_size < 2) {
                continue;
            }

            $scored = bricks_points_backtest_score_race($race_rows);
            $meeting_date = (string) ($race_rows[0]->meeting_date ?? '');

            if ($backtest_mode === 'published') {
                $snapshot = function_exists('bricks_points_published_picks_get')
                    ? bricks_points_published_picks_get($race_id, $meeting_date)
                    : null;
                if (!$snapshot || empty($snapshot['win_horse'])) {
                    $missing_published_snapshot += 1;
                    continue;
                }
                $published = bricks_points_picks_from_published_snapshot($snapshot, $scored);
                $picks = ['winner' => $published['winner'], 'place' => $published['place']];
                $ew_simple = $published['ew_simple'];
                $ew_edge = $published['ew_edge'];
            } else {
                $picks = bricks_points_pick_winner_place($scored);
                $ew_simple = bricks_points_pick_each_way_simple($scored);
                $ew_edge = bricks_points_pick_each_way_edge($scored);
            }
            $place_terms = bricks_points_place_terms_count($field_size);

            $pick_odds = function($pick) {
                if (!$pick) {
                    return null;
                }
                $settle = $pick['settlement_odds_decimal'] ?? null;
                if ($settle !== null && floatval($settle) > 1) {
                    return floatval($settle);
                }
                $pre = $pick['odds_decimal'] ?? null;
                return ($pre !== null && floatval($pre) > 1) ? floatval($pre) : null;
            };

            $apply_win = function($pick) use (&$stats, $pick_odds, &$unsettled_win_bets) {
                $odds = $pick_odds($pick);
                if (!$pick || $odds === null) {
                    return;
                }
                if (!bricks_points_finish_has_result($pick['finish_position'] ?? '')) {
                    $unsettled_win_bets += 1;
                    return;
                }
                $stats['win']['bets'] += 1;
                $is_win = bricks_points_finish_is_win($pick['finish_position'] ?? '');
                if ($is_win) {
                    $stats['win']['hits'] += 1;
                }
                $stats['win']['profit'] += $is_win ? ($odds - 1.0) : -1.0;
            };
            $apply_place = function($pick) use (&$stats, $place_terms, $pick_odds) {
                $odds = $pick_odds($pick);
                if (!$pick || $odds === null) {
                    return;
                }
                $stats['place']['bets'] += 1;
                $placed = bricks_points_finish_is_placed($pick['finish_position'] ?? '', $place_terms);
                if ($placed) {
                    $stats['place']['hits'] += 1;
                }
                $stats['place']['profit'] += $placed ? (($odds - 1.0) * 0.25) : -1.0;
            };
            $apply_ew = function($pick, $key) use (&$stats, $place_terms, $pick_odds) {
                $odds = $pick_odds($pick);
                if (!$pick || $odds === null) {
                    return;
                }
                $stats[$key]['bets'] += 2;
                $is_win = bricks_points_finish_is_win($pick['finish_position'] ?? '');
                $placed = bricks_points_finish_is_placed($pick['finish_position'] ?? '', $place_terms);
                if ($placed) {
                    $stats[$key]['hits'] += 1;
                }
                $stats[$key]['profit'] += $is_win ? ($odds - 1.0) : -1.0;
                $stats[$key]['profit'] += $placed ? (($odds - 1.0) * 0.25) : -1.0;
            };

            $apply_win($picks['winner'] ?? null);
            if (!empty($picks['place'])) {
                foreach (array_slice($picks['place'], 0, 3) as $pp) {
                    $apply_place($pp);
                }
            }
            $apply_ew($ew_simple, 'ew_simple');
            $apply_ew($ew_edge, 'ew_edge');

            if (count($sample_rows) < $sample_limit) {
                $win_pick = $picks['winner'] ?? null;
                $has_result = $win_pick && bricks_points_finish_has_result($win_pick['finish_position'] ?? '');
                $is_win = $has_result && bricks_points_finish_is_win($win_pick['finish_position'] ?? '');
                $race_time = '';
                if (!empty($race_rows[0]->scheduled_time)) {
                    $race_time = wp_date('H:i', strtotime((string) $race_rows[0]->scheduled_time));
                }
                $sample_rows[] = [
                    'meeting_date' => $race_rows[0]->meeting_date ?? '',
                    'race_id' => $race_id,
                    'course' => $race_rows[0]->course ?? '',
                    'race_time' => $race_time,
                    'race_title' => $race_rows[0]->race_title ?? '',
                    'race_type' => $race_rows[0]->race_type ?? '',
                    'winner_pick' => $win_pick['horse_name'] ?? '',
                    'winner_pick_pos' => $win_pick ? bricks_points_format_finish_position($win_pick['finish_position'] ?? '') : '—',
                    'winner_hit' => !$has_result ? '—' : ($is_win ? 'Y' : 'N'),
                    'settlement_odds' => $win_pick ? ($pick_odds($win_pick) ?? '') : '',
                    'ew_simple' => $ew_simple['horse_name'] ?? '',
                    'ew_simple_pos' => bricks_points_format_finish_position($ew_simple['finish_position'] ?? ''),
                    'ew_edge' => $ew_edge['horse_name'] ?? '',
                    'ew_edge_pos' => bricks_points_format_finish_position($ew_edge['finish_position'] ?? ''),
                ];
            }
        }

        if ($day_span <= 7 && count($sample_rows) > 1) {
            usort($sample_rows, function ($a, $b) {
                $a_win = (($a['winner_hit'] ?? '') === 'Y') ? 0 : 1;
                $b_win = (($b['winner_hit'] ?? '') === 'Y') ? 0 : 1;
                if ($a_win !== $b_win) {
                    return $a_win <=> $b_win;
                }
                $dc = strcmp((string) ($a['meeting_date'] ?? ''), (string) ($b['meeting_date'] ?? ''));
                if ($dc !== 0) {
                    return $dc;
                }
                return strcmp((string) ($a['course'] ?? ''), (string) ($b['course'] ?? ''));
            });
        }

        $summary = [];
        foreach ($stats as $k => $v) {
            $bets = intval($v['bets']);
            $profit = round(floatval($v['profit']), 2);
            $roi = $bets > 0 ? round(($profit / $bets) * 100, 2) : 0.0;
            $summary[$k] = [
                'bets' => $bets,
                'hits' => intval($v['hits']),
                'profit' => $profit,
                'roi_pct' => $roi,
                'strike_rate' => $bets > 0 ? round((intval($v['hits']) / $bets) * 100, 2) : 0.0
            ];
        }

        return [
            'summary' => $summary,
            'sample_rows' => $sample_rows,
            'race_count' => count($by_race),
            'runner_count' => count($rows),
            'unsettled_win_bets' => $unsettled_win_bets,
            'day_span' => $day_span,
            'sample_truncated' => ($day_span > 7 && count($by_race) > $sample_limit),
            'missing_published_snapshot' => $missing_published_snapshot,
            'backtest_mode' => $backtest_mode,
            'fetch_meta' => $fetch_meta,
            'latest_historic_date' => bricks_points_backtest_latest_historic_meeting_date(),
        ];
    }
}

if (!function_exists('bricks_points_race_type_is_national_hunt')) {
    function bricks_points_race_type_is_national_hunt($race_type) {
        $race_type_lower = strtolower((string) $race_type);
        return (
            strpos($race_type_lower, 'hurdle') !== false ||
            strpos($race_type_lower, 'chase') !== false ||
            strpos($race_type_lower, 'n_h_flat') !== false ||
            strpos($race_type_lower, 'nh_flat') !== false ||
            strpos($race_type_lower, 'national hunt') !== false
        );
    }
}

if (!function_exists('bricks_points_engine_normalize_horse_name')) {
    function bricks_points_engine_normalize_horse_name($name) {
        $n = strtolower(trim(wp_strip_all_tags((string) $name)));
        return preg_replace('/\s+/', ' ', $n);
    }
}

if (!function_exists('bricks_points_finish_is_win')) {
    function bricks_points_finish_is_win($finish_position) {
        $fp = strtolower(trim((string) $finish_position));
        if ($fp === '' || $fp === 'null') {
            return false;
        }
        if ($fp === '1st' || $fp === 'first') {
            return true;
        }
        if (preg_match('/^\d+$/', $fp)) {
            return intval($fp) === 1;
        }
        $as_float = floatval($fp);
        return abs($as_float - 1.0) < 0.001;
    }
}

if (!function_exists('bricks_points_finish_has_result')) {
    function bricks_points_finish_has_result($finish_position) {
        $fp = strtolower(trim((string) $finish_position));
        if ($fp === '' || $fp === '-' || $fp === 'null') {
            return false;
        }
        if (in_array($fp, ['pu', 'ur', 'bd', 'f', 'ro', 'nr', 'non-runner', 'non runner'], true)) {
            return false;
        }
        return preg_match('/\d/', $fp) === 1;
    }
}

if (!function_exists('bricks_points_format_finish_position')) {
    function bricks_points_format_finish_position($finish_position) {
        $fp = trim((string) $finish_position);
        if ($fp === '' || $fp === '-') {
            return '—';
        }
        return $fp;
    }
}

if (!function_exists('bricks_points_finish_is_placed')) {
    function bricks_points_finish_is_placed($finish_position, $place_terms) {
        $fp = strtolower(trim((string) $finish_position));
        if ($fp === '' || $fp === 'null') {
            return false;
        }
        if (preg_match('/^\d+$/', $fp)) {
            return intval($fp) >= 1 && intval($fp) <= intval($place_terms);
        }
        $as_float = floatval($fp);
        if ($as_float >= 1 && $as_float <= floatval($place_terms) + 0.001) {
            return true;
        }
        return false;
    }
}

if (!function_exists('bricks_points_settlement_odds_decimal')) {
    /**
     * Backtest P&L: prefer result SP (starting_price) over pre-race forecast.
     */
    function bricks_points_settlement_odds_decimal($forecast_decimal, $starting_price = '', $starting_price_decimal = null) {
        if ($starting_price_decimal !== null && $starting_price_decimal !== '' && is_numeric($starting_price_decimal)) {
            $sp = floatval($starting_price_decimal);
            if ($sp > 1) {
                return $sp;
            }
        }
        $from_sp = bricks_points_parse_decimal_odds(null, $starting_price);
        if ($from_sp !== null && $from_sp > 1) {
            return $from_sp;
        }
        return bricks_points_parse_decimal_odds($forecast_decimal, '');
    }
}

if (!function_exists('bricks_points_backtest_speed_data_from_row')) {
    /**
     * Build speed_data for scoring — align with race-card inputs where columns exist on the fetch row.
     */
    function bricks_points_backtest_speed_data_from_row($rr) {
        $fsr = null;
        // Do not map dch.speed_rating (same-race SR from sr_results) onto FSr — that leaks result-era data.
        if (isset($rr->fhorsite_rating) && is_numeric($rr->fhorsite_rating)) {
            $fsr = floatval($rr->fhorsite_rating);
        } elseif (isset($rr->speed_rating) && is_numeric($rr->speed_rating)) {
            $fsr = floatval($rr->speed_rating);
        }

        $fsrr = null;
        if (isset($rr->fhorsite_rating_reliability) && is_numeric($rr->fhorsite_rating_reliability)) {
            $fsrr = floatval($rr->fhorsite_rating_reliability);
        }

        $sr_lto = null;
        if (isset($rr->SR_LTO) && is_numeric($rr->SR_LTO)) {
            $sr_lto = floatval($rr->SR_LTO);
        } elseif (isset($rr->dch_wt_speed_rating) && is_numeric($rr->dch_wt_speed_rating)) {
            $sr_lto = floatval($rr->dch_wt_speed_rating);
        } elseif (isset($rr->wt_speed_rating) && is_numeric($rr->wt_speed_rating)) {
            $sr_lto = floatval($rr->wt_speed_rating);
        }

        $forecast_decimal = null;
        if (isset($rr->forecast_price_decimal) && is_numeric($rr->forecast_price_decimal)) {
            $forecast_decimal = floatval($rr->forecast_price_decimal);
        } elseif (isset($rr->sp_forecast_price_decimal) && is_numeric($rr->sp_forecast_price_decimal)) {
            $forecast_decimal = floatval($rr->sp_forecast_price_decimal);
        }

        return (object) [
            'fhorsite_rating' => $fsr,
            'fhorsite_rating_reliability' => $fsrr !== null ? $fsrr : 60,
            'SR_LTO' => $sr_lto,
            'SR_2' => (isset($rr->SR_2) && is_numeric($rr->SR_2)) ? floatval($rr->SR_2) : null,
            'SR_3' => (isset($rr->SR_3) && is_numeric($rr->SR_3)) ? floatval($rr->SR_3) : null,
            'days_since_ran' => (isset($rr->days_since_ran) && is_numeric($rr->days_since_ran)) ? floatval($rr->days_since_ran) : null,
            'draw_bias_pct' => (isset($rr->draw_bias_pct) && is_numeric($rr->draw_bias_pct)) ? floatval($rr->draw_bias_pct) : null,
            'class_diff' => (isset($rr->class_diff) && is_numeric($rr->class_diff)) ? floatval($rr->class_diff) : null,
            'official_rating_diff' => (isset($rr->official_rating_diff) && is_numeric($rr->official_rating_diff)) ? floatval($rr->official_rating_diff) : null,
            'course_winner' => (isset($rr->course_winner) && is_numeric($rr->course_winner)) ? $rr->course_winner : null,
            'distance_winner' => (isset($rr->distance_winner) && is_numeric($rr->distance_winner)) ? $rr->distance_winner : null,
            'candd_winner' => (isset($rr->candd_winner) && is_numeric($rr->candd_winner)) ? $rr->candd_winner : null,
            'going_prev_wins' => (isset($rr->going_prev_wins) && is_numeric($rr->going_prev_wins)) ? $rr->going_prev_wins : null,
            'beaten_favourite' => (isset($rr->beaten_favourite) && is_numeric($rr->beaten_favourite)) ? $rr->beaten_favourite : null,
            'TnrWinPct14d' => (isset($rr->TnrWinPct14d) && is_numeric($rr->TnrWinPct14d)) ? floatval($rr->TnrWinPct14d) : null,
            'TnrJkyPlacePct' => (isset($rr->TnrJkyPlacePct) && is_numeric($rr->TnrJkyPlacePct)) ? floatval($rr->TnrJkyPlacePct) : null,
            'forecast_price_decimal' => $forecast_decimal,
            'forecast_price' => isset($rr->starting_price) ? $rr->starting_price : '',
        ];
    }
}

if (!function_exists('bricks_points_backtest_score_race')) {
    /**
     * Score all runners in one race the same way as the live race card (NH vs Flat, shared speed mapping).
     *
     * @return array<int, array<string, mixed>>
     */
    function bricks_points_backtest_score_race($race_rows) {
        if (empty($race_rows)) {
            return [];
        }

        $race_type = (string) ($race_rows[0]->race_type ?? '');
        $is_flat = !bricks_points_race_type_is_national_hunt($race_type);
        $race_id = isset($race_rows[0]->race_id) ? intval($race_rows[0]->race_id) : 0;

        $scored = [];
        foreach ($race_rows as $idx => $rr) {
            $speed_data = bricks_points_backtest_speed_data_from_row($rr);
            $pts = bricks_points_score_runner($rr, $speed_data, ['is_flat' => $is_flat]);

            $forecast_decimal = $speed_data->forecast_price_decimal ?? null;
            $forecast_fractional = (string) ($speed_data->forecast_price ?? '');
            $odds_decimal = bricks_points_parse_decimal_odds($forecast_decimal, $forecast_fractional);
            $settlement_odds = bricks_points_settlement_odds_decimal(
                $forecast_decimal,
                isset($rr->starting_price) ? (string) $rr->starting_price : '',
                $rr->starting_price_decimal ?? null
            );

            $scored[] = [
                'runner_key' => $race_id . '_' . $idx,
                'horse_name' => (string) ($rr->horse_name ?? ''),
                'model_score' => floatval($pts['score'] ?? 0),
                'model_reasons' => $pts['reasons'] ?? [],
                'market_prob' => bricks_points_market_implied_rank($odds_decimal),
                'market_rank' => 0,
                'model_rank' => 0,
                'edge_score' => 0,
                'odds_decimal' => $odds_decimal,
                'settlement_odds_decimal' => $settlement_odds,
                'odds_fractional' => (string) ($rr->starting_price ?? ''),
                'is_non_runner' => !empty($rr->is_non_runner),
                'finish_position' => $rr->finish_position ?? '',
                'meeting_date' => (string) ($rr->meeting_date ?? ''),
            ];
        }

        usort($scored, function ($a, $b) {
            return ($b['model_score'] ?? 0) <=> ($a['model_score'] ?? 0);
        });
        $rank = 1;
        foreach ($scored as &$sr) {
            $sr['model_rank'] = $rank++;
        }
        unset($sr);

        $market_sorted = $scored;
        usort($market_sorted, function ($a, $b) {
            return ($b['market_prob'] ?? 0) <=> ($a['market_prob'] ?? 0);
        });
        $mk = 1;
        $mk_map = [];
        foreach ($market_sorted as $mr) {
            if (($mr['market_prob'] ?? 0) > 0) {
                $mk_map[$mr['runner_key']] = $mk++;
            }
        }
        foreach ($scored as &$sr) {
            $sr['market_rank'] = $mk_map[$sr['runner_key']] ?? 0;
            $rank_edge = ($sr['market_rank'] > 0) ? ($sr['market_rank'] - $sr['model_rank']) : 0;
            $score_edge = max(0.0, (floatval($sr['model_score']) - 55.0) * 0.20);
            $sr['edge_score'] = round(($rank_edge * 4.0) + $score_edge, 2);
        }
        unset($sr);

        return $scored;
    }
}

if (!function_exists('bricks_points_engine_meeting_day_win_pick_hit_race_ids')) {
    /**
     * Races on a calendar day where the Points Engine Win pick (highest model score) won.
     *
     * @param string $meeting_date_ymd Y-m-d
     * @return int[]
     */
    function bricks_points_engine_meeting_day_win_pick_hit_race_ids($meeting_date_ymd) {
        if (!function_exists('bricks_points_backtest_fetch_rows')) {
            return [];
        }
        $rows = bricks_points_backtest_fetch_rows($meeting_date_ymd, $meeting_date_ymd, '');
        if (empty($rows)) {
            return [];
        }

        $by_race = [];
        foreach ($rows as $row) {
            $rid = isset($row->race_id) ? intval($row->race_id) : 0;
            if ($rid <= 0) {
                continue;
            }
            if (!isset($by_race[$rid])) {
                $by_race[$rid] = [];
            }
            $by_race[$rid][] = $row;
        }

        $hit_race_ids = [];

        foreach ($by_race as $race_id => $race_rows) {
            $field_size = count($race_rows);
            if ($field_size < 2) {
                continue;
            }

            $scored = bricks_points_backtest_score_race($race_rows);
            $picks = bricks_points_pick_winner_place($scored);
            $pick = $picks['winner'] ?? null;
            if (!$pick || empty($pick['horse_name'])) {
                continue;
            }

            $actual_winner_name = '';
            foreach ($race_rows as $rr) {
                if (bricks_points_finish_is_win($rr->finish_position ?? '')) {
                    $actual_winner_name = (string) ($rr->horse_name ?? '');
                    break;
                }
            }
            if ($actual_winner_name === '') {
                continue;
            }

            if (bricks_points_engine_normalize_horse_name($pick['horse_name']) === bricks_points_engine_normalize_horse_name($actual_winner_name)) {
                $hit_race_ids[] = intval($race_id);
            }
        }

        return $hit_race_ids;
    }
}

if (!function_exists('bricks_points_backtest_shortcode')) {
    function bricks_points_backtest_shortcode($atts = []) {
        if (!function_exists('bricks_user_can_access_points_backtest') || !bricks_user_can_access_points_backtest()) {
            return '<div style="max-width:760px;margin:40px auto;padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                <h2 style="margin:0 0 10px 0;color:#111827;">Points Backtest</h2>
                <p style="margin:0;color:#6b7280;">This page is available to site administrators only.</p>
            </div>';
        }

        $yesterday = wp_date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
        $default_to = $yesterday;
        $default_from = $yesterday;

        $from_date = isset($_GET['pb_from']) ? sanitize_text_field($_GET['pb_from']) : $default_from;
        $to_date = isset($_GET['pb_to']) ? sanitize_text_field($_GET['pb_to']) : $default_to;
        $race_type_filter = isset($_GET['pb_race_type']) ? sanitize_text_field($_GET['pb_race_type']) : '';
        $backtest_mode = isset($_GET['pb_mode']) ? sanitize_text_field($_GET['pb_mode']) : 'published';
        if ($backtest_mode !== 'published' && $backtest_mode !== 'model') {
            $backtest_mode = 'published';
        }
        $requested_from = $from_date;
        $requested_to = $to_date;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
            $from_date = $default_from;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
            $to_date = $default_to;
        }
        if ($from_date > $to_date) {
            $tmp = $from_date;
            $from_date = $to_date;
            $to_date = $tmp;
        }
        $dates_clamped = false;
        if ($to_date > $yesterday) {
            $dates_clamped = true;
            $to_date = $yesterday;
        }
        if ($from_date > $yesterday) {
            $dates_clamped = true;
            $from_date = $yesterday;
        }

        $snapshot_result = null;
        if (
            isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['bricks_snapshot_published_picks'])
            && function_exists('bricks_points_published_picks_bulk_snapshot')
        ) {
            check_admin_referer('bricks_snapshot_published_picks');
            $snap_from = isset($_POST['snap_from']) ? sanitize_text_field(wp_unslash($_POST['snap_from'])) : $from_date;
            $snap_to = isset($_POST['snap_to']) ? sanitize_text_field(wp_unslash($_POST['snap_to'])) : $to_date;
            $snap_overwrite = !empty($_POST['snap_overwrite']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $snap_from)) {
                $snap_from = $from_date;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $snap_to)) {
                $snap_to = $to_date;
            }
            if ($snap_from > $snap_to) {
                $tmp = $snap_from;
                $snap_from = $snap_to;
                $snap_to = $tmp;
            }
            $snapshot_result = bricks_points_published_picks_bulk_snapshot($snap_from, $snap_to, $snap_overwrite);
        }

        $published_snapshot_count = function_exists('bricks_points_published_picks_count_for_range')
            ? bricks_points_published_picks_count_for_range($from_date, $to_date)
            : 0;

        $result = bricks_points_backtest_calculate($from_date, $to_date, $race_type_filter, $backtest_mode);
        $summary = $result['summary'] ?? [];
        $sample_rows = $result['sample_rows'] ?? [];
        $day_span = isset($result['day_span']) ? intval($result['day_span']) : 1;
        $sample_truncated = !empty($result['sample_truncated']);
        $unsettled_win = isset($result['unsettled_win_bets']) ? intval($result['unsettled_win_bets']) : 0;
        $missing_published = isset($result['missing_published_snapshot']) ? intval($result['missing_published_snapshot']) : 0;
        $has_backtest_cr = $GLOBALS['wpdb']->get_var("SHOW TABLES LIKE 'backtest_cr_data'") === 'backtest_cr_data';
        $fetch_meta = is_array($result['fetch_meta'] ?? null) ? $result['fetch_meta'] : [];
        $dch_dates = is_array($fetch_meta['dch_dates'] ?? null) ? $fetch_meta['dch_dates'] : [];
        $latest_historic_date = (string) ($result['latest_historic_date'] ?? '');

        ob_start();
        ?>
        <div style="max-width:1200px;margin:24px auto;padding:0 16px 30px;">
            <h1 style="margin:0 0 6px;color:#111827;font-size:30px;font-weight:800;">Points Engine Backtest</h1>
            <p style="margin:0 0 14px;color:#6b7280;">Historical ROI test for Win, Place, EW Simple and EW Edge. Use <strong>Published picks</strong> to audit what the race card actually showed; <strong>Model replay</strong> recomputes from historic data (may differ from live). <a href="<?php echo esc_url(home_url('/today-picks/')); ?>" style="color:#0f766e;font-weight:700;">Today's Picks sheet</a> for a one-page audit screenshot.</p>

            <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-bottom:14px;">
                <input type="hidden" name="my_points_backtest" value="1" />
                <label style="font-size:12px;color:#374151;">From<br><input type="date" name="pb_from" max="<?php echo esc_attr($yesterday); ?>" value="<?php echo esc_attr($from_date); ?>" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <label style="font-size:12px;color:#374151;">To<br><input type="date" name="pb_to" max="<?php echo esc_attr($yesterday); ?>" value="<?php echo esc_attr($to_date); ?>" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <label style="font-size:12px;color:#374151;">Mode<br>
                    <select name="pb_mode" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;">
                        <option value="published" <?php selected($backtest_mode, 'published'); ?>>Published picks (race card)</option>
                        <option value="model" <?php selected($backtest_mode, 'model'); ?>>Model replay</option>
                    </select>
                </label>
                <label style="font-size:12px;color:#374151;">Race Type<br><input type="text" name="pb_race_type" value="<?php echo esc_attr($race_type_filter); ?>" placeholder="Optional exact match" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <button type="submit" style="padding:9px 14px;border:none;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;">Run Backtest</button>
                <a href="<?php echo esc_url(add_query_arg(['my_points_backtest' => '1', 'pb_from' => $yesterday, 'pb_to' => $yesterday], home_url('/points-backtest/'))); ?>" style="padding:9px 14px;border-radius:8px;background:#ecfdf5;color:#065f46;font-weight:700;text-decoration:none;border:1px solid #6ee7b7;">Yesterday only</a>
            </form>

            <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:14px;margin-bottom:14px;">
                <h2 style="margin:0 0 6px;font-size:16px;color:#0f172a;">Snapshot published picks for date range</h2>
                <p style="margin:0 0 10px;font-size:13px;color:#475569;">
                    Bulk-save win/place/EW picks into <code>points_engine_published_picks</code> for published backtest mode.
                    Uses live <code>speed&amp;performance_table</code> when rows exist; otherwise historic model replay (labelled <code>admin_bulk_replay</code>).
                    <strong><?php echo esc_html($published_snapshot_count); ?></strong> snapshot(s) already stored for the current backtest period.
                </p>
                <form method="post" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                    <?php wp_nonce_field('bricks_snapshot_published_picks'); ?>
                    <input type="hidden" name="bricks_snapshot_published_picks" value="1" />
                    <label style="font-size:12px;color:#374151;">From<br><input type="date" name="snap_from" max="<?php echo esc_attr($yesterday); ?>" value="<?php echo esc_attr($from_date); ?>" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                    <label style="font-size:12px;color:#374151;">To<br><input type="date" name="snap_to" max="<?php echo esc_attr($yesterday); ?>" value="<?php echo esc_attr($to_date); ?>" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                    <label style="font-size:12px;color:#374151;display:flex;align-items:center;gap:6px;padding-bottom:8px;">
                        <input type="checkbox" name="snap_overwrite" value="1" />
                        Overwrite existing
                    </label>
                    <button type="submit" style="padding:9px 14px;border:none;border-radius:8px;background:#0f766e;color:#fff;font-weight:700;cursor:pointer;">Snapshot published picks</button>
                </form>
                <p style="margin:10px 0 0;font-size:12px;color:#64748b;">Processes up to 400 races per run. Re-run for longer ranges. Past dates without speed rows get replay picks, not exact race-card copies.</p>
            </div>

            <?php if (is_array($snapshot_result)): ?>
            <div style="margin:0 0 14px;padding:10px 12px;border-radius:10px;background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;font-size:13px;">
                Snapshot complete:
                <strong><?php echo esc_html($snapshot_result['saved'] ?? 0); ?></strong> saved,
                <strong><?php echo esc_html($snapshot_result['skipped'] ?? 0); ?></strong> skipped (already stored),
                <strong><?php echo esc_html($snapshot_result['failed'] ?? 0); ?></strong> failed.
                <?php
                $src = is_array($snapshot_result['sources'] ?? null) ? $snapshot_result['sources'] : [];
                $live_n = intval($src['admin_bulk_live'] ?? 0);
                $replay_n = intval($src['admin_bulk_replay'] ?? 0);
                if ($live_n > 0 || $replay_n > 0):
                ?>
                Sources: <?php echo esc_html($live_n); ?> live speed, <?php echo esc_html($replay_n); ?> historic replay.
                <?php endif; ?>
                <?php if (!empty($snapshot_result['errors']) && is_array($snapshot_result['errors'])): ?>
                <ul style="margin:8px 0 0;padding-left:18px;">
                    <?php foreach ($snapshot_result['errors'] as $err): ?>
                    <li><?php echo esc_html($err); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($dates_clamped): ?>
            <div style="margin:0 0 14px;padding:10px 12px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:13px;">
                Dates are capped at <strong>yesterday (<?php echo esc_html($yesterday); ?>)</strong>.
                <?php if ($requested_to > $yesterday || $requested_from > $yesterday): ?>
                You asked for <?php echo esc_html($requested_from); ?> → <?php echo esc_html($requested_to); ?>; showing <?php echo esc_html($from_date); ?> → <?php echo esc_html($to_date); ?> instead.
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($backtest_mode === 'model'): ?>
            <div style="margin:0 0 14px;padding:10px 12px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;">
                <strong>Model replay</strong> does not use the live <code>speed&amp;performance_table</code>. It recomputes picks from historic rows
                <?php echo $has_backtest_cr ? '(Fhorsite from <code>backtest_cr_data</code> when available)' : '(Fhorsite point-in-time table <code>backtest_cr_data</code> not found — run CR backtest SQL on DB)'; ?>.
                Picks can differ from what members saw on the race card. For audits, use <strong>Published picks</strong>.
            </div>
            <?php elseif ($missing_published > 0): ?>
            <div style="margin:0 0 14px;padding:10px 12px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;">
                <strong><?php echo esc_html($missing_published); ?></strong> race(s) had no saved published pick. Use <strong>Snapshot published picks</strong> above to backfill, open those race pages while logged in, or switch to Model replay for those days.
            </div>
            <?php endif; ?>

            <?php if (!empty($dch_dates)): ?>
            <div style="margin:0 0 14px;padding:10px 12px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;font-size:13px;">
                <?php echo esc_html(count($dch_dates) === 1 ? 'This day' : count($dch_dates) . ' days'); ?> not yet in historic tables — loaded from <strong>daily_comment_history</strong>
                (<?php echo esc_html(implode(', ', $dch_dates)); ?>).
            </div>
            <?php endif; ?>

            <?php if (intval($result['race_count'] ?? 0) === 0): ?>
            <div style="margin:0 0 14px;padding:10px 12px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;">
                No settled race data for <strong><?php echo esc_html($from_date); ?></strong><?php echo $from_date !== $to_date ? ' → <strong>' . esc_html($to_date) . '</strong>' : ''; ?>.
                <?php if ($latest_historic_date !== ''): ?>
                Latest date in historic tables: <strong><?php echo esc_html($latest_historic_date); ?></strong>.
                <?php endif; ?>
                Recent cards appear here once results are in daily_comment_history or after the historic ETL run.
            </div>
            <?php endif; ?>

            <?php if ($day_span > 31): ?>
            <div style="margin:0 0 14px;padding:10px 12px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;">
                You are backtesting <strong><?php echo esc_html($day_span); ?> days</strong> (<?php echo esc_html($from_date); ?> → <?php echo esc_html($to_date); ?>).
                Summary totals are for the whole period. The race list below shows only the <strong>first ~120 races</strong> in that range — not your most recent card.
                To check last Sunday’s Kelso/Curragh winners, set <strong>From and To to the same day</strong> (e.g. <?php echo esc_html($yesterday); ?>) or use “Yesterday only”.
            </div>
            <?php elseif ($sample_truncated): ?>
            <div style="margin:0 0 14px;padding:10px 12px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;">
                Race list is truncated to the first <?php echo esc_html(count($sample_rows)); ?> races in this range. Narrow the dates to see every race on one card.
            </div>
            <?php endif; ?>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-size:12px;color:#374151;">Period: <strong><?php echo esc_html($from_date); ?></strong> → <strong><?php echo esc_html($to_date); ?></strong> (<?php echo esc_html($day_span); ?> day<?php echo $day_span === 1 ? '' : 's'; ?>)</div>
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-size:12px;color:#374151;">Races: <strong><?php echo esc_html($result['race_count'] ?? 0); ?></strong></div>
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-size:12px;color:#374151;">Runners: <strong><?php echo esc_html($result['runner_count'] ?? 0); ?></strong></div>
                <?php if ($unsettled_win > 0): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px 12px;font-size:12px;color:#991b1b;">Win picks with no finish in historic/DCH: <strong><?php echo esc_html($unsettled_win); ?></strong> (excluded from win P&amp;L — not counted as losses)</div>
                <?php endif; ?>
            </div>

            <div style="overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:16px;">
                <table style="width:100%;border-collapse:collapse;min-width:760px;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Strategy</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Bets</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Hits</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Strike %</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Profit (pts)</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">ROI %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $labels = ['win' => 'Win Pick', 'place' => 'Place Shortlist', 'ew_simple' => 'EW Simple', 'ew_edge' => 'EW Edge'];
                        foreach ($labels as $k => $label):
                            $r = $summary[$k] ?? ['bets'=>0,'hits'=>0,'strike_rate'=>0,'profit'=>0,'roi_pct'=>0];
                            $roi_class = floatval($r['roi_pct']) >= 0 ? '#065f46' : '#991b1b';
                        ?>
                        <tr>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;font-weight:700;"><?php echo esc_html($label); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($r['bets']); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($r['hits']); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html(number_format(floatval($r['strike_rate']), 2)); ?>%</td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;color:<?php echo esc_attr($roi_class); ?>;"><?php echo esc_html(number_format(floatval($r['profit']), 2)); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;color:<?php echo esc_attr($roi_class); ?>;font-weight:700;"><?php echo esc_html(number_format(floatval($r['roi_pct']), 2)); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($sample_rows)): ?>
            <div style="overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                <table style="width:100%;border-collapse:collapse;min-width:900px;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Date</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Time</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Course</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Race</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Win Pick</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Hit</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">SP</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">EW Simple</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">EW Edge</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sample_rows as $sr): ?>
                        <tr>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($sr['meeting_date']); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($sr['race_time'] ?? '-'); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($sr['course']); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;font-size:12px;color:#475569;max-width:220px;"><?php echo esc_html(wp_trim_words($sr['race_title'] ?? '-', 8, '…')); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html(($sr['winner_pick'] ?: '-') . ' (Pos ' . ($sr['winner_pick_pos'] ?? '—') . ')'); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;font-weight:700;color:<?php
                                $hit = $sr['winner_hit'] ?? '-';
                                echo esc_attr($hit === 'Y' ? '#065f46' : ($hit === '—' ? '#92400e' : '#6b7280'));
                            ?>;"><?php echo esc_html($hit); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($sr['settlement_odds'] !== '' ? $sr['settlement_odds'] : '-'); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html(($sr['ew_simple'] ?: '-') . ' (Pos ' . ($sr['ew_simple_pos'] ?: '-') . ')'); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html(($sr['ew_edge'] ?: '-') . ' (Pos ' . ($sr['ew_edge_pos'] ?: '-') . ')'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('points_backtest', 'bricks_points_backtest_shortcode');

function bricks_race_detail_shortcode($atts) {
    global $wpdb;

    if (!fhor_user_has_paid_race_access()) {
        return fhor_race_access_required_message();
    }

    $atts = shortcode_atts(['race_id' => 0], $atts);
    $race_id = bricks_decode_entity_id($atts['race_id'], 'race');
    if (!$race_id) {
        $race_id = bricks_decode_entity_id(get_query_var('race_id'), 'race');
    }
    if (!$race_id && !empty($_SERVER['REQUEST_URI'])) {
        if (preg_match('/race\/([A-Za-z0-9_-]+)/', $_SERVER['REQUEST_URI'], $m)) {
            $race_id = bricks_decode_entity_id($m[1], 'race');
        }
    }
    
    if (!$race_id) {
        return '<div style="color:red;padding:20px;">Error: Race ID is required</div>';
    }
    
    // UPDATED: Determine which tables to use based on date
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    // First, get race details to determine the date
    $race = $wpdb->get_row($wpdb->prepare(
        "SELECT race_id, meeting_date, course, race_type FROM advance_daily_races_beta WHERE race_id = %d",
        $race_id
    ));
    
    // If not found in beta table, try the main table
    if (!$race) {
        $race = $wpdb->get_row($wpdb->prepare(
            "SELECT race_id, meeting_date, course, race_type FROM advance_daily_races WHERE race_id = %d",
            $race_id
        ));
    }
    
    if (!$race) {
        return '<div style="color:red;padding:20px;">Error: Race not found</div>';
    }
    // Get course features
$course_features = get_course_features($race->course);

    // Determine which tables to use based on race date
    if ($race->meeting_date === $tomorrow) {
        $races_table = 'advance_daily_races';
        $runners_table = 'advance_daily_runners';
        $speed_table = 'adv_speed&performance_table'; // Use advanced table for tomorrow
        bricks_debug_log("Race Detail Debug - Using tomorrow tables: $races_table, $runners_table, $speed_table");
    } else {
        $races_table = 'advance_daily_races_beta';
        $runners_table = 'advance_daily_runners_beta';
        $speed_table = 'speed&performance_table'; // Use regular table for today
        bricks_debug_log("Race Detail Debug - Using today tables: $races_table, $runners_table, $speed_table");
    }
    
    // Get race details from the correct table
    $race = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $races_table WHERE race_id = %d", $race_id
    ));
    
    $is_national_hunt = false;
    if ($race && $race->race_type) {
        $race_type_lower = strtolower($race->race_type);
        $is_national_hunt = (
            strpos($race_type_lower, 'hurdle') !== false ||
            strpos($race_type_lower, 'chase') !== false ||
            strpos($race_type_lower, 'n_h_flat') !== false ||
            strpos($race_type_lower, 'nh_flat') !== false ||
            strpos($race_type_lower, 'national hunt') !== false
        );
    }
    
    if (!$race) {
        return '<div style="color:red;padding:20px;">Error: Race not found</div>';
    }
    
    // Check if this is tomorrow's race
    $is_tomorrow_race = ($race->meeting_date === $tomorrow);
    $show_maturity_edge = bricks_is_2yo_flat_race_for_maturity($race, $is_national_hunt);
    
    // Get all available races for the same date for quick navigation
    $race_date = $race->meeting_date;
    $all_races = $wpdb->get_results($wpdb->prepare(
        "SELECT race_id, course, scheduled_time, race_title, class 
         FROM $races_table 
         WHERE meeting_date = %s 
         ORDER BY course ASC, scheduled_time ASC",
        $race_date
    ));
    
    // Group races by course
    $races_by_course = [];
    foreach ($all_races as $r) {
        if (!isset($races_by_course[$r->course])) {
            $races_by_course[$r->course] = [];
        }
        $races_by_course[$r->course][] = $r;
    }
    
    // Get runners from the correct table
  // Get runners from the correct table
// Get runners from the correct table
$runners = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $runners_table WHERE race_id = %d",
    $race_id
));

// Fetch non-runner flags from dedicated table so we can highlight/hide them
$non_runner_lookup = [];
$non_runner_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT race_id, runner_id FROM non_runners WHERE race_id = %d",
    $race_id
));

if (!empty($non_runner_rows)) {
    foreach ($non_runner_rows as $row) {
        $race_key = intval($row->race_id);
        $runner_key = intval($row->runner_id);
        if ($race_key > 0 && $runner_key > 0) {
            $non_runner_lookup[$race_key . ':' . $runner_key] = true;
        }
    }
}

bricks_debug_log(sprintf(
    'Race Detail Debug - Race ID %d has %d non-runners in lookup',
    $race_id,
    count($non_runner_lookup)
));

// Get Speed Rating data from the correct table (support both d-m-Y and Y-m-d Date formats)
$date_dmy = convert_date_format($race->meeting_date, 'd-m-Y');
$date_ymd = convert_date_format($race->meeting_date, 'Y-m-d');

// UPDATED: Use dynamic speed table
// Primary: fetch by race_id only (more reliable if Date formatting differs)
$speed_ratings = $wpdb->get_results($wpdb->prepare(
    "SELECT sp.*, r.name as horse_name
     FROM `$speed_table` sp
     LEFT JOIN $runners_table r ON sp.race_id = r.race_id AND sp.runner_id = r.runner_id
     WHERE sp.race_id = %d",
    $race_id
));

// Fallback: if nothing found by race_id only, try strict Date match (supports d-m-Y and Y-m-d)
if (!$speed_ratings || count($speed_ratings) === 0) {
    $speed_ratings = $wpdb->get_results($wpdb->prepare(
        "SELECT sp.*, r.name as horse_name 
         FROM `$speed_table` sp
         LEFT JOIN $runners_table r ON sp.race_id = r.race_id AND sp.runner_id = r.runner_id
         WHERE sp.race_id = %d AND sp.Date IN (%s, %s)", 
        $race_id, 
        $date_dmy,
        $date_ymd
    ));
}

// Add debug info to see which table was used
bricks_debug_log("Race Detail Debug - Race ID: $race_id, Date: {$race->meeting_date}, Speed table used: $speed_table, Speed ratings found: " . count($speed_ratings));

// Create lookup for speed ratings
$speed_ratings_lookup = [];
if ($speed_ratings) {
    foreach ($speed_ratings as $rating) {
        // Prefer runner name from the joined runners table; if missing (join mismatch),
        // fall back to the speed table's own 'name' field so graphs still populate.
        $horse_name = ($rating->horse_name ?? '') !== '' ? $rating->horse_name : ($rating->name ?? '');
        if ($horse_name) {
            $speed_ratings_lookup[$horse_name] = $rating;
        }
    }
}

// Also build a lookup by runner_id to handle cases where names don't align yet (e.g., debut 2YO)
$speed_ratings_by_runner_id = [];
if ($speed_ratings) {
    foreach ($speed_ratings as $rating) {
        if (isset($rating->runner_id) && $rating->runner_id !== null && $rating->runner_id !== '') {
            $speed_ratings_by_runner_id[(string)$rating->runner_id] = $rating;
        }
    }
}

// Reconcile lookups: ensure every runner's display name resolves to a speed record.
// Prefer exact runner_id match; if already mapped by name, keep existing.
if (!empty($runners) && !empty($speed_ratings_by_runner_id)) {
    foreach ($runners as $runner_item) {
        $runner_name = isset($runner_item->name) ? (string)$runner_item->name : '';
        $runner_id_key = isset($runner_item->runner_id) ? (string)$runner_item->runner_id : '';
        if ($runner_name !== '' && $runner_id_key !== '') {
            if (!isset($speed_ratings_lookup[$runner_name]) && isset($speed_ratings_by_runner_id[$runner_id_key])) {
                $speed_ratings_lookup[$runner_name] = $speed_ratings_by_runner_id[$runner_id_key];
            }
        }
    }
}

// Build a lightweight sire 5Y signal lookup for this race card using internal history only.
$sire_5y_lookup = [];
$sire_backtest_to = date('Y-m-d', strtotime('-1 day'));
$sire_backtest_from = date('Y-m-d', strtotime('-5 years', strtotime($sire_backtest_to)));
$sire_names = [];
if (!function_exists('bricks_normalize_sire_name_key')) {
    function bricks_normalize_sire_name_key($name) {
        $name = is_string($name) ? $name : '';
        // Remove trailing country code in parentheses, e.g., "Galileo (IRE)" -> "Galileo"
        $name = preg_replace('/\s*\([A-Z]{2,3}\)\s*$/', '', $name);
        // Collapse multiple spaces and lowercase for key normalization
        $name = strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        return $name;
    }
}
if (!function_exists('bricks_extract_sire_name')) {
    function bricks_extract_sire_name($row) {
        if (!is_object($row)) {
            return '';
        }
        if (isset($row->sire_name) && $row->sire_name !== '') {
            return trim((string) $row->sire_name);
        }
        if (isset($row->sire) && $row->sire !== '') {
            return trim((string) $row->sire);
        }
        return '';
    }
}
if (!function_exists('bricks_lin5_quality')) {
    function bricks_lin5_quality($runs) {
        $runs = intval($runs);
        if ($runs >= 100) {
            return ['label' => 'High', 'class' => 'lin5-quality-high'];
        }
        if ($runs >= 30) {
            return ['label' => 'Med', 'class' => 'lin5-quality-med'];
        }
        if ($runs >= 1) {
            return ['label' => 'Low', 'class' => 'lin5-quality-low'];
        }
        return ['label' => 'N/A', 'class' => 'lin5-quality-na'];
    }
}
if (!empty($speed_ratings)) {
    foreach ($speed_ratings as $rating) {
        $raw = bricks_extract_sire_name($rating);
        if ($raw !== '') {
            $sire_names[] = $raw;
            $norm = bricks_normalize_sire_name_key($raw);
            if ($norm && $norm !== strtolower($raw)) {
                $sire_names[] = $norm;
            }
        }
    }
}
// Always also collect sire names from runners list to augment candidates
if (!empty($runners)) {
    foreach ($runners as $runner_item) {
        if (isset($runner_item->sire_name) && $runner_item->sire_name) {
            $raw = trim((string) $runner_item->sire_name);
            $sire_names[] = $raw;
            $norm = bricks_normalize_sire_name_key($raw);
            if ($norm && $norm !== strtolower($raw)) {
                $sire_names[] = $norm;
            }
        } elseif (isset($runner_item->sire) && $runner_item->sire) {
            $raw = trim((string) $runner_item->sire);
            $sire_names[] = $raw;
            $norm = bricks_normalize_sire_name_key($raw);
            if ($norm && $norm !== strtolower($raw)) {
                $sire_names[] = $norm;
            }
        }
    }
}
$sire_names = array_values(array_unique(array_filter($sire_names)));
bricks_debug_log('Race Detail Debug - Sire names candidate count: ' . count($sire_names));
if (!empty($sire_names)) {
    $lin5_cache_key = 'bricks_lin5_' . md5(wp_json_encode([
        'race_id' => intval($race_id),
        'from' => $sire_backtest_from,
        'to' => $sire_backtest_to,
        'sire_names' => $sire_names,
    ]));
    $lin5_cached = get_transient($lin5_cache_key);
    if (is_array($lin5_cached) && isset($lin5_cached['lookup']) && is_array($lin5_cached['lookup'])) {
        $sire_5y_lookup = $lin5_cached['lookup'];
    } else {
    $sire_placeholders = implode(',', array_fill(0, count($sire_names), '%s'));
    // PRB-based lineage from historical runners/races (schema-safe; no dependency on daily_comment_history.sire).
    // Reliability shrinkage: adjusted = (runs*raw + k*baseline) / (runs+k)
    $sire_flat_filter = "
          COALESCE(dracb.race_type, hracb.race_type) IS NOT NULL
      AND COALESCE(dracb.race_type, hracb.race_type) != ''
      AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%hurdle%'
      AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%chase%'
      AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%nh%'
      AND LOWER(COALESCE(dracb.race_type, hracb.race_type)) NOT LIKE '%national hunt%'";

    $sire_common_where = "
          hrunb.sire_name IS NOT NULL
      AND hrunb.sire_name != ''
      AND hrunb.finish_position IS NOT NULL
      AND hrunb.finish_position > 0
      AND rf.field_size > 1
      AND hracb.meeting_date BETWEEN %s AND %s
      AND $sire_flat_filter";

    $baseline_sql = "
        SELECT ROUND(AVG((rf.field_size - hrunb.finish_position) / (rf.field_size - 1)) * 100, 1) AS baseline_prb
        FROM historic_runners_beta hrunb
        INNER JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
        LEFT JOIN daily_races_beta dracb ON dracb.race_id = hrunb.race_id
        INNER JOIN (
            SELECT race_id, COUNT(*) AS field_size
            FROM historic_runners_beta
            WHERE finish_position IS NOT NULL AND finish_position > 0
            GROUP BY race_id
            HAVING COUNT(*) > 1
        ) rf ON rf.race_id = hrunb.race_id
        WHERE hracb.meeting_date BETWEEN %s AND %s
          AND MONTH(hracb.meeting_date) BETWEEN 3 AND 10
          AND $sire_flat_filter";
    $baseline_prb = $wpdb->get_var($wpdb->prepare($baseline_sql, $sire_backtest_from, $sire_backtest_to));
    $baseline_prb = ($baseline_prb !== null) ? floatval($baseline_prb) : 50.0;
    $sire_shrink_k = 30.0;

    // Level 1: Mar-Jun + 5-6f
    $lvl1_sql = "
        SELECT hrunb.sire_name AS sire_name,
               COUNT(*) AS runs,
               ROUND(AVG((rf.field_size - hrunb.finish_position) / (rf.field_size - 1)) * 100, 1) AS raw_prb_pct,
               'Mar-Jun, 5-6f' AS context
        FROM historic_runners_beta hrunb
        INNER JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
        LEFT JOIN daily_races_beta dracb ON dracb.race_id = hrunb.race_id
        INNER JOIN (
            SELECT race_id, COUNT(*) AS field_size
            FROM historic_runners_beta
            WHERE finish_position IS NOT NULL AND finish_position > 0
            GROUP BY race_id
            HAVING COUNT(*) > 1
        ) rf ON rf.race_id = hrunb.race_id
        WHERE hrunb.sire_name IN ($sire_placeholders)
          AND MONTH(hracb.meeting_date) BETWEEN 3 AND 6
          AND hracb.distance_yards BETWEEN 1100 AND 1320
          AND $sire_common_where
        GROUP BY hrunb.sire_name";
    $lvl1_params = array_merge($sire_names, [$sire_backtest_from, $sire_backtest_to]);
    $lvl1 = $wpdb->get_results($wpdb->prepare($lvl1_sql, ...$lvl1_params));

    // Level 2: Mar-Jun any distance
    $lvl2_sql = "
        SELECT hrunb.sire_name AS sire_name,
               COUNT(*) AS runs,
               ROUND(AVG((rf.field_size - hrunb.finish_position) / (rf.field_size - 1)) * 100, 1) AS raw_prb_pct,
               'Mar-Jun, any dist' AS context
        FROM historic_runners_beta hrunb
        INNER JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
        LEFT JOIN daily_races_beta dracb ON dracb.race_id = hrunb.race_id
        INNER JOIN (
            SELECT race_id, COUNT(*) AS field_size
            FROM historic_runners_beta
            WHERE finish_position IS NOT NULL AND finish_position > 0
            GROUP BY race_id
            HAVING COUNT(*) > 1
        ) rf ON rf.race_id = hrunb.race_id
        WHERE hrunb.sire_name IN ($sire_placeholders)
          AND MONTH(hracb.meeting_date) BETWEEN 3 AND 6
          AND $sire_common_where
        GROUP BY hrunb.sire_name";
    $lvl2_params = array_merge($sire_names, [$sire_backtest_from, $sire_backtest_to]);
    $lvl2 = $wpdb->get_results($wpdb->prepare($lvl2_sql, ...$lvl2_params));

    // Level 3: Mar-Oct any distance
    $lvl3_sql = "
        SELECT hrunb.sire_name AS sire_name,
               COUNT(*) AS runs,
               ROUND(AVG((rf.field_size - hrunb.finish_position) / (rf.field_size - 1)) * 100, 1) AS raw_prb_pct,
               'Mar-Oct, any dist' AS context
        FROM historic_runners_beta hrunb
        INNER JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
        LEFT JOIN daily_races_beta dracb ON dracb.race_id = hrunb.race_id
        INNER JOIN (
            SELECT race_id, COUNT(*) AS field_size
            FROM historic_runners_beta
            WHERE finish_position IS NOT NULL AND finish_position > 0
            GROUP BY race_id
            HAVING COUNT(*) > 1
        ) rf ON rf.race_id = hrunb.race_id
        WHERE hrunb.sire_name IN ($sire_placeholders)
          AND MONTH(hracb.meeting_date) BETWEEN 3 AND 10
          AND $sire_common_where
        GROUP BY hrunb.sire_name";
    $lvl3_params = array_merge($sire_names, [$sire_backtest_from, $sire_backtest_to]);
    $lvl3 = $wpdb->get_results($wpdb->prepare($lvl3_sql, ...$lvl3_params));

    // Level 4: All Flat any distance (no seasonal filter)
    $lvl4_sql = "
        SELECT hrunb.sire_name AS sire_name,
               COUNT(*) AS runs,
               ROUND(AVG((rf.field_size - hrunb.finish_position) / (rf.field_size - 1)) * 100, 1) AS raw_prb_pct,
               'All Flat' AS context
        FROM historic_runners_beta hrunb
        INNER JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
        LEFT JOIN daily_races_beta dracb ON dracb.race_id = hrunb.race_id
        INNER JOIN (
            SELECT race_id, COUNT(*) AS field_size
            FROM historic_runners_beta
            WHERE finish_position IS NOT NULL AND finish_position > 0
            GROUP BY race_id
            HAVING COUNT(*) > 1
        ) rf ON rf.race_id = hrunb.race_id
        WHERE hrunb.sire_name IN ($sire_placeholders)
          AND $sire_common_where
        GROUP BY hrunb.sire_name";
    $lvl4_params = array_merge($sire_names, [$sire_backtest_from, $sire_backtest_to]);
    $lvl4 = $wpdb->get_results($wpdb->prepare($lvl4_sql, ...$lvl4_params));

    // Merge levels with preference order 1 -> 2 -> 3 -> 4; prefer larger runs within same level
    $by_sire = [];
    $apply_level = function($rows) use (&$by_sire) {
        foreach ($rows as $r) {
            $sire_key = trim((string)$r->sire_name);
            if ($sire_key === '') continue;
            if (!isset($by_sire[$sire_key]) || intval($r->runs) > intval($by_sire[$sire_key]['runs'])) {
                $by_sire[$sire_key] = [
                    'runs' => intval($r->runs),
                    'raw_prb_pct' => floatval($r->raw_prb_pct),
                    'context' => (string)$r->context,
                ];
            }
        }
    };
    $apply_level($lvl4);
    $apply_level($lvl3);
    $apply_level($lvl2);
    $apply_level($lvl1);

    if (!empty($by_sire)) {
        foreach ($by_sire as $sire_key => $vals) {
            $runs = isset($vals['runs']) ? intval($vals['runs']) : 0;
            $raw_prb = isset($vals['raw_prb_pct']) ? floatval($vals['raw_prb_pct']) : $baseline_prb;
            $adj_prb = ($runs > 0)
                ? round((($runs * $raw_prb) + ($sire_shrink_k * $baseline_prb)) / ($runs + $sire_shrink_k), 1)
                : round($baseline_prb, 1);
            $vals['prb_pct'] = $adj_prb;
            $vals['raw_prb_pct'] = round($raw_prb, 1);
            $vals['baseline_prb_pct'] = round($baseline_prb, 1);
            $vals['shrink_k'] = $sire_shrink_k;

            $sire_key_lower = strtolower($sire_key);
            $sire_key_norm = bricks_normalize_sire_name_key($sire_key);
            $sire_5y_lookup[$sire_key] = $vals;
            $sire_5y_lookup[$sire_key_lower] = $vals;
            if ($sire_key_norm !== $sire_key_lower) {
                $sire_5y_lookup[$sire_key_norm] = $vals;
            }
        }
    }
        set_transient($lin5_cache_key, ['lookup' => $sire_5y_lookup], 30 * MINUTE_IN_SECONDS);
    }
}

// Sort runners by FSr (fhorsite_rating) from speed ratings - highest first
if ($runners && count($runners) > 0) {
    usort($runners, function($a, $b) use ($speed_ratings_lookup) {
        // Get FSr for runner A
        $fsr_a = -999; // Default to very low number for horses without FSr
        if (isset($speed_ratings_lookup[$a->name])) {
            $speed_data_a = $speed_ratings_lookup[$a->name];
            if (isset($speed_data_a->fhorsite_rating) && $speed_data_a->fhorsite_rating !== '' && $speed_data_a->fhorsite_rating !== null) {
                $fsr_a = floatval($speed_data_a->fhorsite_rating);
            }
        }
        
        // Get FSr for runner B
        $fsr_b = -999; // Default to very low number for horses without FSr
        if (isset($speed_ratings_lookup[$b->name])) {
            $speed_data_b = $speed_ratings_lookup[$b->name];
            if (isset($speed_data_b->fhorsite_rating) && $speed_data_b->fhorsite_rating !== '' && $speed_data_b->fhorsite_rating !== null) {
                $fsr_b = floatval($speed_data_b->fhorsite_rating);
            }
        }
        
        // Sort descending (highest FSr first)
        // If FSr values are equal, sort by cloth number
        if ($fsr_b == $fsr_a) {
            return intval($a->cloth_number) <=> intval($b->cloth_number);
        }
        return $fsr_b <=> $fsr_a;
    });
    
    bricks_debug_log("Race Detail Debug - Runners sorted by FSr (highest first)");
}

// Trainers-for-course signal (5y lookback up to yesterday) for trainers in this race.
$trainer_course_lookup = [];
$trainer_course_ranks = [];
if (!empty($runners) && !empty($race->course)) {
    $trainer_names = [];
    foreach ($runners as $runner_item) {
        if (isset($runner_item->trainer_name) && $runner_item->trainer_name !== '') {
            $trainer_names[] = trim((string) $runner_item->trainer_name);
        }
    }
    $trainer_names = array_values(array_unique(array_filter($trainer_names)));

    if (!empty($trainer_names)) {
        $tfc_from = date('Y-m-d', strtotime('-5 years', strtotime($race->meeting_date)));
        $tfc_to = date('Y-m-d', strtotime('-1 day', strtotime($race->meeting_date)));
        $tfc_cache_key = 'bricks_tfc_' . md5(wp_json_encode([
            'course' => (string) $race->course,
            'race_date' => (string) $race->meeting_date,
            'trainer_names' => $trainer_names
        ]));
        $tfc_cached = get_transient($tfc_cache_key);

        if (is_array($tfc_cached)) {
            $trainer_course_lookup = $tfc_cached;
        } else {
            $placeholders = implode(',', array_fill(0, count($trainer_names), '%s'));
            $trainer_course_sql = "
                SELECT
                    hrunb.trainer_name AS trainer_name,
                    COUNT(*) AS runs,
                    SUM(CASE WHEN CAST(hrunb.finish_position AS UNSIGNED) = 1 THEN 1 ELSE 0 END) AS wins
                FROM historic_runners_beta hrunb
                INNER JOIN historic_races_beta hracb ON hracb.race_id = hrunb.race_id
                WHERE hrunb.trainer_name IN ($placeholders)
                  AND hracb.course = %s
                  AND hracb.meeting_date BETWEEN %s AND %s
                  AND hrunb.finish_position REGEXP '^[0-9]+$'
                GROUP BY hrunb.trainer_name";

            $query_params = array_merge($trainer_names, [(string) $race->course, $tfc_from, $tfc_to]);
            $trainer_course_rows = $wpdb->get_results($wpdb->prepare($trainer_course_sql, ...$query_params));

            if (!empty($trainer_course_rows)) {
                foreach ($trainer_course_rows as $tc_row) {
                    $tn = isset($tc_row->trainer_name) ? trim((string) $tc_row->trainer_name) : '';
                    $runs = isset($tc_row->runs) ? intval($tc_row->runs) : 0;
                    $wins = isset($tc_row->wins) ? intval($tc_row->wins) : 0;
                    if ($tn === '' || $runs <= 0) {
                        continue;
                    }
                    $trainer_course_lookup[$tn] = [
                        'runs' => $runs,
                        'wins' => $wins,
                        'win_pct' => round(($wins / $runs) * 100, 1)
                    ];
                }
            }
            set_transient($tfc_cache_key, $trainer_course_lookup, 30 * MINUTE_IN_SECONDS);
        }

        if (!empty($trainer_course_lookup)) {
            $rank_pool = $trainer_course_lookup;
            uasort($rank_pool, function($a, $b) {
                if ($b['win_pct'] == $a['win_pct']) {
                    return $b['runs'] <=> $a['runs'];
                }
                return $b['win_pct'] <=> $a['win_pct'];
            });
            $rank = 1;
            foreach ($rank_pool as $trainer_name_key => $vals) {
                $trainer_course_ranks[$trainer_name_key] = $rank++;
            }
        }
    }
}

// Points engine pass: compute model score, market rank, and edge score for each runner.
$race_points_scored = [];
$race_points_by_key = [];
$race_points_by_name = [];
if (!empty($runners)) {
    foreach ($runners as $idx => $runner_item) {
        $runner_name = isset($runner_item->name) ? (string) $runner_item->name : '';
        $runner_id_int = isset($runner_item->runner_id) ? intval($runner_item->runner_id) : 0;
        $runner_key = ($runner_id_int > 0 ? (string) $runner_id_int : 'idx_' . $idx);

        $is_non_runner = false;
        if (isset($runner_item->non_runner) && intval($runner_item->non_runner) === 1) {
            $is_non_runner = true;
        }
        if (!$is_non_runner && $runner_id_int > 0) {
            $lookup_key = $race_id . ':' . $runner_id_int;
            if (isset($non_runner_lookup[$lookup_key])) {
                $is_non_runner = true;
            }
        }

        $speed_data = null;
        if ($runner_name !== '' && isset($speed_ratings_lookup[$runner_name])) {
            $speed_data = $speed_ratings_lookup[$runner_name];
        } elseif ($runner_id_int > 0 && isset($speed_ratings_by_runner_id[(string)$runner_id_int])) {
            $speed_data = $speed_ratings_by_runner_id[(string)$runner_id_int];
        }

        $trainer_name_key = isset($runner_item->trainer_name) ? trim((string) $runner_item->trainer_name) : '';
        $trainer_course = ($trainer_name_key !== '' && isset($trainer_course_lookup[$trainer_name_key])) ? $trainer_course_lookup[$trainer_name_key] : null;
        $trainer_course_pct = ($trainer_course && isset($trainer_course['win_pct'])) ? floatval($trainer_course['win_pct']) : null;

        $maturity_edge_score = null;
        if ($show_maturity_edge) {
            $runner_foaling_date = '';
            if ($speed_data && isset($speed_data->foaling_date)) {
                $runner_foaling_date = $speed_data->foaling_date;
            } elseif (isset($runner_item->foaling_date)) {
                $runner_foaling_date = $runner_item->foaling_date;
            }
            $maturity_edge = bricks_calculate_maturity_edge($runner_foaling_date, $race->meeting_date);
            if (is_array($maturity_edge) && isset($maturity_edge['score'])) {
                $maturity_edge_score = floatval($maturity_edge['score']);
            }
        }

        $points_result = bricks_points_score_runner($runner_item, $speed_data, [
            'is_flat' => !$is_national_hunt,
            'trainer_course_pct' => $trainer_course_pct,
            'maturity_edge_score' => $maturity_edge_score
        ]);

        $forecast_decimal = null;
        $forecast_fractional = '';
        if ($speed_data) {
            $forecast_decimal = $speed_data->forecast_price_decimal ?? null;
            $forecast_fractional = (string) ($speed_data->forecast_price ?? '');
        }
        if (($forecast_decimal === null || $forecast_decimal === '') && isset($runner_item->forecast_price_decimal)) {
            $forecast_decimal = $runner_item->forecast_price_decimal;
        }
        if ($forecast_fractional === '' && isset($runner_item->forecast_price)) {
            $forecast_fractional = (string) $runner_item->forecast_price;
        }

        $odds_decimal = bricks_points_parse_decimal_odds($forecast_decimal, $forecast_fractional);
        $market_prob = bricks_points_market_implied_rank($odds_decimal);

        $entry = [
            'runner_key' => $runner_key,
            'runner_id' => $runner_id_int,
            'horse_name' => $runner_name,
            'model_score' => isset($points_result['score']) ? floatval($points_result['score']) : 0.0,
            'model_reasons' => isset($points_result['reasons']) && is_array($points_result['reasons']) ? $points_result['reasons'] : [],
            'market_prob' => $market_prob,
            'market_rank' => 0,
            'model_rank' => 0,
            'edge_score' => 0.0,
            'odds_decimal' => $odds_decimal,
            'odds_fractional' => $forecast_fractional,
            'is_non_runner' => $is_non_runner
        ];

        $race_points_scored[] = $entry;
    }

    $model_sorted = $race_points_scored;
    usort($model_sorted, function($a, $b) {
        return ($b['model_score'] ?? 0) <=> ($a['model_score'] ?? 0);
    });
    $model_rank = 1;
    $model_rank_map = [];
    foreach ($model_sorted as $ms) {
        if (!empty($ms['is_non_runner'])) continue;
        $model_rank_map[$ms['runner_key']] = $model_rank++;
    }

    $market_sorted = $race_points_scored;
    usort($market_sorted, function($a, $b) {
        return ($b['market_prob'] ?? 0) <=> ($a['market_prob'] ?? 0);
    });
    $market_rank = 1;
    $market_rank_map = [];
    foreach ($market_sorted as $mk) {
        if (!empty($mk['is_non_runner'])) continue;
        if (($mk['market_prob'] ?? 0) <= 0) continue;
        $market_rank_map[$mk['runner_key']] = $market_rank++;
    }

    foreach ($race_points_scored as &$scored_ref) {
        $rk = $scored_ref['runner_key'];
        $scored_ref['model_rank'] = isset($model_rank_map[$rk]) ? intval($model_rank_map[$rk]) : 0;
        $scored_ref['market_rank'] = isset($market_rank_map[$rk]) ? intval($market_rank_map[$rk]) : 0;

        $rank_edge = ($scored_ref['market_rank'] > 0 && $scored_ref['model_rank'] > 0)
            ? ($scored_ref['market_rank'] - $scored_ref['model_rank'])
            : 0;
        $score_edge = max(0.0, (floatval($scored_ref['model_score']) - 55.0) * 0.20);
        $scored_ref['edge_score'] = round(($rank_edge * 4.0) + $score_edge, 2);

        $race_points_by_key[$rk] = $scored_ref;
        if (!empty($scored_ref['horse_name'])) {
            $race_points_by_name[$scored_ref['horse_name']] = $scored_ref;
        }
    }
    unset($scored_ref);
}

$race_points_picks = bricks_points_pick_winner_place($race_points_scored);
$race_points_ew_simple = bricks_points_pick_each_way_simple($race_points_scored);
$race_points_ew_edge = bricks_points_pick_each_way_edge($race_points_scored);

if (function_exists('bricks_points_published_picks_save') && !empty($race->meeting_date)) {
    bricks_points_published_picks_save(
        $race_id,
        $race->meeting_date,
        $race_points_picks,
        $race_points_ew_simple,
        $race_points_ew_edge
    );
}

if (function_exists('bricks_debug_enabled') && bricks_debug_enabled()) {
    bricks_debug_log('Race Points Engine Debug - Payload: ' . wp_json_encode([
        'race_id' => intval($race_id),
        'winner' => $race_points_picks['winner']['horse_name'] ?? null,
        'ew_simple' => $race_points_ew_simple['horse_name'] ?? null,
        'ew_edge' => $race_points_ew_edge['horse_name'] ?? null,
        'rows' => array_map(function($r) {
            return [
                'runner' => $r['horse_name'] ?? '',
                'model_score' => $r['model_score'] ?? 0,
                'model_rank' => $r['model_rank'] ?? 0,
                'market_rank' => $r['market_rank'] ?? 0,
                'edge_score' => $r['edge_score'] ?? 0,
                'odds_decimal' => $r['odds_decimal'] ?? null
            ];
        }, $race_points_scored)
    ]));
}

    
    // Rest of your function continues exactly the same...
    $content = '';
    
    // If this is a standalone page, include header (same as daily page)
    if (bricks_is_standalone_page() && !headers_sent()) {
        ob_start();
        get_header();
        $content .= ob_get_clean();
        
        $content .= '<main class="main-content"><div class="content-container">';
    }
    
    ob_start();
    ?>

    <style>
          <style>
        /* Add these new styles to your existing CSS */
        .back-button-container {
            max-width: 1400px;
            margin: 0 auto 20px auto;
            padding: 0 20px;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
            color: white;
        }
        
        .back-button-icon {
            font-size: 18px;
            transition: transform 0.3s ease;
        }
        
        .back-button:hover .back-button-icon {
            transform: translateX(-4px);
        }
        
        /* Quick Select Navigation */
        .race-quick-nav {
            max-width: 1400px;
            margin: 0 auto 20px auto;
            padding: 0 20px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .race-quick-nav-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 2px solid #e5e7eb;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .race-quick-nav-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #6b7280;
        }
        
        .race-quick-nav-breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .race-quick-nav-breadcrumb a:hover {
            color: #2563eb;
        }
        
        .race-quick-nav-times {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .race-time-slot {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            color: #6b7280;
            background: #f3f4f6;
            border: 2px solid transparent;
        }
        
        .race-time-slot:hover {
            background: #e5e7eb;
            color: #374151;
        }
        
        .race-time-slot.active {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-color: #059669;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .course-selector-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .course-selector-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .course-selector-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .course-selector-btn::after {
            content: '▼';
            font-size: 12px;
            margin-left: 4px;
            transition: transform 0.3s ease;
        }
        
        .course-selector-btn.open::after {
            transform: rotate(180deg);
        }
        
        .course-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            min-width: 300px;
            max-width: 400px;
            max-height: 500px;
            overflow-y: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
            border: 2px solid #e5e7eb;
        }
        
        .course-dropdown.show {
            display: block;
        }
        
        .course-dropdown-item {
            border-bottom: 1px solid #f3f4f6;
        }
        
        .course-dropdown-item:last-child {
            border-bottom: none;
        }
        
        .course-dropdown-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 700;
            font-size: 14px;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .course-type-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .course-type-badge.flat {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .course-type-badge.jumps {
            background: #fef3c7;
            color: #92400e;
        }
        
        .course-race-times {
            padding: 12px 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .course-race-time-link {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            color: #3b82f6;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            transition: all 0.2s ease;
        }
        
        .course-race-time-link:hover {
            background: #dbeafe;
            color: #1e40af;
            transform: translateY(-1px);
        }
        
        .course-race-time-link.current {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-color: #059669;
        }
        
        @media (max-width: 768px) {
            .back-button-container {
                padding: 0 16px;
            }
            
            .back-button {
                padding: 10px 16px;
                font-size: 13px;
            }
            
            .race-quick-nav {
                padding: 0 16px;
            }
            
            .race-quick-nav-top {
                flex-direction: column;
                align-items: flex-start;
                padding: 12px 16px;
            }
            
            .race-quick-nav-breadcrumb {
                font-size: 12px;
                margin-bottom: 8px;
            }
            
            .race-quick-nav-times {
                width: 100%;
                justify-content: flex-start;
            }
            
            .race-time-slot {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .course-selector-btn {
                padding: 10px 16px;
                font-size: 14px;
                width: 100%;
            }
            
            .course-dropdown {
                left: 0;
                right: 0;
                min-width: auto;
                max-width: none;
                max-height: 400px;
            }
            
            .course-dropdown-header {
                padding: 12px 16px;
                font-size: 12px;
            }
            
            .course-race-times {
                padding: 10px 16px;
            }
            
            .course-race-time-link {
                padding: 5px 10px;
                font-size: 11px;
            }
            
            .filter-controls {
                padding: 12px 16px;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-toggle label {
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .back-button-container,
            .race-quick-nav {
                padding: 0 12px;
            }
            
            .race-quick-nav-top {
                padding: 10px 12px;
            }
            
            .race-time-slot {
                padding: 5px 10px;
                font-size: 11px;
            }
            
            .course-selector-btn {
                font-size: 13px;
            }
        }
        
        /* Race Detail Responsive Styles */
        @media (max-width: 768px) {
            .race-detail-container {
                padding: 16px;
            }
            
            .race-header-card {
                border-radius: 12px;
                margin-bottom: 20px;
            }
            
            .race-header-top {
                padding: 16px;
            }
            
            .race-meta-time {
                font-size: 16px;
            }
            
            .race-title {
                font-size: 20px;
                margin-bottom: 16px;
            }
            
            .race-details-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .race-detail-item {
                padding: 10px;
            }
            
            .race-detail-icon {
                font-size: 20px;
            }
            
            .race-detail-value {
                font-size: 14px;
            }
            
            .race-header-bottom {
                padding: 16px;
            }
            
            .track-advice-box {
                padding: 12px;
                font-size: 13px;
            }
            
            .prize-info {
                grid-template-columns: 1fr;
            }
            
            .prize-item {
                padding: 10px 12px;
            }
            
            .prize-amount {
                font-size: 18px;
            }
            
            .last-year-winner {
                padding: 12px;
            }
            
            .runners-card {
                border-radius: 12px;
            }
            
            .runners-header {
                padding: 16px;
            }
            
            .runners-title {
                font-size: 20px;
            }
            
            .runners-subtitle {
                font-size: 13px;
            }
            
            .runners-table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .runners-table {
                font-size: 12px;
                min-width: 1000px;
            }
            
            .runners-table th,
            .runners-table td {
                padding: 10px 8px;
            }
            
            .runners-table th {
                font-size: 10px;
            }
            
            .cloth-badge {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .horse-name {
                font-size: 13px;
            }
            
            .rating-badge,
            .speed-excellent,
            .speed-good,
            .speed-average {
                font-size: 12px;
                padding: 4px 10px;
            }
            
            .weight-badge {
                font-size: 12px;
                padding: 3px 8px;
            }
            
            .odds-badge {
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .race-detail-container {
                padding: 12px;
            }
            
            .race-header-top {
                padding: 12px;
            }
            
            .race-title {
                font-size: 18px;
            }
            
            .race-meta-time {
                font-size: 14px;
            }
            
            .runners-header {
                padding: 12px;
            }
            
            .runners-title {
                font-size: 18px;
            }
            
            .runners-table {
                font-size: 11px;
            }
            
            .runners-table th,
            .runners-table td {
                padding: 8px 6px;
            }
        }
        
        /* Filter controls for non-runners */
        .filter-controls {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 16px 24px;
            border-bottom: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .filter-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-toggle label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
            user-select: none;
        }
        
        .filter-toggle input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #3b82f6;
        }
        
        .non-runner-badge {
            display: inline-block;
            padding: 4px 10px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35);
        }
        
        .runner-row.non-runner {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%) !important;
            border-left: 4px solid #2563eb;
        }
        
        .runner-row.non-runner.hidden {
            display: none;
        }

        .maturity-edge-note {
            margin-top: 8px;
            color: #475569;
            font-size: 12px;
        }

        .maturity-info-tip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            margin-left: 6px;
            border-radius: 50%;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 700;
            cursor: help;
            vertical-align: middle;
        }

        .maturity-edge-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.2;
            cursor: help;
        }

        .maturity-edge-badge.strong {
            background: #dcfce7;
            color: #166534;
        }

        .maturity-edge-badge.positive {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .maturity-edge-badge.neutral {
            background: #e2e8f0;
            color: #334155;
        }

        .maturity-edge-badge.caution {
            background: #fef3c7;
            color: #92400e;
        }

        .maturity-edge-badge.negative {
            background: #fee2e2;
            color: #991b1b;
        }
        .lin5-cell {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .lin5-quality-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 999px;
            letter-spacing: 0.2px;
            line-height: 1.1;
        }
        .lin5-quality-high {
            background: #dcfce7;
            color: #166534;
        }
        .lin5-quality-med {
            background: #fef3c7;
            color: #92400e;
        }
        .lin5-quality-low {
            background: #fee2e2;
            color: #991b1b;
        }
        .lin5-quality-na {
            background: #e5e7eb;
            color: #374151;
        }
        /* Race Detail Modern Styling */
        .race-detail-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .race-header-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }

        .race-header-top {
            background: rgba(0,0,0,0.2);
            padding: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .race-meta-time {
            color: #ef4444;
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .race-title {
            color: white;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .race-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            color: white;
        }

        .race-detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .race-detail-icon {
            font-size: 24px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
        }

        .race-detail-content {
            flex: 1;
        }

        .race-detail-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.8;
            margin-bottom: 4px;
        }

        .race-detail-value {
            font-size: 16px;
            font-weight: 700;
        }

        .race-header-bottom {
            padding: 24px;
        }

        .track-advice-box {
            background: rgba(59, 130, 246, 0.15);
            border-left: 4px solid #3b82f6;
            padding: 16px;
            border-radius: 8px;
            color: #e0f2fe;
            margin-bottom: 16px;
        }

        .track-advice-box strong {
            color: #60a5fa;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .prize-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .prize-item {
            background: rgba(16, 185, 129, 0.15);
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .prize-label {
            font-size: 11px;
            color: #6ee7b7;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .prize-amount {
            font-size: 20px;
            font-weight: 800;
            color: #10b981;
        }

        .last-year-winner {
            background: rgba(245, 158, 11, 0.15);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fde68a;
        }

        .last-year-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #fbbf24;
            margin-bottom: 6px;
        }

        .last-year-value {
            font-size: 16px;
            font-weight: 600;
        }

        /* Runners Table */
        .runners-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
        }

        .runners-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 24px;
            border-bottom: 2px solid #dee2e6;
        }

        .runners-title {
            margin: 0 0 8px 0;
            color: #1e293b;
            font-size: 28px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .runners-subtitle {
            margin: 0;
            color: #64748b;
            font-size: 15px;
        }

        .runners-table-wrapper {
            overflow-x: auto;
        }

        .runners-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .runners-table thead {
            background: linear-gradient(180deg, #f8f9fa 0%, #f1f3f5 100%);
        }

        .runners-table th {
            padding: 16px 12px;
            text-align: left;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }

        .runners-table th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            transition: background-color 0.2s ease;
        }

        .runners-table th.sortable:hover {
            background: #e2e8f0;
        }

        /* Mobile Tooltips for Headers */
        .runners-table th[title] {
            position: relative;
            cursor: help;
            outline: none; /* Remove focus ring */
        }

        .runners-table th[title]::after {
            content: attr(title);
            position: absolute;
            top: 100%; /* Show below the header */
            left: 50%;
            transform: translateX(-50%) translateY(10px);
            background: #1e293b;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: none;
            white-space: normal;
            min-width: 160px;
            text-align: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            pointer-events: none;
        }

        .runners-table th[title]::before {
            content: '';
            position: absolute;
            top: 100%; /* Show below the header */
            left: 50%;
            transform: translateX(-50%) translateY(0);
            border: 6px solid transparent;
            border-bottom-color: #1e293b; /* Arrow pointing up */
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 10000;
            pointer-events: none;
        }

        /* Show tooltip on hover or focus (mobile tap) */
        .runners-table th[title]:hover::after,
        .runners-table th[title]:hover::before,
        .runners-table th[title]:focus::after,
        .runners-table th[title]:focus::before,
        .runners-table th[title]:active::after,
        .runners-table th[title]:active::before {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(5px);
        }

        /* Adjust arrow transform on hover to match text */
        .runners-table th[title]:hover::before,
        .runners-table th[title]:focus::before,
        .runners-table th[title]:active::before {
             transform: translateX(-50%) translateY(0);
        }

        .runners-table th.sortable .sort-arrow {
            font-size: 10px;
            color: #94a3b8;
            margin-left: 3px;
        }

        .runners-table th.sortable.sorted-asc .sort-arrow,
        .runners-table th.sortable.sorted-desc .sort-arrow {
            color: #2563eb;
        }

        .runners-table th.sortable.sorted-asc,
        .runners-table th.sortable.sorted-desc {
            background: #e0e7ff;
            border-bottom-color: #2563eb;
        }

        .runners-table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f1f3f5;
        }

        .runners-table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .runners-table td {
            padding: 16px 12px;
            color: #334155;
        }

        .cloth-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            font-weight: 800;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .horse-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 15px;
        }

        .horse-gender {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .jockey-name {
            font-weight: 600;
            color: #334155;
        }

        .jockey-claim {
            font-size: 11px;
            color: #10b981;
            font-weight: 600;
            margin-top: 2px;
        }

        .rating-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
        }

        .weight-badge {
            font-family: 'SF Mono', Monaco, monospace;
            font-weight: 600;
            color: #6366f1;
            background: #eef2ff;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .odds-badge {
            font-weight: 700;
            color: #059669;
            font-size: 15px;
        }

        .speed-excellent {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.4);
        }

        .speed-good {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 3px 10px rgba(59, 130, 246, 0.4);
        }

        .speed-average {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 3px 10px rgba(245, 158, 11, 0.4);
        }

        .speed-low {
            color: #94a3b8;
            font-weight: 600;
        }
        /* Horse name link styling */
.horse-name-link {
    color: #2563eb !important;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    transition: all 0.2s ease;
    position: relative;
}

.horse-name-link:hover {
    color: #1d4ed8 !important;
    text-decoration: underline;
    transform: translateX(2px);
}

.horse-name-link::after {
    content: '🔗';
    font-size: 10px;
    opacity: 0;
    margin-left: 4px;
    transition: opacity 0.2s ease;
}

.horse-name-link:hover::after {
    opacity: 0.6;
}


        @media (max-width: 768px) {
            .race-detail-container {
                padding: 12px;
            }

            .race-title {
                font-size: 20px;
            }

            .race-details-grid {
                grid-template-columns: 1fr;
            }

            .runners-table {
                font-size: 12px;
            }

            .runners-table th,
            .runners-table td {
                padding: 10px 8px;
            }
        }

        .toggle-details-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.green-text {
    color: #10b981;
    font-weight: 700;
}

.red-text {
    color: #ef4444;
    font-weight: 700;
}

    </style>
          <!-- Add debug info to the page -->

    
    <!-- Your existing styling and HTML continues here... -->

    <div class="race-detail-container">
    <div class="back-button-container">
            <a href="<?php echo home_url('/daily/'); ?>" class="back-button">
                <span class="back-button-icon">←</span>
                <span>Back to Daily Races</span>
            </a>
        </div>

        <!-- Quick Select Navigation -->
        <?php if (!empty($races_by_course)): ?>
        <div class="race-quick-nav">
            <div class="race-quick-nav-top">
                <div class="race-quick-nav-breadcrumb">
                    <a href="<?php echo home_url('/daily/'); ?>">Horse Racing</a>
                    <span>/</span>
                    <span><?php echo esc_html($race->course); ?></span>
                </div>
                
                <div class="course-selector-wrapper">
                    <button class="course-selector-btn" onclick="toggleCourseDropdown()">
                        <?php echo esc_html($race->course); ?>
                    </button>
                    <div class="course-dropdown" id="courseDropdown">
                        <?php foreach ($races_by_course as $course_name => $course_races): 
                            // Determine type from today's races at this course (not static course_features race_code).
                            $has_jumps_type = false;
                            $has_flat_type = false;
                            foreach ($course_races as $course_race_item) {
                                $rt = strtolower((string) ($course_race_item->race_type ?? ''));
                                $is_jump = (
                                    strpos($rt, 'hurdle') !== false ||
                                    strpos($rt, 'chase') !== false ||
                                    strpos($rt, 'nh') !== false ||
                                    strpos($rt, 'national hunt') !== false
                                );
                                if ($is_jump) {
                                    $has_jumps_type = true;
                                } else {
                                    $has_flat_type = true;
                                }
                            }

                            if ($has_jumps_type && $has_flat_type) {
                                $course_type = 'Mixed';
                            } elseif ($has_jumps_type) {
                                $course_type = 'Jumps';
                            } else {
                                $course_type = 'Flat';
                            }
                        ?>
                        <div class="course-dropdown-item">
                            <div class="course-dropdown-header">
                                <span><?php echo esc_html($course_name); ?></span>
                                <span class="course-type-badge <?php echo strtolower($course_type); ?>"><?php echo esc_html($course_type); ?></span>
                            </div>
                            <div class="course-race-times">
                                <?php foreach ($course_races as $cr): 
                                    $time_str = date('H:i', strtotime($cr->scheduled_time));
                                    $is_current = ($cr->race_id == $race_id);
                                ?>
                                <a href="<?php echo esc_url(bricks_race_url($cr->race_id)); ?>" 
                                   class="course-race-time-link <?php echo $is_current ? 'current' : ''; ?>"
                                   title="<?php echo esc_attr($cr->race_title); ?>">
                                    <?php echo esc_html($time_str); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="race-quick-nav-times">
                    <?php 
                    $current_course_races = $races_by_course[$race->course] ?? [];
                    foreach ($current_course_races as $cr): 
                        $time_str = date('H:i', strtotime($cr->scheduled_time));
                        $is_current = ($cr->race_id == $race_id);
                    ?>
                    <a href="<?php echo esc_url(bricks_race_url($cr->race_id)); ?>" 
                       class="race-time-slot <?php echo $is_current ? 'active' : ''; ?>"
                       title="<?php echo esc_attr($cr->race_title); ?>">
                        <?php echo esc_html($time_str); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Race Header Card -->
        <div class="race-header-card">
            <div class="race-header-top">
                <div class="race-meta-time">
                    🏁 <?php echo date('H:i', strtotime($race->scheduled_time)); ?> • 
                    <?php echo esc_html($race->course); ?> • 
                    <?php echo date('l, d M Y', strtotime($race->meeting_date)); ?>
                </div>
                
                <div class="race-title">
                    <?php echo esc_html($race->race_title); ?>
                </div>
                
                <div class="race-details-grid">
                    <div class="race-detail-item">
                        <div class="race-detail-icon">🏆</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Class & Age</div>
                            <div class="race-detail-value">
                                Class <?php echo esc_html($race->class); ?> • <?php echo esc_html($race->age_range); ?>
                            </div>
                        </div>
                    </div>

                    <div class="race-detail-item">
                        <div class="race-detail-icon">🌍</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Country & Runners</div>
                            <div class="race-detail-value">
                                <?php echo esc_html($race->country); ?> • <?php echo count($runners); ?> Runners
                            </div>
                        </div>
                    </div>

                    <div class="race-detail-item">
                        <div class="race-detail-icon">🎯</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Race Type</div>
                            <div class="race-detail-value">
                                <?php echo esc_html($race->race_type); ?>
                                <?php if ($race->handicap !== null): ?>
                                    • <?php echo $race->handicap ? 'Handicap' : 'Non-Handicap'; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="race-detail-item">
                        <div class="race-detail-icon">📏</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Distance</div>
                            <div class="race-detail-value">
                                <?php 
                                $distance_yards = $race->distance_yards;
                                $miles = floor($distance_yards / 1760);
                                $remaining_yards = $distance_yards % 1760;
                                $furlongs = round($distance_yards / 220, 1);
                                
                                if ($miles > 0) {
                                    echo $miles . 'm' . $remaining_yards . 'y (' . $furlongs . 'f)';
                                } else {
                                    echo $remaining_yards . 'y (' . $furlongs . 'f)';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="race-detail-item">
                        <div class="race-detail-icon">🏇</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Track Type</div>
                            <div class="race-detail-value">
                                <?php echo esc_html($race->track_type ?: 'N/A'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="race-detail-item">
                        <div class="race-detail-icon">🌤️</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Going</div>
                            <div class="race-detail-value">
                                <?php 
                                $going = 'Good';
                                if ($race && !empty($race->advanced_going)) {
                                    $going = $race->advanced_going;
                                } elseif ($speed_ratings && count($speed_ratings) > 0) {
                                    $first_rating = $speed_ratings[0];
                                    if (isset($first_rating->advanced_going) && !empty($first_rating->advanced_going)) {
                                        $going = $first_rating->advanced_going;
                                    }
                                }
                                echo esc_html($going);
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($course_features): ?>
    <?php if ($course_features->profile): ?>
        <div class="race-detail-item">
            <div class="race-detail-icon">📊</div>
            <div class="race-detail-content">
                <div class="race-detail-label">Course Profile</div>
                <div class="race-detail-value">
                    <?php echo esc_html($course_features->profile); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($course_features->direction): ?>
        <div class="race-detail-item">
            <div class="race-detail-icon">➡️</div>
            <div class="race-detail-content">
                <div class="race-detail-label">Direction</div>
                <div class="race-detail-value">
                    <?php echo esc_html($course_features->direction); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($course_features->general_features): ?>
        <div class="race-detail-item" style="grid-column: span 2;">
            <div class="race-detail-icon">📝</div>
            <div class="race-detail-content">
                <div class="race-detail-label">General Features</div>
                <div class="race-detail-value" style="font-size:14px;line-height:1.4;">
                    <?php echo esc_html($course_features->general_features); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($course_features->specific_features): ?>
        <div class="race-detail-item" style="grid-column: span 2;">
            <div class="race-detail-icon">🎯</div>
            <div class="race-detail-content">
                <div class="race-detail-label">Specific Features</div>
                <div class="race-detail-value" style="font-size:14px;line-height:1.4;">
                    <?php echo esc_html($course_features->specific_features); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

                </div>
            </div>
            
            <div class="race-header-bottom">
                <!-- Track Advice -->
                <div class="track-advice-box">
                    <strong>💡 Track Advice:</strong><br>
                    <?php 
                    $track_advice = '';
                    if ($race && !empty($race->draw_advantage)) {
                        $track_advice = $race->draw_advantage;
                    } elseif ($speed_ratings && count($speed_ratings) > 0) {
                        $first_rating = $speed_ratings[0];
                        if (isset($first_rating->draw_advantage) && !empty($first_rating->draw_advantage)) {
                            $track_advice = $first_rating->draw_advantage;
                        }
                    }
                    
                    if (empty($track_advice)) {
                        if ($distance_yards <= 1100) {
                            $track_advice = 'Low numbers best on the straight track, especially when soft';
                        } elseif ($distance_yards <= 1760) {
                            $track_advice = 'Middle to high numbers often favoured on the round course';
                        } else {
                            $track_advice = 'Stamina and position important on the longer course';
                        }
                    }
                    echo esc_html($track_advice);
                    ?>
                </div>

                <!-- Prize Information -->
                <div class="prize-info">
                    <div class="prize-item">
                        <div class="prize-label">🥇 1st Prize</div>
                        <div class="prize-amount">£<?php echo number_format($race->prize_pos_1 ?: 0, 0); ?></div>
                    </div>
                    <div class="prize-item">
                        <div class="prize-label">🥈 2nd Prize</div>
                        <div class="prize-amount">£<?php echo number_format($race->prize_pos_2 ?: 0, 0); ?></div>
                    </div>
                    <div class="prize-item">
                        <div class="prize-label">🥉 3rd Prize</div>
                        <div class="prize-amount">£<?php echo number_format($race->prize_pos_3 ?: 0, 0); ?></div>
                    </div>
                </div>

                <!-- Last Year's Winner -->
                <div class="last-year-winner">
                    <div class="last-year-label">🏆 Last Year's Winner</div>
                    <div class="last-year-value">
                        <?php 
                        if ($race && $race->last_winner_name) {
                            $winner_name = $race->last_winner_name;
                            $winner_trainer = $race->last_winner_trainer ?: 'Unknown Trainer';
                            $winner_jockey = $race->last_winner_jockey ?: 'Unknown Jockey';
                            $winner_sp = $race->last_winner_sp ?: 'N/A';
                            
                            echo esc_html($winner_name . ' (' . $winner_sp . ') - ' . $winner_trainer . ' / ' . $winner_jockey);
                        } else {
                            $last_year_winner = get_last_year_winner($race->course, $race->race_title, $race->meeting_date);
                            if ($last_year_winner) {
                                echo esc_html($last_year_winner['winner_name'] . ' ' . $last_year_winner['odds'] . ' ' . $last_year_winner['trainer_name']);
                            } else {
                                echo 'No historical data available';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($runners && count($runners) > 0): ?>

        <div class="premium-ratings-container">
        <?php if (!function_exists('bricks_race_detail_can_view_premium') || !bricks_race_detail_can_view_premium()): ?>
            <div class="premium-ratings-paywall-gate" style="padding:28px 24px;background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);border:1px solid #c7d2fe;border-radius:12px;text-align:center;">
                <h2 style="margin:0 0 10px;font-size:22px;color:#1e3a8a;">Fhorsite &amp; speed ratings</h2>
                <p style="margin:0 0 16px;color:#475569;max-width:560px;margin-left:auto;margin-right:auto;">
                    Full runner ratings, Points Engine picks, and performance charts are available to registered members.
                </p>
                <p style="margin:0 0 18px;font-size:13px;color:#64748b;">
                    <?php
                    $runner_names = array_map(function ($r) {
                        return trim((string) ($r->name ?? ''));
                    }, $runners);
                    $runner_names = array_values(array_filter($runner_names));
                    echo esc_html(count($runner_names) . ' runners: ' . implode(', ', array_slice($runner_names, 0, 12)));
                    if (count($runner_names) > 12) {
                        echo esc_html(' …');
                    }
                    ?>
                </p>
                <a href="<?php echo esc_url(wp_login_url(bricks_race_url($race_id))); ?>" style="display:inline-block;padding:10px 18px;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;text-decoration:none;">Log in to view ratings</a>
            </div>
        <?php else: ?>
        
        <!-- Runners Table -->
        <div class="runners-card">
            <div class="runners-header">
                <h2 class="runners-title">
                    <span>🏇</span>
                    Race Runners
                    <span style="background:#e0f2fe;color:#0369a1;padding:4px 12px;border-radius:8px;font-size:16px;font-weight:700;">
                        <?php echo count($runners); ?>
                    </span>
                </h2>
                <p class="runners-subtitle">Complete performance data and speed ratings for all runners</p>
                <?php if ($show_maturity_edge): ?>
                    <p class="maturity-edge-note">
                        Maturity Edge badges shown for 2YO Flat runners (DOB month x season-phase model).
                        <span
                            class="maturity-info-tip"
                            title="Maturity Edge is active for this race because it is classified as 2YO Flat. The Mat column and badges use DOB month plus race-month season phase from your backtest model.">
                            i
                        </span>
                    </p>
                <?php endif; ?>
            </div>

            <div style="background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);border:1px solid #93c5fd;border-radius:12px;padding:14px 16px;margin:0 0 16px 0;">
                <div style="font-weight:800;color:#1e3a8a;font-size:13px;margin-bottom:8px;">Points Engine Picks</div>
                <div style="display:flex;flex-wrap:wrap;gap:10px;font-size:12px;line-height:1.4;">
                    <span title="Top model score among active runners." style="background:#fff;border:1px solid #bfdbfe;border-radius:999px;padding:6px 10px;color:#1e40af;font-weight:700;">
                        Win: <?php echo esc_html($race_points_picks['winner']['horse_name'] ?? 'N/A'); ?>
                    </span>
                    <span title="Top 3 model-ranked runners for place profile." style="background:#fff;border:1px solid #bfdbfe;border-radius:999px;padding:6px 10px;color:#1e40af;font-weight:700;">
                        Place: <?php echo esc_html(implode(', ', array_map(function($p){ return $p['horse_name'] ?? ''; }, $race_points_picks['place'] ?? [])) ?: 'N/A'); ?>
                    </span>
                    <span title="Simple each-way signal: odds 5/1+ and highest model score." style="background:#fff7ed;border:1px solid #fdba74;border-radius:999px;padding:6px 10px;color:#9a3412;font-weight:700;">
                        EW Simple: <?php echo esc_html($race_points_ew_simple['horse_name'] ?? 'N/A'); ?>
                    </span>
                    <span title="Edge each-way signal: odds 5/1+ with strong model-vs-market edge." style="background:#f5f3ff;border:1px solid #c4b5fd;border-radius:999px;padding:6px 10px;color:#5b21b6;font-weight:700;">
                        EW Edge: <?php echo esc_html($race_points_ew_edge['horse_name'] ?? 'N/A'); ?>
                    </span>
                </div>
            </div>

            <!-- Filter Controls -->
    <div class="filter-controls">
        <div class="filter-toggle">
            <label>
                <input type="checkbox" id="hideNonRunners" onchange="toggleNonRunners()">
                <span>Hide Non-Runners</span>
            </label>
            <?php if ($show_maturity_edge): ?>
            <label>
                <input type="checkbox" id="showMaturityBadges" checked onchange="toggleMaturityBadges()">
                <span title="Visible only when Maturity Edge is active (2YO Flat races).">Show Maturity Badges</span>
            </label>
            <?php endif; ?>
        </div>
        <div style="font-size:12px;color:#6b7280;">
            <span id="activeRunnersCount"><?php echo count($runners); ?></span> runners shown
        </div>
    </div>
    <div style="margin:8px 0 14px 0;font-size:12px;color:#6b7280;line-height:1.5;">
        <strong style="color:#374151;">Legend:</strong>
        <strong style="color:#111827;">Lin5</strong> = Lineage 5Y signal (internal): sire PRB% in the locked backtest scope
        (last 5 years to yesterday, Flat races, Mar-Oct).
        <br>
        <span title="PRB% = percentage of rivals beaten by horses from the same sire line. Higher is better.">
            <strong style="color:#111827;">How to read it:</strong> the % shows how often that sire line beats rivals (higher = better).
        </span>
        <span style="margin-left:8px;" title="Badge shows confidence based on sample size (number of runs in the model window).">
            Badge confidence:
            <span class="lin5-quality-badge lin5-quality-high" style="vertical-align:middle;">High</span>
            <span class="lin5-quality-badge lin5-quality-med" style="vertical-align:middle;">Med</span>
            <span class="lin5-quality-badge lin5-quality-low" style="vertical-align:middle;">Low</span>
            <span class="lin5-quality-badge lin5-quality-na" style="vertical-align:middle;">N/A</span>
            (more runs = more reliable).
        </span>
    </div>
            
            <div class="runners-table-wrapper">
               <table class="runners-table <?php echo $is_national_hunt ? 'national-hunt' : 'flat-race'; ?>">

                   <thead>
                <tr>
                    <th class="sortable" tabindex="0" data-sort="number" data-column="cloth_number" title="Cloth Number">No. <span class="sort-arrow"></span></th>
                    <?php if (!$is_national_hunt): ?>
                        <th class="sortable" tabindex="0" data-sort="number" data-column="stall_number" title="Stall Number">Stall <span class="sort-arrow"></span></th>
                        <th class="sortable" tabindex="0" data-sort="number" data-column="draw_bias_pct" title="Draw Bias Percentage">Db% <span class="sort-arrow"></span></th>
                    <?php endif; ?>
                    <?php if (!$is_tomorrow_race): ?>
                    <th class="sortable" tabindex="0" data-sort="number" data-column="win_pct" title="Runner Win Percentage">WIN% <span class="sort-arrow"></span></th>
                    <th class="sortable" tabindex="0" data-sort="number" data-column="place_pct" title="Runner Place Percentage">Pla% <span class="sort-arrow"></span></th>
                    <?php endif; ?>
                    <th class="sortable sorted-desc" tabindex="0" data-sort="number" data-column="fsr" title="Fhorsite Rating (Speed Rating)">FSr <span class="sort-arrow">▼</span></th>
                    <th class="sortable" tabindex="0" data-sort="number" data-column="fsrr" title="Fhorsite Rating Reliability">FSRr <span class="sort-arrow"></span></th>
                    <th class="sortable" tabindex="0" data-sort="number" data-column="model_points" title="Points model score (0-100)">Pts <span class="sort-arrow"></span></th>
                    <th class="sortable" tabindex="0" data-sort="number" data-column="comb" title="Combination of Jockey and Trainer performance by %">Comb <span class="sort-arrow"></span></th>
                    <th tabindex="0" title="Recent Form Figures">Form</th>
                    <th class="sortable" tabindex="0" data-sort="number" data-column="dslr" title="Days since Last Ran">dslr <span class="sort-arrow"></span></th>
                    <th class="sortable" tabindex="0" data-sort="text" data-column="weight" title="Weight (Stones-Pounds)">Wgt <span class="sort-arrow"></span></th>
                   <th tabindex="0" title="Course, Distance, and Going Winner Indicator">C/D/G</th>
<th class="sortable" tabindex="0" data-sort="number" data-column="lbf" title="Beaten Favorite Last Time Out">LBF <span class="sort-arrow"></span></th>
<th tabindex="0" title="Horse Name">Name</th>
<?php if ($show_maturity_edge): ?>
<th class="sortable" tabindex="0" data-sort="number" data-column="maturity_edge" title="Maturity Edge Rating">Mat <span class="sort-arrow"></span></th>
<?php endif; ?>
<th class="sortable" tabindex="0" data-sort="number" data-column="sire_5y" title="Lineage 5Y: sire PRB% in locked window (last 5 years to yesterday), Flat, Mar-Oct">Lin5 <span class="sort-arrow"></span></th>
<th class="sortable" tabindex="0" data-sort="number" data-column="cls" title="Class Change from Last Run">Cls <span class="sort-arrow"></span></th>
<th class="sortable" tabindex="0" data-sort="number" data-column="or_diff" title="Official Rating Difference from Last Run">OR+/- <span class="sort-arrow"></span></th>
<th class="sortable" tabindex="0" data-sort="text" data-column="jockey" title="Jockey Name and Claim">Jockey <span class="sort-arrow"></span></th>
<th tabindex="0" title="Trainer Run to Form">Trainer / RTF%</th>

<th style="text-align:center;">Details</th>


                </tr>
            </thead>
                  <tbody>
                        <?php foreach ($runners as $index => $runner): ?>
                            <?php
                              // Check if this is a non-runner
                    $is_non_runner = false;
                    if (isset($runner->non_runner) && $runner->non_runner == 1) {
                        $is_non_runner = true;
                    }
                    $runner_id_int = isset($runner->runner_id) ? intval($runner->runner_id) : 0;
                    if (!$is_non_runner && $runner_id_int > 0) {
                        $lookup_key = $race_id . ':' . $runner_id_int;
                        if (isset($non_runner_lookup[$lookup_key])) {
                            $is_non_runner = true;
                        }
                    }
                    // Alternative check: if forecast_price is null or empty, might be non-runner
                    // Uncomment if needed:
                    // if (empty($runner->forecast_price) || $runner->forecast_price === 'NR') {
                    //     $is_non_runner = true;
                    // }
                            // Format weight
                            $weight_formatted = 'N/A';
                            if ($runner->weight_pounds) {
                                $stones = floor($runner->weight_pounds / 14);
                                $pounds = $runner->weight_pounds % 14;
                                $weight_formatted = $stones . '-' . $pounds;
                            }
                            
                            // Get all speed ratings and related data
                            $speed_data = null;
                            if (isset($speed_ratings_lookup[$runner->name])) {
                                $speed_data = $speed_ratings_lookup[$runner->name];
                            }
                            
                            // Extract all the data fields from speed_data
                            $stall_number = $runner->stall_number ?: '';
                            $draw_bias_pct = $speed_data ? (isset($speed_data->draw_bias_pct) ? round($speed_data->draw_bias_pct, 1) . '%' : '-') : '-';
                            $draw_bias_reliability = $speed_data ? (isset($speed_data->{'draw_bias_reliability_~150'}) ? round($speed_data->{'draw_bias_reliability_~150'}, 1) . '%' : '-') : '-';
                            
                            // Course and distance winner indicators
                            // Course and distance winner indicators with counts
$wins_display = '';
if ($speed_data) {
    $course_wins = isset($speed_data->course_winner) ? intval($speed_data->course_winner) : 0;
    $distance_wins = isset($speed_data->distance_winner) ? intval($speed_data->distance_winner) : 0;
    $candd_wins = isset($speed_data->candd_winner) ? intval($speed_data->candd_winner) : 0;
    $going_wins = isset($speed_data->going_prev_wins) ? intval($speed_data->going_prev_wins) : 0;
    
    $win_parts = [];
    if ($candd_wins > 0) {
        $win_parts[] = 'CD' . ($candd_wins > 1 ? 'x' . $candd_wins : '');
    }
    if ($course_wins > 0 && $candd_wins == 0) {
        $win_parts[] = 'C' . ($course_wins > 1 ? 'x' . $course_wins : '');
    }
    if ($distance_wins > 0 && $candd_wins == 0) {
        $win_parts[] = 'D' . ($distance_wins > 1 ? 'x' . $distance_wins : '');
    }
    if ($going_wins > 0) {
        $win_parts[] = 'G' . ($going_wins > 1 ? 'x' . $going_wins : '');
    }
    
    $wins_display = !empty($win_parts) ? implode(', ', $win_parts) : '-';
} else {
    $wins_display = '-';
}

                            // Win% and Place% from speed data
                            $win_percentage = $speed_data ? (isset($speed_data->prev_runner_win_strike) ? round($speed_data->prev_runner_win_strike, 1) . '%' : '-') : '-';
                            $place_percentage = $speed_data ? (isset($speed_data->prev_runner_place_strike) ? round($speed_data->prev_runner_place_strike, 1) . '%' : '-') : '-';
                            
                            // All the speed ratings and related fields
                            $fsr = $speed_data ? (isset($speed_data->fhorsite_rating) ? $speed_data->fhorsite_rating : '-') : '-';
                            $fsrr = $speed_data ? (isset($speed_data->fhorsite_rating_reliability) ? $speed_data->fhorsite_rating_reliability : '-') : '-';
                            $sr_lto = $speed_data ? (isset($speed_data->SR_LTO) ? $speed_data->SR_LTO : '-') : '-';
                            $cl_lto = $speed_data ? (isset($speed_data->class_LTO) ? $speed_data->class_LTO : '-') : '-';
                            $df_lto = $speed_data ? (isset($speed_data->distance_furlongs_LTO) ? $speed_data->distance_furlongs_LTO : '-') : '-';
                            
                            // Going values – pull directly from the same speed table columns as SR/DF/CL
                            // LTO + last 6 runs
                            $going_lto = $speed_data ? (isset($speed_data->going_LTO) && $speed_data->going_LTO !== '' ? $speed_data->going_LTO : '-') : '-';
                            
                            // All speed ratings (SR_2 through SR_6)
                            $sr_2 = $speed_data ? (isset($speed_data->SR_2) ? $speed_data->SR_2 : '-') : '-';
                            $sr_3 = $speed_data ? (isset($speed_data->SR_3) ? $speed_data->SR_3 : '-') : '-';
                            $sr_4 = $speed_data ? (isset($speed_data->SR_4) ? $speed_data->SR_4 : '-') : '-';
                            $sr_5 = $speed_data ? (isset($speed_data->SR_5) ? $speed_data->SR_5 : '-') : '-';
                            $sr_6 = $speed_data ? (isset($speed_data->SR_6) ? $speed_data->SR_6 : '-') : '-';
                            
                            // All distance furlongs (DF_2 through DF_6)
                            $df_2 = $speed_data ? (isset($speed_data->DF_2) ? $speed_data->DF_2 : '-') : '-';
                            $df_3 = $speed_data ? (isset($speed_data->DF_3) ? $speed_data->DF_3 : '-') : '-';
                            $df_4 = $speed_data ? (isset($speed_data->DF_4) ? $speed_data->DF_4 : '-') : '-';
                            $df_5 = $speed_data ? (isset($speed_data->DF_5) ? $speed_data->DF_5 : '-') : '-';
                            $df_6 = $speed_data ? (isset($speed_data->DF_6) ? $speed_data->DF_6 : '-') : '-';
                            
                            // All class ratings (CL_2 through CL_6)
                            $cr_2 = $speed_data ? (isset($speed_data->CL_2) ? $speed_data->CL_2 : '-') : '-';
                            $cr_3 = $speed_data ? (isset($speed_data->CL_3) ? $speed_data->CL_3 : '-') : '-';
                            $cr_4 = $speed_data ? (isset($speed_data->CL_4) ? $speed_data->CL_4 : '-') : '-';
                            $cr_5 = $speed_data ? (isset($speed_data->CL_5) ? $speed_data->CL_5 : '-') : '-';
                            $cr_6 = $speed_data ? (isset($speed_data->CL_6) ? $speed_data->CL_6 : '-') : '-';
                            
                            // Going for runs 2-6 – use dedicated going_1..going_6 columns from the same table
                            $going_2 = $speed_data ? (isset($speed_data->going_1) && $speed_data->going_1 !== '' ? $speed_data->going_1 : '-') : '-';
                            $going_3 = $speed_data ? (isset($speed_data->going_2) && $speed_data->going_2 !== '' ? $speed_data->going_2 : '-') : '-';
                            $going_4 = $speed_data ? (isset($speed_data->going_3) && $speed_data->going_3 !== '' ? $speed_data->going_3 : '-') : '-';
                            $going_5 = $speed_data ? (isset($speed_data->going_4) && $speed_data->going_4 !== '' ? $speed_data->going_4 : '-') : '-';
                            $going_6 = $speed_data ? (isset($speed_data->going_5) && $speed_data->going_5 !== '' ? $speed_data->going_5 : '-') : '-';
                            
                            // Additional data fields
                            $rft_percentage = $speed_data ? (isset($speed_data->TnrRTPPct14d) ? round($speed_data->TnrRTPPct14d, 1) . '%' : '-') : '-';
                            $comb = $speed_data ? (isset($speed_data->TnrJkyPlacePct) ? round($speed_data->TnrJkyPlacePct, 1) . '%' : '-') : '-';
                            $form = $speed_data ? (isset($speed_data->form_figures) ? substr($speed_data->form_figures, 0, 10) : '-') : '-';
                            $dslr = $speed_data ? (isset($speed_data->days_since_ran) ? $speed_data->days_since_ran : '-') : '-';
                            $lbf = $speed_data ? (isset($speed_data->beaten_favourite) ? $speed_data->beaten_favourite : '-') : '-';
                            // Course & Distance indicator (CD, C, D, or -)
                            $cd = '-';
                            if ($speed_data) {
                                $cd_candd = isset($speed_data->candd_winner) ? intval($speed_data->candd_winner) : 0;
                                $cd_course = isset($speed_data->course_winner) ? intval($speed_data->course_winner) : 0;
                                $cd_dist = isset($speed_data->distance_winner) ? intval($speed_data->distance_winner) : 0;
                                if ($cd_candd > 0) {
                                    $cd = 'CD';
                                } elseif ($cd_course > 0 && $cd_dist > 0) {
                                    $cd = 'C, D';
                                } elseif ($cd_course > 0) {
                                    $cd = 'C';
                                } elseif ($cd_dist > 0) {
                                    $cd = 'D';
                                }
                            }
                            $cls = $speed_data ? (isset($speed_data->class_diff) ? ($speed_data->class_diff > 0 ? '+' : '') . $speed_data->class_diff : '-') : '-';
                            $dist = $speed_data ? (isset($speed_data->distance_diff) ? ($speed_data->distance_diff > 0 ? '+' : '') . $speed_data->distance_diff : '-') : '-';
                            $or_diff = $speed_data ? (isset($speed_data->official_rating_diff) ? ($speed_data->official_rating_diff > 0 ? '+' : '') . $speed_data->official_rating_diff : '-') : '-';
                            $c_lb = $speed_data ? (isset($speed_data->c_lb) ? $speed_data->c_lb : '-') : '-';
                            $odds = $speed_data ? (isset($speed_data->forecast_price) ? $speed_data->forecast_price : $runner->forecast_price) : $runner->forecast_price;
                            $dec_odds = $speed_data ? (isset($speed_data->forecast_price_decimal) ? $speed_data->forecast_price_decimal : '-') : '-';

                            $maturity_edge = null;
                            if ($show_maturity_edge) {
                                $runner_foaling_date = '';
                                if ($speed_data && isset($speed_data->foaling_date)) {
                                    $runner_foaling_date = $speed_data->foaling_date;
                                } elseif (isset($runner->foaling_date)) {
                                    $runner_foaling_date = $runner->foaling_date;
                                }
                                $maturity_edge = bricks_calculate_maturity_edge($runner_foaling_date, $race->meeting_date);
                            }
                            // Resolve sire name from speed table; if missing, fall back to runners table fields
                            $sire_name = $speed_data ? bricks_extract_sire_name($speed_data) : '';
                            if ($sire_name === '') {
                                $sire_name = bricks_extract_sire_name($runner);
                            }
                            $sire_5y_pct = null; // now holds PRB%
                            $sire_5y_runs = 0;
                            $sire_5y_display = '-';
                            $sire_5y_title = 'Lineage 5Y PRB unavailable for this runner.';
                            $sire_5y_quality = bricks_lin5_quality(0);
                            if ($sire_name !== '') {
                                if (isset($sire_5y_lookup[$sire_name])) {
                                    $sire_5y_pct = isset($sire_5y_lookup[$sire_name]['prb_pct']) ? $sire_5y_lookup[$sire_name]['prb_pct'] : (isset($sire_5y_lookup[$sire_name]['win_pct']) ? $sire_5y_lookup[$sire_name]['win_pct'] : null);
                                    $sire_5y_runs = isset($sire_5y_lookup[$sire_name]['runs']) ? $sire_5y_lookup[$sire_name]['runs'] : 0;
                                    $ctx_used = isset($sire_5y_lookup[$sire_name]['context']) ? $sire_5y_lookup[$sire_name]['context'] : '';
                                    $raw_prb_used = isset($sire_5y_lookup[$sire_name]['raw_prb_pct']) ? $sire_5y_lookup[$sire_name]['raw_prb_pct'] : null;
                                    $baseline_prb_used = isset($sire_5y_lookup[$sire_name]['baseline_prb_pct']) ? $sire_5y_lookup[$sire_name]['baseline_prb_pct'] : null;
                                } else {
                                    $key_lower = strtolower($sire_name);
                                    $key_norm = bricks_normalize_sire_name_key($sire_name);
                                    if (isset($sire_5y_lookup[$key_lower])) {
                                        $sire_5y_pct = isset($sire_5y_lookup[$key_lower]['prb_pct']) ? $sire_5y_lookup[$key_lower]['prb_pct'] : (isset($sire_5y_lookup[$key_lower]['win_pct']) ? $sire_5y_lookup[$key_lower]['win_pct'] : null);
                                        $sire_5y_runs = isset($sire_5y_lookup[$key_lower]['runs']) ? $sire_5y_lookup[$key_lower]['runs'] : 0;
                                        $ctx_used = isset($sire_5y_lookup[$key_lower]['context']) ? $sire_5y_lookup[$key_lower]['context'] : '';
                                        $raw_prb_used = isset($sire_5y_lookup[$key_lower]['raw_prb_pct']) ? $sire_5y_lookup[$key_lower]['raw_prb_pct'] : null;
                                        $baseline_prb_used = isset($sire_5y_lookup[$key_lower]['baseline_prb_pct']) ? $sire_5y_lookup[$key_lower]['baseline_prb_pct'] : null;
                                    } elseif (isset($sire_5y_lookup[$key_norm])) {
                                        $sire_5y_pct = isset($sire_5y_lookup[$key_norm]['prb_pct']) ? $sire_5y_lookup[$key_norm]['prb_pct'] : (isset($sire_5y_lookup[$key_norm]['win_pct']) ? $sire_5y_lookup[$key_norm]['win_pct'] : null);
                                        $sire_5y_runs = isset($sire_5y_lookup[$key_norm]['runs']) ? $sire_5y_lookup[$key_norm]['runs'] : 0;
                                        $ctx_used = isset($sire_5y_lookup[$key_norm]['context']) ? $sire_5y_lookup[$key_norm]['context'] : '';
                                        $raw_prb_used = isset($sire_5y_lookup[$key_norm]['raw_prb_pct']) ? $sire_5y_lookup[$key_norm]['raw_prb_pct'] : null;
                                        $baseline_prb_used = isset($sire_5y_lookup[$key_norm]['baseline_prb_pct']) ? $sire_5y_lookup[$key_norm]['baseline_prb_pct'] : null;
                                    }
                                }
                                if ($sire_5y_pct === null) {
                                    $sire_5y_pct = 0.0;
                                    $sire_5y_runs = 0;
                                    $ctx_used = 'No data';
                                    $raw_prb_used = null;
                                    $baseline_prb_used = null;
                                }
                                $sire_5y_display = number_format($sire_5y_pct, 1) . '%';
                                $sire_5y_quality = bricks_lin5_quality($sire_5y_runs);
                                $sire_5y_title = 'Lineage 5Y (internal): adjusted sire PRB% (percentage of rivals beaten) in locked window (' . $sire_backtest_from . ' to ' . $sire_backtest_to . '). '
                                    . (!empty($ctx_used) ? 'Context: ' . $ctx_used . '. ' : '')
                                    . 'Runs: ' . number_format($sire_5y_runs) . '.'
                                    . (($raw_prb_used !== null) ? ' Raw PRB: ' . number_format(floatval($raw_prb_used), 1) . '%.' : '')
                                    . (($baseline_prb_used !== null) ? ' Baseline PRB: ' . number_format(floatval($baseline_prb_used), 1) . '%.' : '')
                                    . ' Signal quality: ' . $sire_5y_quality['label'] . '.';
                            }
                            
                            // Row styling
                            $row_bg = $index % 2 === 0 ? '#ffffff' : '#f9fafb';
                            $border_color = '#e5e7eb';
                            
                            // Check if any SR rating is >= 80 for row highlighting
                            $sr_highlight = false;
                            if ($speed_data) {
                                $sr_values = [$fsr, $sr_lto, $sr_2, $sr_3, $sr_4, $sr_5, $sr_6];
                                foreach ($sr_values as $sr) {
                                    if (floatval($sr) >= 80) {
                                        $sr_highlight = true;
                                        break;
                                    }
                                }
                            }
                            $background_color = $sr_highlight ? '#FFE7EF' : $row_bg;
                            if ($is_non_runner) {
                                $background_color = '#dbeafe';
                            }
                            // Add non-runner class
                    $non_runner_class = $is_non_runner ? ' non-runner' : '';
                    $points_runner_key = $runner_id_int > 0 ? (string) $runner_id_int : 'idx_' . $index;
                    $points_info = $race_points_by_key[$points_runner_key] ?? ($race_points_by_name[$runner->name] ?? null);
                    $points_score = $points_info ? floatval($points_info['model_score'] ?? 0) : 0;
                    $points_edge = $points_info ? floatval($points_info['edge_score'] ?? 0) : 0;
                    $is_win_candidate = !empty($points_info) && !empty($race_points_picks['winner']) && (($race_points_picks['winner']['runner_key'] ?? '') === ($points_info['runner_key'] ?? ''));
                    $is_place_candidate = false;
                    if (!empty($points_info) && !empty($race_points_picks['place'])) {
                        foreach ($race_points_picks['place'] as $place_pick) {
                            if (($place_pick['runner_key'] ?? '') === ($points_info['runner_key'] ?? '')) {
                                $is_place_candidate = true;
                                break;
                            }
                        }
                    }
                    $is_ew_simple = !empty($points_info) && !empty($race_points_ew_simple) && (($race_points_ew_simple['runner_key'] ?? '') === ($points_info['runner_key'] ?? ''));
                    $is_ew_edge = !empty($points_info) && !empty($race_points_ew_edge) && (($race_points_ew_edge['runner_key'] ?? '') === ($points_info['runner_key'] ?? ''));
                    $points_reasons_text = !empty($points_info['model_reasons']) ? implode(', ', $points_info['model_reasons']) : 'No standout factors';
                    ?>
                    <tr class="runner-row<?php echo $non_runner_class; ?>" 
                        style="background:<?php echo $background_color; ?>;border-bottom:1px solid <?php echo $border_color; ?>;transition:background-color 0.2s ease;"
                        data-runner-index="<?php echo esc_attr($index); ?>"
                        data-cloth-number="<?php echo esc_attr($runner->cloth_number ?: ''); ?>"
                        data-is-non-runner="<?php echo $is_non_runner ? '1' : '0'; ?>"
                        <?php if (!$is_national_hunt): ?>
                            data-stall-number="<?php echo esc_attr($stall_number); ?>"
                            data-draw-bias-pct="<?php echo esc_attr(str_replace('%', '', $draw_bias_pct)); ?>"
                        <?php endif; ?>
                        <?php if (!$is_tomorrow_race): ?>
                        data-win-pct="<?php echo esc_attr(str_replace('%', '', $win_percentage)); ?>"
                        data-place-pct="<?php echo esc_attr(str_replace('%', '', $place_percentage)); ?>"
                        <?php endif; ?>
                        data-fsr="<?php echo esc_attr($fsr); ?>"
                        data-fsrr="<?php echo esc_attr($fsrr); ?>"
                        data-rtf-pct="<?php echo esc_attr(str_replace('%', '', $rft_percentage)); ?>"
                        data-comb="<?php echo esc_attr(str_replace('%', '', $comb)); ?>"
                        data-form="<?php echo esc_attr($form); ?>"
                        data-dslr="<?php echo esc_attr($dslr); ?>"
                        data-weight="<?php echo esc_attr($weight_formatted); ?>"
                        data-cdg="<?php echo esc_attr($wins_display); ?>"
                        data-cd="<?php echo esc_attr($cd); ?>"
                        data-lbf="<?php echo esc_attr($lbf); ?>"
                        data-name="<?php echo esc_attr($runner->name ?: ''); ?>"
                        data-maturity-edge="<?php echo esc_attr($maturity_edge ? $maturity_edge['score'] : '-9999'); ?>"
                        data-sire5y="<?php echo esc_attr($sire_5y_pct !== null ? $sire_5y_pct : '-9999'); ?>"
                        data-cls="<?php echo esc_attr($cls); ?>"
                        data-or-diff="<?php echo esc_attr($or_diff); ?>"
                        data-model_points="<?php echo esc_attr($points_score); ?>"
                        data-jockey="<?php echo esc_attr($runner->jockey_name ?: ''); ?>"
                        data-sr-highlight="<?php echo $sr_highlight ? '1' : '0'; ?>"
                        data-runner-id="<?php echo esc_attr($runner->runner_id ?: ''); ?>">
                        
                        <!-- No. -->
                        <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="background:linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);color:white;width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;box-shadow:0 2px 8px rgba(59,130,246,0.3);">
                                    <?php echo esc_html($runner->cloth_number ?: ''); ?>
                                </div>
                                <?php if ($is_non_runner): ?>
                                    <span class="non-runner-badge">NR</span>
                                <?php endif; ?>
                            </div>
                        </td>
                                
                                      <?php if (!$is_national_hunt): ?>
        <!-- Stall -->
        <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
            <div style="font-weight:600;color:#374151;"><?php echo esc_html($stall_number); ?></div>
        </td>
        
        <!-- Db% -->
        <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
            <div class="<?php echo (floatval(str_replace('%', '', $draw_bias_pct)) >= 12) ? 'green-text' : ''; ?>" style="font-weight:600;"><?php echo esc_html($draw_bias_pct); ?></div>
        </td>
        <?php endif; ?>
                                
                                <?php if (!$is_tomorrow_race): ?>
                                <!-- Win% -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div class="<?php echo (floatval(str_replace('%', '', $win_percentage)) >= 60) ? 'green-text' : ''; ?>" style="font-weight:600;"><?php echo esc_html($win_percentage); ?></div>
                                </td>
                                
                                <!-- Pla% -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div class="<?php echo (floatval(str_replace('%', '', $place_percentage)) >= 60) ? 'green-text' : ''; ?>" style="font-weight:600;"><?php echo esc_html($place_percentage); ?></div>
                                </td>
                                <?php endif; ?>
                                
                                <!-- FSr -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div class="<?php echo (floatval($fsr) >= 80) ? 'green-text sr-highlight' : ''; ?>" style="font-weight:600;"><?php echo esc_html($fsr); ?></div>
                                </td>
                                
                                <!-- FSRr -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div style="font-weight:600;color:#059669;"><?php echo esc_html($fsrr); ?></div>
                                </td>

                                <!-- Points -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div title="<?php echo esc_attr('Model score 0-100. Reasons: ' . $points_reasons_text); ?>" style="font-weight:800;color:#1e3a8a;">
                                        <?php echo esc_html(number_format($points_score, 1)); ?>
                                    </div>
                                </td>

                                
                                <!-- Comb -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div class="<?php echo (floatval(str_replace('%', '', $comb)) >= 70) ? 'green-text' : ''; ?>" style="font-weight:600;"><?php echo esc_html($comb); ?></div>
                                </td>
                                
                                <!-- Form -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div style="font-weight:600;color:#059669;font-family:monospace;font-size:11px;"><?php echo esc_html($form); ?></div>
                                </td>
                                
                                <!-- dslr -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div class="<?php echo (floatval($dslr) >= 50) ? 'red-text' : ''; ?>" style="font-weight:600;"><?php echo esc_html($dslr); ?></div>
                                </td>
                                
                                <!-- Wgt -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div style="font-weight:600;color:#059669;font-family:monospace;"><?php echo esc_html($weight_formatted); ?></div>
                                </td>
                                
                                <!-- C/D/G -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div style="font-weight:600;color:#059669;"><?php echo esc_html($wins_display); ?></div>
                                </td>
                                
                              
                                
                                <!-- LBF -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div class="<?php echo (floatval($lbf) >= 1) ? 'green-text' : ''; ?>" style="font-weight:600;"><?php echo esc_html($lbf); ?></div>
                                </td>
                                
                                <!-- Name -->
                               <!-- Name -->
     <!-- Name (update this cell to show non-runner status) -->
                        <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                            <?php if ($runner->runner_id): ?>
                                <a href="<?php echo esc_url(bricks_horse_history_url($runner->runner_id)); ?>" 
                                   class="horse-name-link"
                                   title="View <?php echo esc_attr($runner->name); ?>'s complete racing history"
                                   style="font-weight:700;color:#111827;font-size:14px;text-decoration:none;display:block;">
                                    <?php echo esc_html($runner->name ?: 'N/A'); ?>
                                </a>
                            <?php else: ?>
                                <div style="font-weight:700;color:#111827;font-size:14px;"><?php echo esc_html($runner->name ?: 'N/A'); ?></div>
                            <?php endif; ?>
                            <?php
                            echo bricks_tracker_render_horse_widget(
                                $runner->name ?: '',
                                [
                                    'race_id' => $race_id,
                                    'race_date' => $race_date,
                                    'race_time' => isset($race->Time) ? $race->Time : '',
                                    'course' => isset($race->course) ? $race->course : ''
                                ],
                                [
                                    'show_latest_flag' => true,
                                    'wrapper_class' => 'tracker-race-row'
                                ]
                            );
                            ?>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px;">
                                <?php if ($is_win_candidate): ?>
                                    <span title="Top overall model score for this race." style="display:inline-block;padding:2px 6px;border-radius:999px;background:#dcfce7;color:#166534;font-size:10px;font-weight:700;">Win Candidate</span>
                                <?php endif; ?>
                                <?php if ($is_place_candidate): ?>
                                    <span title="Top 3 model-ranked runner for place profile." style="display:inline-block;padding:2px 6px;border-radius:999px;background:#dbeafe;color:#1e40af;font-size:10px;font-weight:700;">Place Candidate</span>
                                <?php endif; ?>
                                <?php if ($is_ew_simple): ?>
                                    <span title="Simple EW: odds 5/1+ and best model score among EW-eligible runners." style="display:inline-block;padding:2px 6px;border-radius:999px;background:#ffedd5;color:#9a3412;font-size:10px;font-weight:700;">EW Simple</span>
                                <?php endif; ?>
                                <?php if ($is_ew_edge): ?>
                                    <span title="Edge EW: odds 5/1+ and strong model-vs-market edge." style="display:inline-block;padding:2px 6px;border-radius:999px;background:#ede9fe;color:#5b21b6;font-size:10px;font-weight:700;">EW Edge</span>
                                <?php endif; ?>
                                <?php if ($points_edge > 0): ?>
                                    <span title="Model vs market edge score. Higher means bigger disagreement in your favour." style="display:inline-block;padding:2px 6px;border-radius:999px;background:#f3f4f6;color:#374151;font-size:10px;font-weight:700;">Edge <?php echo esc_html(number_format($points_edge, 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (isset($runner->bred) && $runner->bred): ?>
                                <div style="font-size:11px;color:#6b7280;margin-top:2px;"><?php echo esc_html($runner->bred); ?></div>
                            <?php endif; ?>
                            <?php if ($runner->gender): ?>
                                <div style="font-size:11px;color:#6b7280;text-transform:uppercase;margin-top:2px;"><?php echo esc_html($runner->gender); ?></div>
                            <?php endif; ?>
                            <?php if ($maturity_edge): ?>
                                <span
                                    class="maturity-edge-badge <?php echo esc_attr($maturity_edge['class']); ?>"
                                    title="<?php echo esc_attr($maturity_edge['tooltip']); ?>">
                                    <?php echo esc_html('Maturity Edge ' . ($maturity_edge['score'] > 0 ? '+' : '') . $maturity_edge['score'] . ' - ' . $maturity_edge['label']); ?>
                                </span>
                            <?php endif; ?>
                        </td>

                                <?php if ($show_maturity_edge): ?>
                                <!-- Maturity -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <?php if ($maturity_edge): ?>
                                        <span
                                            class="maturity-edge-badge <?php echo esc_attr($maturity_edge['class']); ?>"
                                            title="<?php echo esc_attr($maturity_edge['tooltip']); ?>"
                                            style="margin-top:0;">
                                            <?php echo esc_html(($maturity_edge['score'] > 0 ? '+' : '') . $maturity_edge['score']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>


                                <!-- Lineage 5Y -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div
                                        title="<?php echo esc_attr($sire_5y_title); ?>"
                                        class="<?php echo ($sire_5y_pct !== null && $sire_5y_pct >= 14) ? 'green-text' : ''; ?>"
                                        style="font-weight:600;">
                                        <span class="lin5-cell">
                                            <span><?php echo esc_html($sire_5y_display); ?></span>
                                            <span class="lin5-quality-badge <?php echo esc_attr($sire_5y_quality['class']); ?>">
                                                <?php echo esc_html($sire_5y_quality['label']); ?>
                                            </span>
                                        </span>
                                    </div>
                                </td>

                                
                                <!-- Continue with all remaining columns... -->
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div class="<?php echo (floatval($cls) < 0) ? 'green-text' : (floatval($cls) > 0 ? 'red-text' : ''); ?>" style="font-weight:600;"><?php echo esc_html($cls); ?></div>
                                </td>
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div class="<?php echo (floatval($or_diff) < 0) ? 'red-text' : (floatval($or_diff) >= 0 ? 'green-text' : ''); ?>" style="font-weight:600;"><?php echo esc_html($or_diff); ?></div>
                                </td>
                                <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
                                    <div style="font-weight:500;color:#374151;"><?php echo esc_html($runner->jockey_name ?: 'N/A'); ?></div>
                                    <?php if ($runner->jockey_claim): ?>
                                        <div style="font-size:11px;color:#059669;margin-top:2px;">(<?php echo esc_html($runner->jockey_claim); ?>lb)</div>
                                    <?php endif; ?>
                                </td>
                               <td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;">
    <div style="font-weight:500;color:#374151;"><?php echo esc_html($runner->trainer_name ?: 'N/A'); ?></div>
    <?php
    $trainer_name_key = isset($runner->trainer_name) ? trim((string) $runner->trainer_name) : '';
    $trainer_course = ($trainer_name_key !== '' && isset($trainer_course_lookup[$trainer_name_key])) ? $trainer_course_lookup[$trainer_name_key] : null;
    if ($trainer_course):
        $tc_pct = isset($trainer_course['win_pct']) ? floatval($trainer_course['win_pct']) : 0;
        $tc_runs = isset($trainer_course['runs']) ? intval($trainer_course['runs']) : 0;
        $tc_wins = isset($trainer_course['wins']) ? intval($trainer_course['wins']) : 0;
        $tc_rank = isset($trainer_course_ranks[$trainer_name_key]) ? intval($trainer_course_ranks[$trainer_name_key]) : 0;
        $tc_class = ($tc_pct >= 18 && $tc_runs >= 10) ? 'green-text' : (($tc_pct >= 12 && $tc_runs >= 6) ? '' : 'red-text');
        $tc_tooltip = 'Trainer course performance over the last 5 years up to the race date. Win % is wins divided by runs at this course. Displayed as wins/runs, with rank versus other trainers in this race.';
    ?>
        <div style="font-size:11px;margin-top:2px;">
            <span style="color:#6b7280;" title="<?php echo esc_attr($tc_tooltip); ?>">Course 5Y:</span>
            <span class="<?php echo esc_attr($tc_class); ?>" style="font-weight:700;" title="<?php echo esc_attr('Win percentage at this course in the 5-year lookback window.'); ?>">
                <?php echo esc_html(number_format($tc_pct, 1)); ?>%
            </span>
            <span style="color:#6b7280;" title="<?php echo esc_attr('Wins/Runs at this course in the same 5-year lookback window.'); ?>">(<?php echo esc_html($tc_wins . '/' . $tc_runs); ?>)</span>
            <?php if ($tc_rank > 0): ?>
                <span style="color:#6b7280;" title="<?php echo esc_attr('Rank by Course 5Y win percentage among trainers declared in this race.'); ?>">#<?php echo esc_html($tc_rank); ?> in race</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($rft_percentage && $rft_percentage !== '-'): ?>
        <div style="font-size:11px;margin-top:2px;">
            <span style="color:#6b7280;">RTF:</span>
            <span class="<?php echo (floatval(str_replace('%', '', $rft_percentage)) >= 40) ? 'green-text' : ''; ?>" style="font-weight:600;">
                <?php echo esc_html($rft_percentage); ?>
            </span>
        </div>
    <?php endif; ?>
</td>

                               
                                
                                
                                <!-- Speed Ratings with highlighting -->
                              <!-- Details Toggle Button -->
<td style="padding:15px 12px;border-bottom:1px solid <?php echo $border_color; ?>;text-align:center;">
    <button class="toggle-details-btn" data-runner-index="<?php echo $index; ?>" style="background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);color:white;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-weight:600;font-size:12px;transition:all 0.2s ease;">
        <span class="toggle-icon">▼</span> View
    </button>
</td>
</tr>

<!-- Hidden Details Row -->
<tr class="details-row details-row-<?php echo $index; ?>" style="display:none;background:rgba(59,130,246,0.05);">
    <td colspan="<?php echo $is_national_hunt ? '16' : ($show_maturity_edge ? '20' : '19'); ?>" style="padding:20px;">

        <div style="background:white;border-radius:8px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
            <h4 style="margin:0 0 16px 0;color:#1e293b;font-size:16px;font-weight:700;">📊 Speed Rating History - <?php echo esc_html($runner->name); ?></h4>
            
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
                <!-- Last Time Out -->
                <div style="background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);padding:12px;border-radius:6px;border-left:4px solid #3b82f6;">
                    <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;margin-bottom:6px;">Last Time Out</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">SR:</span>
                        <span class="<?php echo (floatval($sr_lto) >= 80) ? 'green-text' : ''; ?>" style="font-weight:700;font-size:14px;"><?php echo esc_html($sr_lto); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Class:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($cl_lto); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Distance:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($df_lto); ?>f</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:12px;color:#64748b;">Going:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($going_lto); ?></span>
                    </div>
                </div>
                
                <!-- 2nd Last Run -->
                <div style="background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);padding:12px;border-radius:6px;border-left:4px solid #10b981;">
                    <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;margin-bottom:6px;">2nd Last Run</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">SR:</span>
                        <span class="<?php echo (floatval($sr_2) >= 80) ? 'green-text' : ''; ?>" style="font-weight:700;font-size:14px;"><?php echo esc_html($sr_2); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Class:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($cr_2); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Distance:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($df_2); ?>f</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:12px;color:#64748b;">Going:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($going_2); ?></span>
                    </div>
                </div>
                
                <!-- 3rd Last Run -->
                <div style="background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);padding:12px;border-radius:6px;border-left:4px solid #f59e0b;">
                    <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;margin-bottom:6px;">3rd Last Run</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">SR:</span>
                        <span class="<?php echo (floatval($sr_3) >= 80) ? 'green-text' : ''; ?>" style="font-weight:700;font-size:14px;"><?php echo esc_html($sr_3); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Class:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($cr_3); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Distance:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($df_3); ?>f</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:12px;color:#64748b;">Going:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($going_3); ?></span>
                    </div>
                </div>
                
                <!-- 4th Last Run -->
                <div style="background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);padding:12px;border-radius:6px;border-left:4px solid #8b5cf6;">
                    <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;margin-bottom:6px;">4th Last Run</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">SR:</span>
                        <span class="<?php echo (floatval($sr_4) >= 80) ? 'green-text' : ''; ?>" style="font-weight:700;font-size:14px;"><?php echo esc_html($sr_4); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Class:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($cr_4); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Distance:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($df_4); ?>f</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:12px;color:#64748b;">Going:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($going_4); ?></span>
                    </div>
                </div>
                
                <!-- 5th Last Run -->
                <div style="background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);padding:12px;border-radius:6px;border-left:4px solid #ec4899;">
                    <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;margin-bottom:6px;">5th Last Run</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">SR:</span>
                        <span class="<?php echo (floatval($sr_5) >= 80) ? 'green-text' : ''; ?>" style="font-weight:700;font-size:14px;"><?php echo esc_html($sr_5); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Class:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($cr_5); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Distance:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($df_5); ?>f</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:12px;color:#64748b;">Going:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($going_5); ?></span>
                    </div>
                </div>
                
                <!-- 6th Last Run -->
                <div style="background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);padding:12px;border-radius:6px;border-left:4px solid #06b6d4;">
                    <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;margin-bottom:6px;">6th Last Run</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">SR:</span>
                        <span class="<?php echo (floatval($sr_6) >= 80) ? 'green-text' : ''; ?>" style="font-weight:700;font-size:14px;"><?php echo esc_html($sr_6); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Class:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($cr_6); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:12px;color:#64748b;">Distance:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($df_6); ?>f</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:12px;color:#64748b;">Going:</span>
                        <span style="font-weight:600;font-size:13px;color:#059669;"><?php echo esc_html($going_6); ?></span>
                    </div>
                </div>
            </div>
            <!-- Add these three new cards at the end of the grid, before the closing </div> -->

<!-- Official Rating Card -->
<div style="background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);padding:12px;border-radius:6px;border-left:4px solid #10b981;">
    <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;margin-bottom:6px;">Official Rating</div>
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:12px;color:#64748b;">OR:</span>
        <span style="font-weight:700;font-size:18px;color:#059669;"><?php echo esc_html($runner->official_rating ?: '-'); ?></span>
    </div>
</div>

<!-- Forecast Odds Card -->
<div style="background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);padding:12px;border-radius:6px;border-left:4px solid #f59e0b;">
    <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;margin-bottom:6px;">Forecast Odds</div>
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:12px;color:#64748b;">Odds:</span>
        <span style="font-weight:700;font-size:18px;color:#059669;"><?php echo esc_html($odds ?: 'N/A'); ?></span>
    </div>
</div>

<!-- Decimal Odds Card -->
<div style="background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);padding:12px;border-radius:6px;border-left:4px solid #8b5cf6;">
    <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;margin-bottom:6px;">Decimal Odds</div>
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:12px;color:#64748b;">Dec:</span>
        <span style="font-weight:700;font-size:18px;color:#059669;"><?php echo esc_html($dec_odds); ?></span>
    </div>
</div>

        </div>
    </td>
</tr>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

          <!-- Speed Rating Chart Section -->
        <?php
        $runner_count_chart = is_countable($runners) ? count($runners) : 0;
        $speed_chart_height_px = min(2400, max(500, ($runner_count_chart * 52) + 160));
        ?>
        <div style="background:white;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);margin-top:30px;padding:30px;">
            <h2 style="color:#111827;margin-bottom:25px;text-align:center;font-size:24px;font-weight:700;">📊 Fhorsite and Speed Rating Analysis</h2>
            <div class="speed-rating-chart-container" style="position:relative;height:<?php echo (int) $speed_chart_height_px; ?>px;min-height:500px;margin:30px 0;background:linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);border-radius:8px;padding:20px;">
                <div style="text-align:center;margin-bottom:10px;font-size:12px;color:#6b7280;font-style:italic;">💡 Click on legend items to filter the chart</div>
                <canvas id="speedRatingChart_<?php echo $race_id; ?>"></canvas>
                <div id="speedRatingNoData_<?php echo $race_id; ?>" style="display:none;text-align:center;padding:40px;color:#666;">
                    <h3 style="margin:0 0 8px 0;">No Speed Rating Data Available</h3>
                    <p style="margin:0;">The Speed Rating columns were not found for this race date.</p>
                </div>
            </div>

        </div>
        
        <!-- RTF Performance Charts Section -->
        <div style="background:white;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);margin-top:30px;padding:30px;">
            <h2 style="color:#111827;margin-bottom:25px;text-align:center;font-size:24px;font-weight:700;">📈 RTF Performance Analysis</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(500px,1fr));gap:30px;margin:20px 0;">
                <!-- Left Chart: RTF_Trainer -->
                <div style="background:#f8f9fa;border-radius:8px;padding:20px;">
                    <h3 style="color:#111827;margin-bottom:20px;text-align:center;font-size:18px;font-weight:600;">RTF_Trainer</h3>
                    <div style="position:relative;height:350px;">
                        <canvas id="rtfTrainerChart_<?php echo $race_id; ?>"></canvas>
                    </div>
                </div>
                <!-- Right Chart: RTF_Trainer/Jockey -->
                <div style="background:#f8f9fa;border-radius:8px;padding:20px;">
                    <h3 style="color:#111827;margin-bottom:20px;text-align:center;font-size:18px;font-weight:600;">RTF_Trainer/Jockey</h3>
                    <div style="position:relative;height:350px;">
                        <canvas id="rtfTrainerJockeyChart_<?php echo $race_id; ?>"></canvas>
                    </div>
                    <div style="display:flex;justify-content:center;gap:20px;margin-top:15px;">
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div style="width:16px;height:16px;background:#36a2eb;border-radius:3px;"></div>
                            <span style="font-size:12px;color:#374151;">RTF%</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div style="width:16px;height:16px;background:#4bc0c0;border-radius:3px;"></div>
                            <span style="font-size:12px;color:#374151;">JkyPlcPct14d</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Trainer Performance Statistics Chart Section -->
        <div style="background:white;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);margin-top:30px;padding:30px;">
            <h2 style="color:#111827;margin-bottom:25px;text-align:center;font-size:24px;font-weight:700;">📊 % Rivals Beaten By Trainer</h2>

           <div style="position:relative;height:<?php echo count($runners) > 10 ? '700px' : '500px'; ?>;margin:30px 0;background:linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);border-radius:8px;padding:20px;">

                <canvas id="trainerPerformanceChart_<?php echo $race_id; ?>"></canvas>
            </div>
            <div style="display:flex;justify-content:center;flex-wrap:wrap;gap:25px;margin-top:30px;padding:20px;background:#f8f9fa;border-radius:8px;">
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:24px;height:24px;background:#87CEEB;border-radius:6px;box-shadow:0 2px 4px rgba(0,0,0,0.1);"></div>
        <span style="font-weight:600;color:#374151;font-size:14px;">21 Days RBT%</span>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:24px;height:24px;background:#8A2BE2;border-radius:6px;box-shadow:0 2px 4px rgba(0,0,0,0.1);"></div>
        <span style="font-weight:600;color:#374151;font-size:14px;">42 Days RBT%</span>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:24px;height:24px;background:#32CD32;border-radius:6px;box-shadow:0 2px 4px rgba(0,0,0,0.1);"></div>
        <span style="font-weight:600;color:#374151;font-size:14px;">5 Years RBT%</span>
    </div>
</div>

        </div>

        <?php if (!$is_tomorrow_race): ?>
        <!-- Runner, Course, Distance, Class, Direction WINS Chart Section -->
        <div style="background:white;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);margin-top:30px;padding:30px;">
            <h2 style="color:#111827;margin-bottom:25px;text-align:center;font-size:24px;font-weight:700;">📊 Runner, Course, Distance, Class, Direction WINS Strike rate</h2>
            <div style="position:relative;height:<?php echo count($runners) > 10 ? '700px' : '500px'; ?>;margin:30px 0;background:linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);border-radius:8px;padding:20px;">
                <div style="text-align:center;margin-bottom:10px;font-size:12px;color:#6b7280;font-style:italic;">💡 Click on legend items to filter the chart</div>
                <canvas id="winsStrikeChart_<?php echo $race_id; ?>"></canvas>
            </div>
        </div>

        <!-- Runner, Course, Distance, Class, Direction PLACES Chart Section -->
        <div style="background:white;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);margin-top:30px;padding:30px;">
            <h2 style="color:#111827;margin-bottom:25px;text-align:center;font-size:24px;font-weight:700;">📊 Runner, Course, Distance, Class, Direction PLACES</h2>
            <div style="position:relative;height:<?php echo count($runners) > 10 ? '700px' : '500px'; ?>;margin:30px 0;background:linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);border-radius:8px;padding:20px;">
                <div style="text-align:center;margin-bottom:10px;font-size:12px;color:#6b7280;font-style:italic;">💡 Click on legend items to filter the chart</div>
                <canvas id="placesStrikeChart_<?php echo $race_id; ?>"></canvas>
            </div>
        </div>
        <?php endif; ?>

         <!-- Chart.js Library -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        
        <!-- Enhanced Chart Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    function initRaceDetailCharts_<?php echo $race_id; ?>() {
        const raceDetailDebug = <?php echo bricks_debug_enabled() ? 'true' : 'false'; ?>;
        const dbg = function() {
            if (raceDetailDebug && window.console && typeof console.log === 'function') {
                console.log.apply(console, arguments);
            }
        };
        if (typeof Chart === 'undefined') { 
            dbg('Chart.js not ready, retrying...');
            setTimeout(initRaceDetailCharts_<?php echo $race_id; ?>, 100); 
            return; 
        }
        dbg('Chart.js initialization started');
        if (raceDetailDebug && window.console && typeof console.groupCollapsed === 'function') {
            console.groupCollapsed('[RaceDetail Debug] <?php echo addslashes($race->course); ?> <?php echo addslashes($race->scheduled_time); ?> | RaceID=<?php echo $race_id; ?>');
            dbg('Race meta', {
                course: '<?php echo addslashes($race->course); ?>',
                time: '<?php echo addslashes($race->scheduled_time); ?>',
                title: '<?php echo addslashes($race->race_title); ?>',
                class: '<?php echo addslashes($race->class); ?>',
                age_range: '<?php echo addslashes($race->age_range); ?>',
                runnersCount: <?php echo $runners ? count($runners) : 0; ?>,
                speedRatingsCount: <?php echo $speed_ratings ? count($speed_ratings) : 0; ?>
            });
        }
        (function(){
            const runnerMapStatus = [
                <?php
                if ($runners && count($runners) > 0) {
                    $items = [];
                    foreach ($runners as $runner) {
                        $rn = $runner->name ?? '';
                        $has = isset($speed_ratings_lookup[$rn]) ? 'true' : 'false';
                        $rid = isset($runner->runner_id) ? (string)$runner->runner_id : '';
                        $items[] = "{name:" . json_encode($rn) . ", runnerId:" . json_encode($rid) . ", hasSpeedData: $has}";
                    }
                    echo implode(",", $items);
                }
                ?>
            ];
            const missing = runnerMapStatus.filter(r => !r.hasSpeedData).map(r => r.name);
            dbg('Runner mapping: total=', runnerMapStatus.length, 'missingSpeedData=', missing.length, missing);
        })();
        (function(){
            // Lin5 diagnostics
            const lin5Count = <?php echo isset($sire_5y_lookup) ? count($sire_5y_lookup) : 0; ?>;
            dbg('Lin5 lookup size (keys):', lin5Count);
            const lin5Map = [
                <?php
                if ($runners && count($runners) > 0) {
                    $items = [];
                    foreach ($runners as $runner) {
                        $horse = $runner->name ?? '';
                        $sireForRunner = '';
                        if (isset($speed_ratings_lookup[$horse])) {
                            $sireForRunner = bricks_extract_sire_name($speed_ratings_lookup[$horse]);
                        }
                        if ($sireForRunner === '') {
                            $sireForRunner = bricks_extract_sire_name($runner);
                        }
                        $hasExact = ($sireForRunner !== '' && isset($sire_5y_lookup[$sireForRunner])) ? 'true' : 'false';
                        $hasLower = ($sireForRunner !== '' && isset($sire_5y_lookup[strtolower($sireForRunner)])) ? 'true' : 'false';
                        $items[] = "{horse:" . json_encode($horse) . ", sire:" . json_encode($sireForRunner) . ", matchExact:$hasExact, matchLower:$hasLower}";
                    }
                    echo implode(",", $items);
                }
                ?>
            ];
            const noLin5 = lin5Map.filter(r => !(r.matchExact || r.matchLower));
            dbg('Lin5 per-runner mapping', lin5Map);
            dbg('Lin5 missing count:', noLin5.length, noLin5);
        })();
    
        // Prepare chart data for Speed Rating Chart
        // Prepare chart data for Speed Rating Chart
const chartData = {
    labels: <?php
    if ($runners && count($runners) > 0) {
        $labels = [];
        foreach ($runners as $runner) {
            $horse_name = $runner->name ?: 'Unknown';
            $cloth_number = $runner->cloth_number ?: '';
            if ($cloth_number) {
                $labels[] = $cloth_number . '. ' . $horse_name;
            } else {
                $labels[] = $horse_name;
            }
        }
        echo json_encode($labels);
    } else {
        echo json_encode(['No runners found']);
    }
    ?>,
    datasets: [{
        label: 'FSr (Fhorsite Rating)',
        data: <?php 
        if ($runners && count($runners) > 0) {
            $data = [];
            foreach ($runners as $runner) {
                $horse_name = $runner->name ?? '';
                $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                $fsr = $speed_data ? (isset($speed_data->fhorsite_rating) ? floatval($speed_data->fhorsite_rating) : 0) : 0;
                $data[] = $fsr;
            }
            echo json_encode($data);
        } else {
            echo json_encode([0]);
        }
        ?>,
        borderColor: '#8b5cf6',
        backgroundColor: 'rgba(139, 92, 246, 0.5)',
        borderWidth: 3
    }, {
        label: 'Last Time Out',
        data: <?php 
        if ($runners && count($runners) > 0) {
            $data = [];
            foreach ($runners as $runner) {
                $horse_name = $runner->name ?? '';
                $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                $sr_lto = $speed_data ? (isset($speed_data->SR_LTO) ? intval($speed_data->SR_LTO) : 0) : 0;
                $data[] = $sr_lto;
            }
            echo json_encode($data);
        } else {
            echo json_encode([0]);
        }
        ?>,
        borderColor: '#ff6384',
        backgroundColor: 'rgba(255, 99, 132, 0.5)',
        borderWidth: 2
    }, {
        label: 'Penultimate Run',
        data: <?php 
        if ($runners && count($runners) > 0) {
            $data = [];
            foreach ($runners as $runner) {
                $horse_name = $runner->name ?? '';
                $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                $sr_2 = $speed_data ? (isset($speed_data->SR_2) ? intval($speed_data->SR_2) : 0) : 0;
                $data[] = $sr_2;
            }
            echo json_encode($data);
        } else {
            echo json_encode([0]);
        }
        ?>,
        borderColor: '#36a2eb',
        backgroundColor: 'rgba(54, 162, 235, 0.5)',
        borderWidth: 2
    }, {
        label: '3rd Last Run',
        data: <?php 
        if ($runners && count($runners) > 0) {
            $data = [];
            foreach ($runners as $runner) {
                $horse_name = $runner->name ?? '';
                $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                $sr_3 = $speed_data ? (isset($speed_data->SR_3) ? intval($speed_data->SR_3) : 0) : 0;
                $data[] = $sr_3;
            }
            echo json_encode($data);
        } else {
            echo json_encode([0]);
        }
        ?>,
        borderColor: '#ffce56',
        backgroundColor: 'rgba(255, 206, 86, 0.5)',
        borderWidth: 2
    }, {
        label: '4th Last Run',
        data: <?php 
        if ($runners && count($runners) > 0) {
            $data = [];
            foreach ($runners as $runner) {
                $horse_name = $runner->name ?? '';
                $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                $sr_4 = $speed_data ? (isset($speed_data->SR_4) ? intval($speed_data->SR_4) : 0) : 0;
                $data[] = $sr_4;
            }
            echo json_encode($data);
        } else {
            echo json_encode([0]);
        }
        ?>,
        borderColor: '#4bc0c0',
        backgroundColor: 'rgba(75, 192, 192, 0.5)',
        borderWidth: 2
    }, {
        label: '5th Last Run',
        data: <?php 
        if ($runners && count($runners) > 0) {
            $data = [];
            foreach ($runners as $runner) {
                $horse_name = $runner->name ?? '';
                $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                $sr_5 = $speed_data ? (isset($speed_data->SR_5) ? intval($speed_data->SR_5) : 0) : 0;
                $data[] = $sr_5;
            }
            echo json_encode($data);
        } else {
            echo json_encode([0]);
        }
        ?>,
        borderColor: '#9966ff',
        backgroundColor: 'rgba(153, 102, 255, 0.5)',
        borderWidth: 2
    }, {
        label: '6th Last Run',
        data: <?php 
        if ($runners && count($runners) > 0) {
            $data = [];
            foreach ($runners as $runner) {
                $horse_name = $runner->name ?? '';
                $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                $sr_6 = $speed_data ? (isset($speed_data->SR_6) ? intval($speed_data->SR_6) : 0) : 0;
                $data[] = $sr_6;
            }
            echo json_encode($data);
        } else {
            echo json_encode([0]);
        }
        ?>,
        borderColor: '#ff9f40',
        backgroundColor: 'rgba(255, 159, 64, 0.5)',
        borderWidth: 2
    }]
};

        
        // Debug: Log chart data
        dbg('Chart data prepared', { labels: chartData.labels.length, datasets: chartData.datasets.length });
        
        // Check if we have any dataset rows and calculate maximum value
        let hasAnyData = false;
        let maxValue = 0;
        chartData.datasets.forEach((dataset, index) => {
            dataset.data.forEach((value) => {
                const numValue = parseFloat(value) || 0;
                hasAnyData = true;
                if (numValue > maxValue) {
                    maxValue = numValue;
                }
            });
        });
        
        if (!hasAnyData || chartData.labels.length === 0) {
            console.warn('No Speed Rating data rows found (all datasets empty)');
            const canvasEl = document.getElementById('speedRatingChart_<?php echo $race_id; ?>');
            const msgEl = document.getElementById('speedRatingNoData_<?php echo $race_id; ?>');
            if (canvasEl) { canvasEl.style.display = 'none'; }
            if (msgEl) { msgEl.style.display = 'block'; }
            return;
        }
        if (maxValue === 0) {
            console.warn('Speed Rating data present but all values are 0; rendering zero-height bars for visibility');
        }
        
        // Calculate dynamic max: round up to nearest 10 and add padding
        // If max is already over 100, ensure we show the full range
        let chartMax = 100;
        if (maxValue > 100) {
            // Round up to nearest 10, then add 10 for padding
            chartMax = Math.ceil((maxValue + 10) / 10) * 10;
            dbg('Speed Rating max value found: ' + maxValue + ', chart max set to: ' + chartMax);
        }
        
        // Calculate appropriate step size based on max value
        let stepSize = 20;
        if (chartMax > 100) {
            if (chartMax <= 110) {
                stepSize = 10;
            } else if (chartMax <= 120) {
                stepSize = 20;
            } else if (chartMax <= 150) {
                stepSize = 25;
            } else {
                stepSize = 30;
            }
        }
        
        // Chart configuration
        const runnerLabelCount = chartData.labels.length;
        const yTickFontSize = runnerLabelCount > 24 ? 10 : (runnerLabelCount > 16 ? 11 : 13);
        const config = {
            type: 'bar',
            data: chartData,
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        max: chartMax,
                        grid: {
                            color: 'rgba(0,0,0,0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: stepSize,
                            font: { size: 12, weight: '600' },
                            color: '#666'
                        }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            autoSkip: false,
                            includeBounds: true,
                            font: { size: yTickFontSize, weight: '600' },
                            color: '#333'
                        }
                    }
                },
                elements: {
                    bar: {
                        borderWidth: 0,
                        borderRadius: 8,
                        borderSkipped: false,
                        categoryPercentage: runnerLabelCount > 20 ? 0.88 : 0.95,
                        barPercentage: runnerLabelCount > 20 ? 0.82 : 0.95
                    }
                },
                layout: { padding: { top: 20, bottom: 20 } },
                interaction: { intersect: false, mode: 'index', axis: 'y' },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 25,
                            font: { size: 14, weight: '600' }
                        }
                    },
                    title: {
                        display: false
                    },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        position: 'nearest',
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#ffffff',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13, weight: '500' },
                        padding: 12,
                        callbacks: {
                            title: function(context) {
                                return 'Horse: ' + context[0].label;
                            },
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.x + ' (Rating)';
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        };
        
        // Create the main speed rating chart
        const ctx = document.getElementById('speedRatingChart_<?php echo $race_id; ?>');
        if (ctx) {
            dbg('Creating Speed Rating chart...');
            const chart = new Chart(ctx, config);
            chart.resize();
            // Store chart instance and original data globally
            if (!window.raceDetailCharts_<?php echo $race_id; ?>) {
                window.raceDetailCharts_<?php echo $race_id; ?> = {};
                window.raceDetailChartData_<?php echo $race_id; ?> = {};
            }
            window.raceDetailCharts_<?php echo $race_id; ?>.speedRatingChart = chart;
            window.raceDetailChartData_<?php echo $race_id; ?>.speedRatingChart = JSON.parse(JSON.stringify(chartData));
            dbg('Speed Rating chart created successfully');
            dbg('Speed Rating counts', {labels: chartData.labels.length, datasets: chartData.datasets.length});
        } else {
            console.error('Speed Rating chart canvas not found');
        }
        
        // RTF Trainer Chart
      // RTF Trainer Chart
const rtfTrainerCtx = document.getElementById('rtfTrainerChart_<?php echo $race_id; ?>');
if (rtfTrainerCtx) {
    const rtfTrainerLabels = <?php 
    if ($runners && count($runners) > 0) {
        $labels = [];
        foreach ($runners as $runner) {
            $labels[] = $runner->trainer_name ? (string)$runner->trainer_name : 'Unknown';
        }
        echo json_encode($labels);
    } else {
        echo '[]';
    }
    ?>;

            
            const rtfTrainerValues = <?php
            if ($runners && count($runners) > 0) {
                $values = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data) {
                        if (isset($speed_data->TnrRTPPct14d) && $speed_data->TnrRTPPct14d !== '') {
                            $val = round(floatval($speed_data->TnrRTPPct14d), 2);
                        } elseif (isset($speed_data->TnrWinPct14d) && $speed_data->TnrWinPct14d !== '') {
                            $val = round(floatval($speed_data->TnrWinPct14d), 2);
                        }
                    }
                    $values[] = $val;
                }
                echo json_encode($values);
            } else {
                echo '[]';
            }
            ?>;
            
            const rtfTrainerChart = new Chart(rtfTrainerCtx, {
                type: 'bar',
                data: {
                    labels: rtfTrainerLabels,
                    datasets: [{
                        label: 'RTF%',
                        data: rtfTrainerValues,
                        backgroundColor: '#36a2eb',
                        borderColor: '#36a2eb',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, title: { display: false } },
                    scales: {
                        x: { beginAtZero: true, suggestedMax: 100, grid: { color: 'rgba(0,0,0,0.1)' }, ticks: { stepSize: 10 } },
                        y: { 
                            grid: { color: 'rgba(0,0,0,0.1)' },
                            ticks: {
                                stepSize: 1,
                                maxTicksLimit: 1000,
                                autoSkip: false
                            }
                        }
                    },
                    elements: { bar: { borderWidth: 1 } }
                }
            });
            // Store chart instance and original data
            if (!window.raceDetailCharts_<?php echo $race_id; ?>) {
                window.raceDetailCharts_<?php echo $race_id; ?> = {};
                window.raceDetailChartData_<?php echo $race_id; ?> = {};
            }
            window.raceDetailCharts_<?php echo $race_id; ?>.rtfTrainerChart = rtfTrainerChart;
            window.raceDetailChartData_<?php echo $race_id; ?>.rtfTrainerChart = {
                labels: rtfTrainerLabels,
                values: rtfTrainerValues
            };
            dbg('RTF_Trainer chart', {labelsCount: rtfTrainerLabels.length, valuesCount: rtfTrainerValues.length});
        }
        
        // RTF Trainer/Jockey Chart
        // RTF Trainer/Jockey Chart
// RTF Trainer/Jockey Chart
// RTF Trainer/Jockey Chart
const rtfTrainerJockeyCtx = document.getElementById('rtfTrainerJockeyChart_<?php echo $race_id; ?>');
if (rtfTrainerJockeyCtx) {
    const trainerJockeyLabels = <?php 
    if ($runners && count($runners) > 0) {
        $labels = [];
        foreach ($runners as $runner) {
            $horse_name = $runner->name ? (string)$runner->name : 'Unknown';
            $cloth_number = $runner->cloth_number ?: '';
            if ($cloth_number) {
                $labels[] = $cloth_number . '. ' . $horse_name;
            } else {
                $labels[] = $horse_name;
            }
        }
        echo json_encode($labels);
    } else {
        echo '[]';
    }
    ?>;
    
    const rtfValues = <?php
    if ($runners && count($runners) > 0) {
        $values = [];
        foreach ($runners as $runner) {
            $horse_name = $runner->name ?? '';
            $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
            $val = 0;
            if ($speed_data) {
                if (isset($speed_data->TnrRTPPct14d) && $speed_data->TnrRTPPct14d !== '') {
                    $val = round(floatval($speed_data->TnrRTPPct14d), 2);
                } elseif (isset($speed_data->TnrWinPct14d) && $speed_data->TnrWinPct14d !== '') {
                    $val = round(floatval($speed_data->TnrWinPct14d), 2);
                }
            }
            $values[] = $val;
        }
        echo json_encode($values);
    } else {
        echo '[]';
    }
    ?>;
    
    const jkyValues = <?php
    if ($runners && count($runners) > 0) {
        $values = [];
        foreach ($runners as $runner) {
            $horse_name = $runner->name ?? '';
            $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
            $val = 0;
            if ($speed_data) {
                if (isset($speed_data->JkyPlcPct14d) && $speed_data->JkyPlcPct14d !== '') {
                    $val = round(floatval($speed_data->JkyPlcPct14d), 2);
                } elseif (isset($speed_data->JkyWinPct14d) && $speed_data->JkyWinPct14d !== '') {
                    $val = round(floatval($speed_data->JkyWinPct14d), 2);
                }
            }
            $values[] = $val;
        }
        echo json_encode($values);
    } else {
        echo '[]';
    }
    ?>;
    
    const jockeyNames = <?php 
    if ($runners && count($runners) > 0) {
        $jockeys = [];
        foreach ($runners as $runner) {
            $jockeys[] = $runner->jockey_name ? (string)$runner->jockey_name : 'Unknown';
        }
        echo json_encode($jockeys);
    } else {
        echo '[]';
    }
    ?>;
    
    const trainerNamesForTooltip = <?php 
    if ($runners && count($runners) > 0) {
        $trainers = [];
        foreach ($runners as $runner) {
            $trainers[] = $runner->trainer_name ? (string)$runner->trainer_name : 'Unknown';
        }
        echo json_encode($trainers);
    } else {
        echo '[]';
    }
    ?>;
    
    const rtfTrainerJockeyChart = new Chart(rtfTrainerJockeyCtx, {
        type: 'bar',
        data: {
            labels: trainerJockeyLabels,
            datasets: [{
                label: 'RTF%',
                data: rtfValues,
                backgroundColor: '#36a2eb',
                borderColor: '#36a2eb',
                borderWidth: 1,
                stack: 'combined'
            }, {
                label: 'JkyPlcPct14d',
                data: jkyValues,
                backgroundColor: '#4bc0c0',
                borderColor: '#4bc0c0',
                borderWidth: 1,
                stack: 'combined'
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false }, 
                title: { display: false },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            const index = context[0].dataIndex;
                            return 'Horse: ' + trainerJockeyLabels[index];
                        },
                        label: function(context) {
                            const index = context.dataIndex;
                            const datasetIndex = context.datasetIndex;
                            
                            if (datasetIndex === 0) {
                                return 'Trainer: ' + trainerNamesForTooltip[index] + ' - RTF%: ' + context.parsed.x + '%';
                            } else {
                                return 'Jockey: ' + jockeyNames[index] + ' - JkyPlcPct14d: ' + context.parsed.x + '%';
                            }
                        }
                    }
                }
            },
            scales: {
                x: { 
                    beginAtZero: true, 
                    suggestedMax: 140, 
                    stacked: true, 
                    grid: { color: 'rgba(0,0,0,0.1)' }, 
                    ticks: { stepSize: 20 } 
                },
                y: { 
                    stacked: true, 
                    grid: { color: 'rgba(0,0,0,0.1)' },
                    ticks: {
                        stepSize: 1,
                        maxTicksLimit: 1000,
                        autoSkip: false
                    }
                }
            },
            elements: { bar: { borderWidth: 1 } }
        }
    });
    // Store chart instance and original data
    if (!window.raceDetailCharts_<?php echo $race_id; ?>) {
        window.raceDetailCharts_<?php echo $race_id; ?> = {};
        window.raceDetailChartData_<?php echo $race_id; ?> = {};
    }
    window.raceDetailCharts_<?php echo $race_id; ?>.rtfTrainerJockeyChart = rtfTrainerJockeyChart;
    window.raceDetailChartData_<?php echo $race_id; ?>.rtfTrainerJockeyChart = {
        labels: trainerJockeyLabels,
        rtfValues: rtfValues,
        jkyValues: jkyValues
    };
    dbg('RTF_Trainer/Jockey chart', {labelsCount: trainerJockeyLabels.length, rtfValuesCount: rtfValues.length, jkyValuesCount: jkyValues.length});
}

        
        // Trainer Performance Chart
       // Trainer Performance Chart
const trainerPerformanceCtx = document.getElementById('trainerPerformanceChart_<?php echo $race_id; ?>');
if (trainerPerformanceCtx) {
    const trainerNames = <?php 
    if ($runners && count($runners) > 0) {
        // Build multi-line labels: [['Trainer Name', '1. Horse A, 5. Horse B'], ...]
        $trainer_horses = [];
        foreach ($runners as $runner) {
            if ($runner->trainer_name) {
                $cloth = $runner->cloth_number ?: '';
                $horse = $runner->name ?: 'Unknown';
                $horse_label = $cloth ? $cloth . '. ' . $horse : $horse;
                if (!isset($trainer_horses[$runner->trainer_name])) {
                    $trainer_horses[$runner->trainer_name] = [];
                }
                $trainer_horses[$runner->trainer_name][] = $horse_label;
            }
        }
        $labels = [];
        foreach ($trainer_horses as $trainer => $horses) {
            // Each label is an array for Chart.js multi-line: line 1 = trainer, line 2 = horses
            $labels[] = [$trainer, implode(', ', $horses)];
        }
        echo json_encode($labels);
    } else {
        echo '[]';
    }
    ?>;
    
    // Get actual trainer performance data from speed ratings (using actual DB columns)
    const trainerData = <?php
    if ($runners && count($runners) > 0) {
        $trainer_stats = [];
        $unique_trainers = [];
        
        // Collect unique trainers
        foreach ($runners as $runner) {
            if ($runner->trainer_name && !in_array($runner->trainer_name, $unique_trainers)) {
                $unique_trainers[] = $runner->trainer_name;
            }
        }
        
        // Get stats for each trainer from speed ratings
        foreach ($unique_trainers as $trainer) {
            $horse_name = '';
            // Find a horse with this trainer
            foreach ($runners as $runner) {
                if ($runner->trainer_name === $trainer) {
                    $horse_name = $runner->name;
                    break;
                }
            }
            
            // Get speed data for this horse
            $speed_data = null;
            if (isset($speed_ratings_lookup[$horse_name])) {
                $speed_data = $speed_ratings_lookup[$horse_name];
            }
            
            // Extract trainer PRB% metrics from actual DB columns
            $prb_21d = 0;
            $prb_42d = 0;
            $prb_5y = 0;
            
            if ($speed_data) {
                // 21 Days Rivals Beaten by Trainer %
                if (isset($speed_data->trn_rc_21D_prb) && $speed_data->trn_rc_21D_prb !== '' && $speed_data->trn_rc_21D_prb !== null) {
                    $prb_21d = round(floatval($speed_data->trn_rc_21D_prb), 1);
                }
                
                // 42 Days Rivals Beaten by Trainer %
                if (isset($speed_data->trn_rc_42D_prb) && $speed_data->trn_rc_42D_prb !== '' && $speed_data->trn_rc_42D_prb !== null) {
                    $prb_42d = round(floatval($speed_data->trn_rc_42D_prb), 1);
                }
                
                // 5 Years Rivals Beaten by Trainer %
                if (isset($speed_data->trn_rc_5Y_prb) && $speed_data->trn_rc_5Y_prb !== '' && $speed_data->trn_rc_5Y_prb !== null) {
                    $prb_5y = round(floatval($speed_data->trn_rc_5Y_prb), 1);
                }
            }
            
            $trainer_stats[] = [
                'trainer' => $trainer,
                'prb_21d' => $prb_21d,
                'prb_42d' => $prb_42d,
                'prb_5y' => $prb_5y
            ];
        }
        
        echo json_encode($trainer_stats);
    } else {
        echo '[]';
    }
    ?>;
    
    // Extract data arrays
    const trainer21D = trainerData.map(t => t.prb_21d);
    const trainer42D = trainerData.map(t => t.prb_42d);
    const trainer5Y = trainerData.map(t => t.prb_5y);
    
    // Dynamic y-axis scaling based on actual trainer PRB values
    const allTrainerValues = [...trainer21D, ...trainer42D, ...trainer5Y]
        .map(v => parseFloat(v) || 0);
    const trainerMaxValue = allTrainerValues.length ? Math.max(...allTrainerValues) : 0;
    const trainerYAxisMax = trainerMaxValue > 0 ? Math.ceil((trainerMaxValue + 5) / 10) * 10 : 10;
    const trainerYAxisStep = trainerYAxisMax <= 50 ? 5 : 10;

          const trainerPerformanceChart = new Chart(trainerPerformanceCtx, {
    type: 'bar',
    data: {
        labels: trainerNames,
        datasets: [{
            label: '21 Days RBT%',
            data: trainer21D,
            backgroundColor: '#87CEEB',
            borderColor: '#87CEEB',
            borderWidth: 1
        }, {
            label: '42 Days RBT%',
            data: trainer42D,
            backgroundColor: '#8A2BE2',
            borderColor: '#8A2BE2',
            borderWidth: 1
        }, {
            label: '5 Years RBT%',
            data: trainer5Y,
            backgroundColor: '#32CD32',
            borderColor: '#32CD32',
            borderWidth: 1
        }]
    },

                options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { 
        legend: { display: false }, 
        title: { display: false } 
    },
    scales: {
        x: { 
            grid: { color: 'rgba(0,0,0,0.1)' }, 
            ticks: { 
                font: { size: 11, weight: '600' }, 
                color: '#333',
                maxRotation: 45,
                minRotation: 0,
                autoSkip: false,
                callback: function(value, index) {
                    // Multi-line labels: trainerNames[index] is an array ['Trainer', 'Cloth. Horse']
                    const label = trainerNames[index];
                    if (Array.isArray(label)) {
                        return label; // Chart.js renders arrays as multi-line
                    }
                    return label;
                }
            } 
        },
        y: { 
            beginAtZero: true, 
            max: trainerYAxisMax, 
            grid: { color: 'rgba(0,0,0,0.1)' }, 
            ticks: { 
                stepSize: trainerYAxisStep, 
                font: { size: 12, weight: '600' }, 
                color: '#666' 
            } 
        }
    },
    elements: { 
        bar: { 
            borderWidth: 1, 
            borderRadius: 4, 
            borderSkipped: false 
        } 
    },
    layout: { 
        padding: { top: 20, bottom: 30 } 
    },
    animation: { 
        duration: 1000, 
        easing: 'easeInOutQuart' 
    }
}

            });
            // Store chart instance and original data
            if (!window.raceDetailCharts_<?php echo $race_id; ?>) {
                window.raceDetailCharts_<?php echo $race_id; ?> = {};
                window.raceDetailChartData_<?php echo $race_id; ?> = {};
            }
            window.raceDetailCharts_<?php echo $race_id; ?>.trainerPerformanceChart = trainerPerformanceChart;
            window.raceDetailChartData_<?php echo $race_id; ?>.trainerPerformanceChart = {
                labels: trainerNames,
                trainer21D: trainer21D,
                trainer42D: trainer42D,
                trainer5Y: trainer5Y
            };
            dbg('% Rivals Beaten chart', {
                labelsCount: trainerNames.length,
                d21: trainer21D.length,
                d42: trainer42D.length,
                d5y: trainer5Y.length
            });
        }

        <?php if (!$is_tomorrow_race): ?>
        // WINS Strike Chart
        const winsStrikeCtx = document.getElementById('winsStrikeChart_<?php echo $race_id; ?>');
        if (winsStrikeCtx) {
            const runnerLabels = <?php 
            if ($runners && count($runners) > 0) {
                $labels = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?: 'Unknown';
                    $cloth_number = $runner->cloth_number ?: '';
                    if ($cloth_number) {
                        $labels[] = $cloth_number . '. ' . $horse_name;
                    } else {
                        $labels[] = $horse_name;
                    }
                }
                echo json_encode($labels);
            } else {
                echo '[]';
            }
            ?>;
            
            const prevRunnerWinStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->prev_runner_win_strike) && $speed_data->prev_runner_win_strike !== '') {
                        // Values are already stored as percentages in the DB
                        $val = floatval($speed_data->prev_runner_win_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            const goingPrevWinStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->going_prev_win_strike) && $speed_data->going_prev_win_strike !== '') {
                        $val = floatval($speed_data->going_prev_win_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            const coursePrevWinStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->course_prev_win_strike) && $speed_data->course_prev_win_strike !== '') {
                        $val = floatval($speed_data->course_prev_win_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            const distancePrevWinStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->distance_prev_win_strike) && $speed_data->distance_prev_win_strike !== '') {
                        $val = floatval($speed_data->distance_prev_win_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            const classPrevWinStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->class_prev_win_strike) && $speed_data->class_prev_win_strike !== '') {
                        $val = floatval($speed_data->class_prev_win_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            const directionPrevWinStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->direction_prev_win_strike) && $speed_data->direction_prev_win_strike !== '') {
                        $val = floatval($speed_data->direction_prev_win_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            // Calculate max value from all datasets for dynamic Y-axis
            const allWinsData = [...prevRunnerWinStrike, ...goingPrevWinStrike, ...coursePrevWinStrike, 
                                ...distancePrevWinStrike, ...classPrevWinStrike, ...directionPrevWinStrike];
            const maxWinsValue = Math.max(...allWinsData.filter(v => !isNaN(v) && v > 0), 0);
            // Add 20% padding and round up to nearest 5
            const dynamicMaxWins = maxWinsValue > 0 ? Math.ceil(maxWinsValue * 1.2 / 5) * 5 : 25;
            // Calculate stepSize based on max value (aim for 4-6 steps)
            const stepSizeWins = dynamicMaxWins <= 30 ? 5 : dynamicMaxWins <= 60 ? 10 : dynamicMaxWins <= 100 ? 20 : 25;
            
            const winsStrikeChart = new Chart(winsStrikeCtx, {
                type: 'bar',
                data: {
                    labels: runnerLabels,
                    datasets: [{
                        label: 'Runner',
                        data: prevRunnerWinStrike,
                        backgroundColor: '#87CEEB',
                        borderColor: '#87CEEB',
                        borderWidth: 1
                    }, {
                        label: 'going',
                        data: goingPrevWinStrike,
                        backgroundColor: '#4B0082',
                        borderColor: '#4B0082',
                        borderWidth: 1
                    }, {
                        label: 'course',
                        data: coursePrevWinStrike,
                        backgroundColor: '#32CD32',
                        borderColor: '#32CD32',
                        borderWidth: 1
                    }, {
                        label: 'distance',
                        data: distancePrevWinStrike,
                        backgroundColor: '#FF8C00',
                        borderColor: '#FF8C00',
                        borderWidth: 1
                    }, {
                        label: 'class',
                        data: classPrevWinStrike,
                        backgroundColor: '#708090',
                        borderColor: '#708090',
                        borderWidth: 1
                    }, {
                        label: 'direction',
                        data: directionPrevWinStrike,
                        backgroundColor: '#9370DB',
                        borderColor: '#9370DB',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { 
                            display: true,
                            position: 'top',
                            onClick: function(e, legendItem) {
                                const index = legendItem.datasetIndex;
                                const chart = this.chart;
                                const meta = chart.getDatasetMeta(index);
                                meta.hidden = meta.hidden === null ? !chart.data.datasets[index].hidden : null;
                                chart.update();
                            },
                            labels: {
                                usePointStyle: true,
                                padding: 25,
                                font: { size: 14, weight: '600' }
                            }
                        }, 
                        title: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = typeof context.parsed?.y !== 'undefined'
                                        ? context.parsed.y
                                        : (typeof context.parsed === 'number' ? context.parsed : 0);
                                    return context.dataset.label + ': ' + value.toFixed(1) + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            grid: { display: false },
                            ticks: { 
                                font: { size: 12, weight: '600' }, 
                                color: '#333',
                                maxRotation: 45,
                                minRotation: 45
                            } 
                        },
                        y: { 
                            beginAtZero: true, 
                            max: dynamicMaxWins,
                            grid: { color: 'rgba(0,0,0,0.1)' }, 
                            ticks: { 
                                stepSize: stepSizeWins, 
                                font: { size: 12, weight: '600' }, 
                                color: '#666' 
                            } 
                        }
                    },
                    elements: { 
                        bar: { 
                            borderWidth: 1, 
                            borderRadius: 4, 
                            borderSkipped: false 
                        } 
                    },
                    layout: { 
                        padding: { top: 20, bottom: 20 } 
                    },
                    animation: { 
                        duration: 1000, 
                        easing: 'easeInOutQuart' 
                    }
                }
            });
            // Store chart instance and original data
            if (!window.raceDetailCharts_<?php echo $race_id; ?>) {
                window.raceDetailCharts_<?php echo $race_id; ?> = {};
                window.raceDetailChartData_<?php echo $race_id; ?> = {};
            }
            window.raceDetailCharts_<?php echo $race_id; ?>.winsStrikeChart = winsStrikeChart;
            window.raceDetailChartData_<?php echo $race_id; ?>.winsStrikeChart = {
                labels: runnerLabels,
                prevRunnerWinStrike: prevRunnerWinStrike,
                goingPrevWinStrike: goingPrevWinStrike,
                coursePrevWinStrike: coursePrevWinStrike,
                distancePrevWinStrike: distancePrevWinStrike,
                classPrevWinStrike: classPrevWinStrike,
                directionPrevWinStrike: directionPrevWinStrike
            };
        }
        <?php endif; ?>

        <?php if (!$is_tomorrow_race): ?>
        // PLACES Strike Chart
        const placesStrikeCtx = document.getElementById('placesStrikeChart_<?php echo $race_id; ?>');
        if (placesStrikeCtx) {
            const runnerLabelsPlaces = <?php 
            if ($runners && count($runners) > 0) {
                $labels = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?: 'Unknown';
                    $cloth_number = $runner->cloth_number ?: '';
                    if ($cloth_number) {
                        $labels[] = $cloth_number . '. ' . $horse_name;
                    } else {
                        $labels[] = $horse_name;
                    }
                }
                echo json_encode($labels);
            } else {
                echo '[]';
            }
            ?>;
            
            const prevRunnerPlaceStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->prev_runner_place_strike) && $speed_data->prev_runner_place_strike !== '') {
                        // Values are already percentages in the DB
                        $val = floatval($speed_data->prev_runner_place_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            const goingPrevPlaceStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->going_prev_place_strike) && $speed_data->going_prev_place_strike !== '') {
                        $val = floatval($speed_data->going_prev_place_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            const coursePrevPlaceStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->course_prev_place_strike) && $speed_data->course_prev_place_strike !== '') {
                        $val = floatval($speed_data->course_prev_place_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            const distancePrevPlaceStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->distance_prev_place_strike) && $speed_data->distance_prev_place_strike !== '') {
                        $val = floatval($speed_data->distance_prev_place_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            const classPrevPlaceStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->class_prev_place_strike) && $speed_data->class_prev_place_strike !== '') {
                        $val = floatval($speed_data->class_prev_place_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            const directionPrevPlaceStrike = <?php
            if ($runners && count($runners) > 0) {
                $data = [];
                foreach ($runners as $runner) {
                    $horse_name = $runner->name ?? '';
                    $speed_data = $speed_ratings_lookup[$horse_name] ?? null;
                    $val = 0;
                    if ($speed_data && isset($speed_data->direction_prev_place_strike) && $speed_data->direction_prev_place_strike !== '') {
                        $val = floatval($speed_data->direction_prev_place_strike);
                    }
                    $data[] = $val;
                }
                echo json_encode($data);
            } else {
                echo '[]';
            }
            ?>;
            
            // Calculate max value from all datasets for dynamic Y-axis
            const allPlacesData = [...prevRunnerPlaceStrike, ...goingPrevPlaceStrike, ...coursePrevPlaceStrike, 
                                   ...distancePrevPlaceStrike, ...classPrevPlaceStrike, ...directionPrevPlaceStrike];
            const maxPlacesValue = Math.max(...allPlacesData.filter(v => !isNaN(v) && v > 0), 0);
            // Add 20% padding and round up to nearest 5
            const dynamicMaxPlaces = maxPlacesValue > 0 ? Math.ceil(maxPlacesValue * 1.2 / 5) * 5 : 25;
            // Calculate stepSize based on max value (aim for 4-6 steps)
            const stepSizePlaces = dynamicMaxPlaces <= 30 ? 5 : dynamicMaxPlaces <= 60 ? 10 : dynamicMaxPlaces <= 100 ? 20 : 25;
            
            const placesStrikeChart = new Chart(placesStrikeCtx, {
                type: 'bar',
                data: {
                    labels: runnerLabelsPlaces,
                    datasets: [{
                        label: 'Runner',
                        data: prevRunnerPlaceStrike,
                        backgroundColor: '#87CEEB',
                        borderColor: '#87CEEB',
                        borderWidth: 1
                    }, {
                        label: 'going',
                        data: goingPrevPlaceStrike,
                        backgroundColor: '#4B0082',
                        borderColor: '#4B0082',
                        borderWidth: 1
                    }, {
                        label: 'course',
                        data: coursePrevPlaceStrike,
                        backgroundColor: '#32CD32',
                        borderColor: '#32CD32',
                        borderWidth: 1
                    }, {
                        label: 'distance',
                        data: distancePrevPlaceStrike,
                        backgroundColor: '#FF8C00',
                        borderColor: '#FF8C00',
                        borderWidth: 1
                    }, {
                        label: 'class',
                        data: classPrevPlaceStrike,
                        backgroundColor: '#708090',
                        borderColor: '#708090',
                        borderWidth: 1
                    }, {
                        label: 'direction',
                        data: directionPrevPlaceStrike,
                        backgroundColor: '#9370DB',
                        borderColor: '#9370DB',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { 
                            display: true,
                            position: 'top',
                            onClick: function(e, legendItem) {
                                const index = legendItem.datasetIndex;
                                const chart = this.chart;
                                const meta = chart.getDatasetMeta(index);
                                meta.hidden = meta.hidden === null ? !chart.data.datasets[index].hidden : null;
                                chart.update();
                            },
                            labels: {
                                usePointStyle: true,
                                padding: 25,
                                font: { size: 14, weight: '600' }
                            }
                        }, 
                        title: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = typeof context.parsed?.y !== 'undefined'
                                        ? context.parsed.y
                                        : (typeof context.parsed === 'number' ? context.parsed : 0);
                                    return context.dataset.label + ': ' + value.toFixed(1) + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            grid: { display: false },
                            ticks: { 
                                font: { size: 12, weight: '600' }, 
                                color: '#333',
                                maxRotation: 45,
                                minRotation: 45
                            } 
                        },
                        y: { 
                            beginAtZero: true, 
                            max: dynamicMaxPlaces,
                            grid: { color: 'rgba(0,0,0,0.1)' }, 
                            ticks: { 
                                stepSize: stepSizePlaces, 
                                font: { size: 12, weight: '600' }, 
                                color: '#666' 
                            } 
                        }
                    },
                    elements: { 
                        bar: { 
                            borderWidth: 1, 
                            borderRadius: 4, 
                            borderSkipped: false 
                        } 
                    },
                    layout: { 
                        padding: { top: 20, bottom: 20 } 
                    },
                    animation: { 
                        duration: 1000, 
                        easing: 'easeInOutQuart' 
                    }
                }
            });
            // Store chart instance and original data
            if (!window.raceDetailCharts_<?php echo $race_id; ?>) {
                window.raceDetailCharts_<?php echo $race_id; ?> = {};
                window.raceDetailChartData_<?php echo $race_id; ?> = {};
            }
            window.raceDetailCharts_<?php echo $race_id; ?>.placesStrikeChart = placesStrikeChart;
            window.raceDetailChartData_<?php echo $race_id; ?>.placesStrikeChart = {
                labels: runnerLabelsPlaces,
                prevRunnerPlaceStrike: prevRunnerPlaceStrike,
                goingPrevPlaceStrike: goingPrevPlaceStrike,
                coursePrevPlaceStrike: coursePrevPlaceStrike,
                distancePrevPlaceStrike: distancePrevPlaceStrike,
                classPrevPlaceStrike: classPrevPlaceStrike,
                directionPrevPlaceStrike: directionPrevPlaceStrike
            };
        }
        <?php endif; ?>
        if (raceDetailDebug && window.console && typeof console.groupEnd === 'function') {
            console.groupEnd();
        }
    }
    
    // Initialize charts after DOM is ready
    initRaceDetailCharts_<?php echo $race_id; ?>();
    
    // Keep charts in sync with the currently visible runner rows/order in the table.
    window.filterChartsByNonRunners_<?php echo $race_id; ?> = function() {
        if (!window.raceDetailCharts_<?php echo $race_id; ?> || !window.raceDetailChartData_<?php echo $race_id; ?>) {
            console.log('Charts not yet initialized, skipping filter');
            return;
        }
        
        // Visible runner indices in current table order (respects filters + sorting).
        const visibleRunnerIndices = [];
        document.querySelectorAll('.runner-row').forEach(function(row) {
            if (!row.classList.contains('hidden')) {
                const idx = parseInt(row.dataset.runnerIndex, 10);
                if (!Number.isNaN(idx)) {
                    visibleRunnerIndices.push(idx);
                }
            }
        });
        
        // Helper function: build a filtered array in table order from original index-based arrays.
        function filterArrayByVisibleIndices(arr, indicesToKeep) {
            return indicesToKeep.map(function(idx) {
                return arr[idx];
            }).filter(function(item) {
                return typeof item !== 'undefined';
            });
        }
        
        // Update Speed Rating Chart
        if (window.raceDetailCharts_<?php echo $race_id; ?>.speedRatingChart && window.raceDetailChartData_<?php echo $race_id; ?>.speedRatingChart) {
            const originalData = window.raceDetailChartData_<?php echo $race_id; ?>.speedRatingChart;
            const chart = window.raceDetailCharts_<?php echo $race_id; ?>.speedRatingChart;

            chart.data.labels = filterArrayByVisibleIndices(originalData.labels, visibleRunnerIndices);
            chart.data.datasets.forEach(function(dataset, datasetIndex) {
                chart.data.datasets[datasetIndex].data = filterArrayByVisibleIndices(
                    originalData.datasets[datasetIndex].data,
                    visibleRunnerIndices
                );
            });
            chart.update();
        }
        
        // Update RTF Trainer Chart
        if (window.raceDetailCharts_<?php echo $race_id; ?>.rtfTrainerChart && window.raceDetailChartData_<?php echo $race_id; ?>.rtfTrainerChart) {
            const originalData = window.raceDetailChartData_<?php echo $race_id; ?>.rtfTrainerChart;
            const chart = window.raceDetailCharts_<?php echo $race_id; ?>.rtfTrainerChart;

            chart.data.labels = filterArrayByVisibleIndices(originalData.labels, visibleRunnerIndices);
            chart.data.datasets[0].data = filterArrayByVisibleIndices(originalData.values, visibleRunnerIndices);
            chart.update();
        }
        
        // Update RTF Trainer/Jockey Chart
        if (window.raceDetailCharts_<?php echo $race_id; ?>.rtfTrainerJockeyChart && window.raceDetailChartData_<?php echo $race_id; ?>.rtfTrainerJockeyChart) {
            const originalData = window.raceDetailChartData_<?php echo $race_id; ?>.rtfTrainerJockeyChart;
            const chart = window.raceDetailCharts_<?php echo $race_id; ?>.rtfTrainerJockeyChart;

            chart.data.labels = filterArrayByVisibleIndices(originalData.labels, visibleRunnerIndices);
            chart.data.datasets[0].data = filterArrayByVisibleIndices(originalData.rtfValues, visibleRunnerIndices);
            chart.data.datasets[1].data = filterArrayByVisibleIndices(originalData.jkyValues, visibleRunnerIndices);
            chart.update();
        }
        
        // Update Trainer Performance Chart (filter by trainer names)
        if (window.raceDetailCharts_<?php echo $race_id; ?>.trainerPerformanceChart && window.raceDetailChartData_<?php echo $race_id; ?>.trainerPerformanceChart) {
            const originalData = window.raceDetailChartData_<?php echo $race_id; ?>.trainerPerformanceChart;
            const chart = window.raceDetailCharts_<?php echo $race_id; ?>.trainerPerformanceChart;

            // Build a set of trainers that have at least one visible runner row.
            const activeTrainers = new Set();
            document.querySelectorAll('.runner-row').forEach(function(row) {
                if (!row.classList.contains('hidden')) {
                    const trainerCell = row.querySelector('td:nth-last-child(2)');
                    if (trainerCell) {
                        const trainerText = trainerCell.textContent.trim();
                        const trainerName = trainerText.split('\n')[0].trim();
                        if (trainerName) {
                            activeTrainers.add(trainerName);
                        }
                    }
                }
            });

            // Keep only trainer bars that still have at least one visible runner.
            const visibleTrainerIndices = [];
            originalData.labels.forEach(function(label, index) {
                const trainerName = Array.isArray(label) ? label[0] : label;
                if (activeTrainers.has(trainerName)) {
                    visibleTrainerIndices.push(index);
                }
            });

            chart.data.labels = filterArrayByVisibleIndices(originalData.labels, visibleTrainerIndices);
            chart.data.datasets[0].data = filterArrayByVisibleIndices(originalData.trainer21D, visibleTrainerIndices);
            chart.data.datasets[1].data = filterArrayByVisibleIndices(originalData.trainer42D, visibleTrainerIndices);
            chart.data.datasets[2].data = filterArrayByVisibleIndices(originalData.trainer5Y, visibleTrainerIndices);
            chart.update();
        }
        
        <?php if (!$is_tomorrow_race): ?>
        // Update WINS Strike Chart
        if (window.raceDetailCharts_<?php echo $race_id; ?>.winsStrikeChart && window.raceDetailChartData_<?php echo $race_id; ?>.winsStrikeChart) {
            const originalData = window.raceDetailChartData_<?php echo $race_id; ?>.winsStrikeChart;
            const chart = window.raceDetailCharts_<?php echo $race_id; ?>.winsStrikeChart;

            chart.data.labels = filterArrayByVisibleIndices(originalData.labels, visibleRunnerIndices);
            chart.data.datasets[0].data = filterArrayByVisibleIndices(originalData.prevRunnerWinStrike, visibleRunnerIndices);
            chart.data.datasets[1].data = filterArrayByVisibleIndices(originalData.goingPrevWinStrike, visibleRunnerIndices);
            chart.data.datasets[2].data = filterArrayByVisibleIndices(originalData.coursePrevWinStrike, visibleRunnerIndices);
            chart.data.datasets[3].data = filterArrayByVisibleIndices(originalData.distancePrevWinStrike, visibleRunnerIndices);
            chart.data.datasets[4].data = filterArrayByVisibleIndices(originalData.classPrevWinStrike, visibleRunnerIndices);
            chart.data.datasets[5].data = filterArrayByVisibleIndices(originalData.directionPrevWinStrike, visibleRunnerIndices);
            chart.update();
        }
        <?php endif; ?>
        
        <?php if (!$is_tomorrow_race): ?>
        // Update PLACES Strike Chart
        if (window.raceDetailCharts_<?php echo $race_id; ?>.placesStrikeChart && window.raceDetailChartData_<?php echo $race_id; ?>.placesStrikeChart) {
            const originalData = window.raceDetailChartData_<?php echo $race_id; ?>.placesStrikeChart;
            const chart = window.raceDetailCharts_<?php echo $race_id; ?>.placesStrikeChart;

            chart.data.labels = filterArrayByVisibleIndices(originalData.labels, visibleRunnerIndices);
            chart.data.datasets[0].data = filterArrayByVisibleIndices(originalData.prevRunnerPlaceStrike, visibleRunnerIndices);
            chart.data.datasets[1].data = filterArrayByVisibleIndices(originalData.goingPrevPlaceStrike, visibleRunnerIndices);
            chart.data.datasets[2].data = filterArrayByVisibleIndices(originalData.coursePrevPlaceStrike, visibleRunnerIndices);
            chart.data.datasets[3].data = filterArrayByVisibleIndices(originalData.distancePrevPlaceStrike, visibleRunnerIndices);
            chart.data.datasets[4].data = filterArrayByVisibleIndices(originalData.classPrevPlaceStrike, visibleRunnerIndices);
            chart.data.datasets[5].data = filterArrayByVisibleIndices(originalData.directionPrevPlaceStrike, visibleRunnerIndices);
            chart.update();
        }
        <?php endif; ?>
    };
});

// Toggle details rows
jQuery(document).on('click', '.toggle-details-btn', function() {
    const index = jQuery(this).data('runner-index');
    const detailsRow = jQuery('.details-row-' + index);
    const icon = jQuery(this).find('.toggle-icon');
    
    if (detailsRow.is(':visible')) {
        detailsRow.hide();
        icon.text('▼');
        jQuery(this).html('<span class="toggle-icon">▼</span> View');
    } else {
        detailsRow.show();
        icon.text('▲');
        jQuery(this).html('<span class="toggle-icon">▲</span> Hide');
    }
});

</script>

    <script>
    // Course dropdown toggle function
    function toggleCourseDropdown() {
        const dropdown = document.getElementById('courseDropdown');
        const button = document.querySelector('.course-selector-btn');
        
        if (dropdown && button) {
            const isOpen = dropdown.classList.contains('show');
            
            if (isOpen) {
                dropdown.classList.remove('show');
                button.classList.remove('open');
            } else {
                dropdown.classList.add('show');
                button.classList.add('open');
            }
        }
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('courseDropdown');
        const button = document.querySelector('.course-selector-btn');
        const wrapper = document.querySelector('.course-selector-wrapper');
        
        if (dropdown && button && wrapper) {
            if (!wrapper.contains(event.target)) {
                dropdown.classList.remove('show');
                button.classList.remove('open');
            }
        }
    });
    </script>

        <?php endif; ?>
        </div><!-- .premium-ratings-container -->
        
        <?php else: ?>
        <div class="runners-card">
            <div style="padding:60px;text-align:center;color:#94a3b8;">
                <div style="font-size:64px;margin-bottom:16px;">🏇</div>
                <h3 style="color:#64748b;margin:0;">No runners found for this race</h3>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        (function() {
            function updateActiveRunnerCount() {
                var counter = document.getElementById('activeRunnersCount');
                if (!counter) {
                    return;
                }
                var visible = 0;
                document.querySelectorAll('.runner-row').forEach(function(row) {
                    if (!row.classList.contains('hidden')) {
                        visible++;
                    }
                });
                counter.textContent = visible;
            }

            window.toggleMaturityBadges = function() {
                var checkbox = document.getElementById('showMaturityBadges');
                if (!checkbox) {
                    return;
                }
                var show = checkbox.checked;
                document.querySelectorAll('.maturity-edge-badge').forEach(function(badge) {
                    badge.style.display = show ? 'inline-block' : 'none';
                });
            };

            window.toggleNonRunners = function() {
                var checkbox = document.getElementById('hideNonRunners');
                if (!checkbox) {
                    return;
                }
                var hide = checkbox.checked;
                document.querySelectorAll('.runner-row').forEach(function(row) {
                    if (row.dataset.isNonRunner === '1') {
                        row.classList.toggle('hidden', hide);
                    }
                });
                updateActiveRunnerCount();
                
                // Update charts to hide/show non-runner data
                if (typeof window.filterChartsByNonRunners_<?php echo $race_id; ?> === 'function') {
                    window.filterChartsByNonRunners_<?php echo $race_id; ?>();
                } else {
                    // If charts aren't ready yet, wait a bit and try again
                    setTimeout(function() {
                        if (typeof window.filterChartsByNonRunners_<?php echo $race_id; ?> === 'function') {
                            window.filterChartsByNonRunners_<?php echo $race_id; ?>();
                        }
                    }, 500);
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    toggleNonRunners();
                    toggleMaturityBadges();
                });
            } else {
                toggleNonRunners();
                toggleMaturityBadges();
            }
        })();

        // Client-side sorting for runners table
        (function() {
            var currentSortColumn = 'fsr';
            var currentSortDirection = 'desc'; // Default: FSr descending

            function getDataAttr(row, column) {
                var map = {
                    'cloth_number': 'clothNumber',
                    'stall_number': 'stallNumber',
                    'draw_bias_pct': 'drawBiasPct',
                    'win_pct': 'winPct',
                    'place_pct': 'placePct',
                    'fsr': 'fsr',
                    'fsrr': 'fsrr',
                    'model_points': 'model_points',
                    'comb': 'comb',
                    'dslr': 'dslr',
                    'weight': 'weight',
                    'lbf': 'lbf',
                    'maturity_edge': 'maturityEdge',
                    'sire_5y': 'sire5y',
                    'cls': 'cls',
                    'or_diff': 'orDiff',
                    'jockey': 'jockey'
                };
                var attr = map[column];
                if (!attr) return '';
                return row.dataset[attr] || '';
            }

            function parseNumeric(val) {
                if (!val || val === '-' || val === 'N/A' || val === '') return -9999;
                // Remove %, lbs, etc.
                var cleaned = val.replace(/[%lbs]/gi, '').trim();
                var num = parseFloat(cleaned);
                return isNaN(num) ? -9999 : num;
            }

            function sortRunnersTable(column, sortType) {
                var table = document.querySelector('.runners-table');
                if (!table) return;

                var tbody = table.querySelector('tbody');
                if (!tbody) return;

                // Determine sort direction
                if (currentSortColumn === column) {
                    currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSortColumn = column;
                    // Numeric columns default to descending (highest first), text to ascending
                    currentSortDirection = sortType === 'number' ? 'desc' : 'asc';
                }

                // Get all runner rows and their associated detail rows
                var allRows = Array.from(tbody.querySelectorAll('tr'));
                var rowPairs = [];
                for (var i = 0; i < allRows.length; i++) {
                    if (allRows[i].classList.contains('runner-row')) {
                        var detailRow = (i + 1 < allRows.length && allRows[i + 1].classList.contains('details-row')) 
                            ? allRows[i + 1] : null;
                        rowPairs.push({ runner: allRows[i], detail: detailRow });
                    }
                }

                // Sort the pairs
                rowPairs.sort(function(a, b) {
                    var valA = getDataAttr(a.runner, column);
                    var valB = getDataAttr(b.runner, column);

                    var result;
                    if (sortType === 'number') {
                        result = parseNumeric(valA) - parseNumeric(valB);
                    } else {
                        result = valA.localeCompare(valB, undefined, { sensitivity: 'base' });
                    }

                    return currentSortDirection === 'desc' ? -result : result;
                });

                // Re-append rows in sorted order and update zebra striping
                rowPairs.forEach(function(pair, idx) {
                    var bgColor = idx % 2 === 0 ? '#ffffff' : '#f9fafb';
                    // Preserve non-runner and SR highlight backgrounds
                    if (pair.runner.dataset.isNonRunner === '1') {
                        bgColor = '#dbeafe';
                    } else if (pair.runner.dataset.srHighlight === '1') {
                        bgColor = '#FFE7EF';
                    }
                    pair.runner.style.background = bgColor;
                    tbody.appendChild(pair.runner);
                    if (pair.detail) {
                        tbody.appendChild(pair.detail);
                    }
                });

                // Update header sort indicators
                table.querySelectorAll('th.sortable').forEach(function(th) {
                    th.classList.remove('sorted-asc', 'sorted-desc');
                    var arrow = th.querySelector('.sort-arrow');
                    if (arrow) arrow.textContent = '';
                });

                var activeHeader = table.querySelector('th.sortable[data-column="' + column + '"]');
                if (activeHeader) {
                    activeHeader.classList.add('sorted-' + currentSortDirection);
                    var arrow = activeHeader.querySelector('.sort-arrow');
                    if (arrow) {
                        arrow.textContent = currentSortDirection === 'asc' ? '▲' : '▼';
                    }
                }

                // Keep charts aligned with current visible table rows after sorting.
                if (typeof window.filterChartsByNonRunners_<?php echo $race_id; ?> === 'function') {
                    window.filterChartsByNonRunners_<?php echo $race_id; ?>();
                }
            }

            // Attach click handlers to sortable headers
            function initSortableHeaders() {
                var table = document.querySelector('.runners-table');
                if (!table) return;

                table.querySelectorAll('th.sortable').forEach(function(th) {
                    th.addEventListener('click', function() {
                        var column = this.getAttribute('data-column');
                        var sortType = this.getAttribute('data-sort');
                        if (column) {
                            sortRunnersTable(column, sortType);
                        }
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initSortableHeaders);
            } else {
                initSortableHeaders();
            }
        })();
    </script>
    
    <?php
    $shortcode_content = ob_get_clean();
    
    // Append shortcode content
    $content .= $shortcode_content;
    
    // If this is a standalone page, include footer (same as daily page)
    if (bricks_is_standalone_page()) {
        $content .= '</div></main>';
        ob_start();
        get_footer();
        $content .= ob_get_clean();
    }
    
    // If header was not added (not standalone), just return shortcode content
    if (empty($content)) {
        $content = $shortcode_content;
    }
    
    return $content;
}

// Register shortcode
add_shortcode('race_detail', 'bricks_race_detail_shortcode');

// ==============================================
// URL REWRITE RULES
// ==============================================

function bricks_is_standalone_page() {
    // Check if we're on a page that should have full page layout
    $current_url = $_SERVER['REQUEST_URI'];

    // Race pages use race-detail.php (header/footer + shortcode). Do not wrap again.
    if (get_query_var('race_id')) {
        return false;
    }

    return (
        strpos($current_url, '/daily') !== false ||
        strpos($current_url, '/speed') !== false ||
        strpos($current_url, '/my-tracker') !== false ||
        strpos($current_url, '/points-backtest') !== false ||
        strpos($current_url, '/today-picks') !== false ||
        strpos($current_url, '/tracks') !== false ||
        strpos($current_url, '/racecourses') !== false ||
        strpos($current_url, '/festivals') !== false ||
        get_query_var('my_tracker_page') ||
        get_query_var('my_points_backtest') ||
        get_query_var('my_today_picks_page') ||
        get_query_var('track_slug') ||
        get_query_var('tracks_index') ||
        get_query_var('racecourses_index') ||
        get_query_var('racecourses_region') ||
        get_query_var('proven_winners_page') ||
        get_query_var('festivals_index') ||
        get_query_var('festival_slug') ||
        (function_exists('bricks_proven_winners_is_request') && bricks_proven_winners_is_request()) ||
        (function_exists('bricks_festival_is_request') && bricks_festival_is_request())
    );
}




function bricks_get_navigation_header() {
    // Simplified fallback header - Bricks templates will override this
    $current_url = $_SERVER['REQUEST_URI'];
    $wp_pages = get_pages(array('post_status' => 'publish', 'sort_column' => 'menu_order'));
    
    ob_start();
    ?>
    <div class="racing-navigation-header">
        <div class="nav-container">
            <div class="site-branding">
                <a href="<?php echo home_url('/'); ?>" class="brand-link">
                    <span class="brand-icon">🏇</span>
                    <span class="brand-text"><?php bloginfo('name'); ?></span>
                </a>
            </div>
            
            <nav class="main-nav">
                <ul class="nav-menu">
                    <li>
                        <a href="<?php echo home_url('/'); ?>" class="nav-link <?php echo (is_home() || is_front_page()) ? 'active' : ''; ?>">
                            <span class="nav-icon">🏠</span>
                            <span class="nav-text">Home</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo home_url('/daily/'); ?>" class="nav-link <?php echo (strpos($current_url, '/daily') !== false) ? 'active' : ''; ?>">
                            <span class="nav-icon">🏁</span>
                            <span class="nav-text">Daily</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo home_url('/speed/'); ?>" class="nav-link <?php echo (strpos($current_url, '/speed') !== false) ? 'active' : ''; ?>">
                            <span class="nav-icon">⚡</span>
                            <span class="nav-text">Quick Reference</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo home_url('/my-tracker/'); ?>" class="nav-link <?php echo (strpos($current_url, '/my-tracker') !== false) ? 'active' : ''; ?>">
                            <span class="nav-icon">📝</span>
                            <span class="nav-text">My Tracker</span>
                        </a>
                    </li>
                    <?php if (function_exists('bricks_user_can_access_points_backtest') && bricks_user_can_access_points_backtest()): ?>
                    <li>
                        <a href="<?php echo home_url('/today-picks/'); ?>" class="nav-link <?php echo (strpos($current_url, '/today-picks') !== false) ? 'active' : ''; ?>">
                            <span class="nav-icon">📋</span>
                            <span class="nav-text">Today's Picks</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo home_url('/points-backtest/'); ?>" class="nav-link <?php echo (strpos($current_url, '/points-backtest') !== false) ? 'active' : ''; ?>">
                            <span class="nav-icon">📊</span>
                            <span class="nav-text">Points Backtest</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($wp_pages): ?>
                        <?php foreach ($wp_pages as $page): ?>
                            <li>
                                <a href="<?php echo get_permalink($page->ID); ?>" class="nav-link">
                                    <span class="nav-icon">📄</span>
                                    <span class="nav-text"><?php echo esc_html($page->post_title); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>

    <style>
        .racing-navigation-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-bottom: 0;
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 70px;
        }
        
        .site-branding .brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: white;
            font-weight: 800;
            font-size: 20px;
        }
        
        .main-nav .nav-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 8px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: #60a5fa;
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
    </style>
    <?php
    return ob_get_clean();
}





/**
 * Horse History Page Implementation
 */
/**
 * Horse History Page Implementation
 */

// ==============================================
// HORSE HISTORY HELPER FUNCTIONS




// ==============================================
// URL REWRITE RULES FOR HORSE HISTORY
// ==============================================





// ==============================================
// URL REWRITE RULES FOR RACE COMMENTS
// ==============================================






/**
 * ============================================================
 * UPDATE DETAILS - User Profile Update Page
 * ============================================================
 * Shortcode: [update_details]
 * Place this shortcode on the "Update Details" page in Bricks.
 * Allows logged-in users to update their profile information.
 * ============================================================
 */

/**
 * Handle the form submission BEFORE headers are sent (on init).
 * This way we can set transients for messages and redirect cleanly.
 */
add_action('init', 'update_details_handle_form_submission');
function update_details_handle_form_submission() {
    // Only process if our form was submitted
    if (!isset($_POST['update_details_submit'])) {
        return;
    }

    // Must be logged in
    if (!is_user_logged_in()) {
        return;
    }

    // Verify nonce
    if (!isset($_POST['update_details_nonce']) || !wp_verify_nonce($_POST['update_details_nonce'], 'update_details_action')) {
        return;
    }

    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $errors = [];
    $success = [];

    // === PROFILE INFO UPDATE ===
    if (isset($_POST['update_profile'])) {
        $first_name   = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name    = sanitize_text_field($_POST['last_name'] ?? '');
        $display_name = sanitize_text_field($_POST['display_name'] ?? '');
        $nickname     = sanitize_text_field($_POST['nickname'] ?? '');
        $description  = sanitize_textarea_field($_POST['description'] ?? '');
        $user_url     = esc_url_raw($_POST['user_url'] ?? '');

        $userdata = [
            'ID'           => $user_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $display_name,
            'nickname'     => $nickname,
            'description'  => $description,
            'user_url'     => $user_url,
        ];

        $result = wp_update_user($userdata);

        if (is_wp_error($result)) {
            $errors[] = $result->get_error_message();
        } else {
            $success[] = 'Your profile details have been updated successfully.';
        }
    }

    // === EMAIL UPDATE ===
    if (isset($_POST['update_email'])) {
        $new_email = sanitize_email($_POST['new_email'] ?? '');

        if (empty($new_email)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (!is_email($new_email)) {
            $errors[] = 'The email address entered is not valid.';
        } elseif ($new_email === $current_user->user_email) {
            $errors[] = 'The new email is the same as your current email.';
        } elseif (email_exists($new_email) && email_exists($new_email) !== $user_id) {
            $errors[] = 'This email address is already in use by another account.';
        } else {
            // Confirm current password before allowing email change
            $confirm_password = $_POST['email_confirm_password'] ?? '';
            if (empty($confirm_password)) {
                $errors[] = 'Please enter your current password to confirm the email change.';
            } elseif (!wp_check_password($confirm_password, $current_user->user_pass, $user_id)) {
                $errors[] = 'The password you entered is incorrect. Email was not changed.';
            } else {
                $result = wp_update_user([
                    'ID'         => $user_id,
                    'user_email' => $new_email,
                ]);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $success[] = 'Your email address has been updated successfully.';
                }
            }
        }
    }

    // === PASSWORD UPDATE ===
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $errors[] = 'Please fill in all password fields.';
        } elseif (!wp_check_password($current_password, $current_user->user_pass, $user_id)) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        } elseif ($current_password === $new_password) {
            $errors[] = 'New password must be different from your current password.';
        } else {
            wp_set_password($new_password, $user_id);
            // Re-login the user so they don't get logged out
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            $success[] = 'Your password has been changed successfully.';
        }
    }

    // Store messages in transients (per-user, short-lived)
    if (!empty($errors)) {
        set_transient('update_details_errors_' . $user_id, $errors, 60);
    }
    if (!empty($success)) {
        set_transient('update_details_success_' . $user_id, $success, 60);
    }

    // Redirect to avoid form resubmission
    $redirect_url = isset($_POST['_wp_http_referer']) ? $_POST['_wp_http_referer'] : wp_get_referer();
    if (!$redirect_url) {
        $redirect_url = home_url('/update-details/');
    }
    wp_safe_redirect($redirect_url);
    exit;
}

/**
 * Target URL for the “Manage subscription” CTA on [update_details].
 * Not in git history: a Bricks-only button on the page would vanish if the page was re-saved without it.
 *
 * Resolution order: FHOR_SUBSCRIPTION_MANAGE_URL constant → option fhor_subscription_manage_url
 * → filter fhor_update_details_subscription_manage_url → WooCommerce My Account → published page by slug.
 *
 * @return string Escaped URL or empty string.
 */
function fhor_get_update_details_subscription_manage_url() {
    if (defined('FHOR_SUBSCRIPTION_MANAGE_URL') && FHOR_SUBSCRIPTION_MANAGE_URL !== '') {
        return esc_url(FHOR_SUBSCRIPTION_MANAGE_URL);
    }
    $opt = get_option('fhor_subscription_manage_url', '');
    if (is_string($opt) && $opt !== '') {
        return esc_url($opt);
    }
    $filtered = apply_filters('fhor_update_details_subscription_manage_url', '');
    if (is_string($filtered) && $filtered !== '') {
        return esc_url($filtered);
    }
    if (function_exists('wc_get_page_permalink')) {
        $my = wc_get_page_permalink('myaccount');
        if (!empty($my)) {
            return esc_url($my);
        }
    }
    foreach (['my-account', 'account', 'subscriptions', 'member-account'] as $slug) {
        $p = get_page_by_path($slug);
        if ($p instanceof WP_Post && $p->post_status === 'publish') {
            return esc_url(get_permalink($p));
        }
    }
    return '';
}

/**
 * Race access policy:
 * - Wednesday: any logged-in user can view races.
 * - Other days: user must be a paid Bricks Members member.
 */
function fhor_user_has_paid_race_access($user_id = 0) {
    if (!is_user_logged_in()) {
        return false;
    }

    $user_id = $user_id ? intval($user_id) : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }

    if (user_can($user_id, 'manage_options')) {
        return true;
    }

    try {
        $race_tz = new DateTimeZone('Europe/London');
    } catch (Exception $e) {
        $race_tz = wp_timezone();
    }
    $now = new DateTimeImmutable('now', $race_tz);
    if ($now->format('N') === '3') {
        return true;
    }

    // Allow explicit site-level override via custom integration.
    $override = apply_filters('fhor_bricks_members_paid_access', null, $user_id);
    if (is_bool($override)) {
        return $override;
    }

    $allowed_level_ids = [5];
    $allowed_level_names = ['fhorsite member'];

    $user_level_ids = [];
    $user_level_names = [];

    $raw_levels = null;
    if (function_exists('bricks_members_get_user_levels')) {
        $raw_levels = bricks_members_get_user_levels($user_id);
    } elseif (function_exists('bm_get_user_levels')) {
        $raw_levels = bm_get_user_levels($user_id);
    }

    if (is_array($raw_levels)) {
        foreach ($raw_levels as $lvl) {
            if (is_numeric($lvl)) {
                $user_level_ids[] = intval($lvl);
                continue;
            }
            if (is_object($lvl)) {
                if (isset($lvl->id) && is_numeric($lvl->id)) {
                    $user_level_ids[] = intval($lvl->id);
                }
                if (isset($lvl->name)) {
                    $user_level_names[] = strtolower(trim((string) $lvl->name));
                }
                continue;
            }
            if (is_array($lvl)) {
                if (isset($lvl['id']) && is_numeric($lvl['id'])) {
                    $user_level_ids[] = intval($lvl['id']);
                }
                if (isset($lvl['name'])) {
                    $user_level_names[] = strtolower(trim((string) $lvl['name']));
                }
            }
        }
    }

    $user_level_ids = array_values(array_unique(array_filter($user_level_ids, function($v) {
        return $v > 0;
    })));
    $user_level_names = array_values(array_unique(array_filter($user_level_names, function($name) {
        return $name !== '';
    })));

    if (!empty($allowed_level_ids) && !empty(array_intersect($allowed_level_ids, $user_level_ids))) {
        return true;
    }

    if (!empty($allowed_level_names) && !empty(array_intersect($allowed_level_names, $user_level_names))) {
        return true;
    }

    return false;
}

function fhor_race_access_required_message() {
    ob_start();
    $manage_url = fhor_get_update_details_subscription_manage_url();
    ?>
    <div style="text-align:center;padding:28px 20px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
        <div style="font-size:28px;margin-bottom:10px;">🔒</div>
        <div style="font-size:18px;font-weight:700;color:#111827;margin-bottom:8px;">Race access is for paid members</div>
        <div style="color:#6b7280;max-width:520px;margin:0 auto 14px;">
            On Wednesdays, all logged-in users can view races. On other days, an active paid membership is required.
        </div>
        <?php if (!is_user_logged_in()): ?>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" style="display:inline-block;padding:10px 16px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">Log in</a>
        <?php elseif (!empty($manage_url)): ?>
            <a href="<?php echo esc_url($manage_url); ?>" style="display:inline-block;padding:10px 16px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">Manage subscription</a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * The Update Details shortcode - renders the profile update form.
 */
function update_details_shortcode($atts) {
    // Must be logged in
    if (!is_user_logged_in()) {
        ob_start();
        ?>
        <div class="ud-wrapper">
            <div class="ud-login-required">
                <div class="ud-login-icon">🔒</div>
                <h2>Login Required</h2>
                <p>You need to be logged in to update your details.</p>
                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="ud-btn ud-btn-primary">
                    Log In
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;

    // Get flash messages
    $errors = get_transient('update_details_errors_' . $user_id);
    $success_msgs = get_transient('update_details_success_' . $user_id);
    delete_transient('update_details_errors_' . $user_id);
    delete_transient('update_details_success_' . $user_id);

    ob_start();
    ?>
    <style>
        /* ===== Update Details Page Styles ===== */
        .ud-wrapper {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 20px 60px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .ud-page-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .ud-page-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .ud-page-header p {
            color: #64748b;
            font-size: 16px;
            margin: 0;
        }

        /* Messages */
        .ud-message {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ud-message-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .ud-message-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Cards / Sections */
        .ud-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .ud-card-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ud-card-header-icon {
            font-size: 22px;
        }

        .ud-card-header h2 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            color: white;
        }

        .ud-card-header p {
            font-size: 13px;
            opacity: 0.8;
            margin: 4px 0 0 0;
            color: white;
        }

        .ud-card-body {
            padding: 24px;
        }

        /* Form Elements */
        .ud-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .ud-form-row.single {
            grid-template-columns: 1fr;
        }

        .ud-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ud-form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .ud-form-group input[type="text"],
        .ud-form-group input[type="email"],
        .ud-form-group input[type="password"],
        .ud-form-group input[type="url"],
        .ud-form-group textarea {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            color: #1e293b;
            background: #f8fafc;
            transition: all 0.2s ease;
            outline: none;
            width: 100%;
            box-sizing: border-box;
        }

        .ud-form-group input:focus,
        .ud-form-group textarea:focus {
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .ud-form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .ud-form-group .ud-field-hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .ud-form-group .ud-current-value {
            font-size: 12px;
            color: #64748b;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 2px;
        }

        /* Buttons */
        .ud-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .ud-btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .ud-btn-primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .ud-btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .ud-btn-warning:hover {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
        }

        .ud-btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .ud-btn-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .ud-card-footer {
            padding: 16px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
        }

        /* User Info Banner */
        .ud-user-banner {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 12px;
            padding: 24px;
            color: white;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
        }

        .ud-user-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.2);
            overflow: hidden;
            flex-shrink: 0;
        }

        .ud-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ud-user-info h3 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: white;
        }

        .ud-user-info p {
            font-size: 14px;
            opacity: 0.7;
            margin: 0;
            color: white;
        }

        .ud-user-meta {
            display: flex;
            gap: 16px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .ud-user-meta span {
            font-size: 12px;
            background: rgba(255,255,255,0.1);
            padding: 4px 10px;
            border-radius: 20px;
        }

        /* Login Required */
        .ud-login-required {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .ud-login-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .ud-login-required h2 {
            color: #1e293b;
            margin: 0 0 8px 0;
        }

        .ud-login-required p {
            color: #64748b;
            margin: 0 0 24px 0;
        }

        /* Password Strength Indicator */
        .ud-password-strength {
            height: 4px;
            border-radius: 2px;
            background: #e2e8f0;
            margin-top: 6px;
            overflow: hidden;
        }

        .ud-password-strength-bar {
            height: 100%;
            border-radius: 2px;
            transition: all 0.3s ease;
            width: 0%;
        }

        /* Toggle password visibility */
        .ud-password-wrapper {
            position: relative;
        }

        .ud-password-wrapper input {
            padding-right: 44px !important;
        }

        .ud-password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #94a3b8;
            padding: 4px;
            line-height: 1;
        }

        .ud-password-toggle:hover {
            color: #475569;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .ud-wrapper {
                padding: 16px 12px 40px;
            }

            .ud-form-row {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .ud-user-banner {
                flex-direction: column;
                text-align: center;
            }

            .ud-user-meta {
                justify-content: center;
            }

            .ud-page-header h1 {
                font-size: 24px;
            }

            .ud-card-body {
                padding: 16px;
            }
        }
    </style>

    <div class="ud-wrapper">
        <div class="ud-page-header">
            <h1>⚙️ Update Your Details</h1>
            <p>Manage your profile information, email and password</p>
        </div>

        <?php
        // Display messages
        if ($success_msgs && is_array($success_msgs)) {
            foreach ($success_msgs as $msg) {
                echo '<div class="ud-message ud-message-success">✅ ' . esc_html($msg) . '</div>';
            }
        }
        if ($errors && is_array($errors)) {
            foreach ($errors as $err) {
                echo '<div class="ud-message ud-message-error">⚠️ ' . esc_html($err) . '</div>';
            }
        }
        ?>

        <!-- User Banner -->
        <div class="ud-user-banner">
            <div class="ud-user-avatar">
                <?php echo get_avatar($user_id, 144); ?>
            </div>
            <div class="ud-user-info">
                <h3><?php echo esc_html($current_user->display_name); ?></h3>
                <p><?php echo esc_html($current_user->user_email); ?></p>
                <div class="ud-user-meta">
                    <span>👤 <?php echo esc_html(ucfirst(implode(', ', $current_user->roles))); ?></span>
                    <span>📅 Member since <?php echo date('M Y', strtotime($current_user->user_registered)); ?></span>
                </div>
            </div>
        </div>

        <!-- ===== SECTION 1: Profile Details ===== -->
        <form method="post" action="">
            <?php wp_nonce_field('update_details_action', 'update_details_nonce'); ?>
            <input type="hidden" name="update_profile" value="1">

            <div class="ud-card">
                <div class="ud-card-header">
                    <span class="ud-card-header-icon">👤</span>
                    <div>
                        <h2>Profile Details</h2>
                        <p>Update your name and personal information</p>
                    </div>
                </div>
                <div class="ud-card-body">
                    <div class="ud-form-row">
                        <div class="ud-form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo esc_attr($current_user->first_name); ?>" 
                                   placeholder="Enter your first name">
                        </div>
                        <div class="ud-form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo esc_attr($current_user->last_name); ?>" 
                                   placeholder="Enter your last name">
                        </div>
                    </div>

                    <div class="ud-form-row">
                        <div class="ud-form-group">
                            <label for="display_name">Display Name</label>
                            <input type="text" id="display_name" name="display_name" 
                                   value="<?php echo esc_attr($current_user->display_name); ?>" 
                                   placeholder="How your name appears on the site">
                            <span class="ud-field-hint">This is the name that will be shown publicly.</span>
                        </div>
                        <div class="ud-form-group">
                            <label for="nickname">Nickname</label>
                            <input type="text" id="nickname" name="nickname" 
                                   value="<?php echo esc_attr($current_user->nickname); ?>" 
                                   placeholder="Your nickname">
                        </div>
                    </div>

                    <div class="ud-form-row single">
                        <div class="ud-form-group">
                            <label for="user_url">Website</label>
                            <input type="url" id="user_url" name="user_url" 
                                   value="<?php echo esc_attr($current_user->user_url); ?>" 
                                   placeholder="https://yourwebsite.com">
                        </div>
                    </div>

                    <div class="ud-form-row single">
                        <div class="ud-form-group">
                            <label for="description">Bio / About</label>
                            <textarea id="description" name="description" 
                                      placeholder="Tell us a little about yourself..."><?php echo esc_textarea($current_user->description); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="ud-card-footer">
                    <button type="submit" name="update_details_submit" class="ud-btn ud-btn-primary">
                        💾 Save Profile
                    </button>
                </div>
            </div>
        </form>

        <!-- ===== SECTION 2: Email Address ===== -->
        <form method="post" action="">
            <?php wp_nonce_field('update_details_action', 'update_details_nonce'); ?>
            <input type="hidden" name="update_email" value="1">

            <div class="ud-card">
                <div class="ud-card-header">
                    <span class="ud-card-header-icon">📧</span>
                    <div>
                        <h2>Email Address</h2>
                        <p>Change the email address associated with your account</p>
                    </div>
                </div>
                <div class="ud-card-body">
                    <div class="ud-form-row single">
                        <div class="ud-form-group">
                            <label>Current Email</label>
                            <span class="ud-current-value"><?php echo esc_html($current_user->user_email); ?></span>
                        </div>
                    </div>
                    <div class="ud-form-row">
                        <div class="ud-form-group">
                            <label for="new_email">New Email Address</label>
                            <input type="email" id="new_email" name="new_email" 
                                   placeholder="Enter your new email address" required>
                        </div>
                        <div class="ud-form-group">
                            <label for="email_confirm_password">Current Password</label>
                            <div class="ud-password-wrapper">
                                <input type="password" id="email_confirm_password" name="email_confirm_password" 
                                       placeholder="Confirm with your password" required>
                                <button type="button" class="ud-password-toggle" onclick="udTogglePassword('email_confirm_password', this)">👁</button>
                            </div>
                            <span class="ud-field-hint">Required to confirm email change.</span>
                        </div>
                    </div>
                </div>
                <div class="ud-card-footer">
                    <button type="submit" name="update_details_submit" class="ud-btn ud-btn-warning">
                        📧 Update Email
                    </button>
                </div>
            </div>
        </form>

        <!-- ===== SECTION 3: Change Password ===== -->
        <form method="post" action="">
            <?php wp_nonce_field('update_details_action', 'update_details_nonce'); ?>
            <input type="hidden" name="update_password" value="1">

            <div class="ud-card">
                <div class="ud-card-header">
                    <span class="ud-card-header-icon">🔑</span>
                    <div>
                        <h2>Change Password</h2>
                        <p>Update your password to keep your account secure</p>
                    </div>
                </div>
                <div class="ud-card-body">
                    <div class="ud-form-row single">
                        <div class="ud-form-group">
                            <label for="current_password">Current Password</label>
                            <div class="ud-password-wrapper">
                                <input type="password" id="current_password" name="current_password" 
                                       placeholder="Enter your current password" required>
                                <button type="button" class="ud-password-toggle" onclick="udTogglePassword('current_password', this)">👁</button>
                            </div>
                        </div>
                    </div>
                    <div class="ud-form-row">
                        <div class="ud-form-group">
                            <label for="new_password">New Password</label>
                            <div class="ud-password-wrapper">
                                <input type="password" id="new_password" name="new_password" 
                                       placeholder="Enter new password" required
                                       oninput="udCheckPasswordStrength(this.value)">
                                <button type="button" class="ud-password-toggle" onclick="udTogglePassword('new_password', this)">👁</button>
                            </div>
                            <div class="ud-password-strength">
                                <div class="ud-password-strength-bar" id="ud-pw-strength-bar"></div>
                            </div>
                            <span class="ud-field-hint" id="ud-pw-strength-text">Minimum 8 characters</span>
                        </div>
                        <div class="ud-form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="ud-password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       placeholder="Re-enter new password" required>
                                <button type="button" class="ud-password-toggle" onclick="udTogglePassword('confirm_password', this)">👁</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ud-card-footer">
                    <button type="submit" name="update_details_submit" class="ud-btn ud-btn-danger">
                        🔑 Change Password
                    </button>
                </div>
            </div>
        </form>

        <?php
        $manage_sub_url = "https://billing.stripe.com/p/login/7sY4gy9tY6dm1FU8u09Zm00?prefilled_email=";
        if ($manage_sub_url !== '') :
            ?>
        <!-- Subscription / billing (code-driven so it is not lost when the Bricks page is edited) -->
        <div class="ud-card">
            <div class="ud-card-header">
                <span class="ud-card-header-icon">💳</span>
                <div>
                    <h2>Subscription &amp; billing</h2>
                    <p>Upgrade, downgrade, update payment details, or cancel from your account area</p>
                </div>
            </div>
            <div class="ud-card-footer">
                <a href="<?php echo esc_url($manage_sub_url); echo esc_html($current_user->user_email); ?>" class="ud-btn ud-btn-primary">Manage subscription</a>
            </div>
        </div>
            <?php
        endif;
        ?>
    </div>

    <script>
    // Toggle password visibility
    function udTogglePassword(inputId, btn) {
        var input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = '🙈';
        } else {
            input.type = 'password';
            btn.textContent = '👁';
        }
    }

    // Password strength checker
    function udCheckPasswordStrength(password) {
        var bar = document.getElementById('ud-pw-strength-bar');
        var text = document.getElementById('ud-pw-strength-text');
        var strength = 0;

        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;

        var levels = [
            { width: '0%',   color: '#e2e8f0', label: 'Minimum 8 characters' },
            { width: '20%',  color: '#ef4444', label: 'Very weak' },
            { width: '40%',  color: '#f97316', label: 'Weak' },
            { width: '60%',  color: '#eab308', label: 'Fair' },
            { width: '80%',  color: '#22c55e', label: 'Strong' },
            { width: '100%', color: '#16a34a', label: 'Very strong' },
        ];

        var level = levels[strength] || levels[0];
        bar.style.width = level.width;
        bar.style.background = level.color;
        text.textContent = level.label;
    }

    // Auto-dismiss success messages after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        var successMsgs = document.querySelectorAll('.ud-message-success');
        successMsgs.forEach(function(msg) {
            setTimeout(function() {
                msg.style.transition = 'opacity 0.5s ease, max-height 0.5s ease';
                msg.style.opacity = '0';
                msg.style.maxHeight = '0';
                msg.style.marginBottom = '0';
                msg.style.padding = '0';
                msg.style.overflow = 'hidden';
            }, 5000);
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('update_details', 'update_details_shortcode');

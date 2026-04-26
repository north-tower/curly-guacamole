<?php
/**
 * Horse history feature: helpers, AJAX filters, and shortcode rendering.
 */

// ==============================================

if (!function_exists('fh_get_horse_profile')) {
    function fh_get_horse_profile($horse_name) {
        global $wpdb;
        $key = 'fh_profile_' . md5($horse_name);
        $cached = get_transient($key);
        if ($cached !== false) return $cached;

        // Only select profile columns - single fast query
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT age_range, colour, sex, form_figures, foaling_date, sire, dam, owner, trainer, avg_rating
             FROM daily_comment_history WHERE name = %s ORDER BY meeting_date DESC LIMIT 1",
            $horse_name
        ));

        // Cache for 1 hour
        set_transient($key, $row, HOUR_IN_SECONDS);
        return $row;
    }
}

if (!function_exists('fh_get_horse_profile_by_runner_id')) {
    function fh_get_horse_profile_by_runner_id($runner_id) {
        global $wpdb;
        $runner_id = intval($runner_id);
        if ($runner_id <= 0) return null;

        $key = 'fh_profile_runner_' . $runner_id;
        $cached = get_transient($key);
        if ($cached !== false) return $cached;

        // Updated to match your actual table structure
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                name, 
                age_range, 
                class, 
                official_rating, 
                weight_pounds, 
                foaling_date,
                form_figures,
                speed_rating,
                legacy_speed_rating,
                wt_speed_rating,
                jockey_name,
                starting_price,
                going,
                race_type,
                country
             FROM daily_comment_history 
             WHERE runner_id = %d 
             ORDER BY meeting_date DESC 
             LIMIT 1",
            $runner_id
        ));

        set_transient($key, $row, 6 * HOUR_IN_SECONDS);
        return $row;
    }
}


if (!function_exists('fh_get_runner_snapshot_from_sp')) {
    function fh_get_runner_snapshot_from_sp($runner_id) {
        global $wpdb;
        $runner_id = intval($runner_id);
        if ($runner_id <= 0) return null;

        $key = 'fh_sp_runner_' . $runner_id;
        $cached = get_transient($key);
        if ($cached !== false) return $cached;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT `name`, `age`, `gender`, `colour`, `form_figures`, `sire_name`, `dam_name`, 
                    `owner_name`, `jockey_name`, `trainer_name`
             FROM `speed&performance_table`
             WHERE `runner_id` = %d
             ORDER BY STR_TO_DATE(`Date`, '%%d-%%m-%%Y') DESC, `Time` DESC
             LIMIT 1",
            $runner_id
        ));

        // Only cache when a row is found
        if ($row) {
            set_transient($key, $row, 3 * HOUR_IN_SECONDS);
        } else {
            delete_transient($key);
        }
        return $row;
    }
}

if (!function_exists('fh_get_runner_snapshot_from_sp_by_name')) {
    function fh_get_runner_snapshot_from_sp_by_name($horse_name) {
        global $wpdb;
        $horse_name = trim($horse_name);
        if ($horse_name === '') return null;

        $key = 'fh_sp_name_' . md5($horse_name);
        $cached = get_transient($key);
        if ($cached !== false) return $cached;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT `name`, `age`, `gender`, `colour`, `form_figures`, `sire_name`, `dam_name`, 
                    `owner_name`, `jockey_name`, `trainer_name`
             FROM `speed&performance_table`
             WHERE `name` = %s
             ORDER BY STR_TO_DATE(`Date`, '%%d-%%m-%%Y') DESC, `Time` DESC
             LIMIT 1",
            $horse_name
        ));

        if ($row) {
            set_transient($key, $row, 3 * HOUR_IN_SECONDS);
        } else {
            delete_transient($key);
        }
        return $row;
    }
}

if (!function_exists('fh_get_race_history_by_runner_id')) {
    function fh_get_race_history_by_runner_id($runner_id, $limit = 10) {
        global $wpdb;
        $runner_id = intval($runner_id);
        if ($runner_id <= 0) return [];

        $key = 'fh_race_history_runner_' . $runner_id;
        $cached = get_transient($key);
        if ($cached !== false) return $cached;

        // Get race history with ALL columns including new ones
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                race_id,
                meeting_date,
                course,
                direction,
                profile,
                general_features,
                specific_features,
                race_title,
                Distance as distance,
                going,
                class,
                finish_position as position,
                Runner_Count as runners,
                name,
                race_type,
                starting_price,
                official_rating,
                speed_rating,
                wt_speed_rating,
                in_race_comment,
                days_since_ran,
                distance_beaten,
                value,
                race_abbrev_name,
                jockey_name
             FROM daily_comment_history 
             WHERE runner_id = %d 
             ORDER BY meeting_date DESC 
             LIMIT %d",
            $runner_id,
            $limit
        ));

        // If no results by runner_id, try by horse name as fallback
        if (empty($results)) {
            // Get horse name from advance_daily_runners_beta table
            $horse_name = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM advance_daily_runners_beta WHERE runner_id = %d LIMIT 1",
                $runner_id
            ));
            
            if ($horse_name) {
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        race_id,
                        meeting_date,
                        course,
                        direction,
                        profile,
                        general_features,
                        specific_features,
                        race_title,
                        Distance as distance,
                        going,
                        class,
                        finish_position as position,
                        Runner_Count as runners,
                        name,
                        race_type,
                        starting_price,
                        official_rating,
                        speed_rating,
                        wt_speed_rating,
                        in_race_comment,
                        days_since_ran,
                        distance_beaten,
                        value,
                        race_abbrev_name,
                        jockey_name
                     FROM daily_comment_history 
                     WHERE name = %s 
                     ORDER BY meeting_date DESC 
                     LIMIT %d",
                    $horse_name,
                    $limit
                ));
            }
        }

        // Cache for 2 hours only if we have results
        if (!empty($results)) {
            set_transient($key, $results, 2 * HOUR_IN_SECONDS);
        }
        
        return $results;
    }

    // ==============================================
// AJAX HANDLER FOR HORSE HISTORY FILTERS
// ==============================================

function get_horse_history_filter_options() {
    global $wpdb;
    
    $runner_id = isset($_POST['runner_id']) ? intval($_POST['runner_id']) : 0;
    $horse_name = isset($_POST['horse_name']) ? sanitize_text_field($_POST['horse_name']) : '';
    
    if (!$runner_id && !$horse_name) {
        wp_send_json_error('Runner ID or horse name required');
        return;
    }
    
    $where = '';
    if ($runner_id) {
        $where = $wpdb->prepare("runner_id = %d", $runner_id);
    } else {
        $where = $wpdb->prepare("name = %s", $horse_name);
    }
    
    // Get unique values for each filter
    $profiles = $wpdb->get_col(
        "SELECT DISTINCT profile FROM daily_comment_history 
         WHERE $where AND profile IS NOT NULL AND profile != '' 
         ORDER BY profile"
    );
    
    $general_features = $wpdb->get_col(
        "SELECT DISTINCT general_features FROM daily_comment_history 
         WHERE $where AND general_features IS NOT NULL AND general_features != '' 
         ORDER BY general_features"
    );
    
    $specific_features = $wpdb->get_col(
        "SELECT DISTINCT specific_features FROM daily_comment_history 
         WHERE $where AND specific_features IS NOT NULL AND specific_features != '' 
         ORDER BY specific_features"
    );
    
    $distances = $wpdb->get_col(
        "SELECT DISTINCT Distance FROM daily_comment_history 
         WHERE $where AND Distance IS NOT NULL AND Distance != '' 
         ORDER BY Distance"
    );
    
    $classes = $wpdb->get_col(
        "SELECT DISTINCT class FROM daily_comment_history 
         WHERE $where AND class IS NOT NULL AND class != '' 
         ORDER BY class"
    );
    
    $goings = $wpdb->get_col(
        "SELECT DISTINCT going FROM daily_comment_history 
         WHERE $where AND going IS NOT NULL AND going != '' 
         ORDER BY going"
    );
    
    wp_send_json([
        'profiles' => $profiles,
        'general_features' => $general_features,
        'specific_features' => $specific_features,
        'distances' => $distances,
        'classes' => $classes,
        'goings' => $goings
    ]);
}
add_action('wp_ajax_get_horse_history_filter_options', 'get_horse_history_filter_options');
add_action('wp_ajax_nopriv_get_horse_history_filter_options', 'get_horse_history_filter_options');

// ==============================================
// AJAX HANDLER FOR FILTERED RACE HISTORY
// ==============================================

function get_filtered_horse_history() {
    global $wpdb;
    
    $runner_id = isset($_POST['runner_id']) ? intval($_POST['runner_id']) : 0;
    $horse_name = isset($_POST['horse_name']) ? sanitize_text_field($_POST['horse_name']) : '';
    
    if (!$runner_id && !$horse_name) {
        wp_send_json_error('Runner ID or horse name required');
        return;
    }
    
    $where = '1=1';
    
    if ($runner_id) {
        $where .= $wpdb->prepare(" AND runner_id = %d", $runner_id);
    } else {
        $where .= $wpdb->prepare(" AND name = %s", $horse_name);
    }
    
    // Apply filters
    if (!empty($_POST['profile'])) {
        $where .= $wpdb->prepare(" AND profile = %s", $_POST['profile']);
    }
    if (!empty($_POST['general_features'])) {
        $where .= $wpdb->prepare(" AND general_features = %s", $_POST['general_features']);
    }
    if (!empty($_POST['specific_features'])) {
        $where .= $wpdb->prepare(" AND specific_features = %s", $_POST['specific_features']);
    }
    if (!empty($_POST['distance'])) {
        $where .= $wpdb->prepare(" AND Distance = %s", $_POST['distance']);
    }
    if (!empty($_POST['class'])) {
        $where .= $wpdb->prepare(" AND class = %s", $_POST['class']);
    }
    if (!empty($_POST['going'])) {
        $where .= $wpdb->prepare(" AND going = %s", $_POST['going']);
    }
    if (!empty($_POST['sr_min']) && is_numeric($_POST['sr_min'])) {
        $where .= $wpdb->prepare(" AND speed_rating >= %d", intval($_POST['sr_min']));
    }
    if (!empty($_POST['sr_max']) && is_numeric($_POST['sr_max'])) {
        $where .= $wpdb->prepare(" AND speed_rating <= %d", intval($_POST['sr_max']));
    }
    
    $results = $wpdb->get_results(
        "SELECT 
            race_id, meeting_date, course, direction, profile, general_features, 
            specific_features, race_title, Distance as distance, going, class, 
            finish_position as position, Runner_Count as runners, name, race_type, 
            starting_price, official_rating, speed_rating, wt_speed_rating, 
            in_race_comment, days_since_ran, distance_beaten, value, 
            race_abbrev_name, jockey_name
         FROM daily_comment_history 
         WHERE $where 
         ORDER BY meeting_date DESC 
         LIMIT 50"
    );
    
    wp_send_json_success(['races' => $results]);
}
add_action('wp_ajax_get_filtered_horse_history', 'get_filtered_horse_history');
add_action('wp_ajax_nopriv_get_filtered_horse_history', 'get_filtered_horse_history');

}


// ==============================================
// HORSE HISTORY SHORTCODE
// ==============================================

function horse_history_shortcode($atts) {
    $atts = shortcode_atts(['horse_name' => '', 'runner_id' => ''], $atts);
    $horse_name = sanitize_text_field(urldecode($atts['horse_name']));
    $runner_id = bricks_decode_entity_id($atts['runner_id'], 'runner');

    // Fallback to query vars if attributes are missing
    if (!$runner_id) {
        $runner_id = bricks_decode_entity_id(get_query_var('runner_id'), 'runner');
        if (!$runner_id && !empty($_SERVER['REQUEST_URI'])) {
            if (preg_match('/horse-history\/([A-Za-z0-9_-]+)/', $_SERVER['REQUEST_URI'], $m)) {
                $runner_id = bricks_decode_entity_id($m[1], 'runner');
            }
        }
    }

    if (!$horse_name) {
        $q_horse = get_query_var('horse_name');
        if (!empty($q_horse)) {
            $horse_name = sanitize_text_field(urldecode($q_horse));
        }
    }

    if (!$horse_name && !$runner_id) {
        return '
        <div style="max-width:600px;margin:60px auto;text-align:center;padding:40px 30px;background:white;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
            <div style="font-size:56px;margin-bottom:16px;">🔍</div>
            <h2 style="color:#1e293b;font-size:22px;font-weight:700;margin:0 0 12px 0;">No Horse Specified</h2>
            <p style="color:#64748b;font-size:15px;line-height:1.6;margin:0 0 24px 0;">
                Please select a horse from a race card to view its history.
            </p>
            <a href="' . esc_url(home_url('/')) . '" 
               style="display:inline-block;background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);color:white;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;transition:all 0.2s ease;box-shadow:0 4px 12px rgba(59,130,246,0.3);">
                ← Back to Races
            </a>
        </div>';
    }

    // Get horse data
    $horse_info = null;
    $sp_info = null;
    if ($runner_id) {
        $horse_info = fh_get_horse_profile_by_runner_id($runner_id);
        $sp_info = fh_get_runner_snapshot_from_sp($runner_id);
    }

    if (!$horse_info) {
        if ($horse_name) {
            $horse_info = fh_get_horse_profile($horse_name);
        }
    }

    if (!$sp_info && $horse_name) {
        $sp_info = fh_get_runner_snapshot_from_sp_by_name($horse_name);
    }

    // Get race history
    $race_history = [];
    if ($runner_id) {
        $race_history = fh_get_race_history_by_runner_id($runner_id, 15);
    }

    if (!$horse_info && !$sp_info) {
        $display_id = esc_html($horse_name ?: ('Runner #' . $runner_id));
        return '
        <div style="max-width:600px;margin:60px auto;text-align:center;padding:40px 30px;background:white;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
            <div style="font-size:56px;margin-bottom:16px;">🐴</div>
            <h2 style="color:#1e293b;font-size:22px;font-weight:700;margin:0 0 12px 0;">Horse Profile Not Available</h2>
            <p style="color:#64748b;font-size:15px;line-height:1.6;margin:0 0 24px 0;">
                We couldn\'t find a profile for <strong style="color:#334155;">' . $display_id . '</strong>.<br>
                This horse may not have run recently or data may not yet be available.
            </p>
            <a href="' . esc_url(home_url('/')) . '" 
               style="display:inline-block;background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);color:white;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;transition:all 0.2s ease;box-shadow:0 4px 12px rgba(59,130,246,0.3);">
                ← Back to Races
            </a>
        </div>';
    }

    // Get display name
    $display_name = null;
    if ($sp_info && isset($sp_info->name) && $sp_info->name) {
        $display_name = $sp_info->name;
    } elseif ($horse_info && isset($horse_info->name) && $horse_info->name) {
        $display_name = $horse_info->name;
    } else {
        $display_name = $horse_name ?: ('Runner #' . $runner_id);
    }

    ob_start();
    ?>

<style>
     /* Updated table styles for more columns */
        .race-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            max-width: 100%;
            min-width: 1800px; /* Increased from 800px to accommodate more columns */
        }
        
        .race-table th,
        .race-table td {
            padding: 12px 8px; /* Slightly reduced padding for more columns */
            color: #334155;
            border-bottom: none;
            white-space: nowrap;
        }
        
        /* Specific column widths */
        .race-table th:nth-child(1), .race-table td:nth-child(1) { width: 90px; } /* Date */
        .race-table th:nth-child(2), .race-table td:nth-child(2) { width: 100px; } /* Course */
        .race-table th:nth-child(3), .race-table td:nth-child(3) { width: 80px; } /* Direction */
        .race-table th:nth-child(4), .race-table td:nth-child(4) { width: 80px; } /* Profile */
        .race-table th:nth-child(5), .race-table td:nth-child(5) { max-width: 200px; white-space: normal; } /* General Features */
        .race-table th:nth-child(6), .race-table td:nth-child(6) { max-width: 200px; white-space: normal; } /* Specific Features */
        .race-table th:nth-child(7), .race-table td:nth-child(7) { width: 80px; } /* Distance */
        .race-table th:nth-child(8), .race-table td:nth-child(8) { width: 100px; } /* Race Type */
        .race-table th:nth-child(9), .race-table td:nth-child(9) { width: 70px; text-align: center; } /* Dist Btn */
        .race-table th:nth-child(10), .race-table td:nth-child(10) { width: 60px; text-align: center; } /* Class */
        .race-table th:nth-child(11), .race-table td:nth-child(11) { max-width: 250px; white-space: normal; } /* Race */
        .race-table th:nth-child(12), .race-table td:nth-child(12) { width: 60px; text-align: center; } /* OR */
        .race-table th:nth-child(13), .race-table td:nth-child(13) { width: 70px; } /* Value */
        .race-table th:nth-child(14), .race-table td:nth-child(14) { width: 80px; } /* Ran */
        .race-table th:nth-child(15), .race-table td:nth-child(15) { width: 60px; text-align: center; } /* WT */
        .race-table th:nth-child(16), .race-table td:nth-child(16) { width: 120px; } /* Jockey */
        .race-table th:nth-child(17), .race-table td:nth-child(17) { width: 60px; text-align: center; } /* SR */
        .race-table th:nth-child(18), .race-table td:nth-child(18) { width: 80px; } /* Going */
        .race-table th:nth-child(19), .race-table td:nth-child(19) { width: 60px; text-align: center; } /* DSLR */
        .race-table th:nth-child(20), .race-table td:nth-child(20) { width: 70px; text-align: center; } /* Pos */
        
        /* Truncate long text with ellipsis */
        .truncate-text {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: help;
        }
        
        .truncate-text:hover {
            white-space: normal;
            overflow: visible;
        }

     .horse-history-container {
    background: #f8f9fa;
    min-height: 100vh;
    padding: 0;
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.horse-content-wrapper {
    max-width: 1800px; /* Increased from 1400px */
    margin: 0 auto;
    width: 100%;
    padding: 20px;
}

/* Add filter styles */
.race-history-filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px solid #e5e7eb;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-group label {
    font-size: 11px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.race-history-filters select,
.race-history-filters input[type="number"] {
    padding: 10px 12px;
    font-size: 14px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    transition: all 0.2s ease;
    outline: none;
}

.race-history-filters select:focus,
.race-history-filters input[type="number"]:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.filter-buttons {
    display: flex;
    gap: 12px;
    align-items: flex-end;
}

.history-header-filter {
    display: block;
    width: 100%;
    margin-top: 6px;
    padding: 6px 8px;
    font-size: 11px;
    font-weight: 500;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #ffffff;
    color: #374151;
}

.filter-button {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.filter-button-apply {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
}

.filter-button-apply:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
}

.filter-button-reset {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
}

.filter-button-reset:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
}

.race-history-section {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.1);
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
}

.race-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    width: 100%; /* Ensure full width */
}

.race-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    max-width: 100%;
    min-width: 2000px; /* Keeps the minimum width for all columns */
}
        
        .horse-header-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 16px;
            margin-bottom: 24px;
            padding: 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        .horse-header-top {
            background: rgba(0,0,0,0.2);
            padding: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .horse-title {
            color: #ffffff;
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 8px 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        
        .horse-subtitle {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .horse-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            padding: 24px;
        }
        
        .stat-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }
        
        .stat-icon {
            font-size: 24px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.8;
            margin-bottom: 4px;
            color: rgba(255,255,255,0.8);
        }
        
        .stat-value {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
        }
        
        .race-history-section {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: 0 auto;
        }
        
       .race-history-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 24px;
    border-bottom: 2px solid #dee2e6;
    width: 100%;
}
        
        .race-history-title {
            margin: 0 0 8px 0;
            color: #1e293b;
            font-size: 28px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .race-table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .race-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            max-width: 100%;
            min-width: 800px; /* Ensure minimum width for desktop */
        }
        
        .race-table thead {
            background: linear-gradient(180deg, #f8f9fa 0%, #f1f3f5 100%);
        }

        /* Keep race history header visible while scrolling long tables */
        .race-table thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: linear-gradient(180deg, #f8f9fa 0%, #f1f3f5 100%);
        }
        
        .race-table th {
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
        
        .race-row {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f1f3f5;
        }
        
        .race-row:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .race-row:nth-child(even) {
            background: #f8f9fa;
        }
        
        .race-row:nth-child(even):hover {
            background: #f1f3f5;
        }
        
        .race-table td {
            padding: 16px 12px;
            color: #334155;
            border-bottom: none;
        }
        
        .comment-row {
            background: rgba(59, 130, 246, 0.05) !important;
        }
        
        .comment-row:hover {
            background: rgba(59, 130, 246, 0.08) !important;
        }
        
        .comment-box {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
            padding: 16px;
            margin: 8px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .comment-label {
            color: #2563eb;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            margin-bottom: 6px;
            display: block;
        }
        
        .comment-text {
            color: #374151;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .position-1 { color: #10b981; font-weight: 800; }
        .position-2-3 { color: #f59e0b; font-weight: 700; }
        .position-other { color: #6b7280; }
        
        .speed-excellent {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .speed-good {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        .race-table.national-hunt .stall-column,
        .race-table.national-hunt .draw-bias-column {
            display: none;
        }
        
        .speed-average {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .no-data-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Mobile Card Layout for Race History */
        .race-card {
            display: none;
            background: white;
            border-radius: 12px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            border-left: 4px solid #3b82f6;
        }
        
        .race-card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .race-card-date {
            color: #3b82f6;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .race-card-course {
            color: #1e293b;
            font-weight: 600;
            font-size: 16px;
        }
        
        .race-card-body {
            padding: 16px;
        }
        
        .race-card-title {
            color: #1e293b;
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 12px;
            line-height: 1.4;
        }
        
        .race-card-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .race-card-stat {
            text-align: center;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .race-card-stat-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        
        .race-card-stat-value {
            font-weight: 700;
            font-size: 14px;
            color: #1e293b;
        }
        
        .race-card-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .race-card-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid #f1f3f5;
        }
        
        .race-card-detail-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }
        
        .race-card-detail-value {
            font-size: 12px;
            color: #1e293b;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .horse-history-container {
                padding: 0;
            }
            
            .horse-content-wrapper {
                padding: 12px;
            }
            
            .horse-header-card {
                border-radius: 12px;
                margin-bottom: 20px;
            }
            
            .horse-header-top {
                padding: 16px;
            }

            .horse-title {
                font-size: 20px;
            }
            
            .horse-subtitle {
                font-size: 12px;
            }

            .horse-stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                padding: 16px;
            }
            
            .stat-card {
                padding: 10px;
            }
            
            .stat-icon {
                font-size: 20px;
            }
            
            .stat-value {
                font-size: 14px;
            }
            
            .race-history-filters {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 16px;
            }
            
            .filter-group label {
                font-size: 10px;
            }
            
            .race-history-filters select,
            .race-history-filters input[type="number"] {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 12px;
            }
            
            .filter-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-button {
                width: 100%;
                padding: 12px;
            }

            .race-history-title {
                font-size: 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .race-history-header {
                padding: 16px;
            }
            
            /* Table wrapper - make scrollable on mobile */
            .race-table-wrapper {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .race-table {
                font-size: 11px;
                min-width: 1800px; /* Keep minimum width for all columns */
            }
            
            .race-table th,
            .race-table td {
                padding: 10px 8px;
            }
            
            .race-table th {
                font-size: 10px;
            }
            
            /* Show card layout on mobile as alternative */
            .race-card {
                display: block;
            }
        }

        @media (max-width: 480px) {
            .horse-content-wrapper {
                padding: 8px;
            }
            
            .horse-header-top {
                padding: 12px;
            }
            
            .horse-title {
                font-size: 18px;
            }
            
            .horse-stats-grid {
                grid-template-columns: 1fr;
                padding: 12px;
            }
            
            .race-history-filters {
                padding: 12px;
            }
            
            .race-history-header {
                padding: 12px;
            }
            
            .race-history-title {
                font-size: 18px;
            }
            
            .race-table {
                font-size: 10px;
            }
            
            .race-table th,
            .race-table td {
                padding: 8px 6px;
            }
            
            .race-card-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .race-card-details {
                grid-template-columns: 1fr;
            }
        }

        /* Sortable column styles */
.sortable-history {
    cursor: pointer;
    user-select: none;
    position: relative;
    padding-right: 20px !important;
    transition: background-color 0.2s ease;
}

.sortable-history:hover {
    background: #e5e7eb !important;
}

.sort-indicator {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 10px;
    color: #9ca3af;
}

.sortable-history.sorted-asc .sort-indicator::after {
    content: '▲';
    color: #3b82f6;
    font-weight: bold;
}

.sortable-history.sorted-desc .sort-indicator::after {
    content: '▼';
    color: #3b82f6;
    font-weight: bold;
}

.sortable-history:not(.sorted-asc):not(.sorted-desc) .sort-indicator::after {
    content: '⇅';
}

.sortable-history.active-sort {
    background: #dbeafe !important;
    color: #1e40af;
}
/* Place color coding */
.place-1 { 
    background: linear-gradient(135deg, #D5A500 0%, #F4C430 100%);
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(213, 165, 0, 0.4);
    display: inline-block;
}

.place-2 { 
    background: linear-gradient(135deg, #B7B7B7 0%, #D3D3D3 100%);
    color: #333;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(183, 183, 183, 0.4);
    display: inline-block;
}

.place-3 { 
    background: linear-gradient(135deg, #A17419 0%, #CD7F32 100%);
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(161, 116, 25, 0.4);
    display: inline-block;
}

.place-other {
    color: #6b7280;
    font-weight: 700;
    font-size: 14px;
}
/* Place row color coding - entire row backgrounds */
.race-row.place-1-row {
    background: linear-gradient(135deg, #FFF9E6 0%, #FFF4D1 100%) !important;
    border-left: 4px solid #D5A500 !important;
}

.race-row.place-1-row:hover {
    background: linear-gradient(135deg, #FFF4D1 0%, #FFEFBC 100%) !important;
}

/* Distance/going columns forced to neutral dark text regardless of row highlight */
.race-table .distance-cell,
.race-table .going-cell {
    color: #1f2937 !important;
    background: transparent !important;
}

.race-row.place-1-row .distance-cell,
.race-row.place-1-row .going-cell,
.race-row.place-2-row .distance-cell,
.race-row.place-2-row .going-cell,
.race-row.place-3-row .distance-cell,
.race-row.place-3-row .going-cell {
    color: #1f2937 !important;
}

/* Mobile detail values for distance/going stay neutral too */
.race-card-detail-value.distance-value,
.race-card-detail-value.going-value {
    color: #1f2937 !important;
}

/* Ensure Place badge stands out on place-1-row gold background - use dark background with gold text */
.race-row.place-1-row .place-badge-1 {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%) !important;
    color: #F4C430 !important;
    border: 2px solid #D5A500 !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4) !important;
}

/* Ensure SR badges are readable on place-1-row gold background */
.race-row.place-1-row .speed-excellent,
.race-row.place-1-row .speed-good,
.race-row.place-1-row .speed-average {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4) !important;
    border: 1px solid rgba(0, 0, 0, 0.2) !important;
}

.race-row.place-2-row {
    background: linear-gradient(135deg, #F5F5F5 0%, #ECECEC 100%) !important;
    border-left: 4px solid #B7B7B7 !important;
}

.race-row.place-2-row:hover {
    background: linear-gradient(135deg, #ECECEC 0%, #E3E3E3 100%) !important;
}

.race-row.place-3-row {
    background: linear-gradient(135deg, #FFF5E6 0%, #FFEFD1 100%) !important;
    border-left: 4px solid #A17419 !important;
}

.race-row.place-3-row:hover {
    background: linear-gradient(135deg, #FFEFD1 0%, #FFE9BC 100%) !important;
}


/* Place badge styling for the Place column */
.place-badge-1 { 
    background: linear-gradient(135deg, #D5A500 0%, #F4C430 100%);
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(213, 165, 0, 0.4);
    display: inline-block;
}

.place-badge-2 { 
    background: linear-gradient(135deg, #B7B7B7 0%, #D3D3D3 100%);
    color: #333;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(183, 183, 183, 0.4);
    display: inline-block;
}

.place-badge-3 { 
    background: linear-gradient(135deg, #A17419 0%, #CD7F32 100%);
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(161, 116, 25, 0.4);
    display: inline-block;
}

.place-badge-other {
    color: #6b7280;
    font-weight: 700;
    font-size: 14px;
}

/* Comment rows should inherit the parent row color */
.comment-row.place-1-comment {
    background: linear-gradient(135deg, #FFF9E6 0%, #FFF4D1 100%) !important;
    border-left: 4px solid #D5A500 !important;
}

.comment-row.place-2-comment {
    background: linear-gradient(135deg, #F5F5F5 0%, #ECECEC 100%) !important;
    border-left: 4px solid #B7B7B7 !important;
}

.comment-row.place-3-comment {
    background: linear-gradient(135deg, #FFF5E6 0%, #FFEFD1 100%) !important;
    border-left: 4px solid #A17419 !important;
}

.race-card.place-1-card {
    border-left: 6px solid #D5A500;
    background: linear-gradient(135deg, #FFF9E6 0%, #FFF4D1 100%);
}

.race-card.place-2-card {
    border-left: 6px solid #B7B7B7;
    background: linear-gradient(135deg, #F5F5F5 0%, #ECECEC 100%);
}

.race-card.place-3-card {
    border-left: 6px solid #A17419;
    background: linear-gradient(135deg, #FFF5E6 0%, #FFEFD1 100%);
}

    </style>


    <div class="horse-history-container">
      <div class="horse-content-wrapper">
        <!-- Horse Header Card -->
        <div class="horse-header-card">
            <div class="horse-header-top">
                <h1 class="horse-title">🐎 <?php echo esc_html($display_name); ?></h1>
                <p class="horse-subtitle">Complete horse profile and racing history</p>
                <?php
                echo bricks_tracker_render_horse_widget(
                    $display_name,
                    [
                        'race_id' => '',
                        'race_date' => '',
                        'race_time' => '',
                        'course' => ''
                    ],
                    [
                        'show_latest_flag' => false,
                        'wrapper_class' => 'tracker-horse-header'
                    ]
                );
                ?>
            </div>
            
          <div class="horse-stats-grid">
    <div class="stat-card">
        <div class="stat-icon">🎂</div>
        <div class="stat-content">
            <div class="stat-label">Age</div>
            <div class="stat-value"><?php echo esc_html(($sp_info && isset($sp_info->age)) ? $sp_info->age : ($horse_info->age_range ?? 'N/A')); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">🎨</div>
        <div class="stat-content">
            <div class="stat-label">Colour</div>
            <div class="stat-value"><?php echo esc_html(($sp_info && isset($sp_info->colour)) ? $sp_info->colour : 'N/A'); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">⚧</div>
        <div class="stat-content">
            <div class="stat-label">Sex</div>
            <div class="stat-value"><?php echo esc_html(($sp_info && isset($sp_info->gender)) ? $sp_info->gender : 'N/A'); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👨‍👦</div>
        <div class="stat-content">
            <div class="stat-label">Sire</div>
            <div class="stat-value"><?php echo esc_html(($sp_info && isset($sp_info->sire_name)) ? $sp_info->sire_name : 'N/A'); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👩‍👦</div>
        <div class="stat-content">
            <div class="stat-label">Dam</div>
            <div class="stat-value"><?php echo esc_html(($sp_info && isset($sp_info->dam_name)) ? $sp_info->dam_name : 'N/A'); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👤</div>
        <div class="stat-content">
            <div class="stat-label">Owner</div>
            <div class="stat-value"><?php echo esc_html(($sp_info && isset($sp_info->owner_name)) ? $sp_info->owner_name : 'N/A'); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">🏇</div>
        <div class="stat-content">
            <div class="stat-label">Jockey</div>
            <div class="stat-value"><?php echo esc_html(($sp_info && isset($sp_info->jockey_name)) ? $sp_info->jockey_name : 'N/A'); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-content">
            <div class="stat-label">Official Rating</div>
            <div class="stat-value"><?php echo esc_html($horse_info->official_rating ?? 'N/A'); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👨‍🏫</div>
        <div class="stat-content">
            <div class="stat-label">Trainer</div>
            <div class="stat-value"><?php echo esc_html(($sp_info && isset($sp_info->trainer_name)) ? $sp_info->trainer_name : 'N/A'); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">📝</div>
        <div class="stat-content">
            <div class="stat-label">Form</div>
            <div class="stat-value" style="font-family:monospace;"><?php echo esc_html(($sp_info && isset($sp_info->form_figures)) ? $sp_info->form_figures : 'N/A'); ?></div>
        </div>
    </div>
</div>

        </div>
        </div>

        <!-- Race History Section -->
 <!-- Race History Section -->
<?php if (!empty($race_history)): ?>
<div class="race-history-section" style="max-width:100% !important;width:100% !important;">
    <div class="race-history-header">
        <h2 class="race-history-title">
            <span>🏇</span>
            Recent Race History
            <span style="background:rgba(59,130,246,0.2);color:#60a5fa;padding:4px 12px;border-radius:8px;font-size:14px;margin-left:auto;">
                <span id="race-count"><?php echo count($race_history); ?></span> races
            </span>
        </h2>
    </div>
    
    <!-- Filter Section -->
    <div class="race-history-filters" style="width:100% !important;">
        <div class="filter-group">
            <label for="filter-profile">Profile:</label>
            <select id="filter-profile" class="race-filter">
                <option value="">All Profiles</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="filter-general-features">General Features:</label>
            <select id="filter-general-features" class="race-filter">
                <option value="">All Features</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="filter-specific-features">Specific Features:</label>
            <select id="filter-specific-features" class="race-filter">
                <option value="">All Specific Features</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="filter-distance">Distance:</label>
            <select id="filter-distance" class="race-filter">
                <option value="">All Distances</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="filter-class">Class:</label>
            <select id="filter-class" class="race-filter">
                <option value="">All Classes</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="filter-going">Going:</label>
            <select id="filter-going" class="race-filter">
                <option value="">All Going</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="filter-sr-min">SR Min:</label>
            <input type="number" id="filter-sr-min" class="race-filter" placeholder="Min" min="0" max="100">
        </div>
        
        <div class="filter-group">
            <label for="filter-sr-max">SR Max:</label>
            <input type="number" id="filter-sr-max" class="race-filter" placeholder="Max" min="0" max="100">
        </div>
        
        <div class="filter-buttons">
            <button type="button" class="filter-button filter-button-apply" id="apply-filters">Apply</button>
            <button type="button" class="filter-button filter-button-reset" id="reset-filters">Reset</button>
        </div>
    </div>
    
    <!-- Desktop Table View -->
   <!-- Desktop Table View -->
<div class="race-table-wrapper" id="race-history-table-container">
    <table class="race-table">
        <thead>
    <tr>
        <th class="sortable-history" data-sort="date">Date <span class="sort-indicator"></span></th>
        <th class="sortable-history" data-sort="course">Course <span class="sort-indicator"></span></th>
        <th class="sortable-history" data-sort="direction">Direction <span class="sort-indicator"></span></th>
        <th class="sortable-history" data-sort="profile">Profile <span class="sort-indicator"></span></th>
        <th>General Features</th>
        <th>
            Specific Features
            <select id="filter-specific-features-header" class="history-header-filter" title="Filter races by specific course features (e.g. Uphill Finish)">
                <option value="">All Specific Features</option>
            </select>
        </th>
        <th class="sortable-history" data-sort="distance">Distance <span class="sort-indicator"></span></th>
        <th class="sortable-history" data-sort="race_type">Race Type <span class="sort-indicator"></span></th>
        <th class="sortable-history" data-sort="distance_beaten" style="text-align:center;">Dist Btn <span class="sort-indicator"></span></th>
        <th class="sortable-history" data-sort="class" style="text-align:center;">Class <span class="sort-indicator"></span></th>
        <th>Race</th>
        <th class="sortable-history" data-sort="official_rating" style="text-align:center;">OR <span class="sort-indicator"></span></th>
        <th class="sortable-history" data-sort="value">Value <span class="sort-indicator"></span></th>
        <th>Ran</th>
        <th class="sortable-history" data-sort="wt_speed_rating" style="text-align:center;" title="Weighted Speed Rating (wt_speed_rating), not carrying weight.">WT SR <span class="sort-indicator"></span></th>
        <th class="sortable-history" data-sort="jockey_name">Jockey <span class="sort-indicator"></span></th>
        <th class="sortable-history" data-sort="speed_rating" style="text-align:center;">SR <span class="sort-indicator"></span></th>
        <th class="sortable-history" data-sort="going">Going <span class="sort-indicator"></span></th>
        <th class="sortable-history" data-sort="days_since_ran" style="text-align:center;">DSLR <span class="sort-indicator"></span></th>
       <th class="sortable-history" data-sort="position" style="text-align:center;">Place <span class="sort-indicator"></span></th>

    </tr>
</thead>

        <tbody>
            <?php foreach ($race_history as $index => $race): ?>
           <?php 
$pos = intval($race->position);
$row_class = 'race-row';
$comment_class = 'comment-row';
if ($pos === 1) {
    $row_class .= ' place-1-row';
    $comment_class .= ' place-1-comment';
} elseif ($pos === 2) {
    $row_class .= ' place-2-row';
    $comment_class .= ' place-2-comment';
} elseif ($pos === 3) {
    $row_class .= ' place-3-row';
    $comment_class .= ' place-3-comment';
}
?>
<tr class="<?php echo $row_class; ?>">
                <!-- Date -->
                <td style="font-weight:600;color:#60a5fa;">
                    <?php echo esc_html($race->meeting_date ?: 'N/A'); ?>
                </td>
                
                <!-- Course -->
                <td style="font-weight:600;">
                    <?php echo esc_html($race->course ?: 'N/A'); ?>
                </td>
                
                <!-- Direction -->
                <td style="color:#6b7280;font-size:12px;">
                    <?php echo esc_html($race->direction ?: '-'); ?>
                </td>
                
                <!-- Profile -->
                <td style="color:#6b7280;font-size:12px;">
                    <?php echo esc_html($race->profile ?: '-'); ?>
                </td>
                
                <!-- General Features -->
                <td>
                    <?php if ($race->general_features): ?>
                        <div class="truncate-text" title="<?php echo esc_attr($race->general_features); ?>">
                            <?php echo esc_html($race->general_features); ?>
                        </div>
                    <?php else: ?>
                        <span style="color:#9ca3af;">-</span>
                    <?php endif; ?>
                </td>
                
                <!-- Specific Features -->
                <td>
                    <?php if ($race->specific_features): ?>
                        <div class="truncate-text" title="<?php echo esc_attr($race->specific_features); ?>">
                            <?php echo esc_html($race->specific_features); ?>
                        </div>
                    <?php else: ?>
                        <span style="color:#9ca3af;">-</span>
                    <?php endif; ?>
                </td>
                
                <!-- Distance -->
                <td class="distance-cell" style="color:#1f2937;font-weight:600;">
                    <?php echo esc_html($race->distance ?: 'N/A'); ?>
                </td>
                
                <!-- Race Type -->
                <td style="color:#6b7280;font-size:12px;">
                    <?php echo esc_html($race->race_type ?: '-'); ?>
                </td>
                
                <!-- Distance Beaten -->
                <td style="text-align:center;font-weight:600;color:#ef4444;">
                    <?php 
                    if ($race->distance_beaten && $race->distance_beaten > 0) {
                        echo esc_html($race->distance_beaten . 'L');
                    } else {
                        echo '<span style="color:#9ca3af;">-</span>';
                    }
                    ?>
                </td>
                
                <!-- Class -->
                <td style="text-align:center;">
                    <span style="display:inline-block;padding:4px 8px;border-radius:6px;background:#dbeafe;color:#1e40af;font-weight:600;font-size:11px;">
                        <?php echo esc_html($race->class ?: '-'); ?>
                    </span>
                </td>
                
                <!-- Race Title -->
                <td>
                    <?php if ($race->race_id && $race->race_id > 0): ?>
                        <a href="<?php echo esc_url(bricks_race_comment_url($race->race_id)); ?>" 
                           style="color:#2563eb;text-decoration:none;font-weight:600;transition:all 0.2s ease;"
                           onmouseover="this.style.color='#1d4ed8';this.style.textDecoration='underline'"
                           onmouseout="this.style.color='#2563eb';this.style.textDecoration='none'"
                           title="View race comments and analysis">
                            🏁 <?php echo esc_html($race->race_title ?: 'N/A'); ?>
                        </a>
                    <?php else: ?>
                        <div style="font-weight:600;"><?php echo esc_html($race->race_title ?: 'N/A'); ?></div>
                    <?php endif; ?>
                </td>
                
                <!-- Official Rating -->
                <td style="text-align:center;font-weight:700;color:#34d399;">
                    <?php echo esc_html($race->official_rating ?: '-'); ?>
                </td>
                
                <!-- Value -->
                <td style="font-weight:600;color:#8b5cf6;">
                    <?php echo esc_html($race->value ?: '-'); ?>
                </td>
                
                <!-- Ran (Runner_Count) -->
                <td style="font-size:12px;color:#6b7280;">
                    <?php echo esc_html($race->runners ?: '-'); ?>
                </td>
                
                <!-- WT Speed Rating -->
                <td style="text-align:center;">
                    <?php if ($race->wt_speed_rating): ?>
                        <?php 
                        $wt_sr = intval($race->wt_speed_rating);
                        $wt_speed_class = '';
                        if ($wt_sr >= 80) $wt_speed_class = 'speed-excellent';
                        elseif ($wt_sr >= 70) $wt_speed_class = 'speed-good';
                        elseif ($wt_sr >= 60) $wt_speed_class = 'speed-average';
                        ?>
                        <span class="<?php echo $wt_speed_class; ?>">
                            <?php echo esc_html($race->wt_speed_rating); ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#9ca3af;">-</span>
                    <?php endif; ?>
                </td>
                
                <!-- Jockey -->
                <td style="font-weight:500;color:#374151;font-size:12px;">
                    <?php echo esc_html($race->jockey_name ?: '-'); ?>
                </td>
                
                <!-- Speed Rating -->
                <td style="text-align:center;">
                    <?php if ($race->speed_rating): ?>
                        <?php 
                        $sr = intval($race->speed_rating);
                        $speed_class = '';
                        if ($sr >= 80) $speed_class = 'speed-excellent';
                        elseif ($sr >= 70) $speed_class = 'speed-good';
                        elseif ($sr >= 60) $speed_class = 'speed-average';
                        ?>
                        <span class="<?php echo $speed_class; ?>">
                            <?php echo esc_html($race->speed_rating); ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#9ca3af;">-</span>
                    <?php endif; ?>
                </td>
                
                <!-- Going -->
                <td class="going-cell" style="color:#1f2937;font-weight:600;font-size:12px;">
                    <?php 
                    $going = $race->going ?: 'N/A';
                    echo esc_html($going);
                    ?>
                </td>
                
                <!-- DSLR (Days Since Last Ran) -->
                <td style="text-align:center;font-weight:600;color:#6366f1;">
                    <?php echo esc_html(isset($race->days_since_ran) && $race->days_since_ran !== '' && $race->days_since_ran !== null ? $race->days_since_ran : '-'); ?>
                </td>
                
<!-- Place -->
<td style="text-align:center;font-weight:bold;">
    <?php 
    $pos = intval($race->position);
    $badge_class = '';
    if ($pos === 1) $badge_class = 'place-badge-1';
    elseif ($pos === 2) $badge_class = 'place-badge-2';
    elseif ($pos === 3) $badge_class = 'place-badge-3';
    else $badge_class = 'place-badge-other';
    
    // Extract just the position number (e.g., "3" from "3/15")
    $position_display = $race->position ?: 'N/A';
    if (strpos($position_display, '/') !== false) {
        $position_display = explode('/', $position_display)[0];
    }
    ?>
    <span class="<?php echo $badge_class; ?>">
        <?php echo esc_html($position_display); ?>
    </span>
</td>


            </tr>
            
            <!-- Comment Row -->
            <?php 
            $comment = '';
            if ($race->in_race_comment) {
                $comment = $race->in_race_comment;
            }
            ?>
            
            <!-- Comment Row -->
<tr class="<?php echo $comment_class; ?>">
    <td colspan="20" style="padding:0 12px 16px 12px;">

                    <?php if ($comment): ?>
                        <div class="comment-box">
                            <span class="comment-label">Race Comment:</span>
                            <div class="comment-text"><?php echo esc_html($comment); ?></div>
                        </div>
                    <?php else: ?>
                        <div style="background:rgba(107,114,128,0.1);border-left:4px solid #6b7280;padding:12px 16px;margin:8px 0;border-radius:0 8px 8px 0;">
                            <span style="color:#6b7280;font-size:12px;font-style:italic;">No race comment available</span>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

    
   <!-- Mobile Card View -->
<?php foreach ($race_history as $index => $race): ?>
<div class="race-card">
    <div class="race-card-header">
        <div class="race-card-date"><?php echo esc_html(date('d M Y', strtotime($race->meeting_date))); ?></div>
        <div class="race-card-course">
            <?php echo esc_html($race->course ?: 'N/A'); ?>
            <?php if ($race->direction): ?>
                <span style="font-size:11px;color:#6b7280;margin-left:8px;">
                    <?php echo esc_html($race->direction); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="race-card-body">
        <div class="race-card-title">
            <?php if ($race->race_id && $race->race_id > 0): ?>
                <a href="<?php echo esc_url(bricks_race_comment_url($race->race_id)); ?>" 
                   style="color:#2563eb;text-decoration:none;">
                    🏁 <?php echo esc_html($race->race_title ?: 'N/A'); ?>
                </a>
            <?php else: ?>
                🏁 <?php echo esc_html($race->race_title ?: 'N/A'); ?>
            <?php endif; ?>
        </div>
        
        <?php if ($race->race_type): ?>
            <div style="font-size:11px;color:#6b7280;margin-bottom:12px;">
                <?php echo esc_html($race->race_type); ?>
            </div>
        <?php endif; ?>
        
        <div class="race-card-stats">
            <div class="race-card-stat">
                <div class="race-card-stat-label">Pos</div>
                <div class="race-card-stat-value">
                    <?php 
                    $pos = intval($race->position);
                    $card_class = 'race-card';
                    if ($pos === 1) $card_class .= ' place-1-card';
                    elseif ($pos === 2) $card_class .= ' place-2-card';
                    elseif ($pos === 3) $card_class .= ' place-3-card';
                    
                    $position_display = $race->position ?: 'N/A';
                    if (strpos($position_display, '/') !== false) {
                        $position_display = explode('/', $position_display)[0];
                    }
                    ?>
                    <span class="<?php 
                        if ($pos === 1) echo 'place-badge-1';
                        elseif ($pos === 2) echo 'place-badge-2';
                        elseif ($pos === 3) echo 'place-badge-3';
                        else echo 'place-badge-other';
                    ?>">
                        <?php echo esc_html($position_display); ?>
                    </span>
                </div>
            </div>

            
            <div class="race-card-stat">
                <div class="race-card-stat-label">OR</div>
                <div class="race-card-stat-value" style="color:#059669;">
                    <?php echo esc_html($race->official_rating ?: '-'); ?>
                </div>
            </div>
            
            <div class="race-card-stat">
                <div class="race-card-stat-label">SR</div>
                <div class="race-card-stat-value">
                    <?php if ($race->speed_rating): ?>
                        <?php 
                        $sr = intval($race->speed_rating);
                        $speed_class = '';
                        if ($sr >= 80) $speed_class = 'speed-excellent';
                        elseif ($sr >= 70) $speed_class = 'speed-good';
                        elseif ($sr >= 60) $speed_class = 'speed-average';
                        ?>
                        <span class="<?php echo $speed_class; ?>" style="padding:2px 6px;border-radius:4px;font-size:11px;">
                            <?php echo esc_html($race->speed_rating); ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#9ca3af;">-</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="race-card-details">
            <div class="race-card-detail">
                <span class="race-card-detail-label">Distance:</span>
                <span class="race-card-detail-value distance-value"><?php echo esc_html($race->distance ?: 'N/A'); ?></span>
            </div>
            
            <div class="race-card-detail">
                <span class="race-card-detail-label">Going:</span>
                <span class="race-card-detail-value going-value"><?php echo esc_html($race->going ?: 'N/A'); ?></span>
            </div>
            
            <div class="race-card-detail">
                <span class="race-card-detail-label">Class:</span>
                <span class="race-card-detail-value"><?php echo esc_html($race->class ?: 'N/A'); ?></span>
            </div>
            
            <div class="race-card-detail">
                <span class="race-card-detail-label">Jockey:</span>
                <span class="race-card-detail-value"><?php echo esc_html($race->jockey_name ?: '-'); ?></span>
            </div>
            
            <?php if ($race->wt_speed_rating): ?>
            <div class="race-card-detail">
                <span class="race-card-detail-label" title="Weighted Speed Rating (wt_speed_rating), not carrying weight.">WT SR:</span>
                <span class="race-card-detail-value"><?php echo esc_html($race->wt_speed_rating); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($race->distance_beaten && $race->distance_beaten > 0): ?>
            <div class="race-card-detail">
                <span class="race-card-detail-label">Beaten:</span>
                <span class="race-card-detail-value"><?php echo esc_html($race->distance_beaten . 'L'); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($race->value): ?>
            <div class="race-card-detail">
                <span class="race-card-detail-label">Value:</span>
                <span class="race-card-detail-value"><?php echo esc_html($race->value); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($race->general_features): ?>
            <div style="background:#f8f9fa;padding:12px;border-radius:6px;margin-top:12px;">
                <div style="font-size:10px;color:#6b7280;text-transform:uppercase;margin-bottom:4px;font-weight:700;">General Features</div>
                <div style="font-size:12px;color:#374151;"><?php echo esc_html($race->general_features); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($race->specific_features): ?>
            <div style="background:#f8f9fa;padding:12px;border-radius:6px;margin-top:8px;">
                <div style="font-size:10px;color:#6b7280;text-transform:uppercase;margin-bottom:4px;font-weight:700;">Specific Features</div>
                <div style="font-size:12px;color:#374151;"><?php echo esc_html($race->specific_features); ?></div>
            </div>
        <?php endif; ?>
        
        <?php 
        $comment = '';
        if ($race->in_race_comment) {
            $comment = $race->in_race_comment;
        }
        ?>
        
        <?php if ($comment): ?>
            <div class="comment-box" style="margin-top:12px;">
                <span class="comment-label">Race Comment:</span>
                <div class="comment-text"><?php echo esc_html($comment); ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<script>
(function() {
    // Wait for jQuery to be available
    function initHorseHistoryFilters() {
        if (typeof jQuery === 'undefined') {
            console.log('jQuery not ready, waiting...');
            setTimeout(initHorseHistoryFilters, 100);
            return;
        }
        
        console.log('jQuery loaded, initializing filters...');
        
        jQuery(document).ready(function($) {
            // Sorting functionality for race history table
            let currentSortColumn = '';
            let currentSortDirection = 'asc';

            // Use event delegation for dynamically loaded content
            $(document).on('click', '.sortable-history', function() {
                const column = $(this).data('sort');
                
                // Toggle direction if clicking the same column
                if (currentSortColumn === column) {
                    currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSortColumn = column;
                    currentSortDirection = 'asc';
                }
                
                // Update visual indicators
                $('.sortable-history').removeClass('sorted-asc sorted-desc active-sort');
                $(this).addClass('sorted-' + currentSortDirection + ' active-sort');
                
                // Sort the table
                sortRaceHistoryTable(column, currentSortDirection);
            });

            function sortRaceHistoryTable(column, direction) {
                const tbody = $('#race-history-table-container tbody');
                const rows = tbody.find('tr.race-row').get();
                
                rows.sort(function(a, b) {
                    let aVal, bVal;
                    
                    // Get values based on column
                    switch(column) {
                        case 'date':
                            aVal = new Date($(a).find('td:eq(0)').text());
                            bVal = new Date($(b).find('td:eq(0)').text());
                            break;
                        case 'course':
                            aVal = $(a).find('td:eq(1)').text().toLowerCase();
                            bVal = $(b).find('td:eq(1)').text().toLowerCase();
                            break;
                        case 'direction':
                            aVal = $(a).find('td:eq(2)').text().toLowerCase();
                            bVal = $(b).find('td:eq(2)').text().toLowerCase();
                            break;
                        case 'profile':
                            aVal = $(a).find('td:eq(3)').text().toLowerCase();
                            bVal = $(b).find('td:eq(3)').text().toLowerCase();
                            break;
                        case 'distance':
                            // Extract numeric value from distance string
                            const distA = $(a).find('td:eq(6)').text();
                            const distB = $(b).find('td:eq(6)').text();
                            aVal = parseFloat(distA.replace(/[^0-9.]/g, '')) || 0;
                            bVal = parseFloat(distB.replace(/[^0-9.]/g, '')) || 0;
                            break;
                        case 'race_type':
                            aVal = $(a).find('td:eq(7)').text().toLowerCase();
                            bVal = $(b).find('td:eq(7)').text().toLowerCase();
                            break;
                        case 'distance_beaten':
                            aVal = parseFloat($(a).find('td:eq(8)').text().replace('L', '').replace('-', '0')) || 0;
                            bVal = parseFloat($(b).find('td:eq(8)').text().replace('L', '').replace('-', '0')) || 0;
                            break;
                        case 'class':
                            aVal = parseInt($(a).find('td:eq(9)').text().replace(/[^0-9]/g, '')) || 999;
                            bVal = parseInt($(b).find('td:eq(9)').text().replace(/[^0-9]/g, '')) || 999;
                            break;
                        case 'official_rating':
                            aVal = parseInt($(a).find('td:eq(11)').text().replace('-', '0')) || 0;
                            bVal = parseInt($(b).find('td:eq(11)').text().replace('-', '0')) || 0;
                            break;
                        case 'value':
                            aVal = $(a).find('td:eq(12)').text().toLowerCase();
                            bVal = $(b).find('td:eq(12)').text().toLowerCase();
                            break;
                        case 'wt_speed_rating':
                            aVal = parseInt($(a).find('td:eq(14)').text().replace('-', '0')) || 0;
                            bVal = parseInt($(b).find('td:eq(14)').text().replace('-', '0')) || 0;
                            break;
                        case 'jockey_name':
                            aVal = $(a).find('td:eq(15)').text().toLowerCase();
                            bVal = $(b).find('td:eq(15)').text().toLowerCase();
                            break;
                        case 'speed_rating':
                            aVal = parseInt($(a).find('td:eq(16)').text().replace('-', '0')) || 0;
                            bVal = parseInt($(b).find('td:eq(16)').text().replace('-', '0')) || 0;
                            break;
                        case 'going':
                            aVal = $(a).find('td:eq(17)').text().toLowerCase();
                            bVal = $(b).find('td:eq(17)').text().toLowerCase();
                            break;
                        case 'position':
                            aVal = parseInt($(a).find('td:eq(18)').text().split('/')[0].replace(/[^0-9]/g, '')) || 999;
                            bVal = parseInt($(b).find('td:eq(18)').text().split('/')[0].replace(/[^0-9]/g, '')) || 999;
                            break;
                        default:
                            return 0;
                    }
                    
                    // Compare values
                    if (aVal < bVal) return direction === 'asc' ? -1 : 1;
                    if (aVal > bVal) return direction === 'asc' ? 1 : -1;
                    return 0;
                });
                
                // Rebuild table with sorted rows and their comment rows
                $.each(rows, function(index, row) {
                    const commentRow = $(row).next('.comment-row');
                    tbody.append(row);
                    if (commentRow.length) {
                        tbody.append(commentRow);
                    }
                });
            }

            const runnerId = <?php echo $runner_id ? $runner_id : 0; ?>;
            const horseName = '<?php echo esc_js($horse_name); ?>';
            
            console.log('Horse History Filters - Runner ID:', runnerId, 'Horse Name:', horseName);
            
            // Load filter options
            function loadFilterOptions() {
                console.log('Loading filter options...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'get_horse_history_filter_options',
                        runner_id: runnerId,
                        horse_name: horseName
                    },
                    success: function(response) {
                        console.log('Filter options response:', response);
                        
                        if (response.profiles) {
                            updateSelect('#filter-profile', response.profiles);
                        }
                        if (response.general_features) {
                            updateSelect('#filter-general-features', response.general_features);
                        }
                        if (response.specific_features) {
                            updateSelect('#filter-specific-features', response.specific_features);
                            updateSelect('#filter-specific-features-header', response.specific_features);
                            syncSpecificFeatureFilters('#filter-specific-features');
                        }
                        if (response.distances) {
                            updateSelect('#filter-distance', response.distances);
                        }
                        if (response.classes) {
                            updateSelect('#filter-class', response.classes);
                        }
                        if (response.goings) {
                            updateSelect('#filter-going', response.goings);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Filter options error:', status, error);
                    }
                });
            }
            
            function updateSelect(selector, options) {
                const select = $(selector);
                if (!select.length) return;
                const currentValue = select.val();
                const firstOption = select.find('option:first').text();
                const sortedOptions = (options || [])
                    .filter(function(option) { return option !== null && option !== undefined && option !== ''; })
                    .slice()
                    .sort(function(a, b) {
                        return String(a).localeCompare(String(b), undefined, { sensitivity: 'base' });
                    });
                
                select.empty().append('<option value="">' + firstOption + '</option>');
                
                sortedOptions.forEach(function(option) {
                    select.append('<option value="' + option + '">' + option + '</option>');
                });
                
                if (currentValue && sortedOptions.includes(currentValue)) {
                    select.val(currentValue);
                }
            }

            function syncSpecificFeatureFilters(sourceSelector) {
                const sourceVal = $(sourceSelector).val() || '';
                if (sourceSelector === '#filter-specific-features') {
                    $('#filter-specific-features-header').val(sourceVal);
                } else {
                    $('#filter-specific-features').val(sourceVal);
                }
            }

            function encodeRaceCommentId(id) {
                const numericId = parseInt(id, 10);
                if (!numericId || numericId <= 0) {
                    return '';
                }
                const encodedNum = (numericId * 41) + 10009;
                return 'c_' + encodedNum.toString(36);
            }
            
            // Load race history
            function loadRaceHistory() {
                const filters = {
                    action: 'get_filtered_horse_history',
                    runner_id: runnerId,
                    horse_name: horseName,
                    profile: $('#filter-profile').val(),
                    general_features: $('#filter-general-features').val(),
                    specific_features: ($('#filter-specific-features-header').val() || $('#filter-specific-features').val()),
                    distance: $('#filter-distance').val(),
                    class: $('#filter-class').val(),
                    going: $('#filter-going').val(),
                    sr_min: $('#filter-sr-min').val(),
                    sr_max: $('#filter-sr-max').val()
                };
                
                console.log('Loading race history with filters:', filters);
                
                $('#race-history-table-container').html('<div style="text-align:center;padding:40px;color:#6b7280;">Loading...</div>');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: filters,
                    success: function(response) {
                        console.log('Race history response:', response);
                        
                        if (response.success && response.data.races) {
                            renderRaceTable(response.data.races);
                            $('#race-count').text(response.data.races.length);
                            
                            // Reapply current sort if any
                            if (currentSortColumn) {
                                $('.sortable-history[data-sort="' + currentSortColumn + '"]')
                                    .addClass('sorted-' + currentSortDirection + ' active-sort');
                            }
                        } else {
                            $('#race-history-table-container').html('<div style="text-align:center;padding:40px;color:#6b7280;">No races found</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Race history error:', status, error);
                        $('#race-history-table-container').html('<div style="text-align:center;padding:40px;color:red;">Error loading races</div>');
                    }
                });
            }
            
            function renderRaceTable(races) {
                let html = '<table class="race-table"><thead><tr>';
                html += '<th class="sortable-history" data-sort="date">Date <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="course">Course <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="direction">Direction <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="profile">Profile <span class="sort-indicator"></span></th>';
                html += '<th>General Features</th>';
                html += '<th>Specific Features <select id="filter-specific-features-header" class="history-header-filter" title="Filter races by specific course features (e.g. Uphill Finish)"><option value="">All Specific Features</option></select></th>';
                html += '<th class="sortable-history" data-sort="distance">Distance <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="race_type">Race Type <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="distance_beaten" style="text-align:center;">Dist Btn <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="class" style="text-align:center;">Class <span class="sort-indicator"></span></th>';
                html += '<th>Race</th>';
                html += '<th class="sortable-history" data-sort="official_rating" style="text-align:center;">OR <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="value">Value <span class="sort-indicator"></span></th>';
                html += '<th>Ran</th>';
                html += '<th class="sortable-history" data-sort="wt_speed_rating" style="text-align:center;" title="Weighted Speed Rating (wt_speed_rating), not carrying weight.">WT SR <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="jockey_name">Jockey <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="speed_rating" style="text-align:center;">SR <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="going">Going <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="days_since_ran" style="text-align:center;">DSLR <span class="sort-indicator"></span></th>';
                html += '<th class="sortable-history" data-sort="position" style="text-align:center;">Pos <span class="sort-indicator"></span></th>';
                html += '</tr></thead><tbody>';
                
                races.forEach(function(race, index) {

                    const rowBg = index % 2 === 0 ? '#ffffff' : '#f9fafb';
                    const borderColor = '#e5e7eb';

                    const pos = parseInt(race.position);
    
    // Determine row classes
    let rowClass = 'race-row';
    let commentClass = 'comment-row';
    let badgeClass = 'place-badge-other';
    
    if (pos === 1) {
        rowClass += ' place-1-row';
        commentClass += ' place-1-comment';
        badgeClass = 'place-badge-1';
    } else if (pos === 2) {
        rowClass += ' place-2-row';
        commentClass += ' place-2-comment';
        badgeClass = 'place-badge-2';
    } else if (pos === 3) {
        rowClass += ' place-3-row';
        commentClass += ' place-3-comment';
        badgeClass = 'place-badge-3';
    }
                    
                    // Main race row
                    html += '<tr class="' + rowClass + '">';
    html += '<td style="font-weight:600;color:#60a5fa;">' + (race.meeting_date || 'N/A') + '</td>';
    // ... rest of the columns ...
                    html += '<td style="font-weight:600;">' + (race.course || 'N/A') + '</td>';
                    html += '<td style="color:#6b7280;font-size:12px;">' + (race.direction || '-') + '</td>';
                    html += '<td style="color:#6b7280;font-size:12px;">' + (race.profile || '-') + '</td>';
                    html += '<td><div class="truncate-text" title="' + (race.general_features || '') + '">' + (race.general_features || '-') + '</div></td>';
                    html += '<td><div class="truncate-text" title="' + (race.specific_features || '') + '">' + (race.specific_features || '-') + '</div></td>';
                    html += '<td class="distance-cell" style="color:#1f2937;font-weight:600;">' + (race.distance || 'N/A') + '</td>';
                    html += '<td style="color:#6b7280;font-size:12px;">' + (race.race_type || '-') + '</td>';
                    html += '<td style="text-align:center;font-weight:600;color:#ef4444;">' + (race.distance_beaten > 0 ? race.distance_beaten + 'L' : '-') + '</td>';
                    html += '<td style="text-align:center;"><span style="display:inline-block;padding:4px 8px;border-radius:6px;background:#dbeafe;color:#1e40af;font-weight:600;font-size:11px;">' + (race.class || '-') + '</span></td>';
                    
                    if (race.race_id && race.race_id > 0) {
                        const raceCommentToken = encodeRaceCommentId(race.race_id);
                        const raceCommentUrl = raceCommentToken
                            ? '<?php echo esc_url(home_url('/race-comments/')); ?>' + raceCommentToken + '/'
                            : '#';
                        html += '<td><a href="' + raceCommentUrl + '" style="color:#2563eb;text-decoration:none;font-weight:600;">🏁 ' + (race.race_title || 'N/A') + '</a></td>';
                    } else {
                        html += '<td style="font-weight:600;">' + (race.race_title || 'N/A') + '</td>';
                    }
                    
                    html += '<td style="text-align:center;font-weight:700;color:#34d399;">' + (race.official_rating || '-') + '</td>';
                    html += '<td style="font-weight:600;color:#8b5cf6;">' + (race.value || '-') + '</td>';
                    html += '<td style="font-size:12px;color:#6b7280;">' + (race.runners || '-') + '</td>';
                    
                    // WT Speed Rating
                    if (race.wt_speed_rating) {
                        const wtSr = parseInt(race.wt_speed_rating);
                        let wtClass = '';
                        if (wtSr >= 80) wtClass = 'speed-excellent';
                        else if (wtSr >= 70) wtClass = 'speed-good';
                        else if (wtSr >= 60) wtClass = 'speed-average';
                        html += '<td style="text-align:center;"><span class="' + wtClass + '">' + race.wt_speed_rating + '</span></td>';
                    } else {
                        html += '<td style="text-align:center;"><span style="color:#9ca3af;">-</span></td>';
                    }
                    
                    html += '<td style="font-weight:500;color:#374151;font-size:12px;">' + (race.jockey_name || '-') + '</td>';
                    
                    // Speed Rating
                    if (race.speed_rating) {
                        const sr = parseInt(race.speed_rating);
                        let srClass = '';
                        if (sr >= 80) srClass = 'speed-excellent';
                        else if (sr >= 70) srClass = 'speed-good';
                        else if (sr >= 60) srClass = 'speed-average';
                        html += '<td style="text-align:center;"><span class="' + srClass + '">' + race.speed_rating + '</span></td>';
                    } else {
                        html += '<td style="text-align:center;"><span style="color:#9ca3af;">-</span></td>';
                    }
                    
                    html += '<td style="font-weight:600;font-size:12px;">' + (race.going || 'N/A') + '</td>';
                    
                    // DSLR (Days Since Last Ran)
                    html += '<td style="text-align:center;font-weight:600;color:#6366f1;">' + (race.days_since_ran || '-') + '</td>';
                    
                    // Position
                   // Place
 let positionDisplay = race.position || 'N/A';
    if (positionDisplay.includes('/')) {
        positionDisplay = positionDisplay.split('/')[0];
    }
    html += '<td style="text-align:center;font-weight:bold;"><span class="' + badgeClass + '">' + positionDisplay + '</span></td>';
    html += '</tr>';
                    
                    // Comment row
                     html += '<tr class="' + commentClass + '"><td colspan="20" style="padding:0 12px 16px 12px;">';
                    if (race.in_race_comment) {
                        html += '<div class="comment-box"><span class="comment-label">Race Comment:</span><div class="comment-text">' + race.in_race_comment + '</div></div>';
                    } else {
                        html += '<div style="background:rgba(107,114,128,0.1);border-left:4px solid #6b7280;padding:12px 16px;margin:8px 0;border-radius:0 8px 8px 0;"><span style="color:#6b7280;font-size:12px;font-style:italic;">No race comment available</span></div>';
                    }
                    html += '</td></tr>';
                });
                
                html += '</tbody></table>';
                $('#race-history-table-container').html(html);
                // Rehydrate header filter options after AJAX re-render
                const specificOptions = [];
                $('#filter-specific-features option').each(function() {
                    specificOptions.push($(this).val() === '' ? null : $(this).text());
                });
                updateSelect('#filter-specific-features-header', specificOptions.filter(Boolean));
                syncSpecificFeatureFilters('#filter-specific-features');
            }
            
            // Event handlers
            $('#apply-filters').on('click', function() {
                loadRaceHistory();
            });
            
            $('#reset-filters').on('click', function() {
                $('.race-filter').val('');
                $('#filter-specific-features-header').val('');
                currentSortColumn = '';
                currentSortDirection = 'asc';
                loadRaceHistory();
            });

            $(document).on('change', '#filter-specific-features', function() {
                syncSpecificFeatureFilters('#filter-specific-features');
            });

            $(document).on('change', '#filter-specific-features-header', function() {
                syncSpecificFeatureFilters('#filter-specific-features-header');
                loadRaceHistory();
            });
            
            // Initialize
            console.log('Initializing filters and loading data...');
            loadFilterOptions();
            loadRaceHistory();
        });
    }
    
    // Start initialization
    initHorseHistoryFilters();
})();
</script>



</div>
<?php else: ?>
<div class="race-history-section">
    <div class="no-data">
        <div class="no-data-icon">🔍</div>
        <h3 style="color:#6b7280;margin:0 0 8px 0;">No race history found</h3>
        <p style="color:#9ca3af;margin:0;">This horse's racing history is not available in our database.</p>
    </div>
</div>
<?php endif; ?>

    </div>
    <?php
    $content = ob_get_clean();
    return $content;
}


// Register the shortcode
add_shortcode('horse_history', 'horse_history_shortcode');

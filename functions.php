<?php 
/**
 * Register/enqueue custom scripts and styles
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







// ==============================================
// ENQUEUE SCRIPTS AND STYLES
// ==============================================

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

// Inline JavaScript fallback
function bricks_race_table_inline_js() {
    ?>
    <script>
    console.log('=== INLINE JS FUNCTION CALLED ===');
    console.log('bricks_race_table_inline_js function is executing');
    
    jQuery(document).ready(function($) {
        console.log('=== JQUERY DOCUMENT READY ===');
        console.log('jQuery document ready fired');
        
        let currentPage = 1;
        let currentFilters = { date: race_ajax_obj.default_date };
        let currentSort = { column: '', direction: '' };

        console.log('=== INITIALIZATION ===');
        console.log('Initial currentFilters:', currentFilters);
        console.log('race_ajax_obj.default_date:', race_ajax_obj.default_date);

        loadRaceTable();

        $('.race-date-tab').on('click', function() {
            console.log('=== NEW CLICK HANDLER ===');
            console.log('Tab clicked:', $(this).text().trim());
            console.log('Selected date from tab:', $(this).data('date'));
            
            $('.race-date-tab').removeClass('active');
            $(this).addClass('active');
            const selectedDate = $(this).data('date');
            
            // Update the current filters with the selected date
            currentFilters = { date: selectedDate };
            currentPage = 1;
            loadFilterOptions(selectedDate);
            
            // Call loadRaceTable with explicit date
            loadRaceTableWithDate(selectedDate);
        });

        // ... rest of your JavaScript code stays the same



        $('.race-filter').on('change', function() {
            currentPage = 1;
            loadRaceTable();
        });

        $('#race-reset-btn').on('click', function() {
    $('.race-filter').val('');
    $('#race-runners-from-filter').val(''); // Add this line
    $('#race-runners-to-filter').val('');   // Add this line
    $('.race-date-tab').removeClass('active');
    $('.race-date-tab[data-date="' + race_ajax_obj.default_date + '"]').addClass('active');
    currentFilters = { date: race_ajax_obj.default_date };
    currentPage = 1;
    currentSort = { column: '', direction: '' };
    loadFilterOptions(race_ajax_obj.default_date);
    loadRaceTable();
});


        $(document).on('click', '.race-pagination-btn', function(e) {
            e.preventDefault();
            currentPage = parseInt($(this).data('page'));
            loadRaceTable();
        });

        $(document).on('click', '.race-table th.sortable', function() {
            const column = $(this).data('sort');
            
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.direction = 'asc';
            }
            
            $('.race-table th.sortable').removeClass('sorted-asc sorted-desc active-column');
            $(this).addClass('sorted-' + currentSort.direction + ' active-column');
            
            currentPage = 1;
            loadRaceTable();
        });

        function loadFilterOptions(date) {
            console.log('=== LOAD FILTER OPTIONS ===');
            console.log('Loading filter options for date:', date);
            
            $.ajax({
                url: race_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_race_filter_options',
                    date: date
                },
                success: function(response) {
                    console.log('Filter options loaded successfully');
                    if (response.countries) {
                        updateSelect('#race-country-filter', response.countries, 'All Countries');
                    }
                    if (response.courses) {
                        updateSelect('#race-course-filter', response.courses, 'All Courses');
                    }
                    if (response.types) {
                        updateSelect('#race-type-filter', response.types, 'All Types');
                    }
                    if (response.classes) {
                        updateSelect('#race-class-filter', response.classes, 'All Classes');
                    }
                    if (response.ages) {
                        updateSelect('#race-age-filter', response.ages, 'All Ages');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Filter options error:', status, error);
                }
            });
        }

        function updateSelect(selector, options, defaultText) {
            const select = $(selector);
            const currentValue = select.val();
            select.empty().append('<option value="">' + defaultText + '</option>');
            
            options.forEach(function(option) {
                select.append('<option value="' + option + '">' + option + '</option>');
            });
            
            if (currentValue && options.includes(currentValue)) {
                select.val(currentValue);
            }
        }

        function loadRaceTable() {
            console.log('=== LOAD RACE TABLE START ===');
            
            // Use currentFilters.date first, then active tab, then default
            let activeDate = currentFilters.date;
            if (!activeDate) {
                activeDate = $('.race-date-tab.active').data('date');
            }
            if (!activeDate) {
                activeDate = race_ajax_obj.default_date;
            }
            
            console.log('currentFilters:', currentFilters);
            console.log('currentFilters.date:', currentFilters.date);
            console.log('Active tab element:', $('.race-date-tab.active').length);
            console.log('Active tab data-date:', $('.race-date-tab.active').data('date'));
            console.log('Active tab text:', $('.race-date-tab.active').text().trim());
            console.log('race_ajax_obj.default_date:', race_ajax_obj.default_date);
            console.log('Final activeDate selected:', activeDate);

            // Find this section in your loadRaceTable function and add the runner count filters:
const filters = {
    action: 'load_race_table',
    race_page: currentPage,
    country: $('#race-country-filter').val(),
    course: $('#race-course-filter').val(),
    race_type: $('#race-type-filter').val(),
    class: $('#race-class-filter').val(),
    handicap: $('#race-handicap-filter').val(),
    age_range: $('#race-age-filter').val(),
    runners_from: $('#race-runners-from-filter').val(), // Add this line
    runners_to: $('#race-runners-to-filter').val(),     // Add this line
    date: activeDate,
    sort_column: currentSort.column,
    sort_direction: currentSort.direction
};


            console.log('=== SENDING TO SERVER ===');
            console.log('Complete filters object:', filters);
            console.log('Date being sent:', filters.date);

            $('#race-table-container').html('<div style="text-align:center;padding:40px;">Loading...</div>');

            $.ajax({
                url: race_ajax_obj.ajax_url,
                type: 'POST',
                data: filters,
                success: function(response) {
                    console.log('=== AJAX SUCCESS ===');
                    console.log('Response received, length:', response.length);
                    $('#race-table-container').html(response);
                    
                    if (currentSort.column) {
                        $('.race-table th[data-sort="' + currentSort.column + '"]')
                            .addClass('sorted-' + currentSort.direction + ' active-column');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('=== AJAX ERROR ===');
                    console.log('Status:', status);
                    console.log('Error:', error);
                    console.log('Response text:', xhr.responseText);
                    $('#race-table-container').html('<div style="text-align:center;padding:40px;color:red;">Error loading races. Please try again.</div>');
                }
            });
        }

        function loadRaceTableWithDate(explicitDate) {
    console.log('=== LOAD RACE TABLE WITH EXPLICIT DATE ===');
    console.log('Explicit date passed:', explicitDate);
    
    // In loadRaceTableWithDate function, add the same runner count filters:
const filters = {
    action: 'load_race_table',
    race_page: currentPage,
    country: $('#race-country-filter').val(),
    course: $('#race-course-filter').val(),
    race_type: $('#race-type-filter').val(),
    class: $('#race-class-filter').val(),
    handicap: $('#race-handicap-filter').val(),
    age_range: $('#race-age-filter').val(),
    runners_from: $('#race-runners-from-filter').val(), // Add this line
    runners_to: $('#race-runners-to-filter').val(),     // Add this line
    date: explicitDate,
    sort_column: currentSort.column,
    sort_direction: currentSort.direction
};


    console.log('Sending filters with explicit date:', filters);

    $('#race-table-container').html('<div style="text-align:center;padding:40px;">Loading...</div>');

    $.ajax({
        url: race_ajax_obj.ajax_url,
        type: 'POST',
        data: filters,
        success: function(response) {
            console.log('AJAX Success with explicit date');
            $('#race-table-container').html(response);
            
            if (currentSort.column) {
                $('.race-table th[data-sort="' + currentSort.column + '"]')
                    .addClass('sorted-' + currentSort.direction + ' active-column');
            }
        },
        error: function() {
            $('#race-table-container').html('<div style="text-align:center;padding:40px;color:red;">Error loading races. Please try again.</div>');
        }
    });
}

    });
    </script>
    <?php
}



// ==============================================
// AJAX HANDLERS
// ==============================================

function bricks_get_race_filter_options() {
    global $wpdb;
    $date = !empty($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');

    // FIXED: Determine which table to use based on date
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    error_log("Filter Debug - Today: $today, Tomorrow: $tomorrow, Requested date: $date");
    
    if ($date === $tomorrow) {
        $table = 'advance_daily_races';
        error_log("Filter Debug - Using advance_daily_races table");
    } else {
        $table = 'advance_daily_races_beta';
        error_log("Filter Debug - Using advance_daily_races_beta table");
    }

    // Continue with the rest of the function...



    $countries = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT country FROM $table WHERE meeting_date = %s ORDER BY country", $date
    ));
    $courses = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT course FROM $table WHERE meeting_date = %s ORDER BY course", $date
    ));
    $types = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT race_type FROM $table WHERE meeting_date = %s ORDER BY race_type", $date
    ));
    $classes = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT class FROM $table WHERE meeting_date = %s ORDER BY class", $date
    ));
    $ages = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT age_range FROM $table WHERE meeting_date = %s ORDER BY age_range", $date
    ));
    $handicaps = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT handicap FROM $table WHERE meeting_date = %s AND handicap IS NOT NULL ORDER BY handicap", $date
    ));

    wp_send_json([
        'countries' => $countries,
        'courses' => $courses,
        'types' => $types,
        'classes' => $classes,
        'ages' => $ages,
        'handicaps' => $handicaps,
    ]);
}


add_action('wp_ajax_get_race_filter_options', 'bricks_get_race_filter_options');
add_action('wp_ajax_nopriv_get_race_filter_options', 'bricks_get_race_filter_options');

function bricks_ajax_load_race_table() {
    global $wpdb;

    $per_page = 50;
    $paged = isset($_POST['race_page']) ? intval($_POST['race_page']) : 1;
    $offset = ($paged - 1) * $per_page;

    $where = '1=1';
    $having_conditions = []; // Add this array for HAVING conditions
    
    if (!empty($_POST['country'])) {
        $where .= $wpdb->prepare(" AND r.country = %s", $_POST['country']);
    }
    if (!empty($_POST['course'])) {
        $where .= $wpdb->prepare(" AND r.course = %s", $_POST['course']);
    }
    if (!empty($_POST['race_type'])) {
        $where .= $wpdb->prepare(" AND r.race_type = %s", $_POST['race_type']);
    }
    if (!empty($_POST['class'])) {
        $where .= $wpdb->prepare(" AND r.class = %s", $_POST['class']);
    }
    if (isset($_POST['handicap']) && $_POST['handicap'] !== '') {
        $handicap_value = intval($_POST['handicap']);
        $where .= $wpdb->prepare(" AND r.handicap = %d", $handicap_value);
    }
    if (!empty($_POST['age_range'])) {
        $where .= $wpdb->prepare(" AND r.age_range = %s", $_POST['age_range']);
    }

    $date = !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');
    $where .= $wpdb->prepare(" AND r.meeting_date = %s", $date);

   // CORRECTED: Runner count filtering - works with either "from" or "to" or both
if (isset($_POST['runners_from']) && $_POST['runners_from'] !== '' && is_numeric($_POST['runners_from'])) {
    $runners_from = intval($_POST['runners_from']);
    if ($runners_from >= 0) {  // Allow 0 as minimum
        $having_conditions[] = "runner_count >= $runners_from";
        error_log("Debug - Applied runners_from filter: >= $runners_from");
    }
}

if (isset($_POST['runners_to']) && $_POST['runners_to'] !== '' && is_numeric($_POST['runners_to'])) {
    $runners_to = intval($_POST['runners_to']);
    if ($runners_to > 0) {  // Must be greater than 0 for maximum
        $having_conditions[] = "runner_count <= $runners_to";
        error_log("Debug - Applied runners_to filter: <= $runners_to");
    }
}

// Build the HAVING clause
$having_clause = '';
if (!empty($having_conditions)) {
    $having_clause = 'HAVING ' . implode(' AND ', $having_conditions);
    error_log("Debug - Final HAVING clause: $having_clause");
}


    // Determine which table to use based on date
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    error_log("AJAX Debug - Today: $today, Tomorrow: $tomorrow, Requested date: $date");
    
    if ($date === $tomorrow) {
        $table = 'advance_daily_races';
        $runners_table = 'advance_daily_runners';
        error_log("AJAX Debug - Using advance_daily_races table for tomorrow");
    } else {
        $table = 'advance_daily_races_beta';
        $runners_table = 'advance_daily_runners_beta';
        error_log("AJAX Debug - Using advance_daily_races_beta table for today/other");
    }

    // CORRECTED: Count query with HAVING clause
    $count_query = "SELECT COUNT(*) FROM (
        SELECT r.race_id, COUNT(ru.runner_id) AS runner_count
        FROM $table r
        LEFT JOIN $runners_table ru ON r.race_id = ru.race_id
        WHERE $where
        GROUP BY r.race_id
        $having_clause
    ) AS filtered_races";

    $total_races = $wpdb->get_var($count_query);
    
    $order_by = 'r.course, r.scheduled_time';
    $allowed_sorts = [
        'scheduled_time', 'country', 'race_type', 'class',
        'handicap', 'age_range', 'distance_yards', 'prize_pos_1', 'runner_count'
    ];
    
    if (!empty($_POST['sort_column']) && in_array($_POST['sort_column'], $allowed_sorts)) {
        $direction = (!empty($_POST['sort_direction']) && $_POST['sort_direction'] === 'desc') ? 'DESC' : 'ASC';
        $order_by = sanitize_sql_orderby($_POST['sort_column'] . ' ' . $direction);
    }

    // CORRECTED: Main query with HAVING clause
    $results = $wpdb->get_results("SELECT 
        r.race_id, r.course, r.country, r.meeting_date, r.scheduled_time,
        r.race_title, r.race_type, r.class, r.handicap, r.age_range,
        r.distance_yards, r.prize_pos_1, r.track_type,
        COUNT(ru.runner_id) AS runner_count
        FROM $table r
        LEFT JOIN $runners_table ru ON r.race_id = ru.race_id
        WHERE $where
        GROUP BY r.race_id
        $having_clause
        ORDER BY $order_by
        LIMIT $per_page OFFSET $offset");

    // Tracker alerts: flag races that contain horses this user is tracking.
    $race_tracker_alerts = [];
    if (is_user_logged_in() && !empty($results) && function_exists('bricks_tracker_get_user_data') && function_exists('bricks_tracker_normalize_horse_key')) {
        $tracker_data = bricks_tracker_get_user_data(get_current_user_id());
        $tracked_keys = [];
        foreach ($tracker_data as $tracker_entry) {
            if (!is_array($tracker_entry) || empty($tracker_entry['horse_name'])) {
                continue;
            }
            $key = bricks_tracker_normalize_horse_key($tracker_entry['horse_name']);
            if ($key !== '') {
                $tracked_keys[$key] = $tracker_entry['horse_name'];
            }
        }

        if (!empty($tracked_keys)) {
            $race_ids = array_values(array_filter(array_map(function($r) {
                return isset($r->race_id) ? intval($r->race_id) : 0;
            }, $results)));

            if (!empty($race_ids)) {
                $race_placeholders = implode(',', array_fill(0, count($race_ids), '%d'));
                $runner_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT race_id, name FROM $runners_table WHERE race_id IN ($race_placeholders) AND name IS NOT NULL AND name != ''",
                    ...$race_ids
                ));

                if (!empty($runner_rows)) {
                    foreach ($runner_rows as $runner_row) {
                        $runner_name = isset($runner_row->name) ? (string) $runner_row->name : '';
                        $runner_key = bricks_tracker_normalize_horse_key($runner_name);
                        $runner_race_id = isset($runner_row->race_id) ? intval($runner_row->race_id) : 0;

                        if ($runner_key === '' || $runner_race_id <= 0 || !isset($tracked_keys[$runner_key])) {
                            continue;
                        }

                        if (!isset($race_tracker_alerts[$runner_race_id])) {
                            $race_tracker_alerts[$runner_race_id] = [];
                        }
                        $race_tracker_alerts[$runner_race_id][$runner_key] = $runner_name;
                    }
                }
            }
        }
    }
    
    // Debug: Log the query and results
    error_log("Debug - Query table: $table, Total races found: $total_races");
    error_log("Debug - HAVING clause: $having_clause");
    error_log("Debug - Results count: " . count($results));
// Add this debug logging right after building the HAVING clause:
error_log("Debug - runners_from: " . ($_POST['runners_from'] ?? 'not set'));
error_log("Debug - runners_to: " . ($_POST['runners_to'] ?? 'not set'));
error_log("Debug - HAVING conditions: " . print_r($having_conditions, true));
error_log("Debug - HAVING clause: $having_clause");

    $total_pages = ceil($total_races / $per_page);

    // Rest of your function continues as before...
    ob_start();
    // ... your existing display code



    if ($results) {
        $current_course = '';
        $tracker_summary_html = '';

        if (!empty($race_tracker_alerts)) {
            $summary_items = [];
            foreach ($results as $summary_row) {
                $summary_race_id = isset($summary_row->race_id) ? intval($summary_row->race_id) : 0;
                if ($summary_race_id <= 0 || empty($race_tracker_alerts[$summary_race_id])) {
                    continue;
                }

                $summary_horses = array_values($race_tracker_alerts[$summary_race_id]);
                $summary_time = !empty($summary_row->scheduled_time) ? date('H:i', strtotime($summary_row->scheduled_time)) : '--:--';
                $summary_label = $summary_time . ' ' . (string) ($summary_row->course ?? '') . ' - ' . implode(', ', $summary_horses);
                $summary_items[] = '<a href="' . esc_url(bricks_race_url($summary_race_id)) . '" class="tracker-summary-link" title="' . esc_attr($summary_label) . '">' . esc_html($summary_label) . '</a>';
            }

            if (!empty($summary_items)) {
                $tracker_summary_html = '<div class="tracker-alert-strip" style="margin:0 0 14px 0;padding:12px 14px;border-radius:10px;background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);border:1px solid #f59e0b;">
                    <div style="font-weight:800;color:#92400e;font-size:13px;margin-bottom:8px;">📝 Tracker Alerts Today</div>
                    <div style="display:flex;flex-direction:column;gap:6px;">' . implode('', $summary_items) . '</div>
                </div>';
            }
        }
        
        // Add debug info at the top of the table (remove this after testing)
        // echo '<div style="background:yellow;padding:10px;margin:10px 0;border:1px solid red;">
        //     <strong>DEBUG INFO:</strong><br>
        //     Date requested: ' . esc_html($date) . '<br>
        //     Table used: ' . esc_html($table) . '<br>
        //     Total races: ' . esc_html($total_races) . '<br>
        //     Results count: ' . count($results) . '
        // </div>';
        
        echo $tracker_summary_html;
        echo '<div style="overflow-x:auto;">
        <table class="race-table sticky-header">
           <thead>
            <tr>
                <th data-sort="scheduled_time" class="sortable">Time</th>
                <th data-sort="country" class="sortable">Country</th>
                <th>Title</th>
                <th data-sort="race_type" class="sortable">Type</th>
                <th data-sort="class" class="sortable">Class</th>
                <th data-sort="handicap" class="sortable">Handicap</th>
                <th data-sort="age_range" class="sortable">Age</th>
                <th data-sort="distance_yards" class="sortable">Dist</th>
                <th data-sort="distance_yards" class="sortable">Furlongs</th>
                <th data-sort="prize_pos_1" class="sortable">Prize</th>
                <th data-sort="runner_count" class="sortable">Runners</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($results as $row) {
            $course_name = $row->course;
            if (strtolower($row->track_type) === 'allweather') {
                $course_name .= ' AW';
            }

            if ($course_name !== $current_course) {
                $current_course = $course_name;
                echo '<tr data-course-header="true">
                    <td colspan="11">' . esc_html($current_course) . '</td>
                </tr>';
            }

            $handicap_display = 'N/A';
            if ($row->handicap !== null) {
                $handicap_display = ($row->handicap == 1) ? 'Handicap' : 'Non-Handicap';
            }

            $formatted_race_type = $row->race_type;
            if (str_contains(strtolower($row->race_type), 'flat')) {
                $surface = '';
                if (strtolower($row->track_type) === 'allweather') {
                    $surface = 'AW';
                } elseif (strtolower($row->track_type) === 'turf') {
                    $surface = 'Turf';
                }
                if ($surface) {
                   $formatted_race_type = trim($row->race_type . ' ' . $surface);
                }
            }

            $currency_symbol = '£';
            if (in_array(strtolower($row->country), ['ireland', 'eire'])) {
                $currency_symbol = '€';
            }

            $formatted_prize = $currency_symbol . number_format(floatval($row->prize_pos_1));
            
            // Add badge styling for handicap
            $handicap_badge = '';
            if ($row->handicap !== null) {
                $badge_color = ($row->handicap == 1) ? '#10b981' : '#6b7280';
                $handicap_badge = '<span style="display:inline-block;padding:4px 8px;border-radius:6px;background:' . $badge_color . ';color:white;font-size:10px;font-weight:700;text-transform:uppercase;">' . $handicap_display . '</span>';
            } else {
                $handicap_badge = '<span style="color:#9ca3af;">N/A</span>';
            }

            $race_alert = isset($race_tracker_alerts[intval($row->race_id)]) ? array_values($race_tracker_alerts[intval($row->race_id)]) : [];
            $tracker_alert_html = '';
            if (!empty($race_alert)) {
                $alert_count = count($race_alert);
                $alert_title = 'Tracked horse running: ' . implode(', ', $race_alert);
                $tracker_alert_html = '<div style="margin-top:4px;">
                    <span title="' . esc_attr($alert_title) . '" style="display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.3px;">
                        📝 Tracker Alert' . ($alert_count > 1 ? ' (' . $alert_count . ')' : '') . '
                    </span>
                </div>';
            }

            echo '<tr>
                <td style="color:#3b82f6;font-weight:600;">' . date('H:i', strtotime($row->scheduled_time)) . '</td>
                <td>' . esc_html($row->country) . '</td>
                <td><a href="' . esc_url(bricks_race_url($row->race_id)) . '" class="race-link">🏁 ' . esc_html($row->race_title) . '</a>' . $tracker_alert_html . '</td>
                <td style="color:#374151;font-weight:500;">' . esc_html($formatted_race_type) . '</td>
                <td><span style="display:inline-block;padding:4px 10px;border-radius:6px;background:#dbeafe;color:#1e40af;font-weight:600;font-size:11px;">' . esc_html($row->class) . '</span></td>
                <td>' . $handicap_badge . '</td>
                <td style="color:#6b7280;">' . esc_html($row->age_range) . '</td>
                <td style="font-family:monospace;color:#374151;">' . esc_html($row->distance_yards) . 'y</td>
                <td style="font-family:monospace;color:#374151;">' . round($row->distance_yards / 220, 1) . 'f</td>
                <td style="color:#059669;font-weight:700;">' . esc_html($formatted_prize) . '</td>
                <td><span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:#eff6ff;color:#2563eb;font-weight:700;font-size:12px;">' . esc_html($row->runner_count) . '</span></td>
            </tr>';
        }

        echo '</tbody></table></div>';

        echo '<div class="race-pagination-wrapper" style="margin-top:15px;text-align:center">';

        if ($paged > 1) {
            echo '<a class="race-pagination-btn" href="#" data-page="' . ($paged - 1) . '">&laquo; Prev</a>';
        }

        for ($i = 1; $i <= $total_pages; $i++) {
            $active = $i == $paged ? ' race-pagination-btn-active' : '';
            echo '<a class="race-pagination-btn' . $active . '" href="#" data-page="' . $i . '">' . $i . '</a>';
        }

        if ($paged < $total_pages) {
            echo '<a class="race-pagination-btn" href="#" data-page="' . ($paged + 1) . '">Next &raquo;</a>';
        }

        echo '</div>';
    } else {
        
        echo '<p>No results found.</p>';
    }

    wp_die(ob_get_clean());
}


add_action('wp_ajax_load_race_table', 'bricks_ajax_load_race_table');
add_action('wp_ajax_nopriv_load_race_table', 'bricks_ajax_load_race_table');

// ==============================================
// SHORTCODE FOR DISPLAY
// ==============================================

function bricks_race_table_shortcode() {
    global $wpdb;

    $today = new DateTimeImmutable();
    $today_date = $today->format('Y-m-d');

    $navigation_header = bricks_get_navigation_header();

    // Use beta table for today's initial load
    $table = 'advance_daily_races_beta';
    
    $countries = $wpdb->get_col(
        $wpdb->prepare("SELECT DISTINCT country FROM $table WHERE meeting_date = %s ORDER BY country", $today_date)
    );
    $courses = $wpdb->get_col(
        $wpdb->prepare("SELECT DISTINCT course FROM $table WHERE meeting_date = %s ORDER BY course", $today_date)
    );
    $types = $wpdb->get_col(
        $wpdb->prepare("SELECT DISTINCT race_type FROM $table WHERE meeting_date = %s ORDER BY race_type", $today_date)
    );
    $classes = $wpdb->get_col(
        $wpdb->prepare("SELECT DISTINCT class FROM $table WHERE meeting_date = %s ORDER BY class", $today_date)
    );
    $ages = $wpdb->get_col(
        $wpdb->prepare("SELECT DISTINCT age_range FROM $table WHERE meeting_date = %s ORDER BY age_range", $today_date)
    );

$dates = [];
$today_str = $today->format('Y-m-d');
$tomorrow_str = date('Y-m-d', strtotime('+1 day'));

// Today
$dates[] = [
    'label' => 'Today',
    'value' => $today_str,
    'is_today' => true
];
// Tomorrow
$dates[] = [
    'label' => 'Tomorrow',
    'value' => $tomorrow_str,
    'is_today' => false
];





    ob_start();
    ?>
    <style>
        /* Container */
        .race-table-wrapper {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 24px;
            margin-bottom: 30px;
              overflow-x: auto;
            max-width: 100%;
        }

        /* Filters Section */
        .race-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .race-filters input[type="number"] {
    padding: 10px 12px;
    font-size: 14px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    transition: all 0.2s ease;
    outline: none;
    width: 100%;
}

.race-filters input[type="number"]:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

        .filter-group label {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .race-filters select,
        .race-filters input[type="date"] {
            padding: 10px 12px;
            font-size: 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            transition: all 0.2s ease;
            outline: none;
        }
        
        .race-filters select:focus,
        .race-filters input[type="date"]:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .race-reset-button {
            padding: 10px 20px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
        }
        
        .race-reset-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }

        /* Date Tabs */
        .race-date-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 10px;
        }
        
       .race-date-tab {
    padding: 15px 30px; /* Make tabs bigger */
    border-radius: 8px;
    background: white;
    cursor: pointer;
    border: 2px solid #e5e7eb;
    font-weight: 600; /* Make text bolder */
    font-size: 16px; /* Increase font size */
    transition: all 0.2s ease;
    color: #374151;
    flex: 1; /* Make tabs equal width */
    text-align: center;
}

        
        .race-date-tab:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        
        .race-date-tab.active {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        /* Table Styling */
        .race-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        
        .race-table th,
        .race-table td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .race-table thead th {
            background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%);
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #d1d5db;
        }
        
        .race-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .race-table tbody tr:hover {
            background: #f9fafb;
        }
        
        .race-table tbody tr td:first-child {
            font-weight: 600;
            color: #3b82f6;
        }

        /* Course Header Rows */
        .race-table tbody tr[data-course-header] {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        }
        
        .race-table tbody tr[data-course-header] td {
            color: white;
            font-weight: 700;
            font-size: 14px;
            padding: 12px;
            border: none;
        }

        /* Links */
        .race-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .race-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        /* Sortable Headers */
        th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 24px;
        }
        
        th.sortable:hover {
            background: #e5e7eb;
        }
        
        th.sortable::after {
            content: '⇅';
            position: absolute;
            right: 8px;
            opacity: 0.3;
            font-size: 12px;
        }
        
        th.sortable.sorted-asc::after {
            content: '↑';
            opacity: 1;
            color: #3b82f6;
        }
        
        th.sortable.sorted-desc::after {
            content: '↓';
            opacity: 1;
            color: #3b82f6;
        }
        
        th.sortable.active-column {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Pagination */
        .race-pagination-wrapper {
            margin-top: 24px;
            text-align: center;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .race-pagination-btn {
            display: inline-block;
            padding: 10px 16px;
            margin: 0 4px;
            background: white;
            border: 2px solid #e5e7eb;
            color: #374151;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        
        .race-pagination-btn:hover {
            border-color: #3b82f6;
            background: #eff6ff;
            color: #2563eb;
            transform: translateY(-1px);
        }
        
        .race-pagination-btn-active {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white !important;
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .race-table-wrapper {
                padding: 16px;
                margin-bottom: 20px;
            }
            
            .race-filters {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 16px;
            }
            
            .filter-group label {
                font-size: 10px;
            }
            
            .race-filters select,
            .race-filters input[type="number"],
            .race-filters input[type="date"] {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 12px;
            }
            
            .race-reset-button {
                width: 100%;
                padding: 12px;
                font-size: 14px;
            }
            
            .race-date-tabs {
                flex-direction: column;
                gap: 8px;
                padding: 12px;
            }
            
            .race-date-tab {
                padding: 12px 20px;
                font-size: 14px;
                width: 100%;
            }
            
            .race-table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .race-table {
                font-size: 11px;
                min-width: 800px; /* Ensure table doesn't get too compressed */
            }
            
            .race-table th,
            .race-table td {
                padding: 8px 6px;
            }
            
            .race-table thead th {
                font-size: 10px;
                padding: 10px 6px;
            }
            
            .race-pagination-wrapper {
                padding: 12px;
            }
            
            .race-pagination-btn {
                padding: 8px 12px;
                font-size: 12px;
                margin: 2px;
            }
        }
        
        @media (max-width: 480px) {
            .race-table-wrapper {
                padding: 12px;
            }
            
            .race-filters {
                padding: 12px;
            }
            
            .race-table {
                font-size: 10px;
            }
            
            .race-table th,
            .race-table td {
                padding: 6px 4px;
            }
            
            .race-pagination-btn {
                padding: 6px 10px;
                font-size: 11px;
            }
        }

        /* Loading State */
        #race-table-container {
            min-height: 200px;
            position: relative;
        }
    </style>

    <div class="race-table-wrapper">

    <div class="race-filters">
        <div class="filter-group">
            <label for="race-country-filter">Country:</label>
            <select id="race-country-filter" class="race-filter">
                <option value="">All Countries</option>
                <?php foreach ($countries as $country): ?>
                    <option value="<?= esc_attr($country) ?>"><?= esc_html($country) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="race-course-filter">Course:</label>
            <select id="race-course-filter" class="race-filter">
                <option value="">All Courses</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= esc_attr($course) ?>"><?= esc_html($course) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="race-type-filter">Race Type:</label>
            <select id="race-type-filter" class="race-filter">
                <option value="">All Types</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= esc_attr($type) ?>"><?= esc_html($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="race-class-filter">Class:</label>
            <select id="race-class-filter" class="race-filter">
                <option value="">All Classes</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?= esc_attr($class) ?>"><?= esc_html($class) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="race-handicap-filter">Handicap:</label>
            <select id="race-handicap-filter" class="race-filter">
                <option value="">All Handicaps</option>
                <option value="1">Handicap</option>
                <option value="0">Non-Handicap</option>
            </select>
        </div>

        <div class="filter-group">
            <label for="race-age-filter">Age Range:</label>
            <select id="race-age-filter" class="race-filter">
                <option value="">All Ages</option>
                <?php foreach ($ages as $age): ?>
                    <option value="<?= esc_attr($age) ?>"><?= esc_html($age) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- <div class="filter-group">
            <label for="race-date-filter">Date:</label>
            <input type="date" id="race-date-filter" class="race-filter" />
        </div> -->

<div class="filter-group">
    <label for="race-runners-from-filter">Runners From:</label>
    <input type="number" id="race-runners-from-filter" class="race-filter" min="1" max="50" placeholder="Min">
</div>

<div class="filter-group">
    <label for="race-runners-to-filter">Runners To:</label>
    <input type="number" id="race-runners-to-filter" class="race-filter" min="1" max="50" placeholder="Max">
</div>


        <button type="button" class="race-reset-button" id="race-reset-btn">Reset</button>
    </div>

   <div class="race-date-tabs">
    <?php foreach ($dates as $d): ?>
        <div class="race-date-tab<?= $d['is_today'] ? ' active' : '' ?>" data-date="<?= esc_attr($d['value']) ?>">
            <?= esc_html($d['label']) ?>
        </div>
    <?php endforeach; ?>
</div>



    <div id="race-table-container">
        <div style="text-align:center;padding:60px 20px;color:#6b7280;">
            <div style="font-size:48px;margin-bottom:16px;">🏇</div>
            <div style="font-size:16px;font-weight:600;">Loading races...</div>
        </div>
    </div>
  
<!-- Add this right before the closing </div> of race-table-wrapper -->
<script>
console.log('=== RACE TABLE DEBUG ===');
console.log('Page loaded, checking if JavaScript is working...');
console.log('jQuery available:', typeof jQuery !== 'undefined');
console.log('race_ajax_obj available:', typeof race_ajax_obj !== 'undefined');
if (typeof race_ajax_obj !== 'undefined') {
    console.log('race_ajax_obj.default_date:', race_ajax_obj.default_date);
}
</script>
<!-- Add this right after your existing debug script -->
<script>
console.log('=== TESTING CLICK HANDLERS ===');
jQuery(document).ready(function($) {
    console.log('Available tabs:', $('.race-date-tab').length);
    $('.race-date-tab').each(function(i) {
        console.log('Tab ' + i + ':', $(this).text().trim(), 'Date:', $(this).data('date'));
    });
    
    // Test if click handlers are working
    $('.race-date-tab').on('click', function() {
        console.log('SIMPLE CLICK TEST: Tab clicked!', $(this).text().trim());
    });
});
</script>



    </div>
    <?php
      $content = ob_get_clean();
    return $content;
}

// Register shortcode
add_shortcode('race_table', 'bricks_race_table_shortcode');
function bricks_race_table_shortcode_with_header() {
    $content = '';
    
    // If this is a standalone page, include header
    if (bricks_is_standalone_page() && !headers_sent()) {
        ob_start();
        get_header();
        $content .= ob_get_clean();
        
        // Add page header
        $content .= '
        <div class="page-header">
            <div class="page-header-container">
                <h1 class="page-title">
                    <span>🏁</span>
                    Today\'s Races
                </h1>
                <p class="page-description">Live racing data, results and comprehensive race information</p>
            </div>
        </div>
        <main class="main-content">
            <div class="content-container">';
    }
    
    // Add the original shortcode content
    $content .= bricks_race_table_shortcode();
    
    // If this is a standalone page, include footer
    if (bricks_is_standalone_page()) {
        $content .= '</div></main>';
        ob_start();
        get_footer();
        $content .= ob_get_clean();
    }
    
    return $content;
}

add_shortcode('race_table_full', 'bricks_race_table_shortcode_with_header');
/**
 * Bricks Builder - Quick Reference Table
 * PART 1: Add this to your child theme's functions.php or code snippets plugin
 */

// ==============================================
// ENQUEUE SCRIPTS FOR SPEED PERFORMANCE
// ==============================================

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
// ==============================================
// ENQUEUE SCRIPTS FOR HORSE HISTORY
// ==============================================

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


// Inline JavaScript fallback
function bricks_speed_performance_inline_js() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        let currentPage = 1;
        let currentFilters = {};
        let currentSort = { column: '', direction: '' };
        const speedStickyDebug = <?php echo bricks_debug_enabled() ? 'true' : 'false'; ?>;

        function runSpeedStickyDebug(tag) {
            const container = document.getElementById('speed-performance-table-container');
            const table = container ? container.querySelector('.speed-performance-table') : null;
            const thead = table ? table.querySelector('thead') : null;
            const th = thead ? thead.querySelector('th') : null;

            if (!container || !table || !thead || !th) {
                console.log('[SpeedStickyDebug][' + tag + '] Missing element(s)', {
                    container: !!container,
                    table: !!table,
                    thead: !!thead,
                    th: !!th
                });
                return;
            }

            const csContainer = window.getComputedStyle(container);
            const csThead = window.getComputedStyle(thead);
            const csTh = window.getComputedStyle(th);
            const rectTh = th.getBoundingClientRect();
            const rectContainer = container.getBoundingClientRect();

            const overflowAncestors = [];
            let p = container.parentElement;
            while (p) {
                const cs = window.getComputedStyle(p);
                const ox = cs.overflowX;
                const oy = cs.overflowY;
                if (/(auto|scroll|hidden|clip)/.test(ox + oy)) {
                    overflowAncestors.push({
                        tag: p.tagName,
                        id: p.id || '',
                        className: p.className || '',
                        overflowX: ox,
                        overflowY: oy,
                        position: cs.position
                    });
                }
                p = p.parentElement;
            }

            console.log('[SpeedStickyDebug][' + tag + ']', {
                container: {
                    overflowX: csContainer.overflowX,
                    overflowY: csContainer.overflowY,
                    position: csContainer.position,
                    maxHeight: csContainer.maxHeight,
                    scrollTop: container.scrollTop
                },
                thead: {
                    position: csThead.position,
                    top: csThead.top,
                    zIndex: csThead.zIndex
                },
                firstTh: {
                    position: csTh.position,
                    top: csTh.top,
                    zIndex: csTh.zIndex,
                    rectTop: Math.round(rectTh.top),
                    rectBottom: Math.round(rectTh.bottom)
                },
                containerRect: {
                    top: Math.round(rectContainer.top),
                    bottom: Math.round(rectContainer.bottom)
                },
                overflowAncestors: overflowAncestors
            });
        }

        // Expose manual debug trigger in browser console:
        // window.fhDebugSpeedSticky()
        window.fhDebugSpeedSticky = function() {
            runSpeedStickyDebug('manual');
        };

        loadFilterOptions(speed_performance_ajax_obj.default_date);
        loadSpeedTable();
        if (speedStickyDebug) {
            setTimeout(function() { runSpeedStickyDebug('initial'); }, 300);
        }

        $('.speed-performance-filter').on('change input', function() {
            currentPage = 1;
            loadSpeedTable();
        });

        $('#speed-performance-reset-btn').on('click', function() {
            $('.speed-performance-filter').val('');
            $('#speed-performance-fsr-filter').val(''); // Clear the new numeric filter
            const defaultDate = speed_performance_ajax_obj.default_date;
            currentFilters = {};
            currentPage = 1;
            currentSort = { column: '', direction: '' };
            loadFilterOptions(defaultDate);
            loadSpeedTable();
        });

        $(document).on('click', '.speed-performance-pagination-btn', function(e) {
            e.preventDefault();
            currentPage = parseInt($(this).data('page'));
            loadSpeedTable();
            $('html, body').animate({ scrollTop: $('#speed-performance-table-container').offset().top - 100 }, 500);
        });

        $(document).on('click', '.speed-performance-table th.sortable', function() {
            const column = $(this).data('sort');
            
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.direction = 'asc';
            }
            
            $('.speed-performance-table th.sortable').removeClass('sorted-asc sorted-desc active-column');
            $(this).addClass('sorted-' + currentSort.direction + ' active-column');
            
            currentPage = 1;
            loadSpeedTable();
        });

        function loadFilterOptions(date) {
            $.ajax({
                url: speed_performance_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_speed_performance_filter_options',
                    date: date
                },
                success: function(response) {
                    if (response.runners) {
                        updateSelect('#speed-performance-runner-filter', response.runners, 'All Runners');
                    }
                    if (response.courses) {
                        updateSelect('#speed-performance-course-filter', response.courses, 'All Courses');
                    }
                    if (response.trainers) {
                        updateSelect('#speed-performance-trainer-filter', response.trainers, 'All Trainers');
                    }
                    if (response.jockeys) {
                        updateSelect('#speed-performance-jockey-filter', response.jockeys, 'All Jockeys');
                    }
                    if (response.distances) {
                        updateSelect('#speed-performance-distance-filter', response.distances, 'All Distances');
                    }
                    if (response.race_types) {
                        updateSelect('#speed-performance-race-type-filter', response.race_types, 'All Race Types');
                    }
                }
            });
        }

        function updateSelect(selector, options, defaultText) {
            const select = $(selector);
            const currentValue = select.val();
            select.empty().append('<option value="">' + defaultText + '</option>');
            
            options.forEach(function(option) {
                select.append('<option value="' + option + '">' + option + '</option>');
            });
            
            if (currentValue && options.includes(currentValue)) {
                select.val(currentValue);
            }
        }

        function loadSpeedTable() {
            const filters = {
                action: 'load_speed_performance_table',
                page: currentPage,
                runner: $('#speed-performance-runner-filter').val(),
                course: $('#speed-performance-course-filter').val(),
                trainer: $('#speed-performance-trainer-filter').val(),
                jockey: $('#speed-performance-jockey-filter').val(),
                distance: $('#speed-performance-distance-filter').val(),
                race_type: $('#speed-performance-race-type-filter').val(),
                min_fsr: $('#speed-performance-fsr-filter').val(),
                date: speed_performance_ajax_obj.default_date,
                sort_column: currentSort.column,
                sort_direction: currentSort.direction
            };

            $('#speed-performance-table-container').html('<div style="text-align:center;padding:60px 20px;color:#6b7280;"><div style="font-size:48px;margin-bottom:16px;">⚡</div><div style="font-size:16px;font-weight:600;">Loading quick reference data...</div></div>');

            $.ajax({
                url: speed_performance_ajax_obj.ajax_url,
                type: 'POST',
                data: filters,
                success: function(response) {
                    $('#speed-performance-table-container').html(response);
                    if (speedStickyDebug) {
                        setTimeout(function() { runSpeedStickyDebug('after-ajax-render'); }, 100);
                    }
                    
                    if (currentSort.column) {
                        $('.speed-performance-table th[data-sort="' + currentSort.column + '"]')
                            .addClass('sorted-' + currentSort.direction + ' active-column');
                    }
                },
                error: function() {
                    $('#speed-performance-table-container').html('<div style="text-align:center;padding:40px;color:red;">Error loading data. Please try again.</div>');
                }
            });
        }
    });
    </script>
    <?php
}

// ==============================================
// AJAX HANDLERS
// ==============================================

if (!function_exists('bricks_tracker_meta_key')) {
    function bricks_tracker_meta_key() {
        return 'bricks_horse_tracker_notes';
    }
}

if (!function_exists('bricks_tracker_normalize_horse_key')) {
    function bricks_tracker_normalize_horse_key($horse_name) {
        return sanitize_title(wp_strip_all_tags((string) $horse_name));
    }
}

if (!function_exists('bricks_tracker_get_user_data')) {
    function bricks_tracker_get_user_data($user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if (!$user_id) {
            return [];
        }

        $data = get_user_meta($user_id, bricks_tracker_meta_key(), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('bricks_tracker_set_user_data')) {
    function bricks_tracker_set_user_data($data, $user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if (!$user_id) {
            return false;
        }

        if (!is_array($data)) {
            $data = [];
        }

        update_user_meta($user_id, bricks_tracker_meta_key(), $data);
        return true;
    }
}

if (!function_exists('bricks_tracker_add_note')) {
    function bricks_tracker_add_note($horse_name, $note, $race_meta = [], $user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'You must be logged in.');
        }

        $horse_name = sanitize_text_field($horse_name);
        $note = sanitize_textarea_field($note);
        if ($horse_name === '' || $note === '') {
            return new WP_Error('invalid_input', 'Horse and note are required.');
        }

        $horse_key = bricks_tracker_normalize_horse_key($horse_name);
        if ($horse_key === '') {
            return new WP_Error('invalid_horse', 'Invalid horse name.');
        }

        $data = bricks_tracker_get_user_data($user_id);
        if (!isset($data[$horse_key]) || !is_array($data[$horse_key])) {
            $data[$horse_key] = [
                'horse_name' => $horse_name,
                'notes' => []
            ];
        }

        $new_note = [
            'id' => uniqid('tn_', true),
            'note' => $note,
            'created_at' => current_time('mysql'),
            'race_date' => sanitize_text_field($race_meta['race_date'] ?? ''),
            'race_time' => sanitize_text_field($race_meta['race_time'] ?? ''),
            'course' => sanitize_text_field($race_meta['course'] ?? ''),
            'race_id' => sanitize_text_field($race_meta['race_id'] ?? ''),
        ];

        $data[$horse_key]['horse_name'] = $horse_name;
        if (!isset($data[$horse_key]['notes']) || !is_array($data[$horse_key]['notes'])) {
            $data[$horse_key]['notes'] = [];
        }
        $data[$horse_key]['notes'][] = $new_note;

        bricks_tracker_set_user_data($data, $user_id);
        return $new_note;
    }
}

if (!function_exists('bricks_tracker_delete_note')) {
    function bricks_tracker_delete_note($horse_name, $note_id, $user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'You must be logged in.');
        }

        $horse_key = bricks_tracker_normalize_horse_key($horse_name);
        if ($horse_key === '' || $note_id === '') {
            return new WP_Error('invalid_input', 'Invalid tracker delete request.');
        }

        $data = bricks_tracker_get_user_data($user_id);
        if (empty($data[$horse_key]['notes']) || !is_array($data[$horse_key]['notes'])) {
            return new WP_Error('not_found', 'Tracker note not found.');
        }

        $data[$horse_key]['notes'] = array_values(array_filter($data[$horse_key]['notes'], function($entry) use ($note_id) {
            return !isset($entry['id']) || $entry['id'] !== $note_id;
        }));

        if (empty($data[$horse_key]['notes'])) {
            unset($data[$horse_key]);
        }

        bricks_tracker_set_user_data($data, $user_id);
        return true;
    }
}

function bricks_ajax_add_tracker_note() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in to use tracker notes.'], 403);
    }

    check_ajax_referer('bricks_tracker_nonce', 'nonce');

    $horse_name = isset($_POST['horse_name']) ? wp_unslash($_POST['horse_name']) : '';
    $note = isset($_POST['note']) ? wp_unslash($_POST['note']) : '';

    $result = bricks_tracker_add_note($horse_name, $note, [
        'race_date' => isset($_POST['race_date']) ? wp_unslash($_POST['race_date']) : '',
        'race_time' => isset($_POST['race_time']) ? wp_unslash($_POST['race_time']) : '',
        'course' => isset($_POST['course']) ? wp_unslash($_POST['course']) : '',
        'race_id' => isset($_POST['race_id']) ? wp_unslash($_POST['race_id']) : '',
    ]);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }

    wp_send_json_success([
        'message' => 'Tracker note saved.',
        'note' => [
            'id' => isset($result['id']) ? sanitize_text_field($result['id']) : '',
            'note' => isset($result['note']) ? sanitize_textarea_field($result['note']) : '',
            'created_at' => isset($result['created_at']) ? sanitize_text_field($result['created_at']) : current_time('mysql'),
            'race_date' => isset($result['race_date']) ? sanitize_text_field($result['race_date']) : '',
            'race_time' => isset($result['race_time']) ? sanitize_text_field($result['race_time']) : '',
            'course' => isset($result['course']) ? sanitize_text_field($result['course']) : '',
            'race_id' => isset($result['race_id']) ? sanitize_text_field($result['race_id']) : '',
        ]
    ]);
}
add_action('wp_ajax_bricks_add_tracker_note', 'bricks_ajax_add_tracker_note');

function bricks_ajax_delete_tracker_note() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in to use tracker notes.'], 403);
    }

    check_ajax_referer('bricks_tracker_nonce', 'nonce');

    $horse_name = isset($_POST['horse_name']) ? sanitize_text_field(wp_unslash($_POST['horse_name'])) : '';
    $note_id = isset($_POST['note_id']) ? sanitize_text_field(wp_unslash($_POST['note_id'])) : '';
    $result = bricks_tracker_delete_note($horse_name, $note_id);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }

    wp_send_json_success(['message' => 'Tracker note deleted.']);
}
add_action('wp_ajax_bricks_delete_tracker_note', 'bricks_ajax_delete_tracker_note');

if (!function_exists('bricks_tracker_get_notes_for_horse')) {
    function bricks_tracker_get_notes_for_horse($horse_name, $user_id = 0) {
        $horse_key = bricks_tracker_normalize_horse_key($horse_name);
        if ($horse_key === '') {
            return [];
        }

        $data = bricks_tracker_get_user_data($user_id ?: get_current_user_id());
        if (empty($data[$horse_key]['notes']) || !is_array($data[$horse_key]['notes'])) {
            return [];
        }

        return $data[$horse_key]['notes'];
    }
}

if (!function_exists('bricks_tracker_render_horse_widget')) {
    function bricks_tracker_render_horse_widget($horse_name, $race_meta = [], $args = []) {
        $horse_name = sanitize_text_field((string) $horse_name);
        if ($horse_name === '') {
            return '';
        }

        $args = wp_parse_args($args, [
            'show_latest_flag' => false,
            'wrapper_class' => '',
            'compact' => false
        ]);

        $notes = is_user_logged_in() ? bricks_tracker_get_notes_for_horse($horse_name, get_current_user_id()) : [];
        $count = count($notes);
        $latest = $count > 0 ? end($notes) : null;

        $panel_id = 'tracker-panel-' . md5($horse_name . '|' . maybe_serialize($race_meta) . '|' . wp_rand(1000, 9999));
        $button_text = $count > 0 ? 'My Tracker (' . $count . ')' : 'Add to Tracker';
        $notes_html = '';

        if ($count > 0) {
            foreach ($notes as $entry) {
                $note_id = esc_attr($entry['id'] ?? '');
                $note_text = esc_html($entry['note'] ?? '');
                $note_date = !empty($entry['created_at']) ? esc_html(date_i18n('d M Y H:i', strtotime($entry['created_at']))) : '';
                $note_race = trim(implode(' | ', array_filter([
                    $entry['race_date'] ?? '',
                    $entry['race_time'] ?? '',
                    $entry['course'] ?? ''
                ])));
                $notes_html .= '<div class="tracker-note-item">
                    <div class="tracker-note-meta">' . $note_date . ($note_race ? ' - ' . esc_html($note_race) : '') . '</div>
                    <div class="tracker-note-text">' . nl2br($note_text) . '</div>
                    <button type="button" class="tracker-delete-btn" data-horse-name="' . esc_attr($horse_name) . '" data-note-id="' . $note_id . '">Delete</button>
                </div>';
            }
        } else {
            $notes_html = '<div class="tracker-empty">No notes yet for this horse.</div>';
        }

        $latest_html = '';
        if ($args['show_latest_flag'] && $latest && !empty($latest['note'])) {
            $latest_html = '<div class="tracker-flag">📝 Tracker: ' . esc_html(wp_trim_words($latest['note'], 14, '...')) . '</div>';
        }

        $widget_html = '<div class="tracker-inline-widget ' . esc_attr($args['wrapper_class']) . '">
            ' . $latest_html;

        if (!is_user_logged_in()) {
            $widget_html .= '<span class="tracker-guest-message">Log in to use tracker</span></div>';
            return $widget_html;
        }

        $widget_html .= '<button type="button" class="tracker-toggle-btn tracker-compact-btn" data-target="#' . esc_attr($panel_id) . '">' . esc_html($button_text) . '</button>
            <div id="' . esc_attr($panel_id) . '" class="tracker-panel" style="display:none;">
                <div class="tracker-notes-list">' . $notes_html . '</div>
                <textarea class="tracker-note-input" rows="3" placeholder="Add your note about this horse..."></textarea>
                <button type="button" class="tracker-save-btn"
                    data-horse-name="' . esc_attr($horse_name) . '"
                    data-race-id="' . esc_attr($race_meta['race_id'] ?? '') . '"
                    data-race-date="' . esc_attr($race_meta['race_date'] ?? '') . '"
                    data-race-time="' . esc_attr($race_meta['race_time'] ?? '') . '"
                    data-course="' . esc_attr($race_meta['course'] ?? '') . '">Save Note</button>
            </div>
        </div>';

        return $widget_html;
    }
}

function bricks_tracker_inline_js() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        if (window.bricksTrackerBindingsAdded) {
            return;
        }
        window.bricksTrackerBindingsAdded = true;

        function trackerConfig() {
            if (typeof speed_performance_ajax_obj !== 'undefined') {
                return speed_performance_ajax_obj;
            }
            if (typeof bricks_tracker_obj !== 'undefined') {
                return bricks_tracker_obj;
            }
            return { ajax_url: '', is_logged_in: false, tracker_nonce: '' };
        }

        function updateTrackerButtonCount($panel) {
            const $widget = $panel.closest('.tracker-inline-widget');
            const $btn = $widget.find('.tracker-toggle-btn').first();
            if (!$btn.length) return;
            const count = $panel.find('.tracker-note-item').length;
            $btn.text(count > 0 ? ('My Tracker (' + count + ')') : 'Add to Tracker');
        }

        function ensureTrackerFlag($panel, noteText) {
            const $widget = $panel.closest('.tracker-inline-widget');
            let $flag = $widget.find('.tracker-flag').first();
            const text = (noteText || '').trim();
            if (!text) return;
            const shortText = text.length > 120 ? (text.substring(0, 117) + '...') : text;
            if (!$flag.length) {
                $flag = $('<div class="tracker-flag"></div>');
                $widget.prepend($flag);
            }
            $flag.text('📝 Tracker: ' + shortText);
        }

        $(document).on('click', '.tracker-toggle-btn', function() {
            const target = $(this).data('target');
            if (target) {
                $(target).slideToggle(150);
            }
        });

        $(document).on('click', '.tracker-save-btn', function() {
            const cfg = trackerConfig();
            if (!cfg.is_logged_in) {
                alert('Please log in to use tracker notes.');
                return;
            }

            const $button = $(this);
            const $panel = $button.closest('.tracker-panel');
            const $input = $panel.find('.tracker-note-input');
            const note = ($input.val() || '').trim();
            if (!note) {
                alert('Please add a note first.');
                return;
            }

            $button.prop('disabled', true).text('Saving...');

            $.post(cfg.ajax_url, {
                action: 'bricks_add_tracker_note',
                nonce: cfg.tracker_nonce,
                horse_name: $button.data('horse-name'),
                race_id: $button.data('race-id'),
                race_date: $button.data('race-date'),
                race_time: $button.data('race-time'),
                course: $button.data('course'),
                note: note
            }).done(function(response) {
                if (!response || !response.success) {
                    const msg = response && response.data && response.data.message ? response.data.message : 'Failed to save note.';
                    alert(msg);
                    return;
                }
                window.location.reload();
            }).fail(function() {
                alert('Failed to save tracker note. Please try again.');
            }).always(function() {
                $button.prop('disabled', false).text('Save Note');
            });
        });

        $(document).on('click', '.tracker-delete-btn', function() {
            const cfg = trackerConfig();
            if (!cfg.is_logged_in) {
                alert('Please log in to use tracker notes.');
                return;
            }
            if (!confirm('Delete this note?')) {
                return;
            }

            const $button = $(this);
            $button.prop('disabled', true).text('Deleting...');

            $.post(cfg.ajax_url, {
                action: 'bricks_delete_tracker_note',
                nonce: cfg.tracker_nonce,
                horse_name: $button.data('horse-name'),
                note_id: $button.data('note-id')
            }).done(function(response) {
                if (!response || !response.success) {
                    const msg = response && response.data && response.data.message ? response.data.message : 'Failed to delete note.';
                    alert(msg);
                    return;
                }
                window.location.reload();
            }).fail(function() {
                alert('Failed to delete tracker note. Please try again.');
            }).always(function() {
                $button.prop('disabled', false).text('Delete');
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'bricks_tracker_inline_js', 1000);

function bricks_tracker_inline_styles() {
    ?>
    <style>
    [title] { cursor: pointer; }
    .tracker-inline-widget { margin-top: 6px; }
    .tracker-flag { margin-top: 4px; font-size: 11px; font-weight: 500; color: #92400e; line-height: 1.4; }
    .tracker-toggle-btn,
    .tracker-save-btn,
    .tracker-delete-btn {
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: #fff;
        color: #111827;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        padding: 6px 10px;
    }
    .tracker-toggle-btn:hover,
    .tracker-save-btn:hover,
    .tracker-delete-btn:hover {
        border-color: #7c3aed;
        color: #6d28d9;
        background: #faf5ff;
    }
    .tracker-panel {
        margin-top: 8px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 8px;
        background: #fff;
    }
    .tracker-note-item { border-bottom: 1px solid #f3f4f6; padding-bottom: 8px; margin-bottom: 8px; }
    .tracker-note-item:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
    .tracker-note-meta { color: #6b7280; font-size: 11px; margin-bottom: 4px; }
    .tracker-note-text { font-size: 12px; color: #111827; margin-bottom: 6px; white-space: normal; }
    .tracker-note-input {
        width: 100%;
        min-height: 64px;
        margin-top: 8px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 8px;
        font-size: 12px;
        resize: vertical;
    }
    .tracker-save-btn { margin-top: 8px; }
    .tracker-guest-message, .tracker-empty { color: #6b7280; font-size: 12px; }
    .tracker-horse-header .tracker-toggle-btn { margin-top: 10px; }
    .tracker-summary-link {
        display: inline-flex;
        align-items: center;
        color: #7c2d12;
        background: rgba(255, 255, 255, 0.6);
        padding: 6px 10px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
    }
    .tracker-summary-link:hover {
        background: #fff;
        color: #9a3412;
    }
    </style>
    <?php
}
add_action('wp_head', 'bricks_tracker_inline_styles', 1000);

function bricks_tracker_floating_quick_link() {
    if (!is_user_logged_in() || is_admin() || get_query_var('my_tracker_page') || get_query_var('my_points_backtest')) {
        return;
    }
    ?>
    <div style="position:fixed;right:18px;bottom:18px;z-index:9999;display:flex;flex-direction:column;gap:8px;">
        <a
            href="<?php echo esc_url(home_url('/my-tracker/')); ?>"
            title="Open My Tracker"
            style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);color:#fff;font-weight:700;font-size:13px;text-decoration:none;box-shadow:0 8px 20px rgba(109,40,217,0.35);"
        >📝 My Tracker</a>
        <a
            href="<?php echo esc_url(home_url('/points-backtest/')); ?>"
            title="Open Points Backtest"
            style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);color:#fff;font-weight:700;font-size:13px;text-decoration:none;box-shadow:0 8px 20px rgba(30,64,175,0.35);"
        >📊 Points Backtest</a>
    </div>
    <?php
}
add_action('wp_footer', 'bricks_tracker_floating_quick_link', 1001);

function bricks_my_tracker_dashboard_shortcode($atts = []) {
    if (!is_user_logged_in()) {
        return '<div style="max-width:760px;margin:40px auto;padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
            <h2 style="margin:0 0 10px 0;color:#111827;">My Tracker</h2>
            <p style="margin:0;color:#6b7280;">Please log in to view and manage your tracked horses.</p>
        </div>';
    }

    $tracker_data = bricks_tracker_get_user_data(get_current_user_id());
    $rows = [];

    foreach ($tracker_data as $entry) {
        $horse_name = isset($entry['horse_name']) ? sanitize_text_field($entry['horse_name']) : '';
        $notes = (isset($entry['notes']) && is_array($entry['notes'])) ? $entry['notes'] : [];
        if ($horse_name === '' || empty($notes)) {
            continue;
        }

        $note_rows = [];
        foreach ($notes as $note_entry) {
            if (!is_array($note_entry)) {
                continue;
            }

            $note_text = isset($note_entry['note']) ? sanitize_textarea_field($note_entry['note']) : '';
            $note_created_at = isset($note_entry['created_at']) ? sanitize_text_field($note_entry['created_at']) : '';
            $note_course = isset($note_entry['course']) ? sanitize_text_field($note_entry['course']) : '';
            $note_id = isset($note_entry['id']) ? sanitize_text_field($note_entry['id']) : '';
            $note_race_date = isset($note_entry['race_date']) ? sanitize_text_field($note_entry['race_date']) : '';
            $note_race_time = isset($note_entry['race_time']) ? sanitize_text_field($note_entry['race_time']) : '';

            if ($note_id === '' || $note_text === '') {
                continue;
            }

            $note_rows[] = [
                'note' => $note_text,
                'created_at' => $note_created_at,
                'course' => $note_course,
                'note_id' => $note_id,
                'race_date' => $note_race_date,
                'race_time' => $note_race_time,
            ];
        }

        if (empty($note_rows)) {
            continue;
        }

        usort($note_rows, function($a, $b) {
            $a_ts = !empty($a['created_at']) ? strtotime($a['created_at']) : 0;
            $b_ts = !empty($b['created_at']) ? strtotime($b['created_at']) : 0;
            return $b_ts <=> $a_ts;
        });

        $latest = $note_rows[0];
        $rows[] = [
            'horse_name' => $horse_name,
            'notes_count' => count($note_rows),
            'latest_created_at' => $latest['created_at'],
            'latest_course' => $latest['course'],
            'latest_race_date' => $latest['race_date'],
            'latest_race_time' => $latest['race_time'],
            'notes' => $note_rows,
        ];
    }

    usort($rows, function($a, $b) {
        $a_ts = !empty($a['latest_created_at']) ? strtotime($a['latest_created_at']) : 0;
        $b_ts = !empty($b['latest_created_at']) ? strtotime($b['latest_created_at']) : 0;
        return $b_ts <=> $a_ts;
    });

    ob_start();
    ?>
    <div class="my-tracker-dashboard">
        <div class="my-tracker-header">
            <h1>📝 My Tracker</h1>
            <p>Tracked horses grouped with expandable comments.</p>
        </div>

        <?php if (empty($rows)): ?>
            <div class="my-tracker-empty">
                No tracked horses yet. Add notes from race cards, quick reference, or comments pages.
            </div>
        <?php else: ?>
            <div class="my-tracker-table-wrap">
                <table class="my-tracker-table">
                    <thead>
                        <tr>
                            <th>Horse</th>
                            <th>Latest Date</th>
                            <th>Latest Course</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $display_date = $row['latest_created_at'] ? date_i18n('d M Y H:i', strtotime($row['latest_created_at'])) : '-';
                            $meta_course = trim(implode(' | ', array_filter([
                                $row['latest_race_date'],
                                $row['latest_race_time'],
                                $row['latest_course']
                            ])));
                            ?>
                            <tr>
                                <td style="font-weight:700;"><?php echo esc_html($row['horse_name']); ?></td>
                                <td><?php echo esc_html($display_date); ?></td>
                                <td><?php echo esc_html($meta_course ?: '-'); ?></td>
                                <td>
                                    <details class="my-tracker-details">
                                        <summary>
                                            View Comments (<?php echo esc_html($row['notes_count']); ?>)
                                        </summary>
                                        <div class="my-tracker-notes-dropdown">
                                            <?php foreach ($row['notes'] as $note_row): ?>
                                                <?php
                                                $note_date = $note_row['created_at'] ? date_i18n('d M Y H:i', strtotime($note_row['created_at'])) : '-';
                                                $note_course = trim(implode(' | ', array_filter([
                                                    $note_row['race_date'],
                                                    $note_row['race_time'],
                                                    $note_row['course']
                                                ])));
                                                ?>
                                                <div class="my-tracker-note-item">
                                                    <div class="my-tracker-note-meta">
                                                        <?php echo esc_html($note_date); ?>
                                                        <?php if ($note_course): ?>
                                                            - <?php echo esc_html($note_course); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="my-tracker-note-text"><?php echo esc_html($note_row['note']); ?></div>
                                                    <button
                                                        type="button"
                                                        class="tracker-delete-btn my-tracker-delete-btn"
                                                        data-horse-name="<?php echo esc_attr($row['horse_name']); ?>"
                                                        data-note-id="<?php echo esc_attr($note_row['note_id']); ?>">
                                                        Delete
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <style>
    .my-tracker-dashboard { max-width: 1200px; margin: 24px auto; padding: 0 16px 30px; }
    .my-tracker-header h1 { margin: 0 0 6px; color: #111827; font-size: 30px; font-weight: 800; }
    .my-tracker-header p { margin: 0 0 16px; color: #6b7280; }
    .my-tracker-empty { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; color: #6b7280; }
    .my-tracker-table-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: auto; }
    .my-tracker-table { width: 100%; border-collapse: collapse; min-width: 900px; }
    .my-tracker-table th, .my-tracker-table td { padding: 12px; border-bottom: 1px solid #f3f4f6; text-align: left; }
    .my-tracker-table th { background: #f9fafb; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; color: #374151; }
    .my-tracker-details summary { cursor: pointer; font-weight: 600; color: #2563eb; }
    .my-tracker-notes-dropdown { margin-top: 8px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; padding: 10px; }
    .my-tracker-note-item { border-bottom: 1px solid #f3f4f6; padding: 10px 0; }
    .my-tracker-note-item:last-child { border-bottom: none; }
    .my-tracker-note-meta { font-size: 11px; color: #6b7280; margin-bottom: 6px; }
    .my-tracker-note-text { font-size: 13px; color: #111827; margin-bottom: 8px; white-space: normal; }
    .my-tracker-delete-btn { white-space: nowrap; }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('my_tracker_dashboard', 'bricks_my_tracker_dashboard_shortcode');

function bricks_my_tracker_rewrite_rules() {
    add_rewrite_rule('my-tracker/?$', 'index.php?my_tracker_page=1', 'top');
    add_rewrite_rule('points-backtest/?$', 'index.php?my_points_backtest=1', 'top');
}
add_action('init', 'bricks_my_tracker_rewrite_rules', 10);

function bricks_my_tracker_query_vars($vars) {
    $vars[] = 'my_tracker_page';
    $vars[] = 'my_points_backtest';
    return $vars;
}
add_filter('query_vars', 'bricks_my_tracker_query_vars');

function bricks_my_tracker_template_redirect() {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_tracker_uri = strpos($request_uri, '/my-tracker') !== false;
    $is_backtest_uri = strpos($request_uri, '/points-backtest') !== false;
    $is_tracker_qv = (bool) get_query_var('my_tracker_page');
    $is_backtest_qv = (bool) get_query_var('my_points_backtest');

    if ((!$is_tracker_qv && !$is_backtest_qv && !$is_tracker_uri && !$is_backtest_uri) || is_admin()) {
        return;
    }

    // Render points backtest in an isolated template to avoid third-party theme JS crashes.
    if ($is_backtest_qv || $is_backtest_uri) {
        status_header(200);
        nocache_headers();
        echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html(get_bloginfo('name') . ' - Points Backtest') . '</title></head><body style="margin:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">';
        echo '<main style="max-width:1280px;margin:0 auto;padding:18px 12px 30px;">';
        echo do_shortcode('[points_backtest]');
        echo '</main></body></html>';
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
    echo ($is_backtest_qv || $is_backtest_uri) ? do_shortcode('[points_backtest]') : do_shortcode('[my_tracker_dashboard]');
    echo '</div></main>';
    echo $footer;
    exit;
}
add_action('template_redirect', 'bricks_my_tracker_template_redirect', 1);

function bricks_flush_my_tracker_rewrite_rules_if_needed() {
    if (get_option('my_tracker_rewrite_rules_flushed') !== '4') {
        flush_rewrite_rules();
        update_option('my_tracker_rewrite_rules_flushed', '4');
    }
}
add_action('init', 'bricks_flush_my_tracker_rewrite_rules_if_needed', 999);

function bricks_add_my_tracker_menu_item($items, $args) {
    if (is_admin()) {
        return $items;
    }

    $tracker_url = home_url('/my-tracker/');
    $points_url = home_url('/points-backtest/');
    if (strpos($items, $tracker_url) !== false) {
        if (strpos($items, $points_url) !== false) {
            return $items;
        }
    }

    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    $active_class = (strpos($current_url, '/my-tracker') !== false) ? ' current-menu-item current_page_item' : '';
    $active_points_class = (strpos($current_url, '/points-backtest') !== false) ? ' current-menu-item current_page_item' : '';

    $items .= '<li class="menu-item menu-item-type-custom menu-item-my-tracker' . esc_attr($active_class) . '">
        <a href="' . esc_url($tracker_url) . '">My Tracker</a>
    </li>';
    $items .= '<li class="menu-item menu-item-type-custom menu-item-points-backtest' . esc_attr($active_points_class) . '">
        <a href="' . esc_url($points_url) . '">Points Backtest</a>
    </li>';

    return $items;
}
add_filter('wp_nav_menu_items', 'bricks_add_my_tracker_menu_item', 20, 2);

function bricks_get_speed_performance_filter_options() {
    if (get_query_var('horse_name') || get_query_var('runner_id')) {
        wp_send_json_error('Not available on horse pages');
        return;
    }
    
    global $wpdb;
    
    $date = !empty($_POST['date']) ? sanitize_text_field($_POST['date']) : date('d-m-Y');
    $date_dmy = convert_date_format($date, 'd-m-Y');
    $date_ymd = convert_date_format($date, 'Y-m-d');
    
    $table_name = 'speed&performance_table';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if (!$table_exists) {
        wp_send_json_error('Table does not exist');
        return;
    }
    
    $runners = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT `name` FROM `$table_name` WHERE (`Date` = %s OR `Date` = %s) AND `name` IS NOT NULL AND `name` != '' ORDER BY `name`", $date_dmy, $date_ymd
    ));
    
    $courses = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT `course` FROM `$table_name` WHERE (`Date` = %s OR `Date` = %s) AND `course` IS NOT NULL AND `course` != '' ORDER BY `course`", $date_dmy, $date_ymd
    ));
    
    $trainers = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT `trainer_name` FROM `$table_name` WHERE (`Date` = %s OR `Date` = %s) AND `trainer_name` IS NOT NULL AND `trainer_name` != '' ORDER BY `trainer_name`", $date_dmy, $date_ymd
    ));
    
    $jockeys = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT `jockey_name` FROM `$table_name` WHERE (`Date` = %s OR `Date` = %s) AND `jockey_name` IS NOT NULL AND `jockey_name` != '' ORDER BY `jockey_name`", $date_dmy, $date_ymd
    ));
    
    $distances = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT `Distance` FROM `$table_name` WHERE (`Date` = %s OR `Date` = %s) AND `Distance` IS NOT NULL AND `Distance` != '' ORDER BY `distance_yards`", $date_dmy, $date_ymd
    ));
    
    $race_types = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT `race_type` FROM `$table_name` WHERE (`Date` = %s OR `Date` = %s) AND `race_type` IS NOT NULL AND `race_type` != '' ORDER BY `race_type`", $date_dmy, $date_ymd
    ));

    wp_send_json([
        'runners' => $runners,
        'courses' => $courses,
        'trainers' => $trainers,
        'jockeys' => $jockeys,
        'distances' => $distances,
        'race_types' => $race_types
    ]);
}
add_action('wp_ajax_get_speed_performance_filter_options', 'bricks_get_speed_performance_filter_options');
add_action('wp_ajax_nopriv_get_speed_performance_filter_options', 'bricks_get_speed_performance_filter_options');

function bricks_ajax_load_speed_performance_table() {
    if (get_query_var('horse_name') || get_query_var('runner_id')) {
        echo '<div style="text-align:center;padding:20px;">Not available on horse pages</div>';
        wp_die();
    }
    
    global $wpdb;
    
    $per_page = 50;
    $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $offset = ($paged - 1) * $per_page;

    $where = '1=1';
    
    $date = !empty($_POST['date']) ? sanitize_text_field($_POST['date']) : date('d-m-Y');
    $date_dmy = convert_date_format($date, 'd-m-Y');
    $date_ymd = convert_date_format($date, 'Y-m-d');
    
    // Debug logging
    error_log("Speed Performance Debug - Date received: $date, Date d-m-Y: $date_dmy, Date Y-m-d: $date_ymd");
    
    // Build date condition using IN clause for better compatibility
    $date_dmy_escaped = esc_sql($date_dmy);
    $date_ymd_escaped = esc_sql($date_ymd);
    $where .= " AND `Date` IN ('$date_dmy_escaped', '$date_ymd_escaped')";

    if (!empty($_POST['runner'])) {
        $where .= $wpdb->prepare(" AND `name` = %s", $_POST['runner']);
    }
    if (!empty($_POST['course'])) {
        $where .= $wpdb->prepare(" AND `course` = %s", $_POST['course']);
    }
    if (!empty($_POST['trainer'])) {
        $where .= $wpdb->prepare(" AND `trainer_name` = %s", $_POST['trainer']);
    }
    if (!empty($_POST['jockey'])) {
        $where .= $wpdb->prepare(" AND `jockey_name` = %s", $_POST['jockey']);
    }
    if (!empty($_POST['distance'])) {
        $where .= $wpdb->prepare(" AND `Distance` = %s", $_POST['distance']);
    }
    if (!empty($_POST['race_type'])) {
        $where .= $wpdb->prepare(" AND `race_type` = %s", $_POST['race_type']);
    }
    if (isset($_POST['min_fsr']) && $_POST['min_fsr'] !== '') {
        $min_fsr = floatval($_POST['min_fsr']);
        $where .= $wpdb->prepare(" AND fhorsite_rating >= %f", $min_fsr);
    }

    $table = '`speed&performance_table`';
    
    // Debug: Check what dates exist in the table
    $sample_dates = $wpdb->get_col("SELECT DISTINCT `Date` FROM $table ORDER BY `Date` DESC LIMIT 5");
    error_log("Speed Performance Debug - Sample dates in table: " . print_r($sample_dates, true));
    
    // Build the full query for debugging
    $full_query = "SELECT COUNT(*) FROM $table WHERE $where";
    error_log("Speed Performance Debug - Full query: $full_query");
    
    $total_records = $wpdb->get_var($full_query);
    error_log("Speed Performance Debug - Total records found: $total_records");
    
    // Also test a simple query to see if any records exist at all
    $all_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    error_log("Speed Performance Debug - Total records in table: $all_count");
    
    $order_by = '`Time`, `course`';
    $allowed_sorts = [
        'Time', 'course', 'Distance', 'name', 'age', 'trainer_name', 'jockey_name',
        'official_rating', 'forecast_price', 'SR_LTO', 'fhorsite_rating',
        'cloth_number', 'stall_number', 'weight_pounds', 'days_since_ran'
    ];
    
    if (!empty($_POST['sort_column']) && in_array($_POST['sort_column'], $allowed_sorts)) {
        $direction = (!empty($_POST['sort_direction']) && $_POST['sort_direction'] === 'desc') ? 'DESC' : 'ASC';
        $order_by = '`' . $_POST['sort_column'] . '` ' . $direction;
        $order_by_sql = $order_by;
    } else {
        // Default: Sort races by minimum cloth_number within each race group, then by Time and course
        // This ensures races appear in numerical order based on their cloth numbers
        // Use a subquery to calculate minimum cloth_number per race for sorting
        $order_by_sql = "(SELECT MIN(`cloth_number`) FROM $table t2 WHERE t2.`race_id` = $table.`race_id` AND t2.`Date` = $table.`Date`) ASC, `Time`, `course`, `cloth_number`";
    }

    $results = $wpdb->get_results("SELECT 
        `race_id`, `runner_id`, `Date`, `Time`, `course`, `Distance`, `distance_yards`,
        `name`, `age`, `gender`, `colour`, `trainer_name`, `jockey_name`, `jockey_claim`,
        `official_rating`, `forecast_price`, `forecast_price_decimal`,
        `cloth_number`, `stall_number`, `weight_pounds`, `days_since_ran`,
        `form_figures`, `prev_runner_win_strike`, `prev_runner_place_strike`,
        `course_winner`, `distance_winner`, `going_prev_wins`, `candd_winner`, `beaten_favourite`,
        `TnrRuns14d`, `TnrWins14d`, `TnrPlaced14d`, `TnrWinPct14d`, `TnrWinProfit14d`,
        `JkyRuns14d`, `JkyWins14d`, `JkyPlaced14d`, `JkyWinPct14d`, `JkyPlcPct14d`, `JkyWinProfit14d`,
        `meeting_date_LTO`, `wt_speed_rating_LTO`, `SR_LTO`, `distance_furlongs_LTO`, `class_LTO`,
        `SR_2`, `DF_2`, `CL_2`, `SR_3`, `DF_3`, `CL_3`, `SR_4`, `DF_4`, `CL_4`, 
        `SR_5`, `DF_5`, `CL_5`, `SR_6`, `DF_6`, `CL_6`,
        `race_title`, `weather`, `draw_advantage`, `race_type`, `track_type`, 
        `advanced_going`, `class`, `handicap`, `age_range`, `prize_pos_1`,
        `draw_bias_pct`, `fhorsite_rating`
        FROM $table
        WHERE $where
        ORDER BY $order_by_sql
        LIMIT $per_page OFFSET $offset");
    
    $total_pages = ceil($total_records / $per_page);

    ob_start();

    $tracker_data = is_user_logged_in() ? bricks_tracker_get_user_data(get_current_user_id()) : [];

    if ($results) {
        $current_course = '';
        echo '<div class="speed-performance-table-scroll">
        <table class="speed-performance-table">
            <thead>
                <tr>
                    <th data-sort="name" class="sortable">Horse</th>
                    <th data-sort="age" class="sortable">Age</th>
                    <th data-sort="cloth_number" class="sortable">No.</th>
                    <th data-sort="stall_number" class="sortable">Draw</th>
                    <th data-sort="trainer_name" class="sortable">Trainer</th>
                    <th data-sort="jockey_name" class="sortable">Jockey</th>
                    <th data-sort="official_rating" class="sortable">OR</th>
                    <th data-sort="weight_pounds" class="sortable">Weight</th>
                    <th data-sort="forecast_price" class="sortable">Price</th>
                    <th data-sort="fhorsite_rating" class="sortable">FSr</th>
                    <th data-sort="SR_LTO" class="sortable">Speed LTO</th>
                    <th data-sort="days_since_ran" class="sortable">Days</th>
                    <th>Form</th>
                    <th>Wins</th>
                    <th>Draw Bias</th>
                    <th>My Tracker</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($results as $row) {
            $time_formatted = $row->Time ? date('H:i', strtotime($row->Time)) : 'N/A';
            $distance_formatted = $row->Distance ?: 'N/A';
            $race_title = $row->race_title ?: 'Race';
            $race_link = bricks_race_url($row->race_id);
            
            // Check if we need a new header row (when course, time, race, or distance changes)
            $header_key = $row->course . '|' . $time_formatted . '|' . $row->race_id . '|' . $distance_formatted;
            if ($header_key !== $current_course) {
                $current_course = $header_key;
                echo '<tr data-course-header="true"><td colspan="16">
                    <div class="course-header-content">
                        <div class="course-header-item">
                            <span class="course-header-label">📍 Course:</span>
                            <span class="course-header-value">' . esc_html($row->course ?: 'N/A') . '</span>
                        </div>
                        <div class="course-header-item">
                            <span class="course-header-label">🕐 Time:</span>
                            <span class="course-header-value">' . esc_html($time_formatted) . '</span>
                        </div>
                        <div class="course-header-item">
                            <span class="course-header-label">🏁 Race:</span>
                            <a href="' . esc_url($race_link) . '" class="course-header-value race-link">' . esc_html($race_title) . '</a>
                        </div>
                        <div class="course-header-item">
                            <span class="course-header-label">📏 Distance:</span>
                            <span class="course-header-value">' . esc_html($distance_formatted) . '</span>
                        </div>
                    </div>
                </td></tr>';
            }
            $age_gender = $row->age . ($row->gender ?: '');
            
            $weight_formatted = 'N/A';
            if ($row->weight_pounds) {
                $stones = floor($row->weight_pounds / 14);
                $pounds = $row->weight_pounds % 14;
                $weight_formatted = $stones . '-' . $pounds;
            }
            
            $jockey_claim = $row->jockey_claim ? '(' . $row->jockey_claim . ')' : '';
            $price_formatted = $row->forecast_price ?: 'N/A';
            $speed_formatted = $row->SR_LTO ?: '-';
            $days_formatted = $row->days_since_ran !== null ? $row->days_since_ran : '-';
            $form_formatted = $row->form_figures ? substr($row->form_figures, 0, 10) : '-';
            
            // Build wins display with counts (matching race pages format)
            $course_wins = isset($row->course_winner) ? intval($row->course_winner) : 0;
            $distance_wins = isset($row->distance_winner) ? intval($row->distance_winner) : 0;
            $candd_wins = isset($row->candd_winner) ? intval($row->candd_winner) : 0;
            $going_wins = isset($row->going_prev_wins) ? intval($row->going_prev_wins) : 0;
            $lbf_count = isset($row->beaten_favourite) ? intval($row->beaten_favourite) : 0;
            
            $win_parts = [];
            if ($candd_wins > 0) {
                $win_parts[] = '<span class="win-badge">CD' . ($candd_wins > 1 ? 'x' . $candd_wins : '') . '</span>';
            }
            if ($course_wins > 0 && $candd_wins == 0) {
                $win_parts[] = '<span class="win-badge">C' . ($course_wins > 1 ? 'x' . $course_wins : '') . '</span>';
            }
            if ($distance_wins > 0 && $candd_wins == 0) {
                $win_parts[] = '<span class="win-badge">D' . ($distance_wins > 1 ? 'x' . $distance_wins : '') . '</span>';
            }
            if ($going_wins > 0) {
                $win_parts[] = '<span class="win-badge win-badge-going">G' . ($going_wins > 1 ? 'x' . $going_wins : '') . '</span>';
            }
            if ($lbf_count > 0) {
                $win_parts[] = '<span class="win-badge win-badge-lbf">LBF' . ($lbf_count > 1 ? 'x' . $lbf_count : '') . '</span>';
            }
            
            $wins_display = !empty($win_parts) ? implode(' ', $win_parts) : '<span style="color:#9ca3af;">-</span>';
            
            $draw_bias = $row->draw_bias_pct ? round($row->draw_bias_pct, 1) . '%' : '-';
            $horse_name = $row->name ?: '';
            $horse_key = bricks_tracker_normalize_horse_key($horse_name);
            $horse_notes = (!empty($tracker_data[$horse_key]['notes']) && is_array($tracker_data[$horse_key]['notes'])) ? $tracker_data[$horse_key]['notes'] : [];
            $tracker_count = count($horse_notes);
            $latest_tracker_note = $tracker_count > 0 ? end($horse_notes) : null;
            $tracker_cell = '<span class="tracker-guest-message">Log in to use tracker</span>';

            if (is_user_logged_in()) {
                $panel_id = 'tracker-panel-' . md5($horse_key . '|' . $row->race_id . '|' . $row->Time);
                $tracker_button_text = $tracker_count > 0 ? 'View Notes (' . $tracker_count . ')' : 'Add Note';
                $tracker_notes_html = '';

                if ($tracker_count > 0) {
                    foreach ($horse_notes as $note_entry) {
                        $note_id = esc_attr($note_entry['id'] ?? '');
                        $note_text = esc_html($note_entry['note'] ?? '');
                        $note_date = !empty($note_entry['created_at']) ? esc_html(date_i18n('d M Y H:i', strtotime($note_entry['created_at']))) : '';
                        $note_race = trim(implode(' | ', array_filter([
                            $note_entry['race_date'] ?? '',
                            $note_entry['race_time'] ?? '',
                            $note_entry['course'] ?? ''
                        ])));

                        $tracker_notes_html .= '<div class="tracker-note-item">
                            <div class="tracker-note-meta">' . $note_date . ($note_race ? ' - ' . esc_html($note_race) : '') . '</div>
                            <div class="tracker-note-text">' . nl2br($note_text) . '</div>
                            <button type="button" class="tracker-delete-btn" data-horse-name="' . esc_attr($horse_name) . '" data-note-id="' . $note_id . '">Delete</button>
                        </div>';
                    }
                } else {
                    $tracker_notes_html = '<div class="tracker-empty">No notes yet for this horse.</div>';
                }

                $tracker_cell = '<button type="button" class="tracker-toggle-btn" data-target="#' . esc_attr($panel_id) . '">' . esc_html($tracker_button_text) . '</button>
                <div id="' . esc_attr($panel_id) . '" class="tracker-panel" style="display:none;">
                    <div class="tracker-notes-list">' . $tracker_notes_html . '</div>
                    <textarea class="tracker-note-input" rows="3" placeholder="Add your note about this horse..."></textarea>
                    <button type="button" class="tracker-save-btn"
                        data-horse-name="' . esc_attr($horse_name) . '"
                        data-race-id="' . esc_attr($row->race_id) . '"
                        data-race-date="' . esc_attr($row->Date) . '"
                        data-race-time="' . esc_attr($time_formatted) . '"
                        data-course="' . esc_attr($row->course) . '">Save Note</button>
                </div>';
            }
            
            // Speed rating styling
            $speed_class = '';
            $speed_value = floatval($speed_formatted);
            if ($speed_value >= 90) {
                $speed_class = 'speed-excellent';
            } elseif ($speed_value >= 80) {
                $speed_class = 'speed-good';
            } elseif ($speed_value >= 70) {
                $speed_class = 'speed-average';
            }

            // Fhorsite Rating styling
            $fsr_formatted = $row->fhorsite_rating ?: '-';
            $fsr_class = '';
            $fsr_value = floatval($fsr_formatted);
            if ($fsr_value >= 90) {
                $fsr_class = 'speed-excellent';
            } elseif ($fsr_value >= 80) {
                $fsr_class = 'speed-good';
            } elseif ($fsr_value >= 70) {
                $fsr_class = 'speed-average';
            }
            
            $tracker_flag = '';
            if (!empty($latest_tracker_note) && !empty($latest_tracker_note['note'])) {
                $tracker_flag = '<div class="tracker-flag">📝 Tracker: ' . esc_html(wp_trim_words($latest_tracker_note['note'], 12, '...')) . '</div>';
            }

            echo '<tr' . ($tracker_count > 0 ? ' class="tracker-row-highlight"' : '') . '>
                <td style="font-weight:600;">' . esc_html($row->name ?: 'N/A') . $tracker_flag . '</td>
                <td style="color:#6b7280;">' . esc_html($age_gender) . '</td>
                <td><span class="cloth-number">' . esc_html($row->cloth_number ?: '') . '</span></td>
                <td style="text-align:center;">' . esc_html($row->stall_number ?: '-') . '</td>
                <td>' . esc_html($row->trainer_name ?: 'N/A') . '</td>
                <td>' . esc_html($row->jockey_name ?: 'N/A') . ' <span style="color:#6b7280;font-size:11px;">' . esc_html($jockey_claim) . '</span></td>
                <td style="text-align:center;font-weight:600;">' . esc_html($row->official_rating ?: '-') . '</td>
                <td style="font-family:monospace;">' . esc_html($weight_formatted) . '</td>
                <td style="color:#059669;font-weight:600;">' . esc_html($price_formatted) . '</td>
                <td><span class="' . $fsr_class . '">' . esc_html($fsr_formatted) . '</span></td>
                <td><span class="' . $speed_class . '">' . esc_html($speed_formatted) . '</span></td>
                <td style="text-align:center;color:#6b7280;">' . esc_html($days_formatted) . '</td>
                <td style="font-family:monospace;font-size:11px;">' . esc_html($form_formatted) . '</td>
                <td>' . $wins_display . '</td>
                <td style="text-align:center;color:#6b7280;">' . esc_html($draw_bias) . '</td>
                <td class="tracker-cell">' . $tracker_cell . '</td>
            </tr>';
        }

        echo '</tbody></table></div>';

        if ($total_pages > 1) {
            echo '<div class="speed-performance-pagination-wrapper">';

            if ($paged > 1) {
                echo '<a class="speed-performance-pagination-btn" href="#" data-page="' . ($paged - 1) . '">&laquo; Prev</a>';
            }

            $start_page = max(1, $paged - 2);
            $end_page = min($total_pages, $paged + 2);

            if ($start_page > 1) {
                echo '<a class="speed-performance-pagination-btn" href="#" data-page="1">1</a>';
                if ($start_page > 2) echo '<span style="padding:0 8px;color:#6b7280;">...</span>';
            }

            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = $i == $paged ? ' speed-performance-pagination-btn-active' : '';
                echo '<a class="speed-performance-pagination-btn' . $active . '" href="#" data-page="' . $i . '">' . $i . '</a>';
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) echo '<span style="padding:0 8px;color:#6b7280;">...</span>';
                echo '<a class="speed-performance-pagination-btn" href="#" data-page="' . $total_pages . '">' . $total_pages . '</a>';
            }

            if ($paged < $total_pages) {
                echo '<a class="speed-performance-pagination-btn" href="#" data-page="' . ($paged + 1) . '">Next &raquo;</a>';
            }

            echo '</div>';
        }
    } else {
        echo '<div style="text-align:center;padding:40px;color:#6b7280;">
            <div style="font-size:48px;margin-bottom:16px;">🔍</div>
            <div style="font-size:18px;font-weight:600;margin-bottom:8px;">No results found</div>
            <div style="font-size:14px;">Try adjusting your filters or selecting a different date</div>
        </div>';
    }

    wp_die(ob_get_clean());
}
add_action('wp_ajax_load_speed_performance_table', 'bricks_ajax_load_speed_performance_table');
add_action('wp_ajax_nopriv_load_speed_performance_table', 'bricks_ajax_load_speed_performance_table');

// ==============================================
// SHORTCODE FOR DISPLAY
// ==============================================

function bricks_speed_performance_shortcode() {
    global $wpdb;

    $today = new DateTimeImmutable();
    $today_date_dmy = $today->format('d-m-Y');
    $today_date_ymd = $today->format('Y-m-d');
    $navigation_header = bricks_get_navigation_header();
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE 'speed&performance_table'");
    if (!$table_exists) {
        return '<div style="color:red;padding:20px;">Error: Database table "speed&performance_table" not found</div>';
    }

    $runners = $wpdb->get_col(
        $wpdb->prepare("SELECT DISTINCT `name` FROM `speed&performance_table` WHERE (`Date` = %s OR `Date` = %s) AND `name` IS NOT NULL AND `name` != '' ORDER BY `name`", $today_date_dmy, $today_date_ymd)
    );
    
    $courses = $wpdb->get_col(
        $wpdb->prepare("SELECT DISTINCT `course` FROM `speed&performance_table` WHERE (`Date` = %s OR `Date` = %s) AND `course` IS NOT NULL AND `course` != '' ORDER BY `course`", $today_date_dmy, $today_date_ymd)
    );
    
    $trainers = $wpdb->get_col(
        $wpdb->prepare("SELECT DISTINCT `trainer_name` FROM `speed&performance_table` WHERE (`Date` = %s OR `Date` = %s) AND `trainer_name` IS NOT NULL AND `trainer_name` != '' ORDER BY `trainer_name`", $today_date_dmy, $today_date_ymd)
    );
    
    $jockeys = $wpdb->get_col(
        $wpdb->prepare("SELECT DISTINCT `jockey_name` FROM `speed&performance_table` WHERE (`Date` = %s OR `Date` = %s) AND `jockey_name` IS NOT NULL AND `jockey_name` != '' ORDER BY `jockey_name`", $today_date_dmy, $today_date_ymd)
    );

    $distances = $wpdb->get_col(
        $wpdb->prepare("SELECT DISTINCT `Distance` FROM `speed&performance_table` WHERE (`Date` = %s OR `Date` = %s) AND `Distance` IS NOT NULL AND `Distance` != '' ORDER BY `distance_yards`", $today_date_dmy, $today_date_ymd)
    );
    
    $race_types = $wpdb->get_col(
        $wpdb->prepare("SELECT DISTINCT `race_type` FROM `speed&performance_table` WHERE (`Date` = %s OR `Date` = %s) AND `race_type` IS NOT NULL AND `race_type` != '' ORDER BY `race_type`", $today_date_dmy, $today_date_ymd)
    );

    // Date tabs removed - always use today's date

    ob_start();
    ?>
    <style>
        /* Quick Reference Modern Styling */
        .speed-table-wrapper {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 24px;
            margin-bottom: 30px;
        }

        /* Make table area its own scroll container so sticky header doesn't overlap first data row */
        #speed-performance-table-container {
            max-height: 72vh;
            overflow: auto;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            position: relative;
        }
        .speed-performance-table-scroll {
            overflow: visible;
        }

        .speed-performance-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .speed-filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .speed-filter-group label {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .speed-performance-filters select,
        .speed-performance-filters input {
            padding: 10px 12px;
            font-size: 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            transition: all 0.2s ease;
            outline: none;
        }

        .speed-performance-filters select:focus,
        .speed-performance-filters input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        #speed-performance-reset-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
        }

        #speed-performance-reset-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }

        /* Date tabs removed */

        .speed-performance-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }

        .speed-performance-table th,
        .speed-performance-table td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .speed-performance-table thead th {
            background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%);
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            position: sticky !important;
            top: 0 !important;
            z-index: 30 !important;
            border-bottom: 2px solid #d1d5db;
            box-shadow: 0 1px 0 rgba(17, 24, 39, 0.08);
            background-clip: padding-box;
        }

        /* Reinforce sticky header behavior inside the table scroll container */
        #speed-performance-table-container .speed-performance-table thead {
            position: static !important;
        }
        #speed-performance-table-container .speed-performance-table thead th {
            position: sticky;
            top: 0 !important;
            z-index: 30 !important;
        }

        .speed-performance-table tbody tr {
            transition: all 0.2s ease;
        }

        .speed-performance-table tbody tr:hover {
            background: #f9fafb;
        }

        .speed-performance-table tbody tr.tracker-row-highlight {
            background: #fffbeb;
        }

        .tracker-flag {
            margin-top: 4px;
            font-size: 11px;
            font-weight: 500;
            color: #92400e;
            line-height: 1.4;
        }

        .tracker-cell {
            min-width: 230px;
            vertical-align: top;
        }

        .tracker-toggle-btn,
        .tracker-save-btn,
        .tracker-delete-btn {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            color: #111827;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            padding: 6px 10px;
        }

        .tracker-toggle-btn:hover,
        .tracker-save-btn:hover,
        .tracker-delete-btn:hover {
            border-color: #7c3aed;
            color: #6d28d9;
            background: #faf5ff;
        }

        .tracker-panel {
            margin-top: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px;
            background: #ffffff;
        }

        .tracker-note-item {
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .tracker-note-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }

        .tracker-note-meta {
            color: #6b7280;
            font-size: 11px;
            margin-bottom: 4px;
        }

        .tracker-note-text {
            font-size: 12px;
            color: #111827;
            margin-bottom: 6px;
            white-space: normal;
        }

        .tracker-note-input {
            width: 100%;
            min-height: 64px;
            margin-top: 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px;
            font-size: 12px;
            resize: vertical;
        }

        .tracker-save-btn {
            margin-top: 8px;
        }

        .tracker-guest-message,
        .tracker-empty {
            color: #6b7280;
            font-size: 12px;
        }

        .speed-performance-table tbody tr[data-course-header] {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        }

        .speed-performance-table tbody tr[data-course-header] td {
            color: white;
            font-weight: 700;
            font-size: 14px;
            padding: 16px 20px;
            border: none;
            text-align: center;
        }

        .course-header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .course-header-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .course-header-label {
            font-size: 11px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .course-header-value {
            font-size: 15px;
            font-weight: 700;
        }

        .course-header-value.race-link {
            color: white;
            text-decoration: underline;
            text-decoration-color: rgba(255, 255, 255, 0.6);
            transition: all 0.2s ease;
        }

        .course-header-value.race-link:hover {
            text-decoration-color: white;
            opacity: 1;
            text-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
        }

        .race-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .race-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 24px;
        }

        th.sortable:hover {
            background: #e5e7eb;
        }

        th.sortable::after {
            content: '⇅';
            position: absolute;
            right: 8px;
            opacity: 0.3;
            font-size: 12px;
        }

        th.sortable.sorted-asc::after {
            content: '↑';
            opacity: 1;
            color: #8b5cf6;
        }

        th.sortable.sorted-desc::after {
            content: '↓';
            opacity: 1;
            color: #8b5cf6;
        }

        th.sortable.active-column {
            background: #f5f3ff;
            color: #6d28d9;
        }

        /* Speed Rating Badges */
        .speed-excellent {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-weight: 700;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        }

        .speed-good {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            font-weight: 700;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }

        .speed-average {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            font-weight: 700;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
        }

        /* Cloth Number Badge */
        .cloth-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 2px 6px rgba(139, 92, 246, 0.3);
        }

        /* Win Badges */
        .win-badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 4px;
            background: #10b981;
            color: white;
            font-size: 10px;
            font-weight: 700;
            margin-right: 4px;
        }
        
        .win-badge-going {
            background: #3b82f6;
        }
        
        .win-badge-lbf {
            background: #f59e0b;
        }

        /* Pagination */
        .speed-performance-pagination-wrapper {
            margin-top: 24px;
            text-align: center;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
        }

        .speed-performance-pagination-btn {
            display: inline-block;
            padding: 10px 16px;
            margin: 0 4px;
            background: white;
            border: 2px solid #e5e7eb;
            color: #374151;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .speed-performance-pagination-btn:hover {
            border-color: #8b5cf6;
            background: #f5f3ff;
            color: #7c3aed;
            transform: translateY(-1px);
        }

        .speed-performance-pagination-btn-active {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white !important;
            border-color: #8b5cf6;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
        }

        @media (max-width: 768px) {
            .speed-table-wrapper {
                padding: 16px;
                margin-bottom: 20px;
            }
            
            .speed-performance-filters {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 16px;
            }
            
            .speed-filter-group label {
                font-size: 10px;
            }
            
            .speed-performance-filters select {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 12px;
            }
            
            #speed-performance-reset-btn {
                width: 100%;
                padding: 12px;
                font-size: 14px;
            }
            
            /* Date tabs removed */
            
            .speed-table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .speed-performance-table {
                font-size: 11px;
                min-width: 600px; /* Ensure table doesn't get too compressed */
            }

            .speed-performance-table th,
            .speed-performance-table td {
                padding: 8px 6px;
            }
            
            .speed-performance-table thead th {
                font-size: 10px;
                padding: 10px 6px;
            }

            .speed-performance-table tbody tr[data-course-header] td {
                padding: 12px 10px;
                font-size: 12px;
            }

            .course-header-content {
                gap: 12px;
            }

            .course-header-item {
                gap: 4px;
            }

            .course-header-label {
                font-size: 9px;
            }

            .course-header-value {
                font-size: 12px;
            }
            
            .speed-performance-pagination-wrapper {
                padding: 12px;
            }
            
            .speed-performance-pagination-btn {
                padding: 8px 12px;
                font-size: 12px;
                margin: 2px;
            }
            
            .speed-excellent,
            .speed-good,
            .speed-average {
                font-size: 11px;
                padding: 3px 8px;
            }
            
            .cloth-number {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .speed-table-wrapper {
                padding: 12px;
            }
            
            .speed-performance-filters {
                padding: 12px;
            }
            
            .speed-performance-table {
                font-size: 10px;
            }
            
            .speed-performance-table th,
            .speed-performance-table td {
                padding: 6px 4px;
            }

            .speed-performance-table tbody tr[data-course-header] td {
                padding: 10px 8px;
                font-size: 11px;
            }

            .course-header-content {
                gap: 8px;
            }

            .course-header-label {
                font-size: 8px;
            }

            .course-header-value {
                font-size: 11px;
            }
            
            .speed-performance-pagination-btn {
                padding: 6px 10px;
                font-size: 11px;
            }
        }
    </style>

    <div class="speed-table-wrapper">
        <div class="speed-performance-filters">
            <div class="speed-filter-group">
                <label for="speed-performance-runner-filter">🏇 Runner:</label>
                <select id="speed-performance-runner-filter" class="speed-performance-filter">
                    <option value="">All Runners</option>
                    <?php foreach ($runners as $runner): ?>
                        <option value="<?= esc_attr($runner) ?>"><?= esc_html($runner) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="speed-filter-group">
                <label for="speed-performance-course-filter">📍 Course:</label>
                <select id="speed-performance-course-filter" class="speed-performance-filter">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= esc_attr($course) ?>"><?= esc_html($course) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="speed-filter-group">
                <label for="speed-performance-trainer-filter">👤 Trainer:</label>
                <select id="speed-performance-trainer-filter" class="speed-performance-filter">
                    <option value="">All Trainers</option>
                    <?php foreach ($trainers as $trainer): ?>
                        <option value="<?= esc_attr($trainer) ?>"><?= esc_html($trainer) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="speed-filter-group">
                <label for="speed-performance-jockey-filter">🎽 Jockey:</label>
                <select id="speed-performance-jockey-filter" class="speed-performance-filter">
                    <option value="">All Jockeys</option>
                    <?php foreach ($jockeys as $jockey): ?>
                        <option value="<?= esc_attr($jockey) ?>"><?= esc_html($jockey) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="speed-filter-group">
                <label for="speed-performance-distance-filter">📏 Distance:</label>
                <select id="speed-performance-distance-filter" class="speed-performance-filter">
                    <option value="">All Distances</option>
                    <?php foreach ($distances as $distance): ?>
                        <option value="<?= esc_attr($distance) ?>"><?= esc_html($distance) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="speed-filter-group">
                <label for="speed-performance-race-type-filter">🏁 Race Type:</label>
                <select id="speed-performance-race-type-filter" class="speed-performance-filter">
                    <option value="">All Race Types</option>
                    <?php foreach ($race_types as $race_type): ?>
                        <option value="<?= esc_attr($race_type) ?>"><?= esc_html($race_type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="speed-filter-group">
                <label for="speed-performance-fsr-filter">⭐ Min FSr:</label>
                <input type="number" id="speed-performance-fsr-filter" class="speed-performance-filter" placeholder="e.g. 80" min="0" max="150" step="1">
            </div>

            <div class="speed-filter-group" style="display:flex;align-items:flex-end;">
                <button type="button" id="speed-performance-reset-btn" style="width:100%;">Reset Filters</button>
            </div>
        </div>

        <div id="speed-performance-table-container">
            <div style="text-align:center;padding:60px 20px;color:#6b7280;">
                <div style="font-size:48px;margin-bottom:16px;">⚡</div>
                <div style="font-size:16px;font-weight:600;">Loading speed data...</div>
            </div>
        </div>
    </div>

    <?php
     $content = ob_get_clean();
    return $content;
}

// Register shortcode
add_shortcode('speed_performance_table', 'bricks_speed_performance_shortcode');


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

if (!function_exists('bricks_points_backtest_fetch_rows')) {
    function bricks_points_backtest_fetch_rows($from_date, $to_date, $race_type_filter = '') {
        global $wpdb;

        $historic_runners = 'historic_runners_beta';
        $historic_races = 'historic_races_beta';
        $daily_races = 'daily_races_beta';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$historic_runners'");
        if (!$table_exists) return [];
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$historic_races'");
        if (!$table_exists) return [];

        $race_type_expr = bricks_points_table_has_column($daily_races, 'race_type')
            ? "COALESCE(dracb.race_type, hracb.race_type)"
            : "hracb.race_type";

        $select_parts = [
            "hrunb.race_id AS race_id",
            "hracb.meeting_date AS meeting_date",
            "hracb.course AS course",
            "$race_type_expr AS race_type",
            "hrunb.name AS horse_name",
            "hrunb.trainer_name AS trainer_name",
            "hrunb.finish_position AS finish_position",
            "hrunb.starting_price AS starting_price"
        ];

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
            LEFT JOIN `$daily_races` dracb ON dracb.race_id = hrunb.race_id
            WHERE hracb.meeting_date BETWEEN %s AND %s
              AND hrunb.name IS NOT NULL
              AND hrunb.name != ''";

        $params = [$from_date, $to_date];
        if ($race_type_filter !== '') {
            $sql .= " AND $race_type_expr = %s";
            $params[] = $race_type_filter;
        }
        $sql .= " ORDER BY hracb.meeting_date ASC, hrunb.race_id ASC";

        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
}

if (!function_exists('bricks_points_backtest_calculate')) {
    function bricks_points_backtest_calculate($from_date, $to_date, $race_type_filter = '') {
        $rows = bricks_points_backtest_fetch_rows($from_date, $to_date, $race_type_filter);
        if (empty($rows)) {
            return [
                'summary' => [],
                'sample_rows' => [],
                'race_count' => 0,
                'runner_count' => 0
            ];
        }

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

        foreach ($by_race as $race_id => $race_rows) {
            $field_size = count($race_rows);
            if ($field_size < 2) continue;

            $scored = [];
            foreach ($race_rows as $idx => $rr) {
                $speed_data = (object) [
                    'fhorsite_rating' => (isset($rr->speed_rating) && is_numeric($rr->speed_rating)) ? $rr->speed_rating : null,
                    'fhorsite_rating_reliability' => 60,
                    'SR_LTO' => (isset($rr->wt_speed_rating) && is_numeric($rr->wt_speed_rating)) ? $rr->wt_speed_rating : null,
                    'days_since_ran' => (isset($rr->days_since_ran) && is_numeric($rr->days_since_ran)) ? $rr->days_since_ran : null,
                    'draw_bias_pct' => (isset($rr->draw_bias_pct) && is_numeric($rr->draw_bias_pct)) ? $rr->draw_bias_pct : null,
                    'class_diff' => (isset($rr->class_diff) && is_numeric($rr->class_diff)) ? $rr->class_diff : null,
                    'official_rating_diff' => (isset($rr->official_rating_diff) && is_numeric($rr->official_rating_diff)) ? $rr->official_rating_diff : null,
                    'course_winner' => (isset($rr->course_winner) && is_numeric($rr->course_winner)) ? $rr->course_winner : null,
                    'distance_winner' => (isset($rr->distance_winner) && is_numeric($rr->distance_winner)) ? $rr->distance_winner : null,
                    'candd_winner' => (isset($rr->candd_winner) && is_numeric($rr->candd_winner)) ? $rr->candd_winner : null,
                    'going_prev_wins' => (isset($rr->going_prev_wins) && is_numeric($rr->going_prev_wins)) ? $rr->going_prev_wins : null,
                    'beaten_favourite' => (isset($rr->beaten_favourite) && is_numeric($rr->beaten_favourite)) ? $rr->beaten_favourite : null,
                    'TnrWinPct14d' => (isset($rr->TnrWinPct14d) && is_numeric($rr->TnrWinPct14d)) ? $rr->TnrWinPct14d : null,
                    'TnrJkyPlacePct' => (isset($rr->TnrJkyPlacePct) && is_numeric($rr->TnrJkyPlacePct)) ? $rr->TnrJkyPlacePct : null,
                    'forecast_price_decimal' => (isset($rr->forecast_price_decimal) && is_numeric($rr->forecast_price_decimal)) ? $rr->forecast_price_decimal : null,
                    'forecast_price' => isset($rr->starting_price) ? $rr->starting_price : ''
                ];

                $pts = bricks_points_score_runner($rr, $speed_data, ['is_flat' => true]);
                $odds_decimal = bricks_points_parse_decimal_odds($speed_data->forecast_price_decimal, $speed_data->forecast_price);
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
                    'odds_fractional' => (string) ($rr->starting_price ?? ''),
                    'is_non_runner' => false,
                    'finish_position' => intval($rr->finish_position ?? 999),
                    'meeting_date' => (string) ($rr->meeting_date ?? '')
                ];
            }

            usort($scored, function($a, $b){ return $b['model_score'] <=> $a['model_score']; });
            $rank = 1;
            foreach ($scored as &$sr) { $sr['model_rank'] = $rank++; }
            unset($sr);
            $market_sorted = $scored;
            usort($market_sorted, function($a, $b){ return ($b['market_prob'] ?? 0) <=> ($a['market_prob'] ?? 0); });
            $mk = 1; $mk_map = [];
            foreach ($market_sorted as $mr) { if (($mr['market_prob'] ?? 0) > 0) $mk_map[$mr['runner_key']] = $mk++; }
            foreach ($scored as &$sr) {
                $sr['market_rank'] = $mk_map[$sr['runner_key']] ?? 0;
                $rank_edge = ($sr['market_rank'] > 0) ? ($sr['market_rank'] - $sr['model_rank']) : 0;
                $score_edge = max(0.0, (floatval($sr['model_score']) - 55.0) * 0.20);
                $sr['edge_score'] = round(($rank_edge * 4.0) + $score_edge, 2);
            }
            unset($sr);

            $picks = bricks_points_pick_winner_place($scored);
            $ew_simple = bricks_points_pick_each_way_simple($scored);
            $ew_edge = bricks_points_pick_each_way_edge($scored);
            $place_terms = bricks_points_place_terms_count($field_size);

            $apply_win = function($pick) use (&$stats) {
                if (!$pick || !isset($pick['odds_decimal']) || $pick['odds_decimal'] === null || floatval($pick['odds_decimal']) <= 1) return;
                $stats['win']['bets'] += 1;
                $is_win = intval($pick['finish_position'] ?? 999) === 1;
                if ($is_win) $stats['win']['hits'] += 1;
                $stats['win']['profit'] += $is_win ? (floatval($pick['odds_decimal']) - 1.0) : -1.0;
            };
            $apply_place = function($pick) use (&$stats, $place_terms) {
                if (!$pick || !isset($pick['odds_decimal']) || $pick['odds_decimal'] === null || floatval($pick['odds_decimal']) <= 1) return;
                $stats['place']['bets'] += 1;
                $placed = intval($pick['finish_position'] ?? 999) <= $place_terms;
                if ($placed) $stats['place']['hits'] += 1;
                $stats['place']['profit'] += $placed ? ((floatval($pick['odds_decimal']) - 1.0) * 0.25) : -1.0;
            };
            $apply_ew = function($pick, $key) use (&$stats, $place_terms) {
                if (!$pick || !isset($pick['odds_decimal']) || $pick['odds_decimal'] === null || floatval($pick['odds_decimal']) <= 1) return;
                $stats[$key]['bets'] += 2;
                $finish = intval($pick['finish_position'] ?? 999);
                $is_win = ($finish === 1);
                $placed = ($finish <= $place_terms);
                if ($placed) $stats[$key]['hits'] += 1;
                $stats[$key]['profit'] += $is_win ? (floatval($pick['odds_decimal']) - 1.0) : -1.0;
                $stats[$key]['profit'] += $placed ? ((floatval($pick['odds_decimal']) - 1.0) * 0.25) : -1.0;
            };

            $apply_win($picks['winner'] ?? null);
            if (!empty($picks['place'])) {
                foreach (array_slice($picks['place'], 0, 3) as $pp) {
                    $apply_place($pp);
                }
            }
            $apply_ew($ew_simple, 'ew_simple');
            $apply_ew($ew_edge, 'ew_edge');

            if (count($sample_rows) < 60) {
                $sample_rows[] = [
                    'meeting_date' => $race_rows[0]->meeting_date ?? '',
                    'race_id' => $race_id,
                    'course' => $race_rows[0]->course ?? '',
                    'winner_pick' => $picks['winner']['horse_name'] ?? '',
                    'winner_pick_pos' => $picks['winner']['finish_position'] ?? '',
                    'ew_simple' => $ew_simple['horse_name'] ?? '',
                    'ew_simple_pos' => $ew_simple['finish_position'] ?? '',
                    'ew_edge' => $ew_edge['horse_name'] ?? '',
                    'ew_edge_pos' => $ew_edge['finish_position'] ?? ''
                ];
            }
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
            'runner_count' => count($rows)
        ];
    }
}

if (!function_exists('bricks_points_backtest_shortcode')) {
    function bricks_points_backtest_shortcode($atts = []) {
        if (!is_user_logged_in()) {
            return '<div style="max-width:760px;margin:40px auto;padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                <h2 style="margin:0 0 10px 0;color:#111827;">Points Backtest</h2>
                <p style="margin:0;color:#6b7280;">Please log in to view backtest results.</p>
            </div>';
        }

        $default_to = date('Y-m-d', strtotime('-1 day'));
        $default_from = date('Y-m-d', strtotime('-365 days', strtotime($default_to)));

        $from_date = isset($_GET['pb_from']) ? sanitize_text_field($_GET['pb_from']) : $default_from;
        $to_date = isset($_GET['pb_to']) ? sanitize_text_field($_GET['pb_to']) : $default_to;
        $race_type_filter = isset($_GET['pb_race_type']) ? sanitize_text_field($_GET['pb_race_type']) : '';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) $from_date = $default_from;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) $to_date = $default_to;
        if ($from_date > $to_date) { $tmp = $from_date; $from_date = $to_date; $to_date = $tmp; }

        $result = bricks_points_backtest_calculate($from_date, $to_date, $race_type_filter);
        $summary = $result['summary'] ?? [];
        $sample_rows = $result['sample_rows'] ?? [];

        ob_start();
        ?>
        <div style="max-width:1200px;margin:24px auto;padding:0 16px 30px;">
            <h1 style="margin:0 0 6px;color:#111827;font-size:30px;font-weight:800;">Points Engine Backtest</h1>
            <p style="margin:0 0 14px;color:#6b7280;">Historical ROI test for Win, Place, EW Simple and EW Edge strategies.</p>

            <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-bottom:14px;">
                <input type="hidden" name="my_points_backtest" value="1" />
                <label style="font-size:12px;color:#374151;">From<br><input type="date" name="pb_from" value="<?php echo esc_attr($from_date); ?>" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <label style="font-size:12px;color:#374151;">To<br><input type="date" name="pb_to" value="<?php echo esc_attr($to_date); ?>" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <label style="font-size:12px;color:#374151;">Race Type<br><input type="text" name="pb_race_type" value="<?php echo esc_attr($race_type_filter); ?>" placeholder="Optional exact match" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;"></label>
                <div style="align-self:flex-end;"><button type="submit" style="padding:9px 14px;border:none;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;">Run Backtest</button></div>
            </form>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-size:12px;color:#374151;">Races: <strong><?php echo esc_html($result['race_count'] ?? 0); ?></strong></div>
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-size:12px;color:#374151;">Runners: <strong><?php echo esc_html($result['runner_count'] ?? 0); ?></strong></div>
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
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Course</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Win Pick</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">EW Simple</th>
                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">EW Edge</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sample_rows as $sr): ?>
                        <tr>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($sr['meeting_date']); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($sr['course']); ?></td>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;"><?php echo esc_html(($sr['winner_pick'] ?: '-') . ' (Pos ' . ($sr['winner_pick_pos'] ?: '-') . ')'); ?></td>
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
        <div style="background:white;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);margin-top:30px;padding:30px;">
            <h2 style="color:#111827;margin-bottom:25px;text-align:center;font-size:24px;font-weight:700;">📊 Fhorsite and Speed Rating Analysis</h2>
            <div class="speed-rating-chart-container" style="position:relative;height:700px;margin:30px 0;background:linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);border-radius:8px;padding:20px;">
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
                            font: { size: 13, weight: '600' },
                            color: '#333'
                        }
                    }
                },
                elements: {
                    bar: {
                        borderWidth: 0,
                        borderRadius: 8,
                        borderSkipped: false,
                        categoryPercentage: 0.95,
                        barPercentage: 0.95
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
 * Ensure a valid global $post exists on custom virtual pages (race detail, horse history, race comments).
 * This prevents BricksMembers (and similar plugins) from crashing when get_the_ID() returns false.
 */
function bricks_setup_virtual_page_post() {
    if (is_admin()) {
        return;
    }

    // Only act on our custom virtual pages
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_virtual = get_query_var('race_id')
        || get_query_var('runner_id')
        || get_query_var('horse_name')
        || get_query_var('race_comment_id')
        || get_query_var('my_tracker_page')
        || get_query_var('my_points_backtest')
        || (strpos($request_uri, '/my-tracker') !== false)
        || (strpos($request_uri, '/points-backtest') !== false);
    if (!$is_virtual) {
        return;
    }

    global $post, $wp_query;

    // If there's already a valid post, nothing to do
    if ($post && $post->ID) {
        return;
    }

    // Try to use the static front page
    $page_id = (int) get_option('page_on_front');

    // Fallback: grab any published page
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

    // Ultimate fallback: create an in-memory fake post so get_the_ID() returns an int (0)
    if (!$post) {
        $post = new WP_Post((object) [
            'ID'           => 0,
            'post_title'   => 'Virtual Page',
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
    }

    $wp_query->post       = $post;
    $wp_query->posts      = [$post];
    $wp_query->post_count = 1;
    $wp_query->is_404     = false;
    $wp_query->is_page    = true;
    $wp_query->is_singular = true;
    setup_postdata($post);

    // Prevent WordPress from redirecting to the real page's permalink
    remove_action('template_redirect', 'redirect_canonical');
}
add_action('wp', 'bricks_setup_virtual_page_post', 1);

function bricks_is_standalone_page() {
    // Check if we're on a page that should have full page layout
    $current_url = $_SERVER['REQUEST_URI'];
    return (
        strpos($current_url, '/daily') !== false || 
        strpos($current_url, '/speed') !== false ||
        strpos($current_url, '/my-tracker') !== false ||
        strpos($current_url, '/points-backtest') !== false ||
        get_query_var('my_tracker_page') ||
        get_query_var('my_points_backtest') ||
        get_query_var('race_id')
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
                    <li>
                        <a href="<?php echo home_url('/points-backtest/'); ?>" class="nav-link <?php echo (strpos($current_url, '/points-backtest') !== false) ? 'active' : ''; ?>">
                            <span class="nav-icon">📊</span>
                            <span class="nav-text">Points Backtest</span>
                        </a>
                    </li>
                    
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

// ==============================================
// URL REWRITE RULES FOR HORSE HISTORY
// ==============================================

function add_horse_history_rewrite_rules() {
    // Tokenized runner_id route first
    add_rewrite_rule(
        'horse-history/(h_[A-Za-z0-9]+)/?$',
        'index.php?runner_id=$matches[1]',
        'top'
    );
    // Legacy numeric runner_id route
    add_rewrite_rule(
        'horse-history/([0-9]+)/?$',
        'index.php?runner_id=$matches[1]',
        'top'
    );
    // Back-compat: name-based route
    add_rewrite_rule(
        'horse-history/([^/]+)/?$',
        'index.php?horse_name=$matches[1]',
        'top'
    );
}
add_action('init', 'add_horse_history_rewrite_rules', 10);

// Add query vars for horse_name and runner_id
function add_horse_history_query_vars($vars) {
    $vars[] = 'horse_name';
    $vars[] = 'runner_id';
    return $vars;
}
add_filter('query_vars', 'add_horse_history_query_vars');

// Handle horse history page template (REPLACE the existing horse_history_template function)
function horse_history_template($template) {
    if (get_query_var('runner_id') || get_query_var('horse_name')) {
        // Don't interfere with admin pages
        if (is_admin()) {
            return $template;
        }
        
        // Create a custom template that uses your theme
        $custom_template = get_stylesheet_directory() . '/horse-history.php';
        
        // If custom template doesn't exist, create it dynamically
        if (!file_exists($custom_template)) {
            // Use page.php or index.php as fallback
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

// REMOVE or comment out the takeover_horse_history_page function entirely
// add_action('init', 'takeover_horse_history_page', 1); // REMOVE THIS LINE


// Flush rewrite rules when needed
function flush_horse_history_rewrite_rules_if_needed() {
    if (get_option('horse_history_rewrite_rules_flushed') !== '2') {
        flush_rewrite_rules();
        update_option('horse_history_rewrite_rules_flushed', '2');
    }
}
add_action('init', 'flush_horse_history_rewrite_rules_if_needed', 999);



/**
 * Daily Race Comment History Page Implementation
 */

// ==============================================
// RACE COMMENT HELPER FUNCTIONS
// ==============================================

if (!function_exists('get_race_comment_details')) {
    function get_race_comment_details($race_id) {
        global $wpdb;
        $race_id = intval($race_id);
        if ($race_id <= 0) return null;

        $key = 'race_comment_details_' . $race_id;
        $cached = get_transient($key);
        if ($cached !== false) return $cached;

        // Get race details from daily_race_comment_history
        $race_details = $wpdb->get_row($wpdb->prepare(
            "SELECT meeting_date, race_type, going, class
             FROM daily_race_comment_history 
             WHERE race_id = %d 
             LIMIT 1",
            $race_id
        ));

        if ($race_details) {
            set_transient($key, $race_details, 2 * HOUR_IN_SECONDS);
        }
        return $race_details;
    }
}

if (!function_exists('get_race_comment_runners')) {
    function get_race_comment_runners($race_id) {
        global $wpdb;
        $race_id = intval($race_id);
        if ($race_id <= 0) return [];

        $key = 'race_comment_runners_' . $race_id;
        $cached = get_transient($key);
        if ($cached !== false) return $cached;

        // Get all runners for this race from daily_race_comment_history
        $runners = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                runner_id,
                name,
                finish_position,
                distance_beaten,
                official_rating,
                wt_speed_rating,
                legacy_speed_rating,
                speed_rating,
                in_race_comment,
                form_figures,
                race_type,
                going,
                class
             FROM daily_race_comment_history 
             WHERE race_id = %d 
             ORDER BY finish_position ASC, name ASC",
            $race_id
        ));

        if (!empty($runners)) {
            set_transient($key, $runners, 2 * HOUR_IN_SECONDS);
        }
        return $runners;
    }
}

// ==============================================
// RACE COMMENT SHORTCODE
// ==============================================

function race_comment_history_shortcode($atts) {
    $atts = shortcode_atts(['race_id' => ''], $atts);
    $race_id = bricks_decode_entity_id($atts['race_id'], 'race_comment');

    // Fallback to query vars if attributes are missing
    if (!$race_id) {
        $race_id = bricks_decode_entity_id(get_query_var('race_comment_id'), 'race_comment');
        if (!$race_id && !empty($_SERVER['REQUEST_URI'])) {
            if (preg_match('/race-comments\/([A-Za-z0-9_-]+)/', $_SERVER['REQUEST_URI'], $m)) {
                $race_id = bricks_decode_entity_id($m[1], 'race_comment');
            }
        }
    }

    if (!$race_id) {
        return '<div style="color:red;padding:20px;">Error: Race ID is required</div>';
    }

    // Get race details and runners
    $race_details = get_race_comment_details($race_id);
    $runners = get_race_comment_runners($race_id);
    
    // Note: daily_race_comment_history does not have a 'course' column
    $course_features = null;

    if (!$race_details) {
        return '<div style="color:red;padding:20px;">Race not found with ID: ' . esc_html($race_id) . '</div>';
    }

    ob_start();
    ?>
    <style>
        .race-comment-container {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 0;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .race-comment-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            padding: 20px;
        }
        
        .race-comment-header-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 16px;
            margin-bottom: 24px;
            padding: 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        .race-comment-header-top {
            background: rgba(0,0,0,0.2);
            padding: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .race-comment-title {
            color: #ffffff;
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 8px 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        
        .race-comment-subtitle {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .race-comment-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            padding: 24px;
        }
        
        .race-detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .race-detail-item:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
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
            color: rgba(255,255,255,0.8);
        }
        
        .race-detail-value {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
        }
        
        .runners-comment-section {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .runners-comment-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 24px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .runners-comment-title {
            margin: 0 0 8px 0;
            color: #1e293b;
            font-size: 28px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .runners-comment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .runners-comment-table thead {
            background: linear-gradient(180deg, #f8f9fa 0%, #f1f3f5 100%);
        }
        
        .runners-comment-table th {
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
        
        .runner-comment-row {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f1f3f5;
        }
        
        .runner-comment-row:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .runner-comment-row:nth-child(even) {
            background: #f8f9fa;
        }
        
        .runner-comment-row:nth-child(even):hover {
            background: #f1f3f5;
        }
        
        .runners-comment-table td {
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
        
        .speed-average {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }

        .horse-name-link {
            color: #2563eb !important;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .horse-name-link:hover {
            color: #1d4ed8 !important;
            text-decoration: underline;
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

        @media (max-width: 768px) {
            .race-comment-container {
                padding: 0;
            }
            
            .race-comment-wrapper {
                padding: 12px;
            }
            
            .race-comment-header-card {
                border-radius: 12px;
                margin-bottom: 20px;
            }
            
            .race-comment-header-top {
                padding: 16px;
            }

            .race-comment-title {
                font-size: 20px;
            }
            
            .race-comment-subtitle {
                font-size: 12px;
            }

            .race-comment-details-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 16px;
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
            
            .runners-comment-section {
                border-radius: 12px;
            }
            
            .runners-comment-header {
                padding: 16px;
            }
            
            .runners-comment-title {
                font-size: 20px;
            }
            
            .runners-comment-section {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .runners-comment-table {
                font-size: 12px;
                min-width: 1000px; /* Ensure table doesn't get too compressed */
            }

            .runners-comment-table th,
            .runners-comment-table td {
                padding: 10px 8px;
            }
            
            .runners-comment-table th {
                font-size: 10px;
            }
            
            .comment-box {
                padding: 12px;
            }
            
            .comment-text {
                font-size: 12px;
            }
            
            .speed-excellent,
            .speed-good,
            .speed-average {
                font-size: 11px;
                padding: 4px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .race-comment-wrapper {
                padding: 8px;
            }
            
            .race-comment-header-top {
                padding: 12px;
            }
            
            .race-comment-title {
                font-size: 18px;
            }
            
            .race-comment-details-grid {
                padding: 12px;
            }
            
            .runners-comment-header {
                padding: 12px;
            }
            
            .runners-comment-title {
                font-size: 18px;
            }
            
            .runners-comment-table {
                font-size: 11px;
            }
            
            .runners-comment-table th,
            .runners-comment-table td {
                padding: 8px 6px;
            }
        }
    </style>

    <div class="race-comment-container">
        <div class="race-comment-wrapper">
            <!-- Race Header Card -->
            <div class="race-comment-header-card">
                <div class="race-comment-header-top">
                    <h1 class="race-comment-title">🏁 Race Comments</h1>
                    <p class="race-comment-subtitle">Complete race comments and runner analysis</p>
                </div>
                
                <div class="race-comment-details-grid">
                    <div class="race-detail-item">
                        <div class="race-detail-icon">📅</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Date</div>
                            <div class="race-detail-value"><?php echo esc_html(date('l, d M Y', strtotime($race_details->meeting_date))); ?></div>
                        </div>
                    </div>


                    <div class="race-detail-item">
                        <div class="race-detail-icon">🌤️</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Going</div>
                            <div class="race-detail-value"><?php echo esc_html($race_details->going ?: 'N/A'); ?></div>
                        </div>
                    </div>

                    <div class="race-detail-item">
                        <div class="race-detail-icon">🏆</div>
                        <div class="race-detail-content">
                            <div class="race-detail-label">Class & Type</div>
                            <div class="race-detail-value">
                                Class <?php echo esc_html($race_details->class ?: 'N/A'); ?> • <?php echo esc_html($race_details->race_type ?: 'N/A'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Runners Comments Section -->
            <?php if (!empty($runners)): ?>
            <div class="runners-comment-section">
                <div class="runners-comment-header">
                    <h2 class="runners-comment-title">
                        <span>💬</span>
                        Race Comments & Analysis
                        <span style="background:rgba(59,130,246,0.2);color:#2563eb;padding:4px 12px;border-radius:8px;font-size:14px;margin-left:auto;">
                            <?php echo count($runners); ?> runners
                        </span>
                    </h2>
                </div>
                
                <div class="race-table-wrapper">
                    <table class="runners-comment-table">
                        <thead>
                            <tr>
                                <th style="text-align:center;">Pos</th>
                                <th>Horse</th>
                                <th style="text-align:center;">OR</th>
                                <th style="text-align:center;">SR</th>
                                <th>Form</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($runners as $index => $runner): ?>
                            <!-- Main Runner Row -->
                            <tr class="runner-comment-row">
                                <td style="text-align:center;font-weight:bold;">
                                    <?php 
                                    $pos = intval($runner->finish_position);
                                    $position_class = '';
                                    if ($pos === 1) $position_class = 'position-1';
                                    elseif ($pos <= 3) $position_class = 'position-2-3';
                                    else $position_class = 'position-other';
                                    
                                    $position = $runner->finish_position ?: 'N/A';
                                    ?>
                                    <span class="<?php echo $position_class; ?>">
                                        <?php echo esc_html($position); ?>
                                    </span>
                                    <?php if ($runner->distance_beaten && $runner->distance_beaten > 0): ?>
                                        <div style="font-size:10px;color:#6b7280;margin-top:2px;">
                                            <?php echo esc_html($runner->distance_beaten . 'L'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if ($runner->runner_id): ?>
                                        <a href="<?php echo esc_url(bricks_horse_history_url($runner->runner_id)); ?>" 
                                           class="horse-name-link"
                                           title="View <?php echo esc_attr($runner->name); ?>'s complete racing history">
                                            <?php echo esc_html($runner->name ?: 'N/A'); ?>
                                        </a>
                                    <?php else: ?>
                                        <div style="font-weight:700;color:#111827;font-size:14px;"><?php echo esc_html($runner->name ?: 'N/A'); ?></div>
                                    <?php endif; ?>
                                    <?php
                                    echo bricks_tracker_render_horse_widget(
                                        $runner->name ?: '',
                                        [
                                            'race_id' => $race_comment_id,
                                            'race_date' => isset($race_details->meeting_date) ? $race_details->meeting_date : '',
                                            'race_time' => isset($race_details->race_time) ? $race_details->race_time : '',
                                            'course' => isset($race_details->course) ? $race_details->course : ''
                                        ],
                                        [
                                            'show_latest_flag' => true,
                                            'wrapper_class' => 'tracker-comment-row'
                                        ]
                                    );
                                    ?>
                                </td>
                                
                                <td style="text-align:center;font-weight:700;color:#059669;">
                                    <?php echo esc_html($runner->official_rating ?: '-'); ?>
                                </td>
                                
                                <td style="text-align:center;">
                                    <?php 
                                    $speed_rating = $runner->speed_rating ?: $runner->wt_speed_rating ?: $runner->legacy_speed_rating;
                                    if ($speed_rating): 
                                        $sr = intval($speed_rating);
                                        $speed_class = '';
                                        if ($sr >= 80) $speed_class = 'speed-excellent';
                                        elseif ($sr >= 70) $speed_class = 'speed-good';
                                        elseif ($sr >= 60) $speed_class = 'speed-average';
                                    ?>
                                        <span class="<?php echo $speed_class; ?>">
                                            <?php echo esc_html($speed_rating); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#9ca3af;">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="font-family:monospace;font-size:11px;color:#059669;">
                                    <?php echo esc_html($runner->form_figures ? substr($runner->form_figures, 0, 10) : '-'); ?>
                                </td>
                            </tr>
                            
                            <!-- Comment Row -->
                            <tr class="comment-row">
                                <td colspan="9" style="padding:0 12px 16px 12px;">
                                    <?php if ($runner->in_race_comment): ?>
                                        <div class="comment-box">
                                            <span class="comment-label">Race Comment:</span>
                                            <div class="comment-text"><?php echo esc_html($runner->in_race_comment); ?></div>
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
            </div>
            <?php else: ?>
            <div class="runners-comment-section">
                <div class="no-data">
                    <div class="no-data-icon">🔍</div>
                    <h3 style="color:#6b7280;margin:0 0 8px 0;">No runners found</h3>
                    <p style="color:#9ca3af;margin:0;">No runner comments are available for this race.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return $content;
}

// Register the shortcode
add_shortcode('race_comment_history', 'race_comment_history_shortcode');

// ==============================================
// URL REWRITE RULES FOR RACE COMMENTS
// ==============================================

function add_race_comment_rewrite_rules() {
    add_rewrite_rule(
        'race-comments/([A-Za-z0-9_-]+)/?$',
        'index.php?race_comment_id=$matches[1]',
        'top'
    );
}
add_action('init', 'add_race_comment_rewrite_rules', 10);

// Add query vars for race_comment_id
function add_race_comment_query_vars($vars) {
    $vars[] = 'race_comment_id';
    return $vars;
}
add_filter('query_vars', 'add_race_comment_query_vars');

// Handle race comment page template
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

// Flush rewrite rules when needed
function flush_race_comment_rewrite_rules_if_needed() {
    if (get_option('race_comment_rewrite_rules_flushed') !== '2') {
        flush_rewrite_rules();
        update_option('race_comment_rewrite_rules_flushed', '2');
    }
}
add_action('init', 'flush_race_comment_rewrite_rules_if_needed', 999);

// ==============================================
// P&L TABLE - Fhorsite Rating ROI Analysis
// ==============================================

/**
 * Helper function to convert fractional odds to decimal
 */
function convert_fractional_to_decimal($fractional) {
    if (empty($fractional) || $fractional === 'N/A' || $fractional === 'NR') {
        return null;
    }
    
    // Handle decimal odds (already in decimal format)
    if (is_numeric($fractional) && strpos($fractional, '/') === false) {
        return floatval($fractional);
    }
    
    // Handle fractional odds (e.g., "2/1", "5/2")
    if (strpos($fractional, '/') !== false) {
        $parts = explode('/', $fractional);
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            return (floatval($parts[0]) / floatval($parts[1])) + 1;
        }
    }
    
    return null;
}

/**
 * Get P&L filter options
 */
function bricks_get_pl_filter_options() {
    global $wpdb;
    
    $table_name = 'speed&performance_table';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if (!$table_exists) {
        wp_send_json_error('Table does not exist');
        return;
    }
    
    // Get unique courses
    $courses = $wpdb->get_col(
        "SELECT DISTINCT `course` FROM `$table_name` 
         WHERE `course` IS NOT NULL AND `course` != '' 
         ORDER BY `course`"
    );
    
    // Get unique race types
    $race_types = $wpdb->get_col(
        "SELECT DISTINCT `race_type` FROM `$table_name` 
         WHERE `race_type` IS NOT NULL AND `race_type` != '' 
         ORDER BY `race_type`"
    );
    
    // Get date range
    $date_range = $wpdb->get_row(
        "SELECT MIN(`Date`) as min_date, MAX(`Date`) as max_date 
         FROM `$table_name` 
         WHERE `Date` IS NOT NULL"
    );
    
    wp_send_json([
        'courses' => $courses,
        'race_types' => $race_types,
        'date_range' => $date_range
    ]);
}
add_action('wp_ajax_get_pl_filter_options', 'bricks_get_pl_filter_options');
add_action('wp_ajax_nopriv_get_pl_filter_options', 'bricks_get_pl_filter_options');

/**
 * AJAX handler to load P&L table
 */
function bricks_ajax_load_pl_table() {
    global $wpdb;
    
    $per_page = 50;
    $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $offset = ($paged - 1) * $per_page;
    
    // Get filter parameters
    // Dates come in d-m-Y format (25-01-2026) from the frontend
    $date_from = !empty($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
    $date_to = !empty($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
    $date_period = !empty($_POST['date_period']) ? sanitize_text_field($_POST['date_period']) : 'daily'; // daily (today), yesterday, monthly, yearly, all
    
    // If custom dates are provided in d-m-Y format, validate them
    if ($date_period === 'custom' && $date_from && !preg_match('/^\d{2}-\d{2}-\d{4}$/', $date_from)) {
        $date_from = ''; // Invalid format, clear it
    }
    if ($date_period === 'custom' && $date_to && !preg_match('/^\d{2}-\d{2}-\d{4}$/', $date_to)) {
        $date_to = ''; // Invalid format, clear it
    }
    $course = !empty($_POST['course']) ? sanitize_text_field($_POST['course']) : '';
    $race_type = !empty($_POST['race_type']) ? sanitize_text_field($_POST['race_type']) : '';
    $class = !empty($_POST['class']) ? sanitize_text_field($_POST['class']) : '';
    $handicap = isset($_POST['handicap']) && $_POST['handicap'] !== '' ? intval($_POST['handicap']) : null;
    $runners_from = !empty($_POST['runners_from']) && is_numeric($_POST['runners_from']) ? intval($_POST['runners_from']) : null;
    $runners_to = !empty($_POST['runners_to']) && is_numeric($_POST['runners_to']) ? intval($_POST['runners_to']) : null;
    
    // Calculate date range based on period - use same approach as daily page (Y-m-d format)
    // Only override if not custom or if custom dates are empty
    if ($date_period !== 'custom' || (empty($date_from) && empty($date_to))) {
        if ($date_period === 'daily' || empty($date_period) || $date_period === '') {
            // Today's date - same as daily page
            $date_from = date('Y-m-d');
            $date_to = date('Y-m-d');
        } elseif ($date_period === 'yesterday') {
            // Yesterday's date
            $date_from = date('Y-m-d', strtotime('-1 day'));
            $date_to = date('Y-m-d', strtotime('-1 day'));
        } elseif ($date_period === 'monthly') {
            $date_from = date('Y-m-01');
            $date_to = date('Y-m-t');
        } elseif ($date_period === 'yearly') {
            $date_from = date('Y-01-01');
            $date_to = date('Y-12-31');
        }
    }
    
    // If custom dates are provided in d-m-Y format, convert to Y-m-d for processing
    if ($date_period === 'custom' && $date_from && preg_match('/^\d{2}-\d{2}-\d{4}$/', $date_from)) {
        // Convert from d-m-Y to Y-m-d
        $date_obj = DateTime::createFromFormat('d-m-Y', $date_from);
        if ($date_obj) {
            $date_from = $date_obj->format('Y-m-d');
        } else {
            $date_from = date('Y-m-d'); // Default to today if invalid
        }
    }
    if ($date_period === 'custom' && $date_to && preg_match('/^\d{2}-\d{2}-\d{4}$/', $date_to)) {
        // Convert from d-m-Y to Y-m-d
        $date_obj = DateTime::createFromFormat('d-m-Y', $date_to);
        if ($date_obj) {
            $date_to = $date_obj->format('Y-m-d');
        } else {
            $date_to = date('Y-m-d'); // Default to today if invalid
        }
    }
    
    // Validate dates - if invalid, default to today (same as daily page)
    if ($date_from && (strtotime($date_from) === false || strtotime($date_from) < 0)) {
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d');
    }
    if ($date_to && (strtotime($date_to) === false || strtotime($date_to) < 0)) {
        $date_to = $date_from ? $date_from : date('Y-m-d');
    }
    
    // Convert dates to d-m-Y format for speed&performance_table (database uses 25-01-2026 format)
    // Dates are now in Y-m-d format (same as daily page), convert to d-m-Y for query
    if ($date_from) {
        $date_from_dmy = convert_date_format($date_from, 'd-m-Y'); // Convert Y-m-d to d-m-Y
        $date_from_ymd = $date_from; // Keep Y-m-d for fallback
    } else {
        $date_from_dmy = '';
        $date_from_ymd = '';
    }
    
    if ($date_to) {
        $date_to_dmy = convert_date_format($date_to, 'd-m-Y'); // Convert Y-m-d to d-m-Y
        $date_to_ymd = $date_to; // Keep Y-m-d for fallback
    } else {
        $date_to_dmy = '';
        $date_to_ymd = '';
    }
    
    // Debug: Log the dates being used
    // Temporarily commented out error_log statements to avoid parse errors
    // error_log("P&L Table Debug - POST data: " . print_r($_POST, true));
    // error_log("P&L Table Debug - Date period: $date_period, Date from: $date_from, Date to: $date_to");
    
    // Check what dates actually exist in the database
    $sample_dates = $wpdb->get_col("SELECT DISTINCT `Date` FROM `speed&performance_table` ORDER BY `Date` DESC LIMIT 10");
    // error_log("P&L Table Debug - Sample dates in database: " . print_r($sample_dates, true));
    
    // Build the query to get highest Fhorsite rated horse per race
    // Use historic_runners table for finish_position and starting_price (better source)
    $speed_table = '`speed&performance_table`';
    $historic_table = '`historic_runners`';
    
    // Build WHERE conditions
    $where_conditions = [];
    
    // Date filtering - use same approach as speed performance table (IN clause with both formats)
    if ($date_from && $date_to) {
        // Build list of all dates in range (primarily d-m-Y format)
        $date_list = [];
        if ($date_from === $date_to) {
            // Single date - prioritize d-m-Y format (database format)
            if ($date_from_dmy) {
                $date_dmy_escaped = esc_sql($date_from_dmy);
                $date_list[] = "'$date_dmy_escaped'";
            }
            // Also check Y-m-d format in case some dates are stored that way
            if ($date_from_ymd) {
                $date_ymd_escaped = esc_sql($date_from_ymd);
                $date_list[] = "'$date_ymd_escaped'";
            }
        } else {
            // Date range - get all dates between
            $start = strtotime($date_from);
            $end = strtotime($date_to);
            if ($start && $end) {
                for ($d = $start; $d <= $end; $d += 86400) {
                    // Primary format: d-m-Y (database format)
                    $date_dmy_escaped = esc_sql(date('d-m-Y', $d));
                    $date_list[] = "'$date_dmy_escaped'";
                    // Also include Y-m-d format just in case
                    $date_ymd_escaped = esc_sql(date('Y-m-d', $d));
                    $date_list[] = "'$date_ymd_escaped'";
                }
            }
        }
        
        if (!empty($date_list)) {
            $date_list = array_unique($date_list);
            $where_conditions[] = "sp.`Date` IN (" . implode(', ', $date_list) . ")";
        }
    } elseif ($date_from) {
        // Single date - prioritize d-m-Y format (database format: 25-01-2026)
        $date_list = [];
        if ($date_from_dmy) {
            $date_dmy_escaped = esc_sql($date_from_dmy);
            $date_list[] = "'$date_dmy_escaped'";
        }
        // Also check Y-m-d format in case some dates are stored that way
        if ($date_from_ymd) {
            $date_ymd_escaped = esc_sql($date_from_ymd);
            $date_list[] = "'$date_ymd_escaped'";
        }
        if (!empty($date_list)) {
            $where_conditions[] = "sp.`Date` IN (" . implode(', ', $date_list) . ")";
        }
    }
    
    // Additional filters
    if ($course) {
        $course_escaped = esc_sql($course);
        $where_conditions[] = "sp.`course` = '$course_escaped'";
    }
    if ($race_type) {
        $race_type_escaped = esc_sql($race_type);
        $where_conditions[] = "sp.`race_type` = '$race_type_escaped'";
    }
    if ($class) {
        $class_escaped = esc_sql($class);
        $where_conditions[] = "sp.`class` = '$class_escaped'";
    }
    if ($handicap !== null) {
        $where_conditions[] = "sp.`handicap` = $handicap";
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get all horses with their ratings, then we'll filter in PHP to get highest per race
    // This is more reliable across different MySQL versions
    $all_horses_query = "
        SELECT 
            sp.race_id,
            sp.Date,
            sp.course,
            sp.Time,
            sp.race_title,
            sp.race_type,
            sp.class,
            sp.handicap,
            sp.Distance,
            sp.name as horse_name,
            sp.runner_id,
            sp.fhorsite_rating,
            sp.forecast_price,
            sp.forecast_price_decimal,
            sp.cloth_number,
            hr.finish_position,
            hr.starting_price,
            hr.starting_price_decimal,
            (SELECT COUNT(*) FROM $historic_table hr2 WHERE hr2.race_id = sp.race_id) as runners
        FROM $speed_table sp
        LEFT JOIN $historic_table hr ON 
            sp.race_id = hr.race_id AND 
            sp.runner_id = hr.runner_id
        $where_clause
        ORDER BY sp.race_id, sp.Date,
            CASE 
                WHEN sp.fhorsite_rating IS NOT NULL AND sp.fhorsite_rating != '' 
                THEN CAST(sp.fhorsite_rating AS DECIMAL(10,2))
                ELSE -999
            END DESC,
            sp.cloth_number ASC
    ";
    
    // Get all results first
    $all_results = $wpdb->get_results($all_horses_query);
    error_log("P&L Table Debug - All results count: " . count($all_results));
    
    // Filter to get only the highest rated horse per race
    $results_by_race = [];
    foreach ($all_results as $row) {
        $race_key = $row->race_id . '_' . $row->Date;
        if (!isset($results_by_race[$race_key])) {
            $results_by_race[$race_key] = $row;
        }
    }
    
    $results = array_values($results_by_race);
    error_log("P&L Table Debug - Filtered results count (highest rated per race): " . count($results));
    
    // Apply runner count filters
    if ($runners_from !== null || $runners_to !== null) {
        $results = array_filter($results, function($row) use ($runners_from, $runners_to) {
            $runners = intval($row->runners);
            if ($runners_from !== null && $runners < $runners_from) return false;
            if ($runners_to !== null && $runners > $runners_to) return false;
            return true;
        });
        $results = array_values($results); // Re-index array
    }
    
    $total_records = count($results);
    
    // Calculate totals from ALL filtered results (not just current page)
    $total_stakes = 0;
    $total_profit = 0;
    $total_wins = 0;
    $total_losses = 0;
    
    foreach ($results as $row) {
        $stake = 1.00;
        $total_stakes += $stake;
        
        $position = $row->finish_position ? intval($row->finish_position) : null;
        $won = ($position === 1);
        
        $sp = $row->starting_price;
        $sp_decimal = convert_fractional_to_decimal($sp);
        
        if ($won && $sp_decimal) {
            $return = $stake * $sp_decimal;
            $pl = $return - $stake;
            $total_profit += $pl;
            $total_wins++;
        } else {
            $pl = -$stake;
            $total_profit += $pl;
            $total_losses++;
        }
    }
    
    // Apply pagination
    $results = array_slice($results, $offset, $per_page);
    
    // Apply runner count filters
    $having_conditions = [];
    if ($runners_from !== null) {
        $having_conditions[] = "runners >= $runners_from";
    }
    if ($runners_to !== null) {
        $having_conditions[] = "runners <= $runners_to";
    }
    
    $having_clause = !empty($having_conditions) ? 'HAVING ' . implode(' AND ', $having_conditions) : '';
    
    // Apply sorting
    $allowed_sorts = ['Date', 'Time', 'course', 'race_type', 'class', 'horse_name', 'fhorsite_rating', 'forecast_price', 'starting_price', 'finish_position'];
    
    if (!empty($_POST['sort_column']) && in_array($_POST['sort_column'], $allowed_sorts)) {
        $direction = (!empty($_POST['sort_direction']) && $_POST['sort_direction'] === 'desc') ? -1 : 1;
        $sort_col = $_POST['sort_column'];
        
        usort($results, function($a, $b) use ($sort_col, $direction) {
            $val_a = '';
            $val_b = '';
            
            switch ($sort_col) {
                case 'Date':
                    $val_a = $a->Date;
                    $val_b = $b->Date;
                    break;
                case 'Time':
                    $val_a = $a->Time ? strtotime($a->Time) : 0;
                    $val_b = $b->Time ? strtotime($b->Time) : 0;
                    break;
                case 'course':
                    $val_a = $a->course ?: '';
                    $val_b = $b->course ?: '';
                    break;
                case 'race_type':
                    $val_a = $a->race_type ?: '';
                    $val_b = $b->race_type ?: '';
                    break;
                case 'class':
                    $val_a = $a->class ?: '';
                    $val_b = $b->class ?: '';
                    break;
                case 'horse_name':
                    $val_a = $a->horse_name ?: '';
                    $val_b = $b->horse_name ?: '';
                    break;
                case 'fhorsite_rating':
                    $val_a = floatval($a->fhorsite_rating ?: -999);
                    $val_b = floatval($b->fhorsite_rating ?: -999);
                    break;
                case 'forecast_price':
                    $val_a = $a->forecast_price ?: '';
                    $val_b = $b->forecast_price ?: '';
                    break;
                case 'starting_price':
                    $val_a = $a->starting_price ?: '';
                    $val_b = $b->starting_price ?: '';
                    break;
                case 'finish_position':
                    $val_a = intval($a->finish_position ?: 999);
                    $val_b = intval($b->finish_position ?: 999);
                    break;
            }
            
            if (is_numeric($val_a) && is_numeric($val_b)) {
                return ($val_a <=> $val_b) * $direction;
            }
            return strcmp($val_a, $val_b) * $direction;
        });
    } else {
        // Default sort: Date DESC, Time ASC
        usort($results, function($a, $b) {
            $date_cmp = strcmp($b->Date, $a->Date);
            if ($date_cmp !== 0) return $date_cmp;
            $time_a = $a->Time ? strtotime($a->Time) : 0;
            $time_b = $b->Time ? strtotime($b->Time) : 0;
            return $time_a <=> $time_b;
        });
    }
    
    $total_pages = ceil($total_records / $per_page);
    
    ob_start();
    
    // Debug: Show what we're looking for if no results
    if (empty($results)) {
        // Check if we found any horses at all (before filtering)
        $check_query = "SELECT COUNT(*) as cnt FROM $speed_table sp WHERE " . str_replace('sp.`', '`', $where_clause);
        $total_horses = $wpdb->get_var($check_query);
        
        echo '<div style="text-align:center;padding:40px;color:#6b7280;">
            <div style="font-size:48px;margin-bottom:16px;">🔍</div>
            <div style="font-size:18px;font-weight:600;margin-bottom:8px;">No results found</div>
            <div style="font-size:14px;margin-bottom:8px;">Date period: ' . esc_html($date_period) . '</div>
            <div style="font-size:14px;margin-bottom:8px;">Date from: ' . esc_html($date_from) . ' (' . esc_html($date_from_dmy) . ' / ' . esc_html($date_from_ymd) . ')</div>
            <div style="font-size:14px;margin-bottom:8px;">Date to: ' . esc_html($date_to) . ' (' . esc_html($date_to_dmy) . ' / ' . esc_html($date_to_ymd) . ')</div>
            <div style="font-size:14px;margin-bottom:8px;">Total horses found in speed table: ' . esc_html($total_horses) . '</div>
            <div style="font-size:14px;margin-bottom:8px;">Total records after filtering: ' . esc_html($total_records) . '</div>
            <div style="font-size:14px;">Try adjusting your filters or selecting a different date</div>
        </div>';
        wp_die(ob_get_clean());
        return;
    }
    
    if ($results) {
        echo '<div style="overflow-x:auto;">
        <table class="pl-table">
            <thead>
                <tr>
                    <th data-sort="Date" class="sortable">Date</th>
                    <th data-sort="Time" class="sortable">Time</th>
                    <th data-sort="course" class="sortable">Course</th>
                    <th data-sort="race_type" class="sortable">Race Type</th>
                    <th data-sort="class" class="sortable">Class</th>
                    <th>Race Title</th>
                    <th data-sort="horse_name" class="sortable">Horse</th>
                    <th data-sort="fhorsite_rating" class="sortable">FSr</th>
                    <th data-sort="forecast_price" class="sortable">Forecast</th>
                    <th data-sort="starting_price" class="sortable">SP</th>
                    <th data-sort="finish_position" class="sortable">Pos</th>
                    <th>P&L</th>
                    <th>Return</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($results as $row) {
            $stake = 1.00; // £1 stake
            
            // Get finish position
            $position = $row->finish_position ? intval($row->finish_position) : null;
            $won = ($position === 1);
            
            // Get starting price and calculate return
            // Use starting_price_decimal from historic_runners if available (more reliable)
            $sp = $row->starting_price;
            $sp_decimal = null;
            
            // Prefer starting_price_decimal if available (from historic_runners table)
            if (isset($row->starting_price_decimal) && $row->starting_price_decimal > 0) {
                $sp_decimal = floatval($row->starting_price_decimal);
            } else {
                // Fallback to converting from starting_price string
                $sp_decimal = convert_fractional_to_decimal($sp);
            }
            
            $pl = 0;
            $return = 0;
            
            if ($won && $sp_decimal) {
                // Won: return = stake * decimal odds, profit = return - stake
                $return = $stake * $sp_decimal;
                $pl = $return - $stake;
            } else {
                // Lost: return = 0, profit = -stake
                $return = 0;
                $pl = -$stake;
            }
            
            // Format display - database uses d-m-Y format (25-01-2026), use it directly
            // No conversion needed since database already has correct format
            $date_formatted = $row->Date ? $row->Date : 'N/A';
            
            $time_formatted = $row->Time ? date('H:i', strtotime($row->Time)) : 'N/A';
            $fsr = $row->fhorsite_rating ?: '-';
            $forecast = $row->forecast_price ?: 'N/A';
            $sp_display = $sp ?: 'N/A';
            $pos_display = $position ? $position : 'N/A';
            
            $pl_class = $pl >= 0 ? 'pl-profit' : 'pl-loss';
            $pl_sign = $pl >= 0 ? '+' : '';
            
            echo '<tr>
                <td>' . esc_html($date_formatted) . '</td>
                <td>' . esc_html($time_formatted) . '</td>
                <td>' . esc_html($row->course ?: 'N/A') . '</td>
                <td>' . esc_html($row->race_type ?: 'N/A') . '</td>
                <td>' . esc_html($row->class ?: 'N/A') . '</td>
                <td><a href="' . esc_url(bricks_race_url($row->race_id)) . '" class="race-link">' . esc_html($row->race_title ?: 'Race') . '</a></td>
                <td style="font-weight:600;">' . esc_html($row->horse_name ?: 'N/A') . '</td>
                <td style="text-align:center;">' . esc_html($fsr) . '</td>
                <td>' . esc_html($forecast) . '</td>
                <td>' . esc_html($sp_display) . '</td>
                <td style="text-align:center;' . ($won ? 'color:#10b981;font-weight:700;' : '') . '">' . esc_html($pos_display) . '</td>
                <td class="' . $pl_class . '" style="font-weight:700;text-align:right;">' . $pl_sign . '£' . number_format($pl, 2) . '</td>
                <td style="text-align:right;">£' . number_format($return, 2) . '</td>
            </tr>';
        }
        
        echo '</tbody></table></div>';
        
        // Summary row
        $roi = $total_stakes > 0 ? (($total_profit / $total_stakes) * 100) : 0;
        $win_rate = ($total_wins + $total_losses) > 0 ? (($total_wins / ($total_wins + $total_losses)) * 100) : 0;
        
        echo '<div class="pl-summary" style="margin-top:24px;padding:20px;background:#f8f9fa;border-radius:10px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;">
                <div>
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:700;margin-bottom:4px;">Total Stakes</div>
                    <div style="font-size:24px;font-weight:700;color:#374151;">£' . number_format($total_stakes, 2) . '</div>
                </div>
                <div>
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:700;margin-bottom:4px;">Total P&L</div>
                    <div style="font-size:24px;font-weight:700;color:' . ($total_profit >= 0 ? '#10b981' : '#ef4444') . ';">' . ($total_profit >= 0 ? '+' : '') . '£' . number_format($total_profit, 2) . '</div>
                </div>
                <div>
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:700;margin-bottom:4px;">ROI</div>
                    <div style="font-size:24px;font-weight:700;color:' . ($roi >= 0 ? '#10b981' : '#ef4444') . ';">' . ($roi >= 0 ? '+' : '') . number_format($roi, 2) . '%</div>
                </div>
                <div>
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:700;margin-bottom:4px;">Wins / Losses</div>
                    <div style="font-size:24px;font-weight:700;color:#374151;">' . $total_wins . ' / ' . $total_losses . '</div>
                </div>
                <div>
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:700;margin-bottom:4px;">Win Rate</div>
                    <div style="font-size:24px;font-weight:700;color:#374151;">' . number_format($win_rate, 1) . '%</div>
                </div>
            </div>
        </div>';
        
        // Pagination
        if ($total_pages > 1) {
            echo '<div class="pl-pagination-wrapper" style="margin-top:20px;text-align:center;">';
            
            if ($paged > 1) {
                echo '<a class="pl-pagination-btn" href="#" data-page="' . ($paged - 1) . '">&laquo; Prev</a>';
            }
            
            $start_page = max(1, $paged - 2);
            $end_page = min($total_pages, $paged + 2);
            
            if ($start_page > 1) {
                echo '<a class="pl-pagination-btn" href="#" data-page="1">1</a>';
                if ($start_page > 2) echo '<span style="padding:0 8px;color:#6b7280;">...</span>';
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = $i == $paged ? ' pl-pagination-btn-active' : '';
                echo '<a class="pl-pagination-btn' . $active . '" href="#" data-page="' . $i . '">' . $i . '</a>';
            }
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) echo '<span style="padding:0 8px;color:#6b7280;">...</span>';
                echo '<a class="pl-pagination-btn" href="#" data-page="' . $total_pages . '">' . $total_pages . '</a>';
            }
            
            if ($paged < $total_pages) {
                echo '<a class="pl-pagination-btn" href="#" data-page="' . ($paged + 1) . '">Next &raquo;</a>';
            }
            
            echo '</div>';
        }
    } else {
        echo '<div style="text-align:center;padding:40px;color:#6b7280;">
            <div style="font-size:48px;margin-bottom:16px;">🔍</div>
            <div style="font-size:18px;font-weight:600;margin-bottom:8px;">No results found</div>
            <div style="font-size:14px;">Try adjusting your filters</div>
        </div>';
    }
    
    wp_die(ob_get_clean());
}
add_action('wp_ajax_load_pl_table', 'bricks_ajax_load_pl_table');
add_action('wp_ajax_nopriv_load_pl_table', 'bricks_ajax_load_pl_table');

/**
 * Inline JavaScript for P&L table
 */
function bricks_pl_table_inline_js() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('=== P&L TABLE JS INITIALIZING ===');
        
        // Only run if the P&L table container exists
        if ($('#pl-table-container').length === 0) {
            console.log('P&L Table: Container not found, exiting');
            return;
        }
        
        console.log('P&L Table: Container found, continuing initialization');
        
        // Define AJAX URL (fallback if not localized)
        var pl_ajax_url = (typeof pl_ajax_obj !== 'undefined' && pl_ajax_obj.ajaxurl) ? pl_ajax_obj.ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>';
        
        let currentPage = 1;
        let currentSort = { column: null, direction: 'desc' };
        
        function loadPLTable() {
            const dateFrom = $('#pl-date-from').val();
            const dateTo = $('#pl-date-to').val();
            const datePeriod = $('#pl-date-period').val();
            
            console.log('P&L Table: Loading with date_period:', datePeriod, 'dateFrom:', dateFrom, 'dateTo:', dateTo);
            
            // Only send custom dates if period is custom and dates are filled and valid
            const data = {
                action: 'load_pl_table',
                page: currentPage,
                date_period: datePeriod,
                course: $('#pl-course').val(),
                race_type: $('#pl-race-type').val(),
                class: $('#pl-class').val(),
                handicap: $('#pl-handicap').val(),
                runners_from: $('#pl-runners-from').val(),
                runners_to: $('#pl-runners-to').val(),
                sort_column: currentSort.column,
                sort_direction: currentSort.direction
            };
            
            // Only add date_from and date_to if period is custom and dates are provided and valid
            if (datePeriod === 'custom') {
                if (dateFrom) {
                    const fromDate = new Date(dateFrom);
                    if (fromDate.getFullYear() > 1970) {
                        data.date_from = dateFrom;
                    } else {
                        console.warn('P&L Table: Invalid date_from, ignoring');
                    }
                }
                if (dateTo) {
                    const toDate = new Date(dateTo);
                    if (toDate.getFullYear() > 1970) {
                        data.date_to = dateTo;
                    } else {
                        console.warn('P&L Table: Invalid date_to, ignoring');
                    }
                }
            }
            
            console.log('P&L Table: Sending data to server:', data);
            
            $('#pl-table-container').html('<div style="text-align:center;padding:40px;"><div style="font-size:18px;">Loading...</div></div>');
            
            $.post(pl_ajax_url, data, function(response) {
                console.log('P&L Table: Response received, length:', response.length);
                $('#pl-table-container').html(response);
            }).fail(function(xhr, status, error) {
                console.error('P&L Table: AJAX error:', status, error);
            });
        }
        
        // Filter change handlers
        $('#pl-date-period, #pl-course, #pl-race-type, #pl-class, #pl-handicap, #pl-runners-from, #pl-runners-to').on('change', function() {
            currentPage = 1;
            loadPLTable();
        });
        
        // Date inputs - validate and trigger when custom dates are set (d-m-Y format)
        $('#pl-date-from, #pl-date-to').on('change blur', function() {
            const dateFrom = $('#pl-date-from').val();
            const dateTo = $('#pl-date-to').val();
            
            // Validate dates are in d-m-Y format (25-01-2026)
            const datePattern = /^(\d{2})-(\d{2})-(\d{4})$/;
            
            if (dateFrom && dateTo) {
                if (datePattern.test(dateFrom) && datePattern.test(dateTo)) {
                    // Parse d-m-Y format to validate the date
                    const fromParts = dateFrom.split('-');
                    const toParts = dateTo.split('-');
                    const fromDate = new Date(fromParts[2], fromParts[1] - 1, fromParts[0]);
                    const toDate = new Date(toParts[2], toParts[1] - 1, toParts[0]);
                    
                    // Check if dates are valid
                    if (fromDate.getFullYear() > 1970 && toDate.getFullYear() > 1970 && 
                        fromDate.getDate() == fromParts[0] && toDate.getDate() == toParts[0]) {
                        $('#pl-date-period').val('custom');
                        currentPage = 1;
                        loadPLTable();
                    } else {
                        console.warn('P&L Table: Invalid dates detected, ignoring');
                    }
                } else {
                    console.warn('P&L Table: Dates must be in dd-mm-yyyy format (e.g., 25-01-2026)');
                }
            }
        });
        
        // Pagination
        $(document).on('click', '.pl-pagination-btn', function(e) {
            e.preventDefault();
            currentPage = parseInt($(this).data('page'));
            loadPLTable();
        });
        
        // Table sorting
        $(document).on('click', '.pl-table th.sortable', function() {
            const column = $(this).data('sort');
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.direction = 'desc';
            }
            
            $('.pl-table th.sortable').removeClass('sorted-asc sorted-desc active-column');
            $(this).addClass('sorted-' + currentSort.direction).addClass('active-column');
            
            currentPage = 1;
            loadPLTable();
        });
        
        // Reset button
        $('#pl-reset-btn').on('click', function() {
            $('#pl-date-period').val('all');
            $('#pl-date-from, #pl-date-to').val('');
            $('#pl-course, #pl-race-type, #pl-class, #pl-handicap').val('');
            $('#pl-runners-from, #pl-runners-to').val('');
            currentPage = 1;
            currentSort = { column: null, direction: 'desc' };
            $('.pl-table th.sortable').removeClass('sorted-asc sorted-desc active-column');
            loadPLTable();
        });
        
        // Set default to today on initial load
        $('#pl-date-period').val('daily');
        console.log('P&L Table: Set date_period to daily (today), value is now:', $('#pl-date-period').val());
        
        // Initial load
        console.log('P&L Table: Starting initial load');
        loadPLTable();
    });
    </script>
    <?php
}
add_action('wp_footer', 'bricks_pl_table_inline_js');

/**
 * P&L Table Shortcode
 */
function bricks_pl_table_shortcode() {
    global $wpdb;
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE 'speed&performance_table'");
    
    if (!$table_exists) {
        return '<div style="color:red;padding:20px;">Error: Database table "speed&performance_table" not found</div>';
    }
    
    // Get filter options
    $courses = $wpdb->get_col(
        "SELECT DISTINCT `course` FROM `speed&performance_table` 
         WHERE `course` IS NOT NULL AND `course` != '' 
         ORDER BY `course`"
    );
    
    $race_types = $wpdb->get_col(
        "SELECT DISTINCT `race_type` FROM `speed&performance_table` 
         WHERE `race_type` IS NOT NULL AND `race_type` != '' 
         ORDER BY `race_type`"
    );
    
    $classes = $wpdb->get_col(
        "SELECT DISTINCT `class` FROM `speed&performance_table` 
         WHERE `class` IS NOT NULL AND `class` != '' 
         ORDER BY `class`"
    );
    
    ob_start();
    ?>
    <style>
        .pl-table-wrapper {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 24px;
            margin-bottom: 30px;
        }
        
        .pl-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .pl-filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .pl-filter-group label {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .pl-filters select,
        .pl-filters input {
            padding: 10px 12px;
            font-size: 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            transition: all 0.2s ease;
            outline: none;
        }
        
        .pl-filters select:focus,
        .pl-filters input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        #pl-reset-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
        }
        
        #pl-reset-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }
        
        .pl-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        
        .pl-table th,
        .pl-table td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .pl-table thead th {
            background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%);
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #d1d5db;
        }
        
        .pl-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .pl-table tbody tr:hover {
            background: #f9fafb;
        }
        
        .pl-table th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 24px;
        }
        
        .pl-table th.sortable:hover {
            background: #e5e7eb;
        }
        
        .pl-table th.sortable::after {
            content: '⇅';
            position: absolute;
            right: 8px;
            opacity: 0.3;
            font-size: 12px;
        }
        
        .pl-table th.sortable.sorted-asc::after {
            content: '↑';
            opacity: 1;
            color: #8b5cf6;
        }
        
        .pl-table th.sortable.sorted-desc::after {
            content: '↓';
            opacity: 1;
            color: #8b5cf6;
        }
        
        .pl-table th.sortable.active-column {
            background: #f5f3ff;
            color: #6d28d9;
        }
        
        .pl-profit {
            color: #10b981;
        }
        
        .pl-loss {
            color: #ef4444;
        }
        
        .pl-pagination-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pl-pagination-btn {
            padding: 8px 16px;
            border-radius: 6px;
            background: white;
            border: 2px solid #e5e7eb;
            color: #374151;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .pl-pagination-btn:hover {
            border-color: #8b5cf6;
            background: #f5f3ff;
            color: #6d28d9;
        }
        
        .pl-pagination-btn-active {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border-color: #8b5cf6;
        }
        
        .race-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .race-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
    </style>
    
    <div class="pl-table-wrapper">
        <h1 style="margin:0 0 24px 0;color:#1e293b;font-size:28px;font-weight:700;">P&L Analysis - Fhorsite Rating ROI</h1>
        <p style="margin:0 0 24px 0;color:#6b7280;font-size:14px;">Shows Return on Investment for backing the highest Fhorsite rated horse with a £1 stake in each historical race.</p>
        
        <div class="pl-filters">
            <div class="pl-filter-group">
                <label>Date Period</label>
                <select id="pl-date-period">
                    <option value="daily" selected>Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="all">All Time</option>
                    <option value="monthly">This Month</option>
                    <option value="yearly">This Year</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            
            <div class="pl-filter-group">
                <label>Date From (dd-mm-yyyy)</label>
                <input type="text" id="pl-date-from" placeholder="25-01-2026" pattern="\d{2}-\d{2}-\d{4}">
            </div>
            
            <div class="pl-filter-group">
                <label>Date To (dd-mm-yyyy)</label>
                <input type="text" id="pl-date-to" placeholder="25-01-2026" pattern="\d{2}-\d{2}-\d{4}">
            </div>
            
            <div class="pl-filter-group">
                <label>Course</label>
                <select id="pl-course">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo esc_attr($course); ?>"><?php echo esc_html($course); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="pl-filter-group">
                <label>Race Type</label>
                <select id="pl-race-type">
                    <option value="">All Types</option>
                    <?php foreach ($race_types as $type): ?>
                        <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="pl-filter-group">
                <label>Class</label>
                <select id="pl-class">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo esc_attr($class); ?>"><?php echo esc_html($class); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="pl-filter-group">
                <label>Handicap</label>
                <select id="pl-handicap">
                    <option value="">All</option>
                    <option value="1">Handicap</option>
                    <option value="0">Non-Handicap</option>
                </select>
            </div>
            
            <div class="pl-filter-group">
                <label>Runners From</label>
                <input type="number" id="pl-runners-from" min="0" placeholder="Min">
            </div>
            
            <div class="pl-filter-group">
                <label>Runners To</label>
                <input type="number" id="pl-runners-to" min="1" placeholder="Max">
            </div>
            
            <div class="pl-filter-group" style="justify-content:flex-end;">
                <label>&nbsp;</label>
                <button id="pl-reset-btn">Reset Filters</button>
            </div>
        </div>
        
        <div id="pl-table-container">
            <div style="text-align:center;padding:40px;">
                <div style="font-size:18px;">Loading...</div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('pl_table', 'bricks_pl_table_shortcode');


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

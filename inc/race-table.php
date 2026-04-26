<?php
/**
 * Race table feature: shortcode, AJAX, and inline UI script.
 */

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

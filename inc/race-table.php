<?php
require_once __DIR__ . '/race-table-query.php';
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

    if (function_exists('bricks_race_tables_for_date')) {
        $tables = bricks_race_tables_for_date($date);
        $table = $tables['races'];
    } else {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $table = ($date === $tomorrow) ? 'advance_daily_races' : 'advance_daily_races_beta';
    }

    $cache_key = function_exists('bricks_cache_key') ? bricks_cache_key('race_filters', array($table, $date)) : ('race_filter_opts_' . md5($table . '|' . $date));
    $cached = get_transient($cache_key);
    if ($cached !== false && is_array($cached)) {
        wp_send_json($cached);
        return;
    }

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

    $payload = [
        'countries' => $countries,
        'courses' => $courses,
        'types' => $types,
        'classes' => $classes,
        'ages' => $ages,
        'handicaps' => $handicaps,
    ];

    set_transient($cache_key, $payload, 10 * MINUTE_IN_SECONDS);
    wp_send_json($payload);
}


add_action('wp_ajax_get_race_filter_options', 'bricks_get_race_filter_options');
add_action('wp_ajax_nopriv_get_race_filter_options', 'bricks_get_race_filter_options');

function bricks_ajax_load_race_table() {
    $html = bricks_race_table_query_html([
        'date' => !empty($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : (function_exists('bricks_daily_archive_today') ? bricks_daily_archive_today() : date('Y-m-d')),
        'page' => isset($_POST['race_page']) ? intval($_POST['race_page']) : 1,
        'country' => isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '',
        'course' => isset($_POST['course']) ? sanitize_text_field(wp_unslash($_POST['course'])) : '',
        'race_type' => isset($_POST['race_type']) ? sanitize_text_field(wp_unslash($_POST['race_type'])) : '',
        'class' => isset($_POST['class']) ? sanitize_text_field(wp_unslash($_POST['class'])) : '',
        'handicap' => isset($_POST['handicap']) ? sanitize_text_field(wp_unslash($_POST['handicap'])) : '',
        'age_range' => isset($_POST['age_range']) ? sanitize_text_field(wp_unslash($_POST['age_range'])) : '',
        'runners_from' => isset($_POST['runners_from']) ? sanitize_text_field(wp_unslash($_POST['runners_from'])) : '',
        'runners_to' => isset($_POST['runners_to']) ? sanitize_text_field(wp_unslash($_POST['runners_to'])) : '',
        'sort_column' => isset($_POST['sort_column']) ? sanitize_text_field(wp_unslash($_POST['sort_column'])) : '',
        'sort_direction' => isset($_POST['sort_direction']) ? sanitize_text_field(wp_unslash($_POST['sort_direction'])) : 'asc',
        'include_tracker' => is_user_logged_in(),
        'use_cache' => !is_user_logged_in(),
    ]);
    wp_die($html);
}

add_action('wp_ajax_load_race_table', 'bricks_ajax_load_race_table');
add_action('wp_ajax_nopriv_load_race_table', 'bricks_ajax_load_race_table');

// ==============================================
// SHORTCODE FOR DISPLAY
// ==============================================

function bricks_race_table_shortcode($atts = []) {
    global $wpdb;

    $atts = shortcode_atts([
        'course' => '',
        'lock_course' => '0',
        'hide_course_filter' => '0',
        'date' => '',
        'archive' => '0',
    ], $atts, 'race_table');

    $locked_course = trim((string) $atts['course']);
    $lock_course = ($atts['lock_course'] === '1' || $atts['lock_course'] === 'true') && $locked_course !== '';
    $hide_course_filter = $atts['hide_course_filter'] === '1' || $atts['hide_course_filter'] === 'true' || $lock_course;
    $is_archive = ($atts['archive'] === '1' || $atts['archive'] === 'true')
        || (function_exists('bricks_daily_archive_is_request') && bricks_daily_archive_is_request());

    $today_date = function_exists('bricks_daily_archive_today')
        ? bricks_daily_archive_today()
        : wp_date('Y-m-d', current_time('timestamp'));
    $tomorrow_str = function_exists('bricks_daily_archive_tomorrow')
        ? bricks_daily_archive_tomorrow()
        : wp_date('Y-m-d', strtotime('+1 day', current_time('timestamp')));

    $active_date = $today_date;
    if ($atts['date'] !== '' && function_exists('bricks_daily_archive_normalize_date')) {
        $normalized = bricks_daily_archive_normalize_date($atts['date']);
        if ($normalized) {
            $active_date = $normalized;
        }
    } elseif ($is_archive && function_exists('bricks_daily_archive_resolve_date_from_request')) {
        $resolved = bricks_daily_archive_resolve_date_from_request();
        if ($resolved) {
            $active_date = $resolved;
            $is_archive = true;
        }
    }

    $navigation_header = bricks_get_navigation_header();

    if (function_exists('bricks_race_tables_for_date')) {
        $tables = bricks_race_tables_for_date($active_date);
        $table = $tables['races'];
    } else {
        $table = 'advance_daily_races_beta';
    }

    $filter_cache_key = function_exists('bricks_cache_key')
        ? bricks_cache_key('race_filters', [$table, $active_date, 'shortcode'])
        : null;
    $filter_cached = $filter_cache_key ? get_transient($filter_cache_key) : false;
    if (is_array($filter_cached)) {
        $countries = $filter_cached['countries'] ?? [];
        $courses = $filter_cached['courses'] ?? [];
        $types = $filter_cached['types'] ?? [];
        $classes = $filter_cached['classes'] ?? [];
        $ages = $filter_cached['ages'] ?? [];
    } else {
        $countries = $wpdb->get_col(
            $wpdb->prepare("SELECT DISTINCT country FROM `$table` WHERE meeting_date = %s ORDER BY country", $active_date)
        );
        $courses = $wpdb->get_col(
            $wpdb->prepare("SELECT DISTINCT course FROM `$table` WHERE meeting_date = %s ORDER BY course", $active_date)
        );
        $types = $wpdb->get_col(
            $wpdb->prepare("SELECT DISTINCT race_type FROM `$table` WHERE meeting_date = %s ORDER BY race_type", $active_date)
        );
        $classes = $wpdb->get_col(
            $wpdb->prepare("SELECT DISTINCT class FROM `$table` WHERE meeting_date = %s ORDER BY class", $active_date)
        );
        $ages = $wpdb->get_col(
            $wpdb->prepare("SELECT DISTINCT age_range FROM `$table` WHERE meeting_date = %s ORDER BY age_range", $active_date)
        );
        if ($filter_cache_key) {
            set_transient($filter_cache_key, compact('countries', 'courses', 'types', 'classes', 'ages'), 10 * MINUTE_IN_SECONDS);
        }
    }

    $dates = [];
    if ($is_archive) {
        $dates[] = [
            'label' => wp_date('D j M', strtotime($active_date . ' 12:00:00')),
            'value' => $active_date,
            'is_today' => true,
        ];
    } else {
        $dates[] = [
            'label' => 'Today',
            'value' => $today_date,
            'is_today' => true,
        ];
        $dates[] = [
            'label' => 'Tomorrow',
            'value' => $tomorrow_str,
            'is_today' => false,
        ];
    }

    $ssr_html = '';
    if (function_exists('bricks_race_table_query_html')) {
        $ssr_html = bricks_race_table_query_html([
            'date' => $active_date,
            'page' => 1,
            'course' => $lock_course ? $locked_course : '',
            'include_tracker' => false,
            'use_cache' => true,
        ]);
    }

    ob_start();
    ?>
    <style>
        /* Dated daily hub H1 */
        .daily-hub-header {
            max-width: 1400px;
            margin: 0 auto 20px;
            padding: 8px 4px 4px;
            box-sizing: border-box;
        }
        .daily-hub-header__title {
            margin: 0 0 8px;
            font-size: clamp(1.35rem, 3.2vw, 1.85rem);
            font-weight: 800;
            line-height: 1.25;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .daily-hub-header__intro {
            margin: 0 0 4px;
            font-size: 14px;
            line-height: 1.5;
            color: #64748b;
        }

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
        .race-table tbody tr[data-course-header],
        .race-table tbody tr.race-course-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        }
        
        .race-table tbody tr[data-course-header] td,
        .race-table tbody tr.race-course-header td {
            color: white;
            font-weight: 700;
            font-size: 14px;
            padding: 12px;
            border: none;
        }

        .race-class-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            background: #dbeafe;
            color: #1e40af;
            font-weight: 600;
            font-size: 11px;
        }

        .race-runners-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            font-weight: 700;
            font-size: 12px;
        }

        .race-cell--time {
            color: #3b82f6;
            font-weight: 600;
        }

        .race-cell--prize {
            color: #059669;
            font-weight: 700;
        }

        .race-filters-toggle,
        .race-mobile-toolbar {
            display: none;
        }

        .race-table-scroll {
            width: 100%;
            max-width: 100%;
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
                padding: 12px;
                margin-bottom: 20px;
                overflow-x: hidden;
            }
            
            .race-filters {
                display: none;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                padding: 12px;
            }

            .race-filters.is-open {
                display: grid;
            }

            .race-filters-toggle {
                display: flex;
                width: 100%;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 10px;
                padding: 12px 14px;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                background: #f8fafc;
                color: #0f172a;
                font-weight: 700;
                font-size: 14px;
                cursor: pointer;
            }

            .race-filters-toggle__state {
                color: #2563eb;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }

            .race-mobile-toolbar {
                display: flex;
                align-items: center;
                gap: 10px;
                margin: 0 0 12px;
                padding: 10px 12px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
            }

            .race-mobile-toolbar__label {
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: #64748b;
                flex-shrink: 0;
            }

            .race-mobile-toolbar__select {
                flex: 1;
                min-width: 0;
                padding: 10px 12px;
                border: 2px solid #e2e8f0;
                border-radius: 8px;
                background: #fff;
                font-size: 16px;
                font-weight: 600;
                color: #0f172a;
            }
            
            .filter-group label {
                font-size: 10px;
            }
            
            .race-filters select,
            .race-filters input[type="number"],
            .race-filters input[type="date"] {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 10px;
            }
            
            .race-reset-button {
                width: 100%;
                padding: 12px;
                font-size: 14px;
                grid-column: 1 / -1;
            }
            
            .race-date-tabs {
                flex-direction: row;
                gap: 8px;
                padding: 8px;
            }
            
            .race-date-tab {
                padding: 10px 14px;
                font-size: 13px;
                flex: 1;
                width: auto;
            }
            
            #race-table-container {
                overflow: visible;
                border-radius: 10px;
                border: none;
                background: transparent;
            }
            
            .race-table,
            .race-table thead,
            .race-table tbody,
            .race-table tr,
            .race-table th,
            .race-table td {
                display: block;
                width: 100%;
                max-width: 100%;
            }

            .race-table {
                font-size: 13px;
                min-width: 0 !important;
                border: none;
            }

            .race-table thead {
                display: none !important;
            }

            .race-table tbody {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .race-table tbody tr.race-course-header,
            .race-table tbody tr[data-course-header] {
                display: block;
                border-radius: 10px;
                overflow: hidden;
            }

            .race-table tbody tr.race-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px 12px;
                padding: 12px;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
            }

            .race-table tbody tr.race-row:hover {
                background: #fff;
            }

            .race-table tbody tr.race-row td.race-cell {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
                padding: 0 !important;
                border: none !important;
                min-width: 0;
            }

            .race-table tbody tr.race-row td.race-cell::before {
                content: attr(data-label);
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: #64748b;
                flex-shrink: 0;
            }

            .race-table tbody tr.race-row td.race-cell > * {
                margin-left: auto;
                text-align: right;
            }

            .race-table tbody tr.race-row td.race-cell--time {
                order: -20;
                grid-column: 1;
                justify-content: flex-start;
                font-size: 18px;
            }

            .race-table tbody tr.race-row td.race-cell--time::before {
                display: none;
            }

            .race-table tbody tr.race-row td.race-cell--runners {
                order: -19;
                grid-column: 2;
                justify-content: flex-end;
            }

            .race-table tbody tr.race-row td.race-cell--runners::before {
                display: none;
            }

            .race-table tbody tr.race-row td.race-cell--title {
                order: -18;
                grid-column: 1 / -1;
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
                padding-bottom: 8px !important;
                border-bottom: 1px solid #e2e8f0 !important;
            }

            .race-table tbody tr.race-row td.race-cell--title::before {
                display: none;
            }

            .race-table tbody tr.race-row td.race-cell--title > * {
                margin-left: 0;
                text-align: left;
                width: 100%;
            }

            .race-table tbody tr.race-row td.race-cell--title .race-link {
                font-size: 15px;
                line-height: 1.3;
            }

            .race-table tbody tr.race-row td.race-cell--prize {
                grid-column: 1 / -1;
                background: #f0fdf4;
                border-radius: 8px;
                padding: 8px 10px !important;
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
                padding: 10px;
            }
            
            .race-filters {
                grid-template-columns: 1fr;
                padding: 10px;
            }
            
            .race-table tbody tr.race-row {
                padding: 10px;
                gap: 7px 10px;
            }
            
            .race-pagination-btn {
                padding: 6px 10px;
                font-size: 11px;
            }
        }

        /* Loading State / CLS reservation for SSR + AJAX swaps */
        #race-table-container {
            min-height: 420px;
            position: relative;
        }
        .race-table-scroll {
            min-height: 280px;
            content-visibility: auto;
            contain-intrinsic-size: 280px;
        }
    </style>

    <?php
    // Dated H1 on /daily/ hub; archive pages get their own dated H1.
    if ($is_archive) {
        $archive_h1 = function_exists('bricks_seo_build_daily_archive_h1')
            ? bricks_seo_build_daily_archive_h1($active_date)
            : ('Horse Racing Ratings Archive: ' . $active_date);
        $archive_intro = function_exists('bricks_seo_build_daily_archive_meta_description')
            ? bricks_seo_build_daily_archive_meta_description($active_date)
            : '';
        echo '<header class="daily-hub-header page-header daily-archive-header">';
        echo '<div class="page-header-container daily-hub-header__inner">';
        echo '<h1 class="page-title daily-hub-header__title"><span aria-hidden="true">🏁</span> ' . esc_html($archive_h1) . '</h1>';
        if ($archive_intro !== '') {
            echo '<p class="page-description daily-hub-header__intro">' . esc_html($archive_intro) . '</p>';
        }
        echo '</div></header>';
        if (function_exists('bricks_daily_archive_nav_html')) {
            echo bricks_daily_archive_nav_html($active_date);
        }
    } elseif (!$lock_course && function_exists('bricks_seo_render_daily_page_header_html')) {
        echo bricks_seo_render_daily_page_header_html();
        if (function_exists('bricks_daily_live_yesterday_archive_link_html')) {
            echo bricks_daily_live_yesterday_archive_link_html();
        }
    }
    ?>

    <div class="race-table-wrapper" data-default-date="<?php echo esc_attr($active_date); ?>" data-archive="<?php echo $is_archive ? '1' : '0'; ?>"<?php
        if ($lock_course && $locked_course !== '') {
            echo ' data-locked-course="' . esc_attr($locked_course) . '"';
        }
    ?>>

    <button type="button" class="race-filters-toggle" id="raceFiltersToggle" aria-expanded="false" aria-controls="raceFiltersPanel">
        <span>Filters</span>
        <span class="race-filters-toggle__state">Show</span>
    </button>

    <div class="race-filters" id="raceFiltersPanel">
        <div class="filter-group">
            <label for="race-country-filter">Country:</label>
            <select id="race-country-filter" class="race-filter">
                <option value="">All Countries</option>
                <?php foreach ($countries as $country): ?>
                    <option value="<?= esc_attr($country) ?>"><?= esc_html($country) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group"<?php echo $hide_course_filter ? ' style="display:none;"' : ''; ?>>
            <label for="race-course-filter">Course:</label>
            <select id="race-course-filter" class="race-filter"<?php echo $lock_course ? ' data-locked="1"' : ''; ?>>
                <option value="">All Courses</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= esc_attr($course) ?>"<?php selected($lock_course && $locked_course === $course); ?>><?= esc_html($course) ?></option>
                <?php endforeach; ?>
                <?php if ($lock_course && $locked_course !== '' && !in_array($locked_course, $courses, true)): ?>
                    <option value="<?= esc_attr($locked_course) ?>" selected><?= esc_html($locked_course) ?></option>
                <?php endif; ?>
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

    <div class="race-mobile-toolbar">
        <label class="race-mobile-toolbar__label" for="raceMobileSort">Sort by</label>
        <select id="raceMobileSort" class="race-mobile-toolbar__select" aria-label="Sort races">
            <option value="scheduled_time" selected>Time</option>
            <option value="country">Country</option>
            <option value="race_type">Type</option>
            <option value="class">Class</option>
            <option value="handicap">Handicap</option>
            <option value="age_range">Age</option>
            <option value="distance_yards">Distance</option>
            <option value="prize_pos_1">Prize</option>
            <option value="runner_count">Runners</option>
        </select>
    </div>

    <div id="race-table-container" data-ssr="<?php echo $ssr_html !== '' ? '1' : '0'; ?>">
        <?php if ($ssr_html !== ''): ?>
            <?php echo $ssr_html; ?>
        <?php else: ?>
        <div style="text-align:center;padding:60px 20px;color:#6b7280;">
            <div style="font-size:48px;margin-bottom:16px;">🏇</div>
            <div style="font-size:16px;font-weight:600;">Loading races...</div>
        </div>
        <?php endif; ?>
    </div>
  
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
        
        $h1 = function_exists('bricks_seo_build_daily_h1')
            ? bricks_seo_build_daily_h1()
            : "Today's Horse Racing Ratings: " . wp_date('l, d F Y', current_time('timestamp'));
        $intro = function_exists('bricks_seo_build_daily_intro')
            ? bricks_seo_build_daily_intro()
            : "Today's UK and Irish meetings — turf speed ratings, All-Weather AW speed figures, and full racecard data";

        // Mark daily header as rendered so race_table does not duplicate the H1.
        if (function_exists('bricks_seo_render_daily_page_header_html')) {
            bricks_seo_render_daily_page_header_html();
        }

        $content .= '
        <div class="page-header daily-hub-header">
            <div class="page-header-container daily-hub-header__inner">
                <h1 class="page-title daily-hub-header__title">
                    <span aria-hidden="true">🏁</span>
                    ' . esc_html($h1) . '
                </h1>
                <p class="page-description daily-hub-header__intro">' . esc_html($intro) . '</p>
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

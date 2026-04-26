<?php
/**
 * Bricks Builder - Quick Reference Table
 * PART 1: Add this to your child theme's functions.php or code snippets plugin
 */

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

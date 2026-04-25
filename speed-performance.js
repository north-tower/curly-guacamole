/**
 * Speed & Performance Table JavaScript
 * Save this as speed-performance.js in your Bricks child theme folder
 */

jQuery(document).ready(function($) {
    let currentPage = 1;
    let currentFilters = {};
    let currentSort = { column: '', direction: 'asc' };

    function loadTable() {
        const $container = $('#speed-performance-table-container');
        
        // Show loading state
        $container.html('<div style="text-align:center;padding:40px;"><div style="display:inline-block;width:40px;height:40px;border:4px solid #f3f3f3;border-top:4px solid #3498db;border-radius:50%;animation:spin 1s linear infinite;"></div><p style="margin-top:10px;color:#666;">Loading data...</p></div>');
        
        // Add CSS for spinner animation if not already added
        if (!$('#spinner-animation-style').length) {
            $('head').append('<style id="spinner-animation-style">@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>');
        }

        const data = {
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

        if (speed_performance_ajax_obj.debug) {
            console.log('Loading table with data:', data);
        }

        $.post(speed_performance_ajax_obj.ajax_url, data, function(response) {
            if (speed_performance_ajax_obj.debug) {
                console.log('Response received:', response.substring(0, 200));
            }
            $container.html(response);
            
            // Update sort indicators
            $('.speed-performance-table th.sortable').removeClass('sorted-asc sorted-desc active-column');
            if (currentSort.column) {
                const $th = $('.speed-performance-table th[data-sort="' + currentSort.column + '"]');
                $th.addClass('active-column');
                $th.addClass(currentSort.direction === 'asc' ? 'sorted-asc' : 'sorted-desc');
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            $container.html('<div style="text-align:center;padding:20px;color:red;">Error loading data. Please try again.</div>');
        });
    }

    function updateFilterOptions() {
        const date = speed_performance_ajax_obj.default_date;
        
        const data = {
            action: 'get_speed_performance_filter_options',
            date: date
        };

        if (speed_performance_ajax_obj.debug) {
            console.log('Updating filter options for date:', date);
        }

        $.post(speed_performance_ajax_obj.ajax_url, data, function(response) {
            if (speed_performance_ajax_obj.debug) {
                console.log('Filter options received:', response);
            }

            // Update runners
            const $runnerFilter = $('#speed-performance-runner-filter');
            const currentRunner = $runnerFilter.val();
            $runnerFilter.html('<option value="">All Runners</option>');
            if (response.runners) {
                response.runners.forEach(function(runner) {
                    $runnerFilter.append('<option value="' + runner + '">' + runner + '</option>');
                });
            }
            $runnerFilter.val(currentRunner);

            // Update courses
            const $courseFilter = $('#speed-performance-course-filter');
            const currentCourse = $courseFilter.val();
            $courseFilter.html('<option value="">All Courses</option>');
            if (response.courses) {
                response.courses.forEach(function(course) {
                    $courseFilter.append('<option value="' + course + '">' + course + '</option>');
                });
            }
            $courseFilter.val(currentCourse);

            // Update trainers
            const $trainerFilter = $('#speed-performance-trainer-filter');
            const currentTrainer = $trainerFilter.val();
            $trainerFilter.html('<option value="">All Trainers</option>');
            if (response.trainers) {
                response.trainers.forEach(function(trainer) {
                    $trainerFilter.append('<option value="' + trainer + '">' + trainer + '</option>');
                });
            }
            $trainerFilter.val(currentTrainer);

            // Update jockeys
            const $jockeyFilter = $('#speed-performance-jockey-filter');
            const currentJockey = $jockeyFilter.val();
            $jockeyFilter.html('<option value="">All Jockeys</option>');
            if (response.jockeys) {
                response.jockeys.forEach(function(jockey) {
                    $jockeyFilter.append('<option value="' + jockey + '">' + jockey + '</option>');
                });
            }
            $jockeyFilter.val(currentJockey);

            // Update distances
            const $distanceFilter = $('#speed-performance-distance-filter');
            const currentDistance = $distanceFilter.val();
            $distanceFilter.html('<option value="">All Distances</option>');
            if (response.distances) {
                response.distances.forEach(function(distance) {
                    $distanceFilter.append('<option value="' + distance + '">' + distance + '</option>');
                });
            }
            $distanceFilter.val(currentDistance);

            // Update race types
            const $raceTypeFilter = $('#speed-performance-race-type-filter');
            const currentRaceType = $raceTypeFilter.val();
            $raceTypeFilter.html('<option value="">All Race Types</option>');
            if (response.race_types) {
                response.race_types.forEach(function(race_type) {
                    $raceTypeFilter.append('<option value="' + race_type + '">' + race_type + '</option>');
                });
            }
            $raceTypeFilter.val(currentRaceType);

        }).fail(function(xhr, status, error) {
            console.error('Error updating filter options:', status, error);
        });
    }

    // Filter change handler
    $('.speed-performance-filter').on('change input', function() {
        currentPage = 1;
        loadTable();
    });

    // Reset button handler
    $('#speed-performance-reset-btn').on('click', function() {
        $('.speed-performance-filter').val('');
        $('#speed-performance-fsr-filter').val('');
        currentPage = 1;
        currentSort = { column: '', direction: 'asc' };
        updateFilterOptions();
        loadTable();
    });

    // Pagination handler
    $(document).on('click', '.speed-performance-pagination-btn', function(e) {
        e.preventDefault();
        currentPage = parseInt($(this).data('page'));
        loadTable();
        
        // Scroll to top of table
        $('html, body').animate({
            scrollTop: $('#speed-performance-table-container').offset().top - 100
        }, 300);
    });

    // Sorting handler
    $(document).on('click', '.speed-performance-table th.sortable', function() {
        const column = $(this).data('sort');
        
        if (currentSort.column === column) {
            // Toggle direction
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            // New column, default to ascending
            currentSort.column = column;
            currentSort.direction = 'asc';
        }
        
        currentPage = 1;
        loadTable();
    });

    if (!window.bricksTrackerBindingsAdded) {
        window.bricksTrackerBindingsAdded = true;

        // Tracker panel toggle
        $(document).on('click', '.tracker-toggle-btn', function() {
            const target = $(this).data('target');
            if (target) {
                $(target).slideToggle(150);
            }
        });

        // Save tracker note
        $(document).on('click', '.tracker-save-btn', function() {
            if (!speed_performance_ajax_obj.is_logged_in) {
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

            $.post(speed_performance_ajax_obj.ajax_url, {
                action: 'bricks_add_tracker_note',
                nonce: speed_performance_ajax_obj.tracker_nonce,
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

        // Delete tracker note
        $(document).on('click', '.tracker-delete-btn', function() {
            if (!speed_performance_ajax_obj.is_logged_in) {
                alert('Please log in to use tracker notes.');
                return;
            }

            if (!confirm('Delete this note?')) {
                return;
            }

            const $button = $(this);
            $button.prop('disabled', true).text('Deleting...');

            $.post(speed_performance_ajax_obj.ajax_url, {
                action: 'bricks_delete_tracker_note',
                nonce: speed_performance_ajax_obj.tracker_nonce,
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
    }

    // Initial load
    updateFilterOptions();
    loadTable();
});
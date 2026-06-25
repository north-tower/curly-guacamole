jQuery(document).ready(function($) {
    let currentPage = 1;
    let currentFilters = { date: race_ajax_obj.default_date };
    let currentSort = { column: '', direction: '' };

    const $wrapper = $('.race-table-wrapper').first();
    const lockedCourse = $wrapper.data('locked-course') || race_ajax_obj.locked_course || '';
    if (lockedCourse) {
        $('#race-course-filter').val(lockedCourse);
        currentFilters.course = lockedCourse;
    }

    loadRaceTable();

    $('.race-date-tab').on('click', function() {
        $('.race-date-tab').removeClass('active');
        $(this).addClass('active');
        const selectedDate = $(this).data('date');

        currentFilters = { date: selectedDate };
        currentPage = 1;
        loadFilterOptions(selectedDate);
        loadRaceTableWithDate(selectedDate);
    });

    $('.race-filter').on('change', function() {
        currentPage = 1;
        loadRaceTable();
    });

    $('#race-reset-btn').on('click', function() {
        $('.race-filter').val('');
        if (lockedCourse) {
            $('#race-course-filter').val(lockedCourse);
        }
        $('#race-runners-from-filter').val('');
        $('#race-runners-to-filter').val('');
        $('.race-date-tab').removeClass('active');
        $('.race-date-tab[data-date="' + race_ajax_obj.default_date + '"]').addClass('active');
        currentFilters = { date: race_ajax_obj.default_date };
        if (lockedCourse) {
            currentFilters.course = lockedCourse;
        }
        currentPage = 1;
        currentSort = { column: '', direction: '' };
        loadFilterOptions(race_ajax_obj.default_date);
        loadRaceTable();
    });

    $(document).on('click', '.race-pagination-btn', function(e) {
        e.preventDefault();
        currentPage = parseInt($(this).data('page'), 10);
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
        $.ajax({
            url: race_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'get_race_filter_options',
                date: date
            },
            success: function(response) {
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
        let activeDate = currentFilters.date;
        if (!activeDate) {
            activeDate = $('.race-date-tab.active').data('date');
        }
        if (!activeDate) {
            activeDate = race_ajax_obj.default_date;
        }

        const filters = {
            action: 'load_race_table',
            race_page: currentPage,
            country: $('#race-country-filter').val(),
            course: lockedCourse || $('#race-course-filter').val(),
            race_type: $('#race-type-filter').val(),
            class: $('#race-class-filter').val(),
            handicap: $('#race-handicap-filter').val(),
            age_range: $('#race-age-filter').val(),
            runners_from: $('#race-runners-from-filter').val(),
            runners_to: $('#race-runners-to-filter').val(),
            date: activeDate,
            sort_column: currentSort.column,
            sort_direction: currentSort.direction
        };

        $('#race-table-container').html('<div style="text-align:center;padding:40px;">Loading...</div>');

        $.ajax({
            url: race_ajax_obj.ajax_url,
            type: 'POST',
            data: filters,
            success: function(response) {
                $('#race-table-container').html(response);

                if (currentSort.column) {
                    $('.race-table th[data-sort="' + currentSort.column + '"]')
                        .addClass('sorted-' + currentSort.direction + ' active-column');
                }
            },
            error: function(xhr, status, error) {
                $('#race-table-container').html('<div style="text-align:center;padding:40px;color:red;">Error loading races. Please try again.</div>');
                if (window.console) {
                    console.error('Race table AJAX error:', status, error);
                }
            }
        });
    }

    function loadRaceTableWithDate(explicitDate) {
        const filters = {
            action: 'load_race_table',
            race_page: currentPage,
            country: $('#race-country-filter').val(),
            course: lockedCourse || $('#race-course-filter').val(),
            race_type: $('#race-type-filter').val(),
            class: $('#race-class-filter').val(),
            handicap: $('#race-handicap-filter').val(),
            age_range: $('#race-age-filter').val(),
            runners_from: $('#race-runners-from-filter').val(),
            runners_to: $('#race-runners-to-filter').val(),
            date: explicitDate,
            sort_column: currentSort.column,
            sort_direction: currentSort.direction
        };

        $('#race-table-container').html('<div style="text-align:center;padding:40px;">Loading...</div>');

        $.ajax({
            url: race_ajax_obj.ajax_url,
            type: 'POST',
            data: filters,
            success: function(response) {
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

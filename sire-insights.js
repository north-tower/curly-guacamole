jQuery(document).ready(function($) {
    if (typeof sire_insights_ajax_obj === 'undefined') {
        return;
    }

    const $container = $('#sire-insights-table-container');
    if (!$container.length) {
        return;
    }

    let currentSort = { column: 'mean_prb', direction: 'desc' };

    function collectFilters() {
        return {
            action: 'load_sire_insights_table',
            date: $('#sire-date-filter').val() || sire_insights_ajax_obj.default_date,
            course: $('#sire-course-filter').val(),
            race_type: $('#sire-type-filter').val(),
            going: $('#sire-going-filter').val(),
            distance_band: $('#sire-distance-filter').val(),
            sire: $('#sire-name-filter').val(),
            sort_column: currentSort.column,
            sort_direction: currentSort.direction
        };
    }

    function updateSelect(selector, options, defaultText) {
        const $select = $(selector);
        const current = $select.val();
        $select.empty().append('<option value="">' + defaultText + '</option>');
        (options || []).forEach(function(opt) {
            $select.append('<option value="' + opt + '">' + opt + '</option>');
        });
        if (current && (options || []).includes(current)) {
            $select.val(current);
        }
    }

    function loadFilterOptions() {
        $.ajax({
            url: sire_insights_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'get_sire_insights_filter_options',
                date: $('#sire-date-filter').val() || sire_insights_ajax_obj.default_date
            },
            success: function(response) {
                if (response && !response.success) {
                    return;
                }
                updateSelect('#sire-course-filter', response.courses, 'All Courses');
                updateSelect('#sire-type-filter', response.race_types, 'All Types');
                updateSelect('#sire-going-filter', response.goings, 'All Going');
                updateSelect('#sire-distance-filter', response.distance_bands, 'All Distances');
                updateSelect('#sire-name-filter', response.sires, 'All Sires');
            }
        });
    }

    function loadTable() {
        $container.html('<div style="text-align:center;padding:28px;color:#6b7280;">Loading...</div>');
        $.ajax({
            url: sire_insights_ajax_obj.ajax_url,
            type: 'POST',
            data: collectFilters(),
            success: function(html) {
                $container.html(html);
                const $headers = $container.find('.sortable-sire');
                $headers.removeClass('sorted-asc sorted-desc');
                $headers.find('.sort-indicator').text('');
                const $active = $container.find('.sortable-sire[data-sort="' + currentSort.column + '"]');
                if ($active.length) {
                    $active.addClass('sorted-' + currentSort.direction);
                    $active.find('.sort-indicator').text(currentSort.direction === 'asc' ? '▲' : '▼');
                }
            },
            error: function() {
                $container.html('<div style="padding:14px;color:#991b1b;background:#fee2e2;border-radius:8px;">Failed to load sire insights.</div>');
            }
        });
    }

    $('#sire-apply-btn').on('click', function() {
        loadTable();
    });

    $('#sire-reset-btn').on('click', function() {
        $('#sire-date-filter').val(sire_insights_ajax_obj.default_date);
        $('#sire-course-filter, #sire-type-filter, #sire-going-filter, #sire-distance-filter, #sire-name-filter').val('');
        currentSort = { column: 'mean_prb', direction: 'desc' };
        loadFilterOptions();
        loadTable();
    });

    $('#sire-date-filter').on('change', function() {
        loadFilterOptions();
    });

    $(document).on('click', '.sire-insights-table th.sortable-sire', function() {
        const nextColumn = $(this).data('sort');
        if (!nextColumn) {
            return;
        }
        if (currentSort.column === nextColumn) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column = nextColumn;
            currentSort.direction = 'asc';
        }
        loadTable();
    });

    loadFilterOptions();
    loadTable();
});

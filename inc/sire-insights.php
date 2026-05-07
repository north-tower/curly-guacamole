<?php
/**
 * Daily sire insights feature: shortcode, AJAX filters, and table render.
 */

if (!function_exists('bricks_sire_insights_table_exists')) {
    function bricks_sire_insights_table_exists() {
        global $wpdb;
        $table = 'daily_sires_insights';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        return $exists === $table;
    }
}

if (!function_exists('bricks_sire_insights_get_columns')) {
    function bricks_sire_insights_get_columns() {
        global $wpdb;
        static $columns = null;
        if ($columns !== null) {
            return $columns;
        }

        if (!bricks_sire_insights_table_exists()) {
            $columns = [];
            return $columns;
        }

        $rows = $wpdb->get_results("SHOW COLUMNS FROM `daily_sires_insights`");
        $columns = [];
        foreach ((array) $rows as $row) {
            if (!empty($row->Field)) {
                $columns[] = $row->Field;
            }
        }
        return $columns;
    }
}

if (!function_exists('bricks_sire_insights_has_column')) {
    function bricks_sire_insights_has_column($column_name) {
        return in_array($column_name, bricks_sire_insights_get_columns(), true);
    }
}

if (!function_exists('bricks_sire_insights_pick_column')) {
    function bricks_sire_insights_pick_column($candidates, $fallback = '') {
        foreach ((array) $candidates as $candidate) {
            if (bricks_sire_insights_has_column($candidate)) {
                return $candidate;
            }
        }
        return $fallback;
    }
}

if (!function_exists('bricks_sire_insights_config')) {
    function bricks_sire_insights_config() {
        $date_col = bricks_sire_insights_pick_column(['meeting_date', 'date', 'race_date', 'Date'], 'meeting_date');
        $course_col = bricks_sire_insights_pick_column(['course']);
        $race_type_col = bricks_sire_insights_pick_column(['race_type', 'race_code']);
        $going_col = bricks_sire_insights_pick_column(['going']);
        $distance_col = bricks_sire_insights_pick_column(['distance_band', 'distance', 'Distance']);
        $sire_col = bricks_sire_insights_pick_column(['sire_name', 'sire']);
        $horse_col = bricks_sire_insights_pick_column(['name', 'horse_name', 'runner_name']);
        $prb_col = bricks_sire_insights_pick_column(['mean_prb', 'prb', 'avg_prb']);

        return [
            'date_col' => $date_col,
            'course_col' => $course_col,
            'race_type_col' => $race_type_col,
            'going_col' => $going_col,
            'distance_col' => $distance_col,
            'sire_col' => $sire_col,
            'horse_col' => $horse_col,
            'prb_col' => $prb_col,
        ];
    }
}

if (!function_exists('bricks_sire_insights_parse_date_for_query')) {
    function bricks_sire_insights_parse_date_for_query($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return date('Y-m-d');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        $ts = strtotime($value);
        if ($ts !== false) {
            return gmdate('Y-m-d', $ts);
        }

        return date('Y-m-d');
    }
}

if (!function_exists('bricks_sire_insights_date_where_sql')) {
    function bricks_sire_insights_date_where_sql($column, $date_ymd, &$params) {
        $params[] = $date_ymd;
        $params[] = convert_date_format($date_ymd, 'd-m-Y');
        return "($column = %s OR $column = %s)";
    }
}

if (!function_exists('bricks_sire_insights_get_filter_options')) {
    function bricks_sire_insights_get_filter_options() {
        global $wpdb;

        if (!bricks_sire_insights_table_exists()) {
            wp_send_json_error(['message' => 'daily_sires_insights table not found']);
            return;
        }

        $cfg = bricks_sire_insights_config();
        if (empty($cfg['date_col'])) {
            wp_send_json_error(['message' => 'No date column found in daily_sires_insights']);
            return;
        }
        $date = bricks_sire_insights_parse_date_for_query($_POST['date'] ?? '');
        $cache_key = bricks_cache_key('sire_filters', [$date]);
        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            wp_send_json($cached);
            return;
        }

        $params = [];
        $where = bricks_sire_insights_date_where_sql('`' . esc_sql($cfg['date_col']) . '`', $date, $params);

        $fetch_distinct = function($column) use ($wpdb, $where, $params) {
            if (empty($column)) {
                return [];
            }
            $safe_col = '`' . esc_sql($column) . '`';
            $sql = "SELECT DISTINCT $safe_col AS v
                    FROM `daily_sires_insights`
                    WHERE $where
                      AND $safe_col IS NOT NULL
                      AND $safe_col != ''
                    ORDER BY $safe_col";
            return $wpdb->get_col($wpdb->prepare($sql, ...$params));
        };

        $payload = [
            'courses' => $fetch_distinct($cfg['course_col']),
            'race_types' => $fetch_distinct($cfg['race_type_col']),
            'goings' => $fetch_distinct($cfg['going_col']),
            'distance_bands' => $fetch_distinct($cfg['distance_col']),
            'sires' => $fetch_distinct($cfg['sire_col']),
        ];

        set_transient($cache_key, $payload, 10 * MINUTE_IN_SECONDS);
        wp_send_json($payload);
    }
}
add_action('wp_ajax_get_sire_insights_filter_options', 'bricks_sire_insights_get_filter_options');
add_action('wp_ajax_nopriv_get_sire_insights_filter_options', 'bricks_sire_insights_get_filter_options');

if (!function_exists('bricks_sire_insights_badge')) {
    function bricks_sire_insights_badge($value, $tone = 'slate') {
        $value = trim((string) $value);
        if ($value === '') {
            return '<span class="sire-pill sire-pill-empty">-</span>';
        }

        $tone_map = [
            'blue' => 'sire-pill-blue',
            'violet' => 'sire-pill-violet',
            'emerald' => 'sire-pill-emerald',
            'amber' => 'sire-pill-amber',
            'slate' => 'sire-pill-slate',
        ];
        $tone_class = $tone_map[$tone] ?? $tone_map['slate'];
        return '<span class="sire-pill ' . esc_attr($tone_class) . '">' . esc_html($value) . '</span>';
    }
}

if (!function_exists('bricks_sire_insights_render_table_html')) {
    function bricks_sire_insights_render_table_html($rows) {
        ob_start();
        ?>
        <div class="sire-insights-table-wrap">
            <table class="sire-insights-table">
                <thead>
                    <tr>
                        <th class="sortable-sire" data-sort="horse_name">Horse <span class="sort-indicator"></span></th>
                        <th class="sortable-sire" data-sort="sire_name">Sire <span class="sort-indicator"></span></th>
                        <th class="sortable-sire" data-sort="course">Course <span class="sort-indicator"></span></th>
                        <th class="sortable-sire" data-sort="race_type">Type <span class="sort-indicator"></span></th>
                        <th class="sortable-sire" data-sort="going">Going <span class="sort-indicator"></span></th>
                        <th class="sortable-sire" data-sort="distance_band">Distance <span class="sort-indicator"></span></th>
                        <th class="sortable-sire ta-right" data-sort="mean_prb">Mean PRB <span class="sort-indicator"></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="sire-empty-cell">No sire insights found for current filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $prb = isset($row->mean_prb) && $row->mean_prb !== null ? floatval($row->mean_prb) : null;
                            $prb_class = 'prb-neutral';
                            if ($prb !== null) {
                                if ($prb >= 58) {
                                    $prb_class = 'prb-strong';
                                } elseif ($prb >= 52) {
                                    $prb_class = 'prb-good';
                                } elseif ($prb <= 42) {
                                    $prb_class = 'prb-weak';
                                }
                            }
                            ?>
                            <tr>
                                <td class="horse-name"><?php echo esc_html($row->horse_name ?? '-'); ?></td>
                                <td class="sire-name"><?php echo esc_html($row->sire_name ?? '-'); ?></td>
                                <td><?php echo bricks_sire_insights_badge($row->course ?? '', 'blue'); ?></td>
                                <td><?php echo bricks_sire_insights_badge($row->race_type ?? '', 'violet'); ?></td>
                                <td><?php echo bricks_sire_insights_badge($row->going ?? '', 'emerald'); ?></td>
                                <td><?php echo bricks_sire_insights_badge($row->distance_band ?? '', 'amber'); ?></td>
                                <td class="ta-right">
                                    <?php if ($prb === null): ?>
                                        <span class="prb-score prb-neutral">-</span>
                                    <?php else: ?>
                                        <span class="prb-score <?php echo esc_attr($prb_class); ?>"><?php echo esc_html(number_format($prb, 1)); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('bricks_ajax_load_sire_insights_table')) {
    function bricks_ajax_load_sire_insights_table() {
        global $wpdb;

        if (!bricks_sire_insights_table_exists()) {
            echo '<div style="padding:16px;color:#991b1b;background:#fee2e2;border-radius:8px;">Sire insights table not found.</div>';
            wp_die();
        }

        $cfg = bricks_sire_insights_config();
        if (empty($cfg['date_col'])) {
            echo '<div style="padding:16px;color:#991b1b;background:#fee2e2;border-radius:8px;">No usable date column found in sire insights table.</div>';
            wp_die();
        }
        $date = bricks_sire_insights_parse_date_for_query($_POST['date'] ?? '');
        $course = sanitize_text_field($_POST['course'] ?? '');
        $race_type = sanitize_text_field($_POST['race_type'] ?? '');
        $going = sanitize_text_field($_POST['going'] ?? '');
        $distance_band = sanitize_text_field($_POST['distance_band'] ?? '');
        $sire = sanitize_text_field($_POST['sire'] ?? '');
        $sort_column = sanitize_text_field($_POST['sort_column'] ?? 'mean_prb');
        $sort_direction = strtolower(sanitize_text_field($_POST['sort_direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $where_parts = [];
        $params = [];
        $where_parts[] = bricks_sire_insights_date_where_sql('`' . esc_sql($cfg['date_col']) . '`', $date, $params);

        $maybe_add_filter = function($value, $column) use (&$where_parts, &$params, $wpdb) {
            if ($value === '' || $column === '') {
                return;
            }
            $where_parts[] = $wpdb->prepare('`' . esc_sql($column) . '` = %s', $value);
        };

        $maybe_add_filter($course, $cfg['course_col']);
        $maybe_add_filter($race_type, $cfg['race_type_col']);
        $maybe_add_filter($going, $cfg['going_col']);
        $maybe_add_filter($distance_band, $cfg['distance_col']);
        $maybe_add_filter($sire, $cfg['sire_col']);

        $sortable_map = [
            'horse_name' => $cfg['horse_col'],
            'sire_name' => $cfg['sire_col'],
            'course' => $cfg['course_col'],
            'race_type' => $cfg['race_type_col'],
            'going' => $cfg['going_col'],
            'distance_band' => $cfg['distance_col'],
            'mean_prb' => $cfg['prb_col'],
        ];
        $selected_sort_col = $sortable_map[$sort_column] ?? $cfg['prb_col'];
        if ($selected_sort_col === '') {
            $selected_sort_col = $cfg['sire_col'] ?: $cfg['date_col'];
        }

        $select_parts = [];
        $select_parts[] = !empty($cfg['horse_col']) ? '`' . esc_sql($cfg['horse_col']) . '` AS horse_name' : "'' AS horse_name";
        $select_parts[] = !empty($cfg['sire_col']) ? '`' . esc_sql($cfg['sire_col']) . '` AS sire_name' : "'' AS sire_name";
        $select_parts[] = !empty($cfg['course_col']) ? '`' . esc_sql($cfg['course_col']) . '` AS course' : "'' AS course";
        $select_parts[] = !empty($cfg['race_type_col']) ? '`' . esc_sql($cfg['race_type_col']) . '` AS race_type' : "'' AS race_type";
        $select_parts[] = !empty($cfg['going_col']) ? '`' . esc_sql($cfg['going_col']) . '` AS going' : "'' AS going";
        $select_parts[] = !empty($cfg['distance_col']) ? '`' . esc_sql($cfg['distance_col']) . '` AS distance_band' : "'' AS distance_band";
        $select_parts[] = !empty($cfg['prb_col']) ? '`' . esc_sql($cfg['prb_col']) . '` AS mean_prb' : "NULL AS mean_prb";

        $where_sql = !empty($where_parts) ? ('WHERE ' . implode(' AND ', $where_parts)) : '';
        $sql = "SELECT " . implode(', ', $select_parts) . "
                FROM `daily_sires_insights`
                $where_sql
                ORDER BY `" . esc_sql($selected_sort_col) . "` $sort_direction
                LIMIT 300";

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        echo bricks_sire_insights_render_table_html($rows);
        wp_die();
    }
}
add_action('wp_ajax_load_sire_insights_table', 'bricks_ajax_load_sire_insights_table');
add_action('wp_ajax_nopriv_load_sire_insights_table', 'bricks_ajax_load_sire_insights_table');

if (!function_exists('bricks_daily_sires_insights_shortcode')) {
    function bricks_daily_sires_insights_shortcode($atts = []) {
        if (!bricks_sire_insights_table_exists()) {
            return '<div style="padding:16px;color:#991b1b;background:#fee2e2;border-radius:8px;">Sire insights data is not available yet.</div>';
        }

        $default_date = date('Y-m-d');
        ob_start();
        ?>
        <div class="daily-sires-insights-wrapper">
            <div class="sire-header-row">
                <h3>Daily Sire Insights</h3>
                <div class="sire-date-label">Date: <?php echo esc_html($default_date); ?></div>
            </div>

            <div class="sire-insights-filters">
                <div class="filter-group">
                    <label for="sire-date-filter">Date</label>
                    <input type="date" id="sire-date-filter" value="<?php echo esc_attr($default_date); ?>" />
                </div>
                <div class="filter-group">
                    <label for="sire-course-filter">Course</label>
                    <select id="sire-course-filter"><option value="">All Courses</option></select>
                </div>
                <div class="filter-group">
                    <label for="sire-type-filter">Type</label>
                    <select id="sire-type-filter"><option value="">All Types</option></select>
                </div>
                <div class="filter-group">
                    <label for="sire-going-filter">Going</label>
                    <select id="sire-going-filter"><option value="">All Going</option></select>
                </div>
                <div class="filter-group">
                    <label for="sire-distance-filter">Distance</label>
                    <select id="sire-distance-filter"><option value="">All Distances</option></select>
                </div>
                <div class="filter-group">
                    <label for="sire-name-filter">Sire</label>
                    <select id="sire-name-filter"><option value="">All Sires</option></select>
                </div>
            </div>

            <div class="sire-actions-row">
                <button type="button" id="sire-apply-btn">Apply Filters</button>
                <button type="button" id="sire-reset-btn">Reset</button>
            </div>

            <div id="sire-insights-table-container">
                <div style="text-align:center;padding:28px;color:#6b7280;">Loading sire insights...</div>
            </div>
        </div>
        <style>
        .daily-sires-insights-wrapper { background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%); border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06); }
        .sire-header-row { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
        .sire-header-row h3 { margin:0; color:#111827; font-size:24px; font-weight:800; }
        .sire-date-label { font-size:12px; color:#6b7280; font-weight:600; }
        .sire-insights-filters { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; margin-bottom:12px; }
        .filter-group label { display:block; margin-bottom:6px; color:#374151; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
        .sire-insights-filters select, .sire-insights-filters input[type="date"] { width:100%; border:2px solid #e5e7eb; border-radius:8px; background:#fff; color:#111827; padding:9px 10px; font-size:13px; }
        .sire-insights-filters select:focus, .sire-insights-filters input[type="date"]:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,0.12); }
        .sire-actions-row { display:flex; gap:8px; margin-bottom:12px; }
        #sire-apply-btn, #sire-reset-btn { border:none; border-radius:8px; padding:10px 14px; font-size:13px; font-weight:700; cursor:pointer; }
        #sire-apply-btn { background:#2563eb; color:#fff; }
        #sire-reset-btn { background:#f3f4f6; color:#111827; border:1px solid #d1d5db; }
        .sire-insights-table-wrap { overflow-x:auto; border:1px solid #e5e7eb; border-radius:10px; background:#fff; }
        .sire-insights-table { width:100%; border-collapse:collapse; min-width:960px; }
        .sire-insights-table th, .sire-insights-table td { padding:11px 12px; border-bottom:1px solid #f3f4f6; text-align:left; vertical-align:middle; }
        .sire-insights-table thead th { background:#111827; color:#fff; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.45px; cursor:pointer; user-select:none; white-space:nowrap; }
        .sire-insights-table tbody tr:hover { background:#f8fafc; }
        .sire-insights-table .horse-name { font-weight:700; color:#111827; }
        .sire-insights-table .sire-name { color:#1f2937; font-weight:600; }
        .ta-right { text-align:right !important; }
        .sort-indicator { margin-left:6px; opacity:0.7; font-size:10px; }
        .sire-empty-cell { padding:16px; text-align:center; color:#6b7280; }
        .sire-pill { display:inline-flex; align-items:center; border-radius:999px; padding:3px 8px; font-size:11px; font-weight:700; border:1px solid transparent; }
        .sire-pill-blue { background:#eff6ff; border-color:#bfdbfe; color:#1e40af; }
        .sire-pill-violet { background:#f5f3ff; border-color:#ddd6fe; color:#5b21b6; }
        .sire-pill-emerald { background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
        .sire-pill-amber { background:#fffbeb; border-color:#fde68a; color:#92400e; }
        .sire-pill-slate { background:#f8fafc; border-color:#e2e8f0; color:#334155; }
        .sire-pill-empty { color:#9ca3af; }
        .prb-score { display:inline-flex; min-width:50px; justify-content:center; border-radius:8px; padding:4px 8px; font-size:12px; font-weight:800; }
        .prb-strong { background:#dcfce7; color:#166534; }
        .prb-good { background:#ecfeff; color:#155e75; }
        .prb-neutral { background:#f3f4f6; color:#374151; }
        .prb-weak { background:#fee2e2; color:#991b1b; }
        @media (max-width: 768px) {
            .daily-sires-insights-wrapper { padding:14px; }
            .sire-header-row h3 { font-size:20px; }
        }
        </style>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('daily_sires_insights', 'bricks_daily_sires_insights_shortcode');

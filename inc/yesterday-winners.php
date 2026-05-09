<?php
/**
 * Yesterday winners shortcode.
 */

if (!function_exists('bricks_yw_table_exists')) {
    function bricks_yw_table_exists($table_name) {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        return $exists === $table_name;
    }
}

if (!function_exists('bricks_yw_table_columns')) {
    function bricks_yw_table_columns($table_name) {
        global $wpdb;
        static $cache = [];
        if (isset($cache[$table_name])) {
            return $cache[$table_name];
        }

        if (!bricks_yw_table_exists($table_name)) {
            $cache[$table_name] = [];
            return $cache[$table_name];
        }

        $rows = $wpdb->get_results('SHOW COLUMNS FROM `' . esc_sql($table_name) . '`');
        $cols = [];
        foreach ((array) $rows as $row) {
            if (!empty($row->Field)) {
                $cols[] = $row->Field;
            }
        }
        $cache[$table_name] = $cols;
        return $cols;
    }
}

if (!function_exists('bricks_yw_pick_col')) {
    function bricks_yw_pick_col($columns, $candidates) {
        foreach ((array) $candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }
        return '';
    }
}

if (!function_exists('bricks_yw_fetch_rows')) {
    function bricks_yw_fetch_rows($limit = 12) {
        global $wpdb;

        $historic_runners = 'historic_runners_beta';
        $historic_races = 'historic_races_beta';
        if (!bricks_yw_table_exists($historic_runners) || !bricks_yw_table_exists($historic_races)) {
            return ['rows' => [], 'error' => 'Historic results tables not found'];
        }

        $site_today = wp_date('Y-m-d', current_time('timestamp'));
        $yesterday_ymd = wp_date('Y-m-d', strtotime('-1 day', strtotime($site_today)));
        $cache_key = bricks_cache_key('yesterday_winners', [$historic_runners, $historic_races, $yesterday_ymd, intval($limit)]);
        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            return ['rows' => $cached, 'error' => '', 'effective_date' => $yesterday_ymd, 'is_fallback' => false];
        }

        $sql = "SELECT
                    hrunb.name AS horse_name,
                    hracb.course AS course_name,
                    hrunb.starting_price AS starting_price,
                    hracb.meeting_date AS meeting_date,
                    '' AS race_time
                FROM `" . esc_sql($historic_runners) . "` hrunb
                INNER JOIN `" . esc_sql($historic_races) . "` hracb ON hracb.race_id = hrunb.race_id
                WHERE hracb.meeting_date = %s
                  AND (
                    hrunb.finish_position = 1
                    OR hrunb.finish_position = '1'
                    OR hrunb.finish_position = '1.0'
                    OR hrunb.finish_position = '01'
                    OR CAST(hrunb.finish_position AS UNSIGNED) = 1
                    OR LOWER(TRIM(hrunb.finish_position)) IN ('1st', 'first')
                  )
                ORDER BY hracb.course ASC, hrunb.name ASC
                LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $yesterday_ymd, intval($limit)));
        if (!empty($rows)) {
            set_transient($cache_key, $rows, 10 * MINUTE_IN_SECONDS);
            return ['rows' => $rows, 'error' => '', 'effective_date' => $yesterday_ymd, 'is_fallback' => false];
        }

        // Fallback: use most recent date in source table with winner rows.
        $latest_date_sql = "SELECT hracb.meeting_date
            FROM `" . esc_sql($historic_runners) . "` hrunb
            INNER JOIN `" . esc_sql($historic_races) . "` hracb ON hracb.race_id = hrunb.race_id
            WHERE (
                hrunb.finish_position = 1
                OR hrunb.finish_position = '1'
                OR hrunb.finish_position = '1.0'
                OR hrunb.finish_position = '01'
                OR CAST(hrunb.finish_position AS UNSIGNED) = 1
                OR LOWER(TRIM(hrunb.finish_position)) IN ('1st', 'first')
            )
            ORDER BY hracb.meeting_date DESC
            LIMIT 1";
        $latest_date = $wpdb->get_var($latest_date_sql);
        if (empty($latest_date)) {
            return ['rows' => [], 'error' => '', 'effective_date' => $yesterday_ymd, 'is_fallback' => false];
        }

        $fallback_cache_key = bricks_cache_key('yesterday_winners', [$historic_runners, $historic_races, 'latest', $latest_date, intval($limit)]);
        $fallback_cached = get_transient($fallback_cache_key);
        if ($fallback_cached !== false && is_array($fallback_cached)) {
            return ['rows' => $fallback_cached, 'error' => '', 'effective_date' => $latest_date, 'is_fallback' => true];
        }

        $fallback_rows = $wpdb->get_results($wpdb->prepare($sql, $latest_date, intval($limit)));
        set_transient($fallback_cache_key, $fallback_rows, 10 * MINUTE_IN_SECONDS);

        return ['rows' => $fallback_rows, 'error' => '', 'effective_date' => $latest_date, 'is_fallback' => true];
    }
}

if (!function_exists('bricks_yesterday_winners_shortcode')) {
    function bricks_yesterday_winners_shortcode($atts = []) {
        $atts = shortcode_atts([
            'limit' => 12,
            'title' => "Yesterday's Winners",
            'layout' => 'cards',
        ], $atts, 'yesterday_winners');

        $limit = max(1, min(50, intval($atts['limit'])));
        $title = sanitize_text_field($atts['title']);
        $layout = strtolower(sanitize_text_field($atts['layout'])) === 'table' ? 'table' : 'cards';

        $result = bricks_yw_fetch_rows($limit);
        $rows = $result['rows'];
        $effective_date = !empty($result['effective_date']) ? $result['effective_date'] : wp_date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
        $is_fallback = !empty($result['is_fallback']);

        ob_start();
        ?>
        <div class="yw-wrap">
            <div class="yw-head">
                <h3><?php echo esc_html($title); ?></h3>
                <span><?php echo esc_html(wp_date('d M Y', strtotime($effective_date))); ?></span>
            </div>
            <?php if ($is_fallback): ?>
                <div style="margin:0 0 10px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;padding:8px 10px;border-radius:8px;font-size:12px;">
                    No settled winners found for yesterday. Showing latest available winner date instead.
                </div>
            <?php endif; ?>

            <?php if (!empty($result['error'])): ?>
                <div class="yw-empty"><?php echo esc_html($result['error']); ?></div>
            <?php elseif (empty($rows)): ?>
                <div class="yw-empty">No winners found for yesterday.</div>
            <?php elseif ($layout === 'table'): ?>
                <div class="yw-table-wrap">
                    <table class="yw-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Course</th>
                                <th>Winner</th>
                                <th>SP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?php echo esc_html($row->race_time ?: '-'); ?></td>
                                    <td><?php echo esc_html($row->course_name ?: '-'); ?></td>
                                    <td class="yw-winner"><?php echo esc_html($row->horse_name ?: '-'); ?></td>
                                    <td><?php echo esc_html($row->starting_price ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="yw-grid">
                    <?php foreach ($rows as $row): ?>
                        <div class="yw-card">
                            <div class="yw-top">
                                <span class="yw-time"><?php echo esc_html($row->race_time ?: '-'); ?></span>
                                <span class="yw-course"><?php echo esc_html($row->course_name ?: '-'); ?></span>
                            </div>
                            <div class="yw-name"><?php echo esc_html($row->horse_name ?: '-'); ?></div>
                            <div class="yw-sp">SP: <?php echo esc_html($row->starting_price ?: '-'); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <style>
        .yw-wrap { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:14px; box-shadow:0 1px 6px rgba(0,0,0,0.04); }
        .yw-head { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:12px; }
        .yw-head h3 { margin:0; color:#111827; font-size:20px; font-weight:800; }
        .yw-head span { color:#6b7280; font-size:12px; font-weight:700; }
        .yw-empty { padding:12px; background:#f9fafb; border:1px dashed #d1d5db; border-radius:8px; color:#6b7280; }
        .yw-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; }
        .yw-card { border:1px solid #e5e7eb; border-radius:10px; padding:10px; background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%); }
        .yw-top { display:flex; justify-content:space-between; gap:8px; margin-bottom:6px; }
        .yw-time { font-size:11px; font-weight:800; color:#1e40af; background:#dbeafe; border-radius:999px; padding:2px 8px; }
        .yw-course { font-size:11px; font-weight:700; color:#374151; }
        .yw-name { font-size:14px; font-weight:800; color:#111827; margin-bottom:4px; }
        .yw-sp { font-size:12px; color:#475569; }
        .yw-table-wrap { overflow-x:auto; border:1px solid #e5e7eb; border-radius:10px; }
        .yw-table { width:100%; border-collapse:collapse; min-width:540px; background:#fff; }
        .yw-table th, .yw-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; text-align:left; }
        .yw-table th { background:#111827; color:#fff; font-size:12px; text-transform:uppercase; letter-spacing:0.4px; }
        .yw-table .yw-winner { font-weight:800; color:#111827; }
        </style>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('yesterday_winners', 'bricks_yesterday_winners_shortcode');

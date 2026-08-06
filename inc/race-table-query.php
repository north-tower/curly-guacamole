<?php
/**
 * Shared race-table query, render, pagination, and fragment cache.
 * Used by AJAX (load_race_table) and SSR for /daily/ + archive pages.
 */

if (!function_exists('bricks_race_table_render_pagination')) {
    function bricks_race_table_render_pagination($paged, $total_pages) {
        $paged = max(1, intval($paged));
        $total_pages = max(1, intval($total_pages));
        if ($total_pages <= 1) {
            return '';
        }

        $html = '<div class="race-pagination-wrapper" style="margin-top:15px;text-align:center">';
        if ($paged > 1) {
            $html .= '<a class="race-pagination-btn" href="#" data-page="' . ($paged - 1) . '">&laquo; Prev</a>';
        }

        $window = 2;
        $start = max(1, $paged - $window);
        $end = min($total_pages, $paged + $window);
        if ($start > 1) {
            $html .= '<a class="race-pagination-btn" href="#" data-page="1">1</a>';
            if ($start > 2) {
                $html .= '<span class="race-pagination-ellipsis" style="padding:0 6px;color:#94a3b8;">…</span>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $paged ? ' race-pagination-btn-active' : '';
            $html .= '<a class="race-pagination-btn' . $active . '" href="#" data-page="' . $i . '">' . $i . '</a>';
        }
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) {
                $html .= '<span class="race-pagination-ellipsis" style="padding:0 6px;color:#94a3b8;">…</span>';
            }
            $html .= '<a class="race-pagination-btn" href="#" data-page="' . $total_pages . '">' . $total_pages . '</a>';
        }
        if ($paged < $total_pages) {
            $html .= '<a class="race-pagination-btn" href="#" data-page="' . ($paged + 1) . '">Next &raquo;</a>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('bricks_race_table_build_html')) {
    /**
     * @param array<int, object> $results
     * @param array<int, array<string, string>> $race_tracker_alerts
     */
    function bricks_race_table_build_html($results, $total_races, $paged, $per_page, $race_tracker_alerts = []) {
        $total_pages = max(1, (int) ceil(intval($total_races) / max(1, intval($per_page))));
        $paged = max(1, intval($paged));

        if (empty($results)) {
            return '<p>No results found.</p>';
        }

        ob_start();
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

        echo $tracker_summary_html;
        echo '<div class="race-table-scroll"><table class="race-table sticky-header"><thead><tr>
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
            </tr></thead><tbody>';

        foreach ($results as $row) {
            $course_name = (string) ($row->course ?? '');
            if (isset($row->track_type) && strtolower((string) $row->track_type) === 'allweather') {
                $course_name .= ' AW';
            }
            $course_label = str_replace('_', ' ', $course_name);

            if ($course_name !== $current_course) {
                $current_course = $course_name;
                echo '<tr class="race-course-header" data-course-header="true"><td class="race-cell race-cell--course" colspan="11">' . esc_html($course_label) . '</td></tr>';
            }

            $handicap_display = 'N/A';
            if ($row->handicap !== null) {
                $handicap_display = ($row->handicap == 1) ? 'Handicap' : 'Non-Handicap';
            }

            $formatted_race_type = (string) ($row->race_type ?? '');
            if (str_contains(strtolower($formatted_race_type), 'flat')) {
                $surface = '';
                $track_type = strtolower((string) ($row->track_type ?? ''));
                if ($track_type === 'allweather') {
                    $surface = 'AW';
                } elseif ($track_type === 'turf') {
                    $surface = 'Turf';
                }
                if ($surface) {
                    $formatted_race_type = trim($formatted_race_type . ' ' . $surface);
                }
            }

            $currency_symbol = in_array(strtolower((string) ($row->country ?? '')), ['ireland', 'eire'], true) ? '€' : '£';
            $formatted_prize = $currency_symbol . number_format(floatval($row->prize_pos_1 ?? 0));
            $furlongs = round(floatval($row->distance_yards ?? 0) / 220, 1);

            if ($row->handicap !== null) {
                $badge_color = ($row->handicap == 1) ? '#10b981' : '#6b7280';
                $handicap_badge = '<span class="race-handicap-badge" style="display:inline-block;padding:4px 8px;border-radius:6px;background:' . $badge_color . ';color:white;font-size:10px;font-weight:700;text-transform:uppercase;">' . $handicap_display . '</span>';
            } else {
                $handicap_badge = '<span style="color:#9ca3af;">N/A</span>';
            }

            $race_alert = isset($race_tracker_alerts[intval($row->race_id)]) ? array_values($race_tracker_alerts[intval($row->race_id)]) : [];
            $tracker_alert_html = '';
            if (!empty($race_alert)) {
                $alert_count = count($race_alert);
                $alert_title = 'Tracked horse running: ' . implode(', ', $race_alert);
                $tracker_alert_html = '<div style="margin-top:4px;"><span title="' . esc_attr($alert_title) . '" style="display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.3px;">📝 Tracker Alert' . ($alert_count > 1 ? ' (' . $alert_count . ')' : '') . '</span></div>';
            }

            $time_str = !empty($row->scheduled_time) ? date('H:i', strtotime($row->scheduled_time)) : '--:--';

            echo '<tr class="race-row">
                <td class="race-cell race-cell--time" data-label="Time">' . esc_html($time_str) . '</td>
                <td class="race-cell" data-label="Country">' . esc_html((string) ($row->country ?? '')) . '</td>
                <td class="race-cell race-cell--title" data-label="Title"><a href="' . esc_url(bricks_race_url($row->race_id)) . '" class="race-link">🏁 ' . esc_html((string) ($row->race_title ?? '')) . '</a>' . $tracker_alert_html . '</td>
                <td class="race-cell" data-label="Type">' . esc_html($formatted_race_type) . '</td>
                <td class="race-cell" data-label="Class"><span class="race-class-badge">' . esc_html((string) ($row->class ?? '')) . '</span></td>
                <td class="race-cell" data-label="Handicap">' . $handicap_badge . '</td>
                <td class="race-cell" data-label="Age">' . esc_html((string) ($row->age_range ?? '')) . '</td>
                <td class="race-cell race-cell--dist" data-label="Dist">' . esc_html((string) ($row->distance_yards ?? '')) . 'y</td>
                <td class="race-cell race-cell--furlongs" data-label="Furlongs">' . esc_html((string) $furlongs) . 'f</td>
                <td class="race-cell race-cell--prize" data-label="Prize">' . esc_html($formatted_prize) . '</td>
                <td class="race-cell race-cell--runners" data-label="Runners"><span class="race-runners-badge">' . esc_html((string) ($row->runner_count ?? 0)) . '</span></td>
            </tr>';
        }

        echo '</tbody></table></div>';
        echo bricks_race_table_render_pagination($paged, $total_pages);
        return ob_get_clean();
    }
}

if (!function_exists('bricks_race_table_query_html')) {
    /**
     * @param array<string, mixed> $args
     */
    function bricks_race_table_query_html(array $args = []) {
        global $wpdb;

        $defaults = [
            'date' => function_exists('bricks_daily_archive_today') ? bricks_daily_archive_today() : wp_date('Y-m-d'),
            'page' => 1,
            'per_page' => 50,
            'country' => '',
            'course' => '',
            'race_type' => '',
            'class' => '',
            'handicap' => '',
            'age_range' => '',
            'runners_from' => '',
            'runners_to' => '',
            'sort_column' => '',
            'sort_direction' => 'asc',
            'include_tracker' => is_user_logged_in(),
            'use_cache' => !is_user_logged_in(),
        ];
        $args = array_merge($defaults, $args);

        $date = function_exists('bricks_daily_archive_normalize_date')
            ? (bricks_daily_archive_normalize_date($args['date']) ?: $defaults['date'])
            : sanitize_text_field((string) $args['date']);
        $per_page = max(10, min(100, intval($args['per_page'])));
        $paged = max(1, intval($args['page']));
        $offset = ($paged - 1) * $per_page;

        $filter_signature = [
            $date, $paged, $per_page,
            (string) $args['country'], (string) $args['course'], (string) $args['race_type'],
            (string) $args['class'], (string) $args['handicap'], (string) $args['age_range'],
            (string) $args['runners_from'], (string) $args['runners_to'],
            (string) $args['sort_column'], (string) $args['sort_direction'],
        ];

        if (!empty($args['use_cache']) && empty($args['include_tracker']) && function_exists('bricks_cache_key')) {
            $cache_key = bricks_cache_key('race_table', $filter_signature);
            $cached = get_transient($cache_key);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $where = '1=1';
        $having_conditions = [];
        if ($args['country'] !== '') {
            $where .= $wpdb->prepare(' AND r.country = %s', $args['country']);
        }
        if ($args['course'] !== '') {
            $where .= $wpdb->prepare(' AND r.course = %s', $args['course']);
        }
        if ($args['race_type'] !== '') {
            $where .= $wpdb->prepare(' AND r.race_type = %s', $args['race_type']);
        }
        if ($args['class'] !== '') {
            $where .= $wpdb->prepare(' AND r.class = %s', $args['class']);
        }
        if ($args['handicap'] !== '' && $args['handicap'] !== null) {
            $where .= $wpdb->prepare(' AND r.handicap = %d', intval($args['handicap']));
        }
        if ($args['age_range'] !== '') {
            $where .= $wpdb->prepare(' AND r.age_range = %s', $args['age_range']);
        }
        $where .= $wpdb->prepare(' AND r.meeting_date = %s', $date);

        if ($args['runners_from'] !== '' && is_numeric($args['runners_from']) && intval($args['runners_from']) >= 0) {
            $having_conditions[] = 'runner_count >= ' . intval($args['runners_from']);
        }
        if ($args['runners_to'] !== '' && is_numeric($args['runners_to']) && intval($args['runners_to']) > 0) {
            $having_conditions[] = 'runner_count <= ' . intval($args['runners_to']);
        }
        $having_clause = !empty($having_conditions) ? 'HAVING ' . implode(' AND ', $having_conditions) : '';

        if (function_exists('bricks_race_tables_for_date')) {
            $tables = bricks_race_tables_for_date($date);
            $table = $tables['races'];
            $runners_table = $tables['runners'];
        } else {
            $tomorrow = function_exists('bricks_daily_archive_tomorrow') ? bricks_daily_archive_tomorrow() : date('Y-m-d', strtotime('+1 day'));
            $table = ($date === $tomorrow) ? 'advance_daily_races' : 'advance_daily_races_beta';
            $runners_table = ($date === $tomorrow) ? 'advance_daily_runners' : 'advance_daily_runners_beta';
        }

        $total_races = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT r.race_id, COUNT(ru.runner_id) AS runner_count
                FROM `$table` r
                LEFT JOIN `$runners_table` ru ON r.race_id = ru.race_id
                WHERE $where
                GROUP BY r.race_id
                $having_clause
            ) AS filtered_races"
        ));

        $order_by = 'r.course, r.scheduled_time';
        $allowed_sorts = ['scheduled_time', 'country', 'race_type', 'class', 'handicap', 'age_range', 'distance_yards', 'prize_pos_1', 'runner_count'];
        if (!empty($args['sort_column']) && in_array($args['sort_column'], $allowed_sorts, true)) {
            $direction = (!empty($args['sort_direction']) && strtolower((string) $args['sort_direction']) === 'desc') ? 'DESC' : 'ASC';
            $sanitized = sanitize_sql_orderby($args['sort_column'] . ' ' . $direction);
            if ($sanitized) {
                $order_by = $sanitized;
            }
        }

        $results = $wpdb->get_results(
            "SELECT r.race_id, r.course, r.country, r.meeting_date, r.scheduled_time,
                r.race_title, r.race_type, r.class, r.handicap, r.age_range,
                r.distance_yards, r.prize_pos_1, r.track_type,
                COUNT(ru.runner_id) AS runner_count
            FROM `$table` r
            LEFT JOIN `$runners_table` ru ON r.race_id = ru.race_id
            WHERE $where
            GROUP BY r.race_id
            $having_clause
            ORDER BY $order_by
            LIMIT $per_page OFFSET $offset"
        );

        $race_tracker_alerts = [];
        if (!empty($args['include_tracker']) && is_user_logged_in() && !empty($results)
            && function_exists('bricks_tracker_get_user_data') && function_exists('bricks_tracker_normalize_horse_key')) {
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
                $race_ids = array_values(array_filter(array_map(static function ($r) {
                    return isset($r->race_id) ? intval($r->race_id) : 0;
                }, $results)));
                if (!empty($race_ids)) {
                    $placeholders = implode(',', array_fill(0, count($race_ids), '%d'));
                    $runner_rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT race_id, name FROM `$runners_table` WHERE race_id IN ($placeholders) AND name IS NOT NULL AND name != ''",
                        ...$race_ids
                    ));
                    foreach ((array) $runner_rows as $runner_row) {
                        $runner_key = bricks_tracker_normalize_horse_key((string) ($runner_row->name ?? ''));
                        $runner_race_id = intval($runner_row->race_id ?? 0);
                        if ($runner_key === '' || $runner_race_id <= 0 || !isset($tracked_keys[$runner_key])) {
                            continue;
                        }
                        if (!isset($race_tracker_alerts[$runner_race_id])) {
                            $race_tracker_alerts[$runner_race_id] = [];
                        }
                        $race_tracker_alerts[$runner_race_id][$runner_key] = $tracked_keys[$runner_key];
                    }
                }
            }
        }

        $html = bricks_race_table_build_html($results ?: [], $total_races, $paged, $per_page, $race_tracker_alerts);

        if (!empty($args['use_cache']) && empty($race_tracker_alerts) && function_exists('bricks_cache_key')) {
            $today = function_exists('bricks_daily_archive_today') ? bricks_daily_archive_today() : wp_date('Y-m-d');
            $ttl = ($date < $today) ? 6 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
            set_transient(bricks_cache_key('race_table', $filter_signature), $html, $ttl);
        }

        return $html;
    }
}

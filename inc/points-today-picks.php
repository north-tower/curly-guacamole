<?php
/**
 * Admin view of all published Points Engine picks for one day (audit / screenshot vs backtest).
 */

if (!function_exists('bricks_points_today_picks_races_table')) {
    function bricks_points_today_picks_races_table() {
        global $wpdb;
        if ($wpdb->get_var("SHOW TABLES LIKE 'daily_races_beta'") === 'daily_races_beta') {
            return 'daily_races_beta';
        }
        if ($wpdb->get_var("SHOW TABLES LIKE 'advance_daily_races_beta'") === 'advance_daily_races_beta') {
            return 'advance_daily_races_beta';
        }
        return '';
    }
}

if (!function_exists('bricks_points_today_picks_format_course')) {
    function bricks_points_today_picks_format_course($course) {
        $course = str_replace('_', ' ', (string) $course);
        return ucwords(strtolower($course));
    }
}

if (!function_exists('bricks_points_today_picks_format_time')) {
    function bricks_points_today_picks_format_time($time) {
        $time = trim((string) $time);
        if ($time === '') {
            return '—';
        }
        if (preg_match('/^(\d{1,2}:\d{2})/', $time, $m)) {
            return $m[1];
        }
        return $time;
    }
}

if (!function_exists('bricks_points_today_picks_decode_places')) {
    function bricks_points_today_picks_decode_places($json) {
        if ($json === '' || $json === null) {
            return [];
        }
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('bricks_points_today_picks_fetch')) {
    /**
     * @return array{picks: array, missing: array, expected_count: int, saved_count: int, meeting_date: string}
     */
    function bricks_points_today_picks_fetch($meeting_date) {
        global $wpdb;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $meeting_date)) {
            return [
                'picks' => [],
                'missing' => [],
                'expected_count' => 0,
                'saved_count' => 0,
                'meeting_date' => $meeting_date,
            ];
        }

        $picks_table = function_exists('bricks_points_published_picks_table_name')
            ? bricks_points_published_picks_table_name()
            : 'points_engine_published_picks';
        $races_table = bricks_points_today_picks_races_table();

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $picks_table)) !== $picks_table) {
            return [
                'picks' => [],
                'missing' => [],
                'expected_count' => 0,
                'saved_count' => 0,
                'meeting_date' => $meeting_date,
            ];
        }

        $picks = $wpdb->get_results($wpdb->prepare(
            "SELECT pp.race_id, pp.meeting_date, pp.win_horse, pp.place_horses,
                    pp.ew_simple_horse, pp.ew_edge_horse, pp.saved_at, pp.source
             FROM `$picks_table` pp
             WHERE pp.meeting_date = %s AND pp.win_horse != ''
             ORDER BY pp.race_id ASC",
            $meeting_date
        ));

        $race_meta = [];
        if ($races_table !== '') {
            $meta_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT race_id, meeting_date, scheduled_time, course, race_title, race_type
                 FROM `$races_table`
                 WHERE meeting_date = %s
                 ORDER BY course ASC, scheduled_time ASC, race_id ASC",
                $meeting_date
            ));
            foreach ($meta_rows as $row) {
                $race_meta[intval($row->race_id)] = $row;
            }
        }

        $saved_by_race = [];
        foreach ($picks as $pick) {
            $rid = intval($pick->race_id);
            $meta = $race_meta[$rid] ?? null;
            $pick->scheduled_time = $meta->scheduled_time ?? '';
            $pick->course = $meta->course ?? '';
            $pick->race_title = $meta->race_title ?? '';
            $pick->race_type = $meta->race_type ?? '';
            $pick->place_list = bricks_points_today_picks_decode_places($pick->place_horses ?? '');
            $saved_by_race[$rid] = $pick;
        }

        $picks_sorted = array_values($saved_by_race);
        usort($picks_sorted, function ($a, $b) {
            $course_cmp = strcasecmp((string) ($a->course ?? ''), (string) ($b->course ?? ''));
            if ($course_cmp !== 0) {
                return $course_cmp;
            }
            return strcmp((string) ($a->scheduled_time ?? ''), (string) ($b->scheduled_time ?? ''));
        });

        $missing = [];
        foreach ($race_meta as $rid => $meta) {
            if (!isset($saved_by_race[$rid])) {
                $missing[] = $meta;
            }
        }

        return [
            'picks' => $picks_sorted,
            'missing' => $missing,
            'expected_count' => count($race_meta),
            'saved_count' => count($picks_sorted),
            'meeting_date' => $meeting_date,
        ];
    }
}

if (!function_exists('bricks_points_today_picks_shortcode')) {
    function bricks_points_today_picks_shortcode($atts = []) {
        if (!function_exists('bricks_user_can_access_points_backtest') || !bricks_user_can_access_points_backtest()) {
            return '<div style="max-width:760px;margin:40px auto;padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                <h2 style="margin:0 0 10px 0;color:#111827;">Today\'s Picks</h2>
                <p style="margin:0;color:#6b7280;">This page is available to site administrators only.</p>
            </div>';
        }

        $today = wp_date('Y-m-d', current_time('timestamp'));
        $yesterday = wp_date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
        $meeting_date = isset($_GET['tp_date']) ? sanitize_text_field(wp_unslash($_GET['tp_date'])) : $today;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $meeting_date)) {
            $meeting_date = $today;
        }

        $data = bricks_points_today_picks_fetch($meeting_date);
        $picks = $data['picks'];
        $missing = $data['missing'];
        $expected = intval($data['expected_count']);
        $saved = intval($data['saved_count']);
        $generated_at = wp_date('Y-m-d H:i:s', current_time('timestamp'));

        $by_course = [];
        foreach ($picks as $pick) {
            $course_key = (string) ($pick->course ?: 'Unknown course');
            if (!isset($by_course[$course_key])) {
                $by_course[$course_key] = [];
            }
            $by_course[$course_key][] = $pick;
        }

        $backtest_url = add_query_arg([
            'my_points_backtest' => '1',
            'pb_from' => $meeting_date,
            'pb_to' => $meeting_date,
            'pb_mode' => 'published',
        ], home_url('/points-backtest/'));

        ob_start();
        ?>
        <style>
            @media print {
                .tp-no-print { display: none !important; }
                .tp-print-root {
                    max-width: none !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }
                .tp-card, .tp-course-block {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }
                body { background: #fff !important; }
            }
        </style>
        <div class="tp-print-root" style="max-width:1100px;margin:24px auto;padding:0 16px 40px;">
            <div class="tp-no-print" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px;">
                <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                    <input type="hidden" name="my_today_picks" value="1" />
                    <label style="font-size:12px;color:#374151;">Meeting date<br>
                        <input type="date" name="tp_date" value="<?php echo esc_attr($meeting_date); ?>" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;">
                    </label>
                    <button type="submit" style="padding:9px 14px;border:none;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;">Show picks</button>
                </form>
                <button type="button" onclick="window.print();" style="padding:9px 14px;border:none;border-radius:8px;background:#0f766e;color:#fff;font-weight:700;cursor:pointer;">Print / screenshot</button>
                <a href="<?php echo esc_url($backtest_url); ?>" style="padding:9px 14px;border-radius:8px;background:#ecfdf5;color:#065f46;font-weight:700;text-decoration:none;border:1px solid #6ee7b7;">Open backtest for this date</a>
            </div>

            <div class="tp-card" style="background:#fff;border:2px solid #111827;border-radius:12px;padding:18px 20px;margin-bottom:16px;">
                <h1 style="margin:0 0 4px;font-size:28px;font-weight:800;color:#111827;">Points Engine — Published Picks</h1>
                <p style="margin:0 0 10px;font-size:16px;color:#374151;">
                    <strong><?php echo esc_html(wp_date('l j F Y', strtotime($meeting_date))); ?></strong>
                    <span style="color:#6b7280;">(<?php echo esc_html($meeting_date); ?>)</span>
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:10px;font-size:13px;color:#374151;">
                    <span style="background:#f3f4f6;border-radius:8px;padding:6px 10px;">Saved picks: <strong><?php echo esc_html($saved); ?></strong><?php echo $expected > 0 ? ' / ' . esc_html($expected) . ' races' : ''; ?></span>
                    <span style="background:#f3f4f6;border-radius:8px;padding:6px 10px;">Generated: <strong><?php echo esc_html($generated_at); ?></strong></span>
                    <?php if ($meeting_date === $today): ?>
                    <span style="background:#eff6ff;border-radius:8px;padding:6px 10px;color:#1e40af;">Tomorrow: backtest this card with <strong>Yesterday = <?php echo esc_html($today); ?></strong> (Published picks)</span>
                    <?php elseif ($meeting_date === $yesterday): ?>
                    <span style="background:#ecfdf5;border-radius:8px;padding:6px 10px;color:#065f46;">Ready to compare with backtest for <?php echo esc_html($meeting_date); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($saved === 0): ?>
            <div class="tp-no-print" style="margin-bottom:14px;padding:12px 14px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;">
                No published picks saved for this date yet. Run <code>CALL points_published_picks_daily_UPDATE();</code> after the speed table rebuild, or open race pages while logged in.
            </div>
            <?php endif; ?>

            <?php if (!empty($missing)): ?>
            <div class="tp-no-print" style="margin-bottom:14px;padding:12px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:13px;">
                <strong><?php echo esc_html(count($missing)); ?></strong> race(s) on the card have no saved pick yet (not included below).
            </div>
            <?php endif; ?>

            <?php foreach ($by_course as $course => $course_picks): ?>
            <div class="tp-course-block" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:14px;overflow:hidden;">
                <div style="background:#111827;color:#fff;padding:10px 14px;font-size:15px;font-weight:800;">
                    <?php echo esc_html(bricks_points_today_picks_format_course($course)); ?>
                    <span style="font-weight:600;opacity:0.85;">(<?php echo esc_html(count($course_picks)); ?> races)</span>
                </div>
                <div style="overflow:auto;">
                    <table style="width:100%;border-collapse:collapse;min-width:760px;font-size:14px;">
                        <thead>
                            <tr>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;width:70px;">Time</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Race</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;width:140px;">Win</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">Place (top 3)</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;width:120px;">EW Simple</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;width:120px;">EW Edge</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f9fafb;width:80px;">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($course_picks as $pick): ?>
                            <tr>
                                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;font-weight:700;white-space:nowrap;">
                                    <?php echo esc_html(bricks_points_today_picks_format_time($pick->scheduled_time ?? '')); ?>
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;color:#475569;font-size:13px;">
                                    <?php echo esc_html(wp_trim_words((string) ($pick->race_title ?? ''), 10, '…')); ?>
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;font-weight:800;color:#111827;">
                                    <?php echo esc_html($pick->win_horse ?: '—'); ?>
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;">
                                    <?php
                                    $places = is_array($pick->place_list ?? null) ? $pick->place_list : [];
                                    echo esc_html(!empty($places) ? implode(' · ', $places) : '—');
                                    ?>
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;">
                                    <?php echo esc_html($pick->ew_simple_horse ?: '—'); ?>
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;">
                                    <?php echo esc_html($pick->ew_edge_horse ?: '—'); ?>
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;font-size:11px;color:#6b7280;">
                                    <?php echo esc_html($pick->source ?? ''); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (!empty($missing)): ?>
            <div class="tp-course-block tp-no-print" style="background:#fff;border:1px solid #fecaca;border-radius:12px;margin-bottom:14px;overflow:hidden;">
                <div style="background:#991b1b;color:#fff;padding:10px 14px;font-size:15px;font-weight:800;">
                    Missing picks (<?php echo esc_html(count($missing)); ?>)
                </div>
                <ul style="margin:0;padding:12px 18px 12px 32px;font-size:13px;color:#7f1d1d;">
                    <?php foreach ($missing as $race): ?>
                    <li>
                        <?php echo esc_html(bricks_points_today_picks_format_time($race->scheduled_time ?? '')); ?>
                        — <?php echo esc_html(bricks_points_today_picks_format_course($race->course ?? '')); ?>
                        — <?php echo esc_html(wp_trim_words((string) ($race->race_title ?? ''), 8, '…')); ?>
                        <?php if (function_exists('bricks_race_url')): ?>
                        (<a href="<?php echo esc_url(bricks_race_url(intval($race->race_id))); ?>">open race</a>)
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('points_today_picks', 'bricks_points_today_picks_shortcode');

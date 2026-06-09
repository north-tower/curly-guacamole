<?php
/**
 * Yesterday winners shortcode.
 *
 * [yesterday_winners] — all winners on the effective meeting date (yesterday, or latest fallback).
 * [yesterday_winners scope="points_engine"] — only races where the Points Engine **Win** pick (same
 *   logic as on race cards / backtest) actually won, on that same calendar day.
 * [yesterday_winners scope="my_tracker"] — only winners for horses in My Tracker (optional).
 * [yesterday_winners date="2026-05-29"] — force a specific meeting date (falls back to daily_comment_history if not in historic yet).
 *
 * pick_match (my_tracker only):
 * - relaxed (default): tracked horse won on that meeting date (any tracker note).
 * - strict: note race_date matches the meeting day, or race_id matches the race.
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
        return $cache[$table_name];
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

if (!function_exists('bricks_yw_winner_position_sql_fragment')) {
    function bricks_yw_winner_position_sql_fragment() {
        return "(
                    hrunb.finish_position = 1
                    OR hrunb.finish_position = '1'
                    OR hrunb.finish_position = '1.0'
                    OR hrunb.finish_position = '01'
                    OR CAST(hrunb.finish_position AS UNSIGNED) = 1
                    OR LOWER(TRIM(hrunb.finish_position)) IN ('1st', 'first')
                  )";
    }
}

if (!function_exists('bricks_yw_build_winner_select_sql')) {
    function bricks_yw_build_winner_select_sql($historic_runners, $historic_races) {
        $hr_cols = bricks_yw_table_columns($historic_runners);
        $ra_cols = bricks_yw_table_columns($historic_races);

        $name_col = bricks_yw_pick_col($hr_cols, ['name', 'horse_name']);
        if ($name_col === '') {
            $name_col = 'name';
        }
        $sp_col = bricks_yw_pick_col($hr_cols, ['starting_price', 'sp', 'starting_price_decimal']);
        $time_col = bricks_yw_pick_col($ra_cols, ['scheduled_time', 'race_time', 'time']);

        $sel = 'hrunb.`' . esc_sql($name_col) . '` AS horse_name,
                    hracb.course AS course_name,';
        if ($sp_col !== '') {
            $sel .= ' hrunb.`' . esc_sql($sp_col) . '` AS starting_price,';
        } else {
            $sel .= " '' AS starting_price,";
        }
        $sel .= ' hracb.meeting_date AS meeting_date,';
        if ($time_col !== '') {
            $sel .= ' hracb.`' . esc_sql($time_col) . '` AS race_time,';
        } else {
            $sel .= " '' AS race_time,";
        }
        if (in_array('race_id', $ra_cols, true)) {
            $sel .= ' hracb.race_id AS race_id,';
        } else {
            $sel .= ' 0 AS race_id,';
        }
        if (in_array('runner_id', $hr_cols, true)) {
            $sel .= ' hrunb.runner_id AS runner_id';
        } else {
            $sel .= ' 0 AS runner_id';
        }

        return $sel;
    }
}

if (!function_exists('bricks_yw_normalize_note_race_date')) {
    function bricks_yw_normalize_note_race_date($race_date_raw) {
        $s = trim((string) $race_date_raw);
        if ($s === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }
        $ts = strtotime($s);
        if ($ts) {
            return wp_date('Y-m-d', $ts);
        }
        return '';
    }
}

if (!function_exists('bricks_yw_tracker_pick_matches_race')) {
    function bricks_yw_tracker_pick_matches_race($note, $meeting_date_ymd, $winner_race_id, $pick_match) {
        if (!is_array($note)) {
            return false;
        }
        $rid_note = isset($note['race_id']) ? trim((string) $note['race_id']) : '';
        $rid_win = (string) intval($winner_race_id);
        if ($pick_match === 'strict') {
            if ($rid_note !== '' && $rid_win !== '0' && intval($rid_note) === intval($rid_win)) {
                return true;
            }
            $nd = bricks_yw_normalize_note_race_date($note['race_date'] ?? '');
            return ($nd !== '' && $nd === $meeting_date_ymd);
        }
        // relaxed: any note counts as “you follow this horse”; meeting day is enforced by the winner query.
        return true;
    }
}

if (!function_exists('bricks_yw_user_tracked_winner_on_date')) {
    function bricks_yw_user_tracked_winner_on_date($user_id, $horse_name, $meeting_date_ymd, $winner_race_id, $pick_match) {
        if (!function_exists('bricks_tracker_get_user_data') || !function_exists('bricks_tracker_normalize_horse_key')) {
            return false;
        }
        $key = bricks_tracker_normalize_horse_key($horse_name);
        if ($key === '') {
            return false;
        }
        $data = bricks_tracker_get_user_data($user_id);
        if (empty($data[$key]['notes']) || !is_array($data[$key]['notes'])) {
            return false;
        }
        foreach ($data[$key]['notes'] as $note) {
            if (bricks_yw_tracker_pick_matches_race($note, $meeting_date_ymd, $winner_race_id, $pick_match)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('bricks_yw_winner_position_sql_fragment_dch')) {
    function bricks_yw_winner_position_sql_fragment_dch() {
        return "(
                    dch.finish_position = 1
                    OR dch.finish_position = '1'
                    OR dch.finish_position = '1.0'
                    OR dch.finish_position = '01'
                    OR CAST(dch.finish_position AS UNSIGNED) = 1
                    OR LOWER(TRIM(dch.finish_position)) IN ('1st', 'first')
                  )";
    }
}

if (!function_exists('bricks_yw_fetch_winners_from_dch')) {
    function bricks_yw_fetch_winners_from_dch($meeting_date_ymd, $limit = 0) {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE 'daily_comment_history'") !== 'daily_comment_history') {
            return [];
        }

        $win_sql = bricks_yw_winner_position_sql_fragment_dch();
        $time_col = bricks_points_table_has_column('daily_comment_history', 'scheduled_time') ? 'dch.scheduled_time' : "''";
        $race_id_col = bricks_points_table_has_column('daily_comment_history', 'race_id') ? 'dch.race_id' : '0';

        $from_order = "FROM daily_comment_history dch
                WHERE dch.meeting_date = %s
                  AND {$win_sql}
                ORDER BY dch.course ASC, dch.name ASC";

        if ($limit > 0) {
            $lim = max(1, min(500, intval($limit)));
            $sql = $wpdb->prepare(
                "SELECT dch.name AS horse_name,
                        dch.course AS course_name,
                        dch.starting_price AS starting_price,
                        dch.meeting_date AS meeting_date,
                        {$time_col} AS race_time,
                        {$race_id_col} AS race_id,
                        dch.runner_id AS runner_id
                 {$from_order} LIMIT %d",
                $meeting_date_ymd,
                $lim
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT dch.name AS horse_name,
                        dch.course AS course_name,
                        dch.starting_price AS starting_price,
                        dch.meeting_date AS meeting_date,
                        {$time_col} AS race_time,
                        {$race_id_col} AS race_id,
                        dch.runner_id AS runner_id
                 {$from_order}",
                $meeting_date_ymd
            );
        }

        $rows = $wpdb->get_results($sql);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('bricks_yw_fetch_winners_for_meeting_date')) {
    /**
     * @param string $meeting_date_ymd Y-m-d
     * @param int    $limit            0 = no LIMIT (all winners that day)
     * @return array{0: array<int, object>, 1: string} rows, error message
     */
    function bricks_yw_fetch_winners_for_meeting_date($meeting_date_ymd, $limit = 0) {
        global $wpdb;

        $historic_runners = 'historic_runners_beta';
        $historic_races = 'historic_races_beta';
        if (!bricks_yw_table_exists($historic_runners) || !bricks_yw_table_exists($historic_races)) {
            return [[], 'Historic results tables not found'];
        }

        $hr_cols = bricks_yw_table_columns($historic_runners);
        $name_col = bricks_yw_pick_col($hr_cols, ['name', 'horse_name']);
        if ($name_col === '') {
            $name_col = 'name';
        }

        $select = bricks_yw_build_winner_select_sql($historic_runners, $historic_races);
        $win_sql = bricks_yw_winner_position_sql_fragment();

        $from_order = "FROM `" . esc_sql($historic_runners) . "` hrunb
                INNER JOIN `" . esc_sql($historic_races) . "` hracb ON hracb.race_id = hrunb.race_id
                WHERE hracb.meeting_date = %s
                  AND {$win_sql}
                ORDER BY hracb.course ASC, hrunb.`" . esc_sql($name_col) . '` ASC';

        if ($limit > 0) {
            $lim = max(1, min(500, intval($limit)));
            $sql = $wpdb->prepare(
                "SELECT {$select} {$from_order} LIMIT %d",
                $meeting_date_ymd,
                $lim
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT {$select} {$from_order}",
                $meeting_date_ymd
            );
        }

        $rows = $wpdb->get_results($sql);
        $rows = is_array($rows) ? $rows : [];
        if (empty($rows)) {
            $rows = bricks_yw_fetch_winners_from_dch($meeting_date_ymd, $limit);
        }
        return [$rows, ''];
    }
}

if (!function_exists('bricks_yw_latest_meeting_date_with_winners')) {
    function bricks_yw_latest_meeting_date_with_winners() {
        global $wpdb;
        $historic_runners = 'historic_runners_beta';
        $historic_races = 'historic_races_beta';
        if (!bricks_yw_table_exists($historic_runners) || !bricks_yw_table_exists($historic_races)) {
            return '';
        }
        $win_sql = bricks_yw_winner_position_sql_fragment();
        $latest_date_sql = "SELECT hracb.meeting_date
            FROM `" . esc_sql($historic_runners) . "` hrunb
            INNER JOIN `" . esc_sql($historic_races) . "` hracb ON hracb.race_id = hrunb.race_id
            WHERE {$win_sql}
            ORDER BY hracb.meeting_date DESC
            LIMIT 1";
        return (string) $wpdb->get_var($latest_date_sql);
    }
}

if (!function_exists('bricks_yw_fetch_rows')) {
    /**
     * @param int   $limit
     * @param array $opts scope: all|my_tracker|points_engine, user_id, pick_match: relaxed|strict (tracker only)
     */
    function bricks_yw_fetch_rows($limit = 12, $opts = []) {
        global $wpdb;

        $historic_runners = 'historic_runners_beta';
        $historic_races = 'historic_races_beta';
        if (!bricks_yw_table_exists($historic_runners) || !bricks_yw_table_exists($historic_races)) {
            return ['rows' => [], 'error' => 'Historic results tables not found', 'effective_date' => '', 'is_fallback' => false, 'hit_count' => 0, 'my_pick_count' => 0];
        }

        $scope_raw = isset($opts['scope']) ? strtolower((string) $opts['scope']) : 'all';
        $scope = ($scope_raw === 'my_tracker' || $scope_raw === 'points_engine') ? $scope_raw : 'all';
        $pick_match = isset($opts['pick_match']) ? strtolower((string) $opts['pick_match']) : 'relaxed';
        if ($pick_match !== 'strict') {
            $pick_match = 'relaxed';
        }
        $user_id = isset($opts['user_id']) ? intval($opts['user_id']) : 0;

        $site_today = wp_date('Y-m-d', current_time('timestamp'));
        $yesterday_ymd = wp_date('Y-m-d', strtotime('-1 day', strtotime($site_today)));

        $requested_date = isset($opts['date']) ? sanitize_text_field((string) $opts['date']) : '';
        if ($requested_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested_date)) {
            $requested_date = '';
        }

        $effective_date = $yesterday_ymd;
        $is_fallback = false;

        if ($requested_date !== '') {
            $effective_date = $requested_date;
        } else {
            // Probe yesterday (no limit for probe)
            list($y_rows) = bricks_yw_fetch_winners_for_meeting_date($yesterday_ymd, 0);
            if (empty($y_rows)) {
                $latest = bricks_yw_latest_meeting_date_with_winners();
                if ($latest === '') {
                    return ['rows' => [], 'error' => '', 'effective_date' => $yesterday_ymd, 'is_fallback' => false, 'hit_count' => 0, 'my_pick_count' => 0];
                }
                $effective_date = $latest;
                $is_fallback = ($latest !== $yesterday_ymd);
            }
        }

        if ($scope === 'my_tracker') {
            if ($user_id <= 0) {
                return ['rows' => [], 'error' => '', 'effective_date' => $effective_date, 'is_fallback' => $is_fallback, 'hit_count' => 0, 'my_pick_count' => 0, 'needs_login' => true];
            }

            $cache_key = bricks_cache_key('yesterday_winners', [
                'my_tracker',
                $historic_runners,
                $historic_races,
                $effective_date,
                $user_id,
                $pick_match,
                intval($limit),
            ]);
            $cached = get_transient($cache_key);
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }

            list($all_day) = bricks_yw_fetch_winners_for_meeting_date($effective_date, 0);
            $picked = [];
            foreach ($all_day as $row) {
                $horse = isset($row->horse_name) ? (string) $row->horse_name : '';
                $race_id = isset($row->race_id) ? intval($row->race_id) : 0;
                if ($horse === '') {
                    continue;
                }
                if (bricks_yw_user_tracked_winner_on_date($user_id, $horse, $effective_date, $race_id, $pick_match)) {
                    $picked[] = $row;
                }
            }
            $my_pick_count = count($picked);
            $lim = max(1, min(50, intval($limit)));
            $out = array_slice($picked, 0, $lim);

            $result = [
                'rows' => $out,
                'error' => '',
                'effective_date' => $effective_date,
                'is_fallback' => $is_fallback,
                'hit_count' => $my_pick_count,
                'my_pick_count' => $my_pick_count,
                'needs_login' => false,
            ];
            set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
            return $result;
        }

        if ($scope === 'points_engine') {
            if (!function_exists('bricks_points_engine_meeting_day_win_pick_hit_race_ids')) {
                return [
                    'rows' => [],
                    'error' => 'Points engine functions not available.',
                    'effective_date' => $effective_date,
                    'is_fallback' => $is_fallback,
                    'hit_count' => 0,
                    'my_pick_count' => 0,
                ];
            }

            $cache_key = bricks_cache_key('yesterday_winners', [
                'points_engine',
                $historic_runners,
                $historic_races,
                $effective_date,
                intval($limit),
            ]);
            $cached = get_transient($cache_key);
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }

            $hit_race_ids = bricks_points_engine_meeting_day_win_pick_hit_race_ids($effective_date);
            $hit_lookup = array_fill_keys(array_map('intval', $hit_race_ids), true);

            list($all_day) = bricks_yw_fetch_winners_for_meeting_date($effective_date, 0);
            $picked = [];
            foreach ($all_day as $row) {
                $rid = isset($row->race_id) ? intval($row->race_id) : 0;
                if ($rid > 0 && !empty($hit_lookup[$rid])) {
                    $picked[] = $row;
                }
            }

            $hit_count = count($picked);
            $lim = max(1, min(50, intval($limit)));
            $out = array_slice($picked, 0, $lim);

            $result = [
                'rows' => $out,
                'error' => '',
                'effective_date' => $effective_date,
                'is_fallback' => $is_fallback,
                'hit_count' => $hit_count,
                'my_pick_count' => $hit_count,
            ];
            set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
            return $result;
        }

        // --- scope: all (original behaviour, SQL LIMIT) ---
        $cache_key = bricks_cache_key('yesterday_winners', [$historic_runners, $historic_races, $effective_date, intval($limit), 'all']);
        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            return ['rows' => $cached, 'error' => '', 'effective_date' => $effective_date, 'is_fallback' => $is_fallback, 'hit_count' => 0, 'my_pick_count' => 0];
        }

        list($rows, $err) = bricks_yw_fetch_winners_for_meeting_date($effective_date, max(1, min(50, intval($limit))));
        if ($err !== '') {
            return ['rows' => [], 'error' => $err, 'effective_date' => $effective_date, 'is_fallback' => $is_fallback, 'hit_count' => 0, 'my_pick_count' => 0];
        }

        if (!empty($rows)) {
            set_transient($cache_key, $rows, 10 * MINUTE_IN_SECONDS);
            return ['rows' => $rows, 'error' => '', 'effective_date' => $effective_date, 'is_fallback' => $is_fallback, 'hit_count' => 0, 'my_pick_count' => 0];
        }

        // Fallback to latest date (legacy)
        $latest_date = bricks_yw_latest_meeting_date_with_winners();
        if (empty($latest_date)) {
            return ['rows' => [], 'error' => '', 'effective_date' => $yesterday_ymd, 'is_fallback' => false, 'hit_count' => 0, 'my_pick_count' => 0];
        }

        $fallback_cache_key = bricks_cache_key('yesterday_winners', [$historic_runners, $historic_races, 'latest', $latest_date, intval($limit), 'all']);
        $fallback_cached = get_transient($fallback_cache_key);
        if ($fallback_cached !== false && is_array($fallback_cached)) {
            return ['rows' => $fallback_cached, 'error' => '', 'effective_date' => $latest_date, 'is_fallback' => true, 'hit_count' => 0, 'my_pick_count' => 0];
        }

        list($fallback_rows) = bricks_yw_fetch_winners_for_meeting_date($latest_date, max(1, min(50, intval($limit))));
        set_transient($fallback_cache_key, $fallback_rows, 10 * MINUTE_IN_SECONDS);

        return ['rows' => $fallback_rows, 'error' => '', 'effective_date' => $latest_date, 'is_fallback' => true, 'hit_count' => 0, 'my_pick_count' => 0];
    }
}

if (!function_exists('bricks_yesterday_winners_shortcode')) {
    function bricks_yesterday_winners_shortcode($atts = []) {
        $atts = shortcode_atts([
            'limit' => 12,
            'title' => "Yesterday's Winners",
            'layout' => 'cards',
            'scope' => 'all',
            'pick_match' => 'relaxed',
            'date' => '',
        ], $atts, 'yesterday_winners');

        $limit = max(1, min(50, intval($atts['limit'])));
        $title = sanitize_text_field($atts['title']);
        $layout = strtolower(sanitize_text_field($atts['layout'])) === 'table' ? 'table' : 'cards';
        $scope_raw = strtolower(sanitize_text_field($atts['scope']));
        $scope = ($scope_raw === 'my_tracker' || $scope_raw === 'points_engine') ? $scope_raw : 'all';
        $pick_match = strtolower(sanitize_text_field($atts['pick_match'])) === 'strict' ? 'strict' : 'relaxed';

        if ($scope === 'my_tracker' && $title === "Yesterday's Winners") {
            $title = 'Your winners';
        }
        if ($scope === 'points_engine' && $title === "Yesterday's Winners") {
            $title = 'Points Engine winners';
        }

        $opts = [
            'scope' => $scope,
            'pick_match' => $pick_match,
            'user_id' => is_user_logged_in() ? get_current_user_id() : 0,
            'date' => sanitize_text_field($atts['date']),
        ];

        $result = bricks_yw_fetch_rows($limit, $opts);
        $rows = $result['rows'];
        $effective_date = !empty($result['effective_date']) ? $result['effective_date'] : wp_date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
        $is_fallback = !empty($result['is_fallback']);
        $hit_count = intval($result['hit_count'] ?? $result['my_pick_count'] ?? 0);
        $needs_login = !empty($result['needs_login']);

        ob_start();
        ?>
        <div class="yw-wrap">
            <div class="yw-head">
                <h3><?php echo esc_html($title); ?></h3>
                <span><?php echo esc_html(wp_date('d M Y', strtotime($effective_date))); ?></span>
            </div>
            <?php if ($scope === 'points_engine'): ?>
                <div style="margin:0 0 12px;padding:10px 12px;border-radius:10px;background:#eff6ff;border:1px solid #93c5fd;font-size:14px;font-weight:700;color:#1e3a8a;">
                    <?php echo esc_html(sprintf(_n('1 Points Engine win pick won on this date.', '%d Points Engine win picks won on this date.', $hit_count, 'bricks-child'), $hit_count)); ?>
                </div>
            <?php elseif ($scope === 'my_tracker' && is_user_logged_in()): ?>
                <div style="margin:0 0 12px;padding:10px 12px;border-radius:10px;background:#ecfdf5;border:1px solid #6ee7b7;font-size:14px;font-weight:700;color:#065f46;">
                    <?php echo esc_html(sprintf(_n('1 of your tracked horses won on this date.', '%d of your tracked horses won on this date.', $hit_count, 'bricks-child'), $hit_count)); ?>
                </div>
            <?php endif; ?>
            <?php if ($needs_login): ?>
                <div class="yw-empty">Log in to see winners among your tracked horses.</div>
            <?php elseif ($is_fallback): ?>
                <div style="margin:0 0 10px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;padding:8px 10px;border-radius:8px;font-size:12px;">
                    No settled winners found for yesterday. Showing latest available winner date instead.
                </div>
            <?php endif; ?>

            <?php if (!empty($result['error'])): ?>
                <div class="yw-empty"><?php echo esc_html($result['error']); ?></div>
            <?php elseif (!$needs_login && empty($rows)): ?>
                <div class="yw-empty"><?php
                    if ($scope === 'my_tracker') {
                        echo esc_html__('None of your tracked horses won on this date.', 'bricks-child');
                    } elseif ($scope === 'points_engine') {
                        echo esc_html__('None of the Points Engine win picks won on this date.', 'bricks-child');
                    } else {
                        echo esc_html__('No winners found for yesterday.', 'bricks-child');
                    }
                ?></div>
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

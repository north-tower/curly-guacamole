<?php
/**
 * Daily sire insights feature: shortcode, AJAX filters, and table render.
 */

if (!function_exists('bricks_sire_insights_table_candidates')) {
    function bricks_sire_insights_table_candidates() {
        return [
            'daily_sires_insights',
            'daily_sire_insights',
            'daily_sires_insights_table',
            'daily_sire_insights_table',
            'sires_insights',
            'sire_insights',
        ];
    }
}

if (!function_exists('bricks_sire_insights_resolve_table')) {
    function bricks_sire_insights_resolve_table() {
        global $wpdb;
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        foreach (bricks_sire_insights_table_candidates() as $candidate) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $candidate));
            if ($exists === $candidate) {
                $resolved = $candidate;
                return $resolved;
            }
        }

        $pattern_matches = $wpdb->get_col("SHOW TABLES LIKE '%sire%insight%'");
        if (!empty($pattern_matches) && is_array($pattern_matches)) {
            $resolved = (string) $pattern_matches[0];
            return $resolved;
        }

        $resolved = '';
        return $resolved;
    }
}

if (!function_exists('bricks_sire_insights_table_exists')) {
    function bricks_sire_insights_table_exists() {
        return bricks_sire_insights_resolve_table() !== '';
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

        $table = bricks_sire_insights_resolve_table();
        if ($table === '') {
            $columns = [];
            return $columns;
        }

        $rows = $wpdb->get_results("SHOW COLUMNS FROM `" . esc_sql($table) . "`");
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
        $race_id_col = bricks_sire_insights_pick_column(['race_id']);
        $runner_id_col = bricks_sire_insights_pick_column(['runner_id']);
        $race_code_col = bricks_sire_insights_pick_column(['race_code']);
        $course_col = bricks_sire_insights_pick_column(['course']);
        $race_type_col = bricks_sire_insights_pick_column(['race_type', 'race_code']);
        $going_col = bricks_sire_insights_pick_column(['going']);
        $distance_col = bricks_sire_insights_pick_column(['distance_furlongs', 'distance_band', 'distance', 'Distance']);
        $age_col = bricks_sire_insights_pick_column(['age']);
        $handicap_col = bricks_sire_insights_pick_column(['handicap']);
        $sire_col = bricks_sire_insights_pick_column(['sire_name', 'sire']);
        $horse_col = bricks_sire_insights_pick_column(['name', 'horse_name', 'runner_name']);
        $prb_col = bricks_sire_insights_pick_column(['rt_5Y_prb', 'rc_5Y_prb', 'mean_prb', 'prb', 'avg_prb']);
        $rc_21d_prb_col = bricks_sire_insights_pick_column(['rc_21D_prb']);
        $course_prb_col = bricks_sire_insights_pick_column(['rt_5Y_course_prb']);
        $distance_prb_col = bricks_sire_insights_pick_column(['rt_5Y_distance_prb']);
        $candd_prb_col = bricks_sire_insights_pick_column(['rt_5Y_candd_prb']);

        return [
            'date_col' => $date_col,
            'race_id_col' => $race_id_col,
            'runner_id_col' => $runner_id_col,
            'race_code_col' => $race_code_col,
            'course_col' => $course_col,
            'race_type_col' => $race_type_col,
            'going_col' => $going_col,
            'distance_col' => $distance_col,
            'age_col' => $age_col,
            'handicap_col' => $handicap_col,
            'sire_col' => $sire_col,
            'horse_col' => $horse_col,
            'prb_col' => $prb_col,
            'rc_21d_prb_col' => $rc_21d_prb_col,
            'course_prb_col' => $course_prb_col,
            'distance_prb_col' => $distance_prb_col,
            'candd_prb_col' => $candd_prb_col,
        ];
    }
}

if (!function_exists('bricks_sire_insights_default_meeting_date')) {
    function bricks_sire_insights_default_meeting_date() {
        if (function_exists('bricks_daily_archive_today')) {
            return bricks_daily_archive_today();
        }
        return wp_date('Y-m-d', current_time('timestamp'));
    }
}

if (!function_exists('bricks_sire_insights_parse_date_for_query')) {
    function bricks_sire_insights_parse_date_for_query($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return bricks_sire_insights_default_meeting_date();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        $ts = strtotime($value);
        if ($ts !== false) {
            return gmdate('Y-m-d', $ts);
        }

        return bricks_sire_insights_default_meeting_date();
    }
}

if (!function_exists('bricks_sire_insights_format_scheduled_time')) {
    function bricks_sire_insights_format_scheduled_time($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}:\d{2})/', $value, $m)) {
            return $m[1];
        }
        $ts = strtotime($value);
        if ($ts !== false) {
            return wp_date('H:i', $ts);
        }
        return $value;
    }
}

if (!function_exists('bricks_sire_insights_enrich_rows')) {
    /**
     * Attach horse names and race labels from daily runner/race tables (no internal IDs in UI).
     *
     * @param object[] $rows
     * @return object[]
     */
    function bricks_sire_insights_enrich_rows(array $rows, $meeting_date = '') {
        global $wpdb;
        if (empty($rows)) {
            return $rows;
        }

        $runner_ids = [];
        $race_ids = [];
        foreach ($rows as $row) {
            $rid = isset($row->runner_id) ? intval($row->runner_id) : 0;
            if ($rid > 0) {
                $runner_ids[$rid] = true;
            }
            $race_id = isset($row->race_id) ? intval($row->race_id) : 0;
            if ($race_id > 0) {
                $race_ids[$race_id] = true;
            }
        }

        $runner_names = [];
        $race_meta = [];
        if (!empty($runner_ids) || !empty($race_ids)) {
            $tables = null;
            if ($meeting_date !== '' && function_exists('bricks_race_tables_for_date')) {
                $tables = bricks_race_tables_for_date($meeting_date);
            }
            if (!is_array($tables)) {
                $tables = [
                    'races' => 'advance_daily_races_beta',
                    'runners' => 'advance_daily_runners_beta',
                ];
            }

            $runners_table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($tables['runners'] ?? ''));
            $races_table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($tables['races'] ?? ''));

            if (!empty($runner_ids) && $runners_table !== '' && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $runners_table)) === $runners_table) {
                $runner_cols = $wpdb->get_col("SHOW COLUMNS FROM `$runners_table`");
                $name_col = in_array('name', (array) $runner_cols, true) ? 'name' : (in_array('horse_name', (array) $runner_cols, true) ? 'horse_name' : '');
                if ($name_col !== '') {
                    $ids = array_keys($runner_ids);
                    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                    $sql = "SELECT runner_id, `$name_col` AS horse_name FROM `$runners_table` WHERE runner_id IN ($placeholders)";
                    $name_rows = $wpdb->get_results($wpdb->prepare($sql, ...$ids));
                    foreach ((array) $name_rows as $name_row) {
                        $key = intval($name_row->runner_id ?? 0);
                        $label = trim((string) ($name_row->horse_name ?? ''));
                        if ($key > 0 && $label !== '') {
                            $runner_names[$key] = $label;
                        }
                    }
                }
            }

            if (!empty($race_ids) && $races_table !== '' && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $races_table)) === $races_table) {
                $ids = array_keys($race_ids);
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $sql = "SELECT race_id, course, scheduled_time, race_title FROM `$races_table` WHERE race_id IN ($placeholders)";
                $race_rows = $wpdb->get_results($wpdb->prepare($sql, ...$ids));
                foreach ((array) $race_rows as $race_row) {
                    $key = intval($race_row->race_id ?? 0);
                    if ($key > 0) {
                        $race_meta[$key] = $race_row;
                    }
                }
            }
        }

        foreach ($rows as $row) {
            $runner_id = isset($row->runner_id) ? intval($row->runner_id) : 0;
            $horse_from_row = trim((string) ($row->horse_name ?? ''));
            if ($horse_from_row !== '' && !preg_match('/^\d+$/', $horse_from_row)) {
                $row->horse_display = $horse_from_row;
            } elseif ($runner_id > 0 && isset($runner_names[$runner_id])) {
                $row->horse_display = $runner_names[$runner_id];
            } else {
                $row->horse_display = '';
            }

            $race_id = isset($row->race_id) ? intval($row->race_id) : 0;
            $meta = ($race_id > 0 && isset($race_meta[$race_id])) ? $race_meta[$race_id] : null;
            $course = trim((string) ($meta->course ?? $row->course ?? ''));
            $time = $meta ? bricks_sire_insights_format_scheduled_time($meta->scheduled_time ?? '') : '';
            $label_parts = [];
            if ($time !== '') {
                $label_parts[] = $time;
            }
            if ($course !== '') {
                $label_parts[] = $course;
            }
            $row->race_label = implode(' · ', $label_parts);
            if ($row->race_label === '' && $meta && !empty($meta->race_title)) {
                $row->race_label = trim((string) $meta->race_title);
            }
        }

        return $rows;
    }
}

if (!function_exists('bricks_sire_insights_get_latest_date')) {
    function bricks_sire_insights_get_latest_date($table = '', $date_col = '') {
        global $wpdb;
        $table = $table ?: bricks_sire_insights_resolve_table();
        if ($table === '') {
            return '';
        }

        if ($date_col === '') {
            $cfg = bricks_sire_insights_config();
            $date_col = $cfg['date_col'] ?? '';
        }
        if ($date_col === '') {
            return '';
        }

        $safe_col = '`' . esc_sql($date_col) . '`';
        $sql = "SELECT COALESCE(
                    DATE($safe_col),
                    DATE(STR_TO_DATE($safe_col, '%d-%m-%Y')),
                    DATE(STR_TO_DATE($safe_col, '%d/%m/%Y'))
                ) AS d
                FROM `" . esc_sql($table) . "`
                HAVING d IS NOT NULL
                ORDER BY d DESC
                LIMIT 1";
        $latest = $wpdb->get_var($sql);
        return is_string($latest) ? $latest : '';
    }
}

if (!function_exists('bricks_sire_insights_date_where_sql')) {
    function bricks_sire_insights_date_where_sql($column, $date_ymd, &$params) {
        $params[] = $date_ymd;
        $params[] = convert_date_format($date_ymd, 'd-m-Y');
        $params[] = str_replace('-', '/', convert_date_format($date_ymd, 'd-m-Y'));
        $params[] = $date_ymd;
        $params[] = $date_ymd;
        $params[] = $date_ymd;
        return "($column = %s OR $column = %s OR $column = %s OR DATE($column) = %s OR DATE(STR_TO_DATE($column, '%d-%m-%Y')) = %s OR DATE(STR_TO_DATE($column, '%d/%m/%Y')) = %s)";
    }
}

if (!function_exists('bricks_sire_insights_has_rows_for_date')) {
    function bricks_sire_insights_has_rows_for_date($table, $date_col, $date_ymd) {
        global $wpdb;
        if ($table === '' || $date_col === '' || $date_ymd === '') {
            return false;
        }
        $params = [];
        $where = bricks_sire_insights_date_where_sql('`' . esc_sql($date_col) . '`', $date_ymd, $params);
        $sql = "SELECT COUNT(*) FROM `" . esc_sql($table) . "` WHERE $where";
        return intval($wpdb->get_var($wpdb->prepare($sql, ...$params))) > 0;
    }
}

if (!function_exists('bricks_sire_insights_get_filter_options')) {
    function bricks_sire_insights_get_filter_options() {
        global $wpdb;
        $table = bricks_sire_insights_resolve_table();

        if ($table === '') {
            wp_send_json_error(['message' => 'Sire insights table not found']);
            return;
        }

        $cfg = bricks_sire_insights_config();
        if (empty($cfg['date_col'])) {
            wp_send_json_error(['message' => 'No date column found in daily_sires_insights']);
            return;
        }
        $date = bricks_sire_insights_parse_date_for_query($_POST['date'] ?? '');
        $effective_date = $date;
        $use_date_scope = bricks_sire_insights_has_rows_for_date($table, $cfg['date_col'], $date);
        if (!$use_date_scope) {
            $latest = bricks_sire_insights_get_latest_date($table, $cfg['date_col']);
            if ($latest !== '') {
                $effective_date = $latest;
                $use_date_scope = bricks_sire_insights_has_rows_for_date($table, $cfg['date_col'], $effective_date);
            }
        }
        $cache_key = bricks_cache_key('sire_filters', [$table, $effective_date, $use_date_scope ? 'date' : 'all']);
        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            wp_send_json($cached);
            return;
        }

        $params = [];
        $where = '1=1';
        if ($use_date_scope) {
            $where = bricks_sire_insights_date_where_sql('`' . esc_sql($cfg['date_col']) . '`', $effective_date, $params);
        }

        $fetch_distinct = function($column) use ($wpdb, $where, $params, $table) {
            if (empty($column)) {
                return [];
            }
            $safe_col = '`' . esc_sql($column) . '`';
            $sql = "SELECT DISTINCT $safe_col AS v
                    FROM `" . esc_sql($table) . "`
                    WHERE $where
                      AND $safe_col IS NOT NULL
                      AND $safe_col != ''
                    ORDER BY $safe_col";
            return $wpdb->get_col($wpdb->prepare($sql, ...$params));
        };

        $payload = [
            'effective_date' => $effective_date,
            'use_date_scope' => $use_date_scope,
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

if (!function_exists('bricks_sire_insights_render_pagination')) {
    function bricks_sire_insights_render_pagination($current_page, $total_pages) {
        $current_page = max(1, intval($current_page));
        $total_pages = max(1, intval($total_pages));
        if ($total_pages <= 1) {
            return '';
        }

        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        ob_start();
        ?>
        <div class="sire-form-pagination">
            <?php if ($current_page > 1): ?>
                <a class="sire-pagination-btn" href="#" data-page="<?php echo esc_attr($current_page - 1); ?>">&laquo; Prev</a>
            <?php endif; ?>
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <a class="sire-pagination-btn<?php echo $i === $current_page ? ' is-active' : ''; ?>" href="#" data-page="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></a>
            <?php endfor; ?>
            <?php if ($current_page < $total_pages): ?>
                <a class="sire-pagination-btn" href="#" data-page="<?php echo esc_attr($current_page + 1); ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('bricks_sire_insights_horse_label')) {
    function bricks_sire_insights_horse_label($row) {
        $horse_label = trim((string) ($row->horse_display ?? $row->horse_name ?? ''));
        if ($horse_label === '' || preg_match('/^\d+$/', $horse_label)) {
            return '';
        }
        return $horse_label;
    }
}

if (!function_exists('bricks_sire_insights_race_link_text')) {
    function bricks_sire_insights_race_link_text($row) {
        $race_label = trim((string) ($row->race_label ?? ''));
        if ($race_label === '') {
            $course = trim((string) ($row->course ?? ''));
            if ($course !== '') {
                $race_label = $course;
            }
        }
        return $race_label;
    }
}

if (!function_exists('bricks_sire_insights_prb_tier')) {
    function bricks_sire_insights_prb_tier($value) {
        if ($value === null || $value === '') {
            return 'neutral';
        }
        $prb = floatval($value);
        if ($prb >= 58) {
            return 'strong';
        }
        if ($prb >= 52) {
            return 'good';
        }
        if ($prb <= 42) {
            return 'weak';
        }
        return 'neutral';
    }
}

if (!function_exists('bricks_sire_insights_format_prb')) {
    function bricks_sire_insights_format_prb($value) {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float) $value, 1);
    }
}

if (!function_exists('bricks_sire_insights_render_sort_bar')) {
    function bricks_sire_insights_render_sort_bar() {
        $columns = [
            'mean_prb' => 'RT 5Y PRB',
            'rc_21d_prb' => 'RC 21D',
            'course_prb' => 'Course',
            'distance_prb' => 'Distance',
            'candd_prb' => 'C&D',
            'horse_name' => 'Horse',
            'sire_name' => 'Sire',
            'course' => 'Meeting',
        ];
        ob_start();
        ?>
        <div class="sire-form-sort-bar" role="toolbar" aria-label="Sort sire form list">
            <?php foreach ($columns as $key => $label): ?>
                <button type="button" class="sortable-sire sire-form-sort-btn" data-sort="<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($label); ?>
                    <span class="sort-indicator" aria-hidden="true"></span>
                </button>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('bricks_sire_insights_render_metric')) {
    function bricks_sire_insights_render_metric($label, $value, $highlight = false) {
        $tier = bricks_sire_insights_prb_tier($value);
        $b_class = ($highlight && in_array($tier, ['strong', 'good'], true)) ? 'green-text' : '';
        ?>
        <div class="runner-card-wide__metric<?php echo $highlight ? ' runner-card-wide__metric--hi' : ''; ?>">
            <small><?php echo esc_html($label); ?></small>
            <?php if ($b_class !== ''): ?>
                <b class="<?php echo esc_attr($b_class); ?>"><?php echo esc_html(bricks_sire_insights_format_prb($value)); ?></b>
            <?php else: ?>
                <b><?php echo esc_html(bricks_sire_insights_format_prb($value)); ?></b>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('bricks_sire_insights_render_table_html')) {
    function bricks_sire_insights_render_table_html($rows, $meta = []) {
        $current_page = intval($meta['page'] ?? 1);
        $total_pages = intval($meta['total_pages'] ?? 1);
        $total_rows = intval($meta['total_rows'] ?? count((array) $rows));
        $per_page = max(1, intval($meta['per_page'] ?? 50));
        $row_offset = ($current_page - 1) * $per_page;
        ob_start();
        echo bricks_sire_insights_render_sort_bar();
        ?>
        <div class="runners-table-wrapper">
            <table class="runners-table runners-table--fit sire-form-cards">
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr class="runner-row sire-form-row sire-form-row--empty">
                            <td class="runner-card-cell">
                                <div class="sire-form-empty">No sire form rows for this date and filter set. Try another date or reset filters.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $index => $row): ?>
                            <?php
                            $horse_label = bricks_sire_insights_horse_label($row);
                            $runner_id = isset($row->runner_id) ? intval($row->runner_id) : 0;
                            $race_id = isset($row->race_id) ? intval($row->race_id) : 0;
                            $race_text = bricks_sire_insights_race_link_text($row);
                            $rt_tier = bricks_sire_insights_prb_tier($row->mean_prb ?? null);
                            $card_class = 'runner-card-wide sire-form-card';
                            if ($rt_tier === 'strong') {
                                $card_class .= ' runner-card-wide--top';
                            }
                            $rank = $row_offset + $index + 1;
                            ?>
                            <tr class="runner-row sire-form-row">
                                <td class="runner-card-cell" colspan="24">
                                    <div class="<?php echo esc_attr($card_class); ?>">
                                        <div class="runner-card-wide__id">
                                            <div class="runner-card-wide__head">
                                                <span class="cloth-badge sire-form-card__rank"><?php echo esc_html((string) $rank); ?></span>
                                                <div class="runner-card-wide__title">
                                                    <?php if ($runner_id > 0 && function_exists('bricks_horse_history_url') && $horse_label !== ''): ?>
                                                        <a href="<?php echo esc_url(bricks_horse_history_url($runner_id)); ?>" class="horse-name-link"><?php echo esc_html($horse_label); ?></a>
                                                    <?php elseif ($horse_label !== ''): ?>
                                                        <span class="runner-card-wide__name"><?php echo esc_html($horse_label); ?></span>
                                                    <?php else: ?>
                                                        <span class="runner-card-wide__name">Runner</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <p class="runner-card-wide__sub">Sire: <?php echo esc_html($row->sire_name ?? '—'); ?></p>
                                            <div class="runner-card-wide__pills">
                                                <?php echo bricks_sire_insights_badge($row->race_type ?? '', 'violet'); ?>
                                                <?php if (!empty($row->going)): ?>
                                                    <?php echo bricks_sire_insights_badge($row->going, 'emerald'); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($row->distance_band)): ?>
                                                    <?php echo bricks_sire_insights_badge($row->distance_band . 'f', 'slate'); ?>
                                                <?php endif; ?>
                                                <?php if (isset($row->age) && $row->age !== null && $row->age !== ''): ?>
                                                    <?php echo bricks_sire_insights_badge('Age ' . $row->age, 'blue'); ?>
                                                <?php endif; ?>
                                                <?php if (isset($row->handicap) && $row->handicap !== null && intval($row->handicap) === 1): ?>
                                                    <?php echo bricks_sire_insights_badge('Hcap', 'amber'); ?>
                                                <?php endif; ?>
                                            </div>
                                            <p class="sire-form-card__race">
                                                <?php if ($race_id > 0 && function_exists('bricks_race_url')): ?>
                                                    <a class="sire-race-link" href="<?php echo esc_url(bricks_race_url($race_id)); ?>"><?php echo esc_html($race_text !== '' ? $race_text : 'Open race card'); ?></a>
                                                <?php else: ?>
                                                    <?php echo esc_html($race_text !== '' ? $race_text : '—'); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="runner-card-wide__body">
                                            <div class="runner-card-wide__rate sire-form-card__rate">
                                                <?php bricks_sire_insights_render_metric('RT 5Y', $row->mean_prb ?? null, true); ?>
                                                <?php bricks_sire_insights_render_metric('RC 21D', $row->rc_21d_prb ?? null); ?>
                                                <?php bricks_sire_insights_render_metric('Course', $row->course_prb ?? null); ?>
                                                <?php bricks_sire_insights_render_metric('Distance', $row->distance_prb ?? null); ?>
                                                <?php bricks_sire_insights_render_metric('C&D', $row->candd_prb ?? null); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($rows)): ?>
            <div class="filter-controls__meta sire-form-results-meta">
                <span><?php echo esc_html(count($rows)); ?></span> shown of <?php echo esc_html($total_rows); ?> runners
            </div>
            <?php echo bricks_sire_insights_render_pagination($current_page, $total_pages); ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('bricks_ajax_load_sire_insights_table')) {
    function bricks_ajax_load_sire_insights_table() {
        global $wpdb;
        $table = bricks_sire_insights_resolve_table();

        if ($table === '') {
            echo '<div style="padding:16px;color:#991b1b;background:#fee2e2;border-radius:8px;">Sire insights table not found in this WordPress database connection.</div>';
            wp_die();
        }

        $cfg = bricks_sire_insights_config();
        if (empty($cfg['date_col'])) {
            echo '<div style="padding:16px;color:#991b1b;background:#fee2e2;border-radius:8px;">No usable date column found in sire insights table.</div>';
            wp_die();
        }
        $date = bricks_sire_insights_parse_date_for_query($_POST['date'] ?? '');
        $effective_date = $date;
        $use_date_scope = bricks_sire_insights_has_rows_for_date($table, $cfg['date_col'], $date);
        if (!$use_date_scope) {
            $latest = bricks_sire_insights_get_latest_date($table, $cfg['date_col']);
            if ($latest !== '') {
                $effective_date = $latest;
                $use_date_scope = bricks_sire_insights_has_rows_for_date($table, $cfg['date_col'], $effective_date);
            }
        }
        $course = sanitize_text_field($_POST['course'] ?? '');
        $race_type = sanitize_text_field($_POST['race_type'] ?? '');
        $going = sanitize_text_field($_POST['going'] ?? '');
        $distance_band = sanitize_text_field($_POST['distance_band'] ?? '');
        $sire = sanitize_text_field($_POST['sire'] ?? '');
        $sort_column = sanitize_text_field($_POST['sort_column'] ?? 'mean_prb');
        $sort_direction = strtolower(sanitize_text_field($_POST['sort_direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = max(10, min(200, intval($_POST['per_page'] ?? 50)));

        $where_parts = [];
        $params = [];
        if ($use_date_scope) {
            $where_parts[] = bricks_sire_insights_date_where_sql('`' . esc_sql($cfg['date_col']) . '`', $effective_date, $params);
        }

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
            'race_id' => $cfg['race_id_col'],
            'runner_id' => $cfg['runner_id_col'],
            'horse_name' => $cfg['horse_col'],
            'sire_name' => $cfg['sire_col'],
            'course' => $cfg['course_col'],
            'race_code' => $cfg['race_code_col'],
            'race_type' => $cfg['race_type_col'],
            'going' => $cfg['going_col'],
            'distance_band' => $cfg['distance_col'],
            'age' => $cfg['age_col'],
            'handicap' => $cfg['handicap_col'],
            'mean_prb' => $cfg['prb_col'],
            'rc_21d_prb' => $cfg['rc_21d_prb_col'],
            'course_prb' => $cfg['course_prb_col'],
            'distance_prb' => $cfg['distance_prb_col'],
            'candd_prb' => $cfg['candd_prb_col'],
        ];
        $selected_sort_col = $sortable_map[$sort_column] ?? $cfg['prb_col'];
        if ($selected_sort_col === '') {
            $selected_sort_col = $cfg['sire_col'] ?: $cfg['date_col'];
        }

        $select_parts = [];
        $select_parts[] = !empty($cfg['race_id_col']) ? '`' . esc_sql($cfg['race_id_col']) . '` AS race_id' : "NULL AS race_id";
        $select_parts[] = !empty($cfg['runner_id_col']) ? '`' . esc_sql($cfg['runner_id_col']) . '` AS runner_id' : "NULL AS runner_id";
        $select_parts[] = !empty($cfg['horse_col']) ? '`' . esc_sql($cfg['horse_col']) . '` AS horse_name' : "'' AS horse_name";
        $select_parts[] = !empty($cfg['sire_col']) ? '`' . esc_sql($cfg['sire_col']) . '` AS sire_name' : "'' AS sire_name";
        $select_parts[] = !empty($cfg['course_col']) ? '`' . esc_sql($cfg['course_col']) . '` AS course' : "'' AS course";
        $select_parts[] = !empty($cfg['race_code_col']) ? '`' . esc_sql($cfg['race_code_col']) . '` AS race_code' : "'' AS race_code";
        $select_parts[] = !empty($cfg['race_type_col']) ? '`' . esc_sql($cfg['race_type_col']) . '` AS race_type' : "'' AS race_type";
        $select_parts[] = !empty($cfg['going_col']) ? '`' . esc_sql($cfg['going_col']) . '` AS going' : "'' AS going";
        $select_parts[] = !empty($cfg['distance_col']) ? '`' . esc_sql($cfg['distance_col']) . '` AS distance_band' : "'' AS distance_band";
        $select_parts[] = !empty($cfg['age_col']) ? '`' . esc_sql($cfg['age_col']) . '` AS age' : "NULL AS age";
        $select_parts[] = !empty($cfg['handicap_col']) ? '`' . esc_sql($cfg['handicap_col']) . '` AS handicap' : "NULL AS handicap";
        $select_parts[] = !empty($cfg['prb_col']) ? '`' . esc_sql($cfg['prb_col']) . '` AS mean_prb' : "NULL AS mean_prb";
        $select_parts[] = !empty($cfg['rc_21d_prb_col']) ? '`' . esc_sql($cfg['rc_21d_prb_col']) . '` AS rc_21d_prb' : "NULL AS rc_21d_prb";
        $select_parts[] = !empty($cfg['course_prb_col']) ? '`' . esc_sql($cfg['course_prb_col']) . '` AS course_prb' : "NULL AS course_prb";
        $select_parts[] = !empty($cfg['distance_prb_col']) ? '`' . esc_sql($cfg['distance_prb_col']) . '` AS distance_prb' : "NULL AS distance_prb";
        $select_parts[] = !empty($cfg['candd_prb_col']) ? '`' . esc_sql($cfg['candd_prb_col']) . '` AS candd_prb' : "NULL AS candd_prb";

        $where_sql = !empty($where_parts) ? ('WHERE ' . implode(' AND ', $where_parts)) : '';
        $count_sql = "SELECT COUNT(*) FROM `" . esc_sql($table) . "` $where_sql";
        $total_rows = intval($wpdb->get_var($wpdb->prepare($count_sql, ...$params)));
        $total_pages = max(1, (int) ceil($total_rows / $per_page));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $per_page;
        $sql = "SELECT " . implode(', ', $select_parts) . "
                FROM `" . esc_sql($table) . "`
                $where_sql
                ORDER BY `" . esc_sql($selected_sort_col) . "` $sort_direction
                LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...array_merge($params, [$per_page, $offset])));
        if (empty($rows) && $use_date_scope) {
            // Last-resort fallback: ignore date and return matching filtered rows.
            $fallback_where_parts = [];
            $fallback_params = [];
            $fallback_add_filter = function($value, $column) use (&$fallback_where_parts, &$fallback_params, $wpdb) {
                if ($value === '' || $column === '') {
                    return;
                }
                $fallback_where_parts[] = '`' . esc_sql($column) . '` = %s';
                $fallback_params[] = $value;
            };
            $fallback_add_filter($course, $cfg['course_col']);
            $fallback_add_filter($race_type, $cfg['race_type_col']);
            $fallback_add_filter($going, $cfg['going_col']);
            $fallback_add_filter($distance_band, $cfg['distance_col']);
            $fallback_add_filter($sire, $cfg['sire_col']);
            $fallback_where_sql = !empty($fallback_where_parts) ? ('WHERE ' . implode(' AND ', $fallback_where_parts)) : '';
            $fallback_count_sql = "SELECT COUNT(*) FROM `" . esc_sql($table) . "` $fallback_where_sql";
            $fallback_total_rows = intval($wpdb->get_var($wpdb->prepare($fallback_count_sql, ...$fallback_params)));
            $total_rows = $fallback_total_rows;
            $total_pages = max(1, (int) ceil($total_rows / $per_page));
            $page = min($page, $total_pages);
            $offset = ($page - 1) * $per_page;

            $fallback_sql = "SELECT " . implode(', ', $select_parts) . "
                    FROM `" . esc_sql($table) . "`
                    $fallback_where_sql
                    ORDER BY `" . esc_sql($selected_sort_col) . "` $sort_direction
                    LIMIT %d OFFSET %d";
            $rows = $wpdb->get_results($wpdb->prepare($fallback_sql, ...array_merge($fallback_params, [$per_page, $offset])));
        }
        $rows = bricks_sire_insights_enrich_rows((array) $rows, $use_date_scope ? $effective_date : '');
        echo bricks_sire_insights_render_table_html($rows, [
            'page' => $page,
            'total_pages' => $total_pages,
            'total_rows' => $total_rows,
            'per_page' => $per_page,
        ]);
        wp_die();
    }
}
add_action('wp_ajax_load_sire_insights_table', 'bricks_ajax_load_sire_insights_table');
add_action('wp_ajax_nopriv_load_sire_insights_table', 'bricks_ajax_load_sire_insights_table');

if (!function_exists('bricks_daily_sires_insights_shortcode')) {
    function bricks_daily_sires_insights_shortcode($atts = []) {
        if (!bricks_sire_insights_table_exists()) {
            return '<div style="padding:16px;color:#991b1b;background:#fee2e2;border-radius:8px;">Sire insights data table is not available on the current WP DB connection yet. If the table exists in another schema, copy/sync it into this site DB.</div>';
        }

        $default_date = bricks_sire_insights_default_meeting_date();
        $daily_url = home_url('/daily/');
        ob_start();
        ?>
        <div class="race-detail-page-shell fhor-sire-form-shell">
            <div class="fhor-race-premium fhor-sire-form-page">
                <div class="back-button-container">
                    <a href="<?php echo esc_url($daily_url); ?>" class="back-button">← Today's races</a>
                </div>

                <div class="race-header-card sire-form-hero">
                    <div class="race-header-top">
                        <div class="race-meta-time">Sire form</div>
                        <h1 class="race-title">Today's sire angles</h1>
                        <div class="race-details-grid">
                            <div class="race-detail-item">
                                <span class="race-detail-label">Card date</span>
                                <span class="race-detail-value" id="sire-display-date"><?php echo esc_html($default_date); ?></span>
                            </div>
                            <div class="race-detail-item">
                                <span class="race-detail-label">Default sort</span>
                                <span class="race-detail-value">RT 5Y PRB</span>
                            </div>
                        </div>
                    </div>
                    <div class="race-header-bottom">
                        <div class="track-advice-box sire-form-hero__copy">
                            <strong>What this is</strong>
                            Runners on the selected card with sire PRB stats for race type, course, distance and recent form — same family of signals as the Lin5 column on race cards, scoped to today's meetings.
                        </div>
                    </div>
                </div>

                <div class="runners-card sire-form-card-panel">
                    <div class="runners-header">
                        <h2 class="runners-title"><span>🧬</span> Sire form list</h2>
                        <p class="runners-subtitle">Wide cards like race runners — tap a horse or race time to open the full card.</p>
                    </div>

                    <button type="button" class="race-filters-toggle sire-filters-toggle" id="sireFiltersToggle" aria-expanded="false" aria-controls="sireFiltersPanel">
                        <span>Filters</span>
                        <span class="race-filters-toggle__state">Show</span>
                    </button>

                    <div class="sire-insights-filters sire-form-filters" id="sireFiltersPanel">
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

                    <div class="filter-controls sire-form-filter-actions">
                        <div class="filter-toggle">
                            <button type="button" id="sire-apply-btn" class="fhor-btn fhor-btn--primary">Apply filters</button>
                            <button type="button" id="sire-reset-btn" class="fhor-btn fhor-btn--secondary">Reset</button>
                        </div>
                    </div>

                    <div class="lin5-legend sire-form-legend">
                        <strong>PRB columns:</strong>
                        RT 5Y = sire line vs race type (5 years). RC 21D = recent course form. Course / Distance / C&amp;D = situational sire PRB for today's race shape.
                        Higher PRB means the sire's runners beat rivals more often in that scope.
                    </div>

                    <div class="runners-mobile-toolbar sire-mobile-toolbar">
                        <label class="runners-mobile-toolbar__label" for="sireMobileSort">Sort by</label>
                        <select id="sireMobileSort" class="runners-mobile-toolbar__select" aria-label="Sort sire form list">
                            <option value="mean_prb">RT 5Y PRB</option>
                            <option value="horse_name">Horse</option>
                            <option value="sire_name">Sire</option>
                            <option value="course">Race (course)</option>
                            <option value="race_type">Type</option>
                            <option value="going">Going</option>
                            <option value="distance_band">Distance</option>
                            <option value="rc_21d_prb">RC 21D PRB</option>
                            <option value="course_prb">Course PRB</option>
                            <option value="distance_prb">Dist PRB</option>
                            <option value="candd_prb">C&amp;D PRB</option>
                        </select>
                    </div>

                    <div id="sire-insights-table-container">
                        <div class="sire-form-loading">Loading sire form…</div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('daily_sires_insights', 'bricks_daily_sires_insights_shortcode');

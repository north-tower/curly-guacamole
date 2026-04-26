<?php
/**
 * Tracker feature: notes storage, widgets, dashboard, and related hooks.
 */

if (!function_exists('bricks_tracker_meta_key')) {
    function bricks_tracker_meta_key() {
        return 'bricks_horse_tracker_notes';
    }
}

if (!function_exists('bricks_tracker_normalize_horse_key')) {
    function bricks_tracker_normalize_horse_key($horse_name) {
        return sanitize_title(wp_strip_all_tags((string) $horse_name));
    }
}

if (!function_exists('bricks_tracker_get_user_data')) {
    function bricks_tracker_get_user_data($user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if (!$user_id) {
            return [];
        }

        $data = get_user_meta($user_id, bricks_tracker_meta_key(), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('bricks_tracker_set_user_data')) {
    function bricks_tracker_set_user_data($data, $user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if (!$user_id) {
            return false;
        }

        if (!is_array($data)) {
            $data = [];
        }

        update_user_meta($user_id, bricks_tracker_meta_key(), $data);
        return true;
    }
}

if (!function_exists('bricks_tracker_add_note')) {
    function bricks_tracker_add_note($horse_name, $note, $race_meta = [], $user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'You must be logged in.');
        }

        $horse_name = sanitize_text_field($horse_name);
        $note = sanitize_textarea_field($note);
        if ($horse_name === '' || $note === '') {
            return new WP_Error('invalid_input', 'Horse and note are required.');
        }

        $horse_key = bricks_tracker_normalize_horse_key($horse_name);
        if ($horse_key === '') {
            return new WP_Error('invalid_horse', 'Invalid horse name.');
        }

        $data = bricks_tracker_get_user_data($user_id);
        if (!isset($data[$horse_key]) || !is_array($data[$horse_key])) {
            $data[$horse_key] = [
                'horse_name' => $horse_name,
                'notes' => []
            ];
        }

        $new_note = [
            'id' => uniqid('tn_', true),
            'note' => $note,
            'created_at' => current_time('mysql'),
            'race_date' => sanitize_text_field($race_meta['race_date'] ?? ''),
            'race_time' => sanitize_text_field($race_meta['race_time'] ?? ''),
            'course' => sanitize_text_field($race_meta['course'] ?? ''),
            'race_id' => sanitize_text_field($race_meta['race_id'] ?? ''),
        ];

        $data[$horse_key]['horse_name'] = $horse_name;
        if (!isset($data[$horse_key]['notes']) || !is_array($data[$horse_key]['notes'])) {
            $data[$horse_key]['notes'] = [];
        }
        $data[$horse_key]['notes'][] = $new_note;

        bricks_tracker_set_user_data($data, $user_id);
        return $new_note;
    }
}

if (!function_exists('bricks_tracker_delete_note')) {
    function bricks_tracker_delete_note($horse_name, $note_id, $user_id = 0) {
        $user_id = $user_id ? intval($user_id) : get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'You must be logged in.');
        }

        $horse_key = bricks_tracker_normalize_horse_key($horse_name);
        if ($horse_key === '' || $note_id === '') {
            return new WP_Error('invalid_input', 'Invalid tracker delete request.');
        }

        $data = bricks_tracker_get_user_data($user_id);
        if (empty($data[$horse_key]['notes']) || !is_array($data[$horse_key]['notes'])) {
            return new WP_Error('not_found', 'Tracker note not found.');
        }

        $data[$horse_key]['notes'] = array_values(array_filter($data[$horse_key]['notes'], function($entry) use ($note_id) {
            return !isset($entry['id']) || $entry['id'] !== $note_id;
        }));

        if (empty($data[$horse_key]['notes'])) {
            unset($data[$horse_key]);
        }

        bricks_tracker_set_user_data($data, $user_id);
        return true;
    }
}

function bricks_ajax_add_tracker_note() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in to use tracker notes.'], 403);
    }

    check_ajax_referer('bricks_tracker_nonce', 'nonce');

    $horse_name = isset($_POST['horse_name']) ? wp_unslash($_POST['horse_name']) : '';
    $note = isset($_POST['note']) ? wp_unslash($_POST['note']) : '';

    $result = bricks_tracker_add_note($horse_name, $note, [
        'race_date' => isset($_POST['race_date']) ? wp_unslash($_POST['race_date']) : '',
        'race_time' => isset($_POST['race_time']) ? wp_unslash($_POST['race_time']) : '',
        'course' => isset($_POST['course']) ? wp_unslash($_POST['course']) : '',
        'race_id' => isset($_POST['race_id']) ? wp_unslash($_POST['race_id']) : '',
    ]);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }

    wp_send_json_success([
        'message' => 'Tracker note saved.',
        'note' => [
            'id' => isset($result['id']) ? sanitize_text_field($result['id']) : '',
            'note' => isset($result['note']) ? sanitize_textarea_field($result['note']) : '',
            'created_at' => isset($result['created_at']) ? sanitize_text_field($result['created_at']) : current_time('mysql'),
            'race_date' => isset($result['race_date']) ? sanitize_text_field($result['race_date']) : '',
            'race_time' => isset($result['race_time']) ? sanitize_text_field($result['race_time']) : '',
            'course' => isset($result['course']) ? sanitize_text_field($result['course']) : '',
            'race_id' => isset($result['race_id']) ? sanitize_text_field($result['race_id']) : '',
        ]
    ]);
}
add_action('wp_ajax_bricks_add_tracker_note', 'bricks_ajax_add_tracker_note');

function bricks_ajax_delete_tracker_note() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in to use tracker notes.'], 403);
    }

    check_ajax_referer('bricks_tracker_nonce', 'nonce');

    $horse_name = isset($_POST['horse_name']) ? sanitize_text_field(wp_unslash($_POST['horse_name'])) : '';
    $note_id = isset($_POST['note_id']) ? sanitize_text_field(wp_unslash($_POST['note_id'])) : '';
    $result = bricks_tracker_delete_note($horse_name, $note_id);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }

    wp_send_json_success(['message' => 'Tracker note deleted.']);
}
add_action('wp_ajax_bricks_delete_tracker_note', 'bricks_ajax_delete_tracker_note');

if (!function_exists('bricks_tracker_get_notes_for_horse')) {
    function bricks_tracker_get_notes_for_horse($horse_name, $user_id = 0) {
        $horse_key = bricks_tracker_normalize_horse_key($horse_name);
        if ($horse_key === '') {
            return [];
        }

        $data = bricks_tracker_get_user_data($user_id ?: get_current_user_id());
        if (empty($data[$horse_key]['notes']) || !is_array($data[$horse_key]['notes'])) {
            return [];
        }

        return $data[$horse_key]['notes'];
    }
}

if (!function_exists('bricks_tracker_render_horse_widget')) {
    function bricks_tracker_render_horse_widget($horse_name, $race_meta = [], $args = []) {
        $horse_name = sanitize_text_field((string) $horse_name);
        if ($horse_name === '') {
            return '';
        }

        $args = wp_parse_args($args, [
            'show_latest_flag' => false,
            'wrapper_class' => '',
            'compact' => false
        ]);

        $notes = is_user_logged_in() ? bricks_tracker_get_notes_for_horse($horse_name, get_current_user_id()) : [];
        $count = count($notes);
        $latest = $count > 0 ? end($notes) : null;

        $panel_id = 'tracker-panel-' . md5($horse_name . '|' . maybe_serialize($race_meta) . '|' . wp_rand(1000, 9999));
        $button_text = $count > 0 ? 'My Tracker (' . $count . ')' : 'Add to Tracker';
        $notes_html = '';

        if ($count > 0) {
            foreach ($notes as $entry) {
                $note_id = esc_attr($entry['id'] ?? '');
                $note_text = esc_html($entry['note'] ?? '');
                $note_date = !empty($entry['created_at']) ? esc_html(date_i18n('d M Y H:i', strtotime($entry['created_at']))) : '';
                $note_race = trim(implode(' | ', array_filter([
                    $entry['race_date'] ?? '',
                    $entry['race_time'] ?? '',
                    $entry['course'] ?? ''
                ])));
                $notes_html .= '<div class="tracker-note-item">
                    <div class="tracker-note-meta">' . $note_date . ($note_race ? ' - ' . esc_html($note_race) : '') . '</div>
                    <div class="tracker-note-text">' . nl2br($note_text) . '</div>
                    <button type="button" class="tracker-delete-btn" data-horse-name="' . esc_attr($horse_name) . '" data-note-id="' . $note_id . '">Delete</button>
                </div>';
            }
        } else {
            $notes_html = '<div class="tracker-empty">No notes yet for this horse.</div>';
        }

        $latest_html = '';
        if ($args['show_latest_flag'] && $latest && !empty($latest['note'])) {
            $latest_html = '<div class="tracker-flag">📝 Tracker: ' . esc_html(wp_trim_words($latest['note'], 14, '...')) . '</div>';
        }

        $widget_html = '<div class="tracker-inline-widget ' . esc_attr($args['wrapper_class']) . '">
            ' . $latest_html;

        if (!is_user_logged_in()) {
            $widget_html .= '<span class="tracker-guest-message">Log in to use tracker</span></div>';
            return $widget_html;
        }

        $widget_html .= '<button type="button" class="tracker-toggle-btn tracker-compact-btn" data-target="#' . esc_attr($panel_id) . '">' . esc_html($button_text) . '</button>
            <div id="' . esc_attr($panel_id) . '" class="tracker-panel" style="display:none;">
                <div class="tracker-notes-list">' . $notes_html . '</div>
                <textarea class="tracker-note-input" rows="3" placeholder="Add your note about this horse..."></textarea>
                <button type="button" class="tracker-save-btn"
                    data-horse-name="' . esc_attr($horse_name) . '"
                    data-race-id="' . esc_attr($race_meta['race_id'] ?? '') . '"
                    data-race-date="' . esc_attr($race_meta['race_date'] ?? '') . '"
                    data-race-time="' . esc_attr($race_meta['race_time'] ?? '') . '"
                    data-course="' . esc_attr($race_meta['course'] ?? '') . '">Save Note</button>
            </div>
        </div>';

        return $widget_html;
    }
}

function bricks_tracker_inline_js() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        if (window.bricksTrackerBindingsAdded) {
            return;
        }
        window.bricksTrackerBindingsAdded = true;

        function trackerConfig() {
            if (typeof speed_performance_ajax_obj !== 'undefined') {
                return speed_performance_ajax_obj;
            }
            if (typeof bricks_tracker_obj !== 'undefined') {
                return bricks_tracker_obj;
            }
            return { ajax_url: '', is_logged_in: false, tracker_nonce: '' };
        }

        function updateTrackerButtonCount($panel) {
            const $widget = $panel.closest('.tracker-inline-widget');
            const $btn = $widget.find('.tracker-toggle-btn').first();
            if (!$btn.length) return;
            const count = $panel.find('.tracker-note-item').length;
            $btn.text(count > 0 ? ('My Tracker (' + count + ')') : 'Add to Tracker');
        }

        function ensureTrackerFlag($panel, noteText) {
            const $widget = $panel.closest('.tracker-inline-widget');
            let $flag = $widget.find('.tracker-flag').first();
            const text = (noteText || '').trim();
            if (!text) return;
            const shortText = text.length > 120 ? (text.substring(0, 117) + '...') : text;
            if (!$flag.length) {
                $flag = $('<div class="tracker-flag"></div>');
                $widget.prepend($flag);
            }
            $flag.text('📝 Tracker: ' + shortText);
        }

        $(document).on('click', '.tracker-toggle-btn', function() {
            const target = $(this).data('target');
            if (target) {
                $(target).slideToggle(150);
            }
        });

        $(document).on('click', '.tracker-save-btn', function() {
            const cfg = trackerConfig();
            if (!cfg.is_logged_in) {
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

            $.post(cfg.ajax_url, {
                action: 'bricks_add_tracker_note',
                nonce: cfg.tracker_nonce,
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

        $(document).on('click', '.tracker-delete-btn', function() {
            const cfg = trackerConfig();
            if (!cfg.is_logged_in) {
                alert('Please log in to use tracker notes.');
                return;
            }
            if (!confirm('Delete this note?')) {
                return;
            }

            const $button = $(this);
            $button.prop('disabled', true).text('Deleting...');

            $.post(cfg.ajax_url, {
                action: 'bricks_delete_tracker_note',
                nonce: cfg.tracker_nonce,
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
    });
    </script>
    <?php
}
add_action('wp_footer', 'bricks_tracker_inline_js', 1000);

function bricks_tracker_inline_styles() {
    ?>
    <style>
    [title] { cursor: pointer; }
    .tracker-inline-widget { margin-top: 6px; }
    .tracker-flag { margin-top: 4px; font-size: 11px; font-weight: 500; color: #92400e; line-height: 1.4; }
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
        background: #fff;
    }
    .tracker-note-item { border-bottom: 1px solid #f3f4f6; padding-bottom: 8px; margin-bottom: 8px; }
    .tracker-note-item:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
    .tracker-note-meta { color: #6b7280; font-size: 11px; margin-bottom: 4px; }
    .tracker-note-text { font-size: 12px; color: #111827; margin-bottom: 6px; white-space: normal; }
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
    .tracker-save-btn { margin-top: 8px; }
    .tracker-guest-message, .tracker-empty { color: #6b7280; font-size: 12px; }
    .tracker-horse-header .tracker-toggle-btn { margin-top: 10px; }
    .tracker-summary-link {
        display: inline-flex;
        align-items: center;
        color: #7c2d12;
        background: rgba(255, 255, 255, 0.6);
        padding: 6px 10px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
    }
    .tracker-summary-link:hover {
        background: #fff;
        color: #9a3412;
    }
    </style>
    <?php
}
add_action('wp_head', 'bricks_tracker_inline_styles', 1000);

function bricks_tracker_floating_quick_link() {
    if (!is_user_logged_in() || is_admin() || get_query_var('my_tracker_page') || get_query_var('my_points_backtest')) {
        return;
    }
    ?>
    <div style="position:fixed;right:18px;bottom:18px;z-index:9999;display:flex;flex-direction:column;gap:8px;">
        <a
            href="<?php echo esc_url(home_url('/my-tracker/')); ?>"
            title="Open My Tracker"
            style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);color:#fff;font-weight:700;font-size:13px;text-decoration:none;box-shadow:0 8px 20px rgba(109,40,217,0.35);"
        >📝 My Tracker</a>
        <a
            href="<?php echo esc_url(home_url('/points-backtest/')); ?>"
            title="Open Points Backtest"
            style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);color:#fff;font-weight:700;font-size:13px;text-decoration:none;box-shadow:0 8px 20px rgba(30,64,175,0.35);"
        >📊 Points Backtest</a>
    </div>
    <?php
}
add_action('wp_footer', 'bricks_tracker_floating_quick_link', 1001);

function bricks_my_tracker_dashboard_shortcode($atts = []) {
    if (!is_user_logged_in()) {
        return '<div style="max-width:760px;margin:40px auto;padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
            <h2 style="margin:0 0 10px 0;color:#111827;">My Tracker</h2>
            <p style="margin:0;color:#6b7280;">Please log in to view and manage your tracked horses.</p>
        </div>';
    }

    $tracker_data = bricks_tracker_get_user_data(get_current_user_id());
    $rows = [];

    foreach ($tracker_data as $entry) {
        $horse_name = isset($entry['horse_name']) ? sanitize_text_field($entry['horse_name']) : '';
        $notes = (isset($entry['notes']) && is_array($entry['notes'])) ? $entry['notes'] : [];
        if ($horse_name === '' || empty($notes)) {
            continue;
        }

        $note_rows = [];
        foreach ($notes as $note_entry) {
            if (!is_array($note_entry)) {
                continue;
            }

            $note_text = isset($note_entry['note']) ? sanitize_textarea_field($note_entry['note']) : '';
            $note_created_at = isset($note_entry['created_at']) ? sanitize_text_field($note_entry['created_at']) : '';
            $note_course = isset($note_entry['course']) ? sanitize_text_field($note_entry['course']) : '';
            $note_id = isset($note_entry['id']) ? sanitize_text_field($note_entry['id']) : '';
            $note_race_date = isset($note_entry['race_date']) ? sanitize_text_field($note_entry['race_date']) : '';
            $note_race_time = isset($note_entry['race_time']) ? sanitize_text_field($note_entry['race_time']) : '';

            if ($note_id === '' || $note_text === '') {
                continue;
            }

            $note_rows[] = [
                'note' => $note_text,
                'created_at' => $note_created_at,
                'course' => $note_course,
                'note_id' => $note_id,
                'race_date' => $note_race_date,
                'race_time' => $note_race_time,
            ];
        }

        if (empty($note_rows)) {
            continue;
        }

        usort($note_rows, function($a, $b) {
            $a_ts = !empty($a['created_at']) ? strtotime($a['created_at']) : 0;
            $b_ts = !empty($b['created_at']) ? strtotime($b['created_at']) : 0;
            return $b_ts <=> $a_ts;
        });

        $latest = $note_rows[0];
        $rows[] = [
            'horse_name' => $horse_name,
            'notes_count' => count($note_rows),
            'latest_created_at' => $latest['created_at'],
            'latest_course' => $latest['course'],
            'latest_race_date' => $latest['race_date'],
            'latest_race_time' => $latest['race_time'],
            'notes' => $note_rows,
        ];
    }

    usort($rows, function($a, $b) {
        $a_ts = !empty($a['latest_created_at']) ? strtotime($a['latest_created_at']) : 0;
        $b_ts = !empty($b['latest_created_at']) ? strtotime($b['latest_created_at']) : 0;
        return $b_ts <=> $a_ts;
    });

    ob_start();
    ?>
    <div class="my-tracker-dashboard">
        <div class="my-tracker-header">
            <h1>📝 My Tracker</h1>
            <p>Tracked horses grouped with expandable comments.</p>
        </div>

        <?php if (empty($rows)): ?>
            <div class="my-tracker-empty">
                No tracked horses yet. Add notes from race cards, quick reference, or comments pages.
            </div>
        <?php else: ?>
            <div class="my-tracker-table-wrap">
                <table class="my-tracker-table">
                    <thead>
                        <tr>
                            <th>Horse</th>
                            <th>Latest Date</th>
                            <th>Latest Course</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $display_date = $row['latest_created_at'] ? date_i18n('d M Y H:i', strtotime($row['latest_created_at'])) : '-';
                            $meta_course = trim(implode(' | ', array_filter([
                                $row['latest_race_date'],
                                $row['latest_race_time'],
                                $row['latest_course']
                            ])));
                            ?>
                            <tr>
                                <td style="font-weight:700;"><?php echo esc_html($row['horse_name']); ?></td>
                                <td><?php echo esc_html($display_date); ?></td>
                                <td><?php echo esc_html($meta_course ?: '-'); ?></td>
                                <td>
                                    <details class="my-tracker-details">
                                        <summary>
                                            View Comments (<?php echo esc_html($row['notes_count']); ?>)
                                        </summary>
                                        <div class="my-tracker-notes-dropdown">
                                            <?php foreach ($row['notes'] as $note_row): ?>
                                                <?php
                                                $note_date = $note_row['created_at'] ? date_i18n('d M Y H:i', strtotime($note_row['created_at'])) : '-';
                                                $note_course = trim(implode(' | ', array_filter([
                                                    $note_row['race_date'],
                                                    $note_row['race_time'],
                                                    $note_row['course']
                                                ])));
                                                ?>
                                                <div class="my-tracker-note-item">
                                                    <div class="my-tracker-note-meta">
                                                        <?php echo esc_html($note_date); ?>
                                                        <?php if ($note_course): ?>
                                                            - <?php echo esc_html($note_course); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="my-tracker-note-text"><?php echo esc_html($note_row['note']); ?></div>
                                                    <button
                                                        type="button"
                                                        class="tracker-delete-btn my-tracker-delete-btn"
                                                        data-horse-name="<?php echo esc_attr($row['horse_name']); ?>"
                                                        data-note-id="<?php echo esc_attr($note_row['note_id']); ?>">
                                                        Delete
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <style>
    .my-tracker-dashboard { max-width: 1200px; margin: 24px auto; padding: 0 16px 30px; }
    .my-tracker-header h1 { margin: 0 0 6px; color: #111827; font-size: 30px; font-weight: 800; }
    .my-tracker-header p { margin: 0 0 16px; color: #6b7280; }
    .my-tracker-empty { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; color: #6b7280; }
    .my-tracker-table-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: auto; }
    .my-tracker-table { width: 100%; border-collapse: collapse; min-width: 900px; }
    .my-tracker-table th, .my-tracker-table td { padding: 12px; border-bottom: 1px solid #f3f4f6; text-align: left; }
    .my-tracker-table th { background: #f9fafb; font-size: 12px; text-transform: uppercase; letter-spacing: 0.4px; color: #374151; }
    .my-tracker-details summary { cursor: pointer; font-weight: 600; color: #2563eb; }
    .my-tracker-notes-dropdown { margin-top: 8px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; padding: 10px; }
    .my-tracker-note-item { border-bottom: 1px solid #f3f4f6; padding: 10px 0; }
    .my-tracker-note-item:last-child { border-bottom: none; }
    .my-tracker-note-meta { font-size: 11px; color: #6b7280; margin-bottom: 6px; }
    .my-tracker-note-text { font-size: 13px; color: #111827; margin-bottom: 8px; white-space: normal; }
    .my-tracker-delete-btn { white-space: nowrap; }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('my_tracker_dashboard', 'bricks_my_tracker_dashboard_shortcode');

function bricks_add_my_tracker_menu_item($items, $args) {
    if (is_admin()) {
        return $items;
    }

    $tracker_url = home_url('/my-tracker/');
    $points_url = home_url('/points-backtest/');
    if (strpos($items, $tracker_url) !== false) {
        if (strpos($items, $points_url) !== false) {
            return $items;
        }
    }

    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    $active_class = (strpos($current_url, '/my-tracker') !== false) ? ' current-menu-item current_page_item' : '';
    $active_points_class = (strpos($current_url, '/points-backtest') !== false) ? ' current-menu-item current_page_item' : '';

    $items .= '<li class="menu-item menu-item-type-custom menu-item-my-tracker' . esc_attr($active_class) . '">
        <a href="' . esc_url($tracker_url) . '">My Tracker</a>
    </li>';
    $items .= '<li class="menu-item menu-item-type-custom menu-item-points-backtest' . esc_attr($active_points_class) . '">
        <a href="' . esc_url($points_url) . '">Points Backtest</a>
    </li>';

    return $items;
}
add_filter('wp_nav_menu_items', 'bricks_add_my_tracker_menu_item', 20, 2);

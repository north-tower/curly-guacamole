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

if (!function_exists('fhor_tracker_note_kinds')) {
    function fhor_tracker_note_kinds() {
        return ['note', 'trainer_quote'];
    }
}

if (!function_exists('fhor_tracker_quote_sources')) {
    function fhor_tracker_quote_sources() {
        return [
            'TV',
            'Newspaper',
            'Trainer website',
            'Podcast / radio',
            'Stable / social',
            'Other',
        ];
    }
}

if (!function_exists('fhor_tracker_sanitize_kind')) {
    function fhor_tracker_sanitize_kind($kind) {
        $kind = sanitize_key((string) $kind);
        return $kind === 'trainer_quote' ? 'trainer_quote' : 'note';
    }
}

if (!function_exists('fhor_tracker_sanitize_source')) {
    function fhor_tracker_sanitize_source($source, $kind) {
        if ($kind !== 'trainer_quote') {
            return '';
        }
        $source = sanitize_text_field((string) $source);
        return in_array($source, fhor_tracker_quote_sources(), true) ? $source : '';
    }
}

if (!function_exists('fhor_tracker_sanitize_trainer_name')) {
    function fhor_tracker_sanitize_trainer_name($trainer_name) {
        $trainer_name = sanitize_text_field((string) $trainer_name);
        if (strlen($trainer_name) > 100) {
            $trainer_name = substr($trainer_name, 0, 100);
        }
        return $trainer_name;
    }
}

if (!function_exists('fhor_tracker_get_entry_kind')) {
    function fhor_tracker_get_entry_kind($entry) {
        if (!is_array($entry)) {
            return 'note';
        }
        return fhor_tracker_sanitize_kind($entry['kind'] ?? 'note');
    }
}

if (!function_exists('bricks_tracker_sort_notes_newest')) {
    function bricks_tracker_sort_notes_newest(array $notes) {
        usort($notes, function ($a, $b) {
            $a_ts = !empty($a['created_at']) ? strtotime($a['created_at']) : 0;
            $b_ts = !empty($b['created_at']) ? strtotime($b['created_at']) : 0;
            return $b_ts <=> $a_ts;
        });
        return $notes;
    }
}

if (!function_exists('bricks_tracker_pick_latest_for_context')) {
    function bricks_tracker_pick_latest_for_context(array $notes, $race_meta = []) {
        if (empty($notes)) {
            return null;
        }
        $notes = bricks_tracker_sort_notes_newest($notes);
        $race_id = sanitize_text_field($race_meta['race_id'] ?? '');
        $race_date = sanitize_text_field($race_meta['race_date'] ?? '');

        if ($race_id !== '') {
            foreach ($notes as $entry) {
                if (!empty($entry['race_id']) && (string) $entry['race_id'] === $race_id) {
                    return $entry;
                }
            }
        }
        if ($race_date !== '') {
            foreach ($notes as $entry) {
                if (!empty($entry['race_date']) && (string) $entry['race_date'] === $race_date) {
                    return $entry;
                }
            }
        }
        return $notes[0];
    }
}

if (!function_exists('fhor_tracker_render_note_item_html')) {
    function fhor_tracker_render_note_item_html($entry, $horse_name, $context = 'inline') {
        if (!is_array($entry)) {
            return '';
        }
        $horse_name = sanitize_text_field((string) $horse_name);
        $note_id = esc_attr($entry['id'] ?? '');
        $kind = fhor_tracker_get_entry_kind($entry);
        $is_quote = ($kind === 'trainer_quote');
        $note_text = esc_html($entry['note'] ?? '');
        $note_date = !empty($entry['created_at']) ? esc_html(date_i18n('d M Y H:i', strtotime($entry['created_at']))) : '';
        $note_race = trim(implode(' | ', array_filter([
            $entry['race_date'] ?? '',
            $entry['race_time'] ?? '',
            $entry['course'] ?? '',
        ])));
        $pill_label = $is_quote ? 'Quote' : 'Note';
        $pill_class = $is_quote ? 'fhor-tracker-type-pill fhor-tracker-type-pill--quote' : 'fhor-tracker-type-pill';
        $body_class = $is_quote ? 'fhor-tracker-note-body fhor-tracker-note-body--quote' : 'fhor-tracker-note-body';

        $footer_parts = [];
        if ($is_quote && !empty($entry['trainer_name'])) {
            $footer_parts[] = 'Trainer: ' . esc_html($entry['trainer_name']);
        }
        if ($is_quote && !empty($entry['source'])) {
            $footer_parts[] = 'Source: ' . esc_html($entry['source']);
        }
        $footer_html = '';
        if (!empty($footer_parts)) {
            $footer_html = '<div class="fhor-tracker-note-footer">' . implode(' · ', $footer_parts) . '</div>';
        }

        $item_class = 'fhor-tracker-note-item tracker-note-item';
        if ($context === 'dashboard') {
            $item_class .= ' my-tracker-note-item';
        }

        return '<div class="' . esc_attr($item_class) . '">
            <div class="fhor-tracker-note-head">
                <span class="' . esc_attr($pill_class) . '">' . esc_html($pill_label) . '</span>
            </div>
            <div class="fhor-tracker-note-meta">' . $note_date . ($note_race ? ' — ' . esc_html($note_race) : '') . '</div>
            <div class="' . esc_attr($body_class) . '">' . nl2br($note_text) . '</div>
            ' . $footer_html . '
            <button type="button" class="fhor-tracker-delete-btn tracker-delete-btn" data-horse-name="' . esc_attr($horse_name) . '" data-note-id="' . $note_id . '">Delete</button>
        </div>';
    }
}

if (!function_exists('fhor_tracker_render_latest_flag_html')) {
    function fhor_tracker_render_latest_flag_html($entry) {
        if (!is_array($entry) || empty($entry['note'])) {
            return '';
        }
        $kind = fhor_tracker_get_entry_kind($entry);
        $snippet = esc_html(wp_trim_words($entry['note'], 14, '…'));
        if ($kind === 'trainer_quote') {
            return '<div class="fhor-tracker-flag tracker-flag"><span class="fhor-tracker-flag__label">Trainer quote:</span> <span class="fhor-tracker-flag__quote">' . $snippet . '</span></div>';
        }
        return '<div class="fhor-tracker-flag tracker-flag"><span class="fhor-tracker-flag__label">Note:</span> ' . $snippet . '</div>';
    }
}

if (!function_exists('fhor_tracker_note_to_array')) {
    function fhor_tracker_note_to_array($note) {
        if (!is_array($note)) {
            return [];
        }
        return [
            'id' => isset($note['id']) ? sanitize_text_field($note['id']) : '',
            'kind' => fhor_tracker_get_entry_kind($note),
            'note' => isset($note['note']) ? sanitize_textarea_field($note['note']) : '',
            'source' => isset($note['source']) ? sanitize_text_field($note['source']) : '',
            'trainer_name' => isset($note['trainer_name']) ? fhor_tracker_sanitize_trainer_name($note['trainer_name']) : '',
            'created_at' => isset($note['created_at']) ? sanitize_text_field($note['created_at']) : current_time('mysql'),
            'race_date' => isset($note['race_date']) ? sanitize_text_field($note['race_date']) : '',
            'race_time' => isset($note['race_time']) ? sanitize_text_field($note['race_time']) : '',
            'course' => isset($note['course']) ? sanitize_text_field($note['course']) : '',
            'race_id' => isset($note['race_id']) ? sanitize_text_field($note['race_id']) : '',
        ];
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

        $kind = fhor_tracker_sanitize_kind($race_meta['kind'] ?? 'note');
        $source = fhor_tracker_sanitize_source($race_meta['source'] ?? '', $kind);
        $trainer_name = fhor_tracker_sanitize_trainer_name($race_meta['trainer_name'] ?? '');

        $data = bricks_tracker_get_user_data($user_id);
        if (!isset($data[$horse_key]) || !is_array($data[$horse_key])) {
            $data[$horse_key] = [
                'horse_name' => $horse_name,
                'notes' => []
            ];
        }

        $new_note = [
            'id' => uniqid('tn_', true),
            'kind' => $kind,
            'note' => $note,
            'source' => $source,
            'trainer_name' => $trainer_name,
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
    $kind = isset($_POST['kind']) ? wp_unslash($_POST['kind']) : 'note';

    $result = bricks_tracker_add_note($horse_name, $note, [
        'race_date' => isset($_POST['race_date']) ? wp_unslash($_POST['race_date']) : '',
        'race_time' => isset($_POST['race_time']) ? wp_unslash($_POST['race_time']) : '',
        'course' => isset($_POST['course']) ? wp_unslash($_POST['course']) : '',
        'race_id' => isset($_POST['race_id']) ? wp_unslash($_POST['race_id']) : '',
        'kind' => $kind,
        'source' => isset($_POST['source']) ? wp_unslash($_POST['source']) : '',
        'trainer_name' => isset($_POST['trainer_name']) ? wp_unslash($_POST['trainer_name']) : '',
    ]);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }

    $horse_name_clean = sanitize_text_field($horse_name);
    $note_payload = fhor_tracker_note_to_array($result);

    wp_send_json_success([
        'message' => $note_payload['kind'] === 'trainer_quote' ? 'Trainer quote saved.' : 'Tracker note saved.',
        'note' => $note_payload,
        'html' => fhor_tracker_render_note_item_html($result, $horse_name_clean, 'inline'),
        'flag_html' => fhor_tracker_render_latest_flag_html($result),
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

if (!function_exists('bricks_tracker_should_load_frontend_assets')) {
    function bricks_tracker_should_load_frontend_assets() {
        $has_route_match = false;
        if (function_exists('bricks_request_uri_contains')) {
            $has_route_match = bricks_request_uri_contains([
                '/my-tracker',
                '/points-backtest',
                '/system-builder',
                '/my-qualifiers',
                '/today-picks',
                '/admin-pnl',
                '/race/',
                '/horse-history/',
                '/race-comments/',
                '/daily',
                '/speed',
                '/tracks',
                '/racecourses',
                '/festivals',
                '/trainer-cycles',
                '/trainers/',
            ]);
        }

        $has_shortcode_match = false;
        if (function_exists('bricks_current_post_has_shortcode')) {
            $has_shortcode_match = bricks_current_post_has_shortcode([
                'my_tracker_dashboard',
                'race_table',
                'race_table_full',
                'racecourse_guide',
                'racecourse_guide_static',
                'racecourse_guide_card',
                'racecourse_index',
                'racing_festivals_index',
                'racing_festival_hub',
                'speed_performance_table',
                'horse_history',
                'race_comment_history',
                'race_detail',
                'points_backtest',
                'points_today_picks',
                'admin_pnl_dashboard',
                'trainer_cycles_dashboard',
                'trainer_cycles_profile',
            ]);
        }

        return (
            get_query_var('race_id') ||
            get_query_var('runner_id') ||
            get_query_var('horse_name') ||
            get_query_var('race_comment_id') ||
            get_query_var('my_tracker_page') ||
            get_query_var('my_points_backtest') ||
            get_query_var('my_today_picks_page') ||
            get_query_var('track_slug') ||
            get_query_var('tracks_index') ||
            get_query_var('racecourses_index') ||
            get_query_var('racecourses_region') ||
            get_query_var('festivals_index') ||
            get_query_var('festival_slug') ||
            get_query_var('fhor_trainer_cycles') ||
            get_query_var('fhor_trainer_slug') ||
            $has_route_match ||
            $has_shortcode_match
        );
    }
}

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

        return bricks_tracker_sort_notes_newest($data[$horse_key]['notes']);
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
            'show_bet_tracker' => false,
            'wrapper_class' => '',
            'compact' => false
        ]);

        $notes = is_user_logged_in() ? bricks_tracker_get_notes_for_horse($horse_name, get_current_user_id()) : [];
        $count = count($notes);
        $latest = $count > 0 ? bricks_tracker_pick_latest_for_context($notes, $race_meta) : null;

        $panel_id = 'tracker-panel-' . md5($horse_name . '|' . maybe_serialize($race_meta) . '|' . wp_rand(1000, 9999));
        $button_text = $count > 0 ? 'My Tracker (' . $count . ')' : 'My Tracker';
        $notes_html = '';

        if ($count > 0) {
            foreach ($notes as $entry) {
                $notes_html .= fhor_tracker_render_note_item_html($entry, $horse_name, 'inline');
            }
        } else {
            $notes_html = '<div class="fhor-tracker-empty tracker-empty">No notes yet for this horse.</div>';
        }

        $latest_html = '';
        if ($args['show_latest_flag'] && $latest) {
            $latest_html = fhor_tracker_render_latest_flag_html($latest);
        }

        $default_trainer = fhor_tracker_sanitize_trainer_name($race_meta['trainer_name'] ?? '');
        $source_options_html = '<option value="">Source (optional)</option>';
        foreach (fhor_tracker_quote_sources() as $source_label) {
            $source_options_html .= '<option value="' . esc_attr($source_label) . '">' . esc_html($source_label) . '</option>';
        }

        $widget_html = '<div class="tracker-inline-widget fhor-tracker-widget ' . esc_attr($args['wrapper_class']) . '">
            ' . $latest_html;

        if (!is_user_logged_in()) {
            $widget_html .= '<span class="fhor-tracker-guest-message tracker-guest-message">Log in to use My Tracker</span></div>';
            return $widget_html;
        }

        $action_buttons = '<button type="button" class="fhor-tracker-toggle-btn tracker-toggle-btn tracker-compact-btn" data-target="#' . esc_attr($panel_id) . '">' . esc_html($button_text) . '</button>';
        if (!empty($args['show_bet_tracker']) && function_exists('fhor_bt_log_button_html')) {
            $forecast_odds = isset($race_meta['forecast_odds']) ? $race_meta['forecast_odds'] : '';
            $action_buttons .= fhor_bt_log_button_html([
                'horse' => $horse_name,
                'course' => $race_meta['course'] ?? '',
                'time' => $race_meta['race_time'] ?? '',
                'date' => $race_meta['race_date'] ?? '',
                'odds' => $forecast_odds,
                'label' => 'Add to Bet Tracker',
                'class' => 'fhor-bt-log--race',
            ]);
        }
        $widget_html .= '<div class="tracker-inline-actions">' . $action_buttons . '</div>
            <div id="' . esc_attr($panel_id) . '" class="fhor-tracker-panel tracker-panel" style="display:none;">
                <div class="fhor-tracker-notes-list tracker-notes-list">' . $notes_html . '</div>
                <div class="fhor-tracker-composer">
                    <div class="fhor-tracker-kind-toggle" role="radiogroup" aria-label="Entry type">
                        <label><input type="radio" name="' . esc_attr($panel_id) . '-kind" class="fhor-tracker-kind-input" value="note" checked> General note</label>
                        <label><input type="radio" name="' . esc_attr($panel_id) . '-kind" class="fhor-tracker-kind-input" value="trainer_quote"> Trainer quote</label>
                    </div>
                    <div class="fhor-tracker-quote-fields">
                        <input type="text" class="fhor-tracker-trainer-input" value="' . esc_attr($default_trainer) . '" placeholder="Trainer name (optional)" maxlength="100">
                        <select class="fhor-tracker-source-select">' . $source_options_html . '</select>
                    </div>
                    <textarea class="fhor-tracker-note-input tracker-note-input" rows="3" placeholder="Add your note about this horse…"></textarea>
                    <button type="button" class="fhor-tracker-save-btn tracker-save-btn"
                        data-horse-name="' . esc_attr($horse_name) . '"
                        data-race-id="' . esc_attr($race_meta['race_id'] ?? '') . '"
                        data-race-date="' . esc_attr($race_meta['race_date'] ?? '') . '"
                        data-race-time="' . esc_attr($race_meta['race_time'] ?? '') . '"
                        data-course="' . esc_attr($race_meta['course'] ?? '') . '"
                        data-default-trainer="' . esc_attr($default_trainer) . '">Save</button>
                </div>
            </div>
        </div>';

        return $widget_html;
    }
}

function bricks_tracker_inline_js() {
    if (is_admin() || !bricks_tracker_should_load_frontend_assets()) {
        return;
    }
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
            const count = $panel.find('.fhor-tracker-note-item').length;
            $btn.text(count > 0 ? ('My Tracker (' + count + ')') : 'My Tracker');
        }

        function syncTrackerComposer($panel) {
            const isQuote = $panel.find('.fhor-tracker-kind-input:checked').val() === 'trainer_quote';
            const $quoteFields = $panel.find('.fhor-tracker-quote-fields');
            const $input = $panel.find('.fhor-tracker-note-input');
            if (isQuote) {
                $quoteFields.addClass('is-visible');
                $input.attr('placeholder', 'Paste or type what the trainer said…');
            } else {
                $quoteFields.removeClass('is-visible');
                $input.attr('placeholder', 'Add your note about this horse…');
            }
        }

        function applyTrackerFlag($widget, flagHtml) {
            if (!flagHtml) return;
            let $flag = $widget.find('.fhor-tracker-flag, .tracker-flag').first();
            if (!$flag.length) {
                $flag = $('<div></div>');
                $widget.prepend($flag);
            }
            $flag.replaceWith($(flagHtml));
        }

        $(document).on('change', '.fhor-tracker-kind-input', function() {
            syncTrackerComposer($(this).closest('.fhor-tracker-panel, .tracker-panel'));
        });

        $(document).on('click', '.tracker-toggle-btn', function() {
            const target = $(this).data('target');
            if (target) {
                const $panel = $(target);
                $panel.slideToggle(150, function() {
                    if ($panel.is(':visible')) {
                        syncTrackerComposer($panel);
                    }
                });
            }
        });

        $(document).on('click', '.tracker-save-btn', function() {
            const cfg = trackerConfig();
            if (!cfg.is_logged_in) {
                alert('Please log in to use My Tracker.');
                return;
            }

            const $button = $(this);
            const $panel = $button.closest('.fhor-tracker-panel, .tracker-panel');
            const $input = $panel.find('.fhor-tracker-note-input');
            const note = ($input.val() || '').trim();
            if (!note) {
                alert('Please add some text first.');
                return;
            }

            const kind = $panel.find('.fhor-tracker-kind-input:checked').val() || 'note';
            const trainerName = ($panel.find('.fhor-tracker-trainer-input').val() || '').trim();
            const source = $panel.find('.fhor-tracker-source-select').val() || '';

            $button.prop('disabled', true).text('Saving…');

            $.post(cfg.ajax_url, {
                action: 'bricks_add_tracker_note',
                nonce: cfg.tracker_nonce,
                horse_name: $button.data('horse-name'),
                race_id: $button.data('race-id'),
                race_date: $button.data('race-date'),
                race_time: $button.data('race-time'),
                course: $button.data('course'),
                kind: kind,
                source: source,
                trainer_name: trainerName,
                note: note
            }).done(function(response) {
                if (!response || !response.success) {
                    const msg = response && response.data && response.data.message ? response.data.message : 'Failed to save.';
                    alert(msg);
                    return;
                }
                const $list = $panel.find('.fhor-tracker-notes-list');
                $list.find('.fhor-tracker-empty, .tracker-empty').remove();
                if (response.data && response.data.html) {
                    $list.prepend(response.data.html);
                }
                $input.val('');
                $panel.find('.fhor-tracker-kind-input[value="note"]').prop('checked', true);
                const defaultTrainer = $button.data('default-trainer') || '';
                $panel.find('.fhor-tracker-trainer-input').val(defaultTrainer);
                $panel.find('.fhor-tracker-source-select').val('');
                syncTrackerComposer($panel);
                updateTrackerButtonCount($panel);
                const $widget = $panel.closest('.tracker-inline-widget');
                if (response.data && response.data.flag_html) {
                    applyTrackerFlag($widget, response.data.flag_html);
                }
            }).fail(function() {
                alert('Failed to save. Please try again.');
            }).always(function() {
                $button.prop('disabled', false).text('Save');
            });
        });

        $(document).on('click', '.tracker-delete-btn', function() {
            const cfg = trackerConfig();
            if (!cfg.is_logged_in) {
                alert('Please log in to use My Tracker.');
                return;
            }
            if (!confirm('Delete this entry?')) {
                return;
            }

            const $button = $(this);
            const $item = $button.closest('.fhor-tracker-note-item');
            const $panel = $button.closest('.fhor-tracker-panel, .tracker-panel');
            $button.prop('disabled', true).text('Deleting…');

            $.post(cfg.ajax_url, {
                action: 'bricks_delete_tracker_note',
                nonce: cfg.tracker_nonce,
                horse_name: $button.data('horse-name'),
                note_id: $button.data('note-id')
            }).done(function(response) {
                if (!response || !response.success) {
                    const msg = response && response.data && response.data.message ? response.data.message : 'Failed to delete.';
                    alert(msg);
                    return;
                }
                $item.remove();
                if ($panel.length) {
                    const $list = $panel.find('.fhor-tracker-notes-list');
                    if (!$list.find('.fhor-tracker-note-item').length) {
                        $list.html('<div class="fhor-tracker-empty tracker-empty">No notes yet for this horse.</div>');
                    }
                    updateTrackerButtonCount($panel);
                } else {
                    window.location.reload();
                }
            }).fail(function() {
                alert('Failed to delete. Please try again.');
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
    if (is_admin() || !bricks_tracker_should_load_frontend_assets()) {
        return;
    }
    ?>
    <style>
    .tracker-alert-strip { display: block; }
    </style>
    <?php
}
add_action('wp_head', 'bricks_tracker_inline_styles', 1000);

function bricks_tracker_floating_quick_link() {
    if (
        !is_user_logged_in() ||
        is_admin() ||
        !bricks_tracker_should_load_frontend_assets() ||
        get_query_var('my_tracker_page') ||
        get_query_var('my_points_backtest') ||
        get_query_var('fhor_system_builder') ||
        get_query_var('fhor_my_qualifiers') ||
        get_query_var('fhor_bet_tracker') ||
        get_query_var('fhor_trainer_cycles') ||
        get_query_var('fhor_trainer_slug') ||
        (function_exists('fhor_sb_is_request') && fhor_sb_is_request()) ||
        (function_exists('fhor_sb_is_qualifiers_request') && fhor_sb_is_qualifiers_request()) ||
        (function_exists('fhor_bt_is_request') && fhor_bt_is_request()) ||
        (function_exists('fhor_tc_is_dashboard_request') && fhor_tc_is_dashboard_request()) ||
        (function_exists('fhor_tc_is_trainer_profile_request') && fhor_tc_is_trainer_profile_request())
    ) {
        return;
    }
    ?>
    <div class="fhor-fab" id="fhorFabMenu">
        <div class="fhor-fab__links" id="fhorFabLinks">
            <a class="fhor-fab__link fhor-fab__link--tracker" href="<?php echo esc_url(home_url('/my-tracker/')); ?>" title="Open My Tracker">📝 My Tracker</a>
            <?php if (function_exists('fhor_sb_user_can_access') && fhor_sb_user_can_access()): ?>
            <a class="fhor-fab__link fhor-fab__link--builder" href="<?php echo esc_url(home_url('/system-builder/')); ?>" title="Open System Builder">📐 System Builder</a>
            <a class="fhor-fab__link fhor-fab__link--qualifiers" href="<?php echo esc_url(home_url('/my-qualifiers/')); ?>" title="Open My Daily Qualifiers">🔔 My Qualifiers</a>
            <a class="fhor-fab__link fhor-fab__link--bets" href="<?php echo esc_url(home_url('/bet-tracker/')); ?>" title="Open Bet Tracker">📈 Bet Tracker</a>
            <?php endif; ?>
            <?php if (function_exists('bricks_user_can_access_points_backtest') && bricks_user_can_access_points_backtest()): ?>
            <a class="fhor-fab__link fhor-fab__link--picks" href="<?php echo esc_url(home_url('/today-picks/')); ?>" title="Open Today's Picks (audit sheet)">📋 Today's Picks</a>
            <a class="fhor-fab__link fhor-fab__link--backtest" href="<?php echo esc_url(home_url('/points-backtest/')); ?>" title="Open Points Backtest">📊 Points Backtest</a>
            <?php endif; ?>
            <?php
            $tc_fab_url = function_exists('fhor_tc_dashboard_url')
                ? fhor_tc_dashboard_url()
                : home_url('/trainer-cycles/');
            ?>
            <a class="fhor-fab__link fhor-fab__link--trainer-cycles" href="<?php echo esc_url($tc_fab_url); ?>" title="Seasonal hot and cold yards by calendar month">📅 Trainer Cycles</a>
            <?php if (current_user_can('manage_options')): ?>
            <a class="fhor-fab__link fhor-fab__link--admin" href="<?php echo esc_url(home_url('/admin-pnl/')); ?>" title="Open Admin P&L">💷 Admin P&amp;L</a>
            <?php endif; ?>
        </div>
        <button type="button" class="fhor-fab__toggle" id="fhorFabToggle" aria-expanded="false" aria-controls="fhorFabLinks" title="Quick links">☰</button>
    </div>
    <script>
    (function () {
        var root = document.getElementById('fhorFabMenu');
        var toggle = document.getElementById('fhorFabToggle');
        if (!root || !toggle) return;
        toggle.addEventListener('click', function () {
            var open = root.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.textContent = open ? '✕' : '☰';
        });
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target) && root.classList.contains('is-open')) {
                root.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.textContent = '☰';
            }
        });
    })();
    </script>
    <?php
}
add_action('wp_footer', 'bricks_tracker_floating_quick_link', 1001);

function bricks_my_tracker_dashboard_shortcode($atts = []) {
    if (!is_user_logged_in()) {
        return '<div class="fhor-tracker-page"><div class="fhor-tracker-login-card">
            <h2>My Tracker</h2>
            <p>Please log in to view and manage your notes and trainer quotes.</p>
        </div></div>';
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
        $quote_count = 0;
        $note_count = 0;
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
            $kind = fhor_tracker_get_entry_kind($note_entry);

            if ($note_id === '' || $note_text === '') {
                continue;
            }

            if ($kind === 'trainer_quote') {
                $quote_count++;
            } else {
                $note_count++;
            }

            $note_rows[] = $note_entry;
        }

        if (empty($note_rows)) {
            continue;
        }

        $note_rows = bricks_tracker_sort_notes_newest($note_rows);
        $latest = $note_rows[0];
        $type_parts = [];
        if ($note_count > 0) {
            $type_parts[] = $note_count . ' note' . ($note_count === 1 ? '' : 's');
        }
        if ($quote_count > 0) {
            $type_parts[] = $quote_count . ' quote' . ($quote_count === 1 ? '' : 's');
        }

        $rows[] = [
            'horse_name' => $horse_name,
            'notes_count' => count($note_rows),
            'type_summary' => implode(' · ', $type_parts),
            'latest_created_at' => $latest['created_at'] ?? '',
            'latest_course' => $latest['course'] ?? '',
            'latest_race_date' => $latest['race_date'] ?? '',
            'latest_race_time' => $latest['race_time'] ?? '',
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
    <div class="fhor-tracker-page">
    <div class="my-tracker-dashboard">
        <div class="my-tracker-header fhor-page-header">
            <h1>My Tracker</h1>
            <p>Your private notes and trainer quotes, grouped by horse.</p>
        </div>

        <?php if (empty($rows)): ?>
            <div class="my-tracker-empty">
                No entries yet. Open a racecard, expand <strong>My Tracker</strong> on a runner, and save a note or trainer quote.
            </div>
        <?php else: ?>
            <div class="my-tracker-table-wrap">
                <table class="my-tracker-table">
                    <thead>
                        <tr>
                            <th>Horse</th>
                            <th>Latest</th>
                            <th>Race context</th>
                            <th>Types</th>
                            <th>Entries</th>
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
                            <tr class="tracker-row">
                                <td class="tracker-cell tracker-cell--horse" data-label="Horse"><?php echo esc_html($row['horse_name']); ?></td>
                                <td class="tracker-cell tracker-cell--date" data-label="Latest"><?php echo esc_html($display_date); ?></td>
                                <td class="tracker-cell tracker-cell--course" data-label="Race context"><?php echo esc_html($meta_course ?: '-'); ?></td>
                                <td class="tracker-cell tracker-cell--types" data-label="Types"><span class="fhor-tracker-type-summary"><?php echo esc_html($row['type_summary']); ?></span></td>
                                <td class="tracker-cell tracker-cell--comments" data-label="Entries">
                                    <details class="my-tracker-details">
                                        <summary>
                                            View (<?php echo esc_html($row['notes_count']); ?>)
                                        </summary>
                                        <div class="my-tracker-notes-dropdown">
                                            <?php foreach ($row['notes'] as $note_row): ?>
                                                <?php echo fhor_tracker_render_note_item_html($note_row, $row['horse_name'], 'dashboard'); ?>
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
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('my_tracker_dashboard', 'bricks_my_tracker_dashboard_shortcode');

function bricks_add_my_tracker_menu_item($items, $args) {
    if (is_admin()) {
        return $items;
    }

    $tracker_url = home_url('/my-tracker/');
    $today_picks_url = home_url('/today-picks/');
    $points_url = home_url('/points-backtest/');
    if (strpos($items, $tracker_url) !== false) {
        if (strpos($items, $points_url) !== false && strpos($items, $today_picks_url) !== false) {
            return $items;
        }
    }

    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    $active_class = (strpos($current_url, '/my-tracker') !== false) ? ' current-menu-item current_page_item' : '';
    $active_today_class = (strpos($current_url, '/today-picks') !== false) ? ' current-menu-item current_page_item' : '';
    $active_points_class = (strpos($current_url, '/points-backtest') !== false) ? ' current-menu-item current_page_item' : '';

    $items .= '<li class="menu-item menu-item-type-custom menu-item-my-tracker' . esc_attr($active_class) . '">
        <a href="' . esc_url($tracker_url) . '">My Tracker</a>
    </li>';
    if (function_exists('bricks_user_can_access_points_backtest') && bricks_user_can_access_points_backtest()) {
        $items .= '<li class="menu-item menu-item-type-custom menu-item-today-picks' . esc_attr($active_today_class) . '">
            <a href="' . esc_url($today_picks_url) . '">Today\'s Picks</a>
        </li>';
        $items .= '<li class="menu-item menu-item-type-custom menu-item-points-backtest' . esc_attr($active_points_class) . '">
            <a href="' . esc_url($points_url) . '">Points Backtest</a>
        </li>';
    }

    return $items;
}
add_filter('wp_nav_menu_items', 'bricks_add_my_tracker_menu_item', 20, 2);

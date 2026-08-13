<?php
/**
 * Racecourse lead-capture banner → Brevo list (FREE #3).
 *
 * Configure via wp-config.php:
 *   define('FHOR_BREVO_API_KEY', 'xkeysib-...');
 *   define('FHOR_BREVO_LIST_ID', 123); // numeric ID of list "FREE #3"
 *
 * Or Settings → Fhorsite Lead Capture in wp-admin.
 */

if (!function_exists('fhor_brevo_api_key')) {
    function fhor_brevo_api_key() {
        if (defined('FHOR_BREVO_API_KEY') && FHOR_BREVO_API_KEY !== '') {
            return (string) FHOR_BREVO_API_KEY;
        }
        $opt = (string) get_option('fhor_brevo_api_key', '');
        return (string) apply_filters('fhor_brevo_api_key', $opt);
    }
}

if (!function_exists('fhor_brevo_list_id')) {
    /**
     * Numeric Brevo list ID for "FREE #3".
     */
    function fhor_brevo_list_id() {
        if (defined('FHOR_BREVO_LIST_ID') && intval(FHOR_BREVO_LIST_ID) > 0) {
            return intval(FHOR_BREVO_LIST_ID);
        }
        $opt = intval(get_option('fhor_brevo_list_id', 0));
        return intval(apply_filters('fhor_brevo_list_id', $opt));
    }
}

if (!function_exists('fhor_brevo_is_configured')) {
    function fhor_brevo_is_configured() {
        return fhor_brevo_api_key() !== '' && fhor_brevo_list_id() > 0;
    }
}

if (!function_exists('fhor_lead_capture_register_settings')) {
    function fhor_lead_capture_register_settings() {
        register_setting('fhor_lead_capture', 'fhor_brevo_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('fhor_lead_capture', 'fhor_brevo_list_id', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]);
    }
}
add_action('admin_init', 'fhor_lead_capture_register_settings');

if (!function_exists('fhor_lead_capture_add_settings_page')) {
    function fhor_lead_capture_add_settings_page() {
        add_options_page(
            'Fhorsite Lead Capture',
            'Fhorsite Lead Capture',
            'manage_options',
            'fhor-lead-capture',
            'fhor_lead_capture_render_settings_page'
        );
    }
}
add_action('admin_menu', 'fhor_lead_capture_add_settings_page');

if (!function_exists('fhor_lead_capture_render_settings_page')) {
    function fhor_lead_capture_render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $has_const_key = defined('FHOR_BREVO_API_KEY') && FHOR_BREVO_API_KEY !== '';
        $has_const_list = defined('FHOR_BREVO_LIST_ID') && intval(FHOR_BREVO_LIST_ID) > 0;
        ?>
        <div class="wrap">
            <h1>Fhorsite Lead Capture (Brevo)</h1>
            <p>Submissions from racecourse pages are added to your Brevo list. Use list <strong>FREE #3</strong> — paste its numeric List ID below (Brevo → Contacts → Lists → open the list → ID in the URL or details).</p>
            <form method="post" action="options.php">
                <?php settings_fields('fhor_lead_capture'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fhor_brevo_api_key">Brevo API key</label></th>
                        <td>
                            <?php if ($has_const_key): ?>
                                <p><em>Using <code>FHOR_BREVO_API_KEY</code> from <code>wp-config.php</code> (recommended).</em></p>
                            <?php else: ?>
                                <input type="password" class="regular-text" id="fhor_brevo_api_key" name="fhor_brevo_api_key" value="<?php echo esc_attr(get_option('fhor_brevo_api_key', '')); ?>" autocomplete="off" />
                                <p class="description">Create under Brevo → SMTP &amp; API → API keys. Prefer defining <code>FHOR_BREVO_API_KEY</code> in wp-config instead of storing here.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fhor_brevo_list_id">List ID (FREE #3)</label></th>
                        <td>
                            <?php if ($has_const_list): ?>
                                <p><em>Using <code>FHOR_BREVO_LIST_ID</code> = <?php echo esc_html((string) intval(FHOR_BREVO_LIST_ID)); ?> from wp-config.</em></p>
                            <?php else: ?>
                                <input type="number" min="1" class="small-text" id="fhor_brevo_list_id" name="fhor_brevo_list_id" value="<?php echo esc_attr((string) intval(get_option('fhor_brevo_list_id', 0))); ?>" />
                                <p class="description">Numeric ID only (not the list name). Example: <code>42</code>.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php if (!$has_const_key || !$has_const_list): ?>
                    <?php submit_button('Save settings'); ?>
                <?php endif; ?>
            </form>
            <p>Status: <?php echo fhor_brevo_is_configured() ? '<strong style="color:#15803d;">Configured — form will submit to Brevo</strong>' : '<strong style="color:#b45309;">Not configured — banner still shows; submissions return a setup error</strong>'; ?></p>
        </div>
        <?php
    }
}

if (!function_exists('fhor_lead_capture_render_banner_html')) {
    /**
     * Lead-capture box for a single racecourse page.
     *
     * @param array{course?:string,display?:string,slug?:string}|string $context_or_display
     */
    function fhor_lead_capture_render_banner_html($context_or_display = []) {
        if (is_string($context_or_display)) {
            $display = trim($context_or_display);
            $slug = sanitize_title($display);
            $course = $display;
        } else {
            $context = is_array($context_or_display) ? $context_or_display : [];
            $display = trim((string) ($context['display'] ?? $context['course'] ?? ''));
            $slug = sanitize_title((string) ($context['slug'] ?? $display));
            $course = trim((string) ($context['course'] ?? $display));
        }

        if ($display === '') {
            $display = 'this';
        }

        $headline = sprintf(
            'Want live speed ratings and course bias analysis for today\'s %s card? Join our free database to get instant access.',
            $display
        );

        $form_id = 'fhor-lead-' . ($slug !== '' ? $slug : 'course');

        ob_start();
        ?>
        <aside class="fhor-lead-capture" aria-label="Free database signup">
            <div class="fhor-lead-capture__inner">
                <p class="fhor-lead-capture__headline"><?php echo esc_html($headline); ?></p>
                <form class="fhor-lead-capture__form" id="<?php echo esc_attr($form_id); ?>" data-fhor-lead-form method="post" novalidate>
                    <?php wp_nonce_field('fhor_lead_capture', 'fhor_lead_nonce'); ?>
                    <input type="hidden" name="action" value="fhor_lead_capture_subscribe" />
                    <input type="hidden" name="course" value="<?php echo esc_attr($course); ?>" />
                    <input type="hidden" name="course_display" value="<?php echo esc_attr($display); ?>" />
                    <input type="hidden" name="course_slug" value="<?php echo esc_attr($slug); ?>" />
                    <input type="text" name="fhor_website" value="" tabindex="-1" autocomplete="off" class="fhor-lead-capture__hp" aria-hidden="true" />

                    <div class="fhor-lead-capture__fields">
                        <label class="fhor-lead-capture__field">
                            <span class="screen-reader-text">Username</span>
                            <input type="text" name="username" required maxlength="80" placeholder="Username" autocomplete="username" />
                        </label>
                        <label class="fhor-lead-capture__field">
                            <span class="screen-reader-text">Email Address</span>
                            <input type="email" name="email" required maxlength="190" placeholder="Email Address" autocomplete="email" />
                        </label>
                        <button type="submit" class="fhor-lead-capture__submit">Join free</button>
                    </div>
                    <p class="fhor-lead-capture__status" role="status" aria-live="polite" hidden></p>
                    <p class="fhor-lead-capture__fineprint">Free signup. Unsubscribe anytime. We’ll email racecourse ratings updates.</p>
                </form>
            </div>
        </aside>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('fhor_lead_capture_styles')) {
    function fhor_lead_capture_styles() {
        return '
        .fhor-lead-capture{margin:.75rem 0 1rem;padding:1rem 1.1rem;border:1px solid #bbf7d0;border-radius:12px;background:linear-gradient(135deg,#f0fdf4 0%,#fff 70%);box-shadow:0 2px 10px rgba(15,23,42,.04)}
        .fhor-lead-capture__headline{margin:0 0 .85rem;font-size:clamp(.98rem,2.6vw,1.12rem);font-weight:700;line-height:1.4;color:#14532d}
        .fhor-lead-capture__fields{display:flex;flex-wrap:wrap;gap:.55rem;align-items:stretch}
        .fhor-lead-capture__field{flex:1 1 140px;min-width:0}
        .fhor-lead-capture__field input{width:100%;box-sizing:border-box;padding:.7rem .85rem;border:1px solid #cbd5e1;border-radius:8px;font-size:1rem;color:#0f172a;background:#fff}
        .fhor-lead-capture__field input:focus{outline:none;border-color:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,.18)}
        .fhor-lead-capture__submit{flex:0 0 auto;padding:.7rem 1.15rem;border:none;border-radius:8px;background:#16a34a;color:#fff;font-weight:700;font-size:.95rem;cursor:pointer;white-space:nowrap}
        .fhor-lead-capture__submit:hover,.fhor-lead-capture__submit:focus-visible{background:#15803d;outline:none}
        .fhor-lead-capture__submit:disabled{opacity:.65;cursor:wait}
        .fhor-lead-capture__status{margin:.65rem 0 0;font-size:.9rem;font-weight:600}
        .fhor-lead-capture__status.is-ok{color:#15803d}
        .fhor-lead-capture__status.is-err{color:#b91c1c}
        .fhor-lead-capture__fineprint{margin:.55rem 0 0;font-size:.75rem;color:#64748b;line-height:1.4}
        .fhor-lead-capture__hp{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;opacity:0!important}
        @media (max-width:560px){
            .fhor-lead-capture__submit{width:100%}
        }
        ';
    }
}

if (!function_exists('fhor_lead_capture_enqueue_assets')) {
    function fhor_lead_capture_enqueue_assets() {
        $is_track = function_exists('bricks_request_uri_contains')
            && bricks_request_uri_contains(['/racecourses/', '/tracks/']);
        $is_qv = (bool) get_query_var('track_slug');
        if (!$is_track && !$is_qv) {
            return;
        }
        // Skip directory / region hubs — only single course URLs have a track_slug that isn't a region.
        if (function_exists('bricks_track_is_directory_index_request') && bricks_track_is_directory_index_request()) {
            return;
        }
        if (function_exists('bricks_track_is_region_hub_request') && bricks_track_is_region_hub_request()) {
            return;
        }

        wp_register_script('fhor-lead-capture', false, [], '1.0.0', true);
        wp_enqueue_script('fhor-lead-capture');
        wp_localize_script('fhor-lead-capture', 'fhorLeadCapture', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
        wp_add_inline_script('fhor-lead-capture', <<<'JS'
(function () {
  function setStatus(form, message, ok) {
    var el = form.querySelector('.fhor-lead-capture__status');
    if (!el) return;
    el.hidden = !message;
    el.textContent = message || '';
    el.classList.toggle('is-ok', !!ok);
    el.classList.toggle('is-err', !ok && !!message);
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || !form.matches || !form.matches('[data-fhor-lead-form]')) return;
    event.preventDefault();

    var btn = form.querySelector('.fhor-lead-capture__submit');
    var data = new FormData(form);
    if (btn) btn.disabled = true;
    setStatus(form, 'Submitting…', true);

    fetch(fhorLeadCapture.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: data
    }).then(function (res) {
      return res.json().then(function (json) {
        return { ok: res.ok, json: json };
      });
    }).then(function (result) {
      var json = result.json || {};
      if (json.success) {
        setStatus(form, (json.data && json.data.message) || 'You\'re in — check your inbox.', true);
        form.reset();
      } else {
        setStatus(form, (json.data && json.data.message) || 'Something went wrong. Please try again.', false);
      }
    }).catch(function () {
      setStatus(form, 'Network error. Please try again.', false);
    }).finally(function () {
      if (btn) btn.disabled = false;
    });
  });
})();
JS
        );
    }
}
add_action('wp_enqueue_scripts', 'fhor_lead_capture_enqueue_assets', 40);

if (!function_exists('fhor_brevo_create_or_update_contact')) {
    /**
     * @return array{ok:bool,message:string,code?:int}
     */
    function fhor_brevo_create_or_update_contact($email, $username, $meta = []) {
        $api_key = fhor_brevo_api_key();
        $list_id = fhor_brevo_list_id();

        if ($api_key === '' || $list_id <= 0) {
            return [
                'ok' => false,
                'message' => 'Signup is temporarily unavailable. Please try again later.',
                'code' => 503,
            ];
        }

        $attributes = [
            'FIRSTNAME' => $username,
            'USERNAME' => $username,
        ];
        if (!empty($meta['course_display'])) {
            $attributes['COURSE'] = (string) $meta['course_display'];
        }
        if (!empty($meta['course_slug'])) {
            $attributes['COURSE_SLUG'] = (string) $meta['course_slug'];
        }
        $attributes['SOURCE'] = 'racecourse_lead_capture';

        $payload = [
            'email' => $email,
            'attributes' => $attributes,
            'listIds' => [$list_id],
            'updateEnabled' => true,
        ];

        $response = wp_remote_post('https://api.brevo.com/v3/contacts', [
            'timeout' => 15,
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'api-key' => $api_key,
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'message' => 'Could not reach the mailing service. Please try again.',
                'code' => 502,
            ];
        }

        $code = intval(wp_remote_retrieve_response_code($response));
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        // 201 created, 204 updated (with updateEnabled), 400 duplicate sometimes returned as already exists.
        if ($code === 201 || $code === 204) {
            return [
                'ok' => true,
                'message' => "Thanks — you're on the free list. Watch your inbox for access details.",
                'code' => $code,
            ];
        }

        // Contact exists: ensure list membership via update endpoint.
        if ($code === 400 && is_array($body) && isset($body['code']) && $body['code'] === 'duplicate_parameter') {
            $encoded = rawurlencode($email);
            $update = wp_remote_request('https://api.brevo.com/v3/contacts/' . $encoded, [
                'method' => 'PUT',
                'timeout' => 15,
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                    'api-key' => $api_key,
                ],
                'body' => wp_json_encode([
                    'attributes' => $attributes,
                    'listIds' => [$list_id],
                ]),
            ]);
            if (!is_wp_error($update)) {
                $update_code = intval(wp_remote_retrieve_response_code($update));
                if ($update_code >= 200 && $update_code < 300) {
                    return [
                        'ok' => true,
                        'message' => "You're already registered — we've refreshed your free-list access.",
                        'code' => $update_code,
                    ];
                }
            }
        }

        $detail = is_array($body) && !empty($body['message']) ? (string) $body['message'] : '';
        return [
            'ok' => false,
            'message' => $detail !== '' ? $detail : 'Signup failed. Please check your details and try again.',
            'code' => $code,
        ];
    }
}

if (!function_exists('fhor_lead_capture_ajax_subscribe')) {
    function fhor_lead_capture_ajax_subscribe() {
        $nonce = isset($_POST['fhor_lead_nonce']) ? sanitize_text_field(wp_unslash($_POST['fhor_lead_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'fhor_lead_capture')) {
            wp_send_json_error(['message' => 'Session expired. Please refresh and try again.'], 403);
        }

        // Honeypot
        $hp = isset($_POST['fhor_website']) ? trim((string) wp_unslash($_POST['fhor_website'])) : '';
        if ($hp !== '') {
            wp_send_json_success(['message' => "Thanks — you're on the free list."]);
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
        $rate_key = 'fhor_lead_' . md5($ip);
        $hits = intval(get_transient($rate_key));
        if ($hits >= 8) {
            wp_send_json_error(['message' => 'Too many attempts. Please wait a few minutes.'], 429);
        }
        set_transient($rate_key, $hits + 1, 15 * MINUTE_IN_SECONDS);

        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $course_display = isset($_POST['course_display']) ? sanitize_text_field(wp_unslash($_POST['course_display'])) : '';
        $course_slug = isset($_POST['course_slug']) ? sanitize_title(wp_unslash($_POST['course_slug'])) : '';

        if ($username === '' || strlen($username) < 2) {
            wp_send_json_error(['message' => 'Please enter a username.'], 400);
        }
        if ($email === '' || !is_email($email)) {
            wp_send_json_error(['message' => 'Please enter a valid email address.'], 400);
        }

        $result = fhor_brevo_create_or_update_contact($email, $username, [
            'course_display' => $course_display,
            'course_slug' => $course_slug,
        ]);

        if (!empty($result['ok'])) {
            wp_send_json_success(['message' => $result['message']]);
        }

        $code = isset($result['code']) ? intval($result['code']) : 400;
        wp_send_json_error(['message' => $result['message']], $code >= 400 ? $code : 400);
    }
}
add_action('wp_ajax_fhor_lead_capture_subscribe', 'fhor_lead_capture_ajax_subscribe');
add_action('wp_ajax_nopriv_fhor_lead_capture_subscribe', 'fhor_lead_capture_ajax_subscribe');

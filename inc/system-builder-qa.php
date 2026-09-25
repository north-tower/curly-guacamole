<?php
/**
 * System Builder QA lab — guided testing page for admins.
 *
 * URL: /system-builder-qa/   Shortcode: [system_builder_qa]
 */

if (!function_exists('fhor_sb_qa_url')) {
    function fhor_sb_qa_url() {
        return home_url('/system-builder-qa/');
    }
}

if (!function_exists('fhor_sb_qa_user_can_view')) {
    function fhor_sb_qa_user_can_view() {
        if (defined('FHOR_SB_QA_OPEN') && FHOR_SB_QA_OPEN) {
            return is_user_logged_in() && fhor_sb_user_can_access();
        }
        return current_user_can('manage_options');
    }
}

if (!function_exists('fhor_sb_qa_is_request')) {
    function fhor_sb_qa_is_request() {
        if (get_query_var('fhor_system_builder_qa')) {
            return true;
        }
        return (bool) preg_match('#/system-builder-qa(?:/|$)#i', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    }
}

if (!function_exists('fhor_sb_qa_is_active')) {
    function fhor_sb_qa_is_active() {
        if (fhor_sb_qa_is_request()) {
            return true;
        }
        return function_exists('bricks_current_post_has_shortcode')
            && bricks_current_post_has_shortcode(['system_builder_qa']);
    }
}

if (!function_exists('fhor_sb_qa_scenarios')) {
    /**
     * @return array<string, array{title:string,summary:string,steps:array<int,string>,filters:array<string,mixed>}>
     */
    function fhor_sb_qa_scenarios() {
        return [
            'smoke_backtest' => [
                'title' => 'Smoke — historic ROI',
                'summary' => 'Short lookback, tight FSr rank. Confirms backtest AJAX and sample table.',
                'steps' => [
                    'Click Load scenario, then Run System.',
                    'Expect success within ~30s; qualifiers > 0 on busy weeks.',
                    'Industry SP ROI and bet count should look plausible (not NaN).',
                    'Run again — identical ROI and qualifier count.',
                ],
                'filters' => [
                    'days' => '14',
                    'bet' => 'win',
                    'pick_mode' => 'all',
                    'country' => ['England'],
                    'race_type' => ['Flat'],
                    'fsr_rank_max' => '3',
                ],
            ],
            'sprint_dosage' => [
                'title' => 'Sprint speed + dosage',
                'summary' => '≤6f with high DI/CD band; sample table should show DI/CD columns.',
                'steps' => [
                    'Load scenario and Run System.',
                    'Confirm note about dosage-filtered historic ROI.',
                    'Open a sample row horse on a race card — DI/CD should match.',
                    'Find qualifiers — DI/CD columns filled on matches.',
                ],
                'filters' => [
                    'days' => '21',
                    'bet' => 'win',
                    'pick_mode' => 'all',
                    'race_type' => ['Flat'],
                    'dist_f_max' => '6',
                    'di_min' => '2.00',
                    'cd_min' => '0.50',
                    'fsr_rank_max' => '3',
                ],
            ],
            'stamina_dosage' => [
                'title' => 'Staying stamina + dosage',
                'summary' => '≥12f with lower DI/CD; Inf DI should not pass when di_max is set.',
                'steps' => [
                    'Load scenario and Run System.',
                    'Check samples — DI ≤ 1.40 and CD ≤ 0.50 where figures exist.',
                    'Find qualifiers for tomorrow; verify distance titles look like 1m2f+.',
                ],
                'filters' => [
                    'days' => '21',
                    'bet' => 'win',
                    'pick_mode' => 'all',
                    'race_type' => ['Flat'],
                    'dist_f_min' => '12',
                    'di_max' => '1.40',
                    'cd_max' => '0.50',
                    'fsr_rank_max' => '3',
                ],
            ],
            'qualifiers_live' => [
                'title' => 'Live card qualifiers',
                'summary' => 'Maps rules onto today/tomorrow without caring about ROI.',
                'steps' => [
                    'Load scenario, click Find qualifiers.',
                    'Toggle Tomorrow / Today tabs.',
                    'Pick one horse — open race link; confirm FSr rank and DI/CD.',
                ],
                'filters' => [
                    'days' => '90',
                    'bet' => 'win',
                    'pick_mode' => 'all',
                    'fsr_rank_max' => '2',
                    'pts_rank_max' => '3',
                ],
            ],
            'country_handicap' => [
                'title' => 'Country + non-handicap',
                'summary' => 'Regression for England-only hurdle maidens / non-handicaps.',
                'steps' => [
                    'Run System — no Irish courses in sample race column.',
                    'No handicap-only races if race_type excludes them.',
                ],
                'filters' => [
                    'days' => '30',
                    'bet' => 'win',
                    'pick_mode' => 'top_sr',
                    'country' => ['England'],
                    'race_type' => ['Hurdle'],
                    'handicap' => 'no',
                    'field_min' => '8',
                    'field_max' => '16',
                ],
            ],
            'save_roundtrip' => [
                'title' => 'Save & reload',
                'summary' => 'Persist filters including cd_min 0 and dosage fields.',
                'steps' => [
                    'Load scenario, set system name to QA-save-test.',
                    'Save system (without email is fine).',
                    'Reload this page, Load saved system — cd_min 0 and di fields restored.',
                    'Delete test system when done.',
                ],
                'filters' => [
                    'days' => '60',
                    'bet' => 'ew',
                    'pick_mode' => 'all',
                    'cd_min' => '0',
                    'cd_max' => '0.80',
                    'di_min' => '1.00',
                    'di_max' => '3.50',
                    'fsr_min' => '50',
                ],
            ],
        ];
    }
}

if (!function_exists('fhor_sb_qa_checklist')) {
    /**
     * @return array<int, array{id:string,label:string,hint:string}>
     */
    function fhor_sb_qa_checklist() {
        return [
            ['id' => 'ajax_backtest', 'label' => 'Backtest AJAX returns success', 'hint' => 'Network → fhor_sb_backtest'],
            ['id' => 'ajax_qualifiers', 'label' => 'Qualifiers AJAX returns today + tomorrow', 'hint' => 'fhor_sb_qualifiers'],
            ['id' => 'dosage_card_match', 'label' => 'DI/CD matches race card for one horse', 'hint' => 'Same Chefs-de-Race engine'],
            ['id' => 'dosage_sample_cols', 'label' => 'Historic sample shows DI/CD when dosage filtered', 'hint' => 'Sprint/stamina scenarios'],
            ['id' => 'pace_note', 'label' => 'Pace-only rules show live-only note on backtest', 'hint' => 'Set pace zone, run backtest'],
            ['id' => 'save_load', 'label' => 'Saved system reloads all fields', 'hint' => 'Including cd_min 0'],
            ['id' => 'premium_cap', 'label' => 'Lookback respects member tier', 'hint' => 'Free vs premium max days'],
            ['id' => 'unit_node', 'label' => 'node tests/system-builder-filters-test.mjs passes', 'hint' => 'Local CI'],
            ['id' => 'unit_php', 'label' => 'php tests/system-builder-test.php passes', 'hint' => 'Server with PHP CLI'],
        ];
    }
}

if (!function_exists('fhor_sb_qa_run_self_tests')) {
    function fhor_sb_qa_run_self_tests() {
        $results = ['ok' => true, 'tests' => []];

        if (function_exists('bricks_dosage_self_test')) {
            $dosage = bricks_dosage_self_test();
            $results['tests'][] = [
                'name' => 'Dosage engine (Secretariat / Inf / split chef)',
                'ok' => !empty($dosage['passed']),
                'detail' => $dosage['results'] ?? [],
            ];
            if (empty($dosage['passed'])) {
                $results['ok'] = false;
            }
        }

        $raw = ['cd_min' => '0', 'di_min' => '2.00', 'di_max' => '', 'days' => '7'];
        $san = fhor_sb_sanitize_filters($raw);
        $cd_ok = ($san['cd_min'] ?? '') === '0';
        $results['tests'][] = [
            'name' => 'Filter sanitize keeps cd_min = 0',
            'ok' => $cd_ok,
            'detail' => ['cd_min' => $san['cd_min'] ?? null],
        ];
        if (!$cd_ok) {
            $results['ok'] = false;
        }

        $results['tests'][] = [
            'name' => 'Lookback clamp (free tier simulation)',
            'ok' => is_numeric($san['days'] ?? null) && intval($san['days']) >= 7,
            'detail' => ['days' => $san['days'] ?? null],
        ];

        return $results;
    }
}

if (!function_exists('fhor_sb_qa_require_ajax')) {
    function fhor_sb_qa_require_ajax() {
        if (!fhor_sb_qa_user_can_view()) {
            wp_send_json_error(['message' => 'QA lab is restricted.'], 403);
        }
        check_ajax_referer('fhor_sb_qa', 'nonce');
    }
}

if (!function_exists('fhor_sb_qa_ajax_self_tests')) {
    function fhor_sb_qa_ajax_self_tests() {
        fhor_sb_qa_require_ajax();
        wp_send_json_success(fhor_sb_qa_run_self_tests());
    }
}
add_action('wp_ajax_fhor_sb_qa_self_tests', 'fhor_sb_qa_ajax_self_tests');

if (!function_exists('fhor_sb_qa_ajax_parse')) {
    function fhor_sb_qa_ajax_parse() {
        fhor_sb_qa_require_ajax();
        $raw = isset($_POST['filters']) ? wp_unslash($_POST['filters']) : '';
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        $san = fhor_sb_sanitize_filters(is_array($raw) ? $raw : []);
        wp_send_json_success([
            'filters' => $san,
            'needs_dosage' => fhor_sb_needs_dosage($san),
            'needs_pace' => fhor_sb_needs_pace($san),
        ]);
    }
}
add_action('wp_ajax_fhor_sb_qa_parse', 'fhor_sb_qa_ajax_parse');

if (!function_exists('fhor_sb_qa_enqueue')) {
    function fhor_sb_qa_enqueue() {
        if (!fhor_sb_qa_is_active()) {
            return;
        }
        fhor_sb_enqueue();
        $js = get_stylesheet_directory() . '/system-builder-qa.js';
        if (!file_exists($js)) {
            return;
        }
        wp_enqueue_script(
            'fhor-system-builder-qa',
            get_stylesheet_directory_uri() . '/system-builder-qa.js',
            ['fhor-system-builder'],
            filemtime($js),
            true
        );
        wp_localize_script('fhor-system-builder-qa', 'fhorSbQa', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fhor_sb_qa'),
            'scenarios' => fhor_sb_qa_scenarios(),
            'checklist' => fhor_sb_qa_checklist(),
            'builderUrl' => fhor_sb_url(),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'fhor_sb_qa_enqueue', 29);

if (!function_exists('fhor_sb_qa_shortcode')) {
    function fhor_sb_qa_shortcode() {
        if (!fhor_sb_qa_user_can_view()) {
            return '<div class="sb-gate"><h1>System Builder QA</h1><p>This lab is for site administrators (or enable <code>FHOR_SB_QA_OPEN</code> on staging).</p></div>';
        }
        if (!is_user_logged_in()) {
            $login = wp_login_url(fhor_sb_qa_url());
            return '<div class="sb-gate"><h1>System Builder QA</h1><p>Log in to run guided tests.</p><p><a class="sb-btn sb-btn-primary" href="' . esc_url($login) . '">Log in</a></p></div>';
        }

        ob_start();
        ?>
        <div class="sb-qa-page" id="fhor-system-builder-qa">
            <header class="sb-qa-hero">
                <h1 class="sb-qa-title">System Builder QA lab</h1>
                <p class="sb-qa-lead">Guided scenarios, health checks, and the live builder on one page. Backtests and qualifiers use <strong>real</strong> data — start with short lookbacks.</p>
                <p class="sb-qa-meta">
                    <a href="<?php echo esc_url(fhor_sb_url()); ?>">Production builder</a>
                    · <a href="<?php echo esc_url(fhor_sb_qualifiers_url()); ?>">My qualifiers</a>
                    · Local scripts: <code>tests/system-builder-test.php</code>, <code>tests/system-builder-filters-test.mjs</code>
                </p>
            </header>

            <div class="sb-qa-grid">
                <section class="sb-qa-panel" id="sb-qa-health">
                    <h2>Health checks</h2>
                    <p class="sb-qa-note">Server-side dosage and filter sanitization (no database scan).</p>
                    <button type="button" class="sb-btn sb-btn-primary" id="sb-qa-run-self-tests">Run self-tests</button>
                    <pre class="sb-qa-log" id="sb-qa-self-test-log" hidden></pre>
                    <details class="sb-qa-guide" open>
                        <summary>How to read results</summary>
                        <ul>
                            <li><strong>Dosage engine</strong> — Secretariat DI 3.00 / CD 0.90; split chefs; Inf when stamina wing is zero.</li>
                            <li><strong>cd_min 0</strong> — must survive save/load (stamina presets use low CD caps, not zero min).</li>
                            <li>CLI: <code>wp eval 'var_export(bricks_dosage_self_test());'</code></li>
                        </ul>
                    </details>
                </section>

                <section class="sb-qa-panel" id="sb-qa-checklist">
                    <h2>Release checklist</h2>
                    <p class="sb-qa-note">Tick as you go; stored in this browser only.</p>
                    <ul class="sb-qa-checks" id="sb-qa-checklist"></ul>
                    <button type="button" class="sb-btn" id="sb-qa-checklist-reset">Reset checklist</button>
                </section>
            </div>

            <section class="sb-qa-panel" id="sb-qa-scenarios">
                <h2>Guided scenarios</h2>
                <p class="sb-qa-note">Load filters into the builder below, then follow the steps. Scroll to the form after loading.</p>
                <div class="sb-qa-scenario-cards" id="sb-qa-scenario-cards"></div>
            </section>

            <section class="sb-qa-panel sb-qa-builder-wrap">
                <h2>Live System Builder</h2>
                <p class="sb-qa-note">Same UI as <a href="<?php echo esc_url(fhor_sb_url()); ?>">/system-builder/</a> — use Run System and Find qualifiers here.</p>
                <?php echo do_shortcode('[system_builder]'); ?>
            </section>
        </div>
        <style>
        .sb-qa-page{max-width:1320px;margin:0 auto 2.5rem;color:#0f172a;padding:0 .25rem}
        .sb-qa-hero{margin:0 0 1.25rem}
        .sb-qa-title{margin:0 0 .35rem;font-size:clamp(1.45rem,2.4vw,1.95rem)}
        .sb-qa-lead{margin:0;color:#475569;line-height:1.55;max-width:52rem}
        .sb-qa-meta{margin:.5rem 0 0;font-size:.82rem;color:#64748b}
        .sb-qa-meta code{font-size:.78rem;background:#f1f5f9;padding:.1rem .35rem;border-radius:4px}
        .sb-qa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-bottom:1rem}
        .sb-qa-panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1rem 1.05rem;margin-bottom:1rem;box-shadow:0 1px 4px rgba(15,23,42,.05)}
        .sb-qa-panel h2{margin:0 0 .5rem;font-size:1rem}
        .sb-qa-note{font-size:.82rem;color:#64748b;margin:0 0 .65rem;line-height:1.45}
        .sb-qa-note a{color:#15803d;font-weight:700}
        .sb-qa-log{margin:.75rem 0 0;padding:.65rem .75rem;background:#0f172a;color:#e2e8f0;border-radius:8px;font-size:.72rem;max-height:220px;overflow:auto;white-space:pre-wrap}
        .sb-qa-guide{margin-top:.75rem;font-size:.82rem;color:#334155}
        .sb-qa-guide summary{cursor:pointer;font-weight:700;color:#475569}
        .sb-qa-guide ul{margin:.4rem 0 0;padding-left:1.1rem}
        .sb-qa-checks{list-style:none;margin:0;padding:0}
        .sb-qa-checks li{margin:.35rem 0;font-size:.85rem}
        .sb-qa-checks label{display:flex;gap:.45rem;align-items:flex-start;cursor:pointer}
        .sb-qa-checks small{display:block;color:#64748b;font-size:.75rem;margin-top:.1rem}
        .sb-qa-scenario-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.75rem}
        .sb-qa-card{border:1px solid #e2e8f0;border-radius:12px;padding:.85rem;background:#f8fafc}
        .sb-qa-card h3{margin:0 0 .35rem;font-size:.92rem}
        .sb-qa-card p{margin:0 0 .5rem;font-size:.8rem;color:#475569;line-height:1.4}
        .sb-qa-card ol{margin:0 0 .65rem;padding-left:1.15rem;font-size:.78rem;color:#334155}
        .sb-qa-card li{margin:.2rem 0}
        .sb-qa-card .sb-btn{font-size:.8rem;padding:.45rem .7rem}
        .sb-qa-builder-wrap .sb-page{margin-top:.5rem}
        </style>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('system_builder_qa', 'fhor_sb_qa_shortcode');

if (!function_exists('fhor_sb_qa_add_rewrite')) {
    function fhor_sb_qa_add_rewrite() {
        add_rewrite_tag('%fhor_system_builder_qa%', '([0-9]+)');
        add_rewrite_rule('^system-builder-qa/?$', 'index.php?fhor_system_builder_qa=1', 'top');
    }
}
add_action('init', 'fhor_sb_qa_add_rewrite', 21);

if (!function_exists('fhor_sb_qa_query_vars')) {
    function fhor_sb_qa_query_vars($vars) {
        $vars[] = 'fhor_system_builder_qa';
        return $vars;
    }
}
add_filter('query_vars', 'fhor_sb_qa_query_vars');

if (!function_exists('fhor_sb_qa_template_redirect')) {
    function fhor_sb_qa_template_redirect() {
        if (is_admin() || !fhor_sb_qa_is_request()) {
            return;
        }
        status_header(200);
        nocache_headers();
        get_header();
        echo '<main id="brx-content" class="sb-page-shell"><div style="padding:0 4px;">';
        echo do_shortcode('[system_builder_qa]');
        echo '</div></main>';
        get_footer();
        exit;
    }
}
add_action('template_redirect', 'fhor_sb_qa_template_redirect', 3);

if (!function_exists('fhor_sb_qa_flush_rewrites')) {
    function fhor_sb_qa_flush_rewrites() {
        if (get_option('fhor_sb_qa_rewrite_flushed') !== '1') {
            flush_rewrite_rules();
            update_option('fhor_sb_qa_rewrite_flushed', '1');
        }
    }
}
add_action('init', 'fhor_sb_qa_flush_rewrites', 998);

if (!function_exists('fhor_sb_qa_document_title')) {
    function fhor_sb_qa_document_title($title) {
        if (fhor_sb_qa_is_request()) {
            return 'System Builder QA | Fhorsite';
        }
        return $title;
    }
}
add_filter('pre_get_document_title', 'fhor_sb_qa_document_title', 31);

if (!function_exists('fhor_sb_qa_robots')) {
    function fhor_sb_qa_robots($robots) {
        if (fhor_sb_qa_is_request()) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }
        return $robots;
    }
}
add_filter('wp_robots', 'fhor_sb_qa_robots');

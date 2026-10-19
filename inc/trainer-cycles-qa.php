<?php
/**
 * Trainer Cycles QA lab — Phase 4.3 staging sign-off.
 *
 * URL: /trainer-cycles-qa/   Shortcode: [trainer_cycles_qa]
 */

if (!function_exists('fhor_tc_qa_url')) {
    function fhor_tc_qa_url() {
        return home_url('/trainer-cycles-qa/');
    }
}

if (!function_exists('fhor_tc_qa_user_can_view')) {
    function fhor_tc_qa_user_can_view() {
        if (defined('FHOR_TC_QA_OPEN') && FHOR_TC_QA_OPEN) {
            return is_user_logged_in();
        }
        return current_user_can('manage_options');
    }
}

if (!function_exists('fhor_tc_qa_is_request')) {
    function fhor_tc_qa_is_request() {
        if (get_query_var('fhor_trainer_cycles_qa')) {
            return true;
        }
        return (bool) preg_match('#/trainer-cycles-qa(?:/|$)#i', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    }
}

if (!function_exists('fhor_tc_qa_is_active')) {
    function fhor_tc_qa_is_active() {
        if (fhor_tc_qa_is_request()) {
            return true;
        }
        return function_exists('bricks_current_post_has_shortcode')
            && bricks_current_post_has_shortcode(['trainer_cycles_qa']);
    }
}

if (!function_exists('fhor_tc_qa_checklist')) {
    /**
     * Manual staging sign-off (Phase 4.3 exit criteria).
     *
     * @return array<int, array{id:string,label:string,hint:string}>
     */
    function fhor_tc_qa_checklist() {
        return [
            [
                'id' => 'dashboard_jumps',
                'label' => 'Macro dashboard loads (Jumps)',
                'hint' => '/trainer-cycles/ — hot/cold lists and spotlights render',
            ],
            [
                'id' => 'dashboard_flat',
                'label' => 'Hero Flat toggle updates without full reload',
                'hint' => 'Network → fhor_tc_dashboard; sections refresh',
            ],
            [
                'id' => 'profile_slug',
                'label' => 'Trainer profile slug resolves (200 + correct name)',
                'hint' => 'Open sample from Slug samples below; garbage slug → 404',
            ],
            [
                'id' => 'profile_code_flat',
                'label' => 'Profile ?code=flat when yard has flat runners',
                'hint' => 'Chart + stats match flat code; toggle if both codes available',
            ],
            [
                'id' => 'small_months',
                'label' => 'Months with <50 runners show ineligible (no delta / badge)',
                'hint' => 'Profile chart gaps; dashboard “small sample” on current month if applicable',
            ],
            [
                'id' => 'non_runners',
                'label' => 'Non-runners excluded from strike %',
                'hint' => 'Spot-check one known NR — not counted in runs/wins (see scenario)',
            ],
            [
                'id' => 'transient_hit',
                'label' => 'Macro transient hit on second load',
                'hint' => 'Cache probe → second meta.cache = hit; or reload dashboard meta in page source',
            ],
            [
                'id' => 'perf_cold',
                'label' => 'Cold cache macro under ~3s (meta.ms / sql_ms)',
                'hint' => 'See TRAINER_CYCLES_DEPLOY.md after flushing transients',
            ],
            [
                'id' => 'a11y_keyboard',
                'label' => 'Dashboard keyboard pass (tabs, toggles, aria-live)',
                'hint' => 'Phase 4.1 — Tab through Jumps/Flat and volatility rail',
            ],
            [
                'id' => 'unit_php',
                'label' => 'php tests/trainer-cycles-test.php passes',
                'hint' => 'Local / CI before deploy',
            ],
        ];
    }
}

if (!function_exists('fhor_tc_qa_scenarios')) {
    /**
     * @return array<string, array{title:string,summary:string,steps:array<int,string>,links:array<string,string>}>
     */
    function fhor_tc_qa_scenarios() {
        $dash = function_exists('fhor_tc_dashboard_url') ? fhor_tc_dashboard_url() : home_url('/trainer-cycles/');
        $dash_flat = function_exists('fhor_tc_dashboard_url') ? fhor_tc_dashboard_url('flat') : add_query_arg('code', 'flat', $dash);

        return [
            'dashboard_smoke' => [
                'title' => 'Dashboard — Jumps smoke',
                'summary' => 'First paint and hot/cold ordering by current-month delta.',
                'steps' => [
                    'Open the macro dashboard (Jumps).',
                    'Confirm hero shows reference month and yard count.',
                    'Open a hot trainer link — profile loads with matching baseline.',
                    'Use browser back — dashboard state unchanged.',
                ],
                'links' => ['dashboard' => $dash],
            ],
            'dashboard_flat_ajax' => [
                'title' => 'Dashboard — Flat toggle',
                'summary' => 'AJAX swap for flat code; spotlight windows use Apr–Jun.',
                'steps' => [
                    'On /trainer-cycles/, click Flat in the hero.',
                    'Network tab: fhor_tc_dashboard returns ok bootstrap.',
                    'Early Birds hint copy mentions spring months for flat.',
                    'Switch back to Jumps — lists resort correctly.',
                ],
                'links' => ['dashboard' => $dash, 'dashboard_flat' => $dash_flat],
            ],
            'profile_slug' => [
                'title' => 'Profile slug + 404',
                'summary' => 'Public /trainers/{slug}/ routing and slug index.',
                'steps' => [
                    'Run Slug samples (health panel) and open each profile URL.',
                    'Page title / H1 matches historic trainer_name spelling.',
                    'Visit /trainers/not-a-real-trainer-slug-xyz/ — expect 404.',
                ],
                'links' => [],
            ],
            'small_months' => [
                'title' => 'Small months (<50 runners)',
                'summary' => 'Eligible flag drives deltas and chart gaps.',
                'steps' => [
                    'Pick a profile with a thin month (or inspect bootstrap months in devtools).',
                    'Ineligible month: no delta point; eligible months show strike vs baseline.',
                    'On dashboard, current month badge when runs &lt; min for that calendar month.',
                ],
                'links' => ['dashboard' => $dash],
            ],
            'non_runners' => [
                'title' => 'Non-runner exclusion',
                'summary' => 'SQL LEFT JOIN non_runners — NR rows never enter aggregates.',
                'steps' => [
                    'Self-tests confirm nr.runner_id IS NULL in WHERE when table exists.',
                    'Optional: find a resulted race where a horse was NR — trainer month runs should not include it.',
                    'Compare with System Builder filters on same trainer/date if unsure.',
                ],
                'links' => [],
            ],
            'cache_cold_hot' => [
                'title' => 'Transient cold → hot',
                'summary' => 'Macro pool cached per code + cache_ver.',
                'steps' => [
                    'wp transient delete --all (staging) or bump cache ver.',
                    'Run Cache probe (Jumps) — note first cache miss and sql_ms.',
                    'Run again — second line must be hit with no sql_ms.',
                    'Repeat probe for flat code.',
                ],
                'links' => ['deploy' => 'TRAINER_CYCLES_DEPLOY.md'],
            ],
        ];
    }
}

if (!function_exists('fhor_tc_qa_run_self_tests')) {
    function fhor_tc_qa_run_self_tests() {
        $results = ['ok' => true, 'tests' => []];

        $slug_name = 'Paul Nicholls';
        $slug = fhor_tc_trainer_slug($slug_name);
        $slug_ok = $slug !== '' && $slug === fhor_tc_trainer_slug('  Paul Nicholls  ');
        $results['tests'][] = [
            'name' => 'Trainer slug sanitize_title round-trip',
            'ok' => $slug_ok,
            'detail' => ['slug' => $slug],
        ];
        if (!$slug_ok) {
            $results['ok'] = false;
        }

        $jumps_types = fhor_tc_code_race_types('jumps');
        $flat_types = fhor_tc_code_race_types('flat');
        $codes_differ = $jumps_types !== $flat_types;
        $results['tests'][] = [
            'name' => 'Jumps vs flat race_type filters differ',
            'ok' => $codes_differ,
            'detail' => ['jumps' => $jumps_types, 'flat' => $flat_types],
        ];
        if (!$codes_differ) {
            $results['ok'] = false;
        }

        $rows = fhor_tc_build_twelve_month_rows([6 => ['runs' => 40, 'wins' => 8]]);
        $june = null;
        foreach ($rows as $row) {
            if ((int) ($row['cal_month'] ?? 0) === 6) {
                $june = $row;
                break;
            }
        }
        $small_ok = is_array($june) && empty($june['eligible']) && count($rows) === 12;
        $results['tests'][] = [
            'name' => 'Small month (< min runners) ineligible',
            'ok' => $small_ok,
            'detail' => $june,
        ];
        if (!$small_ok) {
            $results['ok'] = false;
        }

        $frag = fhor_tc_sql_from_where('jumps');
        $nr_in_sql = false;
        if ($frag !== []) {
            $sql_blob = ($frag['from'] ?? '') . implode(' ', $frag['where'] ?? []);
            if (function_exists('fhor_sb_table_exists') && fhor_sb_table_exists('non_runners')) {
                $nr_in_sql = (strpos($sql_blob, 'non_runners') !== false)
                    && (strpos($sql_blob, 'nr.runner_id IS NULL') !== false);
            } else {
                $nr_in_sql = true;
            }
        }
        $results['tests'][] = [
            'name' => 'Non-runner exclusion in SQL fragment',
            'ok' => $nr_in_sql,
            'detail' => [
                'tables_ok' => fhor_tc_tables_ok(),
                'non_runners_table' => function_exists('fhor_sb_table_exists') && fhor_sb_table_exists('non_runners'),
            ],
        ];
        if (!$nr_in_sql) {
            $results['ok'] = false;
        }

        $index_ok = true;
        $index_detail = ['count' => 0, 'sample' => []];
        if (fhor_tc_tables_ok()) {
            $index = fhor_tc_get_trainer_slug_index();
            $index_detail['count'] = count($index);
            $n = 0;
            foreach ($index as $s => $name) {
                if ($n >= 3) {
                    break;
                }
                $resolved = fhor_tc_resolve_trainer_from_slug($s);
                if ($resolved !== $name) {
                    $index_ok = false;
                }
                $index_detail['sample'][] = ['slug' => $s, 'name' => $name, 'resolved' => $resolved];
                $n++;
            }
        }
        $results['tests'][] = [
            'name' => 'Slug index resolves sample slugs (when DB available)',
            'ok' => $index_ok,
            'detail' => $index_detail,
        ];
        if (!$index_ok) {
            $results['ok'] = false;
        }

        return $results;
    }
}

if (!function_exists('fhor_tc_qa_slug_samples')) {
    /**
     * @return array<int, array{slug:string,name:string,url:string,profile_url_flat:string}>
     */
    function fhor_tc_qa_slug_samples($limit = 5) {
        if (!fhor_tc_tables_ok()) {
            return [];
        }
        $index = fhor_tc_get_trainer_slug_index();
        if ($index === []) {
            return [];
        }
        $out = [];
        foreach ($index as $slug => $name) {
            $out[] = [
                'slug' => (string) $slug,
                'name' => (string) $name,
                'url' => fhor_tc_trainer_url($name),
                'profile_url_flat' => fhor_tc_trainer_url($name, 'flat'),
            ];
            if (count($out) >= max(1, (int) $limit)) {
                break;
            }
        }
        return $out;
    }
}

if (!function_exists('fhor_tc_qa_require_ajax')) {
    function fhor_tc_qa_require_ajax() {
        if (!fhor_tc_qa_user_can_view()) {
            wp_send_json_error(['message' => 'QA lab is restricted.'], 403);
        }
        check_ajax_referer('fhor_tc_qa', 'nonce');
    }
}

if (!function_exists('fhor_tc_qa_ajax_self_tests')) {
    function fhor_tc_qa_ajax_self_tests() {
        fhor_tc_qa_require_ajax();
        wp_send_json_success(fhor_tc_qa_run_self_tests());
    }
}
add_action('wp_ajax_fhor_tc_qa_self_tests', 'fhor_tc_qa_ajax_self_tests');

if (!function_exists('fhor_tc_qa_ajax_macro_probe')) {
    function fhor_tc_qa_ajax_macro_probe() {
        fhor_tc_qa_require_ajax();
        $code = isset($_POST['code']) ? fhor_tc_normalize_code(wp_unslash($_POST['code'])) : 'jumps';
        if (!fhor_tc_tables_ok()) {
            wp_send_json_error(['message' => 'Historic tables unavailable.'], 503);
        }
        $first = fhor_tc_get_macro_pool($code);
        $second = fhor_tc_get_macro_pool($code);
        $second_cache = (string) ($second['meta']['cache'] ?? '');
        wp_send_json_success([
            'code' => $code,
            'first' => $first['meta'] ?? [],
            'second' => $second['meta'] ?? [],
            'trainerCount' => count($first['trainers'] ?? []),
            'pass' => $second_cache === 'hit',
        ]);
    }
}
add_action('wp_ajax_fhor_tc_qa_macro_probe', 'fhor_tc_qa_ajax_macro_probe');

if (!function_exists('fhor_tc_qa_ajax_slug_samples')) {
    function fhor_tc_qa_ajax_slug_samples() {
        fhor_tc_qa_require_ajax();
        wp_send_json_success(['samples' => fhor_tc_qa_slug_samples(8)]);
    }
}
add_action('wp_ajax_fhor_tc_qa_slug_samples', 'fhor_tc_qa_ajax_slug_samples');

if (!function_exists('fhor_tc_qa_enqueue')) {
    function fhor_tc_qa_enqueue() {
        if (!fhor_tc_qa_is_active()) {
            return;
        }
        $js = get_stylesheet_directory() . '/trainer-cycles-qa.js';
        if (!file_exists($js)) {
            return;
        }
        wp_enqueue_script(
            'fhor-trainer-cycles-qa',
            get_stylesheet_directory_uri() . '/trainer-cycles-qa.js',
            [],
            filemtime($js),
            true
        );
        wp_localize_script('fhor-trainer-cycles-qa', 'fhorTcQa', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fhor_tc_qa'),
            'checklist' => fhor_tc_qa_checklist(),
            'scenarios' => fhor_tc_qa_scenarios(),
            'dashboardUrl' => fhor_tc_dashboard_url(),
            'dashboardFlatUrl' => fhor_tc_dashboard_url('flat'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'fhor_tc_qa_enqueue', 30);

if (!function_exists('fhor_tc_qa_shortcode')) {
    function fhor_tc_qa_shortcode() {
        if (!fhor_tc_qa_user_can_view()) {
            return '<div class="tc-qa-gate"><h1>Trainer Cycles QA</h1><p>Administrators only (or enable <code>FHOR_TC_QA_OPEN</code> on staging).</p></div>';
        }
        if (!is_user_logged_in()) {
            $login = wp_login_url(fhor_tc_qa_url());
            return '<div class="tc-qa-gate"><h1>Trainer Cycles QA</h1><p>Log in to run the staging checklist.</p><p><a class="tc-qa-btn tc-qa-btn-primary" href="' . esc_url($login) . '">Log in</a></p></div>';
        }

        $dash = esc_url(fhor_tc_dashboard_url());
        $dash_flat = esc_url(fhor_tc_dashboard_url('flat'));

        ob_start();
        ?>
        <div class="tc-qa-page" id="fhor-trainer-cycles-qa">
            <header class="tc-qa-hero">
                <h1 class="tc-qa-title">Trainer Cycles QA — Phase 4.3</h1>
                <p class="tc-qa-lead">Staging sign-off for jumps/flat, profile slugs, small months, non-runners, and macro transients. Tick the checklist as you verify on staging.</p>
                <p class="tc-qa-meta">
                    <a href="<?php echo $dash; ?>">Macro dashboard</a>
                    · <a href="<?php echo $dash_flat; ?>">Dashboard (Flat)</a>
                    · <code>TRAINER_CYCLES_DEPLOY.md</code>
                    · CLI: <code>php tests/trainer-cycles-test.php</code>
                </p>
            </header>

            <div class="tc-qa-grid">
                <section class="tc-qa-panel" id="tc-qa-health">
                    <h2>Health checks</h2>
                    <p class="tc-qa-note">Logic and SQL guards (no full macro scan except cache probe).</p>
                    <button type="button" class="tc-qa-btn tc-qa-btn-primary" id="tc-qa-run-self-tests">Run self-tests</button>
                    <div class="tc-qa-probes">
                        <button type="button" class="tc-qa-btn" id="tc-qa-macro-probe-jumps" data-code="jumps">Cache probe (Jumps)</button>
                        <button type="button" class="tc-qa-btn" id="tc-qa-macro-probe-flat" data-code="flat">Cache probe (Flat)</button>
                        <button type="button" class="tc-qa-btn" id="tc-qa-slug-samples">Load slug samples</button>
                    </div>
                    <pre class="tc-qa-log" id="tc-qa-self-test-log" hidden></pre>
                    <ul class="tc-qa-slug-list" id="tc-qa-slug-list" hidden></ul>
                </section>

                <section class="tc-qa-panel" id="tc-qa-checklist">
                    <h2>Release checklist</h2>
                    <p class="tc-qa-note">Stored in this browser only (<code>localStorage</code>).</p>
                    <ul class="tc-qa-checks" id="tc-qa-checklist"></ul>
                    <button type="button" class="tc-qa-btn" id="tc-qa-checklist-reset">Reset checklist</button>
                </section>
            </div>

            <section class="tc-qa-panel" id="tc-qa-scenarios">
                <h2>Guided scenarios</h2>
                <p class="tc-qa-note">Follow on staging after deploy; links open production routes in a new tab.</p>
                <div class="tc-qa-scenario-cards" id="tc-qa-scenario-cards"></div>
            </section>
        </div>
        <style>
        .tc-qa-page{max-width:1200px;margin:0 auto 2.5rem;color:#0f172a;padding:0 .25rem}
        .tc-qa-gate{max-width:640px;margin:2rem auto;padding:1.5rem;border:1px solid #e2e8f0;border-radius:12px}
        .tc-qa-hero{margin:0 0 1.25rem}
        .tc-qa-title{margin:0 0 .35rem;font-size:clamp(1.35rem,2.2vw,1.85rem)}
        .tc-qa-lead{margin:0;color:#475569;line-height:1.55;max-width:52rem}
        .tc-qa-meta{margin:.5rem 0 0;font-size:.82rem;color:#64748b}
        .tc-qa-meta code,.tc-qa-note code{font-size:.78rem;background:#f1f5f9;padding:.1rem .35rem;border-radius:4px}
        .tc-qa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-bottom:1rem}
        .tc-qa-panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1rem 1.05rem;margin-bottom:1rem;box-shadow:0 1px 4px rgba(15,23,42,.05)}
        .tc-qa-panel h2{margin:0 0 .5rem;font-size:1rem}
        .tc-qa-note{font-size:.82rem;color:#64748b;margin:0 0 .65rem;line-height:1.45}
        .tc-qa-note a{color:#15803d;font-weight:700}
        .tc-qa-btn{display:inline-block;margin:.25rem .35rem .25rem 0;padding:.45rem .75rem;font-size:.82rem;border-radius:8px;border:1px solid #cbd5e1;background:#f8fafc;cursor:pointer;color:#0f172a}
        .tc-qa-btn-primary{background:#15803d;border-color:#15803d;color:#fff;font-weight:600}
        .tc-qa-btn:disabled{opacity:.55;cursor:not-allowed}
        .tc-qa-probes{margin-top:.5rem}
        .tc-qa-log{margin:.75rem 0 0;padding:.65rem .75rem;background:#0f172a;color:#e2e8f0;border-radius:8px;font-size:.72rem;max-height:260px;overflow:auto;white-space:pre-wrap}
        .tc-qa-checks{list-style:none;margin:0;padding:0}
        .tc-qa-checks li{margin:.35rem 0;font-size:.85rem}
        .tc-qa-checks label{display:flex;gap:.45rem;align-items:flex-start;cursor:pointer}
        .tc-qa-checks small{display:block;color:#64748b;font-size:.75rem;margin-top:.1rem}
        .tc-qa-scenario-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.75rem}
        .tc-qa-card{border:1px solid #e2e8f0;border-radius:12px;padding:.85rem;background:#f8fafc}
        .tc-qa-card h3{margin:0 0 .35rem;font-size:.92rem}
        .tc-qa-card p{margin:0 0 .5rem;font-size:.8rem;color:#475569;line-height:1.4}
        .tc-qa-card ol{margin:0 0 .65rem;padding-left:1.15rem;font-size:.78rem;color:#334155}
        .tc-qa-card li{margin:.2rem 0}
        .tc-qa-card-links{font-size:.78rem;margin-top:.35rem}
        .tc-qa-card-links a{margin-right:.65rem;color:#15803d;font-weight:600}
        .tc-qa-slug-list{margin:.65rem 0 0;padding-left:1.1rem;font-size:.8rem}
        .tc-qa-slug-list a{color:#15803d;font-weight:600}
        </style>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('trainer_cycles_qa', 'fhor_tc_qa_shortcode');

if (!function_exists('fhor_tc_qa_add_rewrite')) {
    function fhor_tc_qa_add_rewrite() {
        add_rewrite_tag('%fhor_trainer_cycles_qa%', '([0-9]+)');
        add_rewrite_rule('^trainer-cycles-qa/?$', 'index.php?fhor_trainer_cycles_qa=1', 'top');
    }
}
add_action('init', 'fhor_tc_qa_add_rewrite', 21);

if (!function_exists('fhor_tc_qa_query_vars')) {
    function fhor_tc_qa_query_vars($vars) {
        $vars[] = 'fhor_trainer_cycles_qa';
        return $vars;
    }
}
add_filter('query_vars', 'fhor_tc_qa_query_vars');

if (!function_exists('fhor_tc_qa_template_redirect')) {
    function fhor_tc_qa_template_redirect() {
        if (is_admin() || !fhor_tc_qa_is_request()) {
            return;
        }
        status_header(200);
        nocache_headers();
        get_header();
        echo '<main id="brx-content" class="tc-qa-shell"><div style="padding:0 4px;">';
        echo do_shortcode('[trainer_cycles_qa]');
        echo '</div></main>';
        get_footer();
        exit;
    }
}
add_action('template_redirect', 'fhor_tc_qa_template_redirect', 3);

if (!function_exists('fhor_tc_qa_flush_rewrites')) {
    function fhor_tc_qa_flush_rewrites() {
        if (get_option('fhor_tc_qa_rewrite_flushed') !== '1') {
            flush_rewrite_rules();
            update_option('fhor_tc_qa_rewrite_flushed', '1');
        }
    }
}
add_action('init', 'fhor_tc_qa_flush_rewrites', 998);

if (!function_exists('fhor_tc_qa_document_title')) {
    function fhor_tc_qa_document_title($title) {
        if (fhor_tc_qa_is_request()) {
            return 'Trainer Cycles QA | Fhorsite';
        }
        return $title;
    }
}
add_filter('pre_get_document_title', 'fhor_tc_qa_document_title', 31);

if (!function_exists('fhor_tc_qa_robots')) {
    function fhor_tc_qa_robots($robots) {
        if (fhor_tc_qa_is_request()) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }
        return $robots;
    }
}
add_filter('wp_robots', 'fhor_tc_qa_robots');

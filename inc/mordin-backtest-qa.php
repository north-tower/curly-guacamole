<?php
/**
 * Mordin Task 10 backtest lab — admin virtual page with export.
 *
 * URL: /mordin-backtest/   Shortcode: [mordin_backtest_lab]
 */

if (!function_exists('fhor_mordin_bt_url')) {
    function fhor_mordin_bt_url() {
        return home_url('/mordin-backtest/');
    }
}

if (!function_exists('fhor_mordin_bt_user_can_view')) {
    function fhor_mordin_bt_user_can_view() {
        return current_user_can('manage_options');
    }
}

if (!function_exists('fhor_mordin_bt_is_request')) {
    function fhor_mordin_bt_is_request() {
        if (get_query_var('fhor_mordin_backtest')) {
            return true;
        }
        return (bool) preg_match('#/mordin-backtest(?:/|$)#i', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    }
}

if (!function_exists('fhor_mordin_bt_ajax_run')) {
    function fhor_mordin_bt_ajax_run() {
        check_ajax_referer('fhor_mordin_backtest', 'nonce');
        if (!fhor_mordin_bt_user_can_view()) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        @set_time_limit(900);
        @ini_set('memory_limit', '512M');

        $days = isset($_POST['days']) ? (int) $_POST['days'] : 1825;
        $result = mordin_bt_run_batch($days);
        if (empty($result['ok'])) {
            wp_send_json_error(['message' => $result['error'] ?? 'Batch failed']);
        }

        wp_send_json_success([
            'meta' => $result['meta'],
            'rows' => $result['rows'],
            'markdown' => $result['markdown'],
            'notes_table_markdown' => $result['notes_table_markdown'],
        ]);
    }
}
add_action('wp_ajax_fhor_mordin_bt_run', 'fhor_mordin_bt_ajax_run');

if (!function_exists('fhor_mordin_bt_enqueue')) {
    function fhor_mordin_bt_enqueue() {
        if (!fhor_mordin_bt_is_request() && !fhor_mordin_bt_shortcode_active()) {
            return;
        }
        if (!fhor_mordin_bt_user_can_view()) {
            return;
        }
        $js = get_stylesheet_directory() . '/mordin-backtest.js';
        if (!file_exists($js)) {
            return;
        }
        wp_enqueue_script(
            'fhor-mordin-backtest',
            get_stylesheet_directory_uri() . '/mordin-backtest.js',
            [],
            filemtime($js),
            true
        );
        wp_localize_script('fhor-mordin-backtest', 'fhorMordinBt', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fhor_mordin_backtest'),
            'maxDays' => fhor_sb_max_lookback_days(),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'fhor_mordin_bt_enqueue', 29);

if (!function_exists('fhor_mordin_bt_shortcode_active')) {
    function fhor_mordin_bt_shortcode_active() {
        return function_exists('bricks_current_post_has_shortcode')
            && bricks_current_post_has_shortcode(['mordin_backtest_lab']);
    }
}

if (!function_exists('fhor_mordin_bt_shortcode')) {
    function fhor_mordin_bt_shortcode() {
        if (!fhor_mordin_bt_user_can_view()) {
            return '<div class="mbt-gate"><p>Administrator login required.</p></div>';
        }

        if (!function_exists('mordin_bt_preflight')) {
            return '<div class="mbt-gate"><p><strong>Theme not fully deployed.</strong> Missing <code>inc/mordin-backtest-batch.php</code> — upload the latest child theme and reload.</p></div>';
        }

        $preflight = mordin_bt_preflight();
        $max_days = fhor_sb_max_lookback_days();
        ob_start();
        ?>
        <div class="mbt-page" id="fhor-mordin-backtest">
            <header class="mbt-hero">
                <h1 class="mbt-title">Mordin Task 10 backtests</h1>
                <p class="mbt-lead">Runs all 11 master-plan scenarios (22 System Builder backtests) on Industry SP, applies badge verdict rules, and exports results for <code>mordin_class_NOTES.md</code>.</p>
                <p class="mbt-meta">Lookback max for your account: <strong><?php echo (int) $max_days; ?></strong> days · Engine: <code>fhor_sb_run_backtest()</code></p>
            </header>

            <?php if (!$preflight['ok']): ?>
                <div class="mbt-alert is-error"><?php echo esc_html($preflight['message']); ?></div>
            <?php else: ?>
                <div class="mbt-toolbar">
                    <label class="mbt-field">
                        <span>Lookback</span>
                        <select id="mbt-days">
                            <option value="90">90 days (smoke)</option>
                            <option value="365">365 days</option>
                            <?php if ($max_days >= 730): ?><option value="730">730 days</option><?php endif; ?>
                            <?php if ($max_days >= 1825): ?><option value="1825" selected>1825 days (Task 10)</option><?php endif; ?>
                        </select>
                    </label>
                    <button type="button" class="mbt-btn mbt-btn-primary" id="mbt-run">Run all backtests</button>
                    <span class="mbt-status" id="mbt-status" aria-live="polite"></span>
                </div>
                <p class="mbt-note" id="mbt-hint">First full run may take several minutes (dosage scenarios are slow). Keep this tab open.</p>

                <div class="mbt-results" id="mbt-results" hidden>
                    <div class="mbt-export-bar">
                        <button type="button" class="mbt-btn" id="mbt-copy-notes">Copy NOTES table</button>
                        <button type="button" class="mbt-btn" id="mbt-copy-full">Copy full report</button>
                        <button type="button" class="mbt-btn" id="mbt-dl-md">Download .md</button>
                        <button type="button" class="mbt-btn" id="mbt-dl-json">Download .json</button>
                    </div>
                    <p class="mbt-meta" id="mbt-range"></p>
                    <div class="mbt-table-wrap">
                        <table class="mbt-table" id="mbt-table">
                            <thead>
                                <tr>
                                    <th>Scenario</th>
                                    <th>Rule</th>
                                    <th class="mbt-n">Bets</th>
                                    <th class="mbt-n">Strike %</th>
                                    <th class="mbt-n">ROI %</th>
                                    <th class="mbt-n">Baseline ROI %</th>
                                    <th class="mbt-n">Removed</th>
                                    <th>Verdict</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <details class="mbt-details">
                        <summary>Full markdown report</summary>
                        <textarea id="mbt-markdown" readonly rows="16" spellcheck="false"></textarea>
                    </details>
                    <details class="mbt-details">
                        <summary>JSON (paste in chat / archive)</summary>
                        <textarea id="mbt-json" readonly rows="10" spellcheck="false"></textarea>
                    </details>
                </div>
            <?php endif; ?>
        </div>
        <style>
        .mbt-page{max-width:1200px;margin:0 auto 2rem;color:#0f172a;padding:0 .25rem}
        .mbt-title{margin:0 0 .35rem;font-size:clamp(1.35rem,2.2vw,1.85rem)}
        .mbt-lead{margin:0;color:#475569;line-height:1.55;max-width:52rem}
        .mbt-meta{margin:.45rem 0 0;font-size:.82rem;color:#64748b}
        .mbt-meta code,.mbt-lead code{font-size:.78rem;background:#f1f5f9;padding:.08rem .3rem;border-radius:4px}
        .mbt-alert{padding:.75rem 1rem;border-radius:10px;margin:1rem 0;font-size:.9rem}
        .mbt-alert.is-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
        .mbt-toolbar{display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;margin:1.25rem 0 .5rem}
        .mbt-field{display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;color:#475569}
        .mbt-field select{min-width:12rem;padding:.4rem .5rem;border-radius:8px;border:1px solid #cbd5e1}
        .mbt-btn{font:inherit;cursor:pointer;border:1px solid #cbd5e1;background:#fff;border-radius:8px;padding:.5rem .85rem;font-size:.85rem}
        .mbt-btn-primary{background:#15803d;border-color:#15803d;color:#fff;font-weight:700}
        .mbt-btn:disabled{opacity:.55;cursor:not-allowed}
        .mbt-status{font-size:.85rem;color:#64748b;min-height:1.25rem}
        .mbt-note{font-size:.82rem;color:#64748b;margin:0 0 1rem}
        .mbt-export-bar{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem}
        .mbt-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
        .mbt-table{width:100%;border-collapse:collapse;font-size:.8rem}
        .mbt-table th,.mbt-table td{padding:.45rem .55rem;border-bottom:1px solid #f1f5f9;text-align:left;vertical-align:top}
        .mbt-table th{background:#f8fafc;font-weight:700;color:#334155}
        .mbt-table .mbt-n{text-align:right;font-variant-numeric:tabular-nums}
        .mbt-table tr.verdict-keep{background:#f0fdf4}
        .mbt-table tr.verdict-drop{background:#fffbeb}
        .mbt-details{margin-top:1rem;font-size:.85rem}
        .mbt-details summary{cursor:pointer;font-weight:700;color:#475569;margin-bottom:.35rem}
        .mbt-details textarea{width:100%;font-family:ui-monospace,Consolas,monospace;font-size:.72rem;padding:.65rem;border:1px solid #e2e8f0;border-radius:8px;box-sizing:border-box}
        .mbt-gate{padding:1rem;border:1px solid #e2e8f0;border-radius:10px}
        </style>
        <?php
        return ob_get_clean();
    }
}
add_shortcode('mordin_backtest_lab', 'fhor_mordin_bt_shortcode');

if (!function_exists('fhor_mordin_bt_add_rewrite')) {
    function fhor_mordin_bt_add_rewrite() {
        add_rewrite_tag('%fhor_mordin_backtest%', '([0-9]+)');
        add_rewrite_rule('^mordin-backtest/?$', 'index.php?fhor_mordin_backtest=1', 'top');
    }
}
add_action('init', 'fhor_mordin_bt_add_rewrite', 21);

if (!function_exists('fhor_mordin_bt_query_vars')) {
    function fhor_mordin_bt_query_vars($vars) {
        $vars[] = 'fhor_mordin_backtest';
        return $vars;
    }
}
add_filter('query_vars', 'fhor_mordin_bt_query_vars');

if (!function_exists('fhor_mordin_bt_template_redirect')) {
    function fhor_mordin_bt_template_redirect() {
        if (is_admin() || !fhor_mordin_bt_is_request()) {
            return;
        }
        status_header(200);
        nocache_headers();
        get_header();
        echo '<main id="brx-content" class="sb-page-shell"><div style="padding:0 4px;">';
        echo do_shortcode('[mordin_backtest_lab]');
        echo '</div></main>';
        get_footer();
        exit;
    }
}
add_action('template_redirect', 'fhor_mordin_bt_template_redirect', 3);

if (!function_exists('fhor_mordin_bt_flush_rewrites')) {
    function fhor_mordin_bt_flush_rewrites() {
        if (get_option('fhor_mordin_bt_rewrite_flushed') !== '2') {
            flush_rewrite_rules();
            update_option('fhor_mordin_bt_rewrite_flushed', '2');
        }
    }
}
add_action('init', 'fhor_mordin_bt_flush_rewrites', 998);

if (!function_exists('fhor_mordin_bt_document_title')) {
    function fhor_mordin_bt_document_title($title) {
        if (fhor_mordin_bt_is_request()) {
            return 'Mordin backtest lab | Fhorsite';
        }
        return $title;
    }
}
add_filter('pre_get_document_title', 'fhor_mordin_bt_document_title', 31);

if (!function_exists('fhor_mordin_bt_robots')) {
    function fhor_mordin_bt_robots($robots) {
        if (fhor_mordin_bt_is_request()) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }
        return $robots;
    }
}
add_filter('wp_robots', 'fhor_mordin_bt_robots');

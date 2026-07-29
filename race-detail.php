<?php
/**
 * Template for race detail pages (/race/{id}/).
 * Loaded via inc/rewrites.php — not a WordPress admin page.
 */

get_header();
?>
<style id="race-detail-page-shell-styles">
    .race-detail-page-shell {
        max-width: 1400px;
        width: 100%;
        margin: 0 auto;
        padding: 12px 12px calc(28px + var(--fhor-fab-clearance, 72px));
        box-sizing: border-box;
        overflow-x: clip;
        background: var(--fhor-bg, #f1f5f9);
    }
    .race-detail-page-shell .back-button-container,
    .race-detail-page-shell .race-quick-nav,
    .race-detail-page-shell .race-detail-container,
    .race-detail-page-shell .cpc,
    .race-detail-page-shell .pace-map,
    .race-detail-page-shell .pace-map-error,
    .race-detail-page-shell .fhor-panel {
        max-width: none;
        width: 100%;
        margin-left: 0;
        margin-right: 0;
        box-sizing: border-box;
    }
    .race-detail-page-shell .back-button-container,
    .race-detail-page-shell .race-quick-nav {
        padding-left: 0;
        padding-right: 0;
    }
    .race-detail-page-shell .race-detail-container {
        padding: 0;
    }
</style>
<main id="brx-content" class="race-detail-page-shell">
    <?php
    $race_id = get_query_var('race_id');
    if ($race_id) {
        echo do_shortcode('[race_detail]');
    } else {
        ?>
        <div style="text-align:center;padding:60px;font-family:sans-serif;">
            <div style="font-size:64px;margin-bottom:20px;">🏇</div>
            <h2 style="color:#334155;margin-bottom:12px;">Race Not Found</h2>
            <p style="color:#64748b;margin-bottom:24px;">The race you're looking for doesn't exist.</p>
            <a href="<?php echo esc_url(home_url('/daily/')); ?>" style="display:inline-block;padding:12px 24px;background:#3b82f6;color:white;text-decoration:none;border-radius:8px;font-weight:600;">
                ← Back to Daily Races
            </a>
        </div>
        <?php
    }
    ?>
</main>
<?php
get_footer();

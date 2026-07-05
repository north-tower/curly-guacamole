<?php
/**
 * Template for race detail pages (/race/{id}/).
 * Loaded via inc/rewrites.php — not a WordPress admin page.
 */

get_header();
?>
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

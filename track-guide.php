<?php
/**
 * Racecourse guide pages — Bricks header/footer + one content render only.
 */

get_header();
?>
<main id="brx-content" class="racecourse-guide-page">
    <div class="racecourse-guide-shell">
        <?php
        if (function_exists('bricks_track_render_main_content')) {
            bricks_track_render_main_content();
        }
        ?>
    </div>
</main>
<?php
get_footer();

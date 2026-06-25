<?php
/**
 * Racing festival hubs — Bricks shell + shortcode or content template.
 */

get_header();
?>
<main id="brx-content" class="racing-festivals-page-shell">
    <?php
    if (function_exists('bricks_festival_render_main_content')) {
        bricks_festival_render_main_content();
    } else {
        echo do_shortcode('[racing_festivals_index]');
    }
    ?>
</main>
<?php
get_footer();

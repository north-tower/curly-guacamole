<?php
/**
 * Proven Winners archive — Bricks shell + shortcode or content template.
 */

get_header();
?>
<main id="brx-content" class="proven-winners-page-shell">
    <?php
    if (function_exists('bricks_proven_winners_render_main_content')) {
        bricks_proven_winners_render_main_content();
    } else {
        echo do_shortcode('[proven_winners_archive]');
    }
    ?>
</main>
<?php
get_footer();

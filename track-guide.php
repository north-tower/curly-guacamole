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

        // Logged-out funnel CTA at the bottom of individual course pages only.
        if (
            function_exists('bricks_track_is_directory_index_request')
            && !bricks_track_is_directory_index_request()
            && function_exists('bricks_track_is_region_hub_request')
            && !bricks_track_is_region_hub_request()
            && !get_query_var('tracks_index')
            && function_exists('bricks_track_resolve_context')
            && function_exists('bricks_track_render_funnel_cta')
            && function_exists('bricks_track_enqueue_styles')
        ) {
            $cta_context = bricks_track_resolve_context([]);
            if ($cta_context) {
                bricks_track_enqueue_styles();
                echo bricks_track_render_funnel_cta($cta_context);
            }
        }
        ?>
    </div>
</main>
<?php
get_footer();

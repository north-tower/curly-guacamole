<?php
/**
 * Template for Race Comment History Page
 */

get_header(); ?>

<div class="race-comment-page-content">
    <?php
    $race_id = get_query_var('race_comment_id');
    if ($race_id) {
        echo do_shortcode('[race_comment_history race_id="' . intval($race_id) . '"]');
    } else {
        echo '<div style="color:red;padding:20px;">Error: Race ID not found</div>';
    }
    ?>
</div>

<?php get_footer(); ?>

<?php
/**
 * Home Page Template
 */

get_header(); ?>

<div class="home-page-content">
    <?php
    // Use the editable landing page content
    echo do_shortcode('[editable_landing_page]');
    ?>
</div>

<?php get_footer(); ?>

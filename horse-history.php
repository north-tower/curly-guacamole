<?php
get_header();

$runner_id = get_query_var('runner_id');
$horse_name = get_query_var('horse_name') ? urldecode(get_query_var('horse_name')) : '';

// Set page title
$page_title = 'Horse History';
if ($runner_id) {
    $page_title = 'Runner #' . intval($runner_id) . ' - Horse History';
} elseif ($horse_name) {
    $page_title = esc_html($horse_name) . ' - Horse History';
}
?>

<style>
    .green-text { color: #10b981 !important; font-weight: 600; }
    .red-text { color: #ef4444 !important; font-weight: 600; }
    .sr-highlight { 
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 700;
    }
</style>

<main class="main-content">
    <div class="content-container">
        <?php
        if ($runner_id) {
            echo do_shortcode('[horse_history runner_id="' . intval($runner_id) . '"]');
        } elseif ($horse_name) {
            echo do_shortcode('[horse_history horse_name="' . esc_attr($horse_name) . '"]');
        } else {
            echo '<div style="color:red;padding:20px;">Error: No horse specified</div>';
        }
        ?>
    </div>
</main>

<?php get_footer(); ?>

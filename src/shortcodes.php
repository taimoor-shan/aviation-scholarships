<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [recent_scholarships count="6"]
 */
function shortcode_recent_scholarships($atts) {

    $atts = shortcode_atts([
        'count' => 6,
    ], $atts);

    $query = new \WP_Query([
        'post_type'      => 'scholarship',
        'posts_per_page' => intval($atts['count']),
        'meta_key'       => 'sch_deadline',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ]);

    if (!$query->have_posts()) {
        return '<p>No scholarships available right now.</p>';
    }

    ob_start();

    echo '<div class="row">';
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();

        echo '<div class="col-md-4">';
        echo render_scholarship_card($post_id);
        echo '</div>';
    }
    echo '</div>';

    wp_reset_postdata();

    return ob_get_clean();
}

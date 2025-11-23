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
        return '<p class="avs-no-results">No scholarships available right now.</p>';
    }

    // Collect all post IDs
    $scholarship_ids = [];
    while ($query->have_posts()) {
        $query->the_post();
        $scholarship_ids[] = get_the_ID();
    }
    wp_reset_postdata();

    // Use the grid wrapper function
    return render_scholarships_grid($scholarship_ids);
}

/**
 * Shortcode: [recent_scholarships_compact count="6"]
 * Compact version with Bootstrap modal for detailed information
 */
function shortcode_recent_scholarships_compact($atts) {

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
        return '<p class="avs-no-results">No scholarships available right now.</p>';
    }

    // Collect all post IDs
    $scholarship_ids = [];
    while ($query->have_posts()) {
        $query->the_post();
        $scholarship_ids[] = get_the_ID();
    }
    wp_reset_postdata();

    // Use the compact grid wrapper function
    return render_scholarships_grid_compact($scholarship_ids);
}

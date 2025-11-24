<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [recent_scholarships count="6"]
 * Displays scholarships in a grid layout, ordered by deadline
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

    // Layout wrapper with grid - shortcode controls the layout
    ob_start();
    ?>
    <div class="avs-scholarships-grid avs-recent-scholarships">
        <?php foreach ($scholarship_ids as $post_id) : ?>
            <?= render_scholarship_card_compact($post_id); ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: [recent_scholarships_compact count="6"]
 * Compact version with Bootstrap modal for detailed information
 * Displays scholarships in a grid layout, ordered by deadline
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

    // Layout wrapper with grid - shortcode controls the layout
    ob_start();
    ?>
    <div class="avs-scholarships-grid avs-recent-scholarships-compact">
        <?php foreach ($scholarship_ids as $post_id) : ?>
            <?= render_scholarship_card_compact($post_id); ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: [closing_soon_scholarships count="6" days="30"]
 * Displays scholarships closing within specified days, ordered by deadline (soonest first)
 */
function shortcode_closing_soon_scholarships($atts) {

    $atts = shortcode_atts([
        'count' => 6,
        'days'  => 30,  // Default: scholarships closing within 30 days
    ], $atts);

    // Calculate the date range
    $today = date('Y-m-d');
    $future_date = date('Y-m-d', strtotime("+{$atts['days']} days"));

    $query = new \WP_Query([
        'post_type'      => 'scholarship',
        'posts_per_page' => intval($atts['count']),
        'meta_key'       => 'sch_deadline',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => 'sch_deadline',
                'value'   => [$today, $future_date],
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ],
        ],
    ]);

    if (!$query->have_posts()) {
        return '<p class="avs-no-results">No scholarships closing soon.</p>';
    }

    // Collect all post IDs
    $scholarship_ids = [];
    while ($query->have_posts()) {
        $query->the_post();
        $scholarship_ids[] = get_the_ID();
    }
    wp_reset_postdata();

    // Layout wrapper with grid - shortcode controls the layout
    ob_start();
    ?>
    <div class="avs-scholarships-grid avs-closing-soon-scholarships">
        <?php foreach ($scholarship_ids as $post_id) : ?>
            <?= render_scholarship_card_compact($post_id); ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: [closing_soon_scholarships_compact count="6" days="30"]
 * Compact version with Bootstrap modal for detailed information
 * Displays scholarships closing within specified days, ordered by deadline (soonest first)
 */
function shortcode_closing_soon_scholarships_compact($atts) {

    $atts = shortcode_atts([
        'count' => 6,
        'days'  => 30,  // Default: scholarships closing within 30 days
    ], $atts);

    // Calculate the date range
    $today = date('Y-m-d');
    $future_date = date('Y-m-d', strtotime("+{$atts['days']} days"));

    $query = new \WP_Query([
        'post_type'      => 'scholarship',
        'posts_per_page' => intval($atts['count']),
        'meta_key'       => 'sch_deadline',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => 'sch_deadline',
                'value'   => [$today, $future_date],
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ],
        ],
    ]);

    if (!$query->have_posts()) {
        return '<p class="avs-no-results">No scholarships closing soon.</p>';
    }

    // Collect all post IDs
    $scholarship_ids = [];
    while ($query->have_posts()) {
        $query->the_post();
        $scholarship_ids[] = get_the_ID();
    }
    wp_reset_postdata();

    // Layout wrapper with grid - shortcode controls the layout
    ob_start();
    ?>
    <div class="avs-scholarships-grid avs-closing-soon-scholarships-compact">
        <?php foreach ($scholarship_ids as $post_id) : ?>
            <?= render_scholarship_card_compact($post_id); ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [all_scholarships]
 * Complete scholarship listing with filtering, sorting, and pagination
 */
function shortcode_all_scholarships($atts) {
    
    $atts = shortcode_atts([
        'per_page' => 12,
    ], $atts);

    // Get current page
    $paged = get_query_var('paged') ? get_query_var('paged') : (isset($_GET['avs_page']) ? intval($_GET['avs_page']) : 1);
    
    // Build query args from filters
    $query_args = build_scholarship_query_args($paged, intval($atts['per_page']));
    
    $query = new \WP_Query($query_args);

    ob_start();
    ?>
    <div class="avs-all-scholarships-container">
        
        <!-- Filters Section -->
        <?= render_scholarship_filters($query->found_posts); ?>
        
        <!-- Results Section -->
        <div class="avs-scholarships-results">
            <?php if ($query->have_posts()) : ?>
                
                <!-- Results Count & Sort -->
                <div class="avs-results-header">
                    <div class="avs-results-count">
                        Showing <?= (($paged - 1) * intval($atts['per_page'])) + 1; ?> - <?= min($paged * intval($atts['per_page']), $query->found_posts); ?> of <?= $query->found_posts; ?> scholarships
                    </div>
                    <?= render_sort_dropdown(); ?>
                </div>

                <!-- Scholarship Grid -->
                <div class="avs-scholarships-grid avs-all-scholarships">
                    <?php 
                    while ($query->have_posts()) : 
                        $query->the_post();
                        $post_id = get_the_ID();
                        echo render_scholarship_card_compact($post_id);
                    endwhile;
                    wp_reset_postdata();
                    ?>
                </div>

                <!-- Pagination -->
                <?= render_scholarship_pagination($query->max_num_pages, $paged); ?>

            <?php else : ?>
                <p class="avs-no-results">No scholarships found matching your criteria. Try adjusting your filters.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Build WP_Query args based on URL parameters (filters & sorting)
 */
function build_scholarship_query_args($paged, $per_page) {
    
    $args = [
        'post_type'      => 'scholarship',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'post_status'    => 'publish',
    ];

    $meta_query = [];
    $tax_query = [];

    // Filter: Title Search (partial match)
    if (!empty($_GET['avs_title'])) {
        $args['s'] = sanitize_text_field($_GET['avs_title']);
    }

    // Filter: Category
    if (!empty($_GET['avs_category'])) {
        $tax_query[] = [
            'taxonomy' => 'sch_category',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_GET['avs_category']),
        ];
    }

    // Filter: License Type
    if (!empty($_GET['avs_license'])) {
        $tax_query[] = [
            'taxonomy' => 'license_type',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_GET['avs_license']),
        ];
    }

    // Filter: Eligibility
    if (!empty($_GET['avs_eligibility']) && $_GET['avs_eligibility'] !== 'all') {
        $meta_query[] = [
            'key'     => 'sch_eligibility',
            'value'   => sanitize_text_field($_GET['avs_eligibility']),
            'compare' => '=',
        ];
    }

    // Filter: Location (partial match)
    if (!empty($_GET['avs_location'])) {
        $meta_query[] = [
            'key'     => 'sch_location',
            'value'   => sanitize_text_field($_GET['avs_location']),
            'compare' => 'LIKE',
        ];
    }

    // Filter: Amount Range
    if (!empty($_GET['avs_min_amount']) || !empty($_GET['avs_max_amount'])) {
        $amount_query = ['key' => 'sch_max_amount', 'type' => 'NUMERIC'];
        
        if (!empty($_GET['avs_min_amount']) && !empty($_GET['avs_max_amount'])) {
            $amount_query['value'] = [intval($_GET['avs_min_amount']), intval($_GET['avs_max_amount'])];
            $amount_query['compare'] = 'BETWEEN';
        } elseif (!empty($_GET['avs_min_amount'])) {
            $amount_query['value'] = intval($_GET['avs_min_amount']);
            $amount_query['compare'] = '>=';
        } else {
            $amount_query['value'] = intval($_GET['avs_max_amount']);
            $amount_query['compare'] = '<=';
        }
        
        $meta_query[] = $amount_query;
    }

    // Filter: Deadline Range
    if (!empty($_GET['avs_deadline_from']) || !empty($_GET['avs_deadline_to'])) {
        $deadline_query = ['key' => 'sch_deadline', 'type' => 'DATE'];
        
        if (!empty($_GET['avs_deadline_from']) && !empty($_GET['avs_deadline_to'])) {
            $deadline_query['value'] = [
                sanitize_text_field($_GET['avs_deadline_from']),
                sanitize_text_field($_GET['avs_deadline_to'])
            ];
            $deadline_query['compare'] = 'BETWEEN';
        } elseif (!empty($_GET['avs_deadline_from'])) {
            $deadline_query['value'] = sanitize_text_field($_GET['avs_deadline_from']);
            $deadline_query['compare'] = '>=';
        } else {
            $deadline_query['value'] = sanitize_text_field($_GET['avs_deadline_to']);
            $deadline_query['compare'] = '<=';
        }
        
        $meta_query[] = $deadline_query;
    }

    // Apply meta query
    if (!empty($meta_query)) {
        $args['meta_query'] = count($meta_query) > 1 ? array_merge(['relation' => 'AND'], $meta_query) : $meta_query;
    }

    // Apply tax query
    if (!empty($tax_query)) {
        $args['tax_query'] = count($tax_query) > 1 ? array_merge(['relation' => 'AND'], $tax_query) : $tax_query;
    }

    // Sorting
    $sort_by = isset($_GET['avs_sort']) ? sanitize_text_field($_GET['avs_sort']) : 'deadline_asc';
    
    switch ($sort_by) {
        case 'deadline_asc':
            $args['meta_key'] = 'sch_deadline';
            $args['orderby'] = 'meta_value';
            $args['order'] = 'ASC';
            break;
        case 'deadline_desc':
            $args['meta_key'] = 'sch_deadline';
            $args['orderby'] = 'meta_value';
            $args['order'] = 'DESC';
            break;
        case 'amount_asc':
            $args['meta_key'] = 'sch_max_amount';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'ASC';
            break;
        case 'amount_desc':
            $args['meta_key'] = 'sch_max_amount';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
            break;
        case 'title_asc':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'title_desc':
            $args['orderby'] = 'title';
            $args['order'] = 'DESC';
            break;
        default:
            $args['meta_key'] = 'sch_deadline';
            $args['orderby'] = 'meta_value';
            $args['order'] = 'ASC';
    }

    return $args;
}

/**
 * Render filters section
 */
function render_scholarship_filters($total_count) {
    
    // Get all categories and license types
    $categories = get_terms(['taxonomy' => 'sch_category', 'hide_empty' => true]);
    $licenses = get_terms(['taxonomy' => 'license_type', 'hide_empty' => true]);
    
    // Get current filter values
    $current_title = isset($_GET['avs_title']) ? sanitize_text_field($_GET['avs_title']) : '';
    $current_category = isset($_GET['avs_category']) ? sanitize_text_field($_GET['avs_category']) : '';
    $current_license = isset($_GET['avs_license']) ? sanitize_text_field($_GET['avs_license']) : '';
    $current_eligibility = isset($_GET['avs_eligibility']) ? sanitize_text_field($_GET['avs_eligibility']) : 'all';
    $current_location = isset($_GET['avs_location']) ? sanitize_text_field($_GET['avs_location']) : '';
    $current_min_amount = isset($_GET['avs_min_amount']) ? intval($_GET['avs_min_amount']) : '';
    $current_max_amount = isset($_GET['avs_max_amount']) ? intval($_GET['avs_max_amount']) : '';
    $current_deadline_from = isset($_GET['avs_deadline_from']) ? sanitize_text_field($_GET['avs_deadline_from']) : '';
    $current_deadline_to = isset($_GET['avs_deadline_to']) ? sanitize_text_field($_GET['avs_deadline_to']) : '';

    ob_start();
    ?>
    <div class="avs-filters-section" style="background:url('<?php echo plugin_dir_url(dirname(__FILE__)); ?>assets/img/aeroplane-1.jpg') no-repeat center center; background-size: cover; background-color: var(--primary);">
        <div class="avs-filters-header">
            <h3 class="avs-filters-title">Filter Scholarships</h3>
            <button type="button" class="avs-filters-toggle" id="avs-toggle-filters">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M6 12h12m-7 5h2"/></svg>
                <span>Filters</span>
            </button>
        </div>

        <form method="get" action="" class="avs-filters-form" id="avs-filters-form">
            <div class="avs-filters-grid">
                
                <!-- Title Search Filter -->
                <div class="avs-filter-group">
                    <label for="avs_title" class="avs-filter-label">Scholarship Title</label>
                    <input type="text" name="avs_title" id="avs_title" class="avs-filter-input" 
                           placeholder="Search by title..." value="<?= esc_attr($current_title); ?>">
                </div>

                <!-- Category Filter -->
                <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
                    <div class="avs-filter-group">
                        <label for="avs_category" class="avs-filter-label">Category</label>
                        <select name="avs_category" id="avs_category" class="avs-filter-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat) : ?>
                                <option value="<?= esc_attr($cat->slug); ?>" <?= selected($current_category, $cat->slug, false); ?>>
                                    <?= esc_html($cat->name); ?> (<?= $cat->count; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- License Type Filter -->
                <?php if (!empty($licenses) && !is_wp_error($licenses)) : ?>
                    <div class="avs-filter-group">
                        <label for="avs_license" class="avs-filter-label">License Type</label>
                        <select name="avs_license" id="avs_license" class="avs-filter-select">
                            <option value="">All License Types</option>
                            <?php foreach ($licenses as $lic) : ?>
                                <option value="<?= esc_attr($lic->slug); ?>" <?= selected($current_license, $lic->slug, false); ?>>
                                    <?= esc_html($lic->name); ?> (<?= $lic->count; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <!-- Eligibility Filter -->
                <div class="avs-filter-group">
                    <label for="avs_eligibility" class="avs-filter-label">Eligibility</label>
                    <select name="avs_eligibility" id="avs_eligibility" class="avs-filter-select">
                        <option value="all" <?= selected($current_eligibility, 'all', false); ?>>All</option>
                        <option value="every" <?= selected($current_eligibility, 'every', false); ?>>Everyone</option>
                        <option value="female" <?= selected($current_eligibility, 'female', false); ?>>Female Only</option>
                        <option value="financial_need" <?= selected($current_eligibility, 'financial_need', false); ?>>Demonstrated Financial Need</option>
                        <option value="minority" <?= selected($current_eligibility, 'minority', false); ?>>Minority</option>
                    </select>
                </div>
                <!-- Location Filter -->
                <div class="avs-filter-group">
                    <label for="avs_location" class="avs-filter-label">Location</label>
                    <input type="text" name="avs_location" id="avs_location" class="avs-filter-input" 
                           placeholder="Enter location..." value="<?= esc_attr($current_location); ?>">
                </div>
                <!-- Amount Range -->
                <div class="avs-filter-group">
                    <label class="avs-filter-label">Award Amount</label>
                    <div class="avs-filter-range">
                        <input type="number" name="avs_min_amount" id="avs_min_amount" class="avs-filter-input avs-range-input" 
                               placeholder="Min $" min="0" step="1000" value="<?= esc_attr($current_min_amount); ?>">
                        <span class="avs-range-separator">to</span>
                        <input type="number" name="avs_max_amount" id="avs_max_amount" class="avs-filter-input avs-range-input" 
                               placeholder="Max $" min="0" step="1000" value="<?= esc_attr($current_max_amount); ?>">
                    </div>
                </div>
                <!-- Deadline Range -->
                <div class="avs-filter-group">
                    <label class="avs-filter-label">Deadline Range</label>
                    <div class="avs-filter-range">
                        <input type="date" name="avs_deadline_from" id="avs_deadline_from" class="avs-filter-input avs-range-input" 
                               value="<?= esc_attr($current_deadline_from); ?>">
                        <span class="avs-range-separator">to</span>
                        <input type="date" name="avs_deadline_to" id="avs_deadline_to" class="avs-filter-input avs-range-input" 
                               value="<?= esc_attr($current_deadline_to); ?>">
                    </div>
                </div>
            </div>

            <!-- Filter Actions -->
            <div class="avs-filters-actions">
                <button type="submit" class="avs-filter-btn avs-filter-apply">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M6 12h12m-7 5h2"/></svg>
                    Apply Filters
                </button>
                <a href="<?= esc_url(strtok($_SERVER['REQUEST_URI'], '?')); ?>" class="avs-filter-btn avs-filter-reset">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12a9 9 0 1 0 9-9a9.75 9.75 0 0 0-6.74 2.74L3 8"/></svg>
                    Clear All
                </a>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render sort dropdown
 */
function render_sort_dropdown() {
    $current_sort = isset($_GET['avs_sort']) ? sanitize_text_field($_GET['avs_sort']) : 'deadline_asc';
    
    ob_start();
    ?>
    <div class="avs-sort-container">
        <label for="avs-sort-select" class="avs-sort-label">Sort by:</label>
        <select id="avs-sort-select" class="avs-sort-select" onchange="avsChangeSorting(this.value)">
            <option value="deadline_asc" <?= selected($current_sort, 'deadline_asc', false); ?>>Deadline (Soonest First)</option>
            <option value="deadline_desc" <?= selected($current_sort, 'deadline_desc', false); ?>>Deadline (Latest First)</option>
            <option value="amount_desc" <?= selected($current_sort, 'amount_desc', false); ?>>Award Amount (Highest First)</option>
            <option value="amount_asc" <?= selected($current_sort, 'amount_asc', false); ?>>Award Amount (Lowest First)</option>
            <option value="title_asc" <?= selected($current_sort, 'title_asc', false); ?>>Title (A-Z)</option>
            <option value="title_desc" <?= selected($current_sort, 'title_desc', false); ?>>Title (Z-A)</option>
        </select>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render pagination
 */
function render_scholarship_pagination($max_pages, $current_page) {
    if ($max_pages <= 1) return '';

    ob_start();
    ?>
    <div class="avs-pagination">
        <?php
        // Previous button
        if ($current_page > 1) {
            echo '<a href="' . esc_url(add_query_arg('avs_page', $current_page - 1)) . '" class="avs-page-link avs-page-prev">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 6l-6 6l6 6"/></svg>';
            echo 'Previous</a>';
        }

        // Page numbers
        $range = 2; // Show 2 pages on each side of current page
        for ($i = 1; $i <= $max_pages; $i++) {
            if ($i == 1 || $i == $max_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                if ($i == $current_page) {
                    echo '<span class="avs-page-link avs-page-current">' . $i . '</span>';
                } else {
                    echo '<a href="' . esc_url(add_query_arg('avs_page', $i)) . '" class="avs-page-link">' . $i . '</a>';
                }
            } elseif ($i == $current_page - $range - 1 || $i == $current_page + $range + 1) {
                echo '<span class="avs-page-link avs-page-dots">...</span>';
            }
        }

        // Next button
        if ($current_page < $max_pages) {
            echo '<a href="' . esc_url(add_query_arg('avs_page', $current_page + 1)) . '" class="avs-page-link avs-page-next">';
            echo 'Next';
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 6l6 6l-6 6"/></svg>';
            echo '</a>';
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}

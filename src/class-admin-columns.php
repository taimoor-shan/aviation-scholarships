<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

class Admin_Columns {

    public static function init() {

        // Add columns
        add_filter('manage_scholarship_posts_columns', [__CLASS__, 'add_columns']);

        // Content for columns
        add_action('manage_scholarship_posts_custom_column', [__CLASS__, 'render_column'], 10, 2);

        // Make columns sortable
        add_filter('manage_edit-scholarship_sortable_columns', [__CLASS__, 'sortable_columns']);

        // Sorting logic
        add_action('pre_get_posts', [__CLASS__, 'handle_sorting']);

        // Taxonomy dropdown filters
        add_action('restrict_manage_posts', [__CLASS__, 'filter_dropdowns']);
    }


    /* --------------------------
     * 1. Add Custom Columns
     * -------------------------- */
    public static function add_columns($columns) {

        // Move date to end
        unset($columns['date']);

        $columns['sch_deadline']     = 'Deadline';
        $columns['sch_max_amount']   = 'Amount';
        $columns['sch_category']     = 'Category';
        $columns['license_type']     = 'License Types';
        $columns['sch_eligibility']  = 'Eligibility';

        // Add back Date
        $columns['date'] = 'Date';

        return $columns;
    }


    /* --------------------------
     * 2. Render Column Values
     * -------------------------- */
    public static function render_column($column, $post_id) {

        switch ($column) {

            case 'sch_deadline':
                $deadline = get_field('sch_deadline', $post_id);
                echo $deadline ? esc_html($deadline) : '—';
                break;

            case 'sch_max_amount':
                $amount = get_field('sch_max_amount', $post_id);
                echo $amount ? '$'.number_format($amount) : '—';
                break;

            case 'sch_category':
                $terms = get_the_terms($post_id, 'sch_category');
                echo $terms ? esc_html($terms[0]->name) : '—';
                break;

            case 'license_type':
                $terms = get_the_terms($post_id, 'license_type');
                if ($terms) {
                    $names = wp_list_pluck($terms, 'name');
                    echo implode(', ', $names);
                } else {
                    echo '—';
                }
                break;

            case 'sch_eligibility':
                $elig = get_field('sch_eligibility', $post_id);
                echo $elig ? ucfirst($elig) : '—';
                break;
        }
    }


    /* --------------------------
     * 3. Make Columns Sortable
     * -------------------------- */
    public static function sortable_columns($columns) {

        $columns['sch_deadline']   = 'sch_deadline';
        $columns['sch_max_amount'] = 'sch_max_amount';

        return $columns;
    }


    /* --------------------------
     * 4. Sorting Logic
     * -------------------------- */
    public static function handle_sorting($query) {

        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'sch_deadline') {
            $query->set('meta_key', 'sch_deadline');
            $query->set('orderby', 'meta_value');
        }

        if ($orderby === 'sch_max_amount') {
            $query->set('meta_key', 'sch_max_amount');
            $query->set('orderby', 'meta_value_num');
        }

        // Handle eligibility filter
        if (isset($_GET['sch_eligibility']) && $_GET['sch_eligibility'] !== '') {
            $meta_query = $query->get('meta_query') ?: [];
            $meta_query[] = [
                'key'     => 'sch_eligibility',
                'value'   => sanitize_text_field($_GET['sch_eligibility']),
                'compare' => '='
            ];
            $query->set('meta_query', $meta_query);
        }
    }


    /* --------------------------
     * 5. Taxonomy Filter Dropdowns
     * -------------------------- */
    public static function filter_dropdowns() {

        global $typenow;

        if ($typenow !== 'scholarship') return;

        // Category
        self::render_dropdown('sch_category', 'Category');

        // License Types
        self::render_dropdown('license_type', 'License Type');

        // Eligibility (ACF select)
        $selected = isset($_GET['sch_eligibility']) ? $_GET['sch_eligibility'] : '';

        ?>
            <select name="sch_eligibility">
                <option value="">Eligibility</option>
                <option value="every" <?php selected($selected, 'every'); ?>>Everyone</option>
                <option value="female" <?php selected($selected, 'female'); ?>>Female</option>
                <option value="minority" <?php selected($selected, 'minority'); ?>>Minority</option>
            </select>
        <?php
    }

    public static function render_dropdown($taxonomy, $label) {

        $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';

        wp_dropdown_categories([
            'taxonomy'         => $taxonomy,
            'name'             => $taxonomy,
            'show_option_all'  => $label,
            'selected'         => $selected,
            'hide_empty'       => false,
            'hierarchical'     => false,
            'show_count'       => false,
            'value_field'      => 'slug'
        ]);
    }
}

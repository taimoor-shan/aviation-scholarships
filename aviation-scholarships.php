<?php
/**
 * Plugin Name: Aviation Scholarships
 * Description: Registers the Scholarship CPT and Aviation Taxonomies.
 * Version: 1.0.4
 * Author: Muhammad
 */

if (!defined('ABSPATH')) exit;

// Load core classes
require_once plugin_dir_path(__FILE__) . 'src/class-register-scholarship.php';
require_once plugin_dir_path(__FILE__) . 'src/class-register-taxonomies.php';
require_once plugin_dir_path(__FILE__) . 'src/class-importer.php';
require_once plugin_dir_path(__FILE__) . 'src/class-assets.php';

// Load email reminder system classes
require_once plugin_dir_path(__FILE__) . 'src/class-reminder-database.php';
require_once plugin_dir_path(__FILE__) . 'src/class-reminder-email.php';
require_once plugin_dir_path(__FILE__) . 'src/class-reminder-manager.php';
require_once plugin_dir_path(__FILE__) . 'src/class-reminder-dashboard.php';

// Activation: flush rewrites
function avs_activate_plugin() {
    // Register CPT & taxonomies first
    \Aviation_Scholarships\Register_Scholarship::register();
    \Aviation_Scholarships\Register_Taxonomies::register();
    flush_rewrite_rules();
    
    // Create reminder database table
    $reminder_db = new \Aviation_Scholarships\Reminder_Database();
    $reminder_db->create_table();
}
register_activation_hook(__FILE__, 'avs_activate_plugin');

// Deactivation
function avs_deactivate_plugin() {
    // Clear scheduled cron jobs
    wp_clear_scheduled_hook('avs_hourly_import');
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'avs_deactivate_plugin');

// Init hooks
add_action('init', ['Aviation_Scholarships\Register_Scholarship', 'register']);
add_action('init', ['Aviation_Scholarships\Register_Taxonomies', 'register']);

// Initialize Importer (handles admin forms, webhooks, and cron)
new \Aviation_Scholarships\Importer();

// ACF fields
require_once plugin_dir_path(__FILE__) . 'src/class-acf-fields.php';

add_action('acf/init', ['Aviation_Scholarships\ACF_Fields', 'register']);


// Admin columns
require_once plugin_dir_path(__FILE__) . 'src/class-admin-columns.php';
add_action('init', ['Aviation_Scholarships\Admin_Columns', 'init']);

// Assets (CSS & JS)
add_action('init', ['Aviation_Scholarships\Assets', 'init']);

// Initialize Email Reminder System
add_action('init', ['Aviation_Scholarships\Reminder_Manager', 'init']);


// Load CLI class (only in CLI mode)
if (defined('WP_CLI') && WP_CLI) {
    require_once plugin_dir_path(__FILE__) . 'src/class-importer-cli.php';
}


// Admin Settings
require_once plugin_dir_path(__FILE__) . 'src/class-settings-page.php';
add_action('init', ['Aviation_Scholarships\Settings_Page', 'init']);

// Email Reminder Dashboard
add_action('init', ['Aviation_Scholarships\Reminder_Dashboard', 'init']);

// Disable Gutenberg editor for scholarship post type
add_filter('use_block_editor_for_post_type', 'avs_disable_gutenberg_for_scholarships', 10, 2);
function avs_disable_gutenberg_for_scholarships($use_block_editor, $post_type) {
    if ($post_type === 'scholarship') {
        return false;
    }
    return $use_block_editor;
}

// Remove content editor completely for scholarship post type (title + ACF fields only)
add_action('init', 'avs_remove_editor_for_scholarships');
function avs_remove_editor_for_scholarships() {
    remove_post_type_support('scholarship', 'editor');
}

// Prevent GeneratePress/GenerateBlocks from interfering with scholarship post type
add_filter('generateblocks_post_types', 'avs_disable_generateblocks_for_scholarships');
function avs_disable_generateblocks_for_scholarships($post_types) {
    if (($key = array_search('scholarship', $post_types)) !== false) {
        unset($post_types[$key]);
    }
    return $post_types;
}

// Remove GeneratePress Layout meta box from scholarship edit screen
add_action('add_meta_boxes', 'avs_remove_generatepress_metaboxes', 99);
function avs_remove_generatepress_metaboxes() {
    remove_meta_box('generate_layout_options_meta_box', 'scholarship', 'side');
    remove_meta_box('generateblocks_custom_css', 'scholarship', 'normal');
}

// Auto-sync cron management
add_action('admin_init', 'avs_manage_cron');
add_action('avs_hourly_import', 'avs_run_hourly_import');

/**
 * Component: Helpers & Templates
 */

// Load compact card template (primary template)
require_once plugin_dir_path(__FILE__) . 'src/helpers-template-compact.php';

require_once plugin_dir_path(__FILE__) . 'src/shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'src/shortcode-all-scholarships.php';

add_shortcode('recent_scholarships', 'Aviation_Scholarships\shortcode_recent_scholarships');
add_shortcode('recent_scholarships_compact', 'Aviation_Scholarships\shortcode_recent_scholarships_compact');
add_shortcode('closing_soon_scholarships', 'Aviation_Scholarships\shortcode_closing_soon_scholarships');
add_shortcode('closing_soon_scholarships_compact', 'Aviation_Scholarships\shortcode_closing_soon_scholarships_compact');
add_shortcode('all_scholarships', 'Aviation_Scholarships\shortcode_all_scholarships');



/**
 * Manage auto-sync cron schedule based on settings
 */
function avs_manage_cron() {
    $auto_sync = get_option('avs_auto_sync', 'no');
    $scheduled = wp_next_scheduled('avs_hourly_import');
    
    if ($auto_sync === 'yes' && !$scheduled) {
        wp_schedule_event(time(), 'hourly', 'avs_hourly_import');
    } elseif ($auto_sync !== 'yes' && $scheduled) {
        wp_clear_scheduled_hook('avs_hourly_import');
    }
}

/**
 * Execute hourly import from configured Google Sheet URL
 */
function avs_run_hourly_import() {
    $importer = new \Aviation_Scholarships\Importer();
    $importer->run_auto_sync();
}

/**
 * Hook to clean up reminder records when a scholarship is deleted
 */
add_action('before_delete_post', 'avs_cleanup_scholarship_reminders');
function avs_cleanup_scholarship_reminders($post_id) {
    if (get_post_type($post_id) === 'scholarship') {
        $reminder_db = new \Aviation_Scholarships\Reminder_Database();
        $reminder_db->delete_scholarship_reminders($post_id);
    }
}

/**
 * Hook to reset reminders when scholarship deadline is updated
 */
add_action('acf/save_post', 'avs_reset_reminders_on_deadline_change', 20);
function avs_reset_reminders_on_deadline_change($post_id) {
    // Only for scholarship post type
    if (get_post_type($post_id) !== 'scholarship') {
        return;
    }
    
    // Check if deadline was modified
    $new_deadline = get_field('sch_deadline', $post_id);
    $old_deadline = get_post_meta($post_id, '_old_deadline', true);
    
    if ($new_deadline && $old_deadline && $new_deadline !== $old_deadline) {
        // Deadline changed, reset reminders for this scholarship
        $manager = new \Aviation_Scholarships\Reminder_Manager();
        $manager->reset_scholarship_reminders($post_id);
    }
    
    // Store current deadline for next comparison
    if ($new_deadline) {
        update_post_meta($post_id, '_old_deadline', $new_deadline);
    }
}





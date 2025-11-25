<?php
/**
 * Plugin Name: Aviation Scholarships
 * Description: Registers the Scholarship CPT and Aviation Taxonomies.
 * Version: 1.0.2
 * Author: Muhammad
 */

if (!defined('ABSPATH')) exit;

// Load core classes
require_once plugin_dir_path(__FILE__) . 'src/class-register-scholarship.php';
require_once plugin_dir_path(__FILE__) . 'src/class-register-taxonomies.php';
require_once plugin_dir_path(__FILE__) . 'src/class-importer.php';
require_once plugin_dir_path(__FILE__) . 'src/class-assets.php';

// Activation: flush rewrites
function avs_activate_plugin() {
    // Register CPT & taxonomies first
    \Aviation_Scholarships\Register_Scholarship::register();
    \Aviation_Scholarships\Register_Taxonomies::register();
    flush_rewrite_rules();
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


// Load CLI class (only in CLI mode)
if (defined('WP_CLI') && WP_CLI) {
    require_once plugin_dir_path(__FILE__) . 'src/class-importer-cli.php';
}


// Admin Settings
require_once plugin_dir_path(__FILE__) . 'src/class-settings-page.php';
add_action('init', ['Aviation_Scholarships\Settings_Page', 'init']);

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





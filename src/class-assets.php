<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

class Assets {

    /**
     * Initialize asset loading
     */
    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('admin_head', [__CLASS__, 'inject_dynamic_css']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
    }

    /**
     * Enqueue admin CSS and JS files
     * 
     * @param string $hook Current admin page hook
     */
    public static function enqueue_admin_assets($hook) {
        
        // Get current screen
        $screen = get_current_screen();
        
        // Only load on scholarship-related pages
        if (!self::is_scholarship_page($screen, $hook)) {
            return;
        }

        // Plugin version for cache busting
        $version = '1.0.0';
        $plugin_url = plugin_dir_url(dirname(__FILE__));

        // Enqueue admin CSS
        wp_enqueue_style(
            'avs-admin-styles',
            $plugin_url . 'assets/css/admin-styles.css',
            [],
            $version,
            'all'
        );

        // Enqueue admin JS
        wp_enqueue_script(
            'avs-admin-scripts',
            $plugin_url . 'assets/js/admin-scripts.js',
            ['jquery'],
            $version,
            true
        );

        // Localize script with data
        wp_localize_script('avs-admin-scripts', 'AVS_Admin_Data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('avs_admin_nonce'),
            'strings'  => [
                'confirm_import' => __('Are you sure you want to run this import?', 'aviation-scholarships'),
                'import_success' => __('Import completed successfully!', 'aviation-scholarships'),
                'import_error'   => __('Import failed. Please check the logs.', 'aviation-scholarships'),
            ]
        ]);
    }

    /**
     * Check if current page is scholarship-related
     * 
     * @param object $screen WP_Screen object
     * @param string $hook Current page hook
     * @return bool
     */
    private static function is_scholarship_page($screen, $hook) {
        
        // Scholarship post type pages
        if ($screen && $screen->post_type === 'scholarship') {
            return true;
        }

        // Scholarship taxonomy pages
        if ($screen && in_array($screen->taxonomy, ['sch_category', 'license_type'])) {
            return true;
        }

        // Scholarship admin pages (Import Settings, Import Logs)
        $scholarship_pages = [
            'scholarship_page_avs-import-settings',
            'scholarship_page_avs-import-logs',
        ];

        if ($screen && in_array($screen->id, $scholarship_pages)) {
            return true;
        }

        // Check hook-based pages
        if (strpos($hook, 'avs-') !== false || strpos($hook, 'scholarship') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Get plugin version (for cache busting)
     * 
     * @return string
     */
    public static function get_version() {
        // Read from main plugin file header
        $plugin_data = get_file_data(
            dirname(dirname(__FILE__)) . '/aviation-scholarships.php',
            ['Version' => 'Version']
        );
        
        return $plugin_data['Version'] ?? '1.0.0';
    }

    /**
     * Enqueue frontend CSS for scholarship cards
     */
    public static function enqueue_frontend_assets() {
        // Only load if we're displaying scholarships
        // This will load on scholarship archives, single pages, or pages with shortcode
        if (is_post_type_archive('scholarship') || is_singular('scholarship') || has_shortcode(get_post()->post_content ?? '', 'recent_scholarships')) {
            $version = '1.0.0';
            $plugin_url = plugin_dir_url(dirname(__FILE__));

            wp_enqueue_style(
                'avs-frontend-cards',
                $plugin_url . 'assets/css/frontend-cards.css',
                [],
                $version,
                'all'
            );

            // Inject GeneratePress colors for frontend as well
            add_action('wp_head', [__CLASS__, 'inject_frontend_dynamic_css']);
        }
    }

    /**
     * Inject dynamic CSS with GeneratePress theme colors
     * This allows the plugin to match the theme's color scheme
     */
    public static function inject_dynamic_css() {
        $screen = get_current_screen();
        
        // Only inject on scholarship pages
        if (!self::is_scholarship_page($screen, '')) {
            return;
        }

        // Get GeneratePress theme colors
        $colors = self::get_generatepress_colors();
        
        // Generate dynamic CSS
        ?>
        <style id="avs-dynamic-colors">
            :root {
                /* GeneratePress Theme Colors */
                --gp-primary-color: <?php echo esc_attr($colors['primary']); ?>;
                --gp-accent-color: <?php echo esc_attr($colors['accent']); ?>;
                --gp-text-color: <?php echo esc_attr($colors['text']); ?>;
                --gp-link-color: <?php echo esc_attr($colors['link']); ?>;
                --gp-link-hover-color: <?php echo esc_attr($colors['link_hover']); ?>;
            }

            /* Apply theme colors to plugin elements */
            .avs-section {
                border-color: <?php echo esc_attr($colors['primary']); ?>;
            }

            .avs-section h2 {
                color: <?php echo esc_attr($colors['text']); ?>;
                border-bottom-color: <?php echo esc_attr($colors['primary']); ?>;
            }

            .avs-import-summary ul li:before {
                color: <?php echo esc_attr($colors['accent']); ?>;
            }

            .avs-webhook-code {
                color: <?php echo esc_attr($colors['link']); ?>;
                border-color: <?php echo esc_attr($colors['primary']); ?>;
            }

            .button.button-primary {
                background-color: <?php echo esc_attr($colors['primary']); ?>;
                border-color: <?php echo esc_attr($colors['primary']); ?>;
            }

            .button.button-primary:hover {
                background-color: <?php echo esc_attr($colors['link_hover']); ?>;
                border-color: <?php echo esc_attr($colors['link_hover']); ?>;
            }

            .avs-log-level.info {
                background: <?php echo esc_attr(self::adjust_color_lightness($colors['accent'], 0.9)); ?>;
                color: <?php echo esc_attr($colors['accent']); ?>;
            }

            /* Links using theme colors */
            .wrap a {
                color: <?php echo esc_attr($colors['link']); ?>;
            }

            .wrap a:hover {
                color: <?php echo esc_attr($colors['link_hover']); ?>;
            }
        </style>
        <?php
    }

    /**
     * Get GeneratePress theme colors from customizer
     * 
     * @return array Associative array of color values
     */
    private static function get_generatepress_colors() {
        // Default fallback colors
        $defaults = [
            'primary'     => '#1e73be',
            'accent'      => '#46b450',
            'text'        => '#3c434a',
            'link'        => '#2271b1',
            'link_hover'  => '#135e96',
        ];

        // Check if GeneratePress theme is active
        $theme = wp_get_theme();
        $is_generatepress = ($theme->get('Name') === 'GeneratePress' || $theme->get('Template') === 'generatepress');

        if (!$is_generatepress) {
            return $defaults;
        }

        // Get GeneratePress color settings from theme mods
        $colors = [
            'primary'     => get_theme_mod('generate_settings', [])['link_color'] ?? 
                            get_theme_mod('link_color') ?? 
                            $defaults['primary'],
            'accent'      => get_theme_mod('generate_settings', [])['form_button_background_color'] ?? 
                            get_theme_mod('form_button_background_color') ?? 
                            $defaults['accent'],
            'text'        => get_theme_mod('generate_settings', [])['text_color'] ?? 
                            get_theme_mod('text_color') ?? 
                            $defaults['text'],
            'link'        => get_theme_mod('generate_settings', [])['link_color'] ?? 
                            get_theme_mod('link_color') ?? 
                            $defaults['link'],
            'link_hover'  => get_theme_mod('generate_settings', [])['link_color_hover'] ?? 
                            get_theme_mod('link_color_hover') ?? 
                            $defaults['link_hover'],
        ];

        // Sanitize colors (ensure they have # prefix)
        foreach ($colors as $key => $color) {
            if (!empty($color) && strpos($color, '#') !== 0) {
                $colors[$key] = '#' . $color;
            }
        }

        return $colors;
    }

    /**
     * Adjust color lightness for variations
     * 
     * @param string $hex Hex color code
     * @param float $percent Lightness adjustment (0-1)
     * @return string Adjusted hex color
     */
    private static function adjust_color_lightness($hex, $percent) {
        // Remove # if present
        $hex = str_replace('#', '', $hex);
        
        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Adjust lightness
        $r = round($r + (255 - $r) * $percent);
        $g = round($g + (255 - $g) * $percent);
        $b = round($b + (255 - $b) * $percent);
        
        // Convert back to hex
        $r = str_pad(dechex(max(0, min(255, $r))), 2, '0', STR_PAD_LEFT);
        $g = str_pad(dechex(max(0, min(255, $g))), 2, '0', STR_PAD_LEFT);
        $b = str_pad(dechex(max(0, min(255, $b))), 2, '0', STR_PAD_LEFT);
        
        return '#' . $r . $g . $b;
    }

    /**
     * Inject dynamic CSS with GeneratePress theme colors for frontend
     */
    public static function inject_frontend_dynamic_css() {
        // Get GeneratePress theme colors
        $colors = self::get_generatepress_colors();
        
        ?>
        <style id="avs-frontend-dynamic-colors">
            :root {
                --gp-primary-color: <?php echo esc_attr($colors['primary']); ?>;
                --gp-accent-color: <?php echo esc_attr($colors['accent']); ?>;
                --gp-text-color: <?php echo esc_attr($colors['text']); ?>;
                --gp-link-color: <?php echo esc_attr($colors['link']); ?>;
                --gp-link-hover-color: <?php echo esc_attr($colors['link_hover']); ?>;
            }
        </style>
        <?php
    }
}

<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

class Register_Taxonomies {

    public static function register() {

        // Scholarship Category (Flight Training, Engineering, Dispatcher, etc.)
        register_taxonomy('sch_category', 'scholarship', [
            'labels' => [
                'name' => 'Scholarship Categories',
                'singular_name' => 'Scholarship Category'
            ],
            'public' => true,
            'hierarchical' => false,
            'show_in_rest' => true,
        ]);

        // License Types (Private, Instrument, Commercial, Multi-Engine, CFI...)
        register_taxonomy('license_type', 'scholarship', [
            'labels' => [
                'name' => 'License Types',
                'singular_name' => 'License Type'
            ],
            'public' => true,
            'hierarchical' => false,
            'show_in_rest' => true,
        ]);
    }
}

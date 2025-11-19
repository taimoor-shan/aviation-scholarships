<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

class Register_Scholarship {

    public static function register() {

        $labels = [
            'name'               => 'Scholarships',
            'singular_name'      => 'Scholarship',
            'add_new'            => 'Add New Scholarship',
            'add_new_item'       => 'Add New Scholarship',
            'edit_item'          => 'Edit Scholarship',
            'new_item'           => 'New Scholarship',
            'view_item'          => 'View Scholarship',
            'search_items'       => 'Search Scholarships',
            'not_found'          => 'No scholarships found',
            'not_found_in_trash' => 'No scholarships found in trash',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'show_in_rest'       => true,   // Gutenberg + REST API
            'menu_icon'          => 'dashicons-airplane',
            'supports'           => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
            'has_archive'        => true,
            'rewrite'            => ['slug' => 'scholarships'],
        ];

        register_post_type('scholarship', $args);
    }
}

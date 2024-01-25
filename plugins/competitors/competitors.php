<?php
/**
 * Plugin Name: Competitors
 * Description:  A RollSM registering and scoreboard plugin.
 * Version: 0.4
 * Author: Tdude
 */

// Include admin and public page functionalities
include_once plugin_dir_path(__FILE__) . 'admin-page.php';
include_once plugin_dir_path(__FILE__) . 'public-page.php';


// On plugin activation
function competitors_activate() {
    // Execute only on activation
    function create_competitors_post_type() {
        register_post_type('competitors', array(
            'labels' => array(
                'name' => __('Competitors', 'competitors-plugin'),
                'singular_name' => __('Competitor custom post type', 'competitors-plugin')
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'custom-fields'),
        ));
    }
    add_action('init', 'create_competitors_post_type'); 
    flush_rewrite_rules();   
}
register_activation_hook(__FILE__, 'competitors_activate');


// On deactivation
function competitors_deactivate() {
    if ( is_plugin_active('competitors/competitors.php') ) {
        deactivate_plugins('competitors/competitors.php');
    }
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'competitors_deactivate');


// Enqueue Scripts and Styles
function competitors_enqueue_scripts() {
    wp_enqueue_style('competitors-style', plugins_url('assets/style.css', __FILE__));
    wp_enqueue_script('competitors-script', plugins_url('assets/script.js', __FILE__));
}
add_action('wp_enqueue_scripts', 'competitors_enqueue_scripts');








// Admin menu for judges and settings page
function competitors_add_admin_menu() {
    add_menu_page(
        'Competitors Custom Settings',  // Page title
        'Competitors Settings',         // Menu title
        'manage_options',               // Capability
        'competitors-settings',         // Menu slug
        'competitors_settings_page',    // Function to display the page
        'dashicons-groups',             // Icon (optional)
        3,                              //Position (optional)
    );
}
add_action('admin_menu', 'competitors_add_admin_menu');

// Submenu
function competitors_add_submenu_pages() {
    add_submenu_page(
        'competitors-settings',        // Parent slug
        'Competitors Data',             // Page title
        'manage_options',               // Capability required
        'competitors-data',             // Menu slug
        'competitors_admin_page',       // Callback function
        'dashicons-settings',           // Dash öööhh, icon
        //2,                            // Position (optional)
    );
}
add_action('admin_menu', 'competitors_add_submenu_pages');


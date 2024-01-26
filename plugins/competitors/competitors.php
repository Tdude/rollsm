<?php
/**
 * Plugin Name: Competitors
 * Description:  A RollSM registering and scoreboard plugin.
 * Version: 0.41
 * Author: Tdude
 */

 // Include admin and public page functionalities
include_once plugin_dir_path(__FILE__) . 'admin-page.php';
include_once plugin_dir_path(__FILE__) . 'public-page.php';


// Enqueue Scripts and Styles
function competitors_enqueue_scripts() {
    wp_enqueue_style('competitors-style', plugins_url('assets/style.css', __FILE__));
    wp_enqueue_script('competitors-script', plugins_url('assets/script.js', __FILE__));
}
add_action('wp_enqueue_scripts', 'competitors_enqueue_scripts');

// Add styling to the admin only
function competitors_admin_styles($hook) {
    // Only add to the competitors admin page
    if ('toplevel_page_competitors-settings' !== $hook) {
        return;
    }
    // Path to plugin CSS file
    wp_enqueue_style('competitors_admin_css', plugin_dir_url(__FILE__) . 'assets/admin.css');
}
add_action('admin_enqueue_scripts', 'competitors_admin_styles');



// Register Custom Post Type
function create_competitors_post_type() {
    register_post_type('competitors', array(
        'labels' => array(
            'name' => __('Competitors', 'competitors-plugin'),
            'singular_name' => __('Competitor', 'competitors-plugin')
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
    ));
}
add_action('init', 'create_competitors_post_type');


// Flush rewrite rules on plugin activation
function competitors_activate() {
    create_competitors_post_type(); // Ensure CPT is registered
    flush_rewrite_rules(); // Then flush rewrite rules
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



// Admin menu for judges and settings page. Order of the params is important.
function competitors_add_admin_menu() {
    add_menu_page(
        'Competitors Custom Settings',  // Page title
        'Competitors admin',            // Menu title
        'manage_options',               // Capability
        'competitors-settings',         // Menu slug
        'competitors_settings_page',    // Function to display the page
        'dashicons-groups'              // Icon (optional)
        //3,                            //Position (optional)
    );
}
add_action('admin_menu', 'competitors_add_admin_menu');



// Submenu (WP needs two items to show sub menu items!)
function competitors_add_submenu_settings() {
    add_submenu_page(
        'competitors-settings',        // Parent slug
        'Competitors Detailed Data',   // Page title
        'Detailed Data',               // Menu title (changed to avoid duplication)
        'manage_options',              // Capability
        'competitors-detailed-data',   // Menu slug (different from the parent slug)
        'competitors_admin_page'       // Callback function
    );
}
add_action('admin_menu', 'competitors_add_submenu_settings');



function competitors_add_submenu_scoring() {
    add_submenu_page(
        'competitors-settings',       // Parent slug (should match the main menu's slug)
        'Judges scoring submenu',     // Page title
        'Judges Scoring',             // Menu title
        'manage_options',             // Capability
        'competitors-scoring',        // Menu slug
        'judges_scoring_page'         // Callback function
    );
}
add_action('admin_menu', 'competitors_add_submenu_scoring');




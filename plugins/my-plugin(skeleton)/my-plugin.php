<?php
/**
 * Plugin Name: My Plugin
 * Description: This is a basic WordPress plugin outline.
 * Version: 1.0
 * Author: Your Name
 */

// Include admin and public page functionalities
include_once plugin_dir_path(__FILE__) . 'admin-page.php';
include_once plugin_dir_path(__FILE__) . 'public-page.php';

// Activation and Deactivation Hooks
register_activation_hook(__FILE__, 'my_plugin_activate');
register_deactivation_hook(__FILE__, 'my_plugin_deactivate');

function my_plugin_activate() {
    // Code to execute on plugin activation
}

function my_plugin_deactivate() {
    // Code to execute on plugin deactivation
}

// Enqueue Scripts and Styles
function my_plugin_enqueue_scripts() {
    wp_enqueue_style('my-plugin-style', plugins_url('assets/style.css', __FILE__));
    wp_enqueue_script('my-plugin-script', plugins_url('assets/script.js', __FILE__));
}
add_action('wp_enqueue_scripts', 'my_plugin_enqueue_scripts');

<?php
/**
 * Plugin Name: Competitors Plugin
 * Description: A not-so-basic plugin to manage competitors.
 * Version: 0.3
 * Author: Tdude via CHatGPT which has helpful one-liners but can't program...
 * Text Domain: competitors-plugin
 */

// Include admin and public page functionalities
include_once plugin_dir_path(__FILE__) . 'admin-page.php';
include_once plugin_dir_path(__FILE__) . 'public-page.php';

// Activation and Deactivation Hooks
register_activation_hook(__FILE__, 'competitors_activate');
register_deactivation_hook(__FILE__, 'competitors_deactivate');

function competitors_activate() {
    // Code to execute on plugin activation
}

function competitors_deactivate() {
    // Code to execute on plugin deactivation
}

// Enqueue Scripts and Styles
function competitors_enqueue_scripts() {
    wp_enqueue_style('my-plugin-style', plugins_url('assets/style.css', __FILE__));
    wp_enqueue_script('my-plugin-script', plugins_url('assets/script.js', __FILE__));
}
add_action('wp_enqueue_scripts', 'competitors_enqueue_scripts');



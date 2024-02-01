<?php
/**
 * Plugin Name: Competitors
 * Description:  A RollSM registering and scoreboard plugin.
 * Version: 0.54
 * Author: Tdude
 */
define('COMPETITORS_PLUGIN_VERSION', '0.54');


 // Include admin and public page functionalities
include_once plugin_dir_path(__FILE__) . 'admin-page.php';
include_once plugin_dir_path(__FILE__) . 'public-page.php';


// Enqueue PUBLIC
function competitors_enqueue_scripts() {
    wp_enqueue_style('competitors-style', plugins_url('assets/style.css', __FILE__));
    wp_enqueue_style('dashicons');

    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
    wp_enqueue_script('competitors_scoring_view_page', plugins_url('assets/script.js', __FILE__), array('jquery'), COMPETITORS_PLUGIN_VERSION, true);
    wp_localize_script('competitors_scoring_view_page', 'competitorsAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('competitors_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'competitors_enqueue_scripts');


// Enqueue ADMIN
function competitors_enqueue_admin_scripts() {
    wp_enqueue_style('competitors-admin-style', plugins_url('assets/admin.css', __FILE__));
    wp_enqueue_script('competitors_admin_page', plugins_url('assets/admin-script.js', __FILE__), array('jquery'), COMPETITORS_PLUGIN_VERSION, true);
    wp_localize_script('competitors_admin_page', 'competitorsData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('competitors_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'competitors_enqueue_admin_scripts');


// Register Custom Post Type
function create_competitors_post_type() {
    register_post_type('competitors', array(
        'labels' => array(
            'name' => __('Competitors', 'competitors-plugin'),
            'singular_name' => __('Competitor', 'competitors-plugin')
        ),
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
        'menu_icon' => 'dashicons-groups',

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


// Admin menu for judges and settings page. Order of the params is important. See https://developer.wordpress.org/reference/functions/add_menu_page/
function competitors_add_admin_menu() {
    add_menu_page(
        'Competitors custom settings',  // Page title
        'Competitors settings',         // Menu title
        'manage_options',               // Capability
        'competitors-settings',         // Menu slug
        'competitors_settings_page',    // Function to display the page
        'dashicons-clipboard',          // Icon (optional)
        27                              // Position (in quintuples)
    );
}
add_action('admin_menu', 'competitors_add_admin_menu');


// Submenu (WP needs two items to show sub menu items!)
function competitors_add_submenu_settings() {
    add_submenu_page(
        'competitors-settings',        // Parent slug
        'Competitors personal data',   // Page title
        'Personal data',               // Menu title (changed to avoid duplication)
        'manage_options',              // Capability
        'competitors-detailed-data',   // Menu slug (different from the parent slug)
        'competitors_admin_page'       // Callback function
    );
}
add_action('admin_menu', 'competitors_add_submenu_settings');


function competitors_add_submenu_scoring() {
    add_submenu_page(
        'competitors-settings',
        'Judges scoring submenu',
        'Judges scoring',
        'manage_options',
        'competitors-scoring',
        'judges_scoring_page'     
    );
}
add_action('admin_menu', 'competitors_add_submenu_scoring');



function competitors_add_submenu_scoring_view() {
    add_submenu_page(
        'null',                         
        'Scoring view page',            
        'Individual scoring',           
        'manage_options',               
        'competitors-view',             
        'competitors_scoring_view_page',
    );
}
add_action('admin_menu', 'competitors_add_submenu_scoring_view');


// Display the settings page content
function competitors_settings_page() {
    if (!current_user_can('manage_options')) {
        echo 'Access denied to settings dude, sorry.';
        return;
    }
    // Check if our transient is set and display the message
    if (get_transient('competitors_form_submitted')) {
        echo '<div id="message" class="updated notice is-dismissible"><p>' . get_transient('competitors_form_submitted') . '</p></div>';
        // Delete the transient so the message doesn't keep appearing
        delete_transient('competitors_form_submitted');
    }
    echo '<div class="wrap"><h1>Competitors rolls and settings</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('competitors_settings');
    do_settings_sections('competitors_settings');
    submit_button();
    echo '</form></div>';
}


// Register a new setting for our "Competitors" page
function competitors_settings_init() {
    register_setting(
        'competitors_settings',
        'competitors_custom_values',
        'competitors_custom_values_sanitize' // Custom sanitize callback
    );

    add_settings_section(
        'competitors_custom_values_section',
        __('Custom Values for roll names. One roll name on each row. Separate with "Enter"', 'wordpress'),
        'competitors_settings_section_callback',
        'competitors_settings'
    );

    add_settings_field(
        'competitors_text_field',
        __('Values', 'wordpress'), 
        'competitors_text_field_render',
        'competitors_settings',
        'competitors_custom_values_section'
    );
}
add_action('admin_init', 'competitors_settings_init');



function competitors_settings_section_callback() {
    echo ('<p>');
    echo __('These values correspond to what rolls competitors check with check boxes on the front end, as well as the admin area. ', 'wordpress');
    echo __('You can display either the registration form for competitors or the results on any WP Post or Page with shortcodes. [competitors_form_public] or (COMING SOON!) [competitors_score_public]', 'wordpress');
    echo '</p>';
}



function competitors_text_field_render() {
    $roll_names = get_option('competitors_custom_values');
    if (!is_array($roll_names)) {
        $roll_names = [$roll_names];
    }

    echo '<div id="competitors_roll_names_wrapper">';
    foreach ($roll_names as $index => $roll_name) {
        echo '<p>';
        echo '<input type="text" name="competitors_custom_values[]" size="60" value="' . esc_attr($roll_name) . '" />';
        if ($index === 0) {
            echo '<button type="button" id="add_more_roll_names" class="button button-primary custom-button"></button>';
        }
        echo '</p>';
    }
    echo '</div>';

}


function competitors_custom_values_sanitize($input) {
    return array_map('sanitize_text_field', $input);
}

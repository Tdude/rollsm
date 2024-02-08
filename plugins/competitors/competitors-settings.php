<?php
/**
 * Plugin Name: Competitors
 * Description:  A RollSM registering and scoreboard plugin.
 * Version: 0.63
 * Author: Tdude
 */
define('COMPETITORS_PLUGIN_VERSION', '0.7');

// Un-clutter color picker for all non-admins
function remove_color_scheme_for_non_admins() {
    // Check if the current user is not an administrator
    if (!current_user_can('manage_options')) {
        // Remove the color scheme picker
        remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');
    }
}
add_action('admin_init', 'remove_color_scheme_for_non_admins');


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
        'ajaxurl' => admin_url('admin-ajax.php'), // Ensure exact same, ajaxurl and nonce, param names in the JS.
        'nonce' => wp_create_nonce('competitors_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'competitors_enqueue_scripts');



// Enqueue ADMIN. The competitors_admin_page is just for WP ref, not the functions.
function competitors_enqueue_admin_scripts() {
    wp_enqueue_style('competitors-admin-style', plugins_url('assets/admin.css', __FILE__));
    wp_enqueue_script('competitors_admin_page', plugins_url('assets/admin-script.js', __FILE__), array('jquery'), COMPETITORS_PLUGIN_VERSION, true);
    wp_localize_script('competitors_admin_page', 'competitorsData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('competitors_nonce_action')
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
        'has_archive' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
        'menu_icon' => 'dashicons-groups'
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



// This is bullshit but added just in case of sour admin privs. Runs on activation of plugin.
function add_custom_capabilities() {
    // Get the administrator role.
    $role = get_role('administrator');

    // Add custom capabilities if they don't already exist.
    if (!$role->has_cap('edit_competitors')) {
        $role->add_cap('edit_competitors', true);
    }
}
// Hook into 'admin_init' or another appropriate action.
add_action('admin_init', 'add_custom_capabilities');



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
        'competitors-settings',        // Parent slug ('null' if hidden from menu)
        'Competitors personal data',   // Page title
        'Personal data',               // Menu title
        'edit_competitors',            // Capability Judge and up
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
        'edit_competitors', // Capability
        'competitors-scoring',
        'judges_scoring_page'     
    );
}
add_action('admin_menu', 'competitors_add_submenu_scoring');





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
    echo '<div class="wrap" id="settings-page"><h1>Competitors rolls and settings</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('competitors_settings');
    do_settings_sections('competitors_settings');
    submit_button();
    echo '</form></div>';
}



// Register a new setting and sections for the "Competitors" page
function competitors_settings_init() {
    register_setting(
        'competitors_settings', // Option group
        'competitors_custom_values', // Option name
        'competitors_custom_values_sanitize' // Sanitize callback
    );
    register_setting(
        'competitors_settings', // Option group
        'competitors_numeric_values', // Option name
        'competitors_numeric_values_sanitize' // Optional: Custom sanitize callback
    );
    
    add_settings_section(
        'competitors_custom_values_section', // ID
        __('Custom Values for roll names. One roll name on each row. Add rows with +.', 'competitors'), // Title
        'competitors_settings_section_callback', // Callback
        'competitors_settings' // Page
    );

    add_settings_field(
        'competitors_text_field', // ID
        __('Values', 'competitors'), // Title
        'competitors_text_field_render', // Callback function
        'competitors_settings', // Page
        'competitors_custom_values_section' // Section
    );
}
add_action('admin_init', 'competitors_settings_init');


// Callback function for the settings section description
function competitors_settings_section_callback() {
    echo '<p>';
    echo __('These values correspond to what rolls competitors check with check boxes on the front end, as well as the admin area. ', 'competitors');
    echo __('You can display either the registration form for competitors or the results on any WP Post or Page with shortcodes. [competitors_form_public] or (COMING SOON!) [competitors_score_public]', 'competitors');
    echo '</p>';
}


// Render the settings field
function competitors_text_field_render() {
    $roll_names = get_option('competitors_custom_values');
    $points_values = get_option('competitors_numeric_values'); // Fetch points values from the database



    if (!is_array($roll_names)) {
        $roll_names = [$roll_names];
    }
    if (!is_array($points_values)) {
        $points_values = array_fill(0, count($roll_names), ''); // Ensure $points_values is an array of the same length as $roll_names
    }
    // Nonce field for security
    wp_nonce_field('competitors_nonce_action', 'competitors_nonce');

    echo '<div id="competitors_roll_names_wrapper">';
    foreach ($roll_names as $index => $roll_name) {
        $point_value = isset($points_values[$index]) ? $points_values[$index] : ''; // Fetch corresponding point value or default to empty
        echo '<p data-index="' . $index . '">';
        echo '<label for="maneuver_' . $index . '">Maneuver: </label>';
        echo '<input type="text" id="maneuver_' . $index . '" name="competitors_custom_values[]" size="60" value="' . esc_attr($roll_name) . '" />';
        echo '<label for="points_' . $index . '"> Points: </label>';
        $point_value = isset($points_values[$index]) ? $points_values[$index] : '0'; // Replace with appropriate default if empty
        echo '<input type="text" class="numeric-input" id="points_' . $index . '" name="competitors_numeric_values[]" size="2" maxlength="2" pattern="\d*" title="Only 2 digits allowed" value="' . esc_attr($point_value) . '" />';
        // Remove button for each row
        echo '<button type="button" name="remove_row" value="' . $index . '" class="button custom-button button-secondary remove-row">Remove</button>';

        if ($index === 0) {
            echo '<button type="button" id="add_more_roll_names" class="button custom-button button-primary plus-button"></button>';
        }
        echo '</p>';
    }
    echo '</div>';
}



// Handle form submission for removing a row
function competitors_remove_row_ajax() {
    // Verify the nonce for security
    check_ajax_referer('competitors_nonce_action', 'security');

    if (isset($_POST['index'])) {
        $index = intval($_POST['index']);
        $roll_names = get_option('competitors_custom_values');
        $points_values = get_option('competitors_numeric_values');

        // Check if the index exists and remove the item from both arrays
        if (isset($roll_names[$index])) {
            unset($roll_names[$index]);
            // Re-index array
            $roll_names = array_values($roll_names);
            update_option('competitors_custom_values', $roll_names);
        }

        if (is_array($points_values) && isset($points_values[$index])) {
            unset($points_values[$index]);
            $points_values = array_values($points_values);
            update_option('competitors_numeric_values', $points_values);
        }

        wp_send_json_success(['message' => 'Row removed successfully']);
    } else {
        wp_send_json_error(['message' => 'Invalid request']);
    }

    wp_die(); // Required to terminate immediately and return a proper response
}
add_action('wp_ajax_remove_competitor_row', 'competitors_remove_row_ajax');



// Sanitize callback function
function competitors_custom_values_sanitize($input) {
    return array_map('sanitize_text_field', $input);
}
function competitors_numeric_values_sanitize($input) {
    return array_map('sanitize_text_field', $input);
}


// Utility functions reuseable

/**
 * Retrieves the roll names and their max scores from WordPress options.
 * @return array An array of roll names with their max scores.
 */
function get_roll_names_and_max_scores() {
    $roll_names = get_option('competitors_custom_values');
    $roll_max_scores = get_option('competitors_numeric_values');
    $combined = [];

    if (!is_array($roll_names)) {
        $roll_names = [];
    }
    if (!is_array($roll_max_scores)) {
        $roll_max_scores = [];
    }

    foreach ($roll_names as $index => $name) {
        $name = trim($name);
        if (!empty($name)) {
            $max_score = isset($roll_max_scores[$index]) && $roll_max_scores[$index] !== '' ? " -" . $roll_max_scores[$index] . "p" : ' N/A';
            $combined[$index] = [
                'name' => $name,
                'max_score' => $max_score
            ];
        }
    }

    return $combined;
}



function custom_back_button_shortcode($atts) {
    // Shortcode attributes with default values for URL and button text
    // [custom_button url="https://example.com" text="Go there or go Home"]
    // [custom_button text="Back to Previous Page"]

    $attributes = shortcode_atts(array(
        'url' => '', // Default is empty, meaning no custom URL is provided
        'text' => 'Go Back', // Default button text
    ), $atts);

    // Sanitize the button text to ensure it's safe to use
    $button_text = sanitize_text_field($attributes['text']);

    // Determine the button's action based on the provided URL
    if (!empty($attributes['url'])) {
        // Sanitize the URL to ensure it's safe to use
        $url = esc_url($attributes['url']);
        $button_html = '<a href="' . $url . '" class="custom-back-button">' . $button_text . '</a>';
    } else {
        // Use JavaScript to go back if no URL is provided
        $button_html = '<a href="#" onclick="window.history.back(); return false;" class="custom-back-button">' . $button_text . '</a>';
    }

    return $button_html;
}
add_shortcode('custom_button', 'custom_back_button_shortcode');






function add_custom_roles() {
    remove_role('competitor');
    remove_role('staff');
    remove_role('judge');
    remove_role('competitor_editor');
    remove_role('competitor_staff');
    remove_role('competitor_judge');

    add_role(
        'competitors_judge',
        __('Judge'),
        array(
            'read' => true,
            'edit_competitors' => true,
            'read_competitors' => true,
            // 'edit_others_posts' => true, // Include only if necessary for Judges.
            // Additional capabilities can be added as needed.
        )
    );
}
add_action('init', 'add_custom_roles');


function restrict_menu_items() {
    if (current_user_can('competitors_judge')) {
        // Remove unnecessary menu items for Judges
        remove_menu_page('edit.php'); // Posts
        remove_menu_page('edit-comments.php'); // Comments
        remove_menu_page('upload.php'); // Media
        remove_menu_page('tools.php'); // Tools
        remove_menu_page('options-general.php'); // Settings
        // Add or remove menu items as needed
    }
}
add_action('admin_menu', 'restrict_menu_items');

function redirect_judge_after_login($user_login, $user) {
    if (user_can($user, 'competitors_judge')) {
        // Assuming 'competitors' is a custom post type and 'competitors-scoring' is a specific page for Judges
        // https://rollsm.se/wp-admin/edit.php?post_type=competitors&page=competitors-scoring
        wp_redirect(admin_url('edit.php?post_type=competitors&page=competitors-scoring')); // This should fckin work but doesnt!
        //wp_redirect(admin_url('/')); // redirects to /wp-admin
        
        exit;
    }
}
add_action('wp_login', 'redirect_judge_after_login', 10, 2);

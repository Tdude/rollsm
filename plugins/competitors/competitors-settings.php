<?php
/**
 * Plugin Name: Competitors
 * Description:  For RollSM, A Greenland Rolling Championships registering and scoreboard plugin with live scores.
 * Version: 0.82
 * Author: <a href="https://klickomaten.com">Tibor Berki</a>. /Tdude @Github.
 * Text Domain: competitors
 * Domain Path: /languages
 */

define('COMPETITORS_PLUGIN_VERSION', '0.82');


// REMOVE OR COMMENT OUT AFTER DONE!!!
error_reporting(E_ALL);
ini_set('display_errors', 1);


/**
 * Removes the admin color scheme picker from user profiles for non-admin users.
 */
function remove_admin_color_scheme_for_non_admins() {
    // Check if the current user is not an administrator
    if (!current_user_can('manage_options')) {
        // Remove the color scheme picker
        remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');
    }
}
add_action('admin_init', 'remove_admin_color_scheme_for_non_admins');


 // Include admin and public page functionalities
include_once plugin_dir_path(__FILE__) . 'admin-page.php';
include_once plugin_dir_path(__FILE__) . 'public-page.php';


/**
 * Enqueues styles and scripts for the front-end part of the site.
 */
function enqueue_competitors_public_scripts() {
    wp_enqueue_style('competitors-style', plugins_url('assets/style.css', __FILE__));
    wp_enqueue_style('dashicons');

    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
    wp_enqueue_script('competitors_scoring_view_page', plugins_url('assets/script.js', __FILE__), array('jquery'), COMPETITORS_PLUGIN_VERSION, true);
    wp_localize_script('competitors_scoring_view_page', 'competitorsPublicAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'), // Ensure exact same, "ajaxurl" and "nonce", param names in the JS.
        'nonce' => wp_create_nonce('competitors_nonce_action'),
        'baseURL' => get_home_url(),
        'thankYouSlug' => 'competitors-thank-you'
        
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_competitors_public_scripts');


/**
 * Enqueues styles and scripts for the WordPress admin area specific to the plugin.
 */
function enqueue_competitors_admin_scripts() {
    wp_enqueue_style('competitors-admin-style', plugins_url('assets/admin.css', __FILE__));
    wp_enqueue_script('competitors_admin_page', plugins_url('assets/admin-script.js', __FILE__), array('jquery'), COMPETITORS_PLUGIN_VERSION, true);
    wp_localize_script('competitors_admin_page', 'competitorsAdminAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('competitors_nonce_action')
    ));
}
add_action('admin_enqueue_scripts', 'enqueue_competitors_admin_scripts');


/**
 * Registers a custom post type for competitors.
 */
function register_competitors_post_type() {
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
add_action('init', 'register_competitors_post_type');


/**
 * Sets up a meta box for custom ordering of competitor posts in the admin.
 */
function setup_competitors_custom_order_meta_box() {
    // Add Meta Box
    add_action('add_meta_boxes', function() {
        add_meta_box(
            'competitors_custom_order',
            __('Custom Order', 'competitors-plugin'),
            'competitors_custom_order_meta_box_callback',
            'competitors',
            'side',
            'high'
        );
    });

    // Meta Box Display Callback
    function competitors_custom_order_meta_box_callback($post) {
        wp_nonce_field('competitors_custom_order_save', 'competitors_custom_order_nonce');
        $value = get_post_meta($post->ID, '_competitors_custom_order', true);
        echo '<label for="competitors_custom_order_field">' . __('Order', 'competitors-plugin') . '</label> ';
        echo '<input type="number" id="competitors_custom_order_field" name="competitors_custom_order_field" value="' . esc_attr($value) . '" size="25" />';
    }

    // Save Meta Box Content and Quick Edit Data
    add_action('save_post', function($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['competitors_custom_order_nonce']) || !wp_verify_nonce($_POST['competitors_custom_order_nonce'], 'competitors_custom_order_save')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (isset($_POST['competitors_custom_order_field'])) {
            $custom_order = sanitize_text_field($_POST['competitors_custom_order_field']);
            update_post_meta($post_id, '_competitors_custom_order', $custom_order);
        }
    });

    // Add Custom Column to Admin List
    add_filter('manage_competitors_posts_columns', function($columns) {
        $columns['custom_order'] = __('Order prio', 'competitors-plugin');
        return $columns;
    });

    // Populate Custom Column with Custom Order Value
    add_action('manage_competitors_posts_custom_column', function($column, $post_id) {
        if ($column == 'custom_order') {
            $order = get_post_meta($post_id, '_competitors_custom_order', true);
            echo esc_html($order);
        }
    }, 10, 2);
}

// Initialize the meta box setup
setup_competitors_custom_order_meta_box();

// Extra for meta box saving order in listing directly from Quick Edit. @Todo: refactor if possible
function save_competitors_custom_order($post_id) {
    // Check if this is a Quick Edit save by verifying the DOING_AJAX constant and the action
    if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] == 'inline-save') {
        // Quick Edit save logic
        if (isset($_POST['competitors_custom_order'])) {
            $custom_order = sanitize_text_field($_POST['competitors_custom_order']);
            update_post_meta($post_id, '_competitors_custom_order', $custom_order);
        }
    } else {
        // Standard Edit Form save logic
        if (!isset($_POST['competitors_custom_order_nonce']) || !wp_verify_nonce($_POST['competitors_custom_order_nonce'], 'competitors_custom_order_save')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (isset($_POST['competitors_custom_order_field'])) {
            $custom_order = sanitize_text_field($_POST['competitors_custom_order_field']);
            update_post_meta($post_id, '_competitors_custom_order', $custom_order);
        }
    }
}
add_action('save_post_competitors', 'save_competitors_custom_order');


/**
 * Flushes rewrite rules on plugin activation/deactivation to ensure custom post type URLs work correctly.
 * Also adds a default page on activation to help the not so savvy to "roll" :)
 */
function flush_rewrite_rules_on_activation() {
    // Ensure CPT is registered
    register_competitors_post_type();
    // Then flush rewrite rules
    flush_rewrite_rules();
    // Defult registered competitor
    create_default_competitor_if_none_exists();

    // Include the predefined rolls from a PHP file
    $predefined_rolls = include(plugin_dir_path(__FILE__) . '/assets/predefined-rolls.php');

    // Sanitize and validate each roll
    $sanitized_rolls = array_map(function($roll) {
        return [
            'name' => sanitize_text_field($roll['name']),
            'points' => is_numeric($roll['points']) ? (int)$roll['points'] : 1, // Default point if not numeric
        ];
    }, $predefined_rolls);

    // Ensure there are 35 maneuvers, filling with default values if necessary
    while (count($sanitized_rolls) < 35) {
        $sanitized_rolls[] = ["name" => "Default maneuver name", "points" => 10];
    }

    // Prepare separate arrays for names and points
    $roll_names = array_map(function($roll) { return $roll['name']; }, $sanitized_rolls);
    $points_values = array_map(function($roll) { return $roll['points']; }, $sanitized_rolls);

    // Update the options with predefined values if they are not set yet
    if (false === get_option('competitors_custom_values')) {
        update_option('competitors_custom_values', $roll_names);
    }
    if (false === get_option('competitors_numeric_values')) {
        update_option('competitors_numeric_values', $points_values);
    }


    // Create the Default Competitors Display Page
    $default_page_title = 'Default Competitors Display Page';
    $default_page_slug = 'competitors-display-page';

    create_plugin_page_if_not_exists($default_page_title, $default_page_slug, '/assets/default-content.php');

    // Create the Competitors Thank You Page
    $thank_you_page_title = 'Default Competitors Thank You Page';
    $thank_you_page_slug = 'competitors-thank-you';

    create_plugin_page_if_not_exists($thank_you_page_title, $thank_you_page_slug, '/assets/default-thank-you-content.php');
}

function create_plugin_page_if_not_exists($page_title, $page_slug, $content_file_path) {
    if (null === get_page_by_title($page_title)) {
        // Start output buffering
        ob_start();
        // Include a file to get content to prefill with, adjust the path as needed
        include(plugin_dir_path(__FILE__) . $content_file_path);
        // Get the content from the buffer and clean it
        $page_content = ob_get_clean();
    
        $page_data = [
            'post_title'   => $page_title,
            'post_name'    => $page_slug,
            'post_content' => $page_content,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
            'post_type'    => 'page',
        ];
        $page_id = wp_insert_post($page_data);
    
        // Store the page ID and slug for future reference or deletion
        if (!is_wp_error($page_id)) {
            update_option('competitors_' . str_replace('-', '_', $page_slug) . '_page_id', $page_id);
            update_option('competitors_' . str_replace('-', '_', $page_slug) . '_page_slug', $page_slug);
        }
    }
}

register_activation_hook(__FILE__, 'flush_rewrite_rules_on_activation');


/**
 * Define a new function to check for and create a default competitor. Good for demos. 
 * It is called within flush_rewrite_rules_on_activation()
 * */ 
function create_default_competitor_if_none_exists() {
    $args = array(
        'post_type' => 'competitors',
        'post_status' => 'publish',
        'numberposts' => 1
    );
    $existing_competitors = get_posts($args);
    if (empty($existing_competitors)) {
        $competitor_data = array(
            'post_title'    => 'Default Competitor',
            'post_content'  => 'This is a default competitor created automatically by the plugin.',
            'post_status'   => 'publish',
            'post_type'     => 'competitors',
            'meta_input'    => array(
                'email' => "default@competitor.com",
                'phone' => "+00-000-0000000",
                'club' => "Default Club",
                'sponsors' => "Default Sponsor",
                'speaker_info' => "This is a default competitor. Update this competitor with actual data.",
                'participation_class' => "open",
                'license' => "yes",
                'consent' => "yes",
                'competitor_scores' => array(), // Initially empty
                'selected_rolls' => array(), // Initially empty or predefined selections
                '_competitors_custom_order' => 0, // Default order
            ),
        );
        // Insert the post into the database
        wp_insert_post($competitor_data);
    }
}


/**
 * Self explanatory, right? Deactivation. Ende. Aus. Terminate.
 * Also deletes the default page upon deactivation
 */
function flush_rewrite_rules_on_deactivation() {
    // Delete the default page created during plugin activation
    $default_page_id = get_option('competitors_default_page_id');
    if ($default_page_id) {
        wp_delete_post($default_page_id, true); // true forces deletion instead of moving to trash
        delete_option('competitors_default_page_id'); // Clean up by removing the option
    }
    // Flush rewrite rules after custom post types and pages have been cleaned up
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'flush_rewrite_rules_on_deactivation');


/**
 * Adds a top-level admin menu page for the plugin's settings.
 * Order of the params is important. See https://developer.wordpress.org/reference/functions/add_menu_page/
 */
function add_competitors_admin_menu() {
    add_menu_page(
        'Competitors custom settings',      // Page title
        'Competitors settings',             // Menu title
        'manage_options',                   // Capability
        'competitors-settings',             // Menu slug
        'render_competitors_settings_page', // Function to display the page
        'dashicons-clipboard',              // Icon (optional)
        27                                  // Position (in quintuples, each moving down the menu)
    );
}
add_action('admin_menu', 'add_competitors_admin_menu');


/**
 * Adds a submenu page under the plugin’s settings for personal data management.
 * (WP needs two items to show sub menu items!)
 */
function add_competitors_submenu_for_settings() {
    add_submenu_page(
        'competitors-settings',        // Parent slug ('null' if hidden from menu)
        'Competitors personal data',   // Page title
        'Personal data',               // Menu title
        'edit_competitors',            // WP "Capability" Judge and up
        'competitors-detailed-data',   // Menu slug (different from the parent slug)
        'competitors_admin_page'       // Callback function
    );
}
add_action('admin_menu', 'add_competitors_submenu_for_settings');


/**
 * Adds a submenu page for judges to enter scoring information.
 */
function add_competitors_submenu_for_scoring() {
    add_submenu_page(
        'competitors-settings',
        'Judges scoring submenu',
        'Judges scoring',
        'edit_competitors',
        'competitors-scoring',
        'judges_scoring_page'     
    );
}
add_action('admin_menu', 'add_competitors_submenu_for_scoring');


/**
 * Everyone loves tabs, right?
 * 
 */
function render_admin_page_header() {
    $current_page = isset($_GET['page']) ? $_GET['page'] : '';

    $tabs = [
        'competitors-settings' => 'General Settings',
        'competitors-detailed-data' => 'Personal Data',
        'competitors-scoring' => 'Judges Scoring',
    ];

    echo '<h2 class="nav-tab-wrapper">';
    foreach ($tabs as $page_slug => $title) {
        $class = ($current_page === $page_slug) ? 'nav-tab-active' : '';
        $url = admin_url('admin.php?page=' . $page_slug);
        echo "<a href='{$url}' class='nav-tab {$class}'>{$title}</a>";
    }
    echo '</h2>';
}


/**
 * Renders the content for the plugin's main settings page.
 */
function render_competitors_settings_page() {
    if (!current_user_can('manage_options')) {
        echo 'Access denied to settings dude, sorry.';
        return;
    }
    render_admin_page_header();
    // Check if our transient is set and display the message
    if (get_transient('competitors_settings_submitted')) {
        echo '<div id="message" class="updated notice is-dismissible"><p>' . get_transient('competitors_settings_submitted') . '</p></div>';
        // Delete the transient so the message doesn't keep appearing
        delete_transient('competitors_settings_submitted');
    }
    echo '<div class="wrap" id="settings-page"><h1>Competitors rolls and settings</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('competitors_settings');
    do_settings_sections('competitors_settings');
    submit_button();
    echo '</form></div>';
}


/**
 * Initializes plugin settings by registering them along with their sections and fields.
 */
function initialize_competitors_settings() {
    register_setting(
        'competitors_settings', // Option group
        'competitors_custom_values', // Option name
        'competitors_custom_values_sanitize' // Sanitize callback
    );
    register_setting(
        'competitors_settings', // Option group
        'competitors_numeric_values', // Option name
        'competitors_numeric_values_sanitize' // Optional: sanitize callback
    );
    
    add_settings_section(
        'competitors_custom_values_section', // ID
        __('Custom Values for roll names. One roll name on each row. Add rows with +.', 'competitors'), // Title w. instruction
        'competitors_settings_section_callback', // Callback
        'competitors_settings' // Page
    );

    add_settings_field(
        'competitors_text_field', // ID
        __('Values', 'competitors'), // Title
        'render_competitors_text_field', // Callback function
        'competitors_settings', // Page
        'competitors_custom_values_section' // Section
    );
}
add_action('admin_init', 'initialize_competitors_settings');



// Callback function for the settings section description. Dodgy URL below but we try for now.
function competitors_settings_section_callback() {
    $external_url = 'https://www.qajaqusa.org/content.aspx?page_id=22&club_id=349669&module_id=345648';

    // Start of the container for two-column layout
    echo '<div class="two-cols">';
    echo '<div>';
    echo __('These values correspond to what rolls competitors check with check boxes on the front end, as well as the admin area. ', 'competitors');
    echo __('You can display either the registration form for competitors or the results on any WP Post or Page with the following shortcodes: <pre>[competitors_form_public]</pre> or <pre>[competitors_scoring_public]</pre>', 'competitors');
    echo '</div>';
    echo '<div>';
    echo __('There is a default page <a href="' . site_url('/competitors-display-page') . '">created here</a> for your convenience, which you can use, edit or delete. ', 'competitors');
    echo __('Over at Qajaq USA there is an <a href="'. esc_url($external_url) . '" target="_blank" rel="noopener noreferrer">excellent page</a> but with dodgy links where you can learn the roll names in Inuit. If the link is a no-go u go to "QAANNAT KATTUFFIAT" > "GREENLAND CHAMPIONSHIP" and have a look at that page.', 'competitors');
    echo '</div>';
    echo '</div>';
}


/**
 * Renders text input fields for the settings page, used for configuration options.
 */
function render_competitors_text_field() {
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


/**
 * Handles AJAX requests for removing rows dynamically from the plugin's settings page.
 */
function handle_ajax_row_removal_for_competitors() {
    check_ajax_referer('competitors_nonce_action', 'security');

    if (isset($_POST['index'])) {
        $index = intval($_POST['index']);
        $roll_names = get_option('competitors_custom_values', []);
        $points_values = get_option('competitors_numeric_values', []);

        // Ensure both options are arrays
        $roll_names = is_array($roll_names) ? $roll_names : [];
        $points_values = is_array($points_values) ? $points_values : [];

        $item_removed = false;

        if (isset($roll_names[$index])) {
            unset($roll_names[$index]);
            $roll_names = array_values($roll_names);
            update_option('competitors_custom_values', $roll_names);
            $item_removed = true;
        }

        if (isset($points_values[$index])) {
            unset($points_values[$index]);
            $points_values = array_values($points_values);
            update_option('competitors_numeric_values', $points_values);
            $item_removed = true;
        }

        if ($item_removed) {
            wp_send_json_success(['message' => 'Row removed successfully']);
        } else {
            wp_send_json_error(['message' => 'Index not found']);
        }
    } else {
        wp_send_json_error(['message' => 'Invalid request']);
    }

    wp_die();
}
add_action('wp_ajax_remove_competitor_row', 'handle_ajax_row_removal_for_competitors');


/**
 * Sanitize callback function
 */
function competitors_custom_values_sanitize($input) {
    return array_map('sanitize_text_field', $input);
}
function competitors_numeric_values_sanitize($input) {
    return array_map('sanitize_text_field', $input);
}


/**
 * Retrieves the roll names and their max scores from WordPress options.
 * @return array An array of roll names with their max scores.
 */
function get_roll_names_and_max_scores() {
    // Fetch roll names and max scores, ensuring they are arrays
    $roll_names = get_option('competitors_custom_values', []);
    $roll_max_scores = get_option('competitors_numeric_values', []);
    $combined = [];

    // Ensure both options are arrays. If not, initialize as empty arrays.
    $roll_names = is_array($roll_names) ? $roll_names : [];
    $roll_max_scores = is_array($roll_max_scores) ? $roll_max_scores : [];

    // Check if both arrays are empty and initialize with default values if necessary
    if (empty($roll_names) && empty($roll_max_scores)) {
        // Define your default rolls and scores here
        $roll_names = ['Default Roll Name'];
        $roll_max_scores = ['Default Score']; // Adjust according to your default score
    }

    foreach ($roll_names as $index => $name) {
        $name = trim($name);
        // Ensure name is not empty and max score is set and not empty; otherwise, assign 'N/A'
        if (!empty($name)) {
            $max_score = isset($roll_max_scores[$index]) && $roll_max_scores[$index] !== '' ? $roll_max_scores[$index] . "p" : 'N/A';
            $combined[$index] = [
                'name' => $name,
                'max_score' => $max_score
            ];
        }
    }

    // Return combined array or a default value if everything is empty
    return !empty($combined) ? $combined : [['name' => 'No roll names defined', 'max_score' => 'N/A']];
}


/**
 * Shortcode attributes with default values for URL and button text
 * [custom_button url="https://rugd.se" text="Go there or go Home"]
 * Or just [custom_button text="Back to Previous Page"]
 */
function custom_back_button_shortcode($atts) {
    $attributes = shortcode_atts(array(
        'url' => '', // Default is empty, meaning no custom URL is provided
        'text' => 'Tillbaka', // Default button text
    ), $atts);

    // Sanitize the button text to ensure it's safe to use
    $button_text = sanitize_text_field($attributes['text']);

    // Determine the button's action based on the provided URL
    if (!empty($attributes['url'])) {
        // Sanitize the URL to ensure it's safe to use
        $url = esc_url($attributes['url']);
        $button_html = '<a href="' . $url . '" class="button custom-back-button">' . $button_text . '</a>';
    } else {
        // Use JavaScript to go back if no URL is provided
        $button_html = '<a href="#" onclick="window.history.back(); return false;" class="button custom-back-button">' . $button_text . '</a>';
    }

    return $button_html;
}
add_shortcode('custom_button', 'custom_back_button_shortcode');


/**
 * Adds custom roles and capabilities specific to the Competitors plugin.
 * Removes old or unused roles and adds a new role for 'competitors_judge' with specific capabilities.
 */
function setup_competitors_roles_and_capabilities() {
    // Add or update custom role with specific capabilities
    add_role('competitors_judge', __('Judge'), array(
        'read' => true,
        'edit_competitors' => true,
        'read_competitors' => true,
    ));

    // Ensure the Administrator role has the necessary custom capabilities
    $admin_role = get_role('administrator');
    if (!$admin_role->has_cap('edit_competitors')) {
        $admin_role->add_cap('edit_competitors', true);
    }
}
// Ideally, you'd run this once, like on plugin activation.
add_action('init', 'setup_competitors_roles_and_capabilities');


/**
 * Restricts access and hides menu links to certain admin menu items for users with the 'competitors_judge' role.
 * Intended to simplify the WordPress admin menu for these users by removing unnecessary items.
 * If you dont like it, just use the WP default user capabilities.
 */
function restrict_menu_items() {
    if (current_user_can('competitors_judge')) {
        $menu_slugs_to_remove = [
            'edit.php',
            'edit-comments.php',
            'upload.php',
            'tools.php',
            'options-general.php'
        ];
        foreach ($menu_slugs_to_remove as $menu_slug) {
            remove_menu_page($menu_slug);
        }
    }
}
add_action('admin_menu', 'restrict_menu_items');


/**
 * Redirects the user to the 'competitors-scoring' admin page based on a transient flag set on login.
 * The redirection is intended for users who can edit competitors, typically those with the 'competitors_judge'
 * role or equivalent to get to relevant content in the admin dashboard. Sometimes it actually works too...
 */
function redirect_judge_to_specific_page() {
    $user = wp_get_current_user();
    if (get_transient('redirect_to_competitors_scoring_' . $user->ID)) {
        delete_transient('redirect_to_competitors_scoring_' . $user->ID);
        if (user_can($user, 'edit_competitors')) {
            exit(wp_redirect(admin_url('edit.php?post_type=competitors&page=competitors-scoring')));
        }
    }
}
add_action('admin_init', 'redirect_judge_to_specific_page', 9999);


/**
 * The Transient is used to ensure that the redirection occurs only once immediately after login,
 * upping the user experience by directing them to a relevant page based on their role capabilities.
 * @param string $user_login The username used to log in.
 * @param WP_User $user The WP_User object representing the logged-in user.
 */
function redirect_judge_after_login($user_login, $user) {
    if (user_can($user, 'edit_competitors')) { // Using a specific capability
        set_transient('redirect_to_competitors_scoring_' . $user->ID, true, 60);
    }
}


/**
 * Inserts footer text but only on the page(s) of this screens ID
 */
function customize_admin_footer_text() {
    $screen = get_current_screen();

    // Define an array of your plugin's admin page screen IDs
    $plugin_pages = [
        'toplevel_page_competitors-settings',
        'competitors-settings_page_competitors-detailed-data',
        'competitors-settings_page_competitors-scoring',
        // Add more screen IDs as needed
    ];

    // Check if the current screen ID is in the array of plugin pages
    if (in_array($screen->id, $plugin_pages)) {
        $version_text = 'This WP Competitors plugin is version: ' . COMPETITORS_PLUGIN_VERSION . ' and still in Beta. If you have encountered bugs or have ideas on how to do it better, don\'t be a stranger!';
        echo 'Thank you for creating friendly rolling events with the Competitors plugin. You can reach the developer on Insta @tdudesthlm or on my site <a href="https://rugd.se/">RUGD.se</a>. ' . $version_text;
    }
}
add_filter('admin_footer_text', 'customize_admin_footer_text');


/**
 * Appends custom arbitrary text next to the WordPress version number in the admin footer.
 */
function append_text_to_admin_footer_version( $text ) {
    $custom_text = 'WP '; // Your text here if you want
    return $custom_text . $text ;
}
add_filter('update_footer', 'append_text_to_admin_footer_version', 11);

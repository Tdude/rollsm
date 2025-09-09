<?php
/**
 * Plugin Name: Competitors
 * Description:  For RollSM, A Greenland Rolling Championships registering and scoreboard plugin with live scores.
 * Version: 0.99
 * Author: <a href="https://klickomaten.com">Tibor Berki</a>. /Tdude @Github.
 * Text Domain: competitors
 * Domain Path: /languages
 */

define('COMPETITORS_PLUGIN_VERSION', '0.99');


// REMOVE OR COMMENT OUT AFTER DONE DEV!!!
// error_reporting(E_ALL);
// ini_set('display_errors', 1);


/**
 * Load text domain for translation
 */
function competitors_load_textdomain() {
    load_plugin_textdomain('competitors', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'competitors_load_textdomain');


/**
 * Removes the admin color scheme picker from user profiles for non-admin users.
 */
function competitors_remove_admin_color_scheme_for_non_admins() {
    if (!current_user_can('manage_options')) {
        remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');
    }
}
add_action('admin_init', 'competitors_remove_admin_color_scheme_for_non_admins');


// Include admin and public page functionalities
include_once plugin_dir_path(__FILE__) . 'admin-page.php';
include_once plugin_dir_path(__FILE__) . 'public-page.php';


/**
 * Checks if the current post has any of the provided shortcodes.
 */
function competitors_post_has_shortcodes($post, $shortcodes) {
    if (!$post || !is_a($post, 'WP_Post')) {
        return false;
    }

    foreach ($shortcodes as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            return true;
        }
    }
    return false;
}



/**
 * Enqueues styles and scripts for the front-end part of the site.
 */
function competitors_enqueue_public_scripts() {
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
    $shortcodes = ['competitors_form_public', 'competitors_scoring_public'];
    global $post;

    if (competitors_post_has_shortcodes($post, $shortcodes)) {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('competitors-style', plugins_url('assets/style.css', __FILE__));
        wp_enqueue_script('competitors_scoring_view_page', plugins_url('assets/script.js', __FILE__), array('jquery'), COMPETITORS_PLUGIN_VERSION, true);
        wp_localize_script('competitors_scoring_view_page', 'competitorsPublicAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('competitors_nonce_action'),
            'baseURL' => get_home_url(),
            'thankYouSlug' => 'competitors-thank-you'
        ));
    }
}
add_action('wp_enqueue_scripts', 'competitors_enqueue_public_scripts');


function competitors_debug_admin_hook($hook) {
    error_log('Current admin page hook: ' . $hook);
    
    global $post_type;
    if ($post_type) {
        error_log('Current post type: ' . $post_type);
    }
    
    // Log the current screen object
    $screen = get_current_screen();
    if ($screen) {
        error_log('Current screen ID: ' . $screen->id);
        error_log('Current screen base: ' . $screen->base);
    }
}
add_action('admin_enqueue_scripts', 'competitors_debug_admin_hook', 1);

/**
 * Enqueues for admin area.
 */
function competitors_enqueue_admin_scripts($hook) {
   // Define an array of page hooks that should include your scripts
   $competitors_pages = [
    'toplevel_page_competitors-settings',
    'competitors-settings_page_competitors-scoring',
    'competitors-settings_page_competitors-detailed-data',
    'competitors-settings_page_competitors-classes-dates',
    'competitors_page_email-history',
    'competitors_page_send-competitor-emails',
    'edit-competitors',
    'edit.php',  // Include the main edit.php page for your custom post type
    'post.php',  // Include individual post edit pages
    'post-new.php'  // Include the "Add New" page
    ];

    // Check if the current page hook starts with any of the defined pages
    $should_enqueue = false;
    foreach ($competitors_pages as $page) {
        if (strpos($hook, $page) === 0) {
            $should_enqueue = true;
            break;
        }
    }

    // If it's not a competitors page, escape escape
    if (!$should_enqueue) {
        return;
    }


    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
    wp_enqueue_style('competitors-admin-style', plugins_url('assets/admin.css', __FILE__));
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_script('competitors_admin_page', plugins_url('assets/admin-script.js', __FILE__), array('jquery'), COMPETITORS_PLUGIN_VERSION, true);
    wp_localize_script('competitors_admin_page', 'competitorsAdminAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('competitors_nonce_action')
    ));
}
add_action('admin_enqueue_scripts', 'competitors_enqueue_admin_scripts');




/**
 * Registers a custom post type for the competitors plugin.
 */
function competitors_register_post_type() {
    $labels = array(
        'name' => __('Competitors', 'competitors'),
        'singular_name' => __('Competitor', 'competitors'),
        // Add more labels as needed
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
        'menu_icon' => 'dashicons-groups',
        'show_in_rest' => false, // This enables the block editor
        'template' => array(
            array('core/paragraph', array(
                'placeholder' => __('This is the Competitors Plugin for roll competition scoring', 'competitors')
            ))
        ),
        'template_lock' => 'all', // This locks the template so users can't add/remove blocks
    );

    register_post_type('competitors', $args);
}
add_action('init', 'competitors_register_post_type');


/**
 * Sets up a meta box for custom ordering of competitor posts in the admin.
 */
function competitors_add_custom_meta_boxes() {
    add_meta_box(
        'competitors_custom_order',
        __('Custom Order', 'competitors'),
        'competitors_custom_order_meta_box_callback',
        'competitors',
        'side',
        'high'
    );

    add_meta_box(
        'competitors_details',
        __('Competitor Details', 'competitors'),
        'competitors_details_meta_box_callback',
        'competitors',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'competitors_add_custom_meta_boxes');

// Meta Box Display Callback for Custom Order
function competitors_custom_order_meta_box_callback($post) {
    // Add a nonce field for security
    wp_nonce_field('competitors_custom_order_save', 'competitors_custom_order_nonce');

    // Get the current value of the meta field
    $value = get_post_meta($post->ID, '_competitors_custom_order', true);

    // Display the input field
    echo '<p>';
    echo '<label for="competitors_custom_order_field">' . esc_html__('Order:', 'competitors') . '</label> ';
    echo '<input type="number" id="competitors_custom_order_field" name="competitors_custom_order_field" value="' . esc_attr($value) . '" size="25" />';
    echo '</p>';
}


    
// Meta Box Display Callback for Competitors Details
function competitors_details_meta_box_callback($post) {
    // Add a nonce field for security
    wp_nonce_field('competitors_details_save', 'competitors_details_nonce');

    // Get the current values of the meta fields
    $club = get_post_meta($post->ID, 'club', true);
    $participation_class = get_post_meta($post->ID, 'participation_class', true);
    $email = get_post_meta($post->ID, 'email', true);
    $speaker_info = get_post_meta($post->ID, 'speaker_info', true);
    $gender = get_post_meta($post->ID, 'gender', true);

    // Display the input fields
    ?>
    <p>
        <label for="competitors_club"><?php echo esc_html__('Club:', 'competitors'); ?></label>
        <input type="text" id="competitors_club" name="competitors_club" value="<?php echo esc_attr($club); ?>" size="25">
    </p>
    <p>
        <label for="competitors_participation_class"><?php echo esc_html__('Participation Class:', 'competitors'); ?></label>
        <input type="text" id="competitors_participation_class" name="competitors_participation_class" value="<?php echo esc_attr($participation_class); ?>" size="25">
    </p>
    <p>
        <label for="competitors_email"><?php echo esc_html__('Email:', 'competitors'); ?></label>
        <input type="email" id="competitors_email" name="competitors_email" value="<?php echo esc_attr($email); ?>" size="25">
    </p>
    <p>
        <label for="competitors_speaker_info"><?php echo esc_html__('Speaker Info:', 'competitors'); ?></label>
        <input type="text" id="competitors_speaker_info" name="competitors_speaker_info" value="<?php echo esc_attr($speaker_info); ?>" size="25">
    </p>
    <p>
        <label><?php echo esc_html__('Gender:', 'competitors'); ?></label><br>
        <input type="radio" id="competitors_gender_woman" name="competitors_gender" value="woman" <?php checked($gender, 'woman'); ?>>
        <label for="competitors_gender_woman"><?php echo esc_html__('Woman', 'competitors'); ?></label>
        <input type="radio" id="competitors_gender_man" name="competitors_gender" value="man" <?php checked($gender, 'man'); ?>>
        <label for="competitors_gender_man"><?php echo esc_html__('Man', 'competitors'); ?></label>
    </p>
    <?php
}



function competitors_save_custom_data($post_id, $post) {
    if ($post->post_type !== 'competitors') {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // Save custom order
    if (isset($_POST['competitors_custom_order_nonce']) && wp_verify_nonce($_POST['competitors_custom_order_nonce'], 'competitors_custom_order_save')) {
        if (isset($_POST['competitors_custom_order_field'])) {
            $custom_order = sanitize_text_field($_POST['competitors_custom_order_field']);
            update_post_meta($post_id, '_competitors_custom_order', $custom_order);
        }
    }

    // Save competitor details
    if (isset($_POST['competitors_details_nonce']) && wp_verify_nonce($_POST['competitors_details_nonce'], 'competitors_details_save')) {
        if (isset($_POST['competitors_club'])) {
            update_post_meta($post_id, 'club', sanitize_text_field($_POST['competitors_club']));
        }
        if (isset($_POST['competitors_participation_class'])) {
            update_post_meta($post_id, 'participation_class', sanitize_text_field($_POST['competitors_participation_class']));
        }
        if (isset($_POST['competitors_email'])) {
            update_post_meta($post_id, 'email', sanitize_email($_POST['competitors_email']));
        }
        if (isset($_POST['competitors_speaker_info'])) {
            update_post_meta($post_id, 'speaker_info', sanitize_text_field($_POST['competitors_speaker_info']));
        }
        if (isset($_POST['competitors_gender'])) {
            update_post_meta($post_id, 'gender', sanitize_text_field($_POST['competitors_gender']));
        }
    }
}
add_action('save_post_competitors', 'competitors_save_custom_data', 10, 2);

/**
 * Flushes rewrite rules on plugin activation/deactivation to make custom post type URLs work well.
 * Also adds a default page on activation to help the not so savvy to "roll" :)
 */
function flush_rewrite_rules_on_activation() {
    register_competitors_post_type();
    flush_rewrite_rules();
    create_default_competitor_if_none_exists();

    $predefined_rolls = include(plugin_dir_path(__FILE__) . '/assets/predefined-rolls.php');

    $sanitized_rolls = array_map(function($roll) {
        return [
            'name' => sanitize_text_field($roll['name']),
            'points' => is_numeric($roll['points']) ? (int)$roll['points'] : 1, // Default point if not numeric
            'is_numeric_field' => isset($roll['is_numeric_field']) ? (bool)$roll['is_numeric_field'] : false,
        ];
    }, $predefined_rolls);



    // Prepare separate arrays for names, points, and numeric flags
    $roll_names = array_map(function($roll) { return $roll['name']; }, $sanitized_rolls);
    $points_values = array_map(function($roll) { return $roll['points']; }, $sanitized_rolls);
    $is_numeric_fields = array_map(function($roll) { return $roll['is_numeric_field']; }, $sanitized_rolls);

    // Update the options with predefined values if they are not set yet
    if (false === get_option('competitors_custom_values')) {
        update_option('competitors_custom_values', $roll_names);
    }
    if (false === get_option('competitors_numeric_values')) {
        update_option('competitors_numeric_values', $points_values);
    }
    if (false === get_option('competitors_is_numeric_field')) {
        update_option('competitors_is_numeric_field', $is_numeric_fields);
    }

    // Define and create pages
    $pages = [
        ['title' => 'Default Competitors Display Page', 'slug' => 'competitors-display-page', 'template' => '/assets/default-content.php'],
        ['title' => 'Default Competitors Thank You Page', 'slug' => 'competitors-thank-you', 'template' => '/assets/default-thank-you-content.php']
    ];
    foreach ($pages as $page) {
        create_plugin_page_if_not_exists($page['title'], $page['slug'], $page['template']);
    }
}

add_action('after_switch_theme', 'flush_rewrite_rules_on_activation');



/**
 * Create a default page for the plugin to work outta the box
 */
function create_plugin_page_if_not_exists($page_title, $page_slug, $content_file_path) {
    // Query to check if the page exists
    $page_query = new WP_Query([
        'post_type' => 'page',
        'name'      => $page_slug,
        'post_status' => 'publish',
        'posts_per_page' => 1
    ]);

    if (!$page_query->have_posts()) {
        // Start output buffering
        ob_start();

        include(plugin_dir_path(__FILE__) . $content_file_path);
        // Get content from buffer and clean it
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
    
        // Store page ID and slug for future reference
        if (!is_wp_error($page_id)) {
            update_option('competitors_' . str_replace('-', '_', $page_slug) . '_page_id', $page_id);
            update_option('competitors_' . str_replace('-', '_', $page_slug) . '_page_slug', $page_slug);
        }
    }
}
register_activation_hook(__FILE__, 'flush_rewrite_rules_on_activation');



/**
 * Self explanatory, right? Deactivation. Ende. Aus. Terminate.
 * Also removes the default page on deactivation
 */
function flush_rewrite_rules_on_deactivation() {
    // Correctly delete pages created by the plugin and their options
    $pages = [
        'competitors_display_page',
        'competitors_thank_you'
    ];

    foreach ($pages as $slug) {
        $page_id = get_option('competitors_' . str_replace('-', '_', $slug) . '_page_id');
        if ($page_id) {
            wp_delete_post($page_id, true); // true forces deletion instead of moving to trash
            delete_option('competitors_' . str_replace('-', '_', $slug) . '_page_id'); // Delete the page ID option
            delete_option('competitors_' . str_replace('-', '_', $slug) . '_page_slug'); // Delete the slug option
        }
    }

    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'flush_rewrite_rules_on_deactivation');


/**
 * Define a function to check for and create a default competitor. Good for demos. 
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
            'post_title'    => 'Test Competitor (Default)',
            'post_content'  => 'This is a default competitor created on installation by the plugin.',
            'post_status'   => 'publish',
            'post_type'     => 'competitors',
            'meta_input'    => array(
                'email' => "default@competitor.com",
                'phone' => "+00-000-0000000",
                'club' => "Default Club",
                'sponsors' => "Default Sponsor",
                'speaker_info' => "This is a default competitor post. Play with it or delete it.",
                'participation_class' => "open",
                'license' => "yes",
                'dinner' => "yes",
                'consent' => "yes",
                'competitor_scores' => array(), // Initially empty
                'selected_rolls' => array(), // Initially empty or predefined selections
                '_competitors_custom_order' => 0, // Default order
            ),
        );
        wp_insert_post($competitor_data);
    }
}


/**
 * Sets default competition classes to make initiation smoother. 
 */
function set_default_competition_classes() {
    $default_classes = [
        ['name' => 'open', 'comment' => esc_html__('Open (International participants)', 'competitors')],
        ['name' => 'championship', 'comment' => esc_html__('Championship (club member and competition license holder)', 'competitors')],
        ['name' => 'amateur', 'comment' => esc_html__('Motionsklass (No license needed)', 'competitors')]
    ];

    // Set default classes if not already set
    if (false === get_option('available_competition_classes')) {
        update_option('available_competition_classes', $default_classes);
    }
}
add_action('admin_init', 'set_default_competition_classes');


/**
 * Adds a top-level admin menu page for the plugin's settings.
 */
function add_competitors_admin_menu() {
    add_menu_page(
        esc_html__('Competitors Custom Settings', 'competitors'),  // Page title
        esc_html__('Competitors settings', 'competitors'),        // Menu title
        'manage_options',                                         // Capability
        'competitors-settings',                                   // Menu slug
        'render_competitors_main_settings_page',                  // Function to display the page
        'dashicons-clipboard',                                    // Icon (optional)
        27                                                        // Position
    );
}
add_action('admin_menu', 'add_competitors_admin_menu');

/**
 * Adds a submenu page for Classes and Dates.
 */
function add_competitors_submenu_for_classes_dates() {
    add_submenu_page(
        'competitors-settings',
        esc_html__('Classes and Dates', 'competitors'),  // Page title
        esc_html__('Classes & Dates', 'competitors'),    // Menu title
        'edit_competitors',
        'competitors-classes-dates',
        'render_classes_dates_page'                      // Callback function for the new tab
    );
}
add_action('admin_menu', 'add_competitors_submenu_for_classes_dates');

/**
 * Adds a submenu page under the plugin’s settings for personal data management.
 */
function add_competitors_submenu_for_personal_data() {
    add_submenu_page(
        'competitors-settings',
        esc_html__('Competitors Personal Data', 'competitors'),  // Page title
        esc_html__('Personal Data', 'competitors'),             // Menu title
        'edit_competitors',
        'competitors-detailed-data',
        'competitors_admin_page'                                // Callback function
    );
}
add_action('admin_menu', 'add_competitors_submenu_for_personal_data');

/**
 * Adds a submenu page for judges to enter scoring information.
 */
function add_competitors_submenu_for_judges_scoring() {
    add_submenu_page(
        'competitors-settings',
        esc_html__('Judges Scoring Submenu', 'competitors'),  // Page title
        esc_html__('Judges Scoring', 'competitors'),          // Menu title
        'edit_competitors',
        'competitors-scoring',
        'judges_scoring_page'                                 // Callback function
    );
}
add_action('admin_menu', 'add_competitors_submenu_for_judges_scoring');


/**
 * Everyone loves tabs in the admin, right?
 * You can just remove this if you don't.
 */
function render_admin_page_header() {
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

    $tabs = [
        'competitors-settings' => esc_html__('Rolls Settings', 'competitors'),
        'competitors-classes-dates' => esc_html__('Classes & Dates', 'competitors'),
        'competitors-detailed-data' => esc_html__('Personal Data', 'competitors'),
        'competitors-scoring' => esc_html__('Judges Scoring', 'competitors'),
    ];

    echo '<h2 class="nav-tab-wrapper">';
    foreach ($tabs as $page_slug => $title) {
        $class = ($current_page === $page_slug) ? 'nav-tab-active' : '';
        $url = esc_url(admin_url('admin.php?page=' . $page_slug));
        echo "<a href='{$url}' class='nav-tab {$class}'>{$title}</a>";
    }
    echo '</h2>';
}

/**
 * Renders the texxt for the plugin's roll settings page.
 */
function competitors_settings_section_callback() {
    $external_url = 'https://www.qajaqusa.org/content.aspx?page_id=22&club_id=349669&module_id=345648';
    
    // Create the URL and link text separately for better translation support
    $default_page_link = sprintf(
        '<a href="%s" target="_blank">%s</a>',
        esc_url(site_url('/competitors-display-page')),
        esc_html__('created here', 'competitors')
    );

    echo '<div class="wrap">';
    echo '<button type="button" id="toggle-instructions" class="button button-secondary">';
    echo esc_html__('Show/Hide Instructions', 'competitors');
    echo '</button>';
    
    echo '<div id="instructions-content" class="instructions-content">';
    echo '<div class="two-cols">';
    
    // Left column
    echo '<div>';
    echo '<h2>' . esc_html__('Here is where you set Custom Values for roll names. One roll name or maneuver on each row. Add rows with +.', 'competitors') . '</h2>';
    echo '<p>' . esc_html__('These values correspond to what rolls (or maneuvers) competitors check with check boxes on the front end, as well as the admin area. See other tabs.', 'competitors') . '</p>';
    echo '<p>' . esc_html__('If you choose a "Numeric" input, you should leave the points blank! It will be filled in by the judges for speedrolling and such.', 'competitors') . '</p>';
    echo '<p>' . esc_html__('There are three different possible scoreboards by default, corresponding to what class the competitor will register to. A competitor can participate in one class only. You can add classes, rolls etc. but keep in mind you can NOT change the sequence later. Also, if you delete a class, all competitors will be unavailable (but the data is retrievable). If more freedom is needed, let me know, but keep in mind: Your favourite apps have thousands of developers and cost billions! Not joking.', 'competitors') . '</p>';
    echo '</div>';
    
    // Right column
    echo '<div>';
    echo '<h2>' . esc_html__('How to use the registration and results page(s)', 'competitors') . '</h2>';
    echo '<p>' . esc_html__('You can display either the registration form for competitors or the results on any WP Post or Page with the following shortcodes:', 'competitors') . '</p>';
    echo '<pre>[competitors_form_public] or [competitors_scoring_public]</pre>';
    
    // Method 1: Using sprintf for complete sentence translation
    echo '<p>' . sprintf(
        /* translators: %s: URL link */
        esc_html__('There is a default page %s for your convenience, which you can use, edit, or delete.', 'competitors'),
        $default_page_link
    ) . '</p>';

    // Method 2: Using wp_kses with the external URL
    $external_link = sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_url($external_url),
        esc_html__('excellent page', 'competitors')
    );
    
    echo '<p>' . sprintf(
        /* translators: %s: URL link */
        esc_html__('Over at Qajaq USA there is an %s but with dodgy links where you can learn the roll names in Inuit. If the link is a no-go, go to "QAANNAT KATTUFFIAT" > "GREENLAND CHAMPIONSHIP" and have a look at that page.', 'competitors'),
        $external_link
    ) . '</p>';
    
    echo '</div>';
    echo '</div>'; // .two-cols
    echo '</div>'; // #instructions-content
    echo '</div>'; // .wrap
}


function render_competitors_main_settings_page() {
    if (!current_user_can('manage_options')) {
        echo esc_html__('Access denied to settings, sorry.', 'competitors');
        return;
    }

    // Render admin page header with navigation tabs
    render_admin_page_header();

    // Display a success message if settings were submitted
    if (get_transient('competitors_settings_submitted')) {
        echo '<div id="message" class="updated notice is-dismissible">';
        echo '<p>' . esc_html(get_transient('competitors_settings_submitted')) . '</p>';
        echo '<button type="button" class="notice-dismiss">';
        echo '<span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'competitors') . '</span>';
        echo '</button></div>';
        delete_transient('competitors_settings_submitted');
    }

    // Render the settings page content
    echo '<div class="wrap" id="settings-page">';
    echo '<h1>' . esc_html__('Rolls Settings', 'competitors') . '</h1>';

    // Display the instructions section
    competitors_settings_section_callback();

    // Render the settings form
    echo '<form method="post" action="options.php">';
    settings_fields('competitors_rollnames_settings_group'); // Output nonce and options group for security
    do_settings_sections('competitors_rollnames_settings');  // Display the sections and fields
    submit_button(); // Default submit button for saving options
    echo '</form>';
    echo '</div>';
}







/**
 * Renders the Classes and Dates tab content.
 */
function render_classes_dates_page() {
    if (!current_user_can('manage_options')) {
        echo esc_html__('Access denied to settings, sorry.', 'competitors');
        return;
    }

    // Render the admin page header with tabs
    render_admin_page_header();

    // Begin page content
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Classes & Dates', 'competitors') . '</h1>';

    // Render settings form
    echo '<form method="post" action="options.php">';
    settings_fields('competitors_classes_dates_settings_group'); // Security nonce for this settings group
    do_settings_sections('competitors_dates_settings'); // Output date-specific settings
    do_settings_sections('competitors_classes_settings'); // Output class-specific settings
    submit_button(); // Render default submit button
    echo '</form>';
    echo '</div>';
}



/**
 * Registers and initializes all plugin settings.
 */
function initialize_competitors_settings() {
    // Register settings for classes, dates, and rollnames
    register_setting(
        'competitors_rollnames_settings_group',
        'competitors_options',
        'competitors_options_sanitize' // Sanitization callback
    );
    register_setting(
        'competitors_classes_dates_settings_group',
        'competitors_options',
        'competitors_options_sanitize' // Sanitization callback
    );

    // Initialize individual settings sections
    initialize_competitors_classes_settings();
    initialize_competitors_dates_settings();
    initialize_competitors_rollnames_settings();
}
add_action('admin_init', 'initialize_competitors_settings');


/**
 * Initializes the settings section for managing competition classes.
 */
function initialize_competitors_classes_settings() {
    add_settings_section(
        'competitors_classes_section', // Section ID
        esc_html__('Manage Competition Classes', 'competitors'), // Title
        'competitors_classes_section_callback', // Callback to display description
        'competitors_classes_settings' // Page slug
    );

    add_settings_field(
        'competitors_classes_field', // Field ID
        esc_html__('Competition Classes', 'competitors'), // Label
        'render_competitors_classes_field', // Callback to render the field
        'competitors_classes_settings', // Page slug
        'competitors_classes_section' // Section ID
    );
}




function initialize_competitors_dates_settings() {
    add_settings_section(
        'competitors_dates_section',
        // Translatable string for section title
        esc_html__('Event Date and Name', 'competitors'),
        'competitors_dates_section_callback',
        'competitors_dates_settings'
    );

    add_settings_field(
        'competitors_dates_field',
        // Translatable string for field label
        esc_html__('Competition Dates', 'competitors'),
        'render_competitors_dates_field',
        'competitors_dates_settings',
        'competitors_dates_section'
    );
}

function initialize_competitors_rollnames_settings() {
    add_settings_section(
        'competitors_rollnames_section',
        // Translatable string for section title
        esc_html__('Roll Names and Points for all competition classes', 'competitors'),
        'competitors_rollnames_section_callback',
        'competitors_rollnames_settings'
    );

    $options = get_option('competitors_options', []);
    $participation_classes = isset($options['available_competition_classes']) ? $options['available_competition_classes'] : [];
    
    foreach ($participation_classes as $class) {
        if (!is_array($class) || !isset($class['name'])) {
            continue;
        }
        $class_name = sanitize_text_field($class['name']);

        add_settings_field(
            "competitors_text_field_{$class_name}",
            // Using sprintf for dynamic string translation
            sprintf(
                // Translatable string with placeholder
                esc_html__('Roll Names for - %s', 'competitors'),
                esc_html($class_name)
            ),
            function() use ($class_name) {
                render_competitors_roll_field($class_name);
            },
            'competitors_rollnames_settings',
            'competitors_rollnames_section'
        );
    }
}


function competitors_classes_section_callback() {
    echo wp_kses(
        '<p>' . esc_html__('The class here is for admin backend purposes, so only a-z please! The class comment is an explanation which appears in the registration form. You can use any characters here.', 'competitors') . '</p>',
        ['p' => []]
    );
}

function competitors_dates_section_callback() {
    echo wp_kses(
        '<p>' . esc_html__('Click in the date field and a calendar where you choose date should appear.', 'competitors') . '</p>',
        ['p' => []]
    );
}

function competitors_rollnames_section_callback() {
    echo wp_kses(
        '<p>' . esc_html__('The numeric checkbox is for speedrolls or meters paddled under water, so there is no more/less button but an input field.', 'competitors') . '</p>',
        ['p' => []]
    );
}

function render_competitors_classes_field() {
    $options = get_option('competitors_options', []);
    $classes = isset($options['available_competition_classes']) ? $options['available_competition_classes'] : [];

    if (!is_array($classes)) {
        $classes = [];
    }
    ob_start();
    ?>
    <div id="add-class-form">
        <label for="new_class_name"><?php esc_html_e('Class Data Name:', 'competitors'); ?></label>
        <input type="text" id="new_class_name" name="new_class_name" value="" />
        <label for="new_class_comment"><?php esc_html_e('Class Comment:', 'competitors'); ?></label>
        <input type="text" id="new_class_comment" name="new_class_comment" value="" />
        <button type="button" id="add-class-button" class="button button-primary plus-button"></button>
    </div>
    <ul id="existing_classes">
        <?php foreach ($classes as $index => $class) : ?>
            <?php if (is_array($class) && isset($class['name']) && isset($class['comment'])) : ?>
                <li class="class-item <?php echo $index % 2 == 0 ? 'alternate' : ''; ?>" data-name="<?php echo esc_attr($class['name']); ?>" data-comment="<?php echo esc_attr($class['comment']); ?>">
                    <?php echo esc_html($class['name'] . ' - ' . $class['comment']); ?>
                    <input type="hidden" name="competitors_options[available_competition_classes][]" value="<?php echo esc_attr(json_encode($class)); ?>" />
                    <button type="button" class="button-secondary remove-class-button"><?php esc_html_e('Remove', 'competitors'); ?></button>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
    <?php
    echo ob_get_clean();
}

function render_competitors_dates_field() {
    $options = get_option('competitors_options', []);
    $events = isset($options['available_competition_dates']) ? $options['available_competition_dates'] : [];
    if (!is_array($events)) {
        $events = [];
    }
    ob_start();
    ?>
    <div id="add-event-form">
        <label for="new_competition_date"><?php esc_html_e('New Competition Date:', 'competitors'); ?></label>
        <input type="text" id="new_competition_date" class="date-picker" name="new_competition_date" value="" />
        <label for="new_event_name"><?php esc_html_e('Event Name:', 'competitors'); ?></label>
        <input type="text" id="new_event_name" name="new_event_name" value="" />
        <button type="button" id="add-event-button" class="button custom-button button-primary plus-button"></button>
    </div>
    <ul id="existing_events">
        <?php foreach ($events as $index => $event): ?>
            <li class="event-item <?php echo $index % 2 == 0 ? 'alternate' : ''; ?>" data-date="<?php echo esc_attr($event['date']); ?>" data-name="<?php echo esc_attr($event['name']); ?>">
                <?php echo esc_html($event['date'] . ' - ' . $event['name']); ?>
                <input type="hidden" name="competitors_options[available_competition_dates][]" value="<?php echo esc_attr(json_encode($event)); ?>" />
                <button type="button" class="button custom-button button-secondary remove-event-button"><?php esc_html_e('Remove', 'competitors'); ?></button>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
    echo ob_get_clean();
 }

 function render_competitors_roll_field($class = 'open') {
    $options = get_option('competitors_options', []);
    $roll_names = isset($options["custom_values_{$class}"]) ? $options["custom_values_{$class}"] : [];
    $points_values = isset($options["numeric_values_{$class}"]) ? $options["numeric_values_{$class}"] : [];
    $is_numeric_fields = isset($options["is_numeric_field_{$class}"]) ? $options["is_numeric_field_{$class}"] : [];
    $no_right_left = isset($options["no_right_left_{$class}"]) ? $options["no_right_left_{$class}"] : [];
 
    $roll_names = is_array($roll_names) ? $roll_names : [];
    $points_values = is_array($points_values) ? $points_values : array_fill(0, count($roll_names), '');
    $is_numeric_fields = is_array($is_numeric_fields) ? $is_numeric_fields : array_fill(0, count($roll_names), false);
    $no_right_left = is_array($no_right_left) ? $no_right_left : array_fill(0, count($roll_names), false);
 
    ob_start();
    ?>
    <div id="competitors_roll_names_wrapper_<?php echo esc_attr($class); ?>">
        <?php if (empty($roll_names)) {
            $roll_names = [''];
            $points_values = [''];
            $is_numeric_fields = [false];
            $no_right_left = [false];
        }
 
        foreach ($roll_names as $index => $roll_name) {
            $roll_name = trim($roll_name);
            $point_value = isset($points_values[$index]) ? esc_attr($points_values[$index]) : '0';
            $numeric_checked = isset($is_numeric_fields[$index]) && $is_numeric_fields[$index] === '1' ? 'checked' : '';
            $no_right_left_checked = isset($no_right_left[$index]) && $no_right_left[$index] === '1' ? 'checked' : '';
            ?>
            <p class="roll-item <?php echo $index % 2 == 0 ? 'alternate' : ''; ?>" data-index="<?php echo esc_attr($index); ?>">
                <label for="maneuver_<?php echo esc_attr($class . '_' . $index); ?>"><?php echo esc_html($index + 1); ?>. </label>
                <input type="text" id="maneuver_<?php echo esc_attr($class . '_' . $index); ?>" name="competitors_options[custom_values_<?php echo esc_attr($class); ?>][]" size="60" value="<?php echo esc_attr($roll_name); ?>" />
                <label for="points_<?php echo esc_attr($class . '_' . $index); ?>"><?php esc_html_e('Points:', 'competitors'); ?></label>
                <input type="text" class="numeric-input" id="points_<?php echo esc_attr($class . '_' . $index); ?>" name="competitors_options[numeric_values_<?php echo esc_attr($class); ?>][]" size="2" maxlength="2" pattern="\d*" value="<?php echo esc_attr($point_value); ?>" />
                <label for="numeric_<?php echo esc_attr($class . '_' . $index); ?>"><?php esc_html_e('Numeric:', 'competitors'); ?></label>
                <input type="checkbox" id="numeric_<?php echo esc_attr($class . '_' . $index); ?>" name="competitors_options[is_numeric_field_<?php echo esc_attr($class); ?>][<?php echo esc_attr($index); ?>]" value="1" <?php echo $numeric_checked; ?>>
                <label for="no_right_left_<?php echo esc_attr($class . '_' . $index); ?>"><?php esc_html_e('No Right/Left:', 'competitors'); ?></label>
                <input type="checkbox" id="no_right_left_<?php echo esc_attr($class . '_' . $index); ?>" name="competitors_options[no_right_left_<?php echo esc_attr($class); ?>][<?php echo esc_attr($index); ?>]" value="1" <?php echo $no_right_left_checked; ?>>
                <?php if ($index === 0) { ?>
                    <button type="button" id="add_more_roll_names_<?php echo esc_attr($class); ?>" class="button custom-button button-primary plus-button"></button>
                <?php } ?>
                <button type="button" class="button custom-button button-secondary remove-row"><?php esc_html_e('Remove', 'competitors'); ?></button>
            </p>
        <?php } ?>
    </div>
    <?php
    echo ob_get_clean();
 }


/**
 * Handles AJAX requests for removing rows dynamically from the plugin's settings page.
 */
function handle_ajax_row_removal_for_competitors() {
    check_ajax_referer('competitors_nonce_action', 'security');

    if (isset($_POST['index']) && isset($_POST['class'])) {
        $index = intval($_POST['index']);
        $class = sanitize_text_field($_POST['class']);
        $options = get_option('competitors_options', []);
        
        $roll_names_key = "custom_values_{$class}";
        $points_values_key = "numeric_values_{$class}";
        $is_numeric_fields_key = "is_numeric_field_{$class}";

        $roll_names = isset($options[$roll_names_key]) ? $options[$roll_names_key] : [];
        $points_values = isset($options[$points_values_key]) ? $options[$points_values_key] : [];
        $is_numeric_fields = isset($options[$is_numeric_fields_key]) ? $options[$is_numeric_fields_key] : [];

        $roll_names = is_array($roll_names) ? $roll_names : [];
        $points_values = is_array($points_values) ? $points_values : [];
        $is_numeric_fields = is_array($is_numeric_fields) ? $is_numeric_fields : [];

        $item_removed = false;

        if (isset($roll_names[$index])) {
            unset($roll_names[$index]);
            $roll_names = array_values($roll_names);
            $options[$roll_names_key] = $roll_names;
            $item_removed = true;
        }

        if (isset($points_values[$index])) {
            unset($points_values[$index]);
            $points_values = array_values($points_values);
            $options[$points_values_key] = $points_values;
            $item_removed = true;
        }

        if (isset($is_numeric_fields[$index])) {
            unset($is_numeric_fields[$index]);
            $is_numeric_fields = array_values($is_numeric_fields);
            $options[$is_numeric_fields_key] = $is_numeric_fields;
            $item_removed = true;
        }

        update_option('competitors_options', $options);

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

function competitors_custom_values_sanitize($input) {
    return array_map('sanitize_text_field', is_array($input) ? $input : []);
}

function competitors_numeric_values_sanitize($input) {
    return array_map('sanitize_text_field', is_array($input) ? $input : []);
}

function competitors_is_numeric_field_sanitize($input) {
    return array_map(function($item) {
        return filter_var($item, FILTER_VALIDATE_BOOLEAN);
    }, is_array($input) ? $input : []);
}


// Temp function for logging
/*
add_action('admin_post_update_competitors_options', 'debug_competitors_options_submission');

function debug_competitors_options_submission() {
    if (isset($_POST['competitors_options'])) {
        error_log('Form Submission Data: ' . print_r($_POST['competitors_options'], true));
    }
}
*/


function competitors_options_sanitize($input) {
	$sanitized = get_option('competitors_options', []);

	// Sanitize available competition classes
	if (isset($input['available_competition_classes'])) {
		$sanitized['available_competition_classes'] = [];
		foreach ($input['available_competition_classes'] as $class) {
			if (is_array($class) && isset($class['name']) && isset($class['comment'])) {
				$sanitized['available_competition_classes'][] = [
					'name' => sanitize_text_field($class['name']),
					'comment' => sanitize_text_field($class['comment']),
				];
			} elseif (is_string($class)) {
				$decoded_class = json_decode(urldecode($class), true);
				if (is_array($decoded_class) && isset($decoded_class['name']) && isset($decoded_class['comment'])) {
					$sanitized['available_competition_classes'][] = [
						'name' => sanitize_text_field($decoded_class['name']),
						'comment' => sanitize_text_field($decoded_class['comment']),
					];
				}
			}
		}
	}

	// Sanitize available competition dates
	if (isset($input['available_competition_dates'])) {
		$sanitized['available_competition_dates'] = [];
		foreach ($input['available_competition_dates'] as $event) {
			if (is_array($event) && isset($event['date']) && isset($event['name'])) {
				$sanitized['available_competition_dates'][] = [
					'date' => sanitize_text_field($event['date']),
					'name' => sanitize_text_field($event['name']),
				];
			} elseif (is_string($event)) {
				$decoded_event = json_decode(urldecode($event), true);
				if (is_array($decoded_event) && isset($decoded_event['date']) && isset($decoded_event['name'])) {
					$sanitized['available_competition_dates'][] = [
						'date' => sanitize_text_field($decoded_event['date']),
						'name' => sanitize_text_field($decoded_event['name']),
					];
				}
			}
		}
	}

	// Sanitize custom values and checkboxes
    foreach ($input as $key => $value) {
        if (strpos($key, 'custom_values_') === 0) {
            $sanitized[$key] = array_map('sanitize_text_field', (array) $value);
        } elseif (strpos($key, 'numeric_values_') === 0) {
            $sanitized[$key] = array_map('sanitize_text_field', (array) $value);
        } elseif (strpos($key, 'is_numeric_field_') === 0) {
            $sanitized[$key] = array_map(function($item) {
                return filter_var($item, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }, (array) $value);
        } elseif (strpos($key, 'no_right_left_') === 0) {
            $sanitized[$key] = array_map(function($item) {
                return filter_var($item, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }, (array) $value);
        }
    }

	return $sanitized;
}


/**
 * Retrieves the roll names and their max scores from WordPress options for a specific class.
 * @param string $class The participation class.
 * @return array An array of roll names with their max scores and numeric status for the specified class.
 */
function get_roll_names_and_max_scores($class = '') {
    if (empty($class)) {
        $class = 'open';
    }
    $options = get_option('competitors_options', []);
    $roll_names = isset($options["custom_values_{$class}"]) ? $options["custom_values_{$class}"] : [];
    $roll_max_scores = isset($options["numeric_values_{$class}"]) ? $options["numeric_values_{$class}"] : [];
    $is_numeric_fields = isset($options["is_numeric_field_{$class}"]) ? $options["is_numeric_field_{$class}"] : [];
    $no_right_left = isset($options["no_right_left_{$class}"]) ? $options["no_right_left_{$class}"] : [];

    // Combining roll data
    $combined = [];
    foreach ($roll_names as $index => $name) {
        $name = trim($name);
        if (!empty($name)) {
            $max_score = isset($roll_max_scores[$index]) && $roll_max_scores[$index] !== '' ? $roll_max_scores[$index] : 'N/A';
            $is_numeric = isset($is_numeric_fields[$index]) && $is_numeric_fields[$index] ? 'Yes' : 'No';
            $no_right_left_value = isset($no_right_left[$index]) && $no_right_left[$index] ? 'Yes' : 'No';

            $combined[] = [
                'name' => ($index + 1) . '. ' . $name, // Adding index number
                'max_score' => $max_score,
                'is_numeric' => $is_numeric,
                'no_right_left' => $no_right_left_value,
            ];
        }
    }

    // Return combined array or default value
    return !empty($combined) ? $combined : [['name' => '1. No roll names defined', 'max_score' => 'N/A', 'is_numeric' => 'N/A', 'no_right_left' => 'N/A']];
}


/**
 * Shortcode attributes with default values for URL and button text
 * [custom_button url="https://rugd.se" text="Go there or go Home"]
 * Or just [custom_button text="Back to Previous Page"]
 */
function custom_back_button_shortcode($atts) {
    $attributes = shortcode_atts(array(
        'url' => '', // Default is empty, meaning no custom URL is provided
        'text' => 'Back', // Default button text
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
 * If you dont like it, just use the WP default user "capabilities".
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
 * Redirects the user to the "competitors-scoring" admin page based on a transient flag set on login.
 * The redirection is intended for users who can edit competitors with the "competitors_judge" role or
 * equivalent to get to relevant content in the admin dashboard. Sometimes it actually works too...
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
 * The Transient is used to ensure that the redirection happens only once immediately after login,
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

    // Define an array of plugin's admin page screen IDs
    $plugin_pages = [
        'toplevel_page_competitors-settings',
        'competitors-settings_page_competitors-detailed-data',
        'competitors-settings_page_competitors-scoring',
        // Add more screen IDs as needed
    ];

    // Check if the current screen ID is in the array of plugin pages
    if (in_array($screen->id, $plugin_pages)) {
        $version_text = 'This WP Competitors plugin is version: ' . COMPETITORS_PLUGIN_VERSION . ' and still in Beta. If you have encountered bugs or have ideas on how to do it better, don\'t be a stranger!';
        echo 'Thank you for creating friendly kayak rolling events with this plugin! You can reach the developer on <a href="https://github.com/Tdude/">Github</a>, <a href="https://www.instagram.com/tdudesthlm/">Insta</a> or on my kayak rant site <a href="https://rugd.se/">RUGD.se</a>. ' . $version_text;
    }
}
add_filter('admin_footer_text', 'customize_admin_footer_text');


/**
 * Appends custom arbitrary text next to the WordPress version number in the admin footer.
 */
function append_text_to_admin_footer_version( $text ) {
    $custom_text = 'WP '; // more bragging text here if you feel like it
    return $custom_text . $text ;
}
add_filter('update_footer', 'append_text_to_admin_footer_version', 11);

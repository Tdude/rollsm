<?php
/**
 * Plugin Name: Competitors
 * Description:  For RollSM, A Greenland Rolling Championships registering and scoreboard plugin with live scores.
 * Version: 1.9
 * Author: <a href="https://klickomaten.com">Tibor Berki</a>. /Tdude @Github.
 * Text Domain: competitors
 * Domain Path: /languages
 */

define('COMPETITORS_PLUGIN_VERSION', '1.9');
define('COMPETITORS_PLUGIN_DIR', plugin_dir_path(__FILE__));



/**
 * Autoloader for Competitors_* classes.
 * Maps Competitors_Foo_Bar to includes/Foo/Bar.php (underscore = directory separator).
 * Also checks includes/Repository/ for repository classes.
 * Handles: includes/, includes/Repository/, includes/Admin/, includes/Ajax/
 */
spl_autoload_register(function ($class) {
    $prefix = 'Competitors_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));

    // First: try underscore-to-path mapping (Admin_ScoringPage → Admin/ScoringPage.php)
    $path = str_replace('_', '/', $relative);
    $file = COMPETITORS_PLUGIN_DIR . 'includes/' . $path . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // Second: try direct name in includes/ root (Database.php, CompetitionLock.php)
    $file = COMPETITORS_PLUGIN_DIR . 'includes/' . $relative . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // Third: try Repository/ subdirectory (CompetitionRepository.php, ScoreRepository.php)
    $file = COMPETITORS_PLUGIN_DIR . 'includes/Repository/' . $relative . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
});


/**
 * On activation: create custom tables + seed defaults (Phase 1 of rewrite).
 * This runs alongside the existing CPT code — both coexist during migration.
 */
register_activation_hook(__FILE__, function () {
    Competitors_Activator::activate();
});

/**
 * On deactivation: clean up transients and temp data.
 */
register_deactivation_hook(__FILE__, function () {
    Competitors_Deactivator::deactivate();
});

/**
 * On admin_init: check if DB needs upgrading (e.g. after plugin update).
 */
add_action('admin_init', function () {
    if (Competitors_Database::needs_upgrade()) {
        Competitors_Database::create_tables();
    }

    // Ensure current competition has roll snapshots (catch-up for pre-existing competitions)
    if (Competitors_Migration::is_complete()) {
        $current = Competitors_CompetitionRepository::find_current();
        if ($current) {
            global $wpdb;
            $snapshot_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . Competitors_Database::table('competition_rolls') . " WHERE competition_id = %d",
                (int) $current['id']
            ));
            if ($snapshot_count === 0) {
                Competitors_CompetitionRepository::snapshot_rolls_for_competition((int) $current['id']);
            }
        }
    }
});

// Initialize migration admin notice + AJAX handlers
Competitors_MigrationAdmin::init();

// When migration is complete, use new Admin classes backed by custom tables.
// The old callbacks remain registered but get overridden at higher priority.
if ( Competitors_Migration::is_complete() ) {
    // Sync CPT saves to custom tables during transition
    Competitors_CptSync::init();

    // Sync settings changes (classes, dates, rolls) to custom tables
    Competitors_SettingsSync::init();

    // Register new AJAX handlers
    Competitors_Ajax_AdminAjaxHandler::init();
    Competitors_Ajax_PublicAjaxHandler::init();
    Competitors_Ajax_OfflineSyncHandler::init();

    // Override shortcodes to use custom-table-backed versions
    add_action('init', function () {
        remove_shortcode('competitors_form_public');
        add_shortcode('competitors_form_public', array('Competitors_Public_RegistrationForm', 'render'));

        remove_shortcode('competitors_scoring_public');
        add_shortcode('competitors_scoring_public', array('Competitors_Public_Scoreboard', 'render'));
    }, 20);

    // Enqueue offline-sync.js on scoring admin pages
    add_action('admin_enqueue_scripts', function ($hook) {
        $scoring_pages = array(
            'competitors-settings_page_competitors-scoring',
        );
        if (!in_array($hook, $scoring_pages, true)) {
            return;
        }
        $competition = Competitors_CompetitionRepository::find_current();
        if (!$competition) {
            return;
        }
        wp_enqueue_script(
            'competitors-offline-sync',
            plugins_url('assets/js/offline-sync.js', __FILE__),
            array(),
            COMPETITORS_PLUGIN_VERSION,
            true
        );
        wp_localize_script('competitors-offline-sync', 'competitorsOfflineSync', array(
            'ajaxurl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('competitors_nonce_action'),
            'competitionId' => (int) $competition['id'],
            'isLocked'      => Competitors_CompetitionLock::is_locked((int) $competition['id']),
        ));
    });
}


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
// Include migration script (created in Phase 2)
if (file_exists(plugin_dir_path(__FILE__) . 'migration.php')) {
    include_once plugin_dir_path(__FILE__) . 'migration.php';
}


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

    // Display the input fields using WP form-table for consistent column alignment
    ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="competitors_club"><?php esc_html_e('Club:', 'competitors'); ?></label></th>
            <td><input type="text" id="competitors_club" name="competitors_club" value="<?php echo esc_attr($club); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th scope="row"><label for="competitors_participation_class"><?php esc_html_e('Participation Class:', 'competitors'); ?></label></th>
            <td>
                <select id="competitors_participation_class" name="competitors_participation_class">
                    <option value=""><?php esc_html_e('-- Select Class --', 'competitors'); ?></option>
                    <?php
                    if (class_exists('Competitors_Migration') && Competitors_Migration::is_complete()) {
                        $available_classes = Competitors_ClassRepository::find_all();
                        foreach ($available_classes as $cls) {
                            $lbl = !empty($cls['comment']) ? $cls['comment'] : $cls['name'];
                            printf('<option value="%s" %s>%s</option>', esc_attr($cls['name']), selected($participation_class, $cls['name'], false), esc_html($lbl));
                        }
                    } else {
                        $opts = get_option('competitors_options', []);
                        $classes_list = isset($opts['available_competition_classes']) ? $opts['available_competition_classes'] : [];
                        foreach ($classes_list as $cls) {
                            if (is_array($cls) && isset($cls['name'])) {
                                $lbl = !empty($cls['comment']) ? $cls['comment'] : $cls['name'];
                                printf('<option value="%s" %s>%s</option>', esc_attr($cls['name']), selected($participation_class, $cls['name'], false), esc_html($lbl));
                            }
                        }
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="competitors_email"><?php esc_html_e('Email:', 'competitors'); ?></label></th>
            <td><input type="email" id="competitors_email" name="competitors_email" value="<?php echo esc_attr($email); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th scope="row"><label for="competitors_speaker_info"><?php esc_html_e('Speaker Info:', 'competitors'); ?></label></th>
            <td><input type="text" id="competitors_speaker_info" name="competitors_speaker_info" value="<?php echo esc_attr($speaker_info); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Gender:', 'competitors'); ?></th>
            <td>
                <fieldset>
                    <label><input type="radio" name="competitors_gender" value="woman" <?php checked($gender, 'woman'); ?>> <?php esc_html_e('Woman', 'competitors'); ?></label>
                    <label style="margin-left:12px;"><input type="radio" name="competitors_gender" value="man" <?php checked($gender, 'man'); ?>> <?php esc_html_e('Man', 'competitors'); ?></label>
                </fieldset>
            </td>
        </tr>
    </table>
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
 * Adds a submenu page under the plugin's settings for personal data management.
 * When migration is complete, delegates to the new custom-table-backed class.
 */
function add_competitors_submenu_for_personal_data() {
    $use_new = class_exists( 'Competitors_Migration' ) && Competitors_Migration::is_complete();
    $cb = $use_new ? array( 'Competitors_Admin_PersonalDataPage', 'render' ) : 'competitors_admin_page';
    add_submenu_page( 'competitors-settings', __( 'Competitors Personal Data', 'competitors' ), __( 'Personal Data', 'competitors' ), 'edit_competitors', 'competitors-detailed-data', $cb );
}
add_action('admin_menu', 'add_competitors_submenu_for_personal_data');

/**
 * Adds a submenu page for judges to enter scoring information.
 * When migration is complete, delegates to the new custom-table-backed class.
 */
function add_competitors_submenu_for_judges_scoring() {
    $use_new = class_exists( 'Competitors_Migration' ) && Competitors_Migration::is_complete();
    $cb = $use_new ? array( 'Competitors_Admin_ScoringPage', 'render' ) : 'judges_scoring_page';
    add_submenu_page( 'competitors-settings', __( 'Judges Scoring Submenu', 'competitors' ), __( 'Judges Scoring', 'competitors' ), 'edit_competitors', 'competitors-scoring', $cb );
}
add_action('admin_menu', 'add_competitors_submenu_for_judges_scoring');


/**
 * Everyone loves tabs in the admin, right?
 * You can just remove this if you don't.
 */
function render_admin_page_header() {
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

    $tabs = [
        'competitors-settings' => esc_html__('Rolls & Points', 'competitors'),
        'competitors-classes-dates' => esc_html__('Classes & Dates', 'competitors'),
        'competitors-detailed-data' => esc_html__('Competitor List', 'competitors'),
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

    // Render the settings page content
    echo '<div class="wrap" id="settings-page">';
    echo '<h1>' . esc_html__('Rolls & Points', 'competitors') . '</h1>';

    // Display a success message if settings were submitted
    if (get_transient('competitors_settings_submitted')) {
        echo '<div id="message" class="updated notice is-dismissible">';
        echo '<p>' . esc_html(get_transient('competitors_settings_submitted')) . '</p>';
        echo '<button type="button" class="notice-dismiss">';
        echo '<span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'competitors') . '</span>';
        echo '</button></div>';
        delete_transient('competitors_settings_submitted');
    }

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
    initialize_roll_date_mapping_settings();
}
add_action('admin_init', 'initialize_competitors_settings');


/**
 * Initializes the settings section for managing competition classes.
 */
function initialize_competitors_classes_settings() {
    add_settings_section(
        'competitors_classes_section',
        '', // No title — the page h1 is sufficient
        'render_competitors_classes_section',
        'competitors_classes_settings'
    );
}

function initialize_competitors_dates_settings() {
    add_settings_section(
        'competitors_dates_section',
        '', // No title — avoids duplicate heading under h1
        'render_competitors_dates_section',
        'competitors_dates_settings'
    );
}

function initialize_competitors_rollnames_settings() {
    add_settings_section(
        'competitors_rollnames_section',
        esc_html__('Roll Definitions', 'competitors'),
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
        $class_label = !empty($class['comment']) ? sanitize_text_field($class['comment']) : $class_name;

        add_settings_field(
            "competitors_text_field_{$class_name}",
            sprintf(
                esc_html__('Roll Names for %s', 'competitors'),
                esc_html($class_label)
            ),
            function() use ($class_name) {
                render_competitors_roll_field($class_name);
            },
            'competitors_rollnames_settings',
            'competitors_rollnames_section'
        );
    }
}

/**
 * Initializes the settings section for roll-date mapping.
 * This allows configuration of which rolls are available for each competition date and participation class.
 */
function initialize_roll_date_mapping_settings() {
    add_settings_section(
        'competitors_roll_date_mapping_section',
        // Translatable string for section title
        esc_html__('Roll to Date Mapping', 'competitors'),
        'competitors_roll_date_mapping_section_callback',
        'competitors_roll_date_mapping_settings'
    );

    add_settings_field(
        'competitors_roll_date_mapping_field',
        // Translatable string for field label
        esc_html__('Configure Roll Availability by Date', 'competitors'),
        'render_competitors_roll_date_mapping_field',
        'competitors_roll_date_mapping_settings',
        'competitors_roll_date_mapping_section'
    );
}


/**
 * Combined section callback for classes: description + add form + list.
 * Renders directly instead of via add_settings_field to avoid nested form-tables.
 */
function render_competitors_classes_section() {
    echo '<h3>' . esc_html__('Classes', 'competitors') . '</h3>';
    echo '<p>' . esc_html__('Add competition classes below. The "Class Name" is what competitors see in the registration form. An internal ID is generated automatically.', 'competitors') . '</p>';
    render_competitors_classes_field();
}

/**
 * Combined section callback for dates: description + add form + list.
 */
function render_competitors_dates_section() {
    echo '<h3>' . esc_html__('Dates', 'competitors') . '</h3>';
    echo '<p>' . esc_html__('Click in the date field to pick a date. Each date creates a competition event.', 'competitors') . '</p>';
    render_competitors_dates_field();
}

function competitors_roll_date_mapping_section_callback() {
    echo wp_kses(
        '<p>' . esc_html__('Configure which rolls are available for each competition date and participation class.', 'competitors') . '</p>',
        ['p' => []]
    );
}

function render_competitors_roll_date_mapping_field() {
    $options = get_option('competitors_options', []);
    $events = isset($options['available_competition_dates']) ? $options['available_competition_dates'] : [];
    $date_roll_mapping = isset($options['date_roll_mapping']) ? $options['date_roll_mapping'] : [];
    $classes = isset($options['competition_classes']) ? $options['competition_classes'] : ['open'];
    
    if (empty($events)) {
        echo '<p>' . esc_html__('You need to add competition dates before you can configure roll mappings.', 'competitors') . '</p>';
        return;
    }
    
    echo '<div class="roll-date-mapping-container">';
    
    foreach ($events as $event) {
        $date = isset($event['date']) ? $event['date'] : '';
        $name = isset($event['name']) ? $event['name'] : '';
        
        if (empty($date)) continue;
        
        echo '<div class="date-mapping-section">';
        echo '<h4>' . esc_html($date . ' - ' . $name) . '</h4>';
        
        foreach ($classes as $class) {
            // Get all rolls for this class
            $roll_data = get_roll_names_and_max_scores($class);
            if (empty($roll_data) || (isset($roll_data[0]['name']) && $roll_data[0]['name'] == '1. No roll names defined')) {
                continue;
            }
            
            echo '<div class="class-mapping">';
            echo '<h5>' . esc_html(ucfirst($class)) . '</h5>';
            
            // Simple table layout for checkboxes
            echo '<table class="widefat">';
            echo '<tr>';
            $counter = 0;
            
            foreach ($roll_data as $index => $roll) {
                $roll_name = preg_replace('/^\d+\.\s/', '', $roll['name']);
                $roll_index = $index;
                $checked = '';
                
                // Check if this roll is mapped to this date and class
                if (isset($date_roll_mapping[$date][$class]) && 
                    in_array($roll_index, $date_roll_mapping[$date][$class])) {
                    $checked = 'checked="checked"';
                }
                
                if ($counter % 3 == 0 && $counter > 0) {
                    echo '</tr><tr>';
                }
                
                echo '<td>';
                echo '<label>';
                echo '<input type="checkbox" name="competitors_options[date_roll_mapping][' . esc_attr($date) . '][' . esc_attr($class) . '][]" value="' . esc_attr($roll_index) . '" ' . $checked . '>';
                echo esc_html($roll_name);
                echo '</label>';
                echo '</td>';
                
                $counter++;
            }
            
            // Fill remaining cells in the last row
            while ($counter % 3 != 0) {
                echo '<td></td>';
                $counter++;
            }
            
            echo '</tr>';
            echo '</table>';
            echo '</div>'; // .class-mapping
        }
        
        echo '</div>'; // .date-mapping-section
    }
    
    echo '</div>'; // .roll-date-mapping-container
}

function competitors_rollnames_section_callback() {
    echo wp_kses(
        '<p>' . esc_html__('The numeric checkbox is for speedrolls or meters paddled under water (so there is no more/less button but an input field on the scoresheet).', 'competitors') . '</p>',
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
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="new_class_comment"><?php esc_html_e('Class Name:', 'competitors'); ?></label></th>
            <td>
                <input type="text" id="new_class_comment" name="new_class_comment" value="" placeholder="<?php esc_attr_e('e.g. Open (International)', 'competitors'); ?>" class="regular-text" />
                <span id="class-slug-preview" style="color:#666; margin-left:8px;"></span>
                <button type="button" id="add-class-button" class="button button-primary plus-button"></button>
            </td>
        </tr>
    </table>

    <table class="wp-list-table widefat fixed striped" id="existing_classes">
        <thead>
            <tr>
                <th><?php esc_html_e('Class Name', 'competitors'); ?></th>
                <th style="width:200px;"><?php esc_html_e('ID', 'competitors'); ?></th>
                <th style="width:100px;"><?php esc_html_e('Actions', 'competitors'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($classes)) : ?>
                <tr class="no-items"><td colspan="3"><?php esc_html_e('No classes added yet.', 'competitors'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($classes as $index => $class) : ?>
                <?php if (is_array($class) && isset($class['name']) && isset($class['comment'])) : ?>
                    <tr class="class-item" data-name="<?php echo esc_attr($class['name']); ?>" data-comment="<?php echo esc_attr($class['comment']); ?>">
                        <td><strong><?php echo esc_html($class['comment'] ?: $class['name']); ?></strong></td>
                        <td><code><?php echo esc_html($class['name']); ?></code></td>
                        <td>
                            <input type="hidden" name="competitors_options[available_competition_classes][]" value="<?php echo esc_attr(json_encode($class)); ?>" />
                            <button type="button" class="button-secondary button-small remove-class-button"><?php esc_html_e('Remove', 'competitors'); ?></button>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
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
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="new_competition_date"><?php esc_html_e('Date:', 'competitors'); ?></label></th>
            <td>
                <input type="text" id="new_competition_date" class="date-picker" name="new_competition_date" value="" />
                <label for="new_event_name" style="margin-left:12px;"><?php esc_html_e('Event Name:', 'competitors'); ?></label>
                <input type="text" id="new_event_name" name="new_event_name" value="" class="regular-text" />
                <button type="button" id="add-event-button" class="button button-primary plus-button"></button>
            </td>
        </tr>
    </table>

    <table class="wp-list-table widefat fixed striped" id="existing_events">
        <thead>
            <tr>
                <th style="width:140px;"><?php esc_html_e('Date', 'competitors'); ?></th>
                <th><?php esc_html_e('Event Name', 'competitors'); ?></th>
                <th style="width:100px;"><?php esc_html_e('Actions', 'competitors'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($events)) : ?>
                <tr class="no-items"><td colspan="3"><?php esc_html_e('No events added yet.', 'competitors'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($events as $index => $event): ?>
                <tr class="event-item" data-date="<?php echo esc_attr($event['date']); ?>" data-name="<?php echo esc_attr($event['name']); ?>">
                    <td><strong><?php echo esc_html($event['date']); ?></strong></td>
                    <td><?php echo esc_html($event['name']); ?></td>
                    <td>
                        <input type="hidden" name="competitors_options[available_competition_dates][]" value="<?php echo esc_attr(json_encode($event)); ?>" />
                        <button type="button" class="button-secondary button-small remove-event-button"><?php esc_html_e('Remove', 'competitors'); ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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

    // Sanitize class name for use in HTML IDs (no spaces, lowercase)
    $class_slug = sanitize_title($class);

    // Build options list of OTHER classes for the copy-from picker
    $other_classes = array();
    $all_classes_opt = get_option('competitors_options', array());
    $available = isset($all_classes_opt['available_competition_classes']) ? $all_classes_opt['available_competition_classes'] : array();
    if (!is_array($available)) {
        $available = array();
    }
    foreach ($available as $c) {
        if (!is_array($c) || empty($c['name']) || $c['name'] === $class) {
            continue;
        }
        $other_classes[] = array(
            'name'  => sanitize_text_field($c['name']),
            'label' => !empty($c['comment']) ? sanitize_text_field($c['comment']) : sanitize_text_field($c['name']),
        );
    }

    ob_start();
    ?>
    <?php if (!empty($other_classes)) : ?>
    <div class="copy-rolls-control" style="margin-bottom:.75em;padding:.5em;background:#f6f7f7;border-left:3px solid #2271b1;">
        <label for="copy_rolls_src_<?php echo esc_attr($class_slug); ?>"><?php esc_html_e('Copy rolls from another class:', 'competitors'); ?></label>
        <select id="copy_rolls_src_<?php echo esc_attr($class_slug); ?>" data-target="<?php echo esc_attr($class); ?>">
            <option value=""><?php esc_html_e('— select source —', 'competitors'); ?></option>
            <?php foreach ($other_classes as $oc) : ?>
                <option value="<?php echo esc_attr($oc['name']); ?>"><?php echo esc_html($oc['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="button copy-rolls-btn"
                data-target="<?php echo esc_attr($class); ?>"
                data-select="copy_rolls_src_<?php echo esc_attr($class_slug); ?>"
                data-nonce="<?php echo esc_attr(wp_create_nonce('competitors_copy_rolls_' . $class)); ?>">
            <?php esc_html_e('Copy & overwrite', 'competitors'); ?>
        </button>
        <span class="copy-rolls-status" style="margin-left:.5em;"></span>
    </div>
    <?php endif; ?>
    <div id="competitors_roll_names_wrapper_<?php echo esc_attr($class_slug); ?>" data-class="<?php echo esc_attr($class); ?>">
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
                <label for="maneuver_<?php echo esc_attr($class_slug . '_' . $index); ?>"><?php echo esc_html($index + 1); ?>. </label>
                <input type="text" id="maneuver_<?php echo esc_attr($class_slug . '_' . $index); ?>" name="competitors_options[custom_values_<?php echo esc_attr($class); ?>][]" size="60" value="<?php echo esc_attr($roll_name); ?>" />
                <label for="points_<?php echo esc_attr($class_slug . '_' . $index); ?>"><?php esc_html_e('Points:', 'competitors'); ?></label>
                <input type="text" class="numeric-input" id="points_<?php echo esc_attr($class_slug . '_' . $index); ?>" name="competitors_options[numeric_values_<?php echo esc_attr($class); ?>][]" size="2" maxlength="2" pattern="\d*" value="<?php echo esc_attr($point_value); ?>" />
                <label for="numeric_<?php echo esc_attr($class_slug . '_' . $index); ?>"><?php esc_html_e('Numeric:', 'competitors'); ?></label>
                <input type="checkbox" id="numeric_<?php echo esc_attr($class_slug . '_' . $index); ?>" name="competitors_options[is_numeric_field_<?php echo esc_attr($class); ?>][<?php echo esc_attr($index); ?>]" value="1" <?php echo $numeric_checked; ?>>
                <label for="no_right_left_<?php echo esc_attr($class_slug . '_' . $index); ?>"><?php esc_html_e('No Right/Left:', 'competitors'); ?></label>
                <input type="checkbox" id="no_right_left_<?php echo esc_attr($class_slug . '_' . $index); ?>" name="competitors_options[no_right_left_<?php echo esc_attr($class); ?>][<?php echo esc_attr($index); ?>]" value="1" <?php echo $no_right_left_checked; ?>>
                <?php if ($index === 0) { ?>
                    <button type="button" id="add_more_roll_names_<?php echo esc_attr($class_slug); ?>" class="button custom-button button-primary plus-button" data-class="<?php echo esc_attr($class); ?>"></button>
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

/**
 * AJAX: copy rolls from one class to another inside competitors_options.
 * Triggers SettingsSync via update_option, which mirrors to comp_rolls
 * and rebuilds comp_competition_rolls for unlocked competitions.
 */
function handle_ajax_copy_rolls_between_classes() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied.'));
    }

    $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
    $target = isset($_POST['target']) ? sanitize_text_field(wp_unslash($_POST['target'])) : '';
    $nonce  = isset($_POST['nonce'])  ? sanitize_text_field(wp_unslash($_POST['nonce']))  : '';

    if ($source === '' || $target === '' || $source === $target) {
        wp_send_json_error(array('message' => 'Invalid source or target class.'));
    }

    if (!wp_verify_nonce($nonce, 'competitors_copy_rolls_' . $target)) {
        wp_send_json_error(array('message' => 'Nonce verification failed.'));
    }

    $options = get_option('competitors_options', array());
    if (!is_array($options)) {
        $options = array();
    }

    $copied_keys = array();
    foreach (array('custom_values', 'numeric_values', 'is_numeric_field', 'no_right_left') as $k) {
        $src_key = "{$k}_{$source}";
        $tgt_key = "{$k}_{$target}";
        if (isset($options[$src_key]) && is_array($options[$src_key])) {
            $options[$tgt_key] = $options[$src_key];
            $copied_keys[]     = $tgt_key;
        }
    }

    if (empty($copied_keys)) {
        wp_send_json_error(array('message' => "No rolls found for source class '{$source}'."));
    }

    update_option('competitors_options', $options);

    wp_send_json_success(array(
        'message' => sprintf('Copied %d field group(s) from %s to %s.', count($copied_keys), $source, $target),
        'keys'    => $copied_keys,
    ));
}
add_action('wp_ajax_competitors_copy_rolls_between_classes', 'handle_ajax_copy_rolls_between_classes');

/**
 * Inline JS for the "Copy & overwrite" button on the Roll Settings page.
 */
function competitors_copy_rolls_inline_js() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || strpos($screen->id, 'competitors') === false) {
        return;
    }
    ?>
    <script>
    (function () {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.copy-rolls-btn');
            if (!btn) return;
            var selectId = btn.dataset.select;
            var sel = document.getElementById(selectId);
            var source = sel ? sel.value : '';
            var target = btn.dataset.target;
            var status = btn.parentNode.querySelector('.copy-rolls-status');
            if (!source) {
                status.textContent = 'Pick a source class first.';
                status.style.color = 'red';
                return;
            }
            if (!confirm('Overwrite all rolls in "' + target + '" with the rolls from "' + source + '"? This cannot be undone.')) {
                return;
            }
            btn.disabled = true;
            status.textContent = 'Copying…';
            status.style.color = '';
            var data = new URLSearchParams({
                action: 'competitors_copy_rolls_between_classes',
                source: source,
                target: target,
                nonce:  btn.dataset.nonce
            });
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res && res.success) {
                    status.textContent = (res.data && res.data.message) || 'Copied.';
                    status.style.color = 'green';
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    status.textContent = (res && res.data && res.data.message) || 'Failed.';
                    status.style.color = 'red';
                    btn.disabled = false;
                }
            }).catch(function () {
                status.textContent = 'Network error.';
                status.style.color = 'red';
                btn.disabled = false;
            });
        });
    })();
    </script>
    <?php
}
add_action('admin_footer', 'competitors_copy_rolls_inline_js');

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
/**
 * Gets roll names and max scores filtered by participation class and optionally by competition date.
 * 
 * @param string $class The participation class
 * @param string $competition_date Optional competition date to filter rolls
 * @return array Filtered array of rolls
 */
function get_roll_names_and_max_scores($class = '', $competition_date = null) {
    if (empty($class)) {
        $class = 'open';
    }
    $options = get_option('competitors_options', []);
    $roll_names = isset($options["custom_values_{$class}"]) ? $options["custom_values_{$class}"] : [];
    $roll_max_scores = isset($options["numeric_values_{$class}"]) ? $options["numeric_values_{$class}"] : [];
    $is_numeric_fields = isset($options["is_numeric_field_{$class}"]) ? $options["is_numeric_field_{$class}"] : [];
    $no_right_left = isset($options["no_right_left_{$class}"]) ? $options["no_right_left_{$class}"] : [];
    
    // Get date-specific roll configuration if available
    $date_roll_mapping = isset($options['date_roll_mapping']) ? $options['date_roll_mapping'] : [];
    
    // Mapping of roll indexes to include for this competition date
    $included_indexes = null;
    if (!empty($competition_date) && isset($date_roll_mapping[$competition_date][$class])) {
        $included_indexes = $date_roll_mapping[$competition_date][$class];
    }

    // Combining roll data
    $combined = [];
    foreach ($roll_names as $index => $name) {
        $name = trim($name);
        if (!empty($name)) {
            // Skip rolls that are not included for this competition date
            if ($included_indexes !== null && !in_array($index, $included_indexes)) {
                continue;
            }
            
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
 * Captures a snapshot of roll definitions for a specific class and date
 *
 * @param string $class The participation class
 * @param string $date The competition date
 * @return array The captured roll definitions
 */
function get_roll_definitions_snapshot($class, $date) {
    return get_roll_names_and_max_scores($class, $date);
}

/**
 * Stores roll definitions for all classes for a specific competition date
 * This ensures all competitors in the same competition date use the same definitions
 *
 * @param string $competition_date The competition date to store definitions for
 * @return bool Whether the operation was successful
 */
function store_competition_roll_definitions($competition_date) {
    if (empty($competition_date)) {
        return false;
    }
    
    // Get all available classes
    $options = get_option('competitors_options', []);
    $classes = isset($options['available_competition_classes']) ? $options['available_competition_classes'] : [];
    
    if (empty($classes)) {
        return false;
    }
    
    $roll_definitions_by_class = [];
    
    // Store roll definitions for each class
    foreach ($classes as $class_data) {
        if (isset($class_data['name'])) {
            $class = $class_data['name'];
            $roll_definitions_by_class[$class] = get_roll_definitions_snapshot($class, $competition_date);
        }
    }
    
    // Store in wp_options using the competition date as key
    $option_name = 'competitors_roll_definitions_' . sanitize_title($competition_date);
    update_option($option_name, $roll_definitions_by_class, false); // No autoload
    
    return true;
}

/**
 * Retrieves stored roll definitions for a specific class and competition date
 *
 * @param string $class The participation class
 * @param string $competition_date The competition date
 * @return array|bool The roll definitions array or false if not found
 */
function get_competition_roll_definitions($class, $competition_date) {
    if (empty($class) || empty($competition_date)) {
        return false;
    }
    
    // Get stored definitions for this competition date
    $option_name = 'competitors_roll_definitions_' . sanitize_title($competition_date);
    $roll_definitions_by_class = get_option($option_name, []);
    
    // Return definitions for the requested class if they exist
    if (isset($roll_definitions_by_class[$class])) {
        return $roll_definitions_by_class[$class];
    }
    
    return false;
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

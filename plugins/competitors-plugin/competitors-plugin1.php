<?php
/**
 * Plugin Name: Competitors Plugin
 * Description: A not-so-basic plugin to manage competitors.
 * Version: 0.2
 * Author: Tdude via CHatGPT which has helpful one-liners but can't program...
 * Text Domain: competitors-plugin
 */

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



function competitors_form_shortcode() {
    ob_start(); 
    // Form HTML here...?>

<form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
<input type="hidden" name="action" value="competitors_form_submit">

        <h2>Anmälan  <?php if (current_user_can('manage_options')): ?>och poängresultat<?php endif; ?> RollSM 2024</h2>
        <label for="name">Name:</label>
        <input type="text" id="name" name="name"><br>

        <label for="email">Email:</label>
        <input type="text" id="email" name="email"><br>

        <label for="phone">Phone:</label>
        <input type="text" id="phone" name="phone"><br>

        <label for="club">Club:</label>
        <input type="text" id="club" name="club"><br>

        <label for="license">License:</label>
        <input type="checkbox" id="license" name="license"><br>

        <label for="sponsors">Sponsors:</label>
        <input type="text" id="sponsors" name="sponsors"><br>

        <label for="speaker_info">Speaker Info:</label>
        <textarea id="speaker_info" name="speaker_info"></textarea><br>

        <label>Participation in Class:</label>
        <input type="radio" id="open" name="participation_class" value="open">
        <label for="open">Open</label>
        <input type="radio" id="championship" name="participation_class" value="championship">
        <label for="championship">Championship</label>
        <input type="radio" id="amateur" name="participation_class" value="amateur">
        <label for="amateur">Amateur</label><br>

        <input type="checkbox" id="consent" name="consent">
        <label for="consent">Jag godkänner, har läst och förstått mm.</label><br>

        <fieldset>
            <legend>Performing Rolls</legend>
            <table>
                <tr>
                    <th>Ska göra</th>
                    <th>Rollnamn</th>
                    <?php if (current_user_can('manage_options')): ?>
                    <th>Poäng V</th>
                    <th>Avdrag V</th>
                    <th>Poäng H</th>
                    <th>Avdrag H</th>
                    <th>Summa</th>
                    <?php endif; ?>
                </tr>
            <?php 
        

            // Outside of loop
            $roll_names = get_option('competitors_custom_values');
            $roll_names_array = explode("\n", $roll_names);
            $roll_names_array = array_filter(array_map('trim', $roll_names_array));

            // Use count of $roll_names_array to control the loop
            for ($i = 0; $i < count($roll_names_array); $i++):
                ?>
                <tr>
                    <td><input type="checkbox" id="roll_<?php echo $i + 1; ?>" name="performing_rolls[]" onchange="checkAll(this)"></td>
                    <td><?php echo esc_html($roll_names_array[$i]); ?></td>
                    
                    <?php if (current_user_can('manage_options')): ?>
                        <!-- These fields are visible only to admins -->
                        <td><input type="text" name="left_<?php echo $i + 1; ?>" maxlength="2"></td>
                        <td><input type="text" name="left_deduct_<?php echo $i + 1; ?>" maxlength="2"></td>
                        <td><input type="text" name="right_<?php echo $i + 1; ?>" maxlength="2"></td>
                        <td><input type="text" name="right_deduct_<?php echo $i + 1; ?>" maxlength="2"></td>
                        <td><input type="text" name="total_<?php echo $i + 1; ?>" maxlength="2"></td>
                    <?php endif; ?>
                </tr>
            <?php endfor; ?>

            </table>
        </fieldset>

        <input type="submit" value="Submit"><?php
        wp_nonce_field('competitors_form_submission', 'competitors_nonce');
        ?>
    </form><?php

    return ob_get_clean(); 
}
add_shortcode('competitors_form', 'competitors_form_shortcode');


function sanitize_phone_number($phone) {
    return preg_replace('/[^\d\s\(\)-]/', '', $phone);
}





function handle_competitors_form_submission() {
    error_log('Form submission initiated.');
    
    if (isset($_POST['competitors_nonce'], $_POST['name'], $_POST['email']) && 
        wp_verify_nonce($_POST['competitors_nonce'], 'competitors_form_submission') && 
        $_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!isset($_POST['competitors_nonce'])) {
            error_log('Nonce field not set.');
        } else {
            error_log('Received nonce: ' . $_POST['competitors_nonce']);
        }
        
        // Sanitize and Validate input
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            error_log('Invalid email: ' . $email); // Log invalid email
            return;
        }

        $phone = sanitize_phone_number($_POST['phone']);
        $club = sanitize_text_field($_POST['club']);
        $sponsors = sanitize_text_field($_POST['sponsors']);
        $speaker_info = sanitize_textarea_field($_POST['speaker_info']);
        $participation_class = sanitize_text_field($_POST['participation_class']);
        $license = isset($_POST['license']) ? 'yes' : 'no';
        $consent = isset($_POST['consent']) ? 'yes' : 'no';

        // Handling performing_rolls as an array of values
        $performing_rolls = isset($_POST['performing_rolls']) ? array_map('sanitize_text_field', $_POST['performing_rolls']) : array();

        // Prepare data for insertion
        $competitor_data = array(
            'post_title'    => wp_strip_all_tags($name),
            'post_content'  => '',
            'post_status'   => 'publish',
            'post_type'     => 'competitors',
            'meta_input'    => array(
                'email' => $email,
                'phone' => $phone,
                'club' => $club,
                'sponsors' => $sponsors,
                'speaker_info' => $speaker_info,
                'participation_class' => $participation_class,
                'license' => $license,
                'consent' => $consent,
                // Additional meta fields
            ),
        );

        // Log data before insertion
        error_log('Inserting post with title: ' . $name);
        
        // Insert the post into the database
        $post_id = wp_insert_post($competitor_data);
        if ($post_id == 0) {
            // Log error in post creation
            error_log('Error in creating post.');
            return;
        } else {
            // Log successful creation
            error_log('Post created with ID: ' . $post_id);
        }
        
        // Save the performing_rolls data
        if ($post_id && !empty($performing_rolls)) {
            update_post_meta($post_id, 'performing_rolls', $performing_rolls);
        }
        // Log redirection
        error_log('Redirecting to thank-you page.');
        // Redirect after successful submission
        wp_redirect(home_url('/thank-you'));
        exit;

    } else {
        // Log that nonce verification failed or required fields are missing
        error_log('Sorry, form submission failed dude. Nonce verification failed or required fields are missing.');
    }
}

add_action('admin_post_competitors_form_submit', 'handle_competitors_form_submission');
add_action('admin_post_nopriv_competitors_form_submit', 'handle_competitors_form_submission');



function competitors_admin_menu() {
    add_menu_page(
        __('Competitors  Data', 'competitors-plugin'),
        __('Competitors Data', 'competitors-plugin'),
        'manage_options',
        'competitors-data',
        'competitors_admin_page',
        'dashicons-groups',
        //2, // Position (optional)
    );
}
add_action('admin_menu', 'competitors_admin_menu');



// The function that displays the content of the admin page
function competitors_admin_page() {
    // Fetch competitors from the database
    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1 // Fetch all posts
    );
    $competitors_query = new WP_Query($args);

    if ($competitors_query->have_posts()) {
        echo '<h1>Competitors gnarly Data</h1>';
        echo '<table border="1" style="width: 100%;">';
        echo '<tr><th>Name</th><th>Email</th><th>Phone</th><th>Club</th><th>Speaker Info</th></tr>';

        while ($competitors_query->have_posts()) {
            $competitors_query->the_post();

            // Get the meta data
            $email = get_post_meta(get_the_ID(), 'email', true);
            $phone = get_post_meta(get_the_ID(), 'phone', true);
            $club = get_post_meta(get_the_ID(), 'club', true);
            $speaker_info = get_post_meta(get_the_ID(), 'speaker_info', true);
            // Fetch other meta data similarly

            echo '<tr>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html($email) . '</td>'; 
            echo '<td>' . esc_html($phone) . '</td>';
            echo '<td>' . esc_html($club) . '</td>'; 
            echo '<td>' . esc_html($speaker_info) . '</td>'; 
            // Display other fields in similar fashion
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No competitors found.</p>';
    }

    wp_reset_postdata(); // Reset the query

}




// The settings page
function competitors_add_admin_menu() {
    add_menu_page(
        'Competitors Custom Settings', // Page title
        'Competitors Settings', // Menu title
        'manage_options', // Capability
        'competitors-settings', // Menu slug
        'competitors_settings_page', // Function to display the page
        'dashicons-groups' // Icon (optional)
        // 10 Position (optional)
    );
}
add_action('admin_menu', 'competitors_add_admin_menu');


// Display the settings page content
function competitors_settings_page() {
    echo '<div class="wrap"><h1>Competitors Settings</h1>';
    echo '<form method="post" action="options.php">';

    settings_fields('competitors_settings');
    do_settings_sections('competitors_settings');
    submit_button();

    echo '</form></div>';
}

// Register a new setting for our "Competitors" page
function competitors_settings_init() {
    register_setting('competitors_settings', 'competitors_custom_values');

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
    echo __('These values correspond to what rolls competitors check with check boxes.', 'wordpress');
}

function competitors_text_field_render() {
    $roll_name = get_option('competitors_custom_values');
    echo '<textarea cols="100" rows="30" name="competitors_custom_values">';
    echo esc_textarea($roll_name);
    echo '</textarea>';
}


// Plugin activation and deactivation hooks
function competitors_plugin_activate() {
    create_competitors_post_type();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'competitors_plugin_activate');

function competitors_plugin_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'competitors_plugin_deactivate');



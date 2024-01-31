<?php
// The function that displays the content of the admin page
function competitors_admin_page() {
    if (current_user_can('edit_posts')) {
    
    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1
    );
    $competitors_query = new WP_Query($args);

    if ($competitors_query->have_posts()) {
        echo '<h1>Competitors Data (for non-judges, sortable)</h1>';
        echo '<p>Click on headers to sort. This enables quick grouping and planning.</p>';
        echo '<table class="competitors-table" id="sortable-table">';
        echo '<thead><tr class="competitors-header">';
        echo '<th>Name</th><th>Club</th><th>Speaker Info</th><th>Sponsors</th><th>Class</th><th>Email</th><th>Phone</th><th>Consent</th>';
        echo '</tr></thead><tbody>';

        while ($competitors_query->have_posts()) {
            $competitors_query->the_post();
            // Get meta data
            $club = get_post_meta(get_the_ID(), 'club', true);
            $speaker_info = get_post_meta(get_the_ID(), 'speaker_info', true);
            $sponsors = get_post_meta(get_the_ID(), 'sponsors', true);
            $participation_class = get_post_meta(get_the_ID(), 'participation_class', true);
            $email = get_post_meta(get_the_ID(), 'email', true);
            $phone = get_post_meta(get_the_ID(), 'phone', true);
            $consent = get_post_meta(get_the_ID(), 'consent', true);
            echo '<tr>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html($club) . '</td>';
            echo '<td>' . esc_html($speaker_info) . '</td>'; 
            echo '<td>' . esc_html($sponsors) . '</td>';
            echo '<td>' . esc_html($participation_class) . '</td>';
            echo '<td>' . esc_html($email) . '</td>'; 
            echo '<td>' . esc_html($phone) . '</td>'; 
            echo '<td>' . esc_html($consent) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No competitors found.</p>';
    }
    wp_reset_postdata();


    ?>

    <?php

    }// current_user_can edit
} // End competitors_admin_page 



function save_sorted_competitors() {
    check_ajax_referer('competitors_nonce', 'nonce');
    $sortedIDs = isset($_POST['order']) ? $_POST['order'] : array();
    foreach ($sortedIDs as $order => $post_id) {
        wp_update_post(array(
            'ID' => $post_id,
            'menu_order' => $order
        ));
    }
    wp_die();
}
add_action('wp_ajax_save_sorted_competitors', 'save_sorted_competitors');




function judges_scoring_page() {
    if (!current_user_can('manage_options')) {
        echo 'Access denied to scoring dude, sorry.';
        return;
    }
    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1 // Fetch all
    );
    $competitors_query = new WP_Query($args);
    $roll_names = get_option('competitors_custom_values');

    echo '<h1>Competitors Judges Scoring Page</h1>';
    echo '<p>Click to have a look-see.</p>';
    echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
    wp_nonce_field('competitors_score_update_action', 'competitors_score_update_nonce');
    echo '<input type="hidden" name="action" value="competitors_score_update">';
    echo '<table class="competitors-table">';

    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        $competitor_id = get_the_ID();
        // Competitor metadata
        $email = get_post_meta(get_the_ID(), 'email', true);
        $phone = get_post_meta(get_the_ID(), 'phone', true);
        $club = get_post_meta(get_the_ID(), 'club', true);
        $speaker_info = get_post_meta(get_the_ID(), 'speaker_info', true);
        $sponsor_info = get_post_meta(get_the_ID(), 'sponsor_info', true);

        // Competitor header row
        echo '<tr class="competitors-header" data-competitor="' . get_the_ID() . '">';
        echo '<th colspan="7"><span class="dashicons dashicons-arrow-down-alt2"></span> ' . get_the_title() . ' (click to see rolls scoresheet)</th>';
        echo '</tr>';

        // Competitor information row
        echo '<tr class="competitors-info">';
        echo '<td>' . get_the_title() . '</td>';
        echo '<td>' . esc_html($club) . '</td>';
        echo '<td colspan="5"><b>Speaker info:</b> ' . esc_html($speaker_info) . ', <b>Sponsor:</b> ' . esc_html($sponsor_info) . '</td>';
        echo '</tr>';

        // Scoring rows
        foreach ($roll_names as $index => $roll_name) {
            $base_meta_key = $competitor_id . '_' . ($index + 1);
            $score_keys = array('left_score_', 'left_deduct_', 'right_score_', 'right_deduct_', 'total_');
            $scores = array();

            foreach ($score_keys as $key) {
                $scores[$key] = get_post_meta($competitor_id, $key . $base_meta_key, true);
            }

            echo '<tr class="competitors-scores" data-competitor="' . $competitor_id . '" data-row-index="' . ($index + 1) . '">';
            echo '<td colspan="2">' . esc_html($roll_name) . '</td>';
            echo '<td><input type="text" class="score-input" name="left_score_' . $base_meta_key . '" maxlength="2" value="' . esc_attr($scores['left_score_']) . '"></td>';
            echo '<td><input type="text" class="score-input" name="left_deduct_' . $base_meta_key . '" maxlength="2" value="' . esc_attr($scores['left_deduct_']) . '"></td>';
            echo '<td><input type="text" class="score-input" name="right_score_' . $base_meta_key . '" maxlength="2" value="' . esc_attr($scores['right_score_']) . '"></td>';
            echo '<td><input type="text" class="score-input" name="right_deduct_' . $base_meta_key . '" maxlength="2" value="' . esc_attr($scores['right_deduct_']) . '"></td>';
            echo '<td><input type="text" readonly name="total_' . $base_meta_key . '" maxlength="2" value="' . esc_attr($scores['total_']) . '"></td>';
            echo '</tr>';
        }
    }

    echo '</table>';
    echo '<input type="submit" value="Update Scores" class="button button-primary">';
    echo '</form>';
    wp_reset_postdata();

  
}




function handle_competitors_score_update() {
    if (!is_valid_score_update_request()) {
        wp_die('Security check failed. Are you sure you should be here?');
    }

    foreach ($_POST as $key => $value) {
        // Check if the key is a score key and update it in the database
        if (is_competitor_score_field($key)) {
            list($competitor_id, $roll_index) = get_competitor_info_from_key($key);
            update_post_meta($competitor_id, $key, sanitize_text_field($value));
        }
    }

    // Set a transient to show a success message and redirect
    set_transient('competitors_scores_updated', 'Scores updated successfully!', 10);
    wp_redirect(admin_url('admin.php?page=competitors-scoring'));
    exit;
}

function is_valid_score_update_request() {
    return isset($_POST['action'], $_POST['competitors_score_update_nonce']) &&
           $_POST['action'] == 'competitors_score_update' &&
           current_user_can('manage_options') &&
           wp_verify_nonce($_POST['competitors_score_update_nonce'], 'competitors_score_update_action');
}

function is_competitor_score_field($key) {
    return preg_match('/^(left_score_|left_deduct_|right_score_|right_deduct_|total_)\d+_\d+$/', $key);
}

function get_competitor_info_from_key($key) {
    $parts = explode('_', $key);
    $competitor_id = $parts[count($parts) - 2];
    $roll_index = $parts[count($parts) - 1];
    return [$competitor_id, $roll_index];
}

add_action('admin_post_competitors_score_update', 'handle_competitors_score_update');



function competitors_scoring_list_page() {
    //error_reporting(E_ALL); 
    //ini_set('display_errors', 1);
    if ($message = get_transient('competitors_scores_updated')) {
        echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html($message) . '</p></div>';
        delete_transient('competitors_scores_updated');
    }
    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1
    );
    $competitors_query = new WP_Query($args);
    echo '<div id="competitors-list">';
    echo '<h1>Competitors List</h1>';
    echo '<ul class="competitors-table">';
    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        echo '<li class="competitors-list-item" data-competitor-id="' . get_the_ID() . '">' . get_the_title() . '</li>';
    }
    wp_reset_postdata();
    wp_reset_query();
    echo '</ul>';
    echo '</div>';
    echo '<div id="competitors-details-container"></div>';
}


// For the public part we have a shortcode: [competitors_scoring]
function competitors_scoring_shortcode() {
    ob_start();
    competitors_scoring_list_page(); // The initial list
    return ob_get_clean();
}
add_shortcode('competitors_scoring', 'competitors_scoring_shortcode');

// AJAX handler for loading the competitor details
add_action('wp_ajax_load_competitor_details', 'load_competitor_details');
add_action('wp_ajax_nopriv_load_competitor_details', 'load_competitor_details'); // If you want it accessible to non-logged-in users


function load_competitor_details() {
    $competitor_id = isset($_POST['competitor_id']) ? intval($_POST['competitor_id']) : 0;
    error_log('Received competitor ID: ' . $competitor_id);
    if (!$competitor_id) {
        echo 'No Competitor ID provided';
        wp_die();
    }
    if ('competitors' !== get_post_type($competitor_id)) {
        echo 'Post with ID ' . $competitor_id . ' is not a Competitor post type';
        wp_die();
    }
    competitors_scoring_view_page($competitor_id);
    wp_die();
}


function competitors_scoring_view_page($competitor_id = 0) {
    $listing_page_url = admin_url('admin.php?page=competitors-scoring');
    $roll_names = get_option('competitors_custom_values');
    echo '<h1><a href="' . esc_url($listing_page_url) . '" class="competitors-back-link"><span class="dashicons dashicons-arrow-left-alt2"></span> Score for ' . esc_html(get_the_title($competitor_id)) . '</a></h1>';
    echo '<table class="competitors-table">';
    echo '<tr><th>Roll Name</th><th>Left Score</th><th>Left Deduct</th><th>Right Score</th><th>Right Deduct</th><th>Total</th></tr>';

    // Iterate through each roll name and fetch corresponding scores
    foreach ($roll_names as $index => $roll_name) {
        $base_meta_key = $competitor_id . '_' . ($index + 1);

        // Fetching meta values in a loop
        $scores = array();
        foreach (array('left_score_', 'left_deduct_', 'right_score_', 'right_deduct_', 'total_') as $key_prefix) {
            $scores[$key_prefix] = get_post_meta($competitor_id, $key_prefix . $base_meta_key, true);
        }
        echo '<tr>';
        echo '<td>' . esc_html($roll_name) . '</td>';
        echo '<td>' . esc_html($scores['left_score_']) . '</td>';
        echo '<td>' . esc_html($scores['left_deduct_']) . '</td>';
        echo '<td>' . esc_html($scores['right_score_']) . '</td>';
        echo '<td>' . esc_html($scores['right_deduct_']) . '</td>';
        echo '<td>' . esc_html($scores['total_']) . ' points</td>';
        echo '</tr>';
    }
    echo '</table>';
}



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
    echo __('<p>');
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
    // Sanitize each input value
    return array_map('sanitize_text_field', $input);
}



<?php

function competitors_form_html() {
    ob_start(); 
    // Form HTML for public part ?>

    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
        <input type="hidden" name="action" value="competitors_form_submit">

        <h2>Registration RollSM 2024</h2>
        <p>Remember to submit your registration at the <a href="#submit-button">bottom of the page</a>.</p>
        <fieldset>
            <legend>Personal info</legend>
            <label for="name">Name:</label>
            <input aria-label="Name" type="text" id="name" name="name"><br>

            <label for="email">Email:</label>
            <input aria-label="Email" type="text" id="email" name="email"><br>

            <label for="phone">Phone:</label>
            <input aria-label="Phone" type="text" id="phone" name="phone"><br>

            <label for="club">Club:</label>
            <input aria-label="Club" type="text" id="club" name="club"><br>

            <label for="license">License:</label>
            <input aria-label="License" type="checkbox" id="license" name="license"><br>

            <label for="sponsors">Sponsors:</label>
            <input aria-label="Sponsors" type="text" id="sponsors" name="sponsors"><br>

            <label for="speaker_info">Speaker Info:</label>
            <textarea aria-label="Speaker Info" id="speaker_info" name="speaker_info"></textarea><br>

            <label>Participation in Class:</label><br>
            <input aria-label="Participation Class - Open" type="radio" id="open" name="participation_class" value="open">
            <label for="open">Open</label><br>
            <input aria-label="Participation Class - Championship" type="radio" id="championship" name="participation_class" value="championship">
            <label for="championship">Championship</label><br>
            <input aria-label="Participation Class - Amateur" type="radio" id="amateur" name="participation_class" value="amateur">
            <label for="amateur">Amateur</label><br>

            <input aria-label="Consent" type="checkbox" id="consent" name="consent">
            <label for="consent">I agree for you to save my data, publish results, photos etc. I also agree to have fun and be nice.</label><br>
        </fieldset>

        <p class="pt-1">According to The Rules you get 30 min to perform your rolls. To save time and make for a better comp, 
            please let us know if there are rolls you will not try to perform. 
            You can always change your mind on the water, we just need a hint for time planning!</p>
       
        <fieldset>
            <legend>Performing Rolls</legend>
            <table>
                <tr>
                    <th>
                        <input type="checkbox" id="check_all" checked />
                        <label for="check_all">Check/uncheck All</label>
                    </th>
                    <th>Name of roll or maneuver. Uncheck the rolls you don't want to perform. You can change your mind during the event.</th>
                </tr>
                <?php 
                $roll_names = get_option('competitors_custom_values');
                $roll_names_array = array_map('trim', $roll_names);
                // Filter out empty values
                $roll_names_array = array_filter($roll_names_array);
                // Add checkboxes
                foreach ($roll_names_array as $i => $roll_name) {
                    echo '<tr>';
                    echo '<td><input type="checkbox" class="roll-checkbox" checked id="roll_' . ($i + 1) . '" name="performing_rolls[]"></td>';
                    echo '<td>' . esc_html($roll_name) . '</td>';
                    echo '</tr>';
                } 
                ?>
            </table>
        </fieldset>

        <a name="submit-button"></a>
        <input type="submit" value="Submit"><?php
        wp_nonce_field('competitors_form_submission', 'competitors_nonce');
        ?>
    </form>

    <?php
    return ob_get_clean(); 
}
add_shortcode('competitors_form_public', 'competitors_form_html');



function sanitize_phone_number($phone) {
    // Allowing country codes and number formatting characters
    $cleaned = preg_replace('/[^\+\d\s\(\)-\.]/', '', $phone);

    // Remove non-digits to count the digits only
    $digitsOnly = preg_replace('/[^\d]/', '', $cleaned);

    // Check if numbers exceed whatever 
    if (strlen($digitsOnly) > 15) {
        return 'Error: is this number really correct Stevie?';
    }
    return $cleaned;
}




function handle_competitors_form_submission() {
    error_log('Form submission initiated.');
    
    // Verify nonce and mandatory fields
    if (isset($_POST['competitors_nonce'], $_POST['name'], $_POST['email']) && 
        wp_verify_nonce($_POST['competitors_nonce'], 'competitors_form_submission') && 
        $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Sanitize and Validate input
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            error_log('Invalid email: ' . $email);
            return;
        }

        // Define sanitize_phone_number function outside for reusability and clarity
        $phone = sanitize_phone_number($_POST['phone']);
        $club = sanitize_text_field($_POST['club']);
        $sponsors = sanitize_text_field($_POST['sponsors']);
        $speaker_info = sanitize_textarea_field($_POST['speaker_info']);
        $participation_class = sanitize_text_field($_POST['participation_class']);
        $license = isset($_POST['license']) ? 'yes' : 'no';
        $consent = isset($_POST['consent']) ? 'yes' : 'no';
        $performing_rolls = isset($_POST['performing_rolls']) ? array_map('sanitize_text_field', $_POST['performing_rolls']) : [];

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
                'grand_total' => 0,
                'meta_value_num' => 0,
                'selected_rolls' => $performing_rolls
            ),
        );

        // Insert the post into the database and capture the new post ID
        $competitor_id = wp_insert_post($competitor_data);
        if ($competitor_id == 0) {
            error_log('Error in creating post.');
            return;
        } else {
            error_log('Post created with ID: ' . $competitor_id);
            set_transient('competitors_form_submitted', 'Thanks for saving, this will be fun!', 10);
        }

        // Handle the selected checkboxes (rolls)
        if (!empty($performing_rolls)) {
            update_post_meta($competitor_id, 'selected_rolls', $performing_rolls);
        } else {
            delete_post_meta($competitor_id, 'selected_rolls');
        }

        // Display the message if our transient is set
        if (get_transient('competitors_form_submitted')) {
            echo '<div id="message" class="updated notice is-dismissible"><p>' . get_transient('competitors_form_submitted') . '</p></div>';
            delete_transient('competitors_form_submitted');
        }

        // Redirect after successful submission
        error_log('Redirecting to thank-you page.');
        wp_redirect(home_url('/thank-you'));
        exit;

    } else {
        error_log('Sorry, form submission failed dude. Nonce verification failed or required fields are missing.');
    }
}

add_action('admin_post_competitors_form_submit', 'handle_competitors_form_submission');
add_action('admin_post_nopriv_competitors_form_submit', 'handle_competitors_form_submission');






// For the public part we have a shortcode: [competitors_scoring]
function competitors_scoring_shortcode() {
    ob_start();
    competitors_scoring_list_page(); // The initial list
    return ob_get_clean();
}
add_shortcode('competitors_scoring', 'competitors_scoring_shortcode');


// Names and scores on the public side
function competitors_scoring_list_page() {
    error_reporting(E_ALL); 
    ini_set('display_errors', 1);
    if ($message = get_transient('competitors_scores_updated')) {
        echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html($message) . '</p></div>';
        delete_transient('competitors_scores_updated');
    }
    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1,
        'meta_key' => 'grand_total',
        'orderby' => 'meta_value_num',
        'order' => 'DESC'
    );
    $competitors_query = new WP_Query($args);

    echo '<div id="competitors-list">';
    echo '<ul class="competitors-table">';
    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        echo '<li class="competitors-list-item" data-competitor-id="' . get_the_ID() . '">' . get_the_title() . '</li>';
    }
    wp_reset_postdata();
    wp_reset_query();
    echo '</ul>';
    echo '</div>';
    echo '<div id="competitors-details-container" class="fade-inout"></div>';
}


function load_competitor_details() {
    $competitor_id = isset($_POST['competitor_id']) ? intval($_POST['competitor_id']) : 0;
    //error_log('Received competitor ID: ' . $competitor_id);
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
// AJAX handler for loading the competitor details
add_action('wp_ajax_load_competitor_details', 'load_competitor_details');
add_action('wp_ajax_nopriv_load_competitor_details', 'load_competitor_details'); // If you want it accessible to non-logged-in users



// Public view of per competitor scoring list
function competitors_scoring_view_page($competitor_id = 0) {
    $listing_page_url = admin_url('admin.php?page=competitors-view');
    $roll_names = get_option('competitors_custom_values');
    $performing_rolls_selected = get_post_meta($competitor_id, 'performing_rolls', true);
    echo $performing_rolls_selected;

    // Ensure array of whatever
    if (!is_array($performing_rolls_selected)) {
        $performing_rolls_selected = [];
    }
    // Define score keys
    $score_keys = ['left_score_', 'left_deduct_', 'right_score_', 'right_deduct_', 'total_'];

    echo '<small>Score for</small>';
    echo '<h2><a href="' . esc_url($listing_page_url) . '" class="competitors-back-link"><i class="dashicons dashicons-arrow-left-alt2 arrow-back"></i> ' . esc_html(get_the_title($competitor_id)) . '</a></h2>';
    echo '<table class="competitors-table">';
    echo '<tr><th>Roll Name</th><th>Left Score</th><th>Left Deduct</th><th>Right Score</th><th>Right Deduct</th><th>Total</th></tr>';

    $grand_total = 0;

    foreach ($roll_names as $index => $roll_name) {
        $base_meta_key = $competitor_id . '_' . ($index + 1);

        // Fetching meta values
        $scores = [];
        foreach ($score_keys as $key_prefix) {
            $scores[$key_prefix] = get_post_meta($competitor_id, $key_prefix . $base_meta_key, true);
        }
       
       // print_r($performing_rolls_selected, true);
       


        $is_selected = in_array($roll_name, $performing_rolls_selected);
        $row_class = $is_selected ? 'competitors-scores' : 'competitors-scores not-sure';

        $total_points = '';
        if ($scores['total_'] > 0) {
            $total_points = esc_html($scores['total_']) . ' p';
            $grand_total += (int)$scores['total_'];
        }

        echo '<tr class="' . esc_attr($row_class) . '">';
        echo '<td>' . esc_html($roll_name) . '</td>';
        foreach ($score_keys as $key) {
            echo '<td>' . esc_html($scores[$key]) . '</td>';
        }
        echo '</tr>';
    }

    // Add the grand total row at the end
    echo '<tr><td colspan="5"><b>Total score</b></td><td><b>' . $grand_total . ' p</b></td></tr>';
    echo '</table>';
}

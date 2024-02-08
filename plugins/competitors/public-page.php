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
            <label for="open">Open (International participants)</label><br>
            <input aria-label="Participation Class - Championship" type="radio" id="championship" name="participation_class" value="championship">
            <label for="championship">Championship (Swedish club member and comp. <a href="https://rollsm.se/tavlingslicens-for-dig-utan-klubbtillhorighet/">license holder</a>)</label><br>
            <input aria-label="Participation Class - Amateur" type="radio" id="amateur" name="participation_class" value="amateur">
            <label for="amateur">Amateur (No license needed)</label><br>
            <input aria-label="Consent" type="checkbox" id="consent" name="consent">
            <label for="consent">I agree for you to save my data, publish results, photos etc. I also agree to have fun and be nice.</label><br>
        </fieldset>

        <p class="pt-1">According to The Rules you get 30 min to perform your rolls. To save time and make for a better comp, 
            please let us know if there are rolls you will not try to perform, ie. uncheck some boxes. 
            You can change your mind on the water, we just need a hint for time planning!</p>
       
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
                // Assuming get_roll_names_and_max_scores() is already defined and returns an associative array
                $rolls = get_roll_names_and_max_scores();

                // Add checkboxes for each roll
                foreach ($rolls as $index => $roll) {
                    echo '<tr>';
                    // Adjust the name attribute to include the index explicitly
                    echo '<td><input type="checkbox" class="roll-checkbox" checked id="roll_' . ($index + 1) . '" name="selected_rolls[' . $index . ']"></td>';
                    // Display the roll name with the max score if available, otherwise display 'N/A'
                    echo '<td>' . esc_html($roll['name']) . (isset($roll['max_score']) ? esc_html($roll['max_score']) : '') . '</td>';
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
        return 'Error: is this number really correct?';
    }
    return $cleaned;
}



function handle_competitors_form_submission() {
    error_log('Form submission initiated.');

    if (isset($_POST['competitors_nonce'], $_POST['name'], $_POST['email']) && 
        wp_verify_nonce($_POST['competitors_nonce'], 'competitors_form_submission') && 
        $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            error_log('Invalid email: ' . $email);
            return;
        }

        // Sanitize other inputs
        $phone = sanitize_text_field($_POST['phone']);
        $club = sanitize_text_field($_POST['club']);
        $sponsors = sanitize_text_field($_POST['sponsors']);
        $speaker_info = sanitize_textarea_field($_POST['speaker_info']);
        $participation_class = sanitize_text_field($_POST['participation_class']);
        $license = isset($_POST['license']) ? 'yes' : 'no';
        $consent = isset($_POST['consent']) ? 'yes' : 'no';

        // Sanitization for selected rolls - ensuring array structure is maintained
        $selected_rolls = isset($_POST['selected_rolls']) ? $_POST['selected_rolls'] : [];
        $selected_rolls_indexes = array_map('intval', array_keys($selected_rolls));

        // Prepare data for insertion, initializing competitor_scores as an empty array for future updates
        $competitor_data = array(
            'post_title'    => wp_strip_all_tags($name),
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
                'competitor_scores' => array(), // Initialize competitor_scores for future updates
                'selected_rolls' => $selected_rolls_indexes,
            ),
        );

        $competitor_id = wp_insert_post($competitor_data);
        if ($competitor_id == 0) {
            error_log('Error in creating post.');
            return;
        } else {
            error_log('Post created with ID: ' . $competitor_id);
            set_transient('competitors_form_submitted', 'Thanks for saving, this will be fun!', 10);
        }

        wp_redirect(home_url('/thank-you'));
        exit;

    } else {
        error_log('Form submission failed. Nonce verification failed or required fields are missing.');
    }
}

add_action('admin_post_competitors_form_submit', 'handle_competitors_form_submission');
add_action('admin_post_nopriv_competitors_form_submit', 'handle_competitors_form_submission');




// For the public part we have a shortcode to show this in the page: [competitors_scoring]
function competitors_scoring_shortcode() {
    ob_start();
    competitors_scoring_list_page(); // The initial list
    return ob_get_clean();
}
add_shortcode('competitors_scoring', 'competitors_scoring_shortcode');




// Names list on the public side. Its clickable and opens load_competitor_details with ajax, which works.
function competitors_scoring_list_page() {
    if ($message = get_transient('competitors_scores_updated')) {
        echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html($message) . '</p></div>';
        delete_transient('competitors_scores_updated');
    }

    $args = [
        'post_type' => 'competitors',
        'posts_per_page' => -1,
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
    ];

    $competitors_query = new WP_Query($args);

    echo '<div id="competitors-list"><i id="spinner"></i>';
    echo '<h2>List of competitors</h2>';
    echo '<ul class="competitors-table">';
    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        echo '<li class="competitors-list-item" data-competitor-id="' . get_the_ID() . '">' . get_the_title() . '</li>';
    }
    echo '</ul>';
    echo '</div>';
    echo '<div id="competitors-details-container"></div>';

}



function load_competitor_details() {
    check_ajax_referer('competitors_nonce', 'security');

    $competitor_id = isset($_POST['competitor_id']) ? intval($_POST['competitor_id']) : 0;
    if (!$competitor_id || 'competitors' !== get_post_type($competitor_id)) {
        wp_send_json_error(['message' => 'Invalid Competitor ID or not a Competitor post type']);
        wp_die();
    }

    competitors_scoring_view_page($competitor_id);
    wp_die(); // this should be here to terminate the AJAX call properly
}

// Register AJAX actions for logged-in and non-logged-in users
add_action('wp_ajax_load_competitor_details', 'load_competitor_details');
add_action('wp_ajax_nopriv_load_competitor_details', 'load_competitor_details');




function competitors_scoring_view_page($competitor_id = 0) {
    $rolls = get_roll_names_and_max_scores();

    $competitor_scores = get_post_meta($competitor_id, 'competitor_scores', true);
    $selected_rolls_indexes = (array) get_post_meta($competitor_id, 'selected_rolls', true);
    $noScoresYet = empty($competitor_scores);
    $scoresText = $noScoresYet ? " - Newly registered" : "";
    echo '<h3><a href="#" id="close-details" class="competitors-back-link"><i class="dashicons dashicons-arrow-right-alt2 arrow-back"></i>' . esc_html(get_the_title($competitor_id)) . $scoresText . '</a></h3>';

    echo '<table class="competitors-table"><tr><th>Roll Name</th><th>Left Score</th><th>Left Deduct</th><th>Right Score</th><th>Right Deduct</th><th>Total</th></tr>';

    $grand_total = 0;

    foreach ($rolls as $index => $roll) {
        $isSelected = in_array($index, $selected_rolls_indexes, true);
        $selectedClass = $isSelected ? 'selected-roll' : 'non-selected-roll';

        $scores = $competitor_scores[$index] ?? [];
        $left_score = $scores['left_score'] ?? 0;
        $left_deduct = $scores['left_deduct'] ?? 0;
        $right_score = $scores['right_score'] ?? 0;
        $right_deduct = $scores['right_deduct'] ?? 0;

        // Calculate total ensuring deduct does not exceed score and total is not negative
        $total_left = max($left_score - $left_deduct, 0);
        $total_right = max($right_score - $right_deduct, 0);
        $total = $total_left + $total_right;

        // Add to grand total
        $grand_total += $total;

        // Display scores, replacing zeros with an empty string
        echo sprintf('<tr class="%s"><td>%s (%s)</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td></tr>',
            esc_attr($selectedClass),
            esc_html($roll['name']),
            esc_html($roll['max_score']),
            $left_score ? $left_score : '',
            $left_deduct ? $left_deduct : '',
            $right_score ? $right_score : '',
            $right_deduct ? $right_deduct : '',
            $total
        );
    }

    echo "<tr><td colspan='5'><b>Grand Total</b></td><td><b>{$grand_total}</b></td></tr></table>";
}

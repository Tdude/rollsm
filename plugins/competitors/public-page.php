<?php
/*
* First, include text-strings.php, which localizes them if your theme has that. Then add strings as neccessary in that file.
' Use like this: {$text_strings['messages']['no_competitors']}'
*/
$text_strings = include_once 'assets/text-strings.php';


function render_competitors_date_field_public() {
    $dates = get_option('available_competition_dates', []);
    if (!is_array($dates)) {
        $dates = [];
    }
    // Use output buffering to capture HTML
    ob_start();

    $dropdown_content = <<<HTMLOUT
    <div class="mb-3">
    <label for="competition_date">Select your competition date</label>
    <select id="competition_date" name="competition_date">
    HTMLOUT;
    // Append options dynamically
    foreach ($dates as $date) {
        $dropdown_content .= '<option value="' . esc_attr($date) . '">' . esc_html($date) . '</option>';
    }
    // Close the dropdown cleanly
    $dropdown_content .= <<<HTMLOUT
    </select>
    </div>
    HTMLOUT;

    echo $dropdown_content;
    return ob_get_clean();
}



function competitors_form_html() {
    ob_start(); 
    ?>
    <form id="competitors-registration-form" action="<?php echo admin_url('admin-post.php'); ?>" method="post">
        <input type="hidden" name="action" value="competitors_form_submit">
        <h2 id="registration">Registration RollSM 2024</h2>
        <p>Remember to submit your registration at the <a href="#submitbutton-anchor">bottom of the page</a>.</p>
 
        <fieldset>
            <legend>Personal Info</legend>
            <label for="name">Name<span class="text-danger">*</span></label>
            <input aria-label="Name" type="text" id="name" name="name"><br>
            <label for="email">Email<span class="text-danger">*</span></label>
            <input aria-label="Email" type="text" id="email" name="email"><br>
            <label for="phone">Phone<span class="text-danger">*</span></label>
            <input aria-label="Phone" type="text" id="phone" name="phone"><br>
            <label for="club">Club</label>
            <input aria-label="Club" type="text" id="club" name="club"><br>
            <label for="sponsors">Your Sponsors</label>
            <input aria-label="Sponsors" type="text" id="sponsors"><br>
            <label for="speaker_info">Speaker Yap (Info about you)</label>
            <textarea aria-label="Speaker Info" id="speaker_info" name="speaker_info"></textarea><br>

            <?php echo render_competitors_date_field_public(); ?>

            <div id="participation-class-container">
                <label>Participation in Class<span class="text-danger">*</span></label><br>
                <input aria-label="Participation Class - Open" type="radio" id="open" name="participation_class" value="open">
                <label for="open">Open (International participants)</label><br>
                <input aria-label="Participation Class - Championship" type="radio" id="championship" name="participation_class" value="championship">
                <label for="championship">Championship (club member and comp. <a target="_blank" href="https://kanot.com/forening/administrativt-stod/licens-och-forsakring">license holder</a>)</label><br>
                <input aria-label="Participation Class - Amateur" type="radio" id="amateur" name="participation_class" value="amateur">
                <label for="amateur">Amateur (No license needed)</label><br>
            </div>

            <div class="extra-visible" id="license-container">
                <input aria-label="License agreement" type="checkbox" id="license-check" name="license-check">
                <label for="license-check">I have a competition license or will get one for this comp! (Read more about <a target="_blank" href="https://kanot.com/forening/administrativt-stod/licens-och-forsakring">licensing rules here</a>)</label>
            </div>

            <div class="extra-visible" id="consent-container">
                <input aria-label="Consent" type="checkbox" id="consent" name="consent" value="yes" required>
                <label for="consent">I agree<span class="text-danger">* </span> for you to save my data, publish results, photos, etc. I also agree to have fun and play nice.</label>
            </div>
        </fieldset>

        <p class="ptb-1">According to <a target="_blank" href="https://kanot.com/grenar/havskajak/tavling/gronlandsroll">The Rules</a>, you get 30 min to perform your rolls. However, to save time and make for a better comp, please let us know if there are rolls you will not try to perform. You can change your mind on the water, but we need a hint for time planning!</p>

        <fieldset>
            <legend>Performing Rolls</legend>
            <table>
                <tr>
                    <th>
                        <input type="checkbox" id="check_all" title="Uncheck or check all boxes" checked />
                        <label for="check_all">All</label>
                    </th>
                    <th>Name of roll or maneuver. Uncheck the rolls you don't want to perform. You can change your mind during the event.</th>
                </tr>
                <?php
                $rolls = get_roll_names_and_max_scores();
                foreach ($rolls as $index => $roll) {
                    echo '<tr class="clickable-row">';
                    echo '<td><input type="checkbox" class="roll-checkbox" checked id="roll_' . ($index + 1) . '" name="selected_rolls[' . $index . ']"></td>';
                    echo '<td>' . esc_html($roll['name']) . ' ' . (isset($roll['max_score']) ? esc_html($roll['max_score']) : '') . '</td>';
                    echo '</tr>';
                } ?>
            </table>
        </fieldset>

        <div id="validation-message" class="hidden alert danger">
            <span class="closebtn">&times;</span>
            <strong><span class="mega-text">\(o_o)/</span></strong> <span class="message-content"></span>
        </div>

        <a name="submitbutton-anchor"></a>
        <input type="submit" value="Submit" id="submit-button" class="button button-success">
        <?php wp_nonce_field('competitors_nonce_action', 'competitors_nonce'); ?>
    </form>
    <?php

    return ob_get_clean(); 
}
add_shortcode('competitors_form_public', 'competitors_form_html');



// WP doesn't have this. Regex for dummies, like me :)
function sanitize_phone_number($phone) {
    // Removes all characters except digits, spaces, plus, parentheses, hyphen/minus, and dots.
    $cleaned = preg_replace('/[^\+\d\s\(\)-\.]/', '', $phone);
    // Removes all non-digit characters, leaving only numbers.
    $digits_only = preg_replace('/[^\d]/', '', $cleaned);
    // How many digits does your country allow?
    if (strlen($digits_only) > 15) {
        // To indicate an error
        return '';
    }
    return $cleaned;
}


function is_valid_name($name) {
    // Use the 'u' modifier for Unicode support, and \p{L} to match any letter. Spaces, hyphen/minus and apostrophe characters are allowed.
    return preg_match('/^[\p{L}\s\-\']+$/u', $name); // Now allows letters (including accented ones), spaces, hyphens, and apostrophes
}



/**
 * After calling wp_send_json_error or wp_send_json_success, no further output should be sent, 
 * and there's no need for an explicit return because these functions call wp_die()
*/
function handle_competitors_form_submission() {
    error_log('Form submission initiated.');

    // Check for nonce field existence.
    if (!isset($_POST['competitors_nonce'])) {
        error_log('Nonce field is missing.');
        wp_send_json_error(['message' => 'Error: Nonce field is missing.']);
    }

    // Check nonce validity.
    if (!wp_verify_nonce($_POST['competitors_nonce'], 'competitors_nonce_action')) {
        error_log('Nonce verification failed.');
        wp_send_json_error(['message' => 'Error: Nonce verification failed.']);
    }

    // Check for each required field separately.
    $required_fields = ['name', 'email', 'phone'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            error_log("Required field {$field} is missing.");
            echo "Required field {$field} is missing.";
            wp_send_json_error(['message' => "Error: Required field {$field} is missing."]);
        }
    }

    // Sanitize and validate inputs.
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_phone_number($_POST['phone']);
    $consent = isset($_POST['consent']) && $_POST['consent'] === 'yes' ? 'yes' : 'no';

    // Validate inputs.
    if ($phone === '') {
        wp_send_json_error(['message' => 'Error: Please check your phone number!']);
    }
    if (!is_valid_name($name)) {
        wp_send_json_error(['message' => 'Error: Please write your name!']);
    }
    if (!is_email($email)) {
        wp_send_json_error(['message' => 'Error: Please check the email address!']);
    }
    if ($consent !== 'yes') {
        wp_send_json_error(['message' => 'Error: You must agree to the terms to proceed.']);
    }

    // Additional sanitization for other fields.
    $club = sanitize_text_field($_POST['club']);
    $sponsors = sanitize_text_field($_POST['sponsors']);
    $speaker_info = sanitize_textarea_field($_POST['speaker_info']);
    $participation_class = sanitize_text_field($_POST['participation_class']);
    $license = isset($_POST['license']) ? 'yes' : 'no';
    $selected_rolls = isset($_POST['selected_rolls']) ? array_map('intval', array_keys($_POST['selected_rolls'])) : [];
    // Fetch the competition date, sanitize and validate
    $competition_date = isset($_POST['competition_date']) ? sanitize_text_field($_POST['competition_date']) : '';

    if ($competition_date === '') {
        wp_send_json_error(['message' => 'Error: A valid competition date is required.']);
    }

    // Prepare and insert post
    $competitor_data = [
        'post_title'    => wp_strip_all_tags($name),
        'post_status'   => 'publish',
        'post_type'     => 'competitors',
        'meta_input'    => [
            'email' => $email,
            'phone' => $phone,
            'club' => $club,
            'sponsors' => $sponsors,
            'speaker_info' => $speaker_info,
            'participation_class' => $participation_class,
            'license' => $license,
            'consent' => $consent,
            'competitor_scores' => [],
            'selected_rolls' => $selected_rolls,
            '_competitors_custom_order' => 0,
            'competition_date' => $competition_date, // Include selected competition date in metadata
        ],
    ];

    $competitor_id = wp_insert_post($competitor_data);
    if ($competitor_id == 0) {
        error_log('Error in creating post.');
        wp_send_json_error(['message' => 'Error in creating post.']);
    }

    error_log('Post created with ID: ' . $competitor_id);
    ob_clean(); // Clean (erase) the output buffer
    wp_send_json_success(['message' => 'Thanks for registering, this will be fun!']);
}
// Register AJAX actions for logged-in and non-logged-in users.
add_action('wp_ajax_competitors_form_submit', 'handle_competitors_form_submission');
add_action('wp_ajax_nopriv_competitors_form_submit', 'handle_competitors_form_submission');



// For the public part we have a shortcode to show this in the page: [competitors_scoring_public]
function competitors_scoring_shortcode() {
    ob_start();
    competitors_scoring_list_page();
    return ob_get_clean();
}
add_shortcode('competitors_scoring_public', 'competitors_scoring_shortcode');




// Available competition dates are created in admin under 'available_competition_dates'
function competitors_scoring_list_page() {
    if ($message = get_transient('competitors_scores_updated')) {
        echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html($message) . '</p></div>';
        delete_transient('competitors_scores_updated');
    }

    $dates = get_option('available_competition_dates', []);
    $list_of_dates = '<option value="">All Dates</option>';
    foreach ($dates as $date) {
        $list_of_dates .= '<option value="' . esc_attr($date) . '">' . esc_html($date) . '</option>';
    }

    echo <<<HTML
    <div id="competitors-list">
        <h2>List of Competitors</h2>
        <fieldset>
            <legend>Select a Competition Date</legend>
            <select id="date-select" name="date_select">
                {$list_of_dates}
            </select>
        </fieldset>
        <div id="spinner"></div>
        <ul class="competitors-table"></ul>
        <div id="competitors-details-container"></div>
    </div>
    HTML;
}



function load_competitors_list() {
    // Check if the nonce and the date_select are passed correctly
    if (!check_ajax_referer('competitors_nonce_action', 'security', false)) {
        wp_send_json_error(['message' => 'Nonce verification failed']);
        return;
    }

    $selected_date = isset($_POST['date_select']) ? sanitize_text_field($_POST['date_select']) : '';

    $args = ['post_type' => 'competitors', 'posts_per_page' => -1];
    if (!empty($selected_date)) {
        $args['meta_query'] = [
            ['key' => 'competition_date', 'value' => $selected_date, 'compare' => '=']
        ];
    }

    $competitors_query = new WP_Query($args);
    $competitors_data = [];

    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        $competitors_data[] = [
            'ID' => get_the_ID(),
            'title' => get_the_title(),
            'total' => get_post_meta(get_the_ID(), 'total_score', true),
            'club' => get_post_meta(get_the_ID(), 'club', true)
        ];
    }
    wp_reset_postdata();
    // Send the JSON response with HTML content built
    wp_send_json_success(['content' => build_competitors_list_html($competitors_data)]);
    
}
add_action('wp_ajax_load_competitors_list', 'load_competitors_list');
add_action('wp_ajax_nopriv_load_competitors_list', 'load_competitors_list');




function build_competitors_list_html($competitors_data) {
    usort($competitors_data, function($a, $b) { return $b['total'] <=> $a['total']; });
    $html = '';  // Start with an empty string
    foreach ($competitors_data as $competitor) {
        $html .= sprintf(
            '<li class="competitors-list-item" data-competitor-id="%d"><b>%s</b> - %s - %d points</li>',
            esc_attr($competitor['ID']),
            esc_html($competitor['title']),
            esc_html($competitor['club']),
            intval($competitor['total'])
        );
    }
    return $html;
}





function load_competitor_details() {
    check_ajax_referer('competitors_nonce_action', 'security');

    $competitor_id = isset($_POST['competitor_id']) ? intval($_POST['competitor_id']) : 0;

    if (!$competitor_id || 'competitors' !== get_post_type($competitor_id)) {
        wp_send_json_error(['message' => 'Invalid Competitor ID or not a Competitor post type']);
        return;  // Exit early to avoid further execution
    }

    competitors_scoring_view_page($competitor_id);
    wp_die(); // Ensure AJAX call termination
}
add_action('wp_ajax_load_competitor_details', 'load_competitor_details');
add_action('wp_ajax_nopriv_load_competitor_details', 'load_competitor_details');





function competitors_scoring_view_page($competitor_id = 0, $selected_date = null) {
    $rolls = get_roll_names_and_max_scores();

    $competitor_scores = get_post_meta($competitor_id, 'competitor_scores', true);
    $selected_rolls_indexes = (array) get_post_meta($competitor_id, 'selected_rolls', true);
    $no_scores_yet = empty($competitor_scores);
    $scores_text = $no_scores_yet ? " - Newly registered" : "";

    echo '<h3><a href="#" id="close-details" class="competitors-back-link"><i class="dashicons dashicons-arrow-right-alt2 arrow-back"></i>' . esc_html(get_the_title($competitor_id)) . $scores_text . '</a></h3>';

    echo '<table class="competitors-table"><tr><th>Roll Name</th><th>Left Score</th><th>Left Deduct</th><th>Right Score</th><th>Right Deduct</th><th>Total</th></tr>';

    $grand_total = 0;

    foreach ($rolls as $index => $roll) {
        $is_selected = in_array($index, $selected_rolls_indexes, true);
        $selected_class = $is_selected ? 'selected-roll' : 'non-selected-roll';

        $scores = $competitor_scores[$index] ?? [];
        $left_score = $scores['left_score'] ?? 0;
        $left_deduct = $scores['left_deduct'] ?? 0;
        $right_score = $scores['right_score'] ?? 0;
        $right_deduct = $scores['right_deduct'] ?? 0;

        $total_left = max($left_score - $left_deduct, 0);
        $total_right = max($right_score - $right_deduct, 0);
        $total = $total_left + $total_right;

        $grand_total += $total;

        echo sprintf('<tr class="%s"><td>%s (%s)</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td></tr>',
            esc_attr($selected_class),
            esc_html($roll['name']),
            esc_html($roll['max_score']),
            $left_score ? $left_score : '',
            $left_deduct ? $left_deduct : '',
            $right_score ? $right_score : '',
            $right_deduct ? $right_deduct : '',
            $total
        );
    }

    echo '<tr><td colspan="5"><b>Grand Total</b></td><td><b>' . $grand_total . '</b></td></tr></table>';
}

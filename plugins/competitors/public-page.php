<?php
/*
* First, include text-strings.php, which localizes them if your theme has that. Then add strings as neccessary in that file.
' Use like this: {$text_strings['messages']['no_competitors']}'
*/
$text_strings = include 'assets/text-strings.php';

function competitors_form_html() {
    ob_start(); 
    // Form HTML for public part ?>

    <form id="competitors-registration-form" action="<?php echo admin_url('admin-post.php'); ?>" method="post">
        <input type="hidden" name="action" value="competitors_form_submit">

        <h2>Registration RollSM 2024</h2>
        <p>Remember to submit your registration at the <a href="#submitbutton-anchor">bottom of the page</a>.</p>

        <fieldset>
            <legend>Personal info</legend>
            <label for="name">Name<span class="text-danger">*</span></label>
            <input aria-label="Name" type="text" id="name" name="name"><br>
            <label for="email">Email<span class="text-danger">*</span></label>
            <input aria-label="Email" type="text" id="email" name="email"><br>
            <label for="phone">Phone<span class="text-danger">*</span></label>
            <input aria-label="Phone" type="text" id="phone" name="phone"><br>
            <label for="club">Club</label>
            <input aria-label="Club" type="text" id="club" name="club"><br>
            <label for="sponsors">My Sponsors</label>
            <input aria-label="Sponsors" type="text" id="sponsors" name="sponsors"><br>
            <label for="speaker_info">Speaker Info</label>
            <textarea aria-label="Speaker Info" id="speaker_info" name="speaker_info"></textarea><br>
            <div id="participation-class-container" class="p-1">
                <label>Participation in Class<span class="text-danger">*</span></label><br>
                <input aria-label="Participation Class - Open" type="radio" id="open" class="i-b" name="participation_class" value="open">
                <label for="open" class="i-b">Open (International participants)</label><br>
                <input aria-label="Participation Class - Championship" type="radio" id="championship" class="i-b" name="participation_class" value="championship">
                <label for="championship" class="i-b">Championship (club member and comp. <a target="_blank" href="https://kanot.com/forening/administrativt-stod/licens-och-forsakring">license holder</a>)</label><br>
                <input aria-label="Participation Class - Amateur" type="radio" id="amateur" class="i-b" name="participation_class" value="amateur">
                <label for="amateur" class="i-b">Amateur (No license needed)</label><br>
            </div>
            <div class="extra-visible fade-inout hidden" id="license-container">
                <input aria-label="License agreement" type="checkbox" id="license-check" name="license-check">
                <label for="license-check">I have a competition license or will get one for this comp! (Read more about <a target="_blank" href="https://kanot.com/forening/administrativt-stod/licens-och-forsakring">licensing rules here</a>)</label>
            </div>
            <div class="extra-visible" id="consent-container">
                <input aria-label="Consent" type="checkbox" id="consent" name="consent" value="yes" required>
                <label for="consent">I agree<span class="text-danger">*</span> for you to save my data, publish results, photos etc. I also agree to have fun and play nice.</label>
            </div>
        </fieldset>

        <p class="ptb-1">According to <a target="_blank" href="https://kanot.com/grenar/havskajak/tavling/gronlandsroll">The Rules</a> you get 30 min to perform your rolls. However, to save time and make for a better comp, please let us know if there are rolls you will not try to perform, ie. uncheck some boxes. You can change your mind on the water, we just need a hint for time planning!</p>
       
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
                // Assuming get_roll_names_and_max_scores() is already defined and returns an associative array
                $rolls = get_roll_names_and_max_scores();

                // Add checkboxes for each roll
                foreach ($rolls as $index => $roll) {
                    echo '<tr class="clickable-row">';
                    // Adjust the name attribute to include the index explicitly
                    echo '<td><input type="checkbox" class="roll-checkbox" checked id="roll_' . ($index + 1) . '" name="selected_rolls[' . $index . ']"></td>';
                    // Display the roll name with the max score if available, otherwise display 'N/A'
                    echo '<td>' . esc_html($roll['name']) . ' ' . (isset($roll['max_score']) ? esc_html($roll['max_score']) : '') . '</td>';
                    echo '</tr>';
                } ?>
            </table>
        </fieldset>

        <div id="validation-message" class="hidden alert danger">
            <span class="closebtn">&times;</span>
            <strong><span class="mega-text">\(o_o)/</span></strong> <span class="message-content"></span> <!-- Used to insert messages -->
        </div>


        <a name="submitbutton-anchor"></a>
        <input type="submit" value="Submit" id="submit-button" class="button button-success"><?php
        wp_nonce_field('competitors_nonce_action', 'competitors_nonce');
        ?>
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
    // Initial log for debugging purposes.
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
        wp_send_json_error(['message' => 'Error from WP to JS: please check your phone number!']);
    }
    if (!is_valid_name($name)) {
        wp_send_json_error(['message' => 'Error from WP to JS: please write your name!']);
    }
    if (!is_email($email)) {
        wp_send_json_error(['message' => 'Error from WP to JS: please check the email address!']);
    }
    // Require consent checkbox to be checked
    if ($consent !== 'yes') {
        wp_send_json_error(['message' => 'Error from WP to JS: You must agree to the terms to proceed.']);
    }

    // Additional sanitization for other fields.
    $club = sanitize_text_field($_POST['club']);
    $sponsors = sanitize_text_field($_POST['sponsors']);
    $speaker_info = sanitize_textarea_field($_POST['speaker_info']);
    $participation_class = sanitize_text_field($_POST['participation_class']);
    $license = isset($_POST['license']) ? 'yes' : 'no';
    $selected_rolls = isset($_POST['selected_rolls']) ? array_map('intval', array_keys($_POST['selected_rolls'])) : [];

    // Prepare and insert post...
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
        ],
    ];

    $competitor_id = wp_insert_post($competitor_data);
    if ($competitor_id == 0) {
        error_log('Error in creating post.');
        wp_send_json_error(['message' => 'Error in creating post.']);
    }

    // Successful submission.
    error_log('Post created with ID: ' . $competitor_id);
    wp_send_json_success(['message' => 'Thanks for registering, this will be fun!']);
}

// Register AJAX actions for logged-in and non-logged-in users.
add_action('wp_ajax_competitors_form_submit', 'handle_competitors_form_submission');
add_action('wp_ajax_nopriv_competitors_form_submit', 'handle_competitors_form_submission');



// For the public part we have a shortcode to show this in the page: [competitors_scoring_public]
function competitors_scoring_shortcode() {
    ob_start();
    competitors_scoring_list_page(); // The initial list
    return ob_get_clean();
}
add_shortcode('competitors_scoring_public', 'competitors_scoring_shortcode');



// Names list on the public side. Its clickable and opens load_competitor_details.
function competitors_scoring_list_page() {
    // Display success message if available
    if ($message = get_transient('competitors_scores_updated')) {
        echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html($message) . '</p></div>';
        delete_transient('competitors_scores_updated');
    }

    // Query to fetch all competitors
    $args = [
        'post_type' => 'competitors',
        'posts_per_page' => -1,
    ];

    $competitors_query = new WP_Query($args);
    $competitors_data = [];

    if ($competitors_query->have_posts()) {
        while ($competitors_query->have_posts()) {
            $competitors_query->the_post();
            $competitor_id = get_the_ID();
            $competitor_club = get_post_meta($competitor_id, 'club', true);
            $competitor_scores = get_post_meta($competitor_id, 'competitor_scores', true) ?: [];
            $competitor_total_score = 0;

            $rolls = get_roll_names_and_max_scores(); // Assuming this returns the required structure

            foreach ($rolls as $index => $roll) {
                $roll_scores = $competitor_scores[$index] ?? [];
                $roll_total = max(0, ($roll_scores['left_score'] ?? 0) - ($roll_scores['left_deduct'] ?? 0) +
                                      ($roll_scores['right_score'] ?? 0) - ($roll_scores['right_deduct'] ?? 0));
                $competitor_total_score += $roll_total;
            }

            $competitors_data[] = [
                'ID' => $competitor_id,
                'total' => $competitor_total_score,
                'title' => get_the_title(),
                'club' => $competitor_club,
            ];
        }
        wp_reset_postdata(); // Reset global post data
    }

    // Sort competitors by total scores in descending order
    usort($competitors_data, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });

    // Begin rendering the list of competitors
    echo '<div id="competitors-list"><div id="spinner"></div><h2>List of competitors</h2><ul class="competitors-table">';

    foreach ($competitors_data as $competitor) {
        $club_info = !empty($competitor['club']) ? " - " . esc_html($competitor['club']) : "";
        echo '<li class="competitors-list-item" data-competitor-id="' . esc_attr($competitor['ID']) . '"><b>' . esc_html($competitor['title']) . '</b>' . $club_info . ' - ' . esc_html($competitor['total']) . ' points</li>';
    }

    echo '</ul><div id="competitors-details-container"></div></div>';
}



function load_competitor_details() {
    check_ajax_referer('competitors_nonce_action', 'security');

    $competitor_id = isset($_POST['competitor_id']) ? intval($_POST['competitor_id']) : 0;
    if (!$competitor_id || 'competitors' !== get_post_type($competitor_id)) {
        wp_send_json_error(['message' => 'Invalid Competitor ID or not a Competitor post type']);
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

        // Calculate total ensuring deduct does not exceed score and total is not negative
        $total_left = max($left_score - $left_deduct, 0);
        $total_right = max($right_score - $right_deduct, 0);
        $total = $total_left + $total_right;

        // Add to grand total
        $grand_total += $total;

        // Display scores, replacing zeros with an empty string
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

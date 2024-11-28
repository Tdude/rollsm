<?php
/* PUBLIC-PAGE WITH GENDER CHOICE
* First, include text-strings.php, which localizes them if your theme has that. Then add strings as neccessary in that file.
* Use like this: {$text_strings['messages']['no_competitors']}'
*/
$text_strings = include_once 'assets/text-strings.php';



function render_competitors_date_field_public() {
    // Retrieve the events; assume they are stored as an array of associative arrays
    $options = get_option('competitors_options', []);
    $events = isset($options['available_competition_dates']) ? $options['available_competition_dates'] : [];
    if (!is_array($events)) {
        $events = [];
    }
    ob_start();
    ?>
    <div class="mb-3">
        <label for="competition_date"><?php _e('Select your competition date', 'competitors'); ?> <span class="text-danger"> * </span></label>
        <select id="competition_date" name="competition_date">
            <option value=""><?php _e('Please select a date', 'competitors'); ?></option>
            <?php foreach ($events as $event) : ?>
                <?php
                if (isset($event['date']) && isset($event['name'])) {
                    $date = esc_html($event['date']);
                    $name = esc_html($event['name']);
                    $formatted_event = $date . ' - ' . $name;
                ?>
                    <option value="<?php echo esc_attr($date); ?>"><?php echo $formatted_event; ?></option>
                <?php } ?>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
    return ob_get_clean();
}



/**
 * The dynamic classes
 * Refactored according to the settings page
 */
function render_competitors_classes_field_public() {
    $options = get_option('competitors_options', []);
    $competitor_classes = isset($options['available_competition_classes']) ? $options['available_competition_classes'] : [
        ['name' => 'open', 'comment' => 'Open (International participants, 30 minutes max)'],
        ['name' => 'championship', 'comment' => 'Mästerskapsklass (30 minuter max)'],
        ['name' => 'amateur', 'comment' => 'Motionsklass (10 minuter max)']
    ];
    
    ob_start();
    ?>
    <div id="participation-class-container">
        <label>Participation in Class <span class="text-danger">*</span></label><br>
        <?php foreach ($competitor_classes as $class): ?>
            <input aria-label="Participation Class - <?php echo esc_attr($class['name']); ?>" type="radio" id="<?php echo esc_attr($class['name']); ?>" name="participation_class" value="<?php echo esc_attr($class['name']); ?>">
            <label for="<?php echo esc_attr($class['name']); ?>"><?php echo esc_html($class['comment']); ?></label><br>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}



function competitors_form_html() {
    ob_start(); 
    ?>
    <form id="competitors-registration-form" action="<?php echo admin_url('admin-post.php'); ?>" method="post">
        <input type="hidden" name="action" value="competitors_form_submit">
        <h2 id="registration">Registration RollSM 2024</h2>
        <p>Remember to submit your registration at the <a href="#submitbutton-anchor">bottom of the page</a>. Fields marked with an asterisk (<span class="text-danger"> * </span>) are mandatory.</p>
 
        <fieldset>
            <legend>Personal Info</legend>
            <label for="name">Name <span class="text-danger">*</span></label>
            <input aria-label="Name" type="text" id="name" name="name"><br>
            <label for="email">Email <span class="text-danger">*</span></label>
            <input aria-label="Email" type="text" id="email" name="email"><br>
            <label for="phone">Phone <span class="text-danger">*</span></label>
            <input aria-label="Phone" type="text" id="phone" name="phone"><br>
            <label for="club">Club</label>
            <input aria-label="Club" type="text" id="club" name="club"><br>
            
            <div class="extra-visible gender-container">
            <label>Gender <span class="text-danger">*</span></label><br>
            <input aria-label="Gender: Woman" type="radio" id="gender_woman" name="gender" value="woman" required>
            <label for="gender_woman">Woman</label>
            <input aria-label="Gender: Man" type="radio" id="gender_man" name="gender" value="man">
            <label for="gender_man">Man</label>
            </div>

            <label for="sponsors">Your Sponsors</label>
            <input aria-label="Sponsors" type="text" id="sponsors"><br>
            <label for="speaker_info">Support text (ICE phone number<span class="text-danger"> * </span>, info about you, food preferences/allergies etc.)</label>
            <textarea aria-label="Speaker Info" id="speaker_info" name="speaker_info"></textarea><br>

            <?php echo render_competitors_date_field_public(); ?>
            <?php echo render_competitors_classes_field_public(); ?>

            <div class="extra-visible" id="license-container">
                <input aria-label="License agreement" type="checkbox" id="license" name="license">
                <label for="license">I have a competition license or will get one for this comp! (Read more about <a target="_blank" href="https://kanot.com/forening/administrativt-stod/licens-och-forsakring"> licensing rules here</a>)</label>
            </div>
            <div class="extra-visible" id="dinner-container">
                <input aria-label="Join competition dinner" type="checkbox" id="dinner" name="dinner">
                <label for="dinner">Join competition dinner (200 SEK). Allergies? Write in the Support text above please!</label>
            </div>
            <div class="extra-visible" id="consent-container">
                <input aria-label="Consent" type="checkbox" id="consent" name="consent" value="yes" required>
                <label for="consent">I agree <span class="text-danger"> * </span> for you to save my data, publish results, photos, etc. I also agree to have fun and play nice.</label>
            </div>
        </fieldset>

        <p class="ptb-1">According to <a target="_blank" href="https://kanot.com/grenar/havskajak/tavling/gronlandsroll">The Rules</a>, you get 30 min to perform your rolls in the Championship and Open classes. However, to save time and make for a better comp, please let us know if there are rolls you will not try to perform. You can change your mind on the water, but we need a hint for time planning!</p>

        <div id="performing-rolls-container">
            <?php echo render_performing_rolls_fieldset('open'); // Default to 'open' class initially ?>
        </div>

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



function render_performing_rolls_fieldset($class = 'open') {
    ob_start();
    ?>
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
            $rolls = get_roll_names_and_max_scores($class); // Ensure this actually handles the class
            foreach ($rolls as $index => $roll) {
                $max_score = isset($roll['max_score']) ? $roll['max_score'] : '';
                $points_display = ($max_score == 0 || $max_score === '') ? 'N/A' : esc_html($max_score) . ' points';
                echo '<tr class="clickable-row">';
                echo '<td><input type="checkbox" class="roll-checkbox" checked id="roll_' . ($index + 1) . '" name="selected_rolls[' . $index . ']"></td>';
                echo '<td>' . esc_html($roll['name']) . ' (' . $points_display . ')</td>';
                echo '</tr>';
            } ?>
        </table>
    </fieldset>
    <?php
    return ob_get_clean();
}



function get_performing_rolls() {
    check_ajax_referer('competitors_nonce_action', 'nonce');

    if (isset($_POST['class_type'])) {
        $class_type = sanitize_text_field($_POST['class_type']);

        // Debug logging
        if (empty($class_type)) {
            error_log('Class type is empty.');
        } else {
            error_log('Class type received: ' . $class_type);
        }

        $html = render_performing_rolls_fieldset($class_type);

        wp_send_json_success(['html' => $html]);
    } else {
        wp_send_json_error('Class type not specified.');
    }
}

add_action('wp_ajax_get_performing_rolls', 'get_performing_rolls');
add_action('wp_ajax_nopriv_get_performing_rolls', 'get_performing_rolls');



// WP doesn't have this. Regex for dummies :)
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
function handle_competitor_form_submission() {
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
            wp_send_json_error(['message' => "Error: Required field {$field} is missing."]);
        }
    }

    // Sanitize and validate inputs.
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
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
    $club = isset($_POST['club']) ? sanitize_text_field($_POST['club']) : '';
    $gender = isset($_POST['gender']) ? sanitize_text_field($_POST['gender']) : '';
    $sponsors = isset($_POST['sponsors']) ? sanitize_text_field($_POST['sponsors']) : '';
    $speaker_info = isset($_POST['speaker_info']) ? sanitize_textarea_field($_POST['speaker_info']) : '';
    $participation_class = sanitize_text_field($_POST['participation_class']);
    $license = isset($_POST['license']) ? 'yes' : 'no';
    $dinner = isset($_POST['dinner']) ? 'yes' : 'no';
    $selected_rolls = isset($_POST['selected_rolls']) ? array_map('intval', array_keys($_POST['selected_rolls'])) : [];
    $competition_date = isset($_POST['competition_date']) ? sanitize_text_field($_POST['competition_date']) : '';

    if ($competition_date === '') {
        wp_send_json_error(['message' => 'Error: A valid competition date is required.']);
    }

    // Get the dynamic price list from the utility function
    $class_prices = get_competitor_price_list();

    // Calculate sum based on class choices and dinner
    $total_sum = 0;

    if (array_key_exists($participation_class, $class_prices)) {
        $total_sum += $class_prices[$participation_class];
    }

    if ($dinner === 'yes' && isset($class_prices['dinner'])) {
        $total_sum += $class_prices['dinner'];
    }

    error_log('Total sum calculated: ' . $total_sum);

    // Prepare and insert post
    $competitor_data = [
        'post_title'    => wp_strip_all_tags($name),
        'post_status'   => 'publish',
        'post_type'     => 'competitors',
        'meta_input'    => [
            'email' => $email,
            'phone' => $phone,
            'club' => $club,
            'gender' => $gender,
            'sponsors' => $sponsors,
            'speaker_info' => $speaker_info,
            'participation_class' => $participation_class,
            'license' => $license,
            'dinner' => $dinner,
            'consent' => $consent,
            'competitor_scores' => [],
            'selected_rolls' => $selected_rolls,
            '_competitors_custom_order' => 0,
            'competition_date' => $competition_date,
            'fee' => $total_sum,
        ],
    ];

    $competitor_id = wp_insert_post($competitor_data);
    if ($competitor_id == 0) {
        error_log('Error in creating post.');
        wp_send_json_error(['message' => 'Error in creating post.']);
    }

    error_log('Post created with ID: ' . $competitor_id);

    // Invalidate relevant caches
    delete_transient('competitors_list_all');
    delete_transient('competitors_list_' . md5(json_encode([$competition_date, $participation_class])));
    // Optionally, delete any other related cached entries
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_competitors_list_%'");

    // Send email to all WP administrators
    send_admin_email($name, $email, $phone, $club, $gender, $sponsors, $participation_class, $competition_date, $total_sum, $dinner);

    // Send confirmation email to the form submitter
    send_confirmation_email($name, $email, $competition_date, $total_sum, $dinner);

    ob_clean(); // Clean (erase) the output buffer
    wp_send_json_success([
        'message' => 'Thanks for registering, this will be fun!',
        'total_sum' => $total_sum,
        'redirect_url' => add_query_arg(['fee' => $total_sum], get_home_url() . '/competitors-thank-you'),
    ]);
}

function send_admin_email($name, $email, $phone, $club, $gender, $sponsors, $participation_class, $competition_date, $total_sum, $dinner) {
    //$test_email = 'tibbecodes@gmail.com';

    // Get all admin users emails
    $admins = get_users(['role' => 'administrator']);
    $admin_emails = wp_list_pluck($admins, 'user_email');

    // Override the admin emails for testing purposes
    //if (defined('WP_ENV') && WP_ENV === 'development') {
    //    $admin_emails = [$test_email];
    //}

    $subject = 'Ny registrering';
    $message = "En till rollare har anmält sig, tjohoo!\n\n" .
               "Namn: $name\n" .
               "Email: $email\n" .
               "Tel: $phone\n" .
               "Klubb: $club\n" .
               "Kön: $gender\n" .
               "Sponsorer: $sponsors\n" .
               "Klass: $participation_class\n" .
               "Datum för tävling: $competition_date\n" .
               "Avgift: $total_sum:-\n";

    if ($dinner === 'yes') {
        $message .= "Middag: Ja (200 SEK)\n";
    }

    if (!wp_mail($admin_emails, $subject, $message)) {
        error_log('Failed to send email to administrators.');
    } else {
        error_log('Email sent to administrators.');
    }
}


function send_confirmation_email($name, $email, $competition_date, $total_sum, $dinner) {
    $subject = 'Registration Confirmation for RollSM';
    $message = "Hej $name!\n\n" .
                "Tack för din anmälan till RollSM $competition_date. Vi ser fram emot att få träffas!\n\n" .
                "Din erlagda avgift som du har eller ska Swisha borde vara $total_sum.\n\n";

    if ($dinner === 'yes') {
        $message .= "Middag: Ja (200 SEK)\n";
    }

    $message .= "Hälsningar,\nRollSM organisationen\n\n\n\n".
                "==============\n\n\n\n".
                "Hi $name,\n\n".
                "Thank you for registering for the RollSM competition on $competition_date. We look forward to seeing you there!\n\n".
                "Your total fee is SEK $total_sum.\n\n";

    if ($dinner === 'yes') {
        $message .= "Dinner: Yes (200 SEK)\n";
    }

    $message .= "Best regards,\nThe Team\n".
                "\nPS\nThis is an automated response. There is no mailbox on the other side so please don\'t return this email.";

    if (!wp_mail($email, $subject, $message)) {
        error_log('Failed to send confirmation email to registered competitor.');
    } else {
        error_log('Confirmation email sent to registered competitor.');
    }
}

// Register AJAX actions for logged-in and non-logged-in users.
add_action('wp_ajax_competitors_form_submit', 'handle_competitor_form_submission');
add_action('wp_ajax_nopriv_competitors_form_submit', 'handle_competitor_form_submission');

function competitors_thank_you_content($content) {
    if (is_page('competitors-thank-you') && isset($_GET['fee'])) {
        $fee = sanitize_text_field($_GET['fee']);
        $fee_message = '<p class="mega-text">Your total fee is SEK ' . esc_html($fee) . '.</p>';
        return $content . $fee_message;
    }
    return $content;
}
add_filter('the_content', 'competitors_thank_you_content');


function get_competitor_price_list() {
    // Prices for each class
    $competition_prices = [
        'amateur' => 300,
        'championship' => 500,
        'open' => 500,
    ];

    // Dinner is optional
    $optional_prices = [
        'dinner' => 200,
    ];
    $prices = array_merge($competition_prices, $optional_prices);
    return apply_filters('competitor_price_list', $prices);
}





// For the public part we have a shortcode to show this in the page: [competitors_scoring_public]
function competitors_scoring_shortcode() {
    ob_start();
    competitors_scoring_list_page();
    return ob_get_clean();
}
add_shortcode('competitors_scoring_public', 'competitors_scoring_shortcode');




function competitors_scoring_list_page() {
    // Handle the transient message display and deletion
    if ($message = get_transient('competitors_scores_updated')) {
        echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html($message) . '</p></div>';
        delete_transient('competitors_scores_updated');
    }

    // Retrieve the options value for competition dates and classes
    $options = get_option('competitors_options', []);

    // Extract competition dates from options
    $dates = isset($options['available_competition_dates']) ? $options['available_competition_dates'] : [];
    if (!is_array($dates)) {
        $dates = [];
    }

    // Generate the options for the dates dropdown
    $list_of_dates = '<option value="">' . __('Alla Datum', 'competitors') . '</option>';
    foreach ($dates as $date) {
        if (isset($date['date']) && isset($date['name'])) {
            $date_value = esc_attr($date['date']);
            $date_text = esc_html($date['date'] . ' - ' . $date['name']);
            $list_of_dates .= '<option value="' . $date_value . '">' . $date_text . '</option>';
        }
    }

    // Extract competition classes from options
    $classes = isset($options['available_competition_classes']) ? $options['available_competition_classes'] : [];
    if (!is_array($classes)) {
        $classes = [];
    }

    // Generate the options for the competitor classes dropdown
    $list_of_classes = '<option value="">' . __('Alla klasser', 'competitors') . '</option>';
    foreach ($classes as $class) {
        if (isset($class['name']) && isset($class['comment'])) {
            $class_value = esc_attr($class['name']);
            $class_text = esc_html($class['comment']);
            $list_of_classes .= '<option value="' . $class_value . '">' . $class_text . '</option>';
        }
    }

    // Generate the options for the gender dropdown
    $list_of_genders = '<option value="">' . __('Kön', 'competitors') . '</option>';
    $list_of_genders .= '<option value="woman">Kvinna</option>';
    $list_of_genders .= '<option value="man">Man</option>';

    // Output the HTML
    echo <<<HTML
    <div id="competitors-list">
        <h2>Deltagarlista</h2>
        <fieldset>
            <legend>Välj filter</legend>
            <select id="date-select" name="date_select">
                {$list_of_dates}
            </select>
            <select id="class-select" name="class_select">
                {$list_of_classes}
            </select>
            <select id="gender-select" name="gender_select">
                {$list_of_genders}
            </select>
        </fieldset>
        <div id="spinner"></div>
        <ul class="competitors-table"></ul>
        <div id="competitors-details-container"></div>
    </div>
    HTML;
}


function load_competitors_list() {
    if (!check_ajax_referer('competitors_nonce_action', 'security', false)) {
        wp_send_json_error(['message' => 'Nonce verification failed']);
        return;
    }

    $selected_date = isset($_POST['date_select']) ? sanitize_text_field($_POST['date_select']) : '';
    $selected_class = isset($_POST['class_select']) ? sanitize_text_field($_POST['class_select']) : '';
    $selected_gender = isset($_POST['gender_select']) ? sanitize_text_field($_POST['gender_select']) : '';

    // Generate a unique cache key
    $cache_key = 'competitors_list_' . md5($selected_date . $selected_class . $selected_gender);

    // Check if cached data exists
    $cached_data = get_transient($cache_key);
    if ($cached_data !== false) {
        wp_send_json_success(['content' => $cached_data]);
        return;
    }

    // Build the query args
    $args = ['post_type' => 'competitors', 'posts_per_page' => -1];
    $meta_query = [];

    if (!empty($selected_date)) {
        $meta_query[] = [
            'key' => 'competition_date',
            'value' => $selected_date,
            'compare' => '='
        ];
    }
    if (!empty($selected_class)) {
        $meta_query[] = [
            'key' => 'participation_class',
            'value' => $selected_class,
            'compare' => '='
        ];
    }
    if (!empty($selected_gender)) {
        $meta_query[] = [
            'key' => 'gender',
            'value' => $selected_gender,
            'compare' => '='
        ];
    }

    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }

    $competitors_query = new WP_Query($args);
    $competitors_data = [];

    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        $competitors_data[] = [
            'ID' => get_the_ID(),
            'title' => get_the_title(),
            'total' => get_post_meta(get_the_ID(), 'total_score', true),
            'club' => get_post_meta(get_the_ID(), 'club', true),
            'participation_class' => get_post_meta(get_the_ID(), 'participation_class', true),
            'gender' => get_post_meta(get_the_ID(), 'gender', true),
        ];
    }
    wp_reset_postdata();

    $html = build_competitors_list_html($competitors_data);

    // Cache the result 12 h
    set_transient($cache_key, $html, 12 * HOUR_IN_SECONDS);

    wp_send_json_success(['content' => $html]);
}

add_action('wp_ajax_load_competitors_list', 'load_competitors_list');
add_action('wp_ajax_nopriv_load_competitors_list', 'load_competitors_list');


function build_competitors_list_html($competitors_data) {
    usort($competitors_data, function($a, $b) { return $b['total'] <=> $a['total']; });
    error_log(print_r($competitors_data, true)); 

    $html = '';
    foreach ($competitors_data as $competitor) {
        error_log('Competitor Total Raw: ' . $competitor['total']);  
        $total_points = intval($competitor['total']);
        error_log('Competitor Total Converted: ' . $total_points);  

        // Create club display string only if club exists
        $club_display = !empty($competitor['club']) ? ' - ' . esc_html($competitor['club']) : '';
    
        $html .= sprintf(
            '<li class="competitors-list-item" data-competitor-id="%d" data-participation-class="%s" data-gender="%s"><b>%s</b>%s - %d points</li>',
            esc_attr($competitor['ID']),
            esc_attr($competitor['participation_class']), 
            esc_attr($competitor['gender']),
            esc_html($competitor['title']),
            $club_display,
            intval($competitor['total'])
        );
    }
    
    return $html;
}




function load_competitor_details() {
    check_ajax_referer('competitors_nonce_action', 'security');

    $competitor_id = isset($_POST['competitor_id']) ? intval($_POST['competitor_id']) : 0;
    $participation_class = isset($_POST['participation_class']) ? sanitize_text_field($_POST['participation_class']) : '';

    if (!$competitor_id || 'competitors' !== get_post_type($competitor_id)) {
        wp_send_json_error(['message' => 'Invalid Competitor ID or not a Competitor post type']);
        return;
    }

    // Generate a cache key
    $cache_key = 'competitor_details_' . $competitor_id . '_' . md5($participation_class);

    // Check if cached data exists
    $cached_html = get_transient($cache_key);
    if ($cached_html !== false) {
        echo $cached_html;
        wp_die();
    }

    ob_start(); // Start output buffering
    competitors_scoring_view_page($competitor_id, null, $participation_class);
    $html = ob_get_clean();

    // Cache the HTML output for 12h
    set_transient($cache_key, $html, 12 * HOUR_IN_SECONDS);

    echo $html;
    wp_die();
}

add_action('wp_ajax_load_competitor_details', 'load_competitor_details');
add_action('wp_ajax_nopriv_load_competitor_details', 'load_competitor_details');



function competitors_scoring_view_page($competitor_id = 0, $selected_date = null, $participation_class = null) {
    // Valid participation class?
    if (empty($participation_class)) {
        $participation_class = get_post_meta($competitor_id, 'participation_class', true);
        if (empty($participation_class)) {
            $participation_class = 'open';
        }
    }
    // Pass the participation_class to get_roll_names_and_max_scores
    $rolls = get_roll_names_and_max_scores($participation_class);
    // Determine the last numeric roll
    $last_numeric_index = -1;
    foreach (array_reverse($rolls, true) as $index => $roll) {
        if ($roll['is_numeric'] === 'Yes') {
            $last_numeric_index = $index;
            break;
        }
    }

    // Retrieve competitor scores and selected rolls
    $competitor_scores = get_post_meta($competitor_id, 'competitor_scores', true);
    // Unserialize the competitor scores if they are serialized
    if (is_serialized($competitor_scores)) {
        $competitor_scores = unserialize($competitor_scores);
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Competitor Scores Data (after potential unserialization): " . print_r($competitor_scores, true));
    }

    $selected_rolls_indexes = (array) get_post_meta($competitor_id, 'selected_rolls', true);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Selected Rolls Indexes: " . print_r($selected_rolls_indexes, true));
    }

    $no_scores_yet = empty($competitor_scores);
    $scores_text = $no_scores_yet ? __(" - Newly registered", 'competitors') : "";

    // Output the competitor's title and status
    echo '<h3><a href="#" id="close-details" class="competitors-back-link"><i class="dashicons dashicons-arrow-right-alt2 arrow-back"></i>' . esc_html(get_the_title($competitor_id)) . esc_html($scores_text) . '</a></h3>';

    // Output the table header
    echo '<table class="competitors-table">';
    echo '<tr>';
    echo '<th>' . __('Roll Name', 'competitors') . '</th>';
    echo '<th>' . __('Left/Saamik', 'competitors') . '</th>';
    echo '<th>' . __('Right/Talerpik', 'competitors') . '</th>';
    echo '<th>' . __('Score', 'competitors') . '</th>';
    echo '</tr>';

    $grand_total = 0;

    foreach ($rolls as $index => $roll) {
        $is_selected = in_array($index, $selected_rolls_indexes, true);
        $selected_css_class = $is_selected ? 'selected-roll' : 'non-selected-roll';
        $scores = isset($competitor_scores[$index]) ? $competitor_scores[$index] : [];
        $is_numeric = ($roll['is_numeric'] === 'Yes');
        $is_last_numeric = ($index === $last_numeric_index);
        // Retrieve and format scores
        $left_score = isset($scores['left_score']) ? intval($scores['left_score']) : 0;
        $left_group = isset($scores['left_group']) ? intval($scores['left_group']) : 0;
        $right_score = isset($scores['right_score']) ? intval($scores['right_score']) : 0;
        $right_group = isset($scores['right_group']) ? intval($scores['right_group']) : 0;
        // Determine the values to display for left and right scores and groups
        $left_display_group = $left_group !== 0 ? esc_html($left_group) : '';
        $right_display_group = $right_group !== 0 ? esc_html($right_group) : '';
        // Calculate total points
        $total_left = $left_score + $left_group;
        $total_right = $right_score + $right_group;
        $total = $total_left + $total_right;
        $grand_total += $total;
        // Determine whether to display max_score
        $max_score_display = $is_numeric ? '' : " (" . esc_html($roll['max_score']) . ")";
        // Start the table row
        echo '<tr class="' . esc_attr($selected_css_class) . '">';
        // Roll name column
        echo '<td>' . esc_html($roll['name']) . $max_score_display . '</td>';
        
        if ($is_last_numeric) {
            // Last numeric field: only show total score
            echo '<td colspan="2"></td>';
            echo '<td>' . esc_html($total) . '</td>';
        } elseif ($is_numeric) {
            // Numeric field: show left and right scores
            echo '<td>' . esc_html($left_score) . '</td>';
            echo '<td>' . esc_html($right_score) . '</td>';
            echo '<td>' . esc_html($total) . '</td>';
        } else {
            echo '<td>' . esc_html($left_display_group) . '</td>';
            echo '<td>' . esc_html($right_display_group) . '</td>';
            echo '<td>' . esc_html($total) . '</td>';
        }

        echo '</tr>';
    }

    // Output the grand total row
    echo '<tr><td colspan="3"><b>' . __('Score Grand Total', 'competitors') . '</b></td><td><b>' . esc_html($grand_total) . '</b></td></tr>';
    echo '</table>';
}

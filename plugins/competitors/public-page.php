<?php

function competitors_form_shortcode() {
    ob_start(); 
    // Form HTML for public part ?>
<style>
.lbl-checkbox,
input[type='checkbox'] {
  padding: 1em;
  display: inline-block;
}


</style>
    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
        <input type="hidden" name="action" value="competitors_form_submit">

        <h2>Anmälan  <?php if (current_user_can('manage_options')): ?>och poängresultat<?php endif; ?> RollSM 2024</h2>
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

            <label>Participation in Class:</label>
            <input aria-label="Participation Class - Open" type="radio" id="open" name="participation_class" value="open">
            <label for="open">Open</label>
            <input aria-label="Participation Class - Championship" type="radio" id="championship" name="participation_class" value="championship">
            <label for="championship">Championship</label>
            <input aria-label="Participation Class - Amateur" type="radio" id="amateur" name="participation_class" value="amateur">
            <label for="amateur">Amateur</label><br>

            <input aria-label="Consent" type="checkbox" id="consent" name="consent">
            <label for="consent">Jag godkänner, har läst och förstått mm.</label><br>
        <fieldset>
            <legend>Performing Rolls</legend>
            <table>
                <tr>
                    <th><label for="check-all" class="lbl-checkbox">Markera alla</label>
                    <input type="checkbox" id="roll_check" name="check-all" onchange="checkAll(this)">
                    </th>
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
                    <td><input type="checkbox" id="roll_<?php echo $i + 1; ?>" name="performing_rolls[]"></td>
                    <td><?php echo esc_html($roll_names_array[$i]); ?></td>
                    
                    <?php if (current_user_can('manage_options')): ?>
                        <!-- These fields are visible only to admins @todo: maybe break out whole table to separate admin page? -->
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
add_shortcode('competitors_form_public', 'competitors_form_shortcode');


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

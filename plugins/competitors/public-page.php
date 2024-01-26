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
                    <th>Uncheck
                        
                    <!--
                        <label for="check-all" class="lbl-checkbox"></label>
                        <input type="checkbox" id="roll_check" name="check-all">
                    -->
                    </th>
                    <th>Name of roll or manouver. Uncheck the rolls you do not want to perform.</th>
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
                    <td><input type="checkbox" checked id="roll_<?php echo $i + 1; ?>" name="performing_rolls[]"></td>
                    <td><?php echo esc_html($roll_names_array[$i]); ?></td>
                </tr>
            <?php endfor; ?>

            </table>
        </fieldset>
        <a name="submit-button"></a>
        <input type="submit" value="Submit"><?php
        wp_nonce_field('competitors_form_submission', 'competitors_nonce');
        ?>
    </form><?php

    return ob_get_clean(); 
    ?>
    <script>
        document.addEventListener("DOMContentLoaded", function(){
            // Check all boxes with event listener for checkbox "<input type="checkbox" onchange="checkAll(this)" name="checks-all" />"
            function checkAll(e) {
                var checkboxes = document.getElementsByTagName('input');
                if (e.checked) {
                    for (var i = 0; i < checkboxes.length; i++) {
                        if (checkboxes[i].type == 'checkbox') {
                            checkboxes[i].checked = true;
                        }
                    }
                } else {
                    for (var i = 0; i < checkboxes.length; i++) {
                        console.log(i)
                        if (checkboxes[i].type == 'checkbox') {
                            checkboxes[i].checked = false;
                        }
                    }
                }
            }
        });
    </script>
    <?php
}
add_shortcode('competitors_form_public', 'competitors_form_html');


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




function competitors_score_html() {

    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1 // Fetch all
    );
    $competitors_query = new WP_Query($args);

    $roll_names = get_option('competitors_custom_values');
    $roll_names_array = explode("\n", $roll_names);
    $roll_names_array = array_filter(array_map('trim', $roll_names_array));

    echo '<h1>Competitors Scoring</h1>';
    echo '<table class="competitors-table">';

    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();

        // Competitor metadata
        $email = get_post_meta(get_the_ID(), 'email', true);
        $phone = get_post_meta(get_the_ID(), 'phone', true);
        $club = get_post_meta(get_the_ID(), 'club', true);
        $speaker_info = get_post_meta(get_the_ID(), 'speaker_info', true);
        $sponsor_info = get_post_meta(get_the_ID(), 'sponsor_info', true);

        // Competitor header row
        echo '<tr class="competitors-header" data-competitor="' . get_the_ID() . '">';
        echo '<th colspan="7">' . get_the_title() . '</th>';
        echo '</tr>';

        // Competitor information row
        echo '<tr>';
        echo '<td>' . get_the_title() . '</td>';
        echo '<td>' . esc_html($club) . '</td>';
        echo '<td colspan="5"><b>Speaker info:</b> ' . esc_html($speaker_info) . ', <b>Sponsor:</b> ' . esc_html($sponsor_info) . '</td>';
        echo '</tr>';

        // Scoring rows
        foreach ($roll_names_array as $index => $roll_name) {
            $left_score = get_post_meta(get_the_ID(), 'left_score_' . ($index + 1), true);
            $left_deduct = get_post_meta(get_the_ID(), 'left_deduct_' . ($index + 1), true);
            $right_score = get_post_meta(get_the_ID(), 'right_score_' . ($index + 1), true);
            $right_deduct = get_post_meta(get_the_ID(), 'right_deduct_' . ($index + 1), true);
            $total = get_post_meta(get_the_ID(), 'total_' . ($index + 1), true);

            echo '<tr class="competitors-scores" data-competitor="' . get_the_ID() . '" data-row-index="' . ($index + 1) . '">';
            echo '<td colspan="2">' . esc_html($roll_name) . '</td>';
            echo '<td>' . esc_html($left_score) . '</td>';
            echo '<td>' . esc_html($left_deduct) . '</td>';
            echo '<td>' . esc_html($right_score) . '</td>';
            echo '<td>' . esc_html($right_deduct) . '</td>';
            echo '<td>' . esc_html($total) . '</td>';
            echo '</tr>';
        }
    }

    echo '</table>';
    wp_reset_postdata();
}


add_shortcode('competitors_score_public', 'competitors_score_html');
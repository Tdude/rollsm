<?php
/**
 * Plugin Name: Competitors Plugin
 * Description: A not-so-basic plugin to manage competitors. It does all the things you'd expect, and maybe a bit more.
 * Version: 0.1
 * Author: Tdude via CHatGPT
 */

// Let's kick things off by creating a custom post type for our beloved competitors
function create_competitors_post_type() {
    register_post_type('competitors', array(
        'labels' => array(
            'name' => __('Competitors'),
            'singular_name' => __('Competitor')
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
    ));
}
add_action('init', 'create_competitors_post_type');

// Now, let's add a shortcode for the form, because who doesn't love shortcodes?
function competitors_form_shortcode() {
    ob_start(); // Start output buffering, because we're fancy like that

    // Let's display the form (HTML goes here)
    ?>

    <form action="" method="post">
        <h2>Anmälan RollSM 2024</h2>
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
        <label for="consent">Jag godkänner</label><br>

        <fieldset>
            <legend>Performing Rolls</legend>
            <table>
            <th>
                <td>Rollmoment</td>
                <td>Poäng V</td>
                <td>Avdrag V</td>
                <td>Poäng H</td>
                <td>Avdrag H</td>
                <td>Summa</td>
            </th>
            <?php 
for ($i = 1; $i <= 35; $i++): ?>
    <tr>
        <td><input type="checkbox" id="roll_<?php echo $i; ?>" name="performing_rolls[]"></td>
        <!-- Display the manouver from the array above-->
        <td><?php echo isset($roll_name[$i]) ? $roll_name[$i] : 'N/A'; ?></td>
        
        <?php if (current_user_can('manage_options')): ?>
            <!-- These fields are visible only to admins -->
            <td><input type="text" name="left_<?php echo $i; ?>" maxlength="2"></td>
            <td><input type="text" name="left_deduct_<?php echo $i; ?>" maxlength="2"></td>
            <td><input type="text" name="right_<?php echo $i; ?>" maxlength="2"></td>
            <td><input type="text" name="right_deduct_<?php echo $i; ?>" maxlength="2"></td>
            <td><input type="text" name="total_<?php echo $i; ?>" maxlength="2"></td>
        <?php endif; ?>
    </tr>
<?php endfor; ?>





            </table>
        </fieldset>

        <input type="submit" value="Submit">
    </form>
    <?php

    return ob_get_clean(); // End buffering and return the form
}
add_shortcode('competitors_form', 'competitors_form_shortcode');

function sanitize_phone_number($phone) {
    // Strip out anything that's not a number, space, parentheses, or dash
    return preg_replace('/[^\d\s\(\)-]/', '', $phone);
}

// Handling the form submission
function handle_competitors_form_submission() {
    // Check if the form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['email'])) { // Add other fields as needed
        // Validate and Sanitize input
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_phone_number($_POST['phone']);
        $club = sanitize_text_field($_POST['club']);
        $license = sanitize_text_field($_POST['license']);
        $sponsors = sanitize_text_field($_POST['sponsors']);
        $speaker_info = sanitize_text_field($_POST['speaker_info']);
        $participation_class = sanitize_text_field($_POST['participation_class']);
        $consent = sanitize_text_field($_POST['consent']);

        if (isset($_POST['performing_rolls']) && is_array($_POST['performing_rolls'])) {
            foreach ($_POST['performing_rolls'] as $roll) {
                // Sanitize each value (assuming it's an integer ID or similar)
                $sanitized_roll = intval($roll);
        
                // Validate the sanitized value
                // For example, ensure it's within a certain range or set of values
                if ($sanitized_roll < 1 || $sanitized_roll > 35) {
                    echo 'ÄRROR. Det är något fel vid antal rollar.';
                } else {
                    // Valid value, do something with it
                    $performing_rolls = $_POST['performing_rolls'];
                }
            }
        }
        

        // Validate email
        if (!is_email($email)) {
            echo 'Invalido emejlo!';
            return;
        }

        // Prepare data for insertion
        $competitor_data = array(
            'post_title'    => wp_strip_all_tags($name),
            'post_content'  => '', // You can add more content here
            'post_status'   => 'publish',
            'post_type'     => 'competitors',
            // Add custom fields as post meta
            'meta_input'    => array(
                'email' => $email,
                // ... other meta fields ...
            ),
        );

        // Insert the post into the database
        wp_insert_post($competitor_data);

        // Redirect or display success message
        // wp_redirect('thank-you-page-url');
        // exit;
    }
}
add_action('init', 'handle_competitors_form_submission');


// Add a new admin menu page
function competitors_admin_menu() {
    add_menu_page(
        'Competitors Data', // Page title
        'Competitors', // Menu title
        'manage_options', // Capability
        'competitors-data', // Menu slug
        'competitors_admin_page' // Function to display the page
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
        echo '<h1>Competitors Data</h1>';
        echo '<table border="1" style="width: 100%;">';
        echo '<tr><th>Name</th><th>Email</th><th>Phone</th><th>Club</th><th>More Info</th></tr>';

        while ($competitors_query->have_posts()) {
            $competitors_query->the_post();

            // Get the meta data
            $email = get_post_meta(get_the_ID(), 'email', true);
            // Fetch other meta data similarly

            echo '<tr>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html($email) . '</td>';
            echo '<td>' . esc_html($phone) . '</td>';
            echo '<td>' . esc_html($club) . '</td>'; 
            // Display other fields in similar fashion
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No competitors found.</p>';
    }

    wp_reset_postdata(); // Reset the query
}

?>
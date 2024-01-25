<?php
// Menu functions are in competitors.php



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



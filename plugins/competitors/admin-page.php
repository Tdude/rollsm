<?php
// Init and menu functions are in competitors.php
// Add styling to the admin only
function competitors_admin_styles($hook) {
    wp_enqueue_style('competitors_admin_css', plugin_dir_url(__FILE__) . 'assets/admin.css');
}
add_action('admin_enqueue_scripts', 'competitors_admin_styles');


// The function that displays the content of the admin page
function competitors_admin_page() {
    // Fetch competitors from the database
    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1 // Fetch all posts
    );
    $competitors_query = new WP_Query($args);

    if ($competitors_query->have_posts()) {
        echo '<h1>Competitors gnarly Admin Page Data</h1>';
        echo '<table class="competitors-table">';
        echo '<tr><th>Name</th><th>Email</th><th>Phone</th><th>Club</th><th>Speaker Info</th><th>Sponsorer</th></tr>';

        while ($competitors_query->have_posts()) {
            $competitors_query->the_post();

            // Get the meta data
            $email = get_post_meta(get_the_ID(), 'email', true);
            $phone = get_post_meta(get_the_ID(), 'phone', true);
            $club = get_post_meta(get_the_ID(), 'club', true);
            $speaker_info = get_post_meta(get_the_ID(), 'speaker_info', true);
            $sponsors = get_post_meta(get_the_ID(), 'sponsors', true);
            // Fetch other meta data similarly

            echo '<tr>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html($email) . '</td>'; 
            echo '<td>' . esc_html($phone) . '</td>';
            echo '<td>' . esc_html($club) . '</td>'; 
            echo '<td>' . esc_html($speaker_info) . '</td>'; 
            echo '<td>' . esc_html($sponsors) . '</td>'; 
            // Display other fields in similar fashion
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No competitors found.</p>';
    }

    wp_reset_postdata(); // Reset the query
}




function judges_scoring_page() {
    if (!current_user_can('manage_options')) {
        echo 'Access denied dude, sorry.';
        return;
    }

    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1 // Fetch all posts
    );
    $competitors_query = new WP_Query($args);

    $roll_names = get_option('competitors_custom_values');
    $roll_names_array = explode("\n", $roll_names);
    $roll_names_array = array_filter(array_map('trim', $roll_names_array));

    echo '<h2>Competitors Scoring Admin Page</h2>';
    echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
    echo '<input type="hidden" name="action" value="competitors_score_update">';
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
        echo '<tr class="competitor-header" data-competitor="' . get_the_ID() . '">';
        echo '<td colspan="8">' . get_the_title() . ' (click to expand)</td>';
        echo '</tr>';

        // Competitor information row
        echo '<tr>';
        echo '<td>' . get_the_title() . '</td>';
        echo '<td>' . esc_html($email) . '</td>';
        echo '<td>' . esc_html($phone) . '</td>';
        echo '<td>' . esc_html($club) . '</td>';
        echo '<td colspan="5">Speaker: ' . esc_html($speaker_info) . ', Sponsor: ' . esc_html($sponsor_info) . '</td>';
        echo '</tr>';


        // Scoring rows
        foreach ($roll_names_array as $index => $roll_name) {
            echo '<tr class="competitor-scores" data-competitor="' . get_the_ID() . '" data-row-index="' . ($index + 1) . '">';

            echo '<td colspan="4">' . esc_html($roll_name) . '</td>';
            echo '<td><input type="text" name="left_score_' . get_the_ID() . '_' . ($index + 1) . '" maxlength="2"></td>';
            echo '<td><input type="text" name="left_deduct_' . get_the_ID() . '_' . ($index + 1) . '" maxlength="2"></td>';
            echo '<td><input type="text" name="right_score_' . get_the_ID() . '_' . ($index + 1) . '" maxlength="2"></td>';
            echo '<td><input type="text" name="right_deduct_' . get_the_ID() . '_' . ($index + 1) . '" maxlength="2"></td>';
            echo '<td><input type="text" readonly name="total_' . get_the_ID() . '_' . ($index + 1) . '" maxlength="4"></td>';
            echo '</tr>';
        }
    }

    echo '</table>';
    echo '<input type="submit" value="Update Scores" class="button button-primary">';
    echo '</form>';
    wp_reset_postdata();




    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle visibility for each competitor's scores
            const headers = document.querySelectorAll('.competitor-header');
            headers.forEach(header => {
                header.addEventListener('click', function() {
                    const competitorId = this.dataset.competitor;
                    const scores = document.querySelectorAll('.competitor-scores[data-competitor="' + competitorId + '"]');
                    scores.forEach(row => {
                        row.style.display = row.style.display === 'none' || row.style.display === '' ? 'table-row' : 'none';
                    });
                });
            });

            // Update scoring totals
            const scoreInputs = document.querySelectorAll('.competitor-scores .score-input');
            scoreInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const parentRow = this.closest('tr');
                    const competitorId = parentRow.dataset.competitor;
                    const rowIndex = parentRow.dataset.rowIndex;
                    const leftScore = parseInt(document.querySelector(`[name='left_score_${competitorId}_${rowIndex}']`).value) || 0;
                    const leftDeduct = parseInt(document.querySelector(`[name='left_deduct_${competitorId}_${rowIndex}']`).value) || 0;
                    const rightScore = parseInt(document.querySelector(`[name='right_score_${competitorId}_${rowIndex}']`).value) || 0;
                    const rightDeduct = parseInt(document.querySelector(`[name='right_deduct_${competitorId}_${rowIndex}']`).value) || 0;

                    let total = (leftScore - leftDeduct) + (rightScore - rightDeduct);
                    total = total < 0 ? 0 : total;

                    document.querySelector(`[name='total_${competitorId}_${rowIndex}']`).value = total;
                });
            });
        });
    </script>

    <?php
    
}








// Display the settings page content
function competitors_settings_page() {
    echo '<div class="wrap"><h1>Competitors rolls and possibly other settings</h1>';
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



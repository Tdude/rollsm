<?php
function enqueue_dashicons_for_competitors() {
    wp_enqueue_style('dashicons');
    wp_enqueue_style('competitors_admin_css', plugin_dir_url(__FILE__) . 'assets/admin.css');
    wp_enqueue_script('competitors_admin_page', plugin_dir_url(__FILE__) . 'assets/script.js', array('jquery'), null, true);

    // Localize script for AJAX
    wp_localize_script('competitors_admin_page', 'competitorsData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('competitors_nonce')
    ));
}

function competitors_admin_styles($hook) {
    // Uncomment and modify the condition as needed for specific admin pages
    /*
    if ($hook !== 'competitors_admin_page' && $hook !== 'judges_scoring_page') {
        return;
    }
    */
    enqueue_dashicons_for_competitors();
}

add_action('admin_enqueue_scripts', 'competitors_admin_styles');




// The function that displays the content of the admin page
function competitors_admin_page() {
    if (current_user_can('edit_posts')) {
    
    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1
    );
    $competitors_query = new WP_Query($args);

    if ($competitors_query->have_posts()) {
        echo '<h1>Competitors Data (for non-judges, sortable)</h1>';
        echo '<p>Click on headers to sort. This enables quick grouping and planning.</p>';
        echo '<table class="competitors-table" id="sortable-table">';
        echo '<thead><tr class="competitors-header">';
        echo '<th>Name</th><th>Club</th><th>Speaker Info</th><th>Sponsors</th><th>Class</th><th>Email</th><th>Phone</th><th>Consent</th>';
        echo '</tr></thead><tbody>';

        while ($competitors_query->have_posts()) {
            $competitors_query->the_post();
            // Get meta data
            $club = get_post_meta(get_the_ID(), 'club', true);
            $speaker_info = get_post_meta(get_the_ID(), 'speaker_info', true);
            $sponsors = get_post_meta(get_the_ID(), 'sponsors', true);
            $participation_class = get_post_meta(get_the_ID(), 'participation_class', true);
            $email = get_post_meta(get_the_ID(), 'email', true);
            $phone = get_post_meta(get_the_ID(), 'phone', true);
            $consent = get_post_meta(get_the_ID(), 'consent', true);
            echo '<tr>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html($club) . '</td>';
            echo '<td>' . esc_html($speaker_info) . '</td>'; 
            echo '<td>' . esc_html($sponsors) . '</td>';
            echo '<td>' . esc_html($participation_class) . '</td>';
            echo '<td>' . esc_html($email) . '</td>'; 
            echo '<td>' . esc_html($phone) . '</td>'; 
            echo '<td>' . esc_html($consent) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No competitors found.</p>';
    }
    wp_reset_postdata();


    ?>
    <script>
        jQuery(document).ready(function($) {
        $('#sortable-table th').on('click', function() {
            var table = $(this).parents('table').eq(0);
            var rows = table.find('tr:gt(0)').toArray().sort(comparer($(this).index()));
            this.asc = !this.asc;
            if (!this.asc) { rows = rows.reverse(); }
            for (var i = 0; i < rows.length; i++) { table.append(rows[i]); }
        });
    
        function comparer(index) {
            return function(a, b) {
                var valA = getCellValue(a, index), valB = getCellValue(b, index);
                return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.localeCompare(valB);
            };
        }
    
        function getCellValue(row, index) { 
            return $(row).children('td').eq(index).text(); 
        }
    });
    
    </script>
    <?php

    }// current_user_can edit
} // End competitors_admin_page 



function save_sorted_competitors() {
    check_ajax_referer('competitors_nonce', 'nonce');
    $sortedIDs = isset($_POST['order']) ? $_POST['order'] : array();
    foreach ($sortedIDs as $order => $post_id) {
        wp_update_post(array(
            'ID' => $post_id,
            'menu_order' => $order
        ));
    }
    wp_die();
}
add_action('wp_ajax_save_sorted_competitors', 'save_sorted_competitors');




function judges_scoring_page() {
    if (!current_user_can('manage_options')) {
        echo 'Access denied dude, sorry.';
        return;
    }
    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1 // Fetch all
    );
    $competitors_query = new WP_Query($args);
    $roll_names = get_option('competitors_custom_values');

    echo '<h1>Competitors Judges Scoring</h1>';
    echo '<p>Click to have a look-see.</p>';
    echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
    wp_nonce_field('competitors_score_update_action', 'competitors_score_update_nonce');
    echo '<input type="hidden" name="action" value="competitors_score_update">';
    echo '<table class="competitors-table">';

    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        $competitor_id = get_the_ID();
        // Competitor metadata
        $email = get_post_meta(get_the_ID(), 'email', true);
        $phone = get_post_meta(get_the_ID(), 'phone', true);
        $club = get_post_meta(get_the_ID(), 'club', true);
        $speaker_info = get_post_meta(get_the_ID(), 'speaker_info', true);
        $sponsor_info = get_post_meta(get_the_ID(), 'sponsor_info', true);

        // Competitor header row
        echo '<tr class="competitors-header" data-competitor="' . get_the_ID() . '">';
        echo '<th colspan="7"><span class="dashicons dashicons-arrow-down-alt2"></span> ' . get_the_title() . ' (click to see rolls scoresheet)</th>';
        echo '</tr>';

        // Competitor information row
        echo '<tr class="competitors-info">';
        echo '<td>' . get_the_title() . '</td>';
        echo '<td>' . esc_html($club) . '</td>';
        echo '<td colspan="5"><b>Speaker info:</b> ' . esc_html($speaker_info) . ', <b>Sponsor:</b> ' . esc_html($sponsor_info) . '</td>';
        echo '</tr>';

        // Scoring rows
        foreach ($roll_names as $index => $roll_name) {
            $base_meta_key = $competitor_id . '_' . ($index + 1);
            $score_keys = array('left_score_', 'left_deduct_', 'right_score_', 'right_deduct_', 'total_');
            $scores = array();

            foreach ($score_keys as $key) {
                $scores[$key] = get_post_meta($competitor_id, $key . $base_meta_key, true);
            }

            echo '<tr class="competitors-scores" data-competitor="' . $competitor_id . '" data-row-index="' . ($index + 1) . '">';
            echo '<td colspan="2">' . esc_html($roll_name) . '</td>';
            echo '<td><input type="text" class="score-input" name="left_score_' . $base_meta_key . '" maxlength="2" value="' . esc_attr($scores['left_score_']) . '"></td>';
            echo '<td><input type="text" class="score-input" name="left_deduct_' . $base_meta_key . '" maxlength="2" value="' . esc_attr($scores['left_deduct_']) . '"></td>';
            echo '<td><input type="text" class="score-input" name="right_score_' . $base_meta_key . '" maxlength="2" value="' . esc_attr($scores['right_score_']) . '"></td>';
            echo '<td><input type="text" class="score-input" name="right_deduct_' . $base_meta_key . '" maxlength="2" value="' . esc_attr($scores['right_deduct_']) . '"></td>';
            echo '<td><input type="text" readonly name="total_' . $base_meta_key . '" maxlength="4" value="' . esc_attr($scores['total_']) . '"></td>';
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
        const headers = document.querySelectorAll('.competitors-header');
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const competitorId = this.dataset.competitor;
                const scores = document.querySelectorAll('.competitors-scores[data-competitor="' + competitorId + '"]');
                scores.forEach(row => row.style.display = row.style.display === 'none' || row.style.display === '' ? 'table-row' : 'none');
                // The competitor info row is immediately following the header row in the DOM
                const infoRow = this.nextElementSibling;
                infoRow.style.display = infoRow.style.display === 'none' || infoRow.style.display === '' ? 'table-row' : 'none';
                // Toggle arrow icon
                const icon = this.querySelector('.dashicons');
                if (icon.classList.contains('dashicons-arrow-down-alt2')) {
                    icon.classList.remove('dashicons-arrow-down-alt2');
                    icon.classList.add('dashicons-arrow-up-alt2');
                } else {
                    icon.classList.remove('dashicons-arrow-up-alt2');
                    icon.classList.add('dashicons-arrow-down-alt2');
                }
            });
        });

        const scoreInputs = document.querySelectorAll('.score-input');
        scoreInputs.forEach(input => {
            input.addEventListener('input', function() {
                const nameParts = this.name.split('_');
                const competitorId = nameParts[2]; // Adjusted index for competitor ID
                const rollIndex = nameParts[3]; // Adjusted index for roll index

                const leftScoreName = `left_score_${competitorId}_${rollIndex}`;
                const leftDeductName = `left_deduct_${competitorId}_${rollIndex}`;
                const rightScoreName = `right_score_${competitorId}_${rollIndex}`;
                const rightDeductName = `right_deduct_${competitorId}_${rollIndex}`;
                const totalName = `total_${competitorId}_${rollIndex}`;

                const leftScore = parseInt(document.querySelector(`[name='${leftScoreName}']`).value) || 0;
                const leftDeduct = parseInt(document.querySelector(`[name='${leftDeductName}']`).value) || 0;
                const rightScore = parseInt(document.querySelector(`[name='${rightScoreName}']`).value) || 0;
                const rightDeduct = parseInt(document.querySelector(`[name='${rightDeductName}']`).value) || 0;

                let total = (leftScore - leftDeduct) + (rightScore - rightDeduct);
                total = total < 0 ? 0 : total;

                const totalField = document.querySelector(`[name='${totalName}']`);
                if (totalField) {
                    totalField.value = total;
                }
            });
        });
    });
</script>

<?php   
}


// make this function handle empty values!

function handle_competitors_score_update() {

    error_reporting(E_ALL); 
    ini_set('display_errors', 1);

    if (isset($_POST['action']) && $_POST['action'] == 'competitors_score_update' && current_user_can('manage_options')) {
        // Verify nonce
        if (!isset($_POST['competitors_score_update_nonce']) || !wp_verify_nonce($_POST['competitors_score_update_nonce'], 'competitors_score_update_action')) {
            wp_die('Security check failed. Are you sure you should be here?');
        }
        // Iterate through POST data and save scores
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'left_score_') !== false || strpos($key, 'left_deduct_') !== false || strpos($key, 'right_score_') !== false || strpos($key, 'right_deduct_') !== false) {
                // Extract competitor ID and roll index from the field name
                $parts = explode('_', $key);
                $competitor_id = $parts[2];
                $roll_index = $parts[3];

                // Update score in the database
                $result = update_post_meta($competitor_id, $key, sanitize_text_field($value));
                //var_dump($competitor_id, $key, $value); // DEBUGGING ONLY
                if ($result === false) {
                    error_log('Oops! Failed to update meta for competitor ID: ' . $competitor_id . ' and key: ' . $key);
                }
            }
        }
    }
    // Set a transient to show a success message
    set_transient('competitors_scores_updated', 'Scores updated successfully!', 10); // 10 seconds expiration

    // Redirect back to appropriate page
    wp_redirect(admin_url('admin.php?page=competitors-list'));
    exit;
}
add_action('admin_post_competitors_score_update', 'handle_competitors_score_update');












function competitors_scoring_list_page() {
    error_reporting(E_ALL); 
    ini_set('display_errors', 1);

    if (get_transient('competitors_scores_updated')) {
        echo '<div id="message" class="updated notice is-dismissible"><p>' . get_transient('competitors_scores_updated') . '</p></div>';
        // Delete the transient so it's not shown again
        delete_transient('competitors_scores_updated');
    }

    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1 // Fetch all
    );
    $competitors_query = new WP_Query($args);

    echo '<h1>Competitors List</h1>';
    echo '<table class="competitors-table">';

    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();

        // Competitors header row
        echo '<tr class="competitors-header">';
        echo '<th>' . get_the_title() . '</th>';
        echo '<th><a href="' . esc_url(admin_url('admin.php?page=competitors-view&competitor_id=' . get_the_ID())) . '">View individual scoring</a></th>';
        echo '</tr>';
    }

    echo '</table>';
    wp_reset_postdata();
}




function competitors_scoring_view_page() {
// error_reporting(E_ALL); 
//ini_set('display_errors', 1);

    $competitor_id = isset($_GET['competitor_id']) ? intval($_GET['competitor_id']) : 0;

    if (!$competitor_id) {
        echo 'Competitor ID not provided.';
        return;
    }
    $roll_names = get_option('competitors_custom_values');
    $listing_page_url = admin_url('admin.php?page=competitors-list');
    echo '<h1><a href="' . esc_url($listing_page_url) . '" class="competitors-back-link"><span class="dashicons dashicons-arrow-left-alt2"></span> Score for ' . esc_html(get_the_title($competitor_id)) . '</a></h1>';
    echo '<table class="competitors-table">';
    echo '<tr><th>Roll Name</th><th>Left Score</th><th>Left Deduct</th><th>Right Score</th><th>Right Deduct</th><th>Total</th></tr>';

    // Iterate through each roll name and fetch corresponding scores
    foreach ($roll_names as $index => $roll_name) {
        $base_meta_key = $competitor_id . '_' . ($index + 1);

        // Fetching meta values in a loop
        $scores = array();
        foreach (array('left_score_', 'left_deduct_', 'right_score_', 'right_deduct_', 'total_') as $key_prefix) {
            $scores[$key_prefix] = get_post_meta($competitor_id, $key_prefix . $base_meta_key, true);
        }
        echo '<tr>';
        echo '<td>' . esc_html($roll_name) . '</td>';
        echo '<td>' . esc_html($scores['left_score_']) . '</td>';
        echo '<td>' . esc_html($scores['left_deduct_']) . '</td>';
        echo '<td>' . esc_html($scores['right_score_']) . '</td>';
        echo '<td>' . esc_html($scores['right_deduct_']) . '</td>';
        echo '<td>' . esc_html($scores['total_']) . ' points</td>';
        echo '</tr>';
    }


    echo '</table>';
}






// Display the settings page content
function competitors_settings_page() {
    // Check if our transient is set and display the message
    if (get_transient('competitors_form_submitted')) {
        echo '<div id="message" class="updated notice is-dismissible"><p>' . get_transient('competitors_form_submitted') . '</p></div>';
        // Delete the transient so the message doesn't keep appearing
        delete_transient('competitors_form_submitted');
    }
    echo '<div class="wrap"><h1>Competitors rolls and settings</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('competitors_settings');
    do_settings_sections('competitors_settings');
    submit_button();
    echo '</form></div>';
}

// Register a new setting for our "Competitors" page
function competitors_settings_init() {
    register_setting(
        'competitors_settings', 
        'competitors_custom_values', 
        'competitors_custom_values_sanitize' // Custom sanitize callback
    );

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
    echo __('<p>');
    echo __('These values correspond to what rolls competitors check with check boxes on the front end, as well as the admin area. ', 'wordpress');
    echo __('You can display either the registration form for competitors or the results on any WP Post or Page with shortcodes. [competitors_form_public] or (COMING SOON!) [competitors_score_public]', 'wordpress');
    echo '</p>';
}

function competitors_text_field_render() {
    $roll_names = get_option('competitors_custom_values');
    if (!is_array($roll_names)) {
        $roll_names = [$roll_names];
    }

    echo '<div id="competitors_roll_names_wrapper">';
    foreach ($roll_names as $index => $roll_name) {
        echo '<p>';
        echo '<input type="text" name="competitors_custom_values[]" size="60" value="' . esc_attr($roll_name) . '" />';
        if ($index === 0) {
            echo '<button type="button" id="add_more_roll_names" class="button button-primary custom-button"></button>';
        }
        echo '</p>';
    }
    echo '</div>';

    // Adding the JS for more rows
    echo "<script>
        jQuery(document).ready(function($) {
            $('#add_more_roll_names').click(function() {
                $('#competitors_roll_names_wrapper').append('<p><input type=\"text\" name=\"competitors_custom_values[]\" size=\"60\" value=\"\" /></p>');
            });
        });
    </script>";
}

function competitors_custom_values_sanitize($input) {
    // Sanitize each input value
    return array_map('sanitize_text_field', $input);
}



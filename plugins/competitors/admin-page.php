<?php
// The function that displays the content of the admin page
function competitors_admin_page() {
    if (current_user_can('edit_posts')) {
        $args = array(
            'post_type' => 'competitors',
            'posts_per_page' => -1          
        );
        $competitors_query = new WP_Query($args);

        if ($competitors_query->have_posts()) {
            echo '<h1>Competitors Data</h1>';
            echo '<p>For admins. Get details here. Click on headers to sort. This enables quick grouping and planning.</p>';
            echo '<table class="competitors-table" id="sortable-table">';
            echo '<thead><tr class="competitors-header">';
            echo '<th>Name</th><th>Club</th><th>Class</th><th>Speaker Info</th><th>Sponsors</th><th>Email</th><th>Phone</th><th>Consent</th><th>Total points</th>';
            echo '</tr></thead><tbody>';

            while ($competitors_query->have_posts()) {
                $competitors_query->the_post();
                // Get meta data
                $club = get_post_meta(get_the_ID(), 'club', true);
                $participation_class = get_post_meta(get_the_ID(), 'participation_class', true);
                $speaker_info = get_post_meta(get_the_ID(), 'speaker_info', true);
                $sponsors = get_post_meta(get_the_ID(), 'sponsors', true);
                $email = get_post_meta(get_the_ID(), 'email', true);
                $phone = get_post_meta(get_the_ID(), 'phone', true);
                $consent = get_post_meta(get_the_ID(), 'consent', true);
                $grand_total = get_post_meta(get_the_ID(), 'grand_total', true);
                echo '<tr>';
                echo '<td>' . get_the_title() . '</td>';
                echo '<td>' . esc_html($club) . '</td>';
                echo '<td>' . esc_html($participation_class) . '</td>';
                echo '<td>' . esc_html($speaker_info) . '</td>';
                echo '<td>' . esc_html($sponsors) . '</td>';
                echo '<td>' . esc_html($email) . '</td>';
                echo '<td>' . esc_html($phone) . '</td>';
                echo '<td>' . esc_html($consent) . '</td>';
                echo '<td>' . esc_html($grand_total) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No competitors found.</p>';
        }
        wp_reset_postdata();
    } // current_user_can
}



// This function shalt be called the King inside the loops for scores and a grayed out row class
function get_competitor_scores_and_class($competitor_id, $base_meta_key, $score_keys, $performing_rolls_selected, $index) {
    $scores = array();
    foreach ($score_keys as $key) {
        $meta_key = $key . $base_meta_key;
        $scores[$key] = get_post_meta($competitor_id, $meta_key, true);
    }
    $is_selected = in_array(strval($index), $performing_rolls_selected); // Assuming index-based selection
    $row_class = $is_selected ? 'competitors-scores' : 'competitors-scores not-sure';
    return array('scores' => $scores, 'row_class' => $row_class);
}



function judges_scoring_page() {
    if (!current_user_can('manage_options')) {
        echo 'Access denied to scoring dude, sorry.';
        return;
    }

    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1
    );
    $competitors_query = new WP_Query($args);

    echo '<h1>Competitors Judges Scoring Page</h1>';
    echo '<p>Click to have a look-see.</p>';
    echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
    wp_nonce_field('competitors_score_update_action', 'competitors_score_update_nonce');
    echo '<input type="hidden" name="action" value="competitors_score_update">';
    echo '<table>';
    echo '<tr>';
    echo '<th>Club</th>';
    echo '<th>Class</th>';
    echo '<th colspan="3">Info</th>';
    echo '<th colspan="2">Sponsor</th>';
    echo '</tr>';
    echo '</table>';
    echo '<table class="competitors-table">';

    // Loop through competitors
    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        $competitor_id = get_the_ID();
        $performing_rolls_selected = get_post_meta($competitor_id, 'performing_rolls', true);
        if (!is_array($performing_rolls_selected)) {
            // If it's not an array, find out what it is
            //error_log('Expected array, got ' . gettype($performing_rolls_selected));
            $performing_rolls_selected = []; // Default to an empty array to avoid errors
        }
        // Competitor metadata
        $club = get_post_meta(get_the_ID(), 'club', true);
        $participation_class = get_post_meta(get_the_ID(), 'participation_class', true);
        $speaker_info = get_post_meta(get_the_ID(), 'speaker_info', true);
        $sponsor_info = get_post_meta(get_the_ID(), 'sponsor_info', true);
        $selected_rolls = get_post_meta($competitor_id, 'selected_rolls', true); // This should return an array

        // Competitor header row opens w ajax
        echo '<tr class="competitors-header" data-competitor="' . get_the_ID() . '">';
        echo '<th colspan="7"><span class="dashicons dashicons-arrow-down-alt2"></span> ' . get_the_title() . ' (click to see scoresheet)</th>';
        echo '</tr>';
        // Competitor information row

        echo '<tr class="competitors-info">';
        echo '<td>' . esc_html($club) . '</td>';
        echo '<td>' . esc_html($participation_class) . '</td>';
        echo '<td colspan="3">' . esc_html($speaker_info) . '</td>';
        echo '<td colspan="2">' . esc_html($sponsor_info) . '</td>';
        echo '</tr>';

        // Handle the scoring rows considering the "performing_rolls" selection in public-page.php
        $roll_names = get_option('competitors_custom_values');
        $roll_names_array = array_map('trim', $roll_names);
        $roll_names_array = array_filter($roll_names_array);

        foreach ($roll_names_array as $index => $roll_name) {
            $base_meta_key = $competitor_id . '_' . ($index + 1);
            $score_keys = array('left_score_', 'left_deduct_', 'right_score_', 'right_deduct_', 'total_');
            $result = get_competitor_scores_and_class($competitor_id, $base_meta_key, $score_keys, $performing_rolls_selected, $index);

            echo '<tr class="' . esc_attr($result['row_class']) . '" data-competitor="' . $competitor_id . '">';
            echo '<td colspan="2">' . esc_html($roll_name) . '</td>';
            foreach ($score_keys as $key) {
                echo '<td><input type="text" class="score-input" name="' . $key . $base_meta_key . '" maxlength="2" value="' . esc_attr($result['scores'][$key]) . '"></td>';
            }
            echo '</tr>';
        }
    }

    echo '</table>';
    echo '<input type="submit" value="Update Scores" class="button button-primary">';
    echo '</form>';
    wp_reset_postdata();
}




function handle_competitors_score_update() {
    if (!is_valid_score_update_request()) {
        wp_die('Security check failed. Are you sure you should be here?');
    }

    foreach ($_POST as $key => $value) {
        // Check if the key is a score key and update db
        if (is_competitor_score_field($key)) {
            list($competitor_id, $roll_index) = get_competitor_info_from_key($key);
            update_post_meta($competitor_id, $key, sanitize_text_field($value));
        }
    }

    error_log('Transient Set: ' . print_r(get_transient('competitors_scores_updated'), true));

    set_transient('competitors_scores_updated', 'Scores updated successfully!', 10);
    wp_redirect(admin_url('admin.php?page=competitors-scoring'));
    exit;
}

function is_valid_score_update_request() {
    return isset($_POST['action'], $_POST['competitors_score_update_nonce']) &&
           $_POST['action'] == 'competitors_score_update' &&
           current_user_can('manage_options') &&
           wp_verify_nonce($_POST['competitors_score_update_nonce'], 'competitors_score_update_action');
}

function is_competitor_score_field($key) {
    return preg_match('/^(left_score_|left_deduct_|right_score_|right_deduct_|total_)\d+_\d+$/', $key);
}

function get_competitor_info_from_key($key) {
    $parts = explode('_', $key);
    $competitor_id = $parts[count($parts) - 2];
    $roll_index = $parts[count($parts) - 1];
    return [$competitor_id, $roll_index];
}

add_action('admin_post_competitors_score_update', 'handle_competitors_score_update');

<?php
// Utility Function
function echo_table_cell($content) {
    echo '<td>' . esc_html($content) . '</td>';
}


function competitors_admin_page() {
    if (!current_user_can('manage_options') && !current_user_can('competitors_judge')) {
        echo '<p>Access denied to scoring, dude. You dont seem to be The Judge.</p>';
        return;
    }
    
    $args = array(
        'post_type' => 'competitors',
        'posts_per_page' => -1          
    );
    $competitors_query = new WP_Query($args);

    if ($competitors_query->have_posts()) {
        // Links within the plugin
        // echo '<a href="' . esc_url(admin_url('admin.php?page=my_custom_page')) . '">Go to My Custom Page</a>';

        echo '<h1>Competitors Data</h1>';
        // Go to similar function but public. This is a temp WP "page"
        $page_slug = 'test-results-list-page';
        echo '<p>Click on headers to sort. This enables quick grouping and planning. <a href="' . esc_url(site_url('/' . $page_slug . '/')) . '">Public page</a> for this data.</p>';
        echo '<table class="competitors-table" id="sortable-table">';
        echo '<thead><tr class="competitors-header">';
        echo '<th>Name</th><th>Club</th><th>Class</th><th>Speaker Info</th><th>Sponsors</th><th>Email</th><th>Phone</th><th>Consent</th>';
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
            echo '<tr>';
            echo_table_cell(get_the_title());
            echo_table_cell(esc_html($club));
            echo_table_cell(esc_html($participation_class));
            echo_table_cell(esc_html($speaker_info));
            echo_table_cell(esc_html($sponsors));
            echo_table_cell(esc_html($email));
            echo_table_cell(esc_html($phone));
            echo_table_cell(esc_html($consent));
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No competitors found.</p>';
    }
    wp_reset_postdata();

}





function judges_scoring_page() {

    if (!current_user_can('manage_options') && !current_user_can('competitors_judge')) {
        echo '<p>Access denied to scoring, dude. You dont seem to be The Judge.</p>';
        return;
    }

    $competitors_query = new WP_Query([
        'post_type' => 'competitors',
        'posts_per_page' => -1,
        'order' => 'DESC',
    ]);

    $actionUrl = esc_url(admin_url('admin-post.php'));
    $nonceField = wp_nonce_field('competitors_score_update_action', 'competitors_score_update_nonce', true, false);

    // Using Heredoc syntax to clean up the echo statements. The ending HTML statement is sketchy. Do not indent passed the opening.
    echo <<<HTML
    <h1>Competitors Judges Scoring Page</h1>
    <form action="{$actionUrl}" method="post">
    {$nonceField}
    <input type="hidden" name="action" value="competitors_score_update">
    <input type="hidden" name="start_time" id="start-time" value="">
    <input type="hidden" name="stop_time" id="stop-time" value="">
    <p><input type="submit" value="Update Scores to show live" class="button button-primary"></p>
    <div id="timer">
        <p>Timer: <span id="timer-display">00:00:00</span></p>
        <button type="button" class="button-success" id="start-timer">Start</button>
        <button type="button" class="button-warning" id="stop-timer">Stop</button>
        <button type="button" class="button-danger" id="reset-timer">Reset</button>
    </div>
    <p>Click name rows to have a look-see. Phone is also ok to handle this.</p>
    <div id="judges-scoring-container">
    <table class="competitors-table" id="judges-scoring">
    <tbody>
    HTML;

    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        $competitor_id = get_the_ID();

        echo render_competitor_header_row($competitor_id);
        echo render_competitor_info_row($competitor_id);

        $rolls = get_roll_names_and_max_scores(); // Ensure this function exists and returns an array

        $competitor_scores = get_post_meta($competitor_id, 'competitor_scores', true) ?: [];
        $selected_rolls = get_post_meta($competitor_id, 'selected_rolls', true) ?: [];

        foreach ($rolls as $index => $roll) {
            $roll_scores = $competitor_scores[$index] ?? [];
            echo render_competitor_score_row($competitor_id, $index, $roll, $roll_scores, $selected_rolls);
        }
    }

    echo <<<HTML
    </tbody></table><i id="spinner"></i></div>
    <p><input type="submit" value="Update Scores" class="button button-primary"></p>
    </form>
    HTML;

    wp_reset_postdata();
}



function render_competitor_header_row($competitor_id) {
    $title = get_the_title($competitor_id);
    // Heredoc syntax works fine
    return <<<HTML
        <tr class="competitors-header" data-competitor="$competitor_id">
            <th colspan="2"><span id="close-details" class="dashicons dashicons-arrow-down-alt2"></span> $title <span class="showonhover">(click to see info and scoresheet)</span></th>
            <th width="7%">L</th><th width="7%">L-</th><th width="7%">R</th><th width="7%">R-</th><th width="7%">Sum</th>
        </tr>
    HTML;
}

function render_competitor_info_row($competitor_id) {
    $club = esc_html(get_post_meta($competitor_id, 'club', true));
    $participation_class = esc_html(get_post_meta($competitor_id, 'participation_class', true));
    $speaker_info = esc_html(get_post_meta($competitor_id, 'speaker_info', true));
    $sponsors = esc_html(get_post_meta($competitor_id, 'sponsors', true));
    
    // Fetch start and stop times
    $start_time_meta = get_post_meta($competitor_id, 'start_time', true);
    $stop_time_meta = get_post_meta($competitor_id, 'stop_time', true);

    // Assuming the times are stored as Unix timestamps or are otherwise directly comparable
    $start_time = $start_time_meta ? date('H:i:s', strtotime($start_time_meta)) : 'N/A';
    $stop_time = $stop_time_meta ? date('H:i:s', strtotime($stop_time_meta)) : 'N/A';
    echo (' STRT: ' . $start_time);
    echo (' STOP: ' . $stop_time);
    
    // Calculate total time if both start and stop times are available
    if ($start_time_meta && $stop_time_meta) {
        $total_seconds = strtotime($stop_time_meta) - strtotime($start_time_meta);
        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);
        $seconds = $total_seconds % 60;
        
        // Format total time as H:i:s
        $total_time = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    } else {
        $total_time = 'N/A';
    }

    return <<<HTML
        <tr class="competitors-info hidden" data-competitor="$competitor_id">
            <td>$speaker_info</td>
            <td>$sponsors</td>
            <td colspan="3">$club</td>
            <td>$participation_class</td>
            <td>Start: $start_time | Total: $total_time</td>
        </tr>
    HTML;
}


function render_competitor_score_row($competitor_id, $index, $roll, $scores, $selected_rolls) {
    $roll_name = esc_html($roll['name']);
    $max_score = isset($roll['max_score']) ? esc_html($roll['max_score']) : 'N/A';
    
    $isSelected = in_array($index, $selected_rolls, true);
    $selectedClass = $isSelected ? 'selected-roll' : '';
    
    $row_contents = "<td colspan=\"2\">$roll_name ($max_score)</td>";

    $score_keys = ['left_score', 'left_deduct', 'right_score', 'right_deduct', 'total'];
    foreach ($score_keys as $key) {
        // Adjusted for 'competitor_scores' data structure to display empty if score is 0
        $value = isset($scores[$key]) && $scores[$key] !== 0 ? esc_attr($scores[$key]) : '';
        $input_name = "competitor_scores[$competitor_id][$index][$key]";
        $row_contents .= "<td><input type=\"text\" class=\"score-input\" name=\"$input_name\" maxlength=\"2\" value=\"$value\"></td>";
    }

    return "<tr class=\"competitors-scores $selectedClass hidden\" data-competitor=\"$competitor_id\">$row_contents</tr>";
}




function handle_competitors_score_update_serialized() {
    if (isset($_POST['action'], $_POST['competitors_score_update_nonce']) &&
        $_POST['action'] === 'competitors_score_update' &&
        check_admin_referer('competitors_score_update_action', 'competitors_score_update_nonce')) {

        // Additional handling for start and stop times
        $start_time_global = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $stop_time_global = isset($_POST['stop_time']) ? sanitize_text_field($_POST['stop_time']) : '';

        if (!empty($_POST['competitor_scores']) && is_array($_POST['competitor_scores'])) {
            foreach ($_POST['competitor_scores'] as $competitor_id => $rolls_scores) {
                $competitor_id = intval($competitor_id); // Ensure $competitor_id is an integer

                // Retrieve existing start time for this competitor, if any
                $existing_start_time = get_post_meta($competitor_id, 'start_time', true);

                // If there's no existing start time, save the new start time
                if (empty($existing_start_time) && !empty($start_time_global)) {
                    update_post_meta($competitor_id, 'start_time', $start_time_global);
                }

                // Always update the stop time
                update_post_meta($competitor_id, 'stop_time', $stop_time_global);

                // Initialize an array to hold all scores for serialization
                $scores_array = [];

                foreach ($rolls_scores as $roll_index => $score_types) {
                    $roll_index = intval($roll_index); // Ensure $roll_index is an integer
                    foreach ($score_types as $score_type => $score_value) {
                        $score_type = sanitize_key($score_type);
                        $sanitized_score_value = intval($score_value);

                        // Store scores in an array instead of creating a unique meta key
                        $scores_array[$roll_index][$score_type] = $sanitized_score_value;
                    }
                }

                // Update the 'competitor_scores' meta for the competitor with the new scores array
                update_post_meta($competitor_id, 'competitor_scores', $scores_array);
            }
        }

        // Process selected_rolls if provided
        if (!empty($_POST['selected_rolls']) && is_array($_POST['selected_rolls'])) {
            foreach ($_POST['selected_rolls'] as $competitor_id => $rolls) {
                $competitor_id = intval($competitor_id);
                $selected_rolls_indices = array_map('intval', array_keys($rolls));
                update_post_meta($competitor_id, 'selected_rolls', $selected_rolls_indices);
            }
        }

        set_transient('competitors_scores_update_success', 'Scores successfully updated! Here is where also the timer gets saved. Soon baby.', 10);

        wp_redirect(add_query_arg('competitors_scores_updated', '1', wp_get_referer()));
        exit;
    }
}
add_action('admin_post_competitors_score_update', 'handle_competitors_score_update_serialized');




function show_competitors_scores_update_message() {
    if ($message = get_transient('competitors_scores_update_success')) {
        // Display the message
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';

        // Delete the transient to ensure it's only shown once
        delete_transient('competitors_scores_update_success');
    }
}

// Hook this function to admin_notices to display the message in the WordPress admin
add_action('admin_notices', 'show_competitors_scores_update_message');

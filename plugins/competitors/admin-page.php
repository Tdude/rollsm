<?php
// Utility Function
function echo_table_cell($content) {
    echo '<td>' . esc_html($content) . '</td>';
}


function competitors_admin_page() {
    if (!current_user_can('manage_options') && !current_user_can('edit_competitors')) {
        echo '<h2>\(o_o)/</h2><p>Access denied to scoring, dude. You dont seem to be The Judge.</p>';
        return;
    }
    render_admin_page_header(); // For nav tabs

    $competitors_query = new WP_Query([
        'post_type' => 'competitors',
        'meta_key' => '_competitors_custom_order',
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
        'posts_per_page' => -1,
    ]);

    if ($competitors_query->have_posts()) {
        // Links within the plugin
        // echo '<a href="' . esc_url(admin_url('admin.php?page=my_custom_page')) . '">Go to My Custom Page</a>';

        echo '<h1>Competitors Data</h1>';
        // Go to similar function but public. This is a temp WP "page" and you will have to adjust the URL.
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
        echo '<h2>\(o_o)/</h2><p>No competitors found! </p>';
    }
    wp_reset_postdata();
}


function judges_scoring_page() {
    if (!current_user_can('manage_options') && !current_user_can('competitors_judge')) {
        echo '<p>Access denied to scoring, dude. You dont seem to be The Judge.</p>';
        return;
    }
    render_admin_page_header(); // For nav tabs

    $args = array(
        'post_type' => 'competitors',
        'meta_key' => '_competitors_custom_order',
        'orderby' => 'meta_value_num',
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => '_competitors_custom_order',
                'compare' => 'EXISTS',
            ],
            [
                'key' => '_competitors_custom_order',
                'compare' => 'NOT EXISTS'
            ]
        ],
        'order' => 'DESC',
        'posts_per_page' => -1
    );
    $competitors_query = new WP_Query($args);

    // Check if there are no competitors
    if (!$competitors_query->have_posts()) {
        $no_competitors_message = esc_html__('Looks like there are no competitors to score right now. Please add some competitors or check back later.', 'competitors');
        echo "<h2>\\(o_o)/</h2><p>{$no_competitors_message}</p>";
        return; // Exit the function early
    }

    $action_url = esc_url(admin_url('admin-post.php'));
    $nonce_field = wp_nonce_field('competitors_score_update_action', 'competitors_score_update_nonce', true, false);
    $admin_email = get_option('admin_email');
    $contact_admin = esc_html__('Please contact the Admin for feedback: ', 'competitors');
    $admin_email_link = "{$contact_admin} " . esc_html($admin_email);

    // Localizing static strings
    $judges_scoring_page_title = esc_html__('Judges Scoring Page', 'competitors');
    $timer_label = esc_html__('Timer', 'competitors');
    $start_button_title = esc_attr__('Start timer before scoring competitors!', 'competitors');
    $save_scores_button_title = esc_attr__('Saves scores and time, resets Timer', 'competitors');
    $reset_button_title = esc_attr__('This button and changing competitor resets Timer', 'competitors');
    $clicking_info = wp_kses_post(__('Clicking any competitor name row while the Timer is running <b><i>always resets Timer</i></b>. Timing for a particular competitor can be Paused if you want, or saved when you click "Save scores". This is live score timing. <b><i>There is no going back to adjust!</i></b> If you resave a competitor\'s score, the timing for that competitor will be reset. Once again:<em> If you change competitor view (click another competitor\'s name), timing will reset</em>. Do not mess around. You have now been warned. ', 'competitors'));

    echo <<<HTML
    <h1 class="distance-large">{$judges_scoring_page_title}</h1>
    <form action="{$action_url}" method="post" id="scoring-form">
    {$nonce_field}
    <input type="hidden" name="action" value="competitors_score_update">
    <div id="timer">
    <span class="hideonsmallscreens"><b>{$timer_label}</b></span>
    <button type="button" class="button button-success" id="start-timer" title="{$start_button_title}">Start</button>
    <input type="submit" value="Save scores" class="button button-primary save-scores hideonsmallscreens" title="{$save_scores_button_title}">
    <span id="timer-display">00:00:00</span>
    <button type="button" class="button button-danger" id="reset-timer" title="{$reset_button_title}">Reset</button>
    </div>
    <p>{$clicking_info} {$admin_email_link}.</p>
    <div id="judges-scoring-container">
    <table class="competitors-table" id="judges-scoring">
    <tbody>
    HTML;

    $grand_total = 0;
    $total_rolls_performed = 0;
    $valid_scores_count = 0; // Initialize a counter for valid (non-zero, non-empty) scores
    
    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        $competitor_id = get_the_ID();
        $rolls = get_roll_names_and_max_scores(); // from settings
        $competitor_scores = get_post_meta($competitor_id, 'competitor_scores', true) ?: [];
        $selected_rolls = get_post_meta($competitor_id, 'selected_rolls', true) ?: [];
        $competitor_total_score = 0;
        $tempHTML = $layoutHTML = '';
        // Generate timing fields for each competitor updated by js
        $tempHTML .= <<<HTML
        <input type="hidden" name="start_time[{$competitor_id}]" id="start-time-{$competitor_id}" value="">
        <input type="hidden" name="stop_time[{$competitor_id}]" id="stop-time-{$competitor_id}" value="">
        <input type="hidden" name="elapsed_time[{$competitor_id}]" id="elapsed-time-{$competitor_id}" value="">
        HTML;
    
        foreach ($rolls as $index => $roll) {
            $roll_scores = $competitor_scores[$index] ?? [];
            // Directly calculate the total points for this roll
            $roll_total = ($roll_scores['left_score'] ?? 0) - ($roll_scores['left_deduct'] ?? 0) +
                          ($roll_scores['right_score'] ?? 0) - ($roll_scores['right_deduct'] ?? 0);
    
            if (!empty($roll_total) && $roll_total != 0) {
                $competitor_total_score += $roll_total;
                $total_rolls_performed++;
            }
    
            // Append score row to temporary HTML
            $tempHTML .= render_competitor_score_row($competitor_id, $index, $roll, $roll_scores, $selected_rolls);
        }
    
        // Append header and info rows with the updated competitor_total_score to temporary HTML
        $layoutHTML .= render_competitor_header_row($competitor_id, $competitor_total_score);
        $layoutHTML .= render_competitor_info_row($competitor_id);
        $layoutHTML .= $tempHTML;
        // Now append the total row
        $layoutHTML .= '<tr class="competitors-totals hidden" data-competitor="' . $competitor_id . '">
        <td colspan="5"><b>Total</b></td><td><b>' . $competitor_total_score . ' points</b></td></tr>';
        // Check if competitor_total_score is non-zero
        if ($competitor_total_score > 0) {
            $grand_total += $competitor_total_score;
            $valid_scores_count++;
        }
        // Output the stored HTML
        echo $layoutHTML;
    }


    // Calculate averages based on valid scores rather than total number of contestants
    $average_score = $valid_scores_count > 0 ? $grand_total / $valid_scores_count : 0;
    $average_rolls = $valid_scores_count > 0 ? $total_rolls_performed / $valid_scores_count : 0;
    // Format to n decimals
    $average_score_formatted = number_format($average_score, 1, '.', '');
    $average_rolls_formatted = number_format($average_rolls, 1, '.', '');

    // After all competitors are processed, display the Grand Total and the average score
    echo <<<HTML
    <tr class="competitors-totals grand-total" data-competitor="$competitor_id">
    <td colspan="2"><b>Rolls to perform</b> (Avg: <b>{$average_rolls_formatted}</b>  per competitor)</td>
    <td colspan="3"><b>Grand Total Score</b> (Avg: <b>{$average_score_formatted}</b> points per competitor)</td>
    <td><b>{$grand_total}</b></td></tr></tbody></table>
    <div id="spinner" class="fade-inout"></div>
    <div id="message-overlay" class="fade-inout"></div>
    <p><input type="submit" value="Save scores" class="button button-primary save-scores" title="Saves scores and time, resets Timer. Just like the button on top."></p>
    </form>
    HTML;
}


function render_competitor_header_row($competitor_id, $competitor_total_score) {
    $title = get_the_title($competitor_id);
    // Now using $competitor_total_score in the heredoc output
    return <<<HTML
    <tr class="competitors-header" data-competitor="$competitor_id" title="Clicking here always resets Timer. Careful!">
    <th colspan="5"><span id="close-details" class="dashicons dashicons-arrow-down-alt2"></span><b class="larger-txt"> $title</b> <span class="showonhover">(click to see info and scoresheet)</span></th>
    <th width="7%">$competitor_total_score points</th>
    </tr>
    HTML;
}


function render_competitor_info_row($competitor_id) {
    $club = esc_html(get_post_meta($competitor_id, 'club', true));
    $participation_class = esc_html(get_post_meta($competitor_id, 'participation_class', true));
    $speaker_info = esc_html(get_post_meta($competitor_id, 'speaker_info', true));
    $sponsors = esc_html(get_post_meta($competitor_id, 'sponsors', true));
    
    // Fetch start, stop, and elapsed times
    $start_time_meta = get_post_meta($competitor_id, 'start_time', true);
    $stop_time_meta = get_post_meta($competitor_id, 'stop_time', true);
    $elapsed_time_meta = get_post_meta($competitor_id, 'elapsed_time', true);

    $start_time = $start_time_meta ? date('H:i:s', strtotime($start_time_meta)) : 'N/A';
    $stop_time = $stop_time_meta ? date('H:i:s', strtotime($stop_time_meta)) : 'N/A';

    // Display the saved elapsed time directly
    $elapsed_time = $elapsed_time_meta ?: 'N/A';

    return <<<HTML
    <tr class="competitors-info hidden" data-competitor="$competitor_id">
    <td colspan="6">
    <table>
    <tbody>
    <tr>
    <th>Info</th>
    <th width="7%">Sponsors</th>
    <th width="7%">Club</th>
    <th width="7%">Class</th>
    <th width="7%">Start - Stop</th>
    <th width="7%">Elapsed Time</th>
    </tr>
    <tr>
    <td class="overflow-ellipsis">$speaker_info</td>
    <td class="overflow-ellipsis">$sponsors</td>
    <td>$club</td>
    <td>$participation_class</td>
    <td>$start_time - $stop_time</td>
    <td>$elapsed_time</td>
    </tr>
    </tbody>
    </table>
    </td>
    </tr>
    <tr class="th-columns hidden" data-competitor="$competitor_id"><th>Maneuver</th><th width="7%">L</th><th width="7%">L-</th><th width="7%">R</th><th width="7%">R-</th><th width="7%">Sum</th></tr>
    HTML;
}



function render_competitor_score_row($competitor_id, $index, $roll, $scores, $selected_rolls) {
    $roll_name = esc_html($roll['name']);
    $max_score = isset($roll['max_score']) ? esc_html($roll['max_score']) : 'N/A';
    $is_selected = in_array($index, $selected_rolls, true);
    $selected_class = $is_selected ? 'selected-roll' : '';
    $row_contents = "<td>$roll_name ($max_score)</td>";
    $score_keys = ['left_score', 'left_deduct', 'right_score', 'right_deduct', 'total'];
    foreach ($score_keys as $key) {
        // Adjusted for 'competitor_scores' data structure to display empty if score is 0
        $value = isset($scores[$key]) && $scores[$key] !== 0 ? esc_attr($scores[$key]) : '';
        $input_name = "competitor_scores[$competitor_id][$index][$key]";
        $row_contents .= '<td><input type="text" class="score-input" name="' . $input_name . '" maxlength="2" value="' . $value . '"></td>';
    }

    return '<tr class="competitors-scores ' . $selected_class . ' hidden" data-competitor="' . $competitor_id . '">' . $row_contents . '</tr>';
}



function handle_competitors_score_update_serialized() {
    if (isset($_POST['action'], $_POST['competitors_score_update_nonce']) &&
        $_POST['action'] === 'competitors_score_update' &&
        check_admin_referer('competitors_score_update_action', 'competitors_score_update_nonce')) {

        if (!empty($_POST['competitor_scores']) && is_array($_POST['competitor_scores'])) {
            //echo $competitor_id;
            foreach ($_POST['competitor_scores'] as $competitor_id => $rolls_scores) {
                $competitor_id = intval($competitor_id);

                // Directly fetch individual start and stop times for each competitor
                $start_time = isset($_POST['start_time'][$competitor_id]) ? sanitize_text_field($_POST['start_time'][$competitor_id]) : '';
                $stop_time = isset($_POST['stop_time'][$competitor_id]) ? sanitize_text_field($_POST['stop_time'][$competitor_id]) : '';
                $elapsed_time = isset($_POST['elapsed_time'][$competitor_id]) ? sanitize_text_field($_POST['elapsed_time'][$competitor_id]) : '';

                // Update times without checking for existing values to allow updates
                if (!empty($start_time)) {
                    update_post_meta($competitor_id, 'start_time', $start_time);
                }
                if (!empty($stop_time)) {
                    update_post_meta($competitor_id, 'stop_time', $stop_time);
                }
                if (!empty($elapsed_time)) {
                    update_post_meta($competitor_id, 'elapsed_time', $elapsed_time);
                }

                // Init to hold all scores for serialization
                $scores_array = [];
                foreach ($rolls_scores as $roll_index => $score_types) {
                    $roll_index = intval($roll_index);
                    foreach ($score_types as $score_type => $score_value) {
                        $score_type = sanitize_key($score_type);
                        $sanitized_score_value = intval($score_value);
                        // Store scores in an array not creating gazillions of unique meta keys
                        $scores_array[$roll_index][$score_type] = $sanitized_score_value;
                    }
                }
                // Update the 'competitor_scores' meta for the competitor with the new scores array
                //error_log(print_r($scores_array, true);
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

        set_transient('competitors_scores_update_success', 'Scores successfully updated! Here is where the timer gets saved too.', 10);
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

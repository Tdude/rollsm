<?php
function echo_table_cell($content) {
    echo '<td>' . esc_html($content) . '</td>';
}



function competitors_admin_page() {
    if (!current_user_can('manage_options') && !current_user_can('edit_competitors')) {
        echo '<h2>\(o_o)/</h2><p>Access denied to scoring, dude. You don’t seem to be The Judge.</p>';
        return;
    }

    render_admin_page_header(); // Admin navigation tabs from competitors-settings.php

    // Base query arguments
    $base_query_args = [
        'post_type' => 'competitors',
        'meta_key' => '_competitors_custom_order',
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
        'posts_per_page' => -1
    ];

    $competitors_query = new WP_Query($base_query_args);

    // Display the data
    if ($competitors_query->have_posts()) {
        $page_slug = 'test-results-list-page';
        echo '<p>Click on headers to sort. This enables quick grouping and planning. <a href="' . esc_url(site_url('/' . $page_slug . '/')) . '">Public page</a> for this data.</p>';
        echo '<table class="competitors-table" id="sortable-table">';
        echo '<thead><tr class="competitors-header">';
        echo '<th>Comp. Date</th><th>Name</th><th>Club</th><th>Class</th><th>Info</th><th>Sponsors</th><th>Email</th><th>Phone</th><th>Dinner</th><th>Consent</th>';
        echo '</tr></thead><tbody>';

        while ($competitors_query->have_posts()) {
            $competitors_query->the_post();

            // Retrieve metadata
            $club = get_post_meta(get_the_ID(), 'club', true);
            $participation_class = get_post_meta(get_the_ID(), 'participation_class', true);
            $speaker_info = get_post_meta(get_the_ID(), 'speaker_info', true);
            $sponsors = get_post_meta(get_the_ID(), 'sponsors', true);
            $email = get_post_meta(get_the_ID(), 'email', true);
            $phone = get_post_meta(get_the_ID(), 'phone', true);
            $dinner = get_post_meta(get_the_ID(), 'dinner', true);
            $consent = get_post_meta(get_the_ID(), 'consent', true);
            $competition_date = get_post_meta(get_the_ID(), 'competition_date', true);

            // Render row content
            echo '<tr class="open-details">';
            echo_table_cell(esc_html($competition_date));
            echo_table_cell(get_the_title());
            echo_table_cell(esc_html($club));
            echo_table_cell(esc_html($participation_class));
            echo_table_cell(esc_html($speaker_info));
            echo_table_cell(esc_html($sponsors));
            echo_table_cell(esc_html($email));
            echo_table_cell(esc_html($phone));
            echo_table_cell(esc_html($dinner));
            echo_table_cell(esc_html($consent));
            echo '</tr>';

            // Include the chosen rolls
           echo render_performing_rolls_admin($participation_class);
        }

        echo '</tbody></table>';
    } else {
        echo '<h2>\(o_o)/</h2><p>No competitors found!</p>';
    }

    wp_reset_postdata();
}




// Printing the competitor scoring lists functionality
function render_performing_rolls_admin($participation_class = 'open') {
    ob_start();
    ?>
        <?php
        $selected_rolls_indexes = (array) get_post_meta(get_the_ID(), 'selected_rolls', true);
        $rolls = get_roll_names_and_max_scores($participation_class);
        echo '<tr class="selected-rolls hidden">';
        echo '<td colspan="10">';
        echo '<table>';
        echo '<tr>';
        echo '<th colspan="6">Roll to perform</th>';
        echo '<th colspan="2">' . __('Left (More/Less)', 'competitors') . '</th>';
        echo '<th colspan="2">' . __('Right (More/Less)', 'competitors') . '</th>';
        echo '<th>' . __('Score', 'competitors') . '</th>';
        echo '</tr>';

        foreach ($rolls as $index => $roll) {
            $is_selected = in_array($index, $selected_rolls_indexes, true);
            $selected_css_class = $is_selected ? 'selected-roll' : 'non-selected-roll';
            $max_score = isset($roll['max_score']) ? $roll['max_score'] : '';
            $points_display = ($max_score == 0 || $max_score === '') ? 'N/A' : esc_html($max_score) . ' points';

            echo '<tr class="' . $selected_css_class . '">';
            echo '<td colspan="6">' . esc_html($roll['name']) . ' (' . $points_display . ')</td>';
            echo '<td width="8%"></td>';
            echo '<td width="8%"></td>';
            echo '<td width="8%"></td>';
            echo '<td width="8%"></td>';
            echo '<td width="8%"></td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</td></tr>'; ?>
    <?php
    return ob_get_clean();
}





// Add a column to the 'competitors' post type listing WP page to see all meta keys
function add_meta_keys_column_to_competitors($columns) {
    $columns['meta_keys'] = 'Meta keys';
    return $columns;
}
add_filter('manage_competitors_posts_columns', 'add_meta_keys_column_to_competitors');

// Populate the custom column with metadata keys from 'competitors' posts
function show_meta_keys_in_competitors_column($column, $post_id) {
    if ($column == 'meta_keys') {
        $all_meta = get_post_meta($post_id);
        $meta_keys = array_keys($all_meta);
        // Display the keys as a comma-separated list
        echo esc_html(implode(', ', $meta_keys));
    }
}
add_action('manage_competitors_posts_custom_column', 'show_meta_keys_in_competitors_column', 10, 2);



function display_filter_form($filter_date = '', $filter_class = '') {
    // Retrieve options
    $options = get_option('competitors_options');
    
    // Extract competition dates from options
    $events = isset($options['available_competition_dates']) ? $options['available_competition_dates'] : [];
    if (!is_array($events)) {
        $events = [];
    }

    // Extract competition classes from options
    $classes = isset($options['available_competition_classes']) ? $options['available_competition_classes'] : [];
    if (!is_array($classes)) {
        $classes = [];
    }

    ?>
    <div id="filter_form" class="distance-large">
        <p>These filters are persistent until you change or reset.</p>
        <label for="filter_date">Select Competition Date: </label>
        <select id="filter_date" name="filter_date">
            <option value=""><?php _e('All Dates', 'competitors'); ?></option>
            <?php foreach ($events as $event): ?>
                <option value="<?php echo esc_attr($event['date']); ?>" <?php selected($filter_date, $event['date']); ?>>
                    <?php echo esc_html($event['date'] . ' - ' . $event['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="filter_class">Select Class: </label>
        <select id="filter_class" name="filter_class">
            <option value=""><?php _e('All Classes', 'competitors'); ?></option>
            <?php foreach ($classes as $class): ?>
                <?php if (isset($class['name']) && isset($class['comment'])): ?>
                    <option value="<?php echo esc_attr($class['name']); ?>" <?php selected($filter_class, $class['name']); ?>>
                        <?php echo esc_html($class['comment']); ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>

        <button type="button" id="filter_button" class="button button-primary">Filter</button>
        <button type="button" id="reset_button" class="button button-secondary">Reset</button>
    </div>
    <?php
}






function get_filtered_competitors_query($filter_date = '', $filter_class = '') {
    $query_args = [
        'post_type' => 'competitors',
        'posts_per_page' => -1,
    ];

    $meta_query = [];

    if (!empty($filter_date)) {
        $meta_query[] = [
            'key' => 'competition_date',
            'value' => $filter_date,
            'compare' => '='
        ];
    }

    if (!empty($filter_class)) {
        $meta_query[] = [
            'key' => 'participation_class',
            'value' => $filter_class,
            'compare' => '='
        ];
    }

    if (!empty($meta_query)) {
        $query_args['meta_query'] = $meta_query;
    }

    return new WP_Query($query_args);
}




function filter_competitors() {
    check_ajax_referer('competitors_nonce_action', 'nonce');
    $filter_date = sanitize_text_field($_POST['filter_date']);
    $filter_class = sanitize_text_field($_POST['filter_class']);
    $competitors_query = get_filtered_competitors_query($filter_date, $filter_class);

    ob_start(); // Start a new buffer to capture HTML output
    if ($competitors_query->have_posts()) {
        display_competitors_table($competitors_query);
        $html = ob_get_clean(); // Get the HTML content and clean the buffer
        wp_send_json_success(['html' => $html]);
    } else {
        $html = ob_get_clean();
        $html .= '<p>No competitors found for the selected filters. Please try different filters or reset the current filters.</p>';
        wp_send_json_success(['html' => $html]);
    }

    wp_die();
}
add_action('wp_ajax_filter_competitors', 'filter_competitors');




// Render the whole page
function judges_scoring_page() {
    if (!current_user_can('manage_options') && !current_user_can('competitors_judge')) {
        echo '<p>Access denied to scoring, dude. You don’t seem to be The Judge.</p>';
        return;
    }
    // For nav tabs
    render_admin_page_header();
    // The page title
    $judges_scoring_page_title = esc_html__('Judges Scoring Page', 'competitors');
    echo '<h1 class="distance-large">' . $judges_scoring_page_title . '</h1>';
    // Retrieve the filter date and class from the query string or initialize them as empty
    $filter_date = isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : '';
    $filter_class = isset($_GET['filter_class']) ? sanitize_text_field($_GET['filter_class']) : '';
    $competitors_query = get_filtered_competitors_query($filter_date, $filter_class);
    display_filter_form($filter_date, $filter_class);
    echo '<div id="judges-scoring-container">';
    display_competitors_table($competitors_query);
    echo '</div>';
}







function display_competitors_table($competitors_query) {
    if (!$competitors_query->have_posts()) {
        $no_competitors_message = esc_html__('Looks like there are no competitors to score here right now. Please add some competitors, choose another date or check back later.', 'competitors');
        echo "<h2>\\(o_o)/</h2><p>{$no_competitors_message}</p>";
        return; // Exit the function early
    }

    $action_url = esc_url(admin_url('admin-ajax.php'));
    $nonce_field = wp_nonce_field('competitors_nonce_action', 'competitors_score_update_nonce');
    $admin_email = get_option('admin_email');
    $contact_admin = esc_html__('Please contact the Admin for feedback: ', 'competitors');
    $admin_email_link = "{$contact_admin} " . esc_html($admin_email);
    $timer_label = esc_html__('Timer', 'competitors');
    $start_button_title = esc_attr__('Start timer before scoring competitors!', 'competitors');
    $save_scores_button_title = esc_attr__('Saves scores and time, resets Timer', 'competitors');
    $reset_button_title = esc_attr__('This button and changing competitor resets Timer', 'competitors');
    $clicking_info = wp_kses_post(__('Clicking any competitor name row while the Timer is running <b><i>always resets the Timer</i></b>. Timing for a particular competitor can be Paused if you want, or saved when you click "Save scores". This is live score timing. <b><i>There is no going back to adjust!</i></b> If you resave a competitor\'s score, the timing for that competitor will be reset. Once again:<em> If you change competitor view (click another competitor\'s name), timing will reset</em>. Do not mess around. You have now been warned. ', 'competitors'));

    echo <<<HTML
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

    <table class="competitors-table" id="judges-scoring">
    <tbody>
    HTML;

    $grand_total = 0;
    $total_rolls_performed = 0;
    $valid_scores_count = 0; // Initialize a counter for valid scores

    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        $competitor_id = get_the_ID();
        $rolls = get_roll_names_and_max_scores(); // from competitors-settings.php
        $competitor_scores = get_competitor_scores($competitor_id) ?: [];
        $selected_rolls = get_post_meta($competitor_id, 'selected_rolls', true) ?: [];
        $competitor_total_score = 0;
        $tempHTML = $layoutHTML = '';

        $tempHTML .= <<<HTML
        <input type="hidden" name="start_time[{$competitor_id}]" id="start-time-{$competitor_id}" value="">
        <input type="hidden" name="stop_time[{$competitor_id}]" id="stop-time-{$competitor_id}" value="">
        <input type="hidden" name="elapsed_time[{$competitor_id}]" id="elapsed-time-{$competitor_id}" value="">
        HTML;

        // Scoring logic to get the sums right
        foreach ($rolls as $index => $roll) {
            $roll_scores = $competitor_scores[$index] ?? [];

            // Add logging to verify the scores structure
            if (defined('WP_DEBUG') && WP_DEBUG) {
                //error_log("Processing roll $index for competitor $competitor_id: " . print_r($roll_scores, true));
            }

            // Initialize points
            $left_points = 0;
            $right_points = 0;

            // Calculate left points
            if (isset($roll_scores['left']) && $roll_scores['left'] > 0) {
                $left_points = $roll_scores['left'];
            } elseif (isset($roll_scores['left_deduct']) && $roll_scores['left_deduct'] > 0) {
                $left_points = max(0, $roll_scores['left_deduct'] - 1);
            }

            // Calculate right points
            if (isset($roll_scores['right']) && $roll_scores['right'] > 0) {
                $right_points = $roll_scores['right'];
            } elseif (isset($roll_scores['right_deduct']) && $roll_scores['right_deduct'] > 0) {
                $right_points = max(0, $roll_scores['right_deduct'] - 1);
            }

            // Calculate total points for the roll
            $roll_total = $left_points + $right_points;

            // Update competitor total score and roll count
            if ($roll_total != 0) {
                $competitor_total_score += $roll_total;
                $total_rolls_performed++;
            }

            $tempHTML .= render_competitor_score_row($competitor_id, $index, $roll, $roll_scores, $selected_rolls);
        }

        $layoutHTML .= render_competitor_header_row($competitor_id, $competitor_total_score);
        $layoutHTML .= render_competitor_info_row($competitor_id);
        $layoutHTML .= $tempHTML;

        $layoutHTML .= '<tr class="competitor-totals hidden" data-competitor-id="' . $competitor_id . '">
        <td colspan="5"><b>Total</b></td><td><span class="total-points">' . $competitor_total_score . '</span> points</td></tr>';

        if ($competitor_total_score > 0) {
            $grand_total += $competitor_total_score;
            $valid_scores_count++;
        }

        echo $layoutHTML;
    }

    $average_score = $valid_scores_count > 0 ? $grand_total / $valid_scores_count : 0;
    $average_rolls = $valid_scores_count > 0 ? $total_rolls_performed / $valid_scores_count : 0;
    $average_score_formatted = number_format($average_score, 1, '.', '');
    $average_rolls_formatted = number_format($average_rolls, 1, '.', '');

    echo <<<HTML
    <tr class="competitors-totals grand-total" data-competitor-id="$competitor_id">
    <td colspan="2"><b>Rolls to perform</b> (Avg: <b>{$average_rolls_formatted}</b> per competitor)</td>
    <td colspan="3"><b>Grand Total Score</b> (Avg: <b>{$average_score_formatted}</b> points per scored competitor)</td>
    <td><b><span id="grand-total-value">{$grand_total}</span></b></td></tr></tbody></table>
    <div id="spinner" class="fade-inout hidden"></div>
    <div id="message-overlay" class="fade-inout hidden"></div>
    </form>
    HTML;
}



// This would be before the form closing tag
// <p><input type="submit" value="Save new scores NOT TIME" class="button button-primary save-scores" title="Saves scores NOT TIME. Just like the button on top."></p>


function render_competitor_header_row($competitor_id, $competitor_total_score) {
    $title = get_the_title($competitor_id);
    return <<<HTML
    <tr class="competitor-header" data-competitor-id="$competitor_id" title="Clicking here always resets Timer. Careful!">
        <th colspan="5">
            <span class="toggle-details-icon dashicons dashicons-arrow-down-alt2"></span>
            <b class="competitor-name larger-text">$title</b>
            <span class="show-on-hover">(click to see info and scoresheet)</span>
        </th>
        <th width="7%"><span class="total-points">$competitor_total_score points</span></th>
    </tr>
    HTML;
}



function render_competitor_info_row($competitor_id) {
    $club = esc_html(get_post_meta($competitor_id, 'club', true));
    $participation_class = esc_html(get_post_meta($competitor_id, 'participation_class', true));
    $speaker_info = esc_html(get_post_meta($competitor_id, 'speaker_info', true));
    $sponsors = esc_html(get_post_meta($competitor_id, 'sponsors', true));
    
    $start_time_meta = get_post_meta($competitor_id, 'start_time', true);
    $stop_time_meta = get_post_meta($competitor_id, 'stop_time', true);
    $elapsed_time_meta = get_post_meta($competitor_id, 'elapsed_time', true);

    $start_time = $start_time_meta ? date('H:i:s', strtotime($start_time_meta)) : 'N/A';
    $stop_time = $stop_time_meta ? date('H:i:s', strtotime($stop_time_meta)) : 'N/A';
    $elapsed_time = $elapsed_time_meta ?: 'N/A';

    return <<<HTML
    <tr class="competitor-info hidden" data-competitor-id="$competitor_id">
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
    <tr class="competitor-columns hidden" data-competitor-id="$competitor_id">
        <th>Makinniagassat/Roll to perform</th>
        <th width="10%" colspan="2" class="">Left</th>
        <th width="10%" colspan="2" class="">Right</th>
        <th width="7%">Sum</th>
    </tr>
    HTML;
}





function handle_competitors_score_update_serialized() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    if (!isset($_POST['competitors_score_update_nonce'], $_POST['competitor_scores'], $_POST['action']) || 
        !wp_verify_nonce($_POST['competitors_score_update_nonce'], 'competitors_nonce_action') || 
        $_POST['action'] !== 'competitors_score_update' || 
        !is_array($_POST['competitor_scores'])) {
        wp_send_json_error(['message' => 'Invalid request']);
        return;
    }

    foreach ($_POST['competitor_scores'] as $competitor_id => $rolls_scores) {
        $competitor_id = intval($competitor_id);
        if ($competitor_id == 0) {
            wp_send_json_error(['message' => 'Invalid competitor ID.']);
            return;
        }

        $total_score = 0;
        $scores_array = [];

        foreach ($rolls_scores as $roll_index => $score_types) {
            if (!is_array($score_types)) {
                continue;
            }

            $roll_total = 0;

            foreach ($score_types as $score_type => $score_value) {
                $score_value = intval($score_value);
                $scores_array[intval($roll_index)][sanitize_key($score_type)] = $score_value;
                $roll_total += $score_value;
            }

            $scores_array[intval($roll_index)]['total_score'] = $roll_total;
            $total_score += $roll_total;
        }

        $serialized_scores = maybe_serialize($scores_array);
        update_post_meta($competitor_id, 'competitor_scores', $serialized_scores);
        update_post_meta($competitor_id, 'total_score', $total_score);

        // Save timing data if available
        if (isset($_POST['start_time'][$competitor_id])) {
            update_post_meta($competitor_id, 'start_time', sanitize_text_field($_POST['start_time'][$competitor_id]));
        }
        if (isset($_POST['stop_time'][$competitor_id])) {
            update_post_meta($competitor_id, 'stop_time', sanitize_text_field($_POST['stop_time'][$competitor_id]));
        }
        if (isset($_POST['elapsed_time'][$competitor_id])) {
            update_post_meta($competitor_id, 'elapsed_time', sanitize_text_field($_POST['elapsed_time'][$competitor_id]));
        }
    }

    wp_send_json_success(['message' => 'Scores and times successfully updated']);
    wp_die();
}
add_action('wp_ajax_competitors_score_update', 'handle_competitors_score_update_serialized');




function get_competitor_scores($competitor_id) {
    $serialized_scores = get_post_meta($competitor_id, 'competitor_scores', true);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Serialized scores: ' . (is_string($serialized_scores) ? $serialized_scores : 'Not a serialized string'));
    }
    $scores = maybe_unserialize($serialized_scores);
    
    if (defined('WP_DEBUG') && WP_DEBUG && is_array($scores)) {
        error_log('Unserialized scores: ' . print_r($scores, true));
    } else {
        error_log('Failed to unserialize scores or scores not an array: ' . print_r($scores, true));
    }

    return $scores;
}




function render_competitor_score_row($competitor_id, $index, $roll, $scores, $selected_rolls) {
    $roll_name = esc_html($roll['name']);
    $max_score = isset($roll['max_score']) ? intval($roll['max_score']) : 0;
    $less_score = $max_score - 1;
    $is_selected = in_array($index, $selected_rolls, true);
    $selected_class = $is_selected ? 'selected-roll' : '';

    $input_prefix = "competitor_scores[$competitor_id][$index]";

    // Determine checked states
    $left_score_checked = isset($scores['left_group']) && $scores['left_group'] == $max_score ? 'checked' : '';
    $left_deduct_checked = isset($scores['left_group']) && $scores['left_group'] == $less_score ? 'checked' : '';
    $right_score_checked = isset($scores['right_group']) && $scores['right_group'] == $max_score ? 'checked' : '';
    $right_deduct_checked = isset($scores['right_group']) && $scores['right_group'] == $less_score ? 'checked' : '';

    $is_numeric_field = ($roll['is_numeric'] === 'Yes');

    if ($is_numeric_field) {
        $left_numeric_value = isset($scores['left_score']) ? intval($scores['left_score']) : '';
        $right_numeric_value = isset($scores['right_score']) ? intval($scores['right_score']) : '';

        $row_contents = <<<HTML
        <td>{$roll_name} ({$max_score})</td>
        <td><input type="text" name="{$input_prefix}[left_score]" class="numeric-input" maxlength="2" value="{$left_numeric_value}" /></td>
        <td></td>
        <td><input type="text" name="{$input_prefix}[right_score]" class="numeric-input" maxlength="2" value="{$right_numeric_value}" /></td>
        <td></td>
        HTML;
    } else {
        // Group left and right inputs to ensure mutual exclusivity
        $left_name = "{$input_prefix}[left_group]";
        $right_name = "{$input_prefix}[right_group]";

        $row_contents = <<<HTML
        <td>{$roll_name} ({$max_score}p)</td>
        <td class="success-light"><label><input type="radio" class="score-input" name="{$left_name}" value="{$max_score}" {$left_score_checked}> More</label></td>
        <td class="danger-light"><label><input type="radio" class="deduct-input" name="{$left_name}" value="{$less_score}" {$left_deduct_checked}> Less</label></td>
        <td class="success-light"><label><input type="radio" class="score-input" name="{$right_name}" value="{$max_score}" {$right_score_checked}> More</label></td>
        <td class="danger-light"><label><input type="radio" class="deduct-input" name="{$right_name}" value="{$less_score}" {$right_deduct_checked}> Less</label></td>
        HTML;
    }

    // Points calculation
    $left_points = $left_score_checked ? $max_score : ($left_deduct_checked ? $less_score : 0);
    $right_points = $right_score_checked ? $max_score : ($right_deduct_checked ? $less_score : 0);

    if ($is_numeric_field) {
        $left_points = isset($scores['left_score']) ? intval($scores['left_score']) : 0;
        $right_points = isset($scores['right_score']) ? intval($scores['right_score']) : 0;
    }

    $total_score = $left_points + $right_points;

    $row_contents .= <<<HTML
    <td class="total-score-row">{$total_score}</td>
    <input type="hidden" name="{$input_prefix}[total_score]" value="{$total_score}" />
    HTML;

    return "<tr class='competitor-scores {$selected_class} hidden' data-competitor-id='{$competitor_id}' data-index='{$index}'>{$row_contents}</tr>";
}






/*
function debug_display_competitor_scores($competitor_id) {
    $scores = get_post_meta($competitor_id, 'competitor_scores', true);
    $total_score = get_post_meta($competitor_id, 'total_score', true);

    echo '<pre>';
    echo 'Competitor ID: ' . $competitor_id . PHP_EOL;
    echo 'Competitor Scores: ' . print_r($scores, true) . PHP_EOL;
    echo 'Total Score: ' . $total_score . PHP_EOL;
    echo '</pre>';
}

add_action('admin_notices', function() {
    $competitor_id = 1159; // Replace with the relevant competitor ID
    debug_display_competitor_scores($competitor_id);
});
*/




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

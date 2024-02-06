<?php
// Utility Function
function echo_table_cell($content) {
    echo '<td>' . esc_html($content) . '</td>';
}


function competitors_admin_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(__('Access denied.', 'competitors'));
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
    if (!current_user_can('manage_options')) {
        echo 'Access denied to scoring, dude.';
        return;
    }

    $competitors_query = new WP_Query([
        'post_type' => 'competitors',
        'posts_per_page' => -1
    ]);

    echo '<h1>Competitors Judges Scoring Page</h1>';
    echo '<p>Click to have a look-see.</p>';
    echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
    wp_nonce_field('competitors_score_update_action', 'competitors_score_update_nonce');
    echo '<input type="hidden" name="action" value="competitors_score_update">';

    echo '<table class="competitors-table" id="judges-scoring">';

    while ($competitors_query->have_posts()) {
        $competitors_query->the_post();
        $competitor_id = get_the_ID();
        $scores_data = get_post_meta($competitor_id, 'scores', true) ?: [];
        $selected_rolls = get_post_meta($competitor_id, 'selected_rolls', true) ?: []; // Assuming this is how selected rolls are stored

        echo render_competitor_header_row($competitor_id);
        echo render_competitor_info_row($competitor_id);
        $rolls = get_roll_names_and_max_scores(); // Assume this function returns an array of rolls

        foreach ($rolls as $index => $roll) {
            $roll_scores = $scores_data[$index] ?? []; // Use existing scores if available, otherwise an empty array
            echo render_competitor_score_row($competitor_id, $index, $roll, $roll_scores, $selected_rolls);
        }
    }

    echo '</table>';
    echo '<p><input type="submit" value="Update Scores" class="button button-primary"></p>';
    echo '</form>';
    wp_reset_postdata();
}


function render_competitor_header_row($competitor_id) {
    $title = get_the_title($competitor_id);
    // Heredoc syntax works fine
    return <<<HTML
        <tr class="competitors-header" data-competitor="$competitor_id">
            <th colspan="2"><span id="close-details" class="dashicons dashicons-arrow-down-alt2"></span> $title (click to see scoresheet)</th>
            <th width="5%">L</th><th width="5%">L-</th><th width="5%">R</th><th width="5%">R-</th><th width="5%">Sum</th>
        </tr>
    HTML;
}

function render_competitor_info_row($competitor_id) {
    $club = esc_html(get_post_meta($competitor_id, 'club', true));
    $participation_class = esc_html(get_post_meta($competitor_id, 'participation_class', true));
    $speaker_info = esc_html(get_post_meta($competitor_id, 'speaker_info', true));
    $sponsors = esc_html(get_post_meta($competitor_id, 'sponsors', true));

    return <<<HTML
        <tr class="competitors-info hidden" data-competitor="$competitor_id">
            <td>$speaker_info</td>
            <td>$sponsors</td>
            <td colspan="3">$club</td>
            <td colspan="2">$participation_class</td>
        </tr>
    HTML;
}

function render_competitor_score_row($competitor_id, $index, $roll, $scores, $selected_rolls) {
    $roll_name = esc_html($roll['name']);
    $max_score = esc_html($roll['max_score']);
    
    // Check if the current roll index is in the array of selected rolls
    $isSelected = in_array($index, $selected_rolls);
    $selectedClass = $isSelected ? 'selected-roll' : ''; // Use an empty string or a specific class for non-selected
    
    $row_contents = "<td colspan=\"2\" class=\"$selectedClass\">$roll_name $max_score</td>";

    $score_keys = ['left_score', 'left_deduct', 'right_score', 'right_deduct', 'total'];
    foreach ($score_keys as $key) {
        $scores[$key] = $scores[$key] ?? ''; // Ensure each score key is initialized
        $input_name = esc_attr("scores[$competitor_id][$index][$key]");
        $value = esc_attr($scores[$key]);
        $row_contents .= "<td><input type=\"text\" class=\"score-input $selectedClass\" name=\"$input_name\" maxlength=\"2\" value=\"$value\"></td>";
    }

    return "<tr class=\"competitors-scores hidden\" data-competitor=\"$competitor_id\">$row_contents</tr>";
}




function handle_competitors_score_update() {
    // Check if the form is submitted and the nonce is valid
    if (isset($_POST['action']) && $_POST['action'] == 'competitors_score_update' && check_admin_referer('competitors_score_update_action', 'competitors_score_update_nonce')) {
        // From DB: score_33_right_deduct etc. It's creating a post and not an array int the public-page.php
        // Assuming the scores are structured as: scores[competitorID][rollIndex][scoreType]
        if (isset($_POST['scores']) && is_array($_POST['scores'])) {
            foreach ($_POST['scores'] as $competitor_id => $rolls_scores) {
                foreach ($rolls_scores as $roll_index => $score_types) {
                    foreach ($score_types as $score_type => $score_value) {
                        // Sanitize each score value
                        $sanitized_score_value = sanitize_text_field($score_value);
                        
                        // Construct a unique meta key for each score type
                        $meta_key = "score_{$roll_index}_{$score_type}";
                        
                        // Update the score in the competitor's post meta
                        update_post_meta($competitor_id, $meta_key, $sanitized_score_value);
                    }
                }
            }
        }

        // Handle selected rolls, assuming they are structured as: selected_rolls[competitorID][rollIndex]
        if (isset($_POST['selected_rolls']) && is_array($_POST['selected_rolls'])) {
            foreach ($_POST['selected_rolls'] as $competitor_id => $rolls) {
                // Simply storing the array of selected roll indices
                $selected_rolls_indices = array_keys($rolls);
                update_post_meta($competitor_id, 'selected_rolls', $selected_rolls_indices);
            }
        }

        // Redirect to avoid form resubmission issues 
        // Defacto updated URL: admin.php?page=competitors-scoring&competitors_scores_updated=1
        wp_redirect(add_query_arg('competitors_scores_updated', '1', wp_get_referer()));
        exit;
    }
}
add_action('admin_post_competitors_score_update', 'handle_competitors_score_update');

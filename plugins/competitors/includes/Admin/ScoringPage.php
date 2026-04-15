<?php
/**
 * Judge scoring interface backed by custom tables.
 *
 * Replaces: judges_scoring_page(), display_competitors_table(),
 * render_competitor_header_row(), render_competitor_info_row(),
 * render_competitor_score_row(), display_filter_form(), etc.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Admin_ScoringPage {

    /**
     * Render the scoring page.
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_competitors' ) ) {
            echo '<p>' . esc_html__( 'Access denied to scoring, dude. You do not seem to be The Judge.', 'competitors' ) . '</p>';
            return;
        }

        render_admin_page_header();

        $competition = Competitors_CompetitionRepository::find_current();
        if ( ! $competition ) {
            echo '<h1>' . esc_html__( 'Judges Scoring Page', 'competitors' ) . '</h1>';
            echo '<p>' . esc_html__( 'No active competition found. Please create a competition in Classes & Dates.', 'competitors' ) . '</p>';
            return;
        }

        $lock_status = Competitors_CompetitionLock::get_status( (int) $competition['id'] );

        echo '<h1 class="distance-large">' . esc_html__( 'Judges Scoring Page', 'competitors' );
        if ( $lock_status['is_locked'] && ! $lock_status['has_temp_unlock'] ) {
            echo ' <span style="color:red;">(' . esc_html__( 'LOCKED — Read Only', 'competitors' ) . ')</span>';
        }
        echo '</h1>';

        $filter_class  = isset( $_GET['filter_class'] ) ? sanitize_text_field( $_GET['filter_class'] ) : '';
        $filter_gender = isset( $_GET['filter_gender'] ) ? sanitize_text_field( $_GET['filter_gender'] ) : '';

        self::render_filter_form( $competition, $filter_class, $filter_gender );

        echo '<div id="judges-scoring-container">';
        self::render_competitors_table( $competition, $filter_class, $filter_gender );
        echo '</div>';
    }

    /**
     * Render the filter form (date is implicit = current competition).
     */
    private static function render_filter_form( $competition, $filter_class, $filter_gender ) {
        $classes = Competitors_ClassRepository::find_all();
        ?>
        <div id="filter_form" class="distance-large">
            <p><?php esc_html_e( 'These filters are persistent until you change or reset.', 'competitors' ); ?></p>

            <input type="hidden" id="filter_date" value="<?php echo esc_attr( $competition['event_date'] ); ?>">

            <label for="filter_class"><?php esc_html_e( 'Select Class:', 'competitors' ); ?> </label>
            <select id="filter_class" name="filter_class">
                <option value=""><?php esc_html_e( 'All Classes', 'competitors' ); ?></option>
                <?php foreach ( $classes as $class ) : ?>
                    <option value="<?php echo esc_attr( $class['name'] ); ?>" <?php selected( $filter_class, $class['name'] ); ?>>
                        <?php echo esc_html( $class['comment'] ?: $class['name'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="filter_gender"><?php esc_html_e( 'Select Gender:', 'competitors' ); ?> </label>
            <select id="filter_gender" name="filter_gender">
                <option value=""><?php esc_html_e( 'All Genders', 'competitors' ); ?></option>
                <option value="woman" <?php selected( $filter_gender, 'woman' ); ?>><?php esc_html_e( 'Woman', 'competitors' ); ?></option>
                <option value="man" <?php selected( $filter_gender, 'man' ); ?>><?php esc_html_e( 'Man', 'competitors' ); ?></option>
            </select>

            <button type="button" id="filter_button" class="button button-primary"><?php esc_html_e( 'Filter', 'competitors' ); ?></button>
            <button type="button" id="reset_button" class="button button-secondary"><?php esc_html_e( 'Reset', 'competitors' ); ?></button>
        </div>
        <?php
    }

    /**
     * Render the competitors scoring table.
     * Public so AdminAjaxHandler::handle_filter() can call it.
     */
    public static function render_competitors_table( $competition, $filter_class = '', $filter_gender = '' ) {
        $competition_id = (int) $competition['id'];
        $is_readonly    = Competitors_CompetitionLock::is_locked( $competition_id );

        // Get class ID from name if filtered
        $class_id = null;
        if ( $filter_class ) {
            $class_obj = Competitors_ClassRepository::find_by_name( $filter_class );
            $class_id  = $class_obj ? (int) $class_obj['id'] : null;
        }

        $competitors = Competitors_CompetitorRepository::find_by_competition( $competition_id, $class_id );

        // Filter by gender in PHP (simple field filter)
        if ( $filter_gender ) {
            $competitors = array_filter( $competitors, function ( $c ) use ( $filter_gender ) {
                return $c['gender'] === $filter_gender;
            } );
        }

        if ( empty( $competitors ) ) {
            echo '<h2>\\(o_o)/</h2><p>' . esc_html__( 'Looks like there are no competitors to score here right now. Please add some competitors, choose another date or check back later.', 'competitors' ) . '</p>';
            return;
        }

        // Gather total scores and sort by score DESC
        $competitors_data = array();
        foreach ( $competitors as $comp ) {
            $total = Competitors_ScoreRepository::get_total_score( (int) $comp['id'] );
            $competitors_data[] = array_merge( $comp, array( 'total_score' => $total ) );
        }
        usort( $competitors_data, function ( $a, $b ) {
            return $b['total_score'] <=> $a['total_score'];
        } );

        // Form setup
        $action_url  = esc_url( admin_url( 'admin-ajax.php' ) );
        $nonce_field = wp_nonce_field( 'competitors_nonce_action', 'competitors_score_update_nonce', true, false );
        $readonly_attr = $is_readonly ? ' disabled' : '';

        $timer_label = esc_html__( 'Timer', 'competitors' );
        $start_title = esc_attr__( 'Start timer before scoring competitors!', 'competitors' );
        $save_title  = esc_attr__( 'Saves scores and time, resets Timer', 'competitors' );
        $reset_title = esc_attr__( 'This button and changing competitor resets Timer', 'competitors' );
        $admin_email = esc_html( get_option( 'admin_email' ) );
        $contact     = esc_html__( 'Please contact the Admin for feedback:', 'competitors' );

        echo <<<HTML
        <form action="{$action_url}" method="post" id="scoring-form">
            {$nonce_field}
            <input type="hidden" name="action" value="competitors_score_update_v2">
            <input type="hidden" name="competition_id" value="{$competition_id}">
            <div id="timer">
                <span class="hideonsmallscreens"><b>{$timer_label}</b></span>
                <button type="button" class="button button-success" id="start-timer" title="{$start_title}">{$start_title}</button>
                <input type="submit" value="{$save_title}" class="button button-primary save-scores hideonsmallscreens" title="{$save_title}"{$readonly_attr}>
                <span id="timer-display">00:00:00</span>
                <button type="button" class="button button-danger" id="reset-timer" title="{$reset_title}">{$reset_title}</button>
            </div>
            <p>{$contact} {$admin_email}.</p>
            <table class="competitors-table" id="judges-scoring"><tbody>
        HTML;

        $grand_total         = 0;
        $total_rolls_done    = 0;
        $valid_scores_count  = 0;

        foreach ( $competitors_data as $rank => $comp ) {
            $comp_id    = (int) $comp['id'];
            $comp_class = (int) $comp['class_id'];
            $total      = (float) $comp['total_score'];

            // Get competition rolls for this class
            $comp_rolls = Competitors_RollRepository::find_competition_rolls( $competition_id, $comp_class );

            // If no competition roll snapshots exist, fall back to master rolls
            if ( empty( $comp_rolls ) ) {
                $master_rolls = Competitors_RollRepository::find_by_class( $comp_class );
                $comp_rolls   = array_map( function ( $r ) {
                    return array(
                        'id'                     => $r['id'],
                        'snapshot_name'          => $r['name'],
                        'snapshot_max_score'     => $r['max_score'],
                        'snapshot_is_numeric'    => $r['is_numeric'],
                        'snapshot_no_right_left' => $r['no_right_left'],
                        'display_order'          => $r['display_order'],
                    );
                }, $master_rolls );
            }

            $scores        = Competitors_ScoreRepository::find_by_competitor( $comp_id );
            $selected_ids  = Competitors_CompetitorRepository::get_selected_rolls( $comp_id );

            // Build score lookup by competition_roll_id
            $score_map = array();
            foreach ( $scores as $s ) {
                $score_map[ (int) $s['competition_roll_id'] ] = $s;
            }

            // Header row
            echo self::render_header_row( $comp_id, $total, $rank + 1, $comp['name'] );
            echo self::render_info_row( $comp_id, $comp );

            // Hidden timer inputs
            echo '<input type="hidden" name="start_time[' . $comp_id . ']" id="start-time-' . $comp_id . '" value="">';
            echo '<input type="hidden" name="stop_time[' . $comp_id . ']" id="stop-time-' . $comp_id . '" value="">';
            echo '<input type="hidden" name="elapsed_time[' . $comp_id . ']" id="elapsed-time-' . $comp_id . '" value="">';

            // Score rows
            foreach ( $comp_rolls as $idx => $roll ) {
                $roll_id     = (int) $roll['id'];
                $roll_scores = isset( $score_map[ $roll_id ] ) ? $score_map[ $roll_id ] : array();
                $is_selected = in_array( $roll_id, $selected_ids );

                echo self::render_score_row( $comp_id, $roll_id, $idx, $roll, $roll_scores, $is_selected, $is_readonly );

                $row_total = (float) ( $roll_scores['total_score'] ?? 0 );
                if ( $row_total > 0 ) {
                    $total_rolls_done++;
                }
            }

            // Totals row
            echo '<tr class="competitor-totals hidden" data-competitor-id="' . $comp_id . '">';
            echo '<td colspan="6"><b>' . esc_html__( 'Total', 'competitors' ) . '</b></td>';
            echo '<td><span class="total-points">' . (int) $total . '</span> ' . esc_html__( 'points', 'competitors' ) . '</td></tr>';

            if ( $total > 0 ) {
                $grand_total += $total;
                $valid_scores_count++;
            }
        }

        // Grand totals
        $avg_score = $valid_scores_count > 0 ? $grand_total / $valid_scores_count : 0;
        $avg_rolls = $valid_scores_count > 0 ? $total_rolls_done / $valid_scores_count : 0;

        echo '<tr class="competitors-totals grand-total">';
        echo '<td colspan="2"><b>' . esc_html__( 'Rolls to perform', 'competitors' ) . '</b> (Avg: <b>' . number_format( $avg_rolls, 1 ) . '</b> ' . esc_html__( 'per competitor', 'competitors' ) . ')</td>';
        echo '<td colspan="2"><b>' . esc_html__( 'Average score:', 'competitors' ) . '</b> <b>' . number_format( $avg_score, 1 ) . '</b></td>';
        echo '<td colspan="2"><b>' . esc_html__( 'Grand Total Score:', 'competitors' ) . '</b> <b><span id="grand-total-value">' . (int) $grand_total . '</span></b></td>';
        echo '</tr></tbody></table>';
        echo '<div id="spinner" class="fade-inout hidden"></div>';
        echo '<div id="message-overlay" class="fade-inout hidden"></div>';
        echo '</form>';
    }

    /**
     * Render a competitor header row.
     */
    private static function render_header_row( $comp_id, $total_score, $rank, $name ) {
        $name_esc  = esc_html( $name );
        $hover     = esc_html__( 'click to see info and scoresheet', 'competitors' );
        $total_int = (int) $total_score;

        return <<<HTML
        <tr class="competitor-header" data-competitor-id="{$comp_id}" title="Clicking here always resets Timer. Careful!">
            <th colspan="5">
                <span class="toggle-details-icon dashicons dashicons-arrow-down-alt2"></span>
                <b class="competitor-name larger-text">{$rank}. {$name_esc}</b>
                <span class="show-on-hover">({$hover})</span>
            </th>
            <th width="7%"><span class="total-points">{$total_int}</span>p</th>
        </tr>
        HTML;
    }

    /**
     * Render a competitor info row.
     */
    private static function render_info_row( $comp_id, $comp ) {
        $club       = esc_html( $comp['club'] );
        $class_name = '';
        if ( $comp['class_id'] ) {
            $cls = Competitors_ClassRepository::find_by_id( (int) $comp['class_id'] );
            $class_name = $cls ? esc_html( $cls['name'] ) : '';
        }
        $speaker    = esc_html( $comp['speaker_info'] );
        $sponsors   = esc_html( $comp['sponsors'] );

        // Timer data
        $timer = Competitors_ScoreRepository::get_timer( $comp_id );
        $start = $timer && $timer['start_time'] ? esc_html( wp_date( 'H:i:s', strtotime( $timer['start_time'] ) ) ) : esc_html__( 'N/A', 'competitors' );
        $stop  = $timer && $timer['stop_time'] ? esc_html( wp_date( 'H:i:s', strtotime( $timer['stop_time'] ) ) ) : esc_html__( 'N/A', 'competitors' );
        $elapsed = $timer ? esc_html( $timer['elapsed_time'] ) : esc_html__( 'N/A', 'competitors' );

        $l = array(
            'info'    => esc_html__( 'Info', 'competitors' ),
            'spons'   => esc_html__( 'Sponsors', 'competitors' ),
            'club'    => esc_html__( 'Club', 'competitors' ),
            'class'   => esc_html__( 'Class', 'competitors' ),
            'startstop' => esc_html__( 'Start - Stop', 'competitors' ),
            'elapsed' => esc_html__( 'Elapsed Time', 'competitors' ),
            'roll'    => esc_html__( 'Makinniagassat/Roll to perform', 'competitors' ),
            'left'    => esc_html__( 'Left', 'competitors' ),
            'right'   => esc_html__( 'Right', 'competitors' ),
            'sum'     => esc_html__( 'Sum', 'competitors' ),
            'reset'   => esc_html__( 'Reset', 'competitors' ),
        );

        return <<<HTML
        <tr class="competitor-info hidden" data-competitor-id="{$comp_id}">
            <td colspan="7">
                <table><tbody>
                    <tr>
                        <th class="hide-for-print">{$l['info']}</th>
                        <th width="7%" class="hide-for-print">{$l['spons']}</th>
                        <th width="7%">{$l['club']}</th>
                        <th width="7%">{$l['class']}</th>
                        <th width="7%">{$l['startstop']}</th>
                        <th width="7%">{$l['elapsed']}</th>
                    </tr>
                    <tr>
                        <td class="overflow-ellipsis hide-for-print">{$speaker}</td>
                        <td class="overflow-ellipsis hide-for-print">{$sponsors}</td>
                        <td>{$club}</td>
                        <td>{$class_name}</td>
                        <td>{$start} - {$stop}</td>
                        <td>{$elapsed}</td>
                    </tr>
                </tbody></table>
            </td>
        </tr>
        <tr class="competitor-columns hidden" data-competitor-id="{$comp_id}">
            <th>{$l['roll']}</th>
            <th width="10%" colspan="2">{$l['left']}</th>
            <th width="10%" colspan="2">{$l['right']}</th>
            <th width="7%">{$l['sum']}</th>
            <th width="7%">{$l['reset']}</th>
        </tr>
        HTML;
    }

    /**
     * Render a single score row for a roll.
     */
    private static function render_score_row( $comp_id, $roll_id, $idx, $roll, $scores, $is_selected, $is_readonly ) {
        $roll_name    = esc_html( $roll['snapshot_name'] );
        $max_score    = (int) $roll['snapshot_max_score'];
        $less_score   = $max_score - 1;
        $is_numeric   = (bool) $roll['snapshot_is_numeric'];
        $no_rl        = (bool) $roll['snapshot_no_right_left'];
        $sel_class    = $is_selected ? 'selected-roll' : '';

        // Input names use competitor_scores[comp_id][roll_id] for the new AJAX handler
        $prefix = "competitor_scores[{$comp_id}][{$roll_id}]";

        $label = $is_numeric ? $max_score : $max_score . 'p';
        $html  = "<td>{$roll_name} ({$label})</td>";

        if ( $is_numeric ) {
            $html .= self::numeric_inputs( $prefix, $scores, $no_rl );
        } else {
            $html .= self::radio_inputs( $prefix, $max_score, $less_score, $scores, $no_rl );
        }

        $total = self::calc_total( $is_numeric, $scores, $max_score, $less_score, $no_rl );
        $html .= "<td class=\"total-score-row\">{$total}</td>";
        $html .= "<input type=\"hidden\" name=\"{$prefix}[total_score]\" value=\"{$total}\" />";
        $html .= '<td><button type="button" class="reset-row button">X</button></td>';

        $row_id = "competitor-row-{$comp_id}-{$roll_id}";
        return "<tr id='{$row_id}' class='competitor-scores {$sel_class} hidden' data-competitor-id='{$comp_id}' data-index='{$roll_id}'>{$html}</tr>";
    }

    private static function numeric_inputs( $prefix, $scores, $no_rl ) {
        $left  = isset( $scores['left_score'] ) ? (int) $scores['left_score'] : '';
        $right = isset( $scores['right_score'] ) ? (int) $scores['right_score'] : '';

        if ( $no_rl ) {
            return "<td><input type=\"number\" name=\"{$prefix}[left_score]\" class=\"numeric-input\" min=\"0\" max=\"99\" value=\"{$left}\" /></td><td></td>";
        }
        return "<td><input type=\"number\" name=\"{$prefix}[left_score]\" class=\"numeric-input\" min=\"0\" max=\"99\" value=\"{$left}\" /></td><td></td>"
             . "<td><input type=\"number\" name=\"{$prefix}[right_score]\" class=\"numeric-input\" min=\"0\" max=\"99\" value=\"{$right}\" /></td><td></td>";
    }

    private static function radio_inputs( $prefix, $max, $less, $scores, $no_rl ) {
        if ( $no_rl ) {
            $name = "{$prefix}[score]";
            $sc   = isset( $scores['left_group'] ) && (int) $scores['left_group'] === $max ? 'checked' : '';
            $dc   = isset( $scores['left_group'] ) && (int) $scores['left_group'] === $less ? 'checked' : '';
            return "<td class=\"success-light\"><label><input type=\"radio\" class=\"score-input\" name=\"{$name}\" value=\"{$max}\" {$sc}> More</label></td>"
                 . "<td class=\"danger-light\"><label><input type=\"radio\" class=\"deduct-input\" name=\"{$name}\" value=\"{$less}\" {$dc}> Less</label></td>";
        }

        $ln = "{$prefix}[left_group]";
        $rn = "{$prefix}[right_group]";
        $ls = isset( $scores['left_group'] ) && (int) $scores['left_group'] === $max ? 'checked' : '';
        $ld = isset( $scores['left_group'] ) && (int) $scores['left_group'] === $less ? 'checked' : '';
        $rs = isset( $scores['right_group'] ) && (int) $scores['right_group'] === $max ? 'checked' : '';
        $rd = isset( $scores['right_group'] ) && (int) $scores['right_group'] === $less ? 'checked' : '';

        return "<td width=\"10%\" class=\"success-light\"><label><input type=\"radio\" class=\"score-input\" name=\"{$ln}\" value=\"{$max}\" {$ls}> More</label></td>"
             . "<td width=\"10%\" class=\"danger-light\"><label><input type=\"radio\" class=\"deduct-input\" name=\"{$ln}\" value=\"{$less}\" {$ld}> Less</label></td>"
             . "<td width=\"10%\" class=\"success-light\"><label><input type=\"radio\" class=\"score-input\" name=\"{$rn}\" value=\"{$max}\" {$rs}> More</label></td>"
             . "<td width=\"10%\" class=\"danger-light\"><label><input type=\"radio\" class=\"deduct-input\" name=\"{$rn}\" value=\"{$less}\" {$rd}> Less</label></td>";
    }

    private static function calc_total( $is_numeric, $scores, $max, $less, $no_rl ) {
        if ( $is_numeric ) {
            $left  = (int) ( $scores['left_score'] ?? 0 );
            $right = $no_rl ? 0 : (int) ( $scores['right_score'] ?? 0 );
            return $left + $right;
        }

        if ( $no_rl ) {
            $val = (int) ( $scores['left_group'] ?? 0 );
            return ( $val === $max ) ? $max : ( ( $val === $less ) ? $less : 0 );
        }

        $lv = (int) ( $scores['left_group'] ?? 0 );
        $rv = (int) ( $scores['right_group'] ?? 0 );
        $lp = ( $lv === $max ) ? $max : ( ( $lv === $less ) ? $less : 0 );
        $rp = ( $rv === $max ) ? $max : ( ( $rv === $less ) ? $less : 0 );
        return $lp + $rp;
    }
}

<?php
/**
 * Public scoreboard backed by custom tables.
 * Shortcode: [competitors_scoring_public]
 *
 * Replaces: competitors_scoring_list_page(), build_competitors_list_html(),
 *           competitors_scoring_view_page()
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Public_Scoreboard {

    /**
     * Render the scoreboard shortcode.
     *
     * @return string
     */
    public static function render() {
        ob_start();
        self::render_list_page();
        return ob_get_clean();
    }

    /**
     * Render the competitor list page with filters.
     */
    private static function render_list_page() {
        $competitions = Competitors_CompetitionRepository::find_all();
        $classes      = Competitors_ClassRepository::find_all();

        // Date options
        $date_options = '<option value="">' . esc_html__( 'All Dates', 'competitors' ) . '</option>';
        foreach ( $competitions as $comp ) {
            $date_options .= sprintf(
                '<option value="%s">%s</option>',
                esc_attr( $comp['event_date'] ),
                esc_html( $comp['event_date'] . ' - ' . $comp['name'] )
            );
        }

        // Class options
        $class_options = '<option value="">' . esc_html__( 'All classes', 'competitors' ) . '</option>';
        foreach ( $classes as $cls ) {
            $class_options .= sprintf(
                '<option value="%s">%s</option>',
                esc_attr( $cls['name'] ),
                esc_html( $cls['comment'] ?: $cls['name'] )
            );
        }

        // Gender options
        $gender_options = '<option value="">' . esc_html__( 'Gender', 'competitors' ) . '</option>';
        $gender_options .= '<option value="woman">' . esc_html__( 'Woman', 'competitors' ) . '</option>';
        $gender_options .= '<option value="man">' . esc_html__( 'Man', 'competitors' ) . '</option>';

        $title  = esc_html__( 'Competitor List', 'competitors' );
        $filter = esc_html__( 'Select filter', 'competitors' );

        echo <<<HTML
        <div id="competitors-list">
            <h2>{$title}</h2>
            <fieldset>
                <legend>{$filter}</legend>
                <select id="date-select" name="date_select">{$date_options}</select>
                <select id="class-select" name="class_select">{$class_options}</select>
                <select id="gender-select" name="gender_select">{$gender_options}</select>
            </fieldset>
            <div id="spinner"></div>
            <ul class="competitors-table"></ul>
            <div id="competitors-details-container"></div>
        </div>
        HTML;
    }

    /**
     * Build competitor list HTML for AJAX response.
     *
     * @param array $competitors Array from repository.
     * @return string
     */
    public static function build_list_html( array $competitors ) {
        // Add total scores and sort
        $data = array();
        foreach ( $competitors as $comp ) {
            $total  = Competitors_ScoreRepository::get_total_score( (int) $comp['id'] );
            $data[] = array_merge( $comp, array( 'total_score' => $total ) );
        }

        usort( $data, function ( $a, $b ) {
            return $b['total_score'] <=> $a['total_score'];
        } );

        // Build class name map
        $class_map = array();
        foreach ( Competitors_ClassRepository::find_all() as $c ) {
            $class_map[ (int) $c['id'] ] = $c['name'];
        }

        $html = '';
        foreach ( $data as $comp ) {
            $club_display = ! empty( $comp['club'] ) ? ' - ' . esc_html( $comp['club'] ) : '';
            $class_name   = isset( $class_map[ (int) $comp['class_id'] ] ) ? $class_map[ (int) $comp['class_id'] ] : '';
            $total        = (int) $comp['total_score'];

            $html .= sprintf(
                '<li class="competitors-list-item" data-competitor-id="%d" data-participation-class="%s" data-gender="%s"><b>%s</b>%s - %d points</li>',
                (int) $comp['id'],
                esc_attr( $class_name ),
                esc_attr( $comp['gender'] ),
                esc_html( $comp['name'] ),
                $club_display,
                $total
            );
        }

        return $html;
    }

    /**
     * Render competitor detail view (scores table).
     *
     * @param int $competitor_id Custom table competitor ID.
     */
    public static function render_detail( $competitor_id ) {
        $comp = Competitors_CompetitorRepository::find_by_id( $competitor_id );
        if ( ! $comp ) {
            echo '<p>' . esc_html__( 'Competitor not found.', 'competitors' ) . '</p>';
            return;
        }

        $competition_id = (int) $comp['competition_id'];
        $class_id       = (int) $comp['class_id'];

        // Get competition rolls (snapshot or master fallback)
        $comp_rolls = Competitors_RollRepository::find_competition_rolls( $competition_id, $class_id );
        if ( empty( $comp_rolls ) ) {
            $master     = Competitors_RollRepository::find_by_class( $class_id );
            $comp_rolls = array_map( function ( $r ) {
                return array(
                    'id'                     => $r['id'],
                    'snapshot_name'          => $r['name'],
                    'snapshot_max_score'     => $r['max_score'],
                    'snapshot_is_numeric'    => $r['is_numeric'],
                    'snapshot_no_right_left' => $r['no_right_left'],
                    'display_order'          => $r['display_order'],
                );
            }, $master );
        }

        // Scores
        $scores    = Competitors_ScoreRepository::find_by_competitor( $competitor_id );
        $score_map = array();
        foreach ( $scores as $s ) {
            $score_map[ (int) $s['competition_roll_id'] ] = $s;
        }

        // Selected rolls
        $selected_ids = Competitors_CompetitorRepository::get_selected_rolls( $competitor_id );

        $has_scores  = ! empty( $scores );
        $status_text = $has_scores ? '' : esc_html__( ' - Newly registered', 'competitors' );

        echo '<h3><a href="#" id="close-details" class="competitors-back-link">'
           . '<i class="dashicons dashicons-arrow-right-alt2 arrow-back"></i>'
           . esc_html( $comp['name'] ) . $status_text . '</a></h3>';

        echo '<table class="competitors-table">';
        echo '<tr>';
        echo '<th>' . esc_html__( 'Roll Name', 'competitors' ) . '</th>';
        echo '<th>' . esc_html__( 'Left/Saamik', 'competitors' ) . '</th>';
        echo '<th>' . esc_html__( 'Right/Talerpik', 'competitors' ) . '</th>';
        echo '<th>' . esc_html__( 'Score', 'competitors' ) . '</th>';
        echo '</tr>';

        $grand_total = 0;

        foreach ( $comp_rolls as $roll ) {
            $roll_id     = (int) $roll['id'];
            $is_selected = in_array( $roll_id, $selected_ids );
            $css_class   = $is_selected ? 'selected-roll' : 'non-selected-roll';
            $s           = isset( $score_map[ $roll_id ] ) ? $score_map[ $roll_id ] : array();

            $is_numeric  = (bool) $roll['snapshot_is_numeric'];
            $max_score   = (int) $roll['snapshot_max_score'];

            $left_score  = (int) ( $s['left_score'] ?? 0 );
            $left_group  = (int) ( $s['left_group'] ?? 0 );
            $right_score = (int) ( $s['right_score'] ?? 0 );
            $right_group = (int) ( $s['right_group'] ?? 0 );

            $total_left  = $left_score + $left_group;
            $total_right = $right_score + $right_group;
            $total       = $total_left + $total_right;
            $grand_total += $total;

            $max_display = $is_numeric ? '' : ' (' . esc_html( $max_score ) . ')';

            echo '<tr class="' . esc_attr( $css_class ) . '">';
            echo '<td>' . esc_html( $roll['snapshot_name'] ) . $max_display . '</td>';

            if ( $is_numeric ) {
                echo '<td>' . esc_html( $left_score ) . '</td>';
                echo '<td>' . esc_html( $right_score ) . '</td>';
            } else {
                echo '<td>' . ( $left_group ? esc_html( $left_group ) : '' ) . '</td>';
                echo '<td>' . ( $right_group ? esc_html( $right_group ) : '' ) . '</td>';
            }

            echo '<td>' . esc_html( $total ) . '</td>';
            echo '</tr>';
        }

        echo '<tr><td colspan="3"><b>' . esc_html__( 'Score Grand Total', 'competitors' ) . '</b></td>';
        echo '<td><b>' . esc_html( $grand_total ) . '</b></td></tr>';
        echo '</table>';
    }
}

<?php
/**
 * Competitor personal data table backed by custom tables.
 * Replaces: competitors_admin_page()
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Admin_PersonalDataPage {

    /**
     * Render the personal data page.
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_competitors' ) ) {
            echo '<h2>\\(o_o)/</h2><p>' . esc_html__( 'Access denied to scoring, dude. You do not seem to be The Judge.', 'competitors' ) . '</p>';
            return;
        }

        render_admin_page_header();

        $competition = Competitors_CompetitionRepository::find_current();
        if ( ! $competition ) {
            echo '<p>' . esc_html__( 'No active competition found.', 'competitors' ) . '</p>';
            return;
        }

        $competitors = Competitors_CompetitorRepository::find_by_competition( (int) $competition['id'] );

        if ( empty( $competitors ) ) {
            echo '<h2>\\(o_o)/</h2><p>' . esc_html__( 'No competitors found!', 'competitors' ) . '</p>';
            return;
        }

        $page_slug = 'test-results-list-page';
        echo '<p class="hide-for-print">'
            . esc_html__( 'Click on headers to sort. This enables quick grouping and planning.', 'competitors' )
            . ' <a href="' . esc_url( site_url( '/' . $page_slug . '/' ) ) . '">'
            . esc_html__( 'Public page', 'competitors' ) . '</a> '
            . esc_html__( 'for this data.', 'competitors' )
            . '</p>';

        echo '<table class="competitors-table" id="sortable-table">';
        echo '<thead><tr class="competitors-header">';
        echo '<th>' . esc_html__( 'CompDate', 'competitors' ) . '</th>'
           . '<th>' . esc_html__( 'Name', 'competitors' ) . '</th>'
           . '<th>' . esc_html__( 'Club', 'competitors' ) . '</th>'
           . '<th>' . esc_html__( 'Class', 'competitors' ) . '</th>'
           . '<th>' . esc_html__( 'Info', 'competitors' ) . '</th>'
           . '<th>' . esc_html__( 'Sponsors', 'competitors' ) . '</th>'
           . '<th class="hide-for-print">' . esc_html__( 'Email', 'competitors' ) . '</th>'
           . '<th>' . esc_html__( 'Phone', 'competitors' ) . '</th>'
           . '<th class="hide-for-print">' . esc_html__( 'Dinner', 'competitors' ) . '</th>'
           . '<th class="hide-for-print">' . esc_html__( 'Consent', 'competitors' ) . '</th>';
        echo '</tr></thead><tbody>';

        // Build class name lookup
        $class_map = array();
        foreach ( Competitors_ClassRepository::find_all() as $c ) {
            $class_map[ (int) $c['id'] ] = $c['name'];
        }

        foreach ( $competitors as $comp ) {
            $class_name = isset( $class_map[ (int) $comp['class_id'] ] ) ? $class_map[ (int) $comp['class_id'] ] : '';

            echo '<tr class="open-details">';
            self::cell( $competition['event_date'] );
            self::cell( $comp['name'] );
            self::cell( $comp['club'] );
            self::cell( $class_name );
            self::cell( $comp['speaker_info'] );
            self::cell( $comp['sponsors'] );
            self::cell_hide( $comp['email'] );
            self::cell( $comp['phone'] );
            self::cell_hide( $comp['dinner'] );
            self::cell_hide( $comp['consent'] );
            echo '</tr>';

            // Render selected rolls detail row
            echo self::render_rolls_detail( $comp, $competition, $class_name );
        }

        echo '</tbody></table>';
    }

    private static function cell( $content ) {
        echo '<td>' . esc_html( $content ) . '</td>';
    }

    private static function cell_hide( $content ) {
        echo '<td class="hide-for-print">' . esc_html( $content ) . '</td>';
    }

    /**
     * Render the expandable rolls detail row for a competitor.
     */
    private static function render_rolls_detail( $comp, $competition, $class_name ) {
        $comp_id        = (int) $comp['id'];
        $class_id       = (int) $comp['class_id'];
        $competition_id = (int) $competition['id'];

        $comp_rolls = Competitors_RollRepository::find_competition_rolls( $competition_id, $class_id );
        if ( empty( $comp_rolls ) ) {
            $master = Competitors_RollRepository::find_by_class( $class_id );
            $comp_rolls = array_map( function ( $r ) {
                return array(
                    'id'                     => $r['id'],
                    'snapshot_name'          => $r['name'],
                    'snapshot_max_score'     => $r['max_score'],
                    'snapshot_is_numeric'    => $r['is_numeric'],
                    'snapshot_no_right_left' => $r['no_right_left'],
                );
            }, $master );
        }

        $selected_ids = Competitors_CompetitorRepository::get_selected_rolls( $comp_id );

        $html = '<tr class="selected-rolls hidden"><td colspan="10"><table>';
        $html .= '<tr><th colspan="6">' . esc_html__( 'Roll to perform', 'competitors' ) . '</th>';
        $html .= '<th colspan="2">' . esc_html__( 'Left (More/Less)', 'competitors' ) . '</th>';
        $html .= '<th colspan="2">' . esc_html__( 'Right (More/Less)', 'competitors' ) . '</th>';
        $html .= '<th>' . esc_html__( 'Score', 'competitors' ) . '</th></tr>';

        foreach ( $comp_rolls as $roll ) {
            $is_selected = in_array( (int) $roll['id'], $selected_ids );
            $css = $is_selected ? 'selected-roll' : 'non-selected-roll';
            $max = (int) $roll['snapshot_max_score'];
            $pts = ( $max === 0 ) ? 'N/A ' : esc_html( $max ) . 'p';

            $html .= '<tr class="' . $css . '">';
            $html .= '<td colspan="6">' . esc_html( $roll['snapshot_name'] ) . ' (' . $pts . ')</td>';
            $html .= '<td width="8%"></td><td width="8%"></td><td width="8%"></td><td width="8%"></td><td width="8%"></td>';
            $html .= '</tr>';
        }

        $html .= '</table></td></tr>';
        return $html;
    }
}

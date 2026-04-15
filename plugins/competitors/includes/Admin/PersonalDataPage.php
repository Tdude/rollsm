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
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Access denied.', 'competitors' ) . '</p></div>';
            return;
        }

        render_admin_page_header();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Competitor List', 'competitors' ) . '</h1>';

        $competition = Competitors_CompetitionRepository::find_current();
        if ( ! $competition ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'No active competition found.', 'competitors' ) . '</p></div>';
            echo '</div>';
            return;
        }

        $competitors = Competitors_CompetitorRepository::find_by_competition( (int) $competition['id'] );

        if ( empty( $competitors ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'No competitors registered yet.', 'competitors' ) . '</p></div>';
            echo '</div>';
            return;
        }

        $page_slug = 'test-results-list-page';
        echo '<p class="hide-for-print">'
            . esc_html__( 'Click on headers to sort.', 'competitors' )
            . ' <a href="' . esc_url( site_url( '/' . $page_slug . '/' ) ) . '">'
            . esc_html__( 'Public page', 'competitors' ) . '</a> '
            . esc_html__( 'for this data.', 'competitors' )
            . '</p>';

        echo '<table class="competitors-table" id="sortable-table">';
        echo '<thead><tr class="competitors-header">';
        echo '<th>' . esc_html__( 'Event Date', 'competitors' ) . '</th>'
           . '<th>' . esc_html__( 'Name', 'competitors' ) . '</th>'
           . '<th>' . esc_html__( 'Club', 'competitors' ) . '</th>'
           . '<th>' . esc_html__( 'Class', 'competitors' ) . '</th>'
           . '<th class="hide-on-narrow">' . esc_html__( 'Info', 'competitors' ) . '</th>'
           . '<th class="hide-on-narrow">' . esc_html__( 'Sponsors', 'competitors' ) . '</th>'
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

            echo '<tr>';
            self::cell( $competition['event_date'] );
            self::cell( $comp['name'] );
            self::cell( $comp['club'] );
            self::cell( $class_name );
            self::cell_narrow( $comp['speaker_info'] );
            self::cell_narrow( $comp['sponsors'] );
            self::cell_hide( $comp['email'] );
            self::cell( $comp['phone'] );
            self::cell_hide( $comp['dinner'] );
            self::cell_hide( $comp['consent'] );
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>'; // .wrap
    }

    private static function cell( $content ) {
        echo '<td>' . esc_html( $content ) . '</td>';
    }

    private static function cell_narrow( $content ) {
        echo '<td class="hide-on-narrow">' . esc_html( $content ) . '</td>';
    }

    private static function cell_hide( $content ) {
        echo '<td class="hide-for-print">' . esc_html( $content ) . '</td>';
    }
}

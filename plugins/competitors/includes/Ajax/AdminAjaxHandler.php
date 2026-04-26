<?php
/**
 * Admin AJAX handlers backed by custom tables.
 * Replaces: handle_competitors_score_update_serialized(), filter_competitors()
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Ajax_AdminAjaxHandler {

    /**
     * Register AJAX hooks.
     */
    public static function init() {
        add_action( 'wp_ajax_competitors_score_update_v2', array( __CLASS__, 'handle_score_update' ) );
        add_action( 'wp_ajax_filter_competitors_v2', array( __CLASS__, 'handle_filter' ) );
    }

    /**
     * Handle score save from the scoring form.
     * Writes to comp_scores table instead of postmeta.
     */
    public static function handle_score_update() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_competitors' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
            return;
        }

        if ( ! isset( $_POST['competitors_score_update_nonce'] ) ||
             ! wp_verify_nonce( $_POST['competitors_score_update_nonce'], 'competitors_nonce_action' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce.' ) );
            return;
        }

        if ( ! isset( $_POST['competitor_scores'] ) || ! is_array( $_POST['competitor_scores'] ) ) {
            wp_send_json_error( array( 'message' => 'No score data received.' ) );
            return;
        }

        $competition_id = isset( $_POST['competition_id'] ) ? (int) $_POST['competition_id'] : 0;

        // Check lock
        if ( $competition_id ) {
            $lock_error = Competitors_CompetitionLock::enforce( $competition_id );
            if ( is_wp_error( $lock_error ) ) {
                wp_send_json_error( array( 'message' => $lock_error->get_error_message() ) );
                return;
            }
        }

        foreach ( $_POST['competitor_scores'] as $competitor_id => $rolls_scores ) {
            $competitor_id = (int) $competitor_id;
            if ( ! $competitor_id ) {
                continue;
            }

            // Verify competitor belongs to this competition
            if ( $competition_id ) {
                $competitor = Competitors_CompetitorRepository::find_by_id( $competitor_id );
                if ( ! $competitor || (int) $competitor['competition_id'] !== $competition_id ) {
                    continue; // Skip — competitor not in this competition
                }
            }

            foreach ( $rolls_scores as $roll_id => $score_data ) {
                $roll_id = (int) $roll_id;
                if ( ! is_array( $score_data ) ) {
                    continue;
                }

                // Server-side score calculation only — never trust client total_score
                $left_group  = max( 0, (int) ( $score_data['left_group'] ?? 0 ) );
                $right_group = max( 0, (int) ( $score_data['right_group'] ?? 0 ) );
                $left_score  = max( 0.0, (float) ( $score_data['left_score'] ?? 0 ) );
                $right_score = max( 0.0, (float) ( $score_data['right_score'] ?? 0 ) );
                $total       = $left_group + $right_group + $left_score + $right_score;

                Competitors_ScoreRepository::save_score( array(
                    'competitor_id'       => $competitor_id,
                    'competition_roll_id' => $roll_id,
                    'left_group'          => $left_group,
                    'right_group'         => $right_group,
                    'left_score'          => $left_score,
                    'right_score'         => $right_score,
                    'total_score'         => $total,
                ) );
            }

            // Save timing data only if actual values were submitted
            $t_start   = sanitize_text_field( $_POST['start_time'][ $competitor_id ] ?? '' );
            $t_stop    = sanitize_text_field( $_POST['stop_time'][ $competitor_id ] ?? '' );
            $t_elapsed = (int) ( $_POST['elapsed_time'][ $competitor_id ] ?? 0 );

            if ( $t_start !== '' || $t_stop !== '' || $t_elapsed > 0 ) {
                Competitors_ScoreRepository::save_timer( array(
                    'competitor_id' => $competitor_id,
                    'start_time'    => $t_start,
                    'stop_time'     => $t_stop,
                    'elapsed_time'  => $t_elapsed,
                    'total_score'   => Competitors_ScoreRepository::get_total_score( $competitor_id ),
                ) );
            }

            // Also update old postmeta for backward compatibility during transition
            self::sync_to_postmeta( $competitor_id );
        }

        wp_send_json_success( array( 'message' => 'Scores and times successfully updated.' ) );
    }

    /**
     * Handle AJAX filter request for the scoring page.
     */
    public static function handle_filter() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_competitors' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
            return;
        }

        check_ajax_referer( 'competitors_nonce_action', 'nonce' );

        $filter_class  = sanitize_text_field( $_POST['filter_class'] ?? '' );
        $filter_gender = sanitize_text_field( $_POST['filter_gender'] ?? '' );

        $competition = Competitors_CompetitionRepository::find_current();
        if ( ! $competition ) {
            wp_send_json_success( array( 'html' => '<p>' . esc_html__( 'No active competition.', 'competitors' ) . '</p>' ) );
            return;
        }

        ob_start();
        Competitors_Admin_ScoringPage::render_competitors_table( $competition, $filter_class, $filter_gender );
        $html = ob_get_clean();
        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * Sync scores back to old postmeta format for backward compatibility.
     */
    private static function sync_to_postmeta( $competitor_id ) {
        $comp = Competitors_CompetitorRepository::find_by_id( $competitor_id );
        if ( ! $comp || ! $comp['wp_post_id'] ) {
            return;
        }

        $wp_post_id = (int) $comp['wp_post_id'];
        $scores     = Competitors_ScoreRepository::find_by_competitor( $competitor_id );
        $total      = 0;

        $comp_rolls_table = Competitors_Database::table( 'competition_rolls' );
        global $wpdb;

        $scores_array = array();
        foreach ( $scores as $s ) {
            $display_order = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT display_order FROM {$comp_rolls_table} WHERE id = %d",
                    (int) $s['competition_roll_id']
                )
            );
            $index = max( 0, $display_order - 1 );

            $scores_array[ $index ] = array(
                'left_group'  => (int) $s['left_group'],
                'right_group' => (int) $s['right_group'],
                'left_score'  => (int) $s['left_score'],
                'right_score' => (int) $s['right_score'],
                'total_score' => (int) $s['total_score'],
            );

            $total += (int) $s['total_score'];
        }

        update_post_meta( $wp_post_id, 'competitor_scores', maybe_serialize( $scores_array ) );
        update_post_meta( $wp_post_id, 'total_score', $total );
    }
}

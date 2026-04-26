<?php
/**
 * Server-side handler for offline score sync.
 *
 * Receives a batch of scores from localStorage, applies conflict resolution
 * (last-write-wins by scored_at timestamp), and returns per-score status.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Ajax_OfflineSyncHandler {

    /**
     * Register AJAX hooks.
     */
    public static function init() {
        add_action( 'wp_ajax_competitors_offline_sync', array( __CLASS__, 'handle_sync' ) );
    }

    /**
     * Handle bulk score sync from localStorage.
     *
     * Expected POST data:
     *   nonce          - WP nonce
     *   competition_id - int
     *   scores         - JSON array of score objects
     *
     * Each score object: {
     *   competitor_id, competition_roll_id,
     *   left_group, right_group, left_score, right_score,
     *   scored_at (ISO 8601)
     * }
     *
     * Response: { results: [ { competitor_id, competition_roll_id, status } ] }
     *   status: "saved" | "skipped_older" | "error"
     */
    public static function handle_sync() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_competitors' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
            return;
        }

        if ( ! check_ajax_referer( 'competitors_nonce_action', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce.' ) );
            return;
        }

        $competition_id = (int) ( $_POST['competition_id'] ?? 0 );
        $scores_json    = $_POST['scores'] ?? '[]';
        $scores         = json_decode( stripslashes( $scores_json ), true );

        if ( ! is_array( $scores ) || empty( $scores ) ) {
            wp_send_json_error( array( 'message' => 'No scores to sync.' ) );
            return;
        }

        // Check competition lock
        if ( $competition_id ) {
            $lock_error = Competitors_CompetitionLock::enforce( $competition_id );
            if ( is_wp_error( $lock_error ) ) {
                wp_send_json_error( array( 'message' => $lock_error->get_error_message() ) );
                return;
            }
        }

        // Cap client timestamps to prevent far-future manipulation
        $max_allowed_time = time() + 3600; // 1 hour in the future max

        $results = array();

        foreach ( $scores as $score ) {
            $competitor_id      = (int) ( $score['competitor_id'] ?? 0 );
            $competition_roll_id = (int) ( $score['competition_roll_id'] ?? 0 );
            $client_scored_at    = $score['scored_at'] ?? '';

            if ( ! $competitor_id || ! $competition_roll_id ) {
                $results[] = array(
                    'competitor_id'      => $competitor_id,
                    'competition_roll_id' => $competition_roll_id,
                    'status'             => 'error',
                    'reason'             => 'Missing IDs.',
                );
                continue;
            }

            // Verify competitor belongs to this competition
            if ( $competition_id ) {
                $competitor = Competitors_CompetitorRepository::find_by_id( $competitor_id );
                if ( ! $competitor || (int) $competitor['competition_id'] !== $competition_id ) {
                    $results[] = array(
                        'competitor_id'      => $competitor_id,
                        'competition_roll_id' => $competition_roll_id,
                        'status'             => 'error',
                        'reason'             => 'Competitor not in this competition.',
                    );
                    continue;
                }
            }

            // Cap client timestamp
            $client_time = ! empty( $client_scored_at ) ? strtotime( $client_scored_at ) : 0;
            if ( $client_time > $max_allowed_time ) {
                $client_time = time();
            }

            // Check if server has a newer score
            $existing = Competitors_ScoreRepository::find_score( $competitor_id, $competition_roll_id );

            if ( $existing && $client_time > 0 ) {
                $server_time = strtotime( $existing['scored_at'] );

                if ( $server_time && $server_time >= $client_time ) {
                    $results[] = array(
                        'competitor_id'      => $competitor_id,
                        'competition_roll_id' => $competition_roll_id,
                        'status'             => 'skipped_older',
                    );
                    continue;
                }
            }

            // Server-side total calculation only — never trust client total
            $left_group  = max( 0, (int) ( $score['left_group'] ?? 0 ) );
            $right_group = max( 0, (int) ( $score['right_group'] ?? 0 ) );
            $left_score  = max( 0.0, (float) ( $score['left_score'] ?? 0 ) );
            $right_score = max( 0.0, (float) ( $score['right_score'] ?? 0 ) );
            $total       = $left_group + $right_group + $left_score + $right_score;

            $saved = Competitors_ScoreRepository::save_score( array(
                'competitor_id'       => $competitor_id,
                'competition_roll_id' => $competition_roll_id,
                'left_group'          => $left_group,
                'right_group'         => $right_group,
                'left_score'          => $left_score,
                'right_score'         => $right_score,
                'total_score'         => $total,
            ) );

            $results[] = array(
                'competitor_id'      => $competitor_id,
                'competition_roll_id' => $competition_roll_id,
                'status'             => $saved ? 'saved' : 'error',
            );
        }

        wp_send_json_success( array(
            'results'  => $results,
            'synced'   => count( array_filter( $results, function ( $r ) { return $r['status'] === 'saved'; } ) ),
            'skipped'  => count( array_filter( $results, function ( $r ) { return $r['status'] === 'skipped_older'; } ) ),
            'errors'   => count( array_filter( $results, function ( $r ) { return $r['status'] === 'error'; } ) ),
        ) );
    }
}

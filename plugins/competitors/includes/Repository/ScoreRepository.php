<?php
/**
 * Repository for score + timer CRUD.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_ScoreRepository {

    private static function scores_table() {
        return Competitors_Database::table( 'scores' );
    }

    private static function timers_table() {
        return Competitors_Database::table( 'timers' );
    }

    // ─── Scores ──────────────────────────────────────────────────

    /**
     * Get all scores for a competitor.
     *
     * @param int $competitor_id
     * @return array
     */
    public static function find_by_competitor( $competitor_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::scores_table() . " WHERE competitor_id = %d ORDER BY competition_roll_id ASC",
                $competitor_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get a single score by competitor + competition roll.
     *
     * @param int $competitor_id
     * @param int $competition_roll_id
     * @return array|null
     */
    public static function find_score( $competitor_id, $competition_roll_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::scores_table() .
                " WHERE competitor_id = %d AND competition_roll_id = %d",
                $competitor_id,
                $competition_roll_id
            ),
            ARRAY_A
        );
    }

    /**
     * Save a score (insert or update).
     * Uses REPLACE INTO for upsert on the unique key (competitor_id, competition_roll_id).
     *
     * @param array $data
     * @return bool
     */
    public static function save_score( array $data ) {
        global $wpdb;
        $table = self::scores_table();

        $existing = self::find_score(
            (int) $data['competitor_id'],
            (int) $data['competition_roll_id']
        );

        $row = array(
            'competitor_id'      => (int) $data['competitor_id'],
            'competition_roll_id'=> (int) $data['competition_roll_id'],
            'left_group'         => (int) ( $data['left_group'] ?? 0 ),
            'right_group'        => (int) ( $data['right_group'] ?? 0 ),
            'left_score'         => (float) ( $data['left_score'] ?? 0 ),
            'right_score'        => (float) ( $data['right_score'] ?? 0 ),
            'total_score'        => (float) ( $data['total_score'] ?? 0 ),
            'scored_at'          => current_time( 'mysql' ),
        );
        $format = array( '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%s' );

        if ( $existing ) {
            return (bool) $wpdb->update(
                $table,
                $row,
                array( 'id' => $existing['id'] ),
                $format,
                array( '%d' )
            );
        }

        return (bool) $wpdb->insert( $table, $row, $format );
    }

    /**
     * Bulk save scores for a competitor.
     *
     * @param int   $competitor_id
     * @param array $scores Array of { competition_roll_id, left_group, right_group, left_score, right_score, total_score }
     * @return int Number of rows saved.
     */
    public static function save_bulk( $competitor_id, array $scores ) {
        $count = 0;
        foreach ( $scores as $score ) {
            $score['competitor_id'] = $competitor_id;
            if ( self::save_score( $score ) ) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get total score for a competitor (sum of all roll total_scores).
     *
     * @param int $competitor_id
     * @return float
     */
    public static function get_total_score( $competitor_id ) {
        global $wpdb;
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(total_score) FROM " . self::scores_table() . " WHERE competitor_id = %d",
                $competitor_id
            )
        );
        return (float) $total;
    }

    /**
     * Get scores for all competitors in a competition, joined with competitor name.
     *
     * @param int $competition_id
     * @return array
     */
    public static function get_scoreboard( $competition_id ) {
        global $wpdb;
        $scores_table      = self::scores_table();
        $competitors_table = Competitors_Database::table( 'competitors' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.id AS competitor_id, c.name, c.club, c.class_id,
                        COALESCE(SUM(s.total_score), 0) AS total_score
                 FROM {$competitors_table} c
                 LEFT JOIN {$scores_table} s ON s.competitor_id = c.id
                 WHERE c.competition_id = %d
                 GROUP BY c.id
                 ORDER BY total_score DESC",
                $competition_id
            ),
            ARRAY_A
        );
    }

    /**
     * Delete all scores for a competitor.
     *
     * @param int $competitor_id
     * @return int|false
     */
    public static function delete_by_competitor( $competitor_id ) {
        global $wpdb;
        return $wpdb->delete( self::scores_table(), array( 'competitor_id' => $competitor_id ), array( '%d' ) );
    }

    // ─── Timers ──────────────────────────────────────────────────

    /**
     * Get timer for a competitor.
     *
     * @param int $competitor_id
     * @return array|null
     */
    public static function get_timer( $competitor_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::timers_table() . " WHERE competitor_id = %d ORDER BY id DESC LIMIT 1",
                $competitor_id
            ),
            ARRAY_A
        );
    }

    /**
     * Save timer data for a competitor.
     *
     * @param array $data
     * @return int|false Inserted ID or false.
     */
    public static function save_timer( array $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            self::timers_table(),
            array(
                'competitor_id' => (int) $data['competitor_id'],
                'start_time'    => $data['start_time'] ?? null,
                'stop_time'     => $data['stop_time'] ?? null,
                'elapsed_time'  => (int) ( $data['elapsed_time'] ?? 0 ),
                'total_score'   => (float) ( $data['total_score'] ?? 0 ),
            ),
            array( '%d', '%s', '%s', '%d', '%f' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Delete timers for a competitor.
     *
     * @param int $competitor_id
     * @return int|false
     */
    public static function delete_timers( $competitor_id ) {
        global $wpdb;
        return $wpdb->delete( self::timers_table(), array( 'competitor_id' => $competitor_id ), array( '%d' ) );
    }
}

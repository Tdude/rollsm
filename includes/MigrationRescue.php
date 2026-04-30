<?php
/**
 * Post-migration rescue tool.
 *
 * Backfills competition_rolls snapshots that the original migration missed
 * (when competitors_roll_definitions_{slug} options lacked some classes),
 * then re-imports competitor_scores postmeta into comp_scores for any
 * competitor that still has no scores in custom tables.
 *
 * Non-destructive: only INSERTs. Existing comp_scores rows are left alone
 * (UNIQUE KEY on (competitor_id, competition_roll_id) blocks duplicates,
 * and the re-import skips competitors that already have any score).
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_MigrationRescue {

    /**
     * Run the full rescue.
     *
     * @return array { master_rolls_added, snapshots_added, scores_added, competitors_rescued, missing_master_rolls }
     */
    public static function run() {
        // Step 0: Seed master comp_rolls from legacy top-level options for any
        // class that currently has no master rolls. This unblocks the snapshot
        // backfill for classes that the original migrate_rolls() missed
        // (it only looked inside the competitors_options array).
        $master_rolls_added = self::seed_missing_master_rolls();

        $missing_combos = self::find_missing_snapshot_combos();

        $snapshots_added       = 0;
        $missing_master_rolls  = array();

        foreach ( $missing_combos as $combo ) {
            $added = self::snapshot_from_master( (int) $combo['competition_id'], (int) $combo['class_id'] );
            if ( $added === 0 ) {
                $missing_master_rolls[] = $combo;
            }
            $snapshots_added += $added;
        }

        list( $scores_added, $competitors_rescued ) = self::reimport_missing_scores();

        return array(
            'master_rolls_added'   => $master_rolls_added,
            'snapshots_added'      => $snapshots_added,
            'scores_added'         => $scores_added,
            'competitors_rescued'  => $competitors_rescued,
            'missing_master_rolls' => $missing_master_rolls,
        );
    }

    /**
     * For each class with zero master rolls, try to seed comp_rolls from
     * legacy top-level options:
     *   - competitors_custom_values_{class_name}        (roll names)
     *   - competitors_numeric_values_{class_name}       (max scores)
     *   - competitors_is_numeric_field_{class_name}     (numeric flags)
     *   - competitors_no_right_left_{class_name}        (no L/R flags, optional)
     *
     * @return int Rows inserted.
     */
    private static function seed_missing_master_rolls() {
        global $wpdb;
        $classes_table = Competitors_Database::table( 'classes' );
        $rolls_table   = Competitors_Database::table( 'rolls' );

        $classes = $wpdb->get_results( "SELECT * FROM {$classes_table}", ARRAY_A );

        $count = 0;
        foreach ( $classes as $class ) {
            $class_id   = (int) $class['id'];
            $class_name = $class['name'];

            $existing = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$rolls_table} WHERE class_id = %d",
                $class_id
            ) );
            if ( $existing > 0 ) {
                continue;
            }

            $roll_names    = get_option( "competitors_custom_values_{$class_name}", array() );
            $points_values = get_option( "competitors_numeric_values_{$class_name}", array() );
            $is_numeric    = get_option( "competitors_is_numeric_field_{$class_name}", array() );
            $no_right_left = get_option( "competitors_no_right_left_{$class_name}", array() );

            if ( ! is_array( $roll_names ) || empty( $roll_names ) ) {
                continue;
            }

            foreach ( $roll_names as $index => $name ) {
                $name = trim( (string) $name );
                if ( $name === '' ) {
                    continue;
                }

                $max_score = isset( $points_values[ $index ] ) && is_numeric( $points_values[ $index ] )
                    ? (int) $points_values[ $index ]
                    : 0;

                $result = $wpdb->insert(
                    $rolls_table,
                    array(
                        'class_id'      => $class_id,
                        'name'          => sanitize_text_field( $name ),
                        'max_score'     => $max_score,
                        'is_numeric'    => ! empty( $is_numeric[ $index ] ) ? 1 : 0,
                        'no_right_left' => ! empty( $no_right_left[ $index ] ) ? 1 : 0,
                        'display_order' => $index + 1,
                    ),
                    array( '%d', '%s', '%d', '%d', '%d', '%d' )
                );

                if ( $result ) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Find (competition_id, class_id) combos that have competitors but no
     * competition_rolls rows.
     *
     * @return array
     */
    private static function find_missing_snapshot_combos() {
        global $wpdb;
        $competitors_table = Competitors_Database::table( 'competitors' );
        $cr_table          = Competitors_Database::table( 'competition_rolls' );

        return $wpdb->get_results(
            "SELECT DISTINCT c.competition_id, c.class_id
             FROM {$competitors_table} c
             WHERE c.competition_id > 0 AND c.class_id > 0
               AND NOT EXISTS (
                   SELECT 1 FROM {$cr_table} cr
                   WHERE cr.competition_id = c.competition_id
                     AND cr.class_id = c.class_id
               )",
            ARRAY_A
        );
    }

    /**
     * Snapshot competition_rolls for one (competition, class) using the master
     * rolls table as the source of truth.
     *
     * @param int $competition_id
     * @param int $class_id
     * @return int Rows inserted.
     */
    private static function snapshot_from_master( $competition_id, $class_id ) {
        global $wpdb;
        $rolls_table = Competitors_Database::table( 'rolls' );
        $cr_table    = Competitors_Database::table( 'competition_rolls' );

        $rolls = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$rolls_table} WHERE class_id = %d ORDER BY display_order ASC",
                $class_id
            ),
            ARRAY_A
        );

        $count = 0;
        foreach ( $rolls as $roll ) {
            $result = $wpdb->insert(
                $cr_table,
                array(
                    'competition_id'         => $competition_id,
                    'class_id'               => $class_id,
                    'roll_id'                => (int) $roll['id'],
                    'snapshot_name'          => $roll['name'],
                    'snapshot_max_score'     => (int) $roll['max_score'],
                    'snapshot_is_numeric'    => (int) $roll['is_numeric'],
                    'snapshot_no_right_left' => (int) $roll['no_right_left'],
                    'display_order'          => (int) $roll['display_order'],
                ),
                array( '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d' )
            );
            if ( $result ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * For each competitor that has no rows in comp_scores but does have
     * competitor_scores postmeta, map the postmeta to competition_roll IDs
     * and insert.
     *
     * @return array{0:int,1:int} [scores_added, competitors_rescued]
     */
    private static function reimport_missing_scores() {
        global $wpdb;
        $competitors_table = Competitors_Database::table( 'competitors' );
        $scores_table      = Competitors_Database::table( 'scores' );
        $cr_table          = Competitors_Database::table( 'competition_rolls' );

        $candidates = $wpdb->get_results(
            "SELECT c.id, c.wp_post_id, c.competition_id, c.class_id
             FROM {$competitors_table} c
             WHERE c.wp_post_id IS NOT NULL
               AND c.wp_post_id > 0
               AND NOT EXISTS (
                   SELECT 1 FROM {$scores_table} s WHERE s.competitor_id = c.id
               )",
            ARRAY_A
        );

        $scores_added        = 0;
        $competitors_rescued = 0;

        foreach ( $candidates as $row ) {
            $raw    = get_post_meta( (int) $row['wp_post_id'], 'competitor_scores', true );
            $scores = maybe_unserialize( $raw );
            if ( ! is_array( $scores ) || empty( $scores ) ) {
                continue;
            }

            $cr_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, display_order FROM {$cr_table}
                     WHERE competition_id = %d AND class_id = %d
                     ORDER BY display_order ASC",
                    (int) $row['competition_id'],
                    (int) $row['class_id']
                ),
                ARRAY_A
            );

            if ( empty( $cr_rows ) ) {
                continue;
            }

            $roll_id_map = array();
            foreach ( $cr_rows as $cr ) {
                $roll_id_map[ (int) $cr['display_order'] - 1 ] = (int) $cr['id'];
            }

            $inserted_for_this_competitor = 0;

            foreach ( $scores as $roll_index => $score_data ) {
                if ( ! is_array( $score_data ) ) {
                    continue;
                }
                $cr_id = isset( $roll_id_map[ (int) $roll_index ] ) ? $roll_id_map[ (int) $roll_index ] : 0;
                if ( ! $cr_id ) {
                    continue;
                }

                $result = $wpdb->insert(
                    $scores_table,
                    array(
                        'competitor_id'       => (int) $row['id'],
                        'competition_roll_id' => $cr_id,
                        'left_group'          => (int) ( $score_data['left_group'] ?? 0 ),
                        'right_group'         => (int) ( $score_data['right_group'] ?? 0 ),
                        'left_score'          => (float) ( $score_data['left_score'] ?? 0 ),
                        'right_score'         => (float) ( $score_data['right_score'] ?? 0 ),
                        'total_score'         => (float) ( $score_data['total_score'] ?? 0 ),
                    ),
                    array( '%d', '%d', '%d', '%d', '%f', '%f', '%f' )
                );
                if ( $result ) {
                    $scores_added++;
                    $inserted_for_this_competitor++;
                }
            }

            if ( $inserted_for_this_competitor > 0 ) {
                $competitors_rescued++;
            }
        }

        return array( $scores_added, $competitors_rescued );
    }
}

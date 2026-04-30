<?php
/**
 * Repository for master roll definitions + competition roll snapshots.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_RollRepository {

    private static function rolls_table() {
        return Competitors_Database::table( 'rolls' );
    }

    private static function competition_rolls_table() {
        return Competitors_Database::table( 'competition_rolls' );
    }

    // ─── Master Rolls ────────────────────────────────────────────

    /**
     * Get all master rolls for a class.
     *
     * @param int $class_id
     * @return array
     */
    public static function find_by_class( $class_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::rolls_table() . " WHERE class_id = %d ORDER BY display_order ASC",
                $class_id
            ),
            ARRAY_A
        );
    }

    /**
     * Find a master roll by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::rolls_table() . " WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    /**
     * Create a master roll.
     *
     * @param array $data
     * @return int|false
     */
    public static function create( array $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            self::rolls_table(),
            array(
                'class_id'      => (int) $data['class_id'],
                'name'          => sanitize_text_field( $data['name'] ),
                'max_score'     => (int) ( $data['max_score'] ?? 0 ),
                'is_numeric'    => (int) ( $data['is_numeric'] ?? 0 ),
                'no_right_left' => (int) ( $data['no_right_left'] ?? 0 ),
                'display_order' => (int) ( $data['display_order'] ?? 0 ),
            ),
            array( '%d', '%s', '%d', '%d', '%d', '%d' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a master roll.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update( $id, array $data ) {
        global $wpdb;
        $update = array();
        $format = array();

        $fields = array(
            'name'          => '%s',
            'max_score'     => '%d',
            'is_numeric'    => '%d',
            'no_right_left' => '%d',
            'display_order' => '%d',
            'class_id'      => '%d',
        );

        foreach ( $fields as $field => $fmt ) {
            if ( isset( $data[ $field ] ) ) {
                $update[ $field ] = $fmt === '%s' ? sanitize_text_field( $data[ $field ] ) : (int) $data[ $field ];
                $format[]         = $fmt;
            }
        }

        if ( empty( $update ) ) {
            return false;
        }

        return (bool) $wpdb->update( self::rolls_table(), $update, array( 'id' => $id ), $format, array( '%d' ) );
    }

    /**
     * Delete a master roll.
     *
     * @param int $id
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::rolls_table(), array( 'id' => $id ), array( '%d' ) );
    }

    // ─── Competition Roll Snapshots ──────────────────────────────

    /**
     * Snapshot all master rolls for a competition + class.
     * Copies current master roll definitions into the competition_rolls table.
     *
     * @param int $competition_id
     * @param int $class_id
     * @return int Number of rows inserted.
     */
    public static function snapshot_for_competition( $competition_id, $class_id ) {
        global $wpdb;

        $master_rolls = self::find_by_class( $class_id );
        $count        = 0;

        foreach ( $master_rolls as $roll ) {
            $result = $wpdb->insert(
                self::competition_rolls_table(),
                array(
                    'competition_id'        => (int) $competition_id,
                    'class_id'              => (int) $class_id,
                    'roll_id'               => (int) $roll['id'],
                    'snapshot_name'         => $roll['name'],
                    'snapshot_max_score'    => (int) $roll['max_score'],
                    'snapshot_is_numeric'   => (int) $roll['is_numeric'],
                    'snapshot_no_right_left'=> (int) $roll['no_right_left'],
                    'display_order'         => (int) $roll['display_order'],
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
     * Get competition rolls for a competition + class.
     *
     * @param int $competition_id
     * @param int $class_id
     * @return array
     */
    public static function find_competition_rolls( $competition_id, $class_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::competition_rolls_table() .
                " WHERE competition_id = %d AND class_id = %d ORDER BY display_order ASC",
                $competition_id,
                $class_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get a single competition roll by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find_competition_roll_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::competition_rolls_table() . " WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    /**
     * Delete all competition rolls for a competition.
     *
     * @param int $competition_id
     * @return int|false
     */
    public static function delete_competition_rolls( $competition_id ) {
        global $wpdb;
        return $wpdb->delete(
            self::competition_rolls_table(),
            array( 'competition_id' => $competition_id ),
            array( '%d' )
        );
    }
}

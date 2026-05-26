<?php
/**
 * Repository for competition class CRUD.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_ClassRepository {

    private static function table() {
        return Competitors_Database::table( 'classes' );
    }

    /**
     * Get all classes ordered by display_order.
     *
     * @param bool $include_archived When false (default), archived classes
     *                               are excluded — appropriate for the
     *                               public registration form. Pass true
     *                               from scoreboard / admin code that
     *                               needs to surface historical classes.
     * @return array
     */
    public static function find_all( $include_archived = false ) {
        global $wpdb;
        $where = $include_archived ? '' : 'WHERE is_archived = 0';
        return $wpdb->get_results(
            "SELECT * FROM " . self::table() . " {$where} ORDER BY display_order ASC",
            ARRAY_A
        );
    }

    /**
     * Mark a class as archived. Hides it from the public registration
     * form but keeps all competitor assignments, snapshot rolls, and
     * scores intact. Reversible via unarchive().
     *
     * @param int $id
     * @return bool
     */
    public static function archive( $id ) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table(),
            array( 'is_archived' => 1 ),
            array( 'id' => (int) $id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Restore a previously archived class to active status.
     *
     * @param int $id
     * @return bool
     */
    public static function unarchive( $id ) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table(),
            array( 'is_archived' => 0 ),
            array( 'id' => (int) $id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Find a class by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    /**
     * Find a class by name.
     *
     * @param string $name
     * @return array|null
     */
    public static function find_by_name( $name ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE name = %s", $name ),
            ARRAY_A
        );
    }

    /**
     * Create a new class.
     *
     * @param array $data { name, comment, display_order }
     * @return int|false
     */
    public static function create( array $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            self::table(),
            array(
                'name'          => sanitize_text_field( $data['name'] ),
                'comment'       => sanitize_text_field( $data['comment'] ?? '' ),
                'display_order' => (int) ( $data['display_order'] ?? 0 ),
            ),
            array( '%s', '%s', '%d' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a class.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update( $id, array $data ) {
        global $wpdb;
        $update = array();
        $format = array();

        if ( isset( $data['name'] ) ) {
            $update['name'] = sanitize_text_field( $data['name'] );
            $format[]       = '%s';
        }
        if ( isset( $data['comment'] ) ) {
            $update['comment'] = sanitize_text_field( $data['comment'] );
            $format[]          = '%s';
        }
        if ( isset( $data['display_order'] ) ) {
            $update['display_order'] = (int) $data['display_order'];
            $format[]                = '%d';
        }
        if ( isset( $data['is_archived'] ) ) {
            $update['is_archived'] = (int) (bool) $data['is_archived'];
            $format[]              = '%d';
        }

        if ( empty( $update ) ) {
            return false;
        }

        return (bool) $wpdb->update( self::table(), $update, array( 'id' => $id ), $format, array( '%d' ) );
    }

    /**
     * Delete a class by ID.
     *
     * @param int $id
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }
}

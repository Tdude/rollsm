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
     * @return array
     */
    public static function find_all() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . self::table() . " ORDER BY display_order ASC",
            ARRAY_A
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

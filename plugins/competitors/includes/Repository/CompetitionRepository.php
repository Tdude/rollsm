<?php
/**
 * Repository for competition CRUD and lock management.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_CompetitionRepository {

    /**
     * @return string Table name.
     */
    private static function table() {
        return Competitors_Database::table( 'competitions' );
    }

    /**
     * Get all competitions ordered by event_date DESC.
     *
     * @return array
     */
    public static function find_all() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . self::table() . " ORDER BY event_date DESC",
            ARRAY_A
        );
    }

    /**
     * Find a competition by ID.
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
     * Find competition by slug.
     *
     * @param string $slug
     * @return array|null
     */
    public static function find_by_slug( $slug ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE slug = %s", $slug ),
            ARRAY_A
        );
    }

    /**
     * Get the current (active) competition.
     *
     * @return array|null
     */
    public static function find_current() {
        global $wpdb;
        return $wpdb->get_row(
            "SELECT * FROM " . self::table() . " WHERE is_current = 1 LIMIT 1",
            ARRAY_A
        );
    }

    /**
     * Create a new competition.
     * Automatically sets all other competitions to non-current.
     *
     * @param array $data { name, event_date, slug }
     * @return int|false Inserted ID or false.
     */
    public static function create( array $data ) {
        global $wpdb;
        $table = self::table();

        // Clear current flag on all existing competitions
        $wpdb->update( $table, array( 'is_current' => 0 ), array( 'is_current' => 1 ), array( '%d' ), array( '%d' ) );

        $slug = ! empty( $data['slug'] )
            ? sanitize_title( $data['slug'] )
            : sanitize_title( $data['name'] . '-' . $data['event_date'] );

        $result = $wpdb->insert(
            $table,
            array(
                'name'       => sanitize_text_field( $data['name'] ),
                'event_date' => sanitize_text_field( $data['event_date'] ),
                'slug'       => $slug,
                'is_current' => 1,
                'is_locked'  => 0,
            ),
            array( '%s', '%s', '%s', '%d', '%d' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a competition.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update( $id, array $data ) {
        global $wpdb;
        $allowed = array( 'name', 'event_date', 'slug', 'is_current', 'is_locked' );
        $update  = array();
        $format  = array();

        foreach ( $allowed as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $update[ $field ] = $data[ $field ];
                $format[]         = in_array( $field, array( 'is_current', 'is_locked' ), true ) ? '%d' : '%s';
            }
        }

        if ( empty( $update ) ) {
            return false;
        }

        return (bool) $wpdb->update( self::table(), $update, array( 'id' => $id ), $format, array( '%d' ) );
    }

    /**
     * Delete a competition and all its child data (cascade).
     *
     * @param int $id
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;
        $id = (int) $id;

        // Delete child data in dependency order
        // 1. Scores + selected_rolls + timers for competitors in this competition
        $competitor_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM " . Competitors_Database::table( 'competitors' ) . " WHERE competition_id = %d",
            $id
        ) );

        if ( ! empty( $competitor_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $competitor_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM " . Competitors_Database::table( 'scores' ) . " WHERE competitor_id IN ($placeholders)",
                ...$competitor_ids
            ) );
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM " . Competitors_Database::table( 'selected_rolls' ) . " WHERE competitor_id IN ($placeholders)",
                ...$competitor_ids
            ) );
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM " . Competitors_Database::table( 'timers' ) . " WHERE competitor_id IN ($placeholders)",
                ...$competitor_ids
            ) );
        }

        // 2. Competitors
        $wpdb->delete( Competitors_Database::table( 'competitors' ), array( 'competition_id' => $id ), array( '%d' ) );

        // 3. Competition rolls
        $wpdb->delete( Competitors_Database::table( 'competition_rolls' ), array( 'competition_id' => $id ), array( '%d' ) );

        // 4. The competition itself
        return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Lock a competition (prevent edits).
     *
     * @param int $id
     * @return bool
     */
    public static function lock( $id ) {
        return self::update( $id, array( 'is_locked' => 1 ) );
    }

    /**
     * Unlock a competition for corrections.
     *
     * @param int $id
     * @return bool
     */
    public static function unlock( $id ) {
        return self::update( $id, array( 'is_locked' => 0 ) );
    }

    /**
     * Lock all competitions except the given one.
     *
     * @param int $except_id
     * @return int Number of rows updated.
     */
    public static function lock_all_except( $except_id ) {
        global $wpdb;
        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE " . self::table() . " SET is_locked = 1 WHERE id != %d AND is_locked = 0",
                $except_id
            )
        );
    }

    /**
     * Check whether a competition is locked.
     *
     * @param int $id
     * @return bool
     */
    public static function is_locked( $id ) {
        global $wpdb;
        $locked = $wpdb->get_var(
            $wpdb->prepare( "SELECT is_locked FROM " . self::table() . " WHERE id = %d", $id )
        );
        return (bool) $locked;
    }
}

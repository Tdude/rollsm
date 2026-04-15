<?php
/**
 * Repository for competitor CRUD + selected rolls.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_CompetitorRepository {

    private static function table() {
        return Competitors_Database::table( 'competitors' );
    }

    private static function selected_rolls_table() {
        return Competitors_Database::table( 'selected_rolls' );
    }

    /**
     * Find all competitors for a competition, optionally filtered by class.
     *
     * @param int      $competition_id
     * @param int|null $class_id
     * @return array
     */
    public static function find_by_competition( $competition_id, $class_id = null ) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE competition_id = %d",
            $competition_id
        );

        if ( $class_id ) {
            $sql .= $wpdb->prepare( " AND class_id = %d", $class_id );
        }

        $sql .= " ORDER BY display_order ASC, name ASC";

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Find a competitor by ID.
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
     * Find a competitor by their legacy WP post ID.
     *
     * @param int $wp_post_id
     * @return array|null
     */
    public static function find_by_wp_post_id( $wp_post_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE wp_post_id = %d", $wp_post_id ),
            ARRAY_A
        );
    }

    /**
     * Create a new competitor.
     *
     * @param array $data
     * @return int|false Inserted ID or false.
     */
    public static function create( array $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            self::table(),
            array(
                'competition_id' => (int) ( $data['competition_id'] ?? 0 ),
                'class_id'       => (int) ( $data['class_id'] ?? 0 ),
                'wp_post_id'     => isset( $data['wp_post_id'] ) ? (int) $data['wp_post_id'] : null,
                'name'           => sanitize_text_field( $data['name'] ?? '' ),
                'email'          => sanitize_email( $data['email'] ?? '' ),
                'phone'          => sanitize_text_field( $data['phone'] ?? '' ),
                'club'           => sanitize_text_field( $data['club'] ?? '' ),
                'gender'         => sanitize_text_field( $data['gender'] ?? '' ),
                'sponsors'       => sanitize_textarea_field( $data['sponsors'] ?? '' ),
                'speaker_info'   => sanitize_textarea_field( $data['speaker_info'] ?? '' ),
                'license'        => sanitize_text_field( $data['license'] ?? '' ),
                'dinner'         => sanitize_text_field( $data['dinner'] ?? '' ),
                'consent'        => sanitize_text_field( $data['consent'] ?? '' ),
                'fee'            => (float) ( $data['fee'] ?? 0 ),
                'display_order'  => (int) ( $data['display_order'] ?? 0 ),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a competitor.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update( $id, array $data ) {
        global $wpdb;

        $fields = array(
            'competition_id' => '%d',
            'class_id'       => '%d',
            'name'           => '%s',
            'email'          => '%s',
            'phone'          => '%s',
            'club'           => '%s',
            'gender'         => '%s',
            'sponsors'       => '%s',
            'speaker_info'   => '%s',
            'license'        => '%s',
            'dinner'         => '%s',
            'consent'        => '%s',
            'fee'            => '%f',
            'display_order'  => '%d',
        );

        $update = array();
        $format = array();

        foreach ( $fields as $field => $fmt ) {
            if ( ! isset( $data[ $field ] ) ) {
                continue;
            }
            if ( $field === 'email' ) {
                $update[ $field ] = sanitize_email( $data[ $field ] );
            } elseif ( $fmt === '%s' ) {
                $update[ $field ] = sanitize_text_field( $data[ $field ] );
            } elseif ( $fmt === '%f' ) {
                $update[ $field ] = (float) $data[ $field ];
            } else {
                $update[ $field ] = (int) $data[ $field ];
            }
            $format[] = $fmt;
        }

        if ( empty( $update ) ) {
            return false;
        }

        return (bool) $wpdb->update( self::table(), $update, array( 'id' => $id ), $format, array( '%d' ) );
    }

    /**
     * Delete a competitor.
     *
     * @param int $id
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;
        // Delete selected rolls first
        $wpdb->delete( self::selected_rolls_table(), array( 'competitor_id' => $id ), array( '%d' ) );
        return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }

    // ─── Selected Rolls ──────────────────────────────────────────

    /**
     * Get selected roll IDs for a competitor.
     *
     * @param int $competitor_id
     * @return array Array of competition_roll_id values.
     */
    public static function get_selected_rolls( $competitor_id ) {
        global $wpdb;
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT competition_roll_id FROM " . self::selected_rolls_table() . " WHERE competitor_id = %d",
                $competitor_id
            )
        );
    }

    /**
     * Set selected rolls for a competitor (replace all).
     *
     * @param int   $competitor_id
     * @param array $competition_roll_ids
     * @return int Number of rows inserted.
     */
    public static function set_selected_rolls( $competitor_id, array $competition_roll_ids ) {
        global $wpdb;
        $table = self::selected_rolls_table();

        // Remove existing selections
        $wpdb->delete( $table, array( 'competitor_id' => $competitor_id ), array( '%d' ) );

        $count = 0;
        foreach ( $competition_roll_ids as $roll_id ) {
            $result = $wpdb->insert(
                $table,
                array(
                    'competitor_id'      => (int) $competitor_id,
                    'competition_roll_id' => (int) $roll_id,
                ),
                array( '%d', '%d' )
            );
            if ( $result ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Count competitors in a competition.
     *
     * @param int $competition_id
     * @return int
     */
    public static function count_by_competition( $competition_id ) {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::table() . " WHERE competition_id = %d",
                $competition_id
            )
        );
    }
}

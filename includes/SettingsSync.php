<?php
/**
 * Syncs settings changes (classes, dates/competitions) from wp_options to custom tables.
 *
 * The Roll Settings and Classes & Dates pages still write to wp_options via
 * the WP Settings API. This class hooks into the option update to mirror
 * changes to the custom tables.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_SettingsSync {

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'update_option_competitors_options', array( __CLASS__, 'sync_on_save' ), 10, 2 );
    }

    /**
     * When competitors_options is updated, sync classes, competitions, and rolls
     * to the custom tables.
     *
     * @param mixed $old_value
     * @param mixed $new_value
     */
    public static function sync_on_save( $old_value, $new_value ) {
        if ( ! is_array( $new_value ) ) {
            return;
        }

        self::sync_classes( $new_value );
        self::sync_competitions( $new_value );
        self::sync_rolls( $new_value );
        self::refresh_unlocked_snapshots();
    }

    /**
     * After master rolls are re-seeded, rebuild competition_rolls for any
     * competition that is not locked. Locked competitions keep their existing
     * snapshot so historical scoring is preserved.
     *
     * Note: this clobbers any per-event customization on unlocked competitions.
     * If you need per-event overrides to survive, lock the competition first.
     */
    private static function refresh_unlocked_snapshots() {
        global $wpdb;

        $unlocked = $wpdb->get_results(
            "SELECT id FROM " . Competitors_Database::table( 'competitions' ) . " WHERE is_locked = 0",
            ARRAY_A
        );

        if ( empty( $unlocked ) ) {
            return;
        }

        $classes = Competitors_ClassRepository::find_all();
        $cr_table = Competitors_Database::table( 'competition_rolls' );

        foreach ( $unlocked as $comp ) {
            $comp_id = (int) $comp['id'];

            // Drop existing snapshot rows for this competition
            $wpdb->delete( $cr_table, array( 'competition_id' => $comp_id ), array( '%d' ) );

            // Re-snapshot from master for each class
            foreach ( $classes as $class ) {
                Competitors_RollRepository::snapshot_for_competition( $comp_id, (int) $class['id'] );
            }
        }
    }

    /**
     * Sync competition classes from wp_options to comp_classes table.
     */
    private static function sync_classes( $options ) {
        if ( ! isset( $options['available_competition_classes'] ) ) {
            return;
        }

        $option_classes = $options['available_competition_classes'];
        if ( ! is_array( $option_classes ) ) {
            return;
        }

        foreach ( $option_classes as $index => $class_data ) {
            if ( ! is_array( $class_data ) || empty( $class_data['name'] ) ) {
                continue;
            }

            $name    = sanitize_text_field( $class_data['name'] );
            $comment = sanitize_text_field( $class_data['comment'] ?? '' );

            $existing = Competitors_ClassRepository::find_by_name( $name );

            if ( $existing ) {
                Competitors_ClassRepository::update( (int) $existing['id'], array(
                    'comment'       => $comment,
                    'display_order' => $index + 1,
                ) );
            } else {
                Competitors_ClassRepository::create( array(
                    'name'          => $name,
                    'comment'       => $comment,
                    'display_order' => $index + 1,
                ) );
            }
        }
    }

    /**
     * Sync competition dates from wp_options to comp_competitions table.
     */
    private static function sync_competitions( $options ) {
        if ( ! isset( $options['available_competition_dates'] ) ) {
            return;
        }

        $option_events = $options['available_competition_dates'];
        if ( ! is_array( $option_events ) ) {
            return;
        }

        foreach ( $option_events as $event ) {
            if ( ! is_array( $event ) || empty( $event['date'] ) ) {
                continue;
            }

            $date = sanitize_text_field( $event['date'] );
            $name = sanitize_text_field( $event['name'] ?? $date );

            // Check if competition already exists by date
            global $wpdb;
            $existing_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM " . Competitors_Database::table( 'competitions' ) . " WHERE event_date = %s",
                $date
            ) );

            if ( $existing_id ) {
                Competitors_CompetitionRepository::update( $existing_id, array(
                    'name' => $name,
                ) );
            } else {
                Competitors_CompetitionRepository::create( array(
                    'name'       => $name,
                    'event_date' => $date,
                ) );
            }
        }
    }

    /**
     * Sync master roll definitions from wp_options to comp_rolls table.
     */
    private static function sync_rolls( $options ) {
        global $wpdb;
        $rolls_table = Competitors_Database::table( 'rolls' );

        $classes = Competitors_ClassRepository::find_all();

        foreach ( $classes as $class ) {
            $class_name = $class['name'];
            $class_id   = (int) $class['id'];

            $roll_names    = isset( $options["custom_values_{$class_name}"] ) ? $options["custom_values_{$class_name}"] : array();
            $points_values = isset( $options["numeric_values_{$class_name}"] ) ? $options["numeric_values_{$class_name}"] : array();
            $is_numeric    = isset( $options["is_numeric_field_{$class_name}"] ) ? $options["is_numeric_field_{$class_name}"] : array();
            $no_right_left = isset( $options["no_right_left_{$class_name}"] ) ? $options["no_right_left_{$class_name}"] : array();

            if ( ! is_array( $roll_names ) ) {
                continue;
            }

            // Delete existing rolls for this class and re-insert
            // (simpler than diffing individual rolls)
            $wpdb->delete( $rolls_table, array( 'class_id' => $class_id ), array( '%d' ) );

            foreach ( $roll_names as $index => $name ) {
                $name = trim( $name );
                if ( empty( $name ) ) {
                    continue;
                }

                $max_score = isset( $points_values[ $index ] ) && is_numeric( $points_values[ $index ] )
                    ? (int) $points_values[ $index ]
                    : 0;

                $wpdb->insert(
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
            }
        }
    }

    /**
     * Force a full sync now (useful for catch-up after migration).
     */
    public static function force_sync() {
        $options = get_option( 'competitors_options', array() );
        if ( ! empty( $options ) ) {
            self::sync_on_save( array(), $options );
        }
    }
}

<?php
/**
 * Plugin activation handler.
 * Creates tables and seeds default data.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Activator {

    /**
     * Run on plugin activation.
     * Creates custom tables and seeds default classes if empty.
     */
    public static function activate() {
        self::create_tables();
        self::seed_default_classes();
        self::seed_default_rolls();
    }

    /**
     * Create all custom tables via Database class.
     */
    private static function create_tables() {
        Competitors_Database::create_tables();
    }

    /**
     * Seed default competition classes if none exist.
     */
    private static function seed_default_classes() {
        global $wpdb;
        $table = Competitors_Database::table( 'classes' );

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $count > 0 ) {
            return;
        }

        $defaults = array(
            array( 'name' => 'open',         'comment' => 'Open (International participants)',              'display_order' => 1 ),
            array( 'name' => 'championship', 'comment' => 'Championship (club member and competition license holder)', 'display_order' => 2 ),
            array( 'name' => 'amateur',      'comment' => 'Motionsklass (No license needed)',               'display_order' => 3 ),
        );

        foreach ( $defaults as $row ) {
            $wpdb->insert( $table, $row, array( '%s', '%s', '%d' ) );
        }
    }

    /**
     * Seed default roll definitions from predefined-rolls.php if no master rolls exist.
     */
    private static function seed_default_rolls() {
        global $wpdb;

        $rolls_table  = Competitors_Database::table( 'rolls' );
        $class_table  = Competitors_Database::table( 'classes' );

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rolls_table}" );
        if ( $count > 0 ) {
            return;
        }

        // Load predefined rolls
        $predefined_file = dirname( __DIR__ ) . '/assets/predefined-rolls.php';
        if ( ! file_exists( $predefined_file ) ) {
            return;
        }

        $predefined_rolls = include $predefined_file;
        if ( ! is_array( $predefined_rolls ) ) {
            return;
        }

        // Get the "open" class ID — seed rolls for open class by default
        $open_class_id = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$class_table} WHERE name = %s", 'open' )
        );

        if ( ! $open_class_id ) {
            return;
        }

        foreach ( $predefined_rolls as $order => $roll ) {
            $name      = sanitize_text_field( $roll['name'] );
            $max_score = is_numeric( $roll['points'] ) ? (int) $roll['points'] : 0;

            $wpdb->insert(
                $rolls_table,
                array(
                    'class_id'      => $open_class_id,
                    'name'          => $name,
                    'max_score'     => $max_score,
                    'is_numeric'    => 0,
                    'no_right_left' => 0,
                    'display_order' => $order + 1,
                ),
                array( '%d', '%s', '%d', '%d', '%d', '%d' )
            );
        }
    }
}

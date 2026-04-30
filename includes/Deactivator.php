<?php
/**
 * Plugin deactivation handler.
 * Cleans up transients and temporary data. Does NOT drop tables.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Deactivator {

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate() {
        self::clear_transients();
        self::clear_temp_unlocks();
    }

    /**
     * Remove all plugin transients.
     */
    private static function clear_transients() {
        global $wpdb;

        // Clear competitor list caches
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_competitors_list_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_competitors_list_%'"
        );

        // Clear competitor detail caches
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_competitor_details_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_competitor_details_%'"
        );

        // Clear temp unlock transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_comp_temp_unlock_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_comp_temp_unlock_%'"
        );

        // Clear settings submitted transient
        delete_transient( 'competitors_settings_submitted' );
        delete_transient( 'competitors_scores_update_success' );
        delete_transient( 'competitors_scores_updated' );
    }

    /**
     * Revoke any active temporary competition unlocks.
     */
    private static function clear_temp_unlocks() {
        global $wpdb;

        $table = Competitors_Database::table( 'competitions' );
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( "SHOW TABLES LIKE %s", $table )
        );

        if ( ! $table_exists ) {
            return;
        }

        $competitions = $wpdb->get_col( "SELECT id FROM {$table}" );
        foreach ( $competitions as $id ) {
            delete_transient( 'comp_temp_unlock_' . $id );
        }
    }
}

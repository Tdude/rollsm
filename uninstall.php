<?php
/**
 * Fired when the plugin is uninstalled (deleted via WP admin).
 *
 * Drops all custom comp_* tables and removes plugin options.
 * The old CPT data (posts, postmeta) is left intact — WordPress
 * handles orphaned post types gracefully.
 *
 * @package Competitors
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Load the Database class for table management
require_once __DIR__ . '/includes/Database.php';

// Drop all custom tables
Competitors_Database::drop_tables();

// Remove plugin-specific options
$options_to_delete = array(
    'comp_db_version',
    'comp_migration_complete',
    'competitors_options',
    'available_competition_classes',
    'competitors_custom_values',
    'competitors_numeric_values',
    'competitors_is_numeric_field',
    'competitors_competitors_display_page_page_id',
    'competitors_competitors_display_page_page_slug',
    'competitors_competitors_thank_you_page_id',
    'competitors_competitors_thank_you_page_slug',
);

foreach ( $options_to_delete as $option ) {
    delete_option( $option );
}

// Remove roll definition snapshots
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'competitors_roll_definitions_%'"
);

// Remove all plugin transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_competitors_%' OR option_name LIKE '_transient_timeout_competitors_%'"
);
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_comp_%' OR option_name LIKE '_transient_timeout_comp_%'"
);

// Remove the judge role
remove_role( 'competitors_judge' );

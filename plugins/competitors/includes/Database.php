<?php
/**
 * Database schema management for Competitors plugin.
 * Creates and upgrades all custom tables via dbDelta().
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Database {

    const DB_VERSION = '1.0.0';
    const DB_VERSION_OPTION = 'comp_db_version';

    /**
     * Get the table name with WP prefix.
     */
    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'comp_' . $name;
    }

    /**
     * Create or update all custom tables.
     * Safe to call multiple times — dbDelta handles diffs.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables = self::get_schema( $charset_collate );

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Check if tables need upgrading.
     */
    public static function needs_upgrade() {
        $installed = get_option( self::DB_VERSION_OPTION, '0' );
        return version_compare( $installed, self::DB_VERSION, '<' );
    }

    /**
     * Drop all custom tables (used by uninstall.php).
     */
    public static function drop_tables() {
        global $wpdb;

        $table_names = array(
            'email_recipients',
            'emails',
            'timers',
            'scores',
            'selected_rolls',
            'competitors',
            'competition_rolls',
            'rolls',
            'classes',
            'competitions',
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names are hardcoded above
        foreach ( $table_names as $name ) {
            $wpdb->query( "DROP TABLE IF EXISTS " . self::table( $name ) );
        }

        delete_option( self::DB_VERSION_OPTION );
    }

    /**
     * Return array of CREATE TABLE statements.
     */
    private static function get_schema( $charset_collate ) {
        $t = array(
            'competitions'     => self::table( 'competitions' ),
            'classes'          => self::table( 'classes' ),
            'rolls'            => self::table( 'rolls' ),
            'competition_rolls'=> self::table( 'competition_rolls' ),
            'competitors'      => self::table( 'competitors' ),
            'selected_rolls'   => self::table( 'selected_rolls' ),
            'scores'           => self::table( 'scores' ),
            'timers'           => self::table( 'timers' ),
            'emails'           => self::table( 'emails' ),
            'email_recipients' => self::table( 'email_recipients' ),
        );

        // Note: dbDelta does NOT support FOREIGN KEY constraints reliably.
        // We use INDEX + application-level integrity instead.

        $sql = array();

        // 1. Competitions — replaces scattered date arrays in wp_options
        $sql[] = "CREATE TABLE {$t['competitions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL DEFAULT '',
            event_date date NOT NULL,
            slug varchar(255) NOT NULL DEFAULT '',
            is_current tinyint(1) NOT NULL DEFAULT 0,
            is_locked tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY is_current (is_current)
        ) $charset_collate;";

        // 2. Classes — open, championship, amateur, etc.
        $sql[] = "CREATE TABLE {$t['classes']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL DEFAULT '',
            comment varchar(255) NOT NULL DEFAULT '',
            display_order int NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";

        // 3. Rolls — master roll definitions per class
        $sql[] = "CREATE TABLE {$t['rolls']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            class_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(500) NOT NULL DEFAULT '',
            max_score int NOT NULL DEFAULT 0,
            is_numeric tinyint(1) NOT NULL DEFAULT 0,
            no_right_left tinyint(1) NOT NULL DEFAULT 0,
            display_order int NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY class_id (class_id)
        ) $charset_collate;";

        // 4. Competition Rolls — snapshot of roll definitions for a specific competition
        $sql[] = "CREATE TABLE {$t['competition_rolls']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            competition_id bigint(20) unsigned NOT NULL DEFAULT 0,
            class_id bigint(20) unsigned NOT NULL DEFAULT 0,
            roll_id bigint(20) unsigned NOT NULL DEFAULT 0,
            snapshot_name varchar(500) NOT NULL DEFAULT '',
            snapshot_max_score int NOT NULL DEFAULT 0,
            snapshot_is_numeric tinyint(1) NOT NULL DEFAULT 0,
            snapshot_no_right_left tinyint(1) NOT NULL DEFAULT 0,
            display_order int NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY competition_id (competition_id),
            KEY class_id (class_id),
            KEY roll_id (roll_id)
        ) $charset_collate;";

        // 5. Competitors — replaces competitors CPT + postmeta
        $sql[] = "CREATE TABLE {$t['competitors']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            competition_id bigint(20) unsigned NOT NULL DEFAULT 0,
            class_id bigint(20) unsigned NOT NULL DEFAULT 0,
            wp_post_id bigint(20) unsigned DEFAULT NULL,
            name varchar(255) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            club varchar(255) NOT NULL DEFAULT '',
            gender varchar(20) NOT NULL DEFAULT '',
            sponsors text NOT NULL,
            speaker_info text NOT NULL,
            license varchar(10) NOT NULL DEFAULT '',
            dinner varchar(10) NOT NULL DEFAULT '',
            consent varchar(10) NOT NULL DEFAULT '',
            fee decimal(10,2) NOT NULL DEFAULT 0.00,
            display_order int NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY competition_id (competition_id),
            KEY class_id (class_id),
            KEY wp_post_id (wp_post_id)
        ) $charset_collate;";

        // 6. Selected Rolls — which rolls a competitor chose to perform
        $sql[] = "CREATE TABLE {$t['selected_rolls']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            competitor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            competition_roll_id bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY competitor_id (competitor_id),
            KEY competition_roll_id (competition_roll_id)
        ) $charset_collate;";

        // 7. Scores — one row per competitor per roll
        $sql[] = "CREATE TABLE {$t['scores']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            competitor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            competition_roll_id bigint(20) unsigned NOT NULL DEFAULT 0,
            left_group tinyint(1) NOT NULL DEFAULT 0,
            right_group tinyint(1) NOT NULL DEFAULT 0,
            left_score decimal(10,2) NOT NULL DEFAULT 0.00,
            right_score decimal(10,2) NOT NULL DEFAULT 0.00,
            total_score decimal(10,2) NOT NULL DEFAULT 0.00,
            scored_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY competitor_id (competitor_id),
            KEY competition_roll_id (competition_roll_id),
            UNIQUE KEY competitor_roll (competitor_id, competition_roll_id)
        ) $charset_collate;";

        // 8. Timers — timer data per competitor
        $sql[] = "CREATE TABLE {$t['timers']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            competitor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            start_time datetime DEFAULT NULL,
            stop_time datetime DEFAULT NULL,
            elapsed_time int NOT NULL DEFAULT 0,
            total_score decimal(10,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (id),
            KEY competitor_id (competitor_id)
        ) $charset_collate;";

        // 9. Emails — replaces sent_emails CPT
        $sql[] = "CREATE TABLE {$t['emails']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subject varchar(255) NOT NULL DEFAULT '',
            content longtext NOT NULL,
            sent_by bigint(20) unsigned NOT NULL DEFAULT 0,
            sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 10. Email Recipients
        $sql[] = "CREATE TABLE {$t['email_recipients']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email_id bigint(20) unsigned NOT NULL DEFAULT 0,
            competitor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            email_address varchar(255) NOT NULL DEFAULT '',
            name varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY email_id (email_id),
            KEY competitor_id (competitor_id)
        ) $charset_collate;";

        return $sql;
    }
}

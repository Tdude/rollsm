<?php
/**
 * One-time data migration from CPT/postmeta/wp_options to custom tables.
 *
 * Reads:
 *   - competitors CPT posts + postmeta
 *   - competitors_options (classes, dates, roll definitions per class)
 *   - competitors_roll_definitions_{slug} options (snapshots)
 *   - sent_emails CPT + recipients postmeta
 *
 * Writes to wp_comp_* tables.
 *
 * Safety:
 *   - Wrapped in a transaction — rolls back on any failure
 *   - Original CPT/postmeta/wp_options data is NEVER deleted
 *   - Can be re-run safely (skips if migration already complete)
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Migration {

    const MIGRATION_OPTION = 'comp_migration_complete';

    /**
     * Check if migration has already been completed.
     *
     * @return bool
     */
    public static function is_complete() {
        return (bool) get_option( self::MIGRATION_OPTION, false );
    }

    /**
     * Run the full migration.
     *
     * @return array { success: bool, message: string, counts: array }
     */
    public static function run() {
        global $wpdb;

        if ( self::is_complete() ) {
            return array(
                'success' => true,
                'message' => 'Migration already completed.',
                'counts'  => array(),
            );
        }

        // Ensure tables exist
        Competitors_Database::create_tables();

        $counts = array(
            'competitions' => 0,
            'classes'      => 0,
            'rolls'        => 0,
            'comp_rolls'   => 0,
            'competitors'  => 0,
            'scores'       => 0,
            'emails'       => 0,
        );

        // Use a transaction for atomicity
        $wpdb->query( 'START TRANSACTION' );

        try {
            $counts['classes']      = self::migrate_classes();
            $counts['competitions'] = self::migrate_competitions();
            $counts['rolls']        = self::migrate_rolls();
            $counts['comp_rolls']   = self::migrate_competition_roll_snapshots();
            $counts['competitors']  = self::migrate_competitors();
            $counts['scores']       = self::migrate_scores();
            $counts['emails']       = self::migrate_emails();

            $wpdb->query( 'COMMIT' );
        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return array(
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
                'counts'  => $counts,
            );
        }

        update_option( self::MIGRATION_OPTION, true );

        return array(
            'success' => true,
            'message' => 'Migration completed successfully.',
            'counts'  => $counts,
        );
    }

    /**
     * Revert migration flag (allows re-running).
     */
    public static function revert() {
        delete_option( self::MIGRATION_OPTION );
    }

    // ─── Private Migration Steps ─────────────────────────────────

    /**
     * Step 1: Migrate competition classes from wp_options.
     */
    private static function migrate_classes() {
        global $wpdb;
        $table = Competitors_Database::table( 'classes' );

        // Skip if classes already have data (seeded by Activator)
        $existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $existing > 0 ) {
            return $existing;
        }

        $options = get_option( 'competitors_options', array() );
        $classes = isset( $options['available_competition_classes'] )
            ? $options['available_competition_classes']
            : get_option( 'available_competition_classes', array() );

        $count = 0;
        foreach ( $classes as $index => $class ) {
            if ( ! is_array( $class ) || empty( $class['name'] ) ) {
                continue;
            }

            $result = $wpdb->insert(
                $table,
                array(
                    'name'          => sanitize_text_field( $class['name'] ),
                    'comment'       => sanitize_text_field( $class['comment'] ?? '' ),
                    'display_order' => $index + 1,
                ),
                array( '%s', '%s', '%d' )
            );

            if ( $result ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Step 2: Migrate competition dates/events to competitions table.
     */
    private static function migrate_competitions() {
        global $wpdb;
        $table = Competitors_Database::table( 'competitions' );

        $existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $existing > 0 ) {
            return $existing;
        }

        $options = get_option( 'competitors_options', array() );
        $events  = isset( $options['available_competition_dates'] )
            ? $options['available_competition_dates']
            : array();

        if ( empty( $events ) ) {
            // Try to extract unique competition dates from competitor postmeta
            $dates = $wpdb->get_col(
                "SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key = 'competition_date' AND meta_value != ''
                 ORDER BY meta_value DESC"
            );

            foreach ( $dates as $date ) {
                $events[] = array( 'date' => $date, 'name' => $date );
            }
        }

        $count = 0;
        foreach ( $events as $index => $event ) {
            if ( ! is_array( $event ) || empty( $event['date'] ) ) {
                continue;
            }

            $date = sanitize_text_field( $event['date'] );
            $name = sanitize_text_field( $event['name'] ?? $date );
            $slug = sanitize_title( $name . '-' . $date );

            $result = $wpdb->insert(
                $table,
                array(
                    'name'       => $name,
                    'event_date' => $date,
                    'slug'       => $slug,
                    'is_current' => ( $index === 0 ) ? 1 : 0,
                    'is_locked'  => ( $index === 0 ) ? 0 : 1,
                ),
                array( '%s', '%s', '%s', '%d', '%d' )
            );

            if ( $result ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Step 3: Migrate master roll definitions from wp_options to rolls table.
     */
    private static function migrate_rolls() {
        global $wpdb;

        $rolls_table = Competitors_Database::table( 'rolls' );
        $class_table = Competitors_Database::table( 'classes' );

        $existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rolls_table}" );
        if ( $existing > 0 ) {
            return $existing;
        }

        $options = get_option( 'competitors_options', array() );
        $classes = $wpdb->get_results( "SELECT * FROM {$class_table}", ARRAY_A );

        $count = 0;

        foreach ( $classes as $class ) {
            $class_name = $class['name'];
            $class_id   = (int) $class['id'];

            $roll_names      = isset( $options["custom_values_{$class_name}"] ) ? $options["custom_values_{$class_name}"] : array();
            $points_values   = isset( $options["numeric_values_{$class_name}"] ) ? $options["numeric_values_{$class_name}"] : array();
            $is_numeric      = isset( $options["is_numeric_field_{$class_name}"] ) ? $options["is_numeric_field_{$class_name}"] : array();
            $no_right_left   = isset( $options["no_right_left_{$class_name}"] ) ? $options["no_right_left_{$class_name}"] : array();

            // If no class-specific rolls, try the generic option keys (old format)
            if ( empty( $roll_names ) && $class_name === 'open' ) {
                $roll_names    = get_option( 'competitors_custom_values', array() );
                $points_values = get_option( 'competitors_numeric_values', array() );
                $is_numeric    = get_option( 'competitors_is_numeric_field', array() );
            }

            if ( ! is_array( $roll_names ) ) {
                continue;
            }

            foreach ( $roll_names as $index => $name ) {
                $name = trim( $name );
                if ( empty( $name ) ) {
                    continue;
                }

                $max_score = isset( $points_values[ $index ] ) && is_numeric( $points_values[ $index ] )
                    ? (int) $points_values[ $index ]
                    : 0;

                $result = $wpdb->insert(
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

                if ( $result ) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Step 4: Migrate competition roll snapshots.
     * Reads from competitors_roll_definitions_{slug} options.
     */
    private static function migrate_competition_roll_snapshots() {
        global $wpdb;

        $comp_rolls_table = Competitors_Database::table( 'competition_rolls' );
        $comp_table       = Competitors_Database::table( 'competitions' );
        $class_table      = Competitors_Database::table( 'classes' );
        $rolls_table      = Competitors_Database::table( 'rolls' );

        $existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$comp_rolls_table}" );
        if ( $existing > 0 ) {
            return $existing;
        }

        // Find all roll definition snapshots in wp_options
        $snapshot_options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options}
             WHERE option_name LIKE 'competitors_roll_definitions_%'",
            ARRAY_A
        );

        $competitions = $wpdb->get_results( "SELECT * FROM {$comp_table}", ARRAY_A );
        $classes      = $wpdb->get_results( "SELECT * FROM {$class_table}", ARRAY_A );

        // Build lookup maps
        $class_map = array();
        foreach ( $classes as $c ) {
            $class_map[ $c['name'] ] = (int) $c['id'];
        }

        $comp_map = array();
        foreach ( $competitions as $comp ) {
            $comp_map[ sanitize_title( $comp['event_date'] ) ] = (int) $comp['id'];
            $comp_map[ sanitize_title( $comp['name'] . '-' . $comp['event_date'] ) ] = (int) $comp['id'];
            // Also try just the date as key
            $comp_map[ $comp['event_date'] ] = (int) $comp['id'];
        }

        $count = 0;

        foreach ( $snapshot_options as $opt ) {
            // Extract date slug from option name: competitors_roll_definitions_{slug}
            $slug = str_replace( 'competitors_roll_definitions_', '', $opt['option_name'] );
            $data = maybe_unserialize( $opt['option_value'] );

            if ( ! is_array( $data ) ) {
                continue;
            }

            // Find the competition ID for this snapshot
            $competition_id = isset( $comp_map[ $slug ] ) ? $comp_map[ $slug ] : 0;
            if ( ! $competition_id ) {
                // Try matching by date format variations
                foreach ( $comp_map as $key => $cid ) {
                    if ( sanitize_title( $key ) === $slug ) {
                        $competition_id = $cid;
                        break;
                    }
                }
            }

            if ( ! $competition_id ) {
                continue;
            }

            // $data is keyed by class name => array of rolls
            foreach ( $data as $class_name => $rolls ) {
                $class_id = isset( $class_map[ $class_name ] ) ? $class_map[ $class_name ] : 0;
                if ( ! $class_id || ! is_array( $rolls ) ) {
                    continue;
                }

                foreach ( $rolls as $order => $roll ) {
                    if ( ! is_array( $roll ) || empty( $roll['name'] ) ) {
                        continue;
                    }

                    // Try to find the matching master roll ID
                    $clean_name = preg_replace( '/^\d+\.\s*/', '', $roll['name'] );
                    $roll_id    = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM {$rolls_table} WHERE class_id = %d AND name = %s LIMIT 1",
                            $class_id,
                            $clean_name
                        )
                    );

                    $max_score = isset( $roll['max_score'] ) && is_numeric( $roll['max_score'] )
                        ? (int) $roll['max_score']
                        : 0;

                    $result = $wpdb->insert(
                        $comp_rolls_table,
                        array(
                            'competition_id'         => $competition_id,
                            'class_id'               => $class_id,
                            'roll_id'                => $roll_id,
                            'snapshot_name'          => sanitize_text_field( $roll['name'] ),
                            'snapshot_max_score'     => $max_score,
                            'snapshot_is_numeric'    => ( isset( $roll['is_numeric'] ) && $roll['is_numeric'] === 'Yes' ) ? 1 : 0,
                            'snapshot_no_right_left' => ( isset( $roll['no_right_left'] ) && $roll['no_right_left'] === 'Yes' ) ? 1 : 0,
                            'display_order'          => $order + 1,
                        ),
                        array( '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d' )
                    );

                    if ( $result ) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Step 5: Migrate competitors from CPT posts + postmeta.
     */
    private static function migrate_competitors() {
        global $wpdb;

        $table      = Competitors_Database::table( 'competitors' );
        $sel_table  = Competitors_Database::table( 'selected_rolls' );
        $comp_table = Competitors_Database::table( 'competitions' );
        $class_table = Competitors_Database::table( 'classes' );
        $comp_rolls_table = Competitors_Database::table( 'competition_rolls' );

        $existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $existing > 0 ) {
            return $existing;
        }

        // Build lookup maps
        $class_map = array();
        foreach ( $wpdb->get_results( "SELECT * FROM {$class_table}", ARRAY_A ) as $c ) {
            $class_map[ $c['name'] ] = (int) $c['id'];
        }

        $comp_date_map = array();
        foreach ( $wpdb->get_results( "SELECT * FROM {$comp_table}", ARRAY_A ) as $comp ) {
            $comp_date_map[ $comp['event_date'] ] = (int) $comp['id'];
        }

        // Get all competitor CPT posts
        $posts = get_posts( array(
            'post_type'      => 'competitors',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        $count = 0;

        foreach ( $posts as $post ) {
            $meta = get_post_meta( $post->ID );

            $participation_class = isset( $meta['participation_class'][0] ) ? $meta['participation_class'][0] : 'open';
            $competition_date    = isset( $meta['competition_date'][0] ) ? $meta['competition_date'][0] : '';
            $custom_order        = isset( $meta['_competitors_custom_order'][0] ) ? (int) $meta['_competitors_custom_order'][0] : 0;

            $class_id       = isset( $class_map[ $participation_class ] ) ? $class_map[ $participation_class ] : 0;
            $competition_id = isset( $comp_date_map[ $competition_date ] ) ? $comp_date_map[ $competition_date ] : 0;

            // If no competition was found and we have a date, pick the first one
            if ( ! $competition_id && ! empty( $comp_date_map ) ) {
                $competition_id = reset( $comp_date_map );
            }

            $result = $wpdb->insert(
                $table,
                array(
                    'competition_id' => $competition_id,
                    'class_id'       => $class_id,
                    'wp_post_id'     => $post->ID,
                    'name'           => $post->post_title,
                    'email'          => sanitize_email( $meta['email'][0] ?? '' ),
                    'phone'          => sanitize_text_field( $meta['phone'][0] ?? '' ),
                    'club'           => sanitize_text_field( $meta['club'][0] ?? '' ),
                    'gender'         => sanitize_text_field( $meta['gender'][0] ?? '' ),
                    'sponsors'       => sanitize_textarea_field( $meta['sponsors'][0] ?? '' ),
                    'speaker_info'   => sanitize_textarea_field( $meta['speaker_info'][0] ?? '' ),
                    'license'        => sanitize_text_field( $meta['license'][0] ?? '' ),
                    'dinner'         => sanitize_text_field( $meta['dinner'][0] ?? '' ),
                    'consent'        => sanitize_text_field( $meta['consent'][0] ?? '' ),
                    'fee'            => (float) ( $meta['fee'][0] ?? 0 ),
                    'display_order'  => $custom_order,
                ),
                array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d' )
            );

            if ( ! $result ) {
                continue;
            }

            $new_competitor_id = $wpdb->insert_id;
            $count++;

            // Migrate selected rolls
            $selected_rolls = maybe_unserialize( $meta['selected_rolls'][0] ?? '' );
            if ( is_array( $selected_rolls ) && ! empty( $selected_rolls ) && $competition_id ) {
                // selected_rolls are stored as roll indexes (0-based)
                // Map to competition_roll IDs
                $comp_rolls = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, display_order FROM {$comp_rolls_table}
                         WHERE competition_id = %d AND class_id = %d
                         ORDER BY display_order ASC",
                        $competition_id,
                        $class_id
                    ),
                    ARRAY_A
                );

                foreach ( $selected_rolls as $roll_index ) {
                    // Map old index to new competition_roll_id by display_order
                    $roll_index = (int) $roll_index;
                    foreach ( $comp_rolls as $cr ) {
                        if ( ( (int) $cr['display_order'] - 1 ) === $roll_index ) {
                            $wpdb->insert(
                                $sel_table,
                                array(
                                    'competitor_id'       => $new_competitor_id,
                                    'competition_roll_id' => (int) $cr['id'],
                                ),
                                array( '%d', '%d' )
                            );
                            break;
                        }
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Step 6: Migrate scores from competitor_scores postmeta.
     */
    private static function migrate_scores() {
        global $wpdb;

        $scores_table     = Competitors_Database::table( 'scores' );
        $comp_table       = Competitors_Database::table( 'competitors' );
        $comp_rolls_table = Competitors_Database::table( 'competition_rolls' );

        $existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$scores_table}" );
        if ( $existing > 0 ) {
            return $existing;
        }

        // Get all migrated competitors with their wp_post_id reference
        $competitors = $wpdb->get_results(
            "SELECT id, wp_post_id, competition_id, class_id FROM {$comp_table} WHERE wp_post_id IS NOT NULL",
            ARRAY_A
        );

        $count = 0;

        foreach ( $competitors as $comp ) {
            $raw_scores = get_post_meta( (int) $comp['wp_post_id'], 'competitor_scores', true );
            $scores     = maybe_unserialize( $raw_scores );

            if ( ! is_array( $scores ) || empty( $scores ) ) {
                continue;
            }

            // Get competition rolls for this competitor's competition + class
            $comp_rolls = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, display_order FROM {$comp_rolls_table}
                     WHERE competition_id = %d AND class_id = %d
                     ORDER BY display_order ASC",
                    (int) $comp['competition_id'],
                    (int) $comp['class_id']
                ),
                ARRAY_A
            );

            // Build index-to-id map (display_order is 1-based, score index is 0-based)
            $roll_id_map = array();
            foreach ( $comp_rolls as $cr ) {
                $roll_id_map[ (int) $cr['display_order'] - 1 ] = (int) $cr['id'];
            }

            // scores format: [roll_index => ['left_score'=>int, 'right_score'=>int, 'left_group'=>int, 'right_group'=>int, 'total_score'=>int]]
            foreach ( $scores as $roll_index => $score_data ) {
                if ( ! is_array( $score_data ) ) {
                    continue;
                }

                $competition_roll_id = isset( $roll_id_map[ (int) $roll_index ] )
                    ? $roll_id_map[ (int) $roll_index ]
                    : 0;

                if ( ! $competition_roll_id ) {
                    continue;
                }

                $result = $wpdb->insert(
                    $scores_table,
                    array(
                        'competitor_id'       => (int) $comp['id'],
                        'competition_roll_id' => $competition_roll_id,
                        'left_group'          => (int) ( $score_data['left_group'] ?? 0 ),
                        'right_group'         => (int) ( $score_data['right_group'] ?? 0 ),
                        'left_score'          => (float) ( $score_data['left_score'] ?? 0 ),
                        'right_score'         => (float) ( $score_data['right_score'] ?? 0 ),
                        'total_score'         => (float) ( $score_data['total_score'] ?? 0 ),
                    ),
                    array( '%d', '%d', '%d', '%d', '%f', '%f', '%f' )
                );

                if ( $result ) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Step 7: Migrate sent_emails CPT to emails + email_recipients tables.
     */
    private static function migrate_emails() {
        global $wpdb;

        $emails_table     = Competitors_Database::table( 'emails' );
        $recipients_table = Competitors_Database::table( 'email_recipients' );
        $comp_table       = Competitors_Database::table( 'competitors' );

        $existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$emails_table}" );
        if ( $existing > 0 ) {
            return $existing;
        }

        $email_posts = get_posts( array(
            'post_type'      => 'sent_emails',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ) );

        $count = 0;

        foreach ( $email_posts as $post ) {
            $sent_date = get_post_meta( $post->ID, 'sent_date', true );

            $result = $wpdb->insert(
                $emails_table,
                array(
                    'subject' => $post->post_title,
                    'content' => $post->post_content,
                    'sent_by' => (int) $post->post_author,
                    'sent_at' => $sent_date ? $sent_date : $post->post_date,
                ),
                array( '%s', '%s', '%d', '%s' )
            );

            if ( ! $result ) {
                continue;
            }

            $email_id = $wpdb->insert_id;
            $count++;

            // Migrate recipients
            $recipients = get_post_meta( $post->ID, 'recipients', true );
            if ( is_array( $recipients ) ) {
                foreach ( $recipients as $recipient ) {
                    // Try to find the migrated competitor ID
                    $competitor_id = 0;
                    if ( ! empty( $recipient['id'] ) ) {
                        $migrated = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT id FROM {$comp_table} WHERE wp_post_id = %d",
                                (int) $recipient['id']
                            )
                        );
                        if ( $migrated ) {
                            $competitor_id = (int) $migrated;
                        }
                    }

                    $wpdb->insert(
                        $recipients_table,
                        array(
                            'email_id'      => $email_id,
                            'competitor_id' => $competitor_id,
                            'email_address' => sanitize_email( $recipient['email'] ?? '' ),
                            'name'          => sanitize_text_field( $recipient['name'] ?? '' ),
                        ),
                        array( '%d', '%d', '%s', '%s' )
                    );
                }
            }
        }

        return $count;
    }
}

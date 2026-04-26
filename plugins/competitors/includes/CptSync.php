<?php
/**
 * Syncs competitor CPT posts to custom tables.
 *
 * During the transition period, competitors may be added via the WP CPT
 * editor. This class hooks into save_post to mirror them to comp_competitors.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_CptSync {

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'save_post_competitors', array( __CLASS__, 'sync_to_custom_table' ), 20, 2 );
    }

    /**
     * On CPT save, mirror competitor data to the custom table.
     *
     * @param int     $post_id
     * @param WP_Post $post
     */
    public static function sync_to_custom_table( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( $post->post_status !== 'publish' ) {
            return;
        }
        if ( ! Competitors_Migration::is_complete() ) {
            return;
        }

        // Check if this post is already linked in custom table
        $existing = Competitors_CompetitorRepository::find_by_wp_post_id( $post_id );

        // Resolve competition: use competition_date meta, or fall back to current
        $competition_date = get_post_meta( $post_id, 'competition_date', true );
        $competition_id   = 0;

        if ( $competition_date ) {
            global $wpdb;
            $competition_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM " . Competitors_Database::table( 'competitions' ) . " WHERE event_date = %s",
                $competition_date
            ) );
        }

        if ( ! $competition_id ) {
            // Fall back to current competition
            $current = Competitors_CompetitionRepository::find_current();
            $competition_id = $current ? (int) $current['id'] : 0;
        }

        // Resolve class
        $participation_class = get_post_meta( $post_id, 'participation_class', true );
        $class_id = 0;
        if ( $participation_class ) {
            $cls = Competitors_ClassRepository::find_by_name( $participation_class );
            $class_id = $cls ? (int) $cls['id'] : 0;
        }

        // If class not found, use the first available class
        if ( ! $class_id ) {
            $all_classes = Competitors_ClassRepository::find_all();
            $class_id = ! empty( $all_classes ) ? (int) $all_classes[0]['id'] : 0;
        }

        $data = array(
            'competition_id' => $competition_id,
            'class_id'       => $class_id,
            'name'           => $post->post_title,
            'email'          => get_post_meta( $post_id, 'email', true ),
            'phone'          => get_post_meta( $post_id, 'phone', true ),
            'club'           => get_post_meta( $post_id, 'club', true ),
            'gender'         => get_post_meta( $post_id, 'gender', true ),
            'sponsors'       => get_post_meta( $post_id, 'sponsors', true ),
            'speaker_info'   => get_post_meta( $post_id, 'speaker_info', true ),
            'license'        => get_post_meta( $post_id, 'license', true ),
            'dinner'         => get_post_meta( $post_id, 'dinner', true ),
            'consent'        => get_post_meta( $post_id, 'consent', true ),
            'fee'            => (float) get_post_meta( $post_id, 'fee', true ),
            'display_order'  => (int) get_post_meta( $post_id, '_competitors_custom_order', true ),
        );

        if ( $existing ) {
            // Update existing row
            Competitors_CompetitorRepository::update( (int) $existing['id'], $data );
        } else {
            // Create new row linked to this CPT post
            $data['wp_post_id'] = $post_id;
            Competitors_CompetitorRepository::create( $data );
        }
    }

    /**
     * Bulk sync all CPT competitors that are missing from custom tables.
     * Used as a one-time catch-up for competitors added before the sync hook existed.
     *
     * @return int Number of competitors synced.
     */
    public static function sync_all_missing() {
        $posts = get_posts( array(
            'post_type'      => 'competitors',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        $count = 0;
        foreach ( $posts as $post ) {
            $existing = Competitors_CompetitorRepository::find_by_wp_post_id( $post->ID );
            if ( ! $existing ) {
                self::sync_to_custom_table( $post->ID, $post );
                $count++;
            }
        }

        return $count;
    }
}

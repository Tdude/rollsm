<?php
/**
 * Competition lock enforcement.
 *
 * Rules:
 * - Only the current competition is editable
 * - Creating a new competition auto-locks all previous ones
 * - Locked competitions render read-only (server-side + client-side)
 * - Admin override: unlock for correction, auto-relocks after 30 min
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_CompetitionLock {

    const UNLOCK_DURATION = 1800; // 30 minutes in seconds
    const UNLOCK_TRANSIENT_PREFIX = 'comp_temp_unlock_';

    /**
     * Check if a competition is editable.
     * A competition is editable if:
     * - it is the current competition AND not locked, OR
     * - it has a temporary unlock active
     *
     * @param int $competition_id
     * @return bool
     */
    public static function is_editable( $competition_id ) {
        // Check temporary unlock first
        if ( self::has_temp_unlock( $competition_id ) ) {
            return true;
        }

        $competition = Competitors_CompetitionRepository::find_by_id( $competition_id );
        if ( ! $competition ) {
            return false;
        }

        return (bool) $competition['is_current'] && ! (bool) $competition['is_locked'];
    }

    /**
     * Check if a competition is locked (read-only).
     *
     * @param int $competition_id
     * @return bool
     */
    public static function is_locked( $competition_id ) {
        return ! self::is_editable( $competition_id );
    }

    /**
     * Temporarily unlock a competition for corrections.
     * Requires manage_options capability.
     * Auto-expires after 30 minutes.
     *
     * @param int $competition_id
     * @return bool
     */
    public static function temp_unlock( $competition_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        set_transient(
            self::UNLOCK_TRANSIENT_PREFIX . $competition_id,
            true,
            self::UNLOCK_DURATION
        );

        return true;
    }

    /**
     * Revoke a temporary unlock.
     *
     * @param int $competition_id
     * @return bool
     */
    public static function revoke_temp_unlock( $competition_id ) {
        return delete_transient( self::UNLOCK_TRANSIENT_PREFIX . $competition_id );
    }

    /**
     * Check if a temporary unlock is active.
     *
     * @param int $competition_id
     * @return bool
     */
    public static function has_temp_unlock( $competition_id ) {
        return (bool) get_transient( self::UNLOCK_TRANSIENT_PREFIX . $competition_id );
    }

    /**
     * Enforce lock on a write operation.
     * Returns a WP_Error if locked, null if editable.
     *
     * @param int $competition_id
     * @return \WP_Error|null
     */
    public static function enforce( $competition_id ) {
        if ( self::is_locked( $competition_id ) ) {
            return new \WP_Error(
                'competition_locked',
                __( 'This competition is locked. No changes can be made.', 'competitors' )
            );
        }
        return null;
    }

    /**
     * Get lock status info for a competition (for UI rendering).
     *
     * @param int $competition_id
     * @return array { is_locked, is_current, has_temp_unlock, editable }
     */
    public static function get_status( $competition_id ) {
        $competition = Competitors_CompetitionRepository::find_by_id( $competition_id );

        return array(
            'is_locked'       => $competition ? (bool) $competition['is_locked'] : true,
            'is_current'      => $competition ? (bool) $competition['is_current'] : false,
            'has_temp_unlock'  => self::has_temp_unlock( $competition_id ),
            'editable'        => self::is_editable( $competition_id ),
        );
    }
}

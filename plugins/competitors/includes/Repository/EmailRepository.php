<?php
/**
 * Repository for email history CRUD.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_EmailRepository {

    private static function emails_table() {
        return Competitors_Database::table( 'emails' );
    }

    private static function recipients_table() {
        return Competitors_Database::table( 'email_recipients' );
    }

    /**
     * Get all sent emails, most recent first.
     *
     * @param int $limit
     * @return array
     */
    public static function find_all( $limit = 50 ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::emails_table() . " ORDER BY sent_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Find an email by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::emails_table() . " WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    /**
     * Get recipients for an email.
     *
     * @param int $email_id
     * @return array
     */
    public static function get_recipients( $email_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::recipients_table() . " WHERE email_id = %d",
                $email_id
            ),
            ARRAY_A
        );
    }

    /**
     * Store a sent email with its recipients.
     *
     * @param array $email_data  { subject, content, sent_by }
     * @param array $recipients  Array of { competitor_id, email_address, name }
     * @return int|false Email ID or false.
     */
    public static function store( array $email_data, array $recipients ) {
        global $wpdb;

        $result = $wpdb->insert(
            self::emails_table(),
            array(
                'subject' => sanitize_text_field( $email_data['subject'] ),
                'content' => wp_kses_post( $email_data['content'] ),
                'sent_by' => (int) ( $email_data['sent_by'] ?? get_current_user_id() ),
            ),
            array( '%s', '%s', '%d' )
        );

        if ( ! $result ) {
            return false;
        }

        $email_id = $wpdb->insert_id;

        foreach ( $recipients as $recipient ) {
            $wpdb->insert(
                self::recipients_table(),
                array(
                    'email_id'      => $email_id,
                    'competitor_id' => (int) ( $recipient['competitor_id'] ?? 0 ),
                    'email_address' => sanitize_email( $recipient['email_address'] ?? $recipient['email'] ?? '' ),
                    'name'          => sanitize_text_field( $recipient['name'] ?? '' ),
                ),
                array( '%d', '%d', '%s', '%s' )
            );
        }

        return $email_id;
    }

    /**
     * Delete an email and its recipients.
     *
     * @param int $id
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;
        $wpdb->delete( self::recipients_table(), array( 'email_id' => $id ), array( '%d' ) );
        return (bool) $wpdb->delete( self::emails_table(), array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Count recipients for an email.
     *
     * @param int $email_id
     * @return int
     */
    public static function count_recipients( $email_id ) {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::recipients_table() . " WHERE email_id = %d",
                $email_id
            )
        );
    }
}

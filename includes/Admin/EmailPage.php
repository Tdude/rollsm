<?php
/**
 * Email sending + history page backed by custom tables.
 * Replaces: display_email_form(), display_email_history(), send_email_to_selected_competitors()
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Admin_EmailPage {

    /**
     * Render the email sending form.
     */
    public static function render_form() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_competitors' ) ) {
            wp_die( esc_html__( 'Access denied. You do not have permission to send emails.', 'competitors' ) );
        }

        // Handle form submission
        if ( isset( $_POST['send_emails'] ) ) {
            check_admin_referer( 'competitors_send_email_action' );
            self::handle_send( $_POST );
        }

        // Build recipient groups: the current event first, then past events
        // (newest first). Former competitors stay reachable so the club can
        // keep them in the loop — they consented to data storage at sign-up.
        $current = Competitors_CompetitionRepository::find_current();
        $current_id = $current ? (int) $current['id'] : 0;

        $groups = array();
        foreach ( Competitors_CompetitionRepository::find_all() as $event ) {
            $event_id = (int) $event['id'];
            $people   = Competitors_CompetitorRepository::find_by_competition( $event_id );
            if ( empty( $people ) ) {
                continue;
            }
            $groups[] = array(
                'id'         => $event_id,
                'label'      => $event['event_date'] . ' — ' . $event['name'],
                'is_current' => ( $event_id === $current_id ),
                'people'     => $people,
            );
        }
        // Current event to the top.
        usort( $groups, function ( $a, $b ) {
            return ( $b['is_current'] ? 1 : 0 ) - ( $a['is_current'] ? 1 : 0 );
        } );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Send Emails to Competitors', 'competitors' ); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'competitors_send_email_action' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="email_subject"><?php esc_html_e( 'Email Subject', 'competitors' ); ?></label></th>
                        <td><input type="text" name="email_subject" id="email_subject" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="email_content"><?php esc_html_e( 'Email Content', 'competitors' ); ?></label></th>
                        <td>
                            <?php
                            wp_editor( '', 'email_content', array(
                                'textarea_name' => 'email_content',
                                'media_buttons' => false,
                                'textarea_rows' => 10,
                            ) );
                            ?>
                            <p class="description"><?php esc_html_e( "Use {name} to include the competitor's name in the email.", 'competitors' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Select Recipients', 'competitors' ); ?></label></th>
                        <td>
                            <div class="competitors-list" style="border: 1px solid #ddd; padding: 10px; max-height: 400px; overflow-y: auto;">
                                <?php if ( ! empty( $groups ) ) : ?>
                                    <?php foreach ( $groups as $group ) : ?>
                                        <p style="margin:8px 0 4px;border-bottom:1px solid #eee;">
                                            <strong><?php echo esc_html( $group['label'] ); ?></strong>
                                            <?php if ( $group['is_current'] ) : ?>
                                                <span style="color:#00a32a;font-size:11px;"><?php esc_html_e( '(current)', 'competitors' ); ?></span>
                                            <?php else : ?>
                                                <span style="background:#dba617;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px;"><?php esc_html_e( 'former', 'competitors' ); ?></span>
                                            <?php endif; ?>
                                            <a href="#" class="select-group" style="font-size:11px;margin-left:6px;"><?php esc_html_e( 'select group', 'competitors' ); ?></a>
                                        </p>
                                        <?php foreach ( $group['people'] as $comp ) : ?>
                                            <?php if ( empty( $comp['email'] ) ) { continue; } ?>
                                            <label style="display:block;">
                                                <input type="checkbox" name="selected_competitors[]" value="<?php echo esc_attr( $comp['id'] ); ?>">
                                                <?php echo esc_html( $comp['name'] . ' (' . $comp['email'] . ')' ); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'No competitors found.', 'competitors' ); ?>
                                <?php endif; ?>
                            </div>
                            <p>
                                <a href="#" id="select-all"><?php esc_html_e( 'Select All', 'competitors' ); ?></a> |
                                <a href="#" id="deselect-all"><?php esc_html_e( 'Deselect All', 'competitors' ); ?></a>
                            </p>
                            <p class="description"><?php esc_html_e( 'Tip: the same person can appear under multiple events — duplicate email addresses are only sent to once.', 'competitors' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( esc_html__( 'Send Emails', 'competitors' ), 'primary', 'send_emails' ); ?>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#select-all').click(function(e) { e.preventDefault(); $('input[name="selected_competitors[]"]').prop('checked', true); });
            $('#deselect-all').click(function(e) { e.preventDefault(); $('input[name="selected_competitors[]"]').prop('checked', false); });
            // Select every competitor under the clicked event heading.
            $('.select-group').click(function(e) {
                e.preventDefault();
                $(this).closest('p').nextUntil('p').filter('label').find('input[type="checkbox"]').prop('checked', true);
            });
        });
        </script>
        <?php
    }

    /**
     * Handle the email sending.
     */
    private static function handle_send( $post_data ) {
        $subject    = sanitize_text_field( $post_data['email_subject'] );
        $content    = wp_kses_post( $post_data['email_content'] );
        $selected   = isset( $post_data['selected_competitors'] ) ? array_map( 'intval', $post_data['selected_competitors'] ) : array();

        $sent_count   = 0;
        $failed_count = 0;
        $recipients   = array();
        $seen_emails  = array();

        foreach ( $selected as $comp_id ) {
            $comp = Competitors_CompetitorRepository::find_by_id( $comp_id );
            if ( ! $comp || empty( $comp['email'] ) ) {
                continue;
            }

            // Dedupe by email: the same person can be selected under more than
            // one event, but should only receive the message once.
            $email_key = strtolower( trim( $comp['email'] ) );
            if ( isset( $seen_emails[ $email_key ] ) ) {
                continue;
            }
            $seen_emails[ $email_key ] = true;

            $message = str_replace( '{name}', $comp['name'], $content );
            $headers = array( 'Content-Type: text/html; charset=UTF-8' );

            if ( wp_mail( $comp['email'], $subject, $message, $headers ) ) {
                $sent_count++;
                $recipients[] = array(
                    'competitor_id' => $comp_id,
                    'email_address' => $comp['email'],
                    'name'          => $comp['name'],
                );
            } else {
                $failed_count++;
            }
        }

        // Store in custom table
        if ( ! empty( $recipients ) ) {
            Competitors_EmailRepository::store(
                array( 'subject' => $subject, 'content' => $content ),
                $recipients
            );
        }

        echo '<div class="notice notice-success"><p>';
        echo esc_html( sprintf( __( 'Emails sent: %d', 'competitors' ), $sent_count ) );
        if ( $failed_count > 0 ) {
            echo ' | ' . esc_html( sprintf( __( 'Failed: %d', 'competitors' ), $failed_count ) );
        }
        echo '</p></div>';
    }

    /**
     * Render the email history page.
     */
    public static function render_history() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_competitors' ) ) {
            wp_die( esc_html__( 'Access denied.', 'competitors' ) );
        }

        $emails = Competitors_EmailRepository::find_all( 50 );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Email History', 'competitors' ); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Subject', 'competitors' ); ?></th>
                        <th><?php esc_html_e( 'Sent Date', 'competitors' ); ?></th>
                        <th><?php esc_html_e( 'Recipients', 'competitors' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $emails ) ) : ?>
                        <?php foreach ( $emails as $email ) : ?>
                            <tr>
                                <td><?php echo esc_html( $email['subject'] ); ?></td>
                                <td><?php echo esc_html( $email['sent_at'] ); ?></td>
                                <td><?php echo esc_html( Competitors_EmailRepository::count_recipients( (int) $email['id'] ) ); ?> <?php esc_html_e( 'recipients', 'competitors' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="3"><?php esc_html_e( 'No emails sent yet.', 'competitors' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

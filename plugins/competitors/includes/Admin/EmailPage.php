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

        $competition = Competitors_CompetitionRepository::find_current();
        $competitors = $competition
            ? Competitors_CompetitorRepository::find_by_competition( (int) $competition['id'] )
            : array();

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
                                <?php if ( ! empty( $competitors ) ) : ?>
                                    <?php foreach ( $competitors as $comp ) : ?>
                                        <label>
                                            <input type="checkbox" name="selected_competitors[]" value="<?php echo esc_attr( $comp['id'] ); ?>">
                                            <?php echo esc_html( $comp['name'] . ' (' . $comp['email'] . ')' ); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <?php esc_html_e( 'No competitors found.', 'competitors' ); ?>
                                <?php endif; ?>
                            </div>
                            <p>
                                <a href="#" id="select-all"><?php esc_html_e( 'Select All', 'competitors' ); ?></a> |
                                <a href="#" id="deselect-all"><?php esc_html_e( 'Deselect All', 'competitors' ); ?></a>
                            </p>
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

        foreach ( $selected as $comp_id ) {
            $comp = Competitors_CompetitorRepository::find_by_id( $comp_id );
            if ( ! $comp || empty( $comp['email'] ) ) {
                continue;
            }

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

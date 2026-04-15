<?php
/**
 * Admin notice and AJAX handler for data migration.
 *
 * Shows a notice when migration hasn't run yet.
 * Provides a button to trigger migration via AJAX.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_MigrationAdmin {

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'admin_notices', array( __CLASS__, 'show_migration_notice' ) );
        add_action( 'wp_ajax_competitors_run_migration', array( __CLASS__, 'handle_ajax_migration' ) );
        add_action( 'wp_ajax_competitors_revert_migration', array( __CLASS__, 'handle_ajax_revert' ) );
    }

    /**
     * Show admin notice if migration hasn't been completed.
     */
    public static function show_migration_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Only show on competitors admin pages
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'competitors' ) === false ) {
            return;
        }

        if ( Competitors_Migration::is_complete() ) {
            self::render_completed_notice();
            return;
        }

        self::render_migration_notice();
    }

    /**
     * Render the "migrate now" notice.
     */
    private static function render_migration_notice() {
        $nonce = wp_create_nonce( 'competitors_migration_nonce' );
        ?>
        <div class="notice notice-warning" id="comp-migration-notice">
            <h3><?php esc_html_e( 'Competitors: Database Migration Available', 'competitors' ); ?></h3>
            <p>
                <?php esc_html_e(
                    'The Competitors plugin now uses custom database tables for better performance and data export. '
                    . 'Click the button below to migrate your existing data. Your original data will NOT be deleted.',
                    'competitors'
                ); ?>
            </p>
            <p>
                <button type="button" class="button button-primary" id="comp-run-migration"
                        data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    <?php esc_html_e( 'Migrate Data Now', 'competitors' ); ?>
                </button>
                <span id="comp-migration-status" style="margin-left: 10px;"></span>
            </p>
        </div>
        <script>
        (function() {
            var btn = document.getElementById('comp-run-migration');
            var status = document.getElementById('comp-migration-status');
            if (!btn) return;

            btn.addEventListener('click', function() {
                btn.disabled = true;
                status.textContent = '<?php echo esc_js( __( 'Migrating...', 'competitors' ) ); ?>';

                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            var c = res.data.counts;
                            var msg = '<?php echo esc_js( __( 'Migration complete!', 'competitors' ) ); ?>'
                                + ' Competitions: ' + (c.competitions || 0)
                                + ', Classes: ' + (c.classes || 0)
                                + ', Rolls: ' + (c.rolls || 0)
                                + ', Competitors: ' + (c.competitors || 0)
                                + ', Scores: ' + (c.scores || 0)
                                + ', Emails: ' + (c.emails || 0);
                            status.textContent = msg;
                            status.style.color = 'green';
                            var notice = document.getElementById('comp-migration-notice');
                            if (notice) notice.className = 'notice notice-success';
                        } else {
                            status.textContent = res.data.message || '<?php echo esc_js( __( 'Migration failed.', 'competitors' ) ); ?>';
                            status.style.color = 'red';
                            btn.disabled = false;
                        }
                    } catch(e) {
                        status.textContent = '<?php echo esc_js( __( 'Error parsing response.', 'competitors' ) ); ?>';
                        status.style.color = 'red';
                        btn.disabled = false;
                    }
                };
                xhr.onerror = function() {
                    status.textContent = '<?php echo esc_js( __( 'Network error.', 'competitors' ) ); ?>';
                    status.style.color = 'red';
                    btn.disabled = false;
                };
                xhr.send('action=competitors_run_migration&nonce=' + btn.dataset.nonce);
            });
        })();
        </script>
        <?php
    }

    /**
     * Render a small notice after migration is complete, with option to re-run.
     */
    private static function render_completed_notice() {
        // Only show on the main settings page
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'toplevel_page_competitors-settings' ) {
            return;
        }

        $nonce = wp_create_nonce( 'competitors_migration_nonce' );
        ?>
        <div class="notice notice-info is-dismissible" id="comp-migration-done-notice">
            <p>
                <?php esc_html_e( 'Data migration to custom tables is complete.', 'competitors' ); ?>
                <button type="button" class="button button-link-delete" id="comp-revert-migration"
                        data-nonce="<?php echo esc_attr( $nonce ); ?>"
                        style="margin-left: 10px;">
                    <?php esc_html_e( 'Re-run Migration', 'competitors' ); ?>
                </button>
                <span id="comp-revert-status" style="margin-left: 10px;"></span>
            </p>
        </div>
        <script>
        (function() {
            var btn = document.getElementById('comp-revert-migration');
            if (!btn) return;
            btn.addEventListener('click', function() {
                if (!confirm('<?php echo esc_js( __( 'This will clear the custom tables and re-import from the original data. Continue?', 'competitors' ) ); ?>')) return;
                btn.disabled = true;
                var status = document.getElementById('comp-revert-status');
                status.textContent = '<?php echo esc_js( __( 'Re-running...', 'competitors' ) ); ?>';

                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            location.reload();
                        } else {
                            status.textContent = res.data.message || 'Failed';
                            btn.disabled = false;
                        }
                    } catch(e) {
                        status.textContent = 'Error';
                        btn.disabled = false;
                    }
                };
                xhr.send('action=competitors_revert_migration&nonce=' + btn.dataset.nonce);
            });
        })();
        </script>
        <?php
    }

    /**
     * AJAX: Run migration.
     */
    public static function handle_ajax_migration() {
        check_ajax_referer( 'competitors_migration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $result = Competitors_Migration::run();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Revert migration flag and clear tables for re-run.
     */
    public static function handle_ajax_revert() {
        check_ajax_referer( 'competitors_migration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        // Clear all custom table data (but keep table structure)
        global $wpdb;
        $tables = array(
            'email_recipients', 'emails', 'timers', 'scores',
            'selected_rolls', 'competitors', 'competition_rolls',
            'rolls', 'classes', 'competitions',
        );
        foreach ( $tables as $name ) {
            $wpdb->query( "TRUNCATE TABLE " . Competitors_Database::table( $name ) );
        }

        Competitors_Migration::revert();

        wp_send_json_success( array( 'message' => 'Migration reverted. You can re-run it now.' ) );
    }
}

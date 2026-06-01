<?php
/**
 * Public AJAX handlers backed by custom tables.
 * Replaces: handle_competitor_form_submission(), load_competitors_list(),
 *           load_competitor_details(), get_performing_rolls()
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Ajax_PublicAjaxHandler {

    /**
     * Register AJAX hooks.
     */
    public static function init() {
        // Registration form submit
        add_action( 'wp_ajax_competitors_form_submit_v2', array( __CLASS__, 'handle_form_submit' ) );
        add_action( 'wp_ajax_nopriv_competitors_form_submit_v2', array( __CLASS__, 'handle_form_submit' ) );

        // Load competitors list (scoreboard)
        add_action( 'wp_ajax_load_competitors_list_v2', array( __CLASS__, 'handle_load_list' ) );
        add_action( 'wp_ajax_nopriv_load_competitors_list_v2', array( __CLASS__, 'handle_load_list' ) );

        // Load competitor detail view
        add_action( 'wp_ajax_load_competitor_details_v2', array( __CLASS__, 'handle_load_details' ) );
        add_action( 'wp_ajax_nopriv_load_competitor_details_v2', array( __CLASS__, 'handle_load_details' ) );

        // Dynamic rolls fieldset (when class changes)
        add_action( 'wp_ajax_get_performing_rolls_v2', array( __CLASS__, 'handle_get_rolls' ) );
        add_action( 'wp_ajax_nopriv_get_performing_rolls_v2', array( __CLASS__, 'handle_get_rolls' ) );
    }

    /**
     * Handle registration form submission.
     * Writes to comp_competitors + comp_selected_rolls.
     * Also creates a CPT post for backward compatibility.
     */
    public static function handle_form_submit() {
        if ( ! isset( $_POST['competitors_nonce'] ) ||
             ! wp_verify_nonce( $_POST['competitors_nonce'], 'competitors_nonce_action' ) ) {
            wp_send_json_error( array( 'message' => 'Error: Nonce verification failed.' ) );
            return;
        }

        // Rate limiting — max 3 registrations per IP per hour
        $ip_key   = 'comp_reg_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
        $attempts = (int) get_transient( $ip_key );
        if ( $attempts >= 3 ) {
            wp_send_json_error( array( 'message' => 'Too many registration attempts. Please try again later.' ) );
            return;
        }
        set_transient( $ip_key, $attempts + 1, 3600 );

        // Validate required fields
        $name    = sanitize_text_field( $_POST['name'] ?? '' );
        $email   = sanitize_email( $_POST['email'] ?? '' );
        $phone   = sanitize_text_field( $_POST['phone'] ?? '' );
        $consent = ( isset( $_POST['consent'] ) && $_POST['consent'] === 'yes' ) ? 'yes' : 'no';

        if ( empty( $phone ) ) {
            wp_send_json_error( array( 'message' => 'Error: Please check your phone number!' ) );
        }
        if ( ! preg_match( '/^[\p{L}\s\-\']+$/u', $name ) ) {
            wp_send_json_error( array( 'message' => 'Error: Please write your name!' ) );
        }
        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'Error: Please check the email address!' ) );
        }
        if ( $consent !== 'yes' ) {
            wp_send_json_error( array( 'message' => 'Error: You must agree to the terms to proceed.' ) );
        }

        $club              = sanitize_text_field( $_POST['club'] ?? '' );
        $address           = sanitize_text_field( $_POST['address'] ?? '' );
        $gender            = sanitize_text_field( $_POST['gender'] ?? '' );
        $sponsors          = sanitize_text_field( $_POST['sponsors'] ?? '' );
        $speaker_info      = sanitize_textarea_field( $_POST['speaker_info'] ?? '' );
        $emergency_contact = sanitize_text_field( $_POST['emergency_contact'] ?? '' );
        $special_diet      = sanitize_text_field( $_POST['special_diet'] ?? '' );
        $participation_class = sanitize_text_field( $_POST['participation_class'] ?? '' );
        $license           = isset( $_POST['license'] ) ? 'yes' : 'no';
        // Dinner is retired as a fixed yes/no field — it is now a per-event
        // custom field (a headcount, with no fee). Kept as 'no' so the legacy
        // dinner column and email path stay valid for historical data.
        $dinner            = 'no';
        $competition_date  = sanitize_text_field( $_POST['competition_date'] ?? '' );

        if ( empty( $competition_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $competition_date ) ) {
            wp_send_json_error( array( 'message' => 'Error: A valid competition date is required.' ) );
            return;
        }

        // Resolve competition and class IDs
        $competitions = Competitors_CompetitionRepository::find_all();
        $competition_id = 0;
        foreach ( $competitions as $comp ) {
            if ( $comp['event_date'] === $competition_date ) {
                $competition_id = (int) $comp['id'];
                break;
            }
        }

        if ( ! $competition_id ) {
            wp_send_json_error( array( 'message' => 'Error: Selected competition date is not available.' ) );
            return;
        }

        $class = Competitors_ClassRepository::find_by_name( $participation_class );
        $class_id = $class ? (int) $class['id'] : 0;

        global $wpdb;

        // Cross-request mutex on (competition, email, name). Stops two
        // concurrent AJAX submissions from both passing the dedupe SELECT
        // before either commits an INSERT. Without this, the
        // application-level dedupe below has a TOCTOU race: both requests
        // see "no existing row" and both create one.
        //
        // GET_LOCK is session-scoped and auto-released when the MySQL
        // connection closes at the end of the PHP request, so we never
        // strand a lock even if a fatal aborts before the explicit release.
        // Name length is capped at 64 chars in MySQL; md5 hex is 32.
        $lock_key  = 'comp_reg_' . md5( $competition_id . '|' . strtolower( $email ) . '|' . strtolower( $name ) );
        $lock_held = (int) $wpdb->get_var( $wpdb->prepare( "SELECT GET_LOCK(%s, 0)", $lock_key ) );

        if ( $lock_held !== 1 ) {
            // A concurrent request is mid-flight for the same person.
            // Treat as a duplicate-success so the user sees a friendly
            // outcome and the actual row stays single.
            wp_send_json_success( array(
                'message'      => __( 'Thanks — your registration is being processed.', 'competitors' ),
                'total_sum'    => 0,
                'redirect_url' => add_query_arg( array( 'fee' => 0 ), get_home_url() . '/competitors-thank-you' ),
            ) );
            return;
        }

        // Idempotency guard: if the same person already registered for this
        // competition in the last 30 seconds, treat the second click as a
        // no-op rather than creating a duplicate. Defensive against rapid
        // double-clicks, accidental form resubmits, and browser retries.
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM " . Competitors_Database::table( 'competitors' ) . "
             WHERE competition_id = %d AND email = %s AND name = %s
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
             LIMIT 1",
            $competition_id,
            $email,
            $name
        ), ARRAY_A );
        if ( $existing ) {
            $wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_key ) );
            wp_send_json_success( array(
                'message'      => __( 'Thanks — your registration is already in.', 'competitors' ),
                'total_sum'    => 0,
                'redirect_url' => add_query_arg( array( 'fee' => 0 ), get_home_url() . '/competitors-thank-you' ),
            ) );
        }

        // Calculate fee (class fee only — dinner is no longer charged here;
        // attendees pay the restaurant directly).
        $prices = function_exists( 'get_competitor_price_list' ) ? get_competitor_price_list() : array();
        $total_sum = 0;
        if ( isset( $prices[ $participation_class ] ) ) {
            $total_sum += $prices[ $participation_class ];
        }

        // Per-event custom fields (dinner headcount, planned rolls, distance
        // paddle, …). Built from the current event's definitions so the saved
        // values match exactly what the form rendered, and stored
        // self-describing (key+label+type+value) on the competitor row.
        $custom_defs   = class_exists( 'Competitors_CustomFieldRepository' )
            ? Competitors_CustomFieldRepository::get_for_current()
            : array();
        $custom_posted = isset( $_POST['custom'] ) && is_array( $_POST['custom'] ) ? wp_unslash( $_POST['custom'] ) : array();
        $custom_values = $custom_defs ? Competitors_CustomFieldRepository::build_values( $custom_defs, $custom_posted ) : array();
        $extra_fields_json = ! empty( $custom_values ) ? wp_json_encode( $custom_values ) : null;

        // Insert into custom table
        $competitor_id = Competitors_CompetitorRepository::create( array(
            'competition_id' => $competition_id,
            'class_id'       => $class_id,
            'name'           => $name,
            'email'          => $email,
            'phone'          => $phone,
            'club'           => $club,
            'address'        => $address,
            'gender'         => $gender,
            'sponsors'       => $sponsors,
            'speaker_info'   => $speaker_info,
            'emergency_contact' => $emergency_contact,
            'special_diet'   => $special_diet,
            'license'        => $license,
            'dinner'         => $dinner,
            'consent'        => $consent,
            'extra_fields'   => $extra_fields_json,
            'fee'            => $total_sum,
        ) );

        if ( ! $competitor_id ) {
            wp_send_json_error( array( 'message' => 'Error creating competitor record.' ) );
        }

        // Save selected rolls
        $selected_rolls = isset( $_POST['selected_rolls'] ) ? array_map( 'intval', array_keys( $_POST['selected_rolls'] ) ) : array();
        if ( ! empty( $selected_rolls ) ) {
            Competitors_CompetitorRepository::set_selected_rolls( $competitor_id, $selected_rolls );
        }

        // Also create CPT post for backward compatibility.
        //
        // Suppress CptSync for this insert: we already created the
        // comp_competitors row above and will link it to wp_post_id
        // immediately after. Without this, CptSync's save_post hook fires
        // before the link is written and creates a SECOND row, which then
        // shows up as a duplicate on the public scoreboard and admin list.
        $cpt_sync_callback = array( 'Competitors_CptSync', 'sync_to_custom_table' );
        $cpt_sync_was_hooked = has_action( 'save_post_competitors', $cpt_sync_callback );
        if ( $cpt_sync_was_hooked ) {
            remove_action( 'save_post_competitors', $cpt_sync_callback, 20 );
        }

        $wp_post_id = wp_insert_post( array(
            'post_title'  => wp_strip_all_tags( $name ),
            'post_status' => 'publish',
            'post_type'   => 'competitors',
            'meta_input'  => array(
                'email'              => $email,
                'phone'              => $phone,
                'club'               => $club,
                'address'            => $address,
                'gender'             => $gender,
                'sponsors'           => $sponsors,
                'speaker_info'       => $speaker_info,
                'emergency_contact'  => $emergency_contact,
                'special_diet'       => $special_diet,
                'participation_class'=> $participation_class,
                'license'            => $license,
                'dinner'             => $dinner,
                'consent'            => $consent,
                'competitor_scores'  => array(),
                'selected_rolls'     => $selected_rolls,
                '_competitors_custom_order' => 0,
                'competition_date'   => $competition_date,
                'extra_fields'       => $extra_fields_json,
                'fee'                => $total_sum,
            ),
        ) );

        if ( $cpt_sync_was_hooked ) {
            add_action( 'save_post_competitors', $cpt_sync_callback, 20, 2 );
        }

        // Link the custom table row to the CPT post
        if ( $wp_post_id && ! is_wp_error( $wp_post_id ) ) {
            Competitors_CompetitorRepository::update( $competitor_id, array( 'wp_post_id' => $wp_post_id ) );
        }

        // Send notification emails
        if ( function_exists( 'send_admin_email' ) ) {
            send_admin_email( $name, $email, $phone, $club, $gender, $sponsors, $participation_class, $competition_date, $total_sum, $dinner, $address, $emergency_contact, $special_diet, $custom_values );
        }
        if ( function_exists( 'send_confirmation_email' ) ) {
            send_confirmation_email( $name, $email, $competition_date, $total_sum, $dinner );
        }

        // Release the cross-request mutex before signalling success.
        // (MySQL would auto-release it on connection close anyway when the
        // PHP request ends, but explicit release keeps the lock held for
        // the minimum possible window.)
        $wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_key ) );

        wp_send_json_success( array(
            'message'      => __( 'Thanks for registering, this will be fun!', 'competitors' ),
            'total_sum'    => $total_sum,
            'redirect_url' => add_query_arg( array( 'fee' => $total_sum ), get_home_url() . '/competitors-thank-you' ),
        ) );
    }

    /**
     * Load competitors list for the public scoreboard.
     */
    public static function handle_load_list() {
        if ( ! check_ajax_referer( 'competitors_nonce_action', 'security', false ) ) {
            wp_send_json_error( array( 'message' => 'Nonce verification failed.' ) );
        }

        $selected_date   = sanitize_text_field( $_POST['date_select'] ?? '' );
        $selected_class  = sanitize_text_field( $_POST['class_select'] ?? '' );
        $selected_gender = sanitize_text_field( $_POST['gender_select'] ?? '' );

        // Find competition by date
        $competition_id = null;
        if ( $selected_date ) {
            $competitions = Competitors_CompetitionRepository::find_all();
            foreach ( $competitions as $comp ) {
                if ( $comp['event_date'] === $selected_date ) {
                    $competition_id = (int) $comp['id'];
                    break;
                }
            }
        }

        // Resolve class ID
        $class_id = null;
        if ( $selected_class ) {
            $cls = Competitors_ClassRepository::find_by_name( $selected_class );
            $class_id = $cls ? (int) $cls['id'] : null;
        }

        // If a specific date, get competitors for that competition
        if ( $competition_id ) {
            $competitors = Competitors_CompetitorRepository::find_by_competition( $competition_id, $class_id );
        } else {
            // No date filter: get all competitors across all competitions
            $all_competitions = Competitors_CompetitionRepository::find_all();
            $competitors      = array();
            foreach ( $all_competitions as $comp ) {
                $batch = Competitors_CompetitorRepository::find_by_competition( (int) $comp['id'], $class_id );
                $competitors = array_merge( $competitors, $batch );
            }
        }

        // Filter by gender
        if ( $selected_gender ) {
            $competitors = array_filter( $competitors, function ( $c ) use ( $selected_gender ) {
                return $c['gender'] === $selected_gender;
            } );
        }

        $html = Competitors_Public_Scoreboard::build_list_html( $competitors );

        wp_send_json_success( array( 'content' => $html ) );
    }

    /**
     * Load competitor detail view.
     */
    public static function handle_load_details() {
        check_ajax_referer( 'competitors_nonce_action', 'security' );

        $competitor_id = (int) ( $_POST['competitor_id'] ?? 0 );
        if ( ! $competitor_id ) {
            wp_send_json_error( array( 'message' => 'Invalid competitor ID.' ) );
        }

        ob_start();
        Competitors_Public_Scoreboard::render_detail( $competitor_id );
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * Get performing rolls fieldset when class changes in registration form.
     */
    public static function handle_get_rolls() {
        check_ajax_referer( 'competitors_nonce_action', 'security' );

        $class_type = sanitize_text_field( $_POST['class_type'] ?? '' );
        if ( empty( $class_type ) ) {
            wp_send_json_error( 'Class type not specified.' );
        }

        $html = Competitors_Public_RegistrationForm::render_rolls_fieldset( $class_type );
        wp_send_json_success( array( 'html' => $html ) );
    }
}

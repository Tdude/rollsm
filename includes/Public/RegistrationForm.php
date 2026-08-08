<?php
/**
 * Public registration form backed by custom tables.
 * Shortcode: [competitors_form_public]
 *
 * Replaces: competitors_form_html(), render_performing_rolls_fieldset(),
 *           render_competitors_date_field_public(), render_competitors_classes_field_public()
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_Public_RegistrationForm {

    /**
     * Defaults for toggleable / re-labelable registration form fields.
     * Used by the Form Settings admin page to know which fields are
     * exposed and what the fallback labels are when no override is set.
     * Adding a key here also adds it to the admin UI automatically.
     *
     * @return array<string,array{visible:int,label:string,required?:int}>
     */
    public static function field_defaults() {
        return array(
            'club' => array(
                'visible' => 1,
                'label'   => __( 'Club', 'competitors' ),
            ),
            'address' => array(
                'visible'  => 1,
                'label'    => __( 'Address', 'competitors' ),
                'required' => 1,
            ),
            'sponsors' => array(
                'visible' => 1,
                'label'   => __( 'Your Sponsors', 'competitors' ),
            ),
            'speaker_info' => array(
                'visible'  => 1,
                'label'    => __( 'Support text (ICE phone number, info about you, food preferences/allergies etc.)', 'competitors' ),
                'required' => 1,
            ),
            'emergency_contact' => array(
                'visible'  => 1,
                'label'    => __( 'Next of kin (name and phone number)', 'competitors' ),
                'required' => 1,
            ),
            'special_diet' => array(
                'visible' => 1,
                'label'   => __( 'Special dietary needs (if any)', 'competitors' ),
            ),
            'license' => array(
                'visible' => 1,
                'label'   => __( 'I have a competition license or will get one for this comp!', 'competitors' ),
            ),
            // Dinner is no longer a fixed yes/no field — it is now a
            // per-event custom field (e.g. a headcount). See the
            // "Custom Fields" admin tab and Competitors_CustomFieldRepository.
        );
    }

    /**
     * Resolve admin override + default for a single field. Returns null
     * if the key is not a toggleable field. Custom labels are honoured
     * even when empty string is stored — admin can explicitly blank a
     * label by toggling visibility off instead.
     *
     * @param string $key
     * @return array|null
     */
    public static function field_setting( $key ) {
        $defaults = self::field_defaults();
        if ( ! isset( $defaults[ $key ] ) ) {
            return null;
        }
        $opts   = get_option( 'competitors_options', array() );
        $stored = isset( $opts['form_field_settings'][ $key ] ) && is_array( $opts['form_field_settings'][ $key ] )
            ? $opts['form_field_settings'][ $key ]
            : array();

        $merged = array_merge( $defaults[ $key ], $stored );
        // Treat blank custom label as "use default".
        if ( isset( $stored['label'] ) && trim( (string) $stored['label'] ) === '' ) {
            $merged['label'] = $defaults[ $key ]['label'];
        }
        $merged['visible'] = ! empty( $merged['visible'] ) ? 1 : 0;
        if ( isset( $merged['required'] ) ) {
            $merged['required'] = ! empty( $merged['required'] ) ? 1 : 0;
        }
        return $merged;
    }

    /**
     * Render the registration form shortcode.
     *
     * @return string
     */
    public static function render() {
        ob_start();
        ?>
        <form id="competitors-registration-form" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post">
            <input type="hidden" name="action" value="competitors_form_submit_v2">

            <?php /* Honeypot: real users never see or tab to this field. Left
                     in normal layout flow (not display:none) so bots that
                     specifically skip hidden fields still fill it in. Any
                     value here means the submit is treated as spam — see
                     Competitors_Ajax_PublicAjaxHandler::handle_form_submit(). */ ?>
            <div class="comp-hp-field" aria-hidden="true">
                <label for="comp_hp_confirm">Leave this field empty</label>
                <input type="text" id="comp_hp_confirm" name="comp_hp_confirm" tabindex="-1" autocomplete="off">
            </div>

            <h2 id="registration"><?php esc_html_e( 'Registration', 'competitors' ); ?></h2>
            <p><?php echo wp_kses_post( __( 'Remember to submit your registration at the <a href="#submitbutton-anchor">bottom of the page</a>. Fields marked with an asterisk (<span class="text-danger"> * </span>) are mandatory.', 'competitors' ) ); ?></p>

            <fieldset>
                <legend><?php esc_html_e( 'Personal Info', 'competitors' ); ?></legend>
                <label for="name"><?php esc_html_e( 'Name', 'competitors' ); ?> <span class="text-danger">*</span></label>
                <input aria-label="Name" type="text" id="name" name="name"><br>
                <label for="email"><?php esc_html_e( 'Email', 'competitors' ); ?> <span class="text-danger">*</span></label>
                <input aria-label="Email" type="text" id="email" name="email"><br>
                <label for="phone"><?php esc_html_e( 'Phone', 'competitors' ); ?> <span class="text-danger">*</span></label>
                <input aria-label="Phone" type="text" id="phone" name="phone"><br>
                <?php $club = self::field_setting( 'club' ); if ( $club && $club['visible'] ) : ?>
                    <label for="club"><?php echo wp_kses_post( $club['label'] ); ?></label>
                    <input aria-label="Club" type="text" id="club" name="club"><br>
                <?php endif; ?>
                <?php $address = self::field_setting( 'address' ); if ( $address && $address['visible'] ) : ?>
                    <label for="address"><?php echo wp_kses_post( $address['label'] ); ?><?php if ( ! empty( $address['required'] ) ) : ?> <span class="text-danger">*</span><?php endif; ?></label>
                    <input aria-label="Address" type="text" id="address" name="address"<?php echo ! empty( $address['required'] ) ? ' data-required="1"' : ''; ?>><br>
                <?php endif; ?>

                <div class="extra-visible gender-container">
                    <label><?php esc_html_e( 'Gender', 'competitors' ); ?> <span class="text-danger">*</span></label><br>
                    <input aria-label="Gender: Woman" type="radio" id="gender_woman" name="gender" value="woman" required>
                    <label for="gender_woman"><?php esc_html_e( 'Woman', 'competitors' ); ?></label>
                    <input aria-label="Gender: Man" type="radio" id="gender_man" name="gender" value="man">
                    <label for="gender_man"><?php esc_html_e( 'Man', 'competitors' ); ?></label>
                </div>

                <?php $sponsors = self::field_setting( 'sponsors' ); if ( $sponsors && $sponsors['visible'] ) : ?>
                    <label for="sponsors"><?php echo wp_kses_post( $sponsors['label'] ); ?></label>
                    <input aria-label="Sponsors" type="text" id="sponsors" name="sponsors"><br>
                <?php endif; ?>

                <?php $speaker = self::field_setting( 'speaker_info' ); if ( $speaker && $speaker['visible'] ) : ?>
                    <label for="speaker_info"><?php echo wp_kses_post( $speaker['label'] ); ?><?php if ( ! empty( $speaker['required'] ) ) : ?> <span class="text-danger">*</span><?php endif; ?></label>
                    <textarea aria-label="Speaker Info" id="speaker_info" name="speaker_info"<?php echo ! empty( $speaker['required'] ) ? ' data-required="1"' : ''; ?>></textarea><br>
                <?php endif; ?>

                <?php $kin = self::field_setting( 'emergency_contact' ); if ( $kin && $kin['visible'] ) : ?>
                    <label for="emergency_contact"><?php echo wp_kses_post( $kin['label'] ); ?><?php if ( ! empty( $kin['required'] ) ) : ?> <span class="text-danger">*</span><?php endif; ?></label>
                    <input aria-label="Next of kin" type="text" id="emergency_contact" name="emergency_contact"<?php echo ! empty( $kin['required'] ) ? ' data-required="1"' : ''; ?>><br>
                <?php endif; ?>

                <?php $diet = self::field_setting( 'special_diet' ); if ( $diet && $diet['visible'] ) : ?>
                    <label for="special_diet"><?php echo wp_kses_post( $diet['label'] ); ?><?php if ( ! empty( $diet['required'] ) ) : ?> <span class="text-danger">*</span><?php endif; ?></label>
                    <input aria-label="Special diet" type="text" id="special_diet" name="special_diet"<?php echo ! empty( $diet['required'] ) ? ' data-required="1"' : ''; ?>><br>
                <?php endif; ?>

                <?php echo self::render_date_field(); ?>
                <?php echo self::render_class_field(); ?>

                <?php $license = self::field_setting( 'license' ); if ( $license && $license['visible'] ) : ?>
                    <div class="extra-visible" id="license-container">
                        <input aria-label="License agreement" type="checkbox" id="license" name="license">
                        <label for="license"><?php echo wp_kses_post( $license['label'] ); ?></label>
                    </div>
                <?php endif; ?>

                <?php echo self::render_custom_fields(); ?>

                <div class="extra-visible" id="consent-container">
                    <input aria-label="Consent" type="checkbox" id="consent" name="consent" value="yes" required>
                    <label for="consent"><?php echo wp_kses_post( __( 'I agree <span class="text-danger"> * </span> for you to save my data, publish results, photos, etc.', 'competitors' ) ); ?></label>
                </div>
            </fieldset>

            <div id="performing-rolls-container">
                <?php echo self::render_rolls_fieldset(); ?>
            </div>

            <div id="validation-message" class="hidden alert danger">
                <span class="closebtn">&times;</span>
                <strong><span class="mega-text">\(o_o)/</span></strong> <span class="message-content"></span>
            </div>

            <a name="submitbutton-anchor"></a>
            <input type="submit" value="<?php esc_attr_e( 'Submit', 'competitors' ); ?>" id="submit-button" class="button button-success">
            <?php wp_nonce_field( 'competitors_nonce_action', 'competitors_nonce' ); ?>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render competition date dropdown from custom tables.
     */
    private static function render_date_field() {
        $competitions = Competitors_CompetitionRepository::find_all();

        // Pre-select the current competition's date so most registrants
        // don't have to touch the dropdown.
        $current      = Competitors_CompetitionRepository::find_current();
        $current_date = $current ? $current['event_date'] : '';

        ob_start();
        ?>
        <div class="mb-3">
            <label for="competition_date"><?php esc_html_e( 'Select date', 'competitors' ); ?> <span class="text-danger"> * </span></label>
            <select id="competition_date" name="competition_date">
                <option value=""><?php esc_html_e( 'Please select a date', 'competitors' ); ?></option>
                <?php foreach ( $competitions as $comp ) : ?>
                    <?php if ( ! (bool) $comp['is_locked'] ) : ?>
                        <option value="<?php echo esc_attr( $comp['event_date'] ); ?>" <?php selected( $current_date, $comp['event_date'] ); ?>>
                            <?php echo esc_html( $comp['event_date'] . ' - ' . $comp['name'] ); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the current event's custom fields (per-event extras such as
     * dinner headcount, planned rolls, distance paddle). Definitions come
     * from the Custom Fields admin tab; inputs are named custom[<key>].
     *
     * @return string
     */
    public static function render_custom_fields() {
        if ( ! class_exists( 'Competitors_CustomFieldRepository' ) ) {
            return '';
        }
        $defs = Competitors_CustomFieldRepository::get_for_current();
        if ( empty( $defs ) ) {
            return '';
        }

        ob_start();
        foreach ( $defs as $def ) {
            $key      = $def['key'];
            $id       = 'custom_' . $key;
            $name     = 'custom[' . $key . ']';
            $required = ! empty( $def['required'] );
            $req_attr = $required ? ' data-required="1"' : '';
            ?>
            <div class="extra-visible custom-field custom-field-<?php echo esc_attr( $def['type'] ); ?>">
                <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $def['label'] ); ?><?php if ( $required ) : ?> <span class="text-danger">*</span><?php endif; ?></label>
                <?php
                switch ( $def['type'] ) {
                    case 'textarea':
                        ?><textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $req_attr; // phpcs:ignore ?>></textarea><?php
                        break;
                    case 'number':
                        ?><input type="number" min="0" step="1" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $req_attr; // phpcs:ignore ?>><?php
                        break;
                    case 'yesno':
                        ?>
                        <select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $req_attr; // phpcs:ignore ?>>
                            <option value=""><?php esc_html_e( 'Please select', 'competitors' ); ?></option>
                            <option value="yes"><?php esc_html_e( 'Yes', 'competitors' ); ?></option>
                            <option value="no"><?php esc_html_e( 'No', 'competitors' ); ?></option>
                        </select>
                        <?php
                        break;
                    case 'select':
                        ?>
                        <select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $req_attr; // phpcs:ignore ?>>
                            <option value=""><?php esc_html_e( 'Please select', 'competitors' ); ?></option>
                            <?php foreach ( (array) $def['options'] as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                        break;
                    default: // text
                        ?><input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $req_attr; // phpcs:ignore ?>><?php
                }
                ?>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Render class radio buttons from custom tables.
     */
    private static function render_class_field() {
        $classes = Competitors_ClassRepository::find_all();

        ob_start();
        ?>
        <div id="participation-class-container">
            <label><?php esc_html_e( 'Participation in Class', 'competitors' ); ?> <span class="text-danger">*</span></label><br>
            <?php foreach ( $classes as $class ) : ?>
                <input aria-label="Participation Class - <?php echo esc_attr( $class['name'] ); ?>"
                       type="radio" id="<?php echo esc_attr( $class['name'] ); ?>"
                       name="participation_class" value="<?php echo esc_attr( $class['name'] ); ?>">
                <label for="<?php echo esc_attr( $class['name'] ); ?>"><?php echo esc_html( $class['comment'] ?: $class['name'] ); ?></label><br>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the performing rolls checkboxes.
     * Defaults to the first class (open).
     *
     * @param string $class_name
     * @return string
     */
    public static function render_rolls_fieldset( $class_name = 'open' ) {
        $class = Competitors_ClassRepository::find_by_name( $class_name );
        if ( ! $class ) {
            $classes = Competitors_ClassRepository::find_all();
            $class   = ! empty( $classes ) ? $classes[0] : null;
        }

        if ( ! $class ) {
            return '<p>' . esc_html__( 'No rolls configured.', 'competitors' ) . '</p>';
        }

        $rolls = Competitors_RollRepository::find_by_class( (int) $class['id'] );

        ob_start();
        ?>
        <fieldset>
            <legend><?php esc_html_e( 'Performing Rolls', 'competitors' ); ?></legend>
            <table>
                <tr>
                    <th>
                        <input type="checkbox" id="check_all" title="<?php esc_attr_e( 'Uncheck or check all boxes', 'competitors' ); ?>" checked />
                        <label for="check_all"><?php esc_html_e( 'All', 'competitors' ); ?></label>
                    </th>
                    <th><?php esc_html_e( 'Name of roll or maneuver. Uncheck the rolls you don\'t want to perform.', 'competitors' ); ?></th>
                </tr>
                <?php foreach ( $rolls as $index => $roll ) :
                    $max   = (int) $roll['max_score'];
                    $label = ( $max === 0 ) ? 'N/A' : esc_html( $max ) . ' points';
                ?>
                    <tr class="clickable-row">
                        <td><input type="checkbox" class="roll-checkbox" checked id="roll_<?php echo esc_attr( $roll['id'] ); ?>" name="selected_rolls[<?php echo esc_attr( $roll['id'] ); ?>]"></td>
                        <td><?php echo esc_html( $roll['name'] ); ?> (<?php echo $label; ?>)</td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </fieldset>
        <?php
        return ob_get_clean();
    }
}

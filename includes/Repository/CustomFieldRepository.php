<?php
/**
 * Per-event custom registration fields.
 *
 * Definitions are stored per competition under
 *   competitors_options['custom_fields'][<competition_id>]
 * as an ordered list of:
 *   array{ key:string, label:string, type:string, required:int, options:string[] }
 *
 * Submitted VALUES are stored self-describing on the competitor row
 * (comp_competitors.extra_fields, JSON) as an ordered list of:
 *   array{ key:string, label:string, type:string, value:string }
 * Storing the label + type alongside the value freezes how the answer
 * was presented, so editing definitions next season never rewrites a
 * past competitor's data — same principle as the roll snapshots.
 *
 * @package Competitors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Competitors_CustomFieldRepository {

    /** Supported field types. */
    const TYPES = array( 'text', 'textarea', 'number', 'yesno', 'select' );

    /**
     * Get the definitions for a specific competition.
     *
     * @param int $competition_id
     * @return array<int,array>
     */
    public static function get_for_competition( $competition_id ) {
        $opts = get_option( 'competitors_options', array() );
        $all  = isset( $opts['custom_fields'] ) && is_array( $opts['custom_fields'] ) ? $opts['custom_fields'] : array();
        $defs = isset( $all[ (int) $competition_id ] ) && is_array( $all[ (int) $competition_id ] )
            ? $all[ (int) $competition_id ]
            : array();
        return array_values( $defs );
    }

    /**
     * Get the definitions for the current competition (the one being
     * registered for this season). Returns [] if there is no current
     * competition or it has no custom fields.
     *
     * @return array<int,array>
     */
    public static function get_for_current() {
        if ( ! class_exists( 'Competitors_CompetitionRepository' ) ) {
            return array();
        }
        $current = Competitors_CompetitionRepository::find_current();
        if ( ! $current ) {
            return array();
        }
        return self::get_for_competition( (int) $current['id'] );
    }

    /**
     * Persist the definitions for a competition. Input is the raw row
     * list from the admin form; rows without a usable key+label are
     * dropped and keys are de-duplicated (first wins).
     *
     * @param int   $competition_id
     * @param array $rows
     * @return void
     */
    public static function save_for_competition( $competition_id, array $rows ) {
        $clean = self::sanitize_definitions( $rows );

        $opts = get_option( 'competitors_options', array() );
        if ( ! isset( $opts['custom_fields'] ) || ! is_array( $opts['custom_fields'] ) ) {
            $opts['custom_fields'] = array();
        }
        $opts['custom_fields'][ (int) $competition_id ] = $clean;

        update_option( 'competitors_options', $opts );
    }

    /**
     * Sanitize a raw list of definition rows into storable form.
     *
     * @param array $rows
     * @return array<int,array>
     */
    public static function sanitize_definitions( array $rows ) {
        $clean = array();
        $seen  = array();

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
            $key   = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';

            // Derive a key from the label when the admin left it blank.
            if ( $key === '' && $label !== '' ) {
                $key = sanitize_key( str_replace( '-', '_', sanitize_title( $label ) ) );
            }
            // A row with neither key nor label is an empty spare row — skip.
            if ( $key === '' || $label === '' ) {
                continue;
            }
            if ( isset( $seen[ $key ] ) ) {
                continue; // de-dupe: first occurrence wins
            }
            $seen[ $key ] = true;

            $type = isset( $row['type'] ) && in_array( $row['type'], self::TYPES, true ) ? $row['type'] : 'text';

            $options = array();
            if ( $type === 'select' && ! empty( $row['options'] ) ) {
                // Accept options as a comma-separated string (from the admin
                // form) or an already-split array (when re-sanitizing stored
                // defs), so this method is idempotent.
                $raw_opts = is_array( $row['options'] ) ? $row['options'] : explode( ',', (string) $row['options'] );
                foreach ( $raw_opts as $opt ) {
                    $opt = sanitize_text_field( trim( (string) $opt ) );
                    if ( $opt !== '' ) {
                        $options[] = $opt;
                    }
                }
            }

            $clean[] = array(
                'key'      => $key,
                'label'    => $label,
                'type'     => $type,
                'required' => empty( $row['required'] ) ? 0 : 1,
                'options'  => $options,
            );
        }

        return $clean;
    }

    /**
     * Sanitize a single submitted value according to its field type.
     *
     * @param string $type
     * @param mixed  $raw
     * @return string
     */
    public static function sanitize_value( $type, $raw ) {
        switch ( $type ) {
            case 'number':
                $raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
                if ( $raw === '' ) {
                    return '';
                }
                // Keep it a clean integer count; negatives clamped to 0.
                return (string) max( 0, (int) $raw );
            case 'yesno':
                return ( $raw === 'yes' ) ? 'yes' : ( $raw === 'no' ? 'no' : '' );
            case 'textarea':
                return sanitize_textarea_field( is_scalar( $raw ) ? $raw : '' );
            default:
                return sanitize_text_field( is_scalar( $raw ) ? $raw : '' );
        }
    }

    /**
     * Build the self-describing value list for a competitor from POSTed
     * data, given the competition's definitions.
     *
     * @param array $defs  Definitions from get_for_competition().
     * @param array $posted Raw $_POST['custom'] array.
     * @return array<int,array{key:string,label:string,type:string,value:string}>
     */
    public static function build_values( array $defs, array $posted ) {
        $out = array();
        foreach ( $defs as $def ) {
            $raw = isset( $posted[ $def['key'] ] ) ? $posted[ $def['key'] ] : '';
            $out[] = array(
                'key'   => $def['key'],
                'label' => $def['label'],
                'type'  => $def['type'],
                'value' => self::sanitize_value( $def['type'], $raw ),
            );
        }
        return $out;
    }

    /**
     * Decode the stored extra_fields JSON to an array (defensive).
     *
     * @param string|null $json
     * @return array<int,array>
     */
    public static function decode_values( $json ) {
        if ( empty( $json ) ) {
            return array();
        }
        $decoded = json_decode( $json, true );
        return is_array( $decoded ) ? $decoded : array();
    }

    /**
     * Render stored values as a compact "Label: value" string for admin
     * lists and emails. Skips blank answers.
     *
     * @param string|null $json
     * @param string      $sep
     * @return string
     */
    public static function values_to_text( $json, $sep = '; ' ) {
        $parts = array();
        foreach ( self::decode_values( $json ) as $v ) {
            $value = isset( $v['value'] ) ? (string) $v['value'] : '';
            if ( $value === '' ) {
                continue;
            }
            $label = isset( $v['label'] ) ? (string) $v['label'] : ( $v['key'] ?? '' );
            $parts[] = $label . ': ' . $value;
        }
        return implode( $sep, $parts );
    }

    /**
     * Suggested starter fields for the 2026 event. Used to pre-fill the
     * admin editor when a competition has no custom fields yet — the
     * admin reviews and clicks Save to persist them.
     *
     * @return array<int,array>
     */
    public static function default_2026_seed() {
        return array(
            array(
                'key'      => 'dinner_count',
                'label'    => __( 'Number for the dinner (including yourself and any companions)', 'competitors' ),
                'type'     => 'number',
                'required' => 0,
                'options'  => array(),
            ),
            array(
                'key'      => 'planned_rolls',
                'label'    => __( 'How many rolls do you plan to do?', 'competitors' ),
                'type'     => 'number',
                'required' => 0,
                'options'  => array(),
            ),
            array(
                'key'      => 'distance_paddle',
                'label'    => __( 'Will you do a distance paddle (up and down)?', 'competitors' ),
                'type'     => 'yesno',
                'required' => 0,
                'options'  => array(),
            ),
        );
    }
}

<?php
/**
 * Plugin Name: RollSM One-Shot — Rescue 2025 Junior Competitors
 * Description: Restores the 'junior' class as it was used in 2025, reattaches
 *              competitors Rumer Fenn (post 2277) and Idun Weidmert (post 2271)
 *              to it, snapshots the historically judged 37-roll Junior set
 *              into comp_competition_rolls, then imports their scores from
 *              the original competitor_scores postmeta. Kaia Weidmert is
 *              left untouched (already correct in championship).
 *              DELETE THIS FILE AFTER A SUCCESSFUL RUN.
 *
 * HOW TO USE
 *   1. Copy this file to /wp-content/mu-plugins/
 *   2. As an admin (user ID 1), visit:
 *        /wp-admin/?rescue_2025=1&dry_run=1   ← preview only, no writes
 *        /wp-admin/?rescue_2025=1              ← actually run
 *   3. Read the result page, then `rm` this file.
 *
 * WHAT IT DOES (in order)
 *   1. Ensures comp_classes has the 'junior' slug. INSERTs if missing.
 *   2. For each (post_id → 'junior') in the mapping, sets comp_competitors.class_id
 *      ONLY when currently 0. Refuses to overwrite existing assignments.
 *   3. Reads the legacy option competitors_roll_definitions_2025-09-13,
 *      extracts the 'junior' subarray (37 rolls), snapshots them into
 *      comp_competition_rolls for (competition_id=2, class_id=junior).
 *      Skips entirely if the snapshot already exists.
 *   4. Calls Competitors_MigrationRescue::run() to import scores from
 *      competitor_scores postmeta into comp_scores for any competitor
 *      still missing scores.
 *
 * DESIGN NOTES
 *   - Idempotent at every step; re-running is a no-op.
 *   - Does NOT touch wp_options. The 'junior' class lives only in
 *     comp_classes — it will appear in scoreboard filters (correct, since
 *     2025 results exist for it) but will also appear in the public
 *     registration form's class radios (side-effect to address as part of
 *     the SettingsSync deletion fix later).
 *   - Does NOT seed master comp_rolls for junior. Only the historical
 *     2025 per-event snapshot is materialised — keeps the rescue scoped
 *     to display correctness without affecting 2026+ planning.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const ROLLSM_RESCUE_LEGACY_OPTION = 'competitors_roll_definitions_2025-09-13';
const ROLLSM_RESCUE_LEGACY_KEY    = 'junior';
const ROLLSM_RESCUE_TARGET_COMP   = 2;
const ROLLSM_RESCUE_TARGET_SLUG   = 'junior';
const ROLLSM_RESCUE_TARGET_LABEL  = 'Junior';
const ROLLSM_RESCUE_TARGET_ORDER  = 5;

add_action( 'admin_init', function () {
    if ( empty( $_GET['rescue_2025'] ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) || get_current_user_id() !== 1 ) {
        wp_die( 'Insufficient privileges.' );
    }
    if ( ! class_exists( 'Competitors_MigrationRescue' ) || ! class_exists( 'Competitors_Database' ) ) {
        wp_die( 'Competitors plugin not active. Activate it before running the rescue.' );
    }

    $dry_run = ! empty( $_GET['dry_run'] );

    // wp_post_id → target slug. Both originally had participation_class='junior'.
    $reclassify_by_post_id = array(
        2277 => ROLLSM_RESCUE_TARGET_SLUG,  // Rumer Fenn
        2271 => ROLLSM_RESCUE_TARGET_SLUG,  // Idun Weidmert
    );

    rollsm_run_rescue_2025( $reclassify_by_post_id, $dry_run );
});

function rollsm_run_rescue_2025( array $reclassify_by_post_id, bool $dry_run ) {
    global $wpdb;

    $competitors_table = Competitors_Database::table( 'competitors' );
    $classes_table     = Competitors_Database::table( 'classes' );
    $scores_table      = Competitors_Database::table( 'scores' );
    $cr_table          = Competitors_Database::table( 'competition_rolls' );

    $report = array(
        'mode'              => $dry_run ? 'DRY RUN (no writes)' : 'LIVE',
        'class_ensure'      => null,
        'class_updates'     => array(),
        'class_update_skips'=> array(),
        'snapshot'          => null,
        'rescue_result'     => null,
    );

    // ─── Step 1: ensure 'junior' class exists. ────────────────────────────
    $junior_class_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$classes_table} WHERE name = %s LIMIT 1",
        ROLLSM_RESCUE_TARGET_SLUG
    ) );

    if ( $junior_class_id ) {
        $report['class_ensure'] = "comp_classes.{$junior_class_id} '" . ROLLSM_RESCUE_TARGET_SLUG . "' already exists, leaving as-is";
    } else {
        if ( $dry_run ) {
            $report['class_ensure'] = "would INSERT comp_classes (name='" . ROLLSM_RESCUE_TARGET_SLUG
                . "', comment='" . ROLLSM_RESCUE_TARGET_LABEL
                . "', display_order=" . ROLLSM_RESCUE_TARGET_ORDER . ")";
            // Dry-run cannot resolve the new id; later steps will report 'would …'.
        } else {
            $ok = $wpdb->insert(
                $classes_table,
                array(
                    'name'          => ROLLSM_RESCUE_TARGET_SLUG,
                    'comment'       => ROLLSM_RESCUE_TARGET_LABEL,
                    'display_order' => ROLLSM_RESCUE_TARGET_ORDER,
                ),
                array( '%s', '%s', '%d' )
            );
            if ( $ok === false ) {
                wp_die( 'INSERT comp_classes failed: ' . esc_html( $wpdb->last_error ) );
            }
            $junior_class_id = (int) $wpdb->insert_id;
            $report['class_ensure'] = "INSERTed comp_classes.{$junior_class_id} '" . ROLLSM_RESCUE_TARGET_SLUG . "'";
        }
    }

    // ─── Step 2: reclassify the two competitors. ──────────────────────────
    foreach ( $reclassify_by_post_id as $post_id => $slug ) {
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, competition_id, class_id, wp_post_id
             FROM {$competitors_table}
             WHERE wp_post_id = %d LIMIT 1",
            $post_id
        ), ARRAY_A );

        if ( ! $row ) {
            $report['class_update_skips'][] = "post_id={$post_id}: no comp_competitors row";
            continue;
        }

        $current_class = (int) $row['class_id'];

        if ( $current_class !== 0 ) {
            $report['class_update_skips'][] = sprintf(
                'post_id=%d (%s): class_id already %d, refusing to overwrite',
                $post_id, $row['name'], $current_class
            );
            continue;
        }

        $existing_scores = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$scores_table} WHERE competitor_id = %d",
            (int) $row['id']
        ) );

        $report['class_updates'][] = sprintf(
            'comp_competitors id=%d (%s, post_id=%d): class_id 0 → %s (%s)%s',
            (int) $row['id'],
            $row['name'],
            $post_id,
            $dry_run ? '<new junior>' : (string) $junior_class_id,
            $slug,
            $existing_scores > 0 ? " [WARN: already has {$existing_scores} scores]" : ''
        );

        if ( $dry_run || ! $junior_class_id ) {
            continue;
        }

        $updated = $wpdb->update(
            $competitors_table,
            array( 'class_id' => $junior_class_id ),
            array( 'id' => (int) $row['id'] ),
            array( '%d' ),
            array( '%d' )
        );
        if ( $updated === false ) {
            wp_die( 'UPDATE failed for competitor id=' . (int) $row['id'] . ': ' . esc_html( $wpdb->last_error ) );
        }
    }

    // ─── Step 3: snapshot the historical Junior roll set. ─────────────────
    $report['snapshot'] = rollsm_snapshot_junior_for_2025(
        $cr_table,
        $junior_class_id,
        $dry_run
    );

    // ─── Step 4: import scores from postmeta. ─────────────────────────────
    if ( $dry_run ) {
        $report['rescue_result'] = 'skipped (dry run)';
    } else {
        $report['rescue_result'] = Competitors_MigrationRescue::run();
    }

    // ─── Post-state snapshot for the targeted rows. ───────────────────────
    $post_ids_csv = implode( ',', array_map( 'intval', array_keys( $reclassify_by_post_id ) ) );
    $report['post_state'] = $wpdb->get_results(
        "SELECT c.id, c.name, c.wp_post_id, c.competition_id, c.class_id, c.gender,
                (SELECT COUNT(*) FROM {$scores_table} s WHERE s.competitor_id = c.id) AS score_count,
                (SELECT COALESCE(SUM(s.total_score), 0) FROM {$scores_table} s WHERE s.competitor_id = c.id) AS total_score
         FROM {$competitors_table} c
         WHERE c.wp_post_id IN ({$post_ids_csv})",
        ARRAY_A
    );

    wp_die(
        '<h1>RollSM Rescue 2025 — Result</h1><pre>'
        . esc_html( print_r( $report, true ) )
        . '</pre><p>Delete this mu-plugin file once you are satisfied.</p>',
        'Rescue Result',
        array( 'response' => 200 )
    );
}

/**
 * Reads the legacy roll-definitions option, extracts the 'junior' subarray,
 * and snapshots each roll into comp_competition_rolls for the rescue's
 * target (competition, class) tuple.
 *
 * @return array Report fragment.
 */
function rollsm_snapshot_junior_for_2025( string $cr_table, int $junior_class_id, bool $dry_run ) {
    global $wpdb;

    if ( ! $junior_class_id && ! $dry_run ) {
        return array( 'status' => 'skipped — no junior class_id resolved' );
    }

    $raw = get_option( ROLLSM_RESCUE_LEGACY_OPTION );
    if ( ! is_array( $raw ) || empty( $raw[ ROLLSM_RESCUE_LEGACY_KEY ] ) ) {
        return array(
            'status' => 'FAILED — legacy option missing the "' . ROLLSM_RESCUE_LEGACY_KEY . '" key',
            'option' => ROLLSM_RESCUE_LEGACY_OPTION,
        );
    }

    $junior_rolls = $raw[ ROLLSM_RESCUE_LEGACY_KEY ];

    if ( $junior_class_id ) {
        $existing = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$cr_table} WHERE competition_id = %d AND class_id = %d",
            ROLLSM_RESCUE_TARGET_COMP,
            $junior_class_id
        ) );
        if ( $existing > 0 ) {
            return array(
                'status'      => "snapshot already exists ({$existing} rows), skipping",
                'comp_id'     => ROLLSM_RESCUE_TARGET_COMP,
                'class_id'    => $junior_class_id,
                'source_rolls'=> count( $junior_rolls ),
            );
        }
    }

    if ( $dry_run ) {
        return array(
            'status'      => 'would snapshot ' . count( $junior_rolls ) . ' rolls',
            'comp_id'     => ROLLSM_RESCUE_TARGET_COMP,
            'class_id'    => $junior_class_id ?: '<new junior>',
            'source_rolls'=> count( $junior_rolls ),
        );
    }

    $inserted = 0;
    foreach ( $junior_rolls as $index => $roll ) {
        if ( ! is_array( $roll ) || empty( $roll['name'] ) ) {
            continue;
        }
        $name           = sanitize_text_field( (string) $roll['name'] );
        $max_score      = isset( $roll['max_score'] ) && is_numeric( $roll['max_score'] )
            ? (int) $roll['max_score'] : 0;
        $is_numeric_str = isset( $roll['is_numeric'] ) ? (string) $roll['is_numeric'] : 'No';
        $no_rl_str      = isset( $roll['no_right_left'] ) ? (string) $roll['no_right_left'] : 'No';

        $ok = $wpdb->insert(
            $cr_table,
            array(
                'competition_id'         => ROLLSM_RESCUE_TARGET_COMP,
                'class_id'               => $junior_class_id,
                'roll_id'                => 0,  // no master roll for junior, that's intentional
                'snapshot_name'          => $name,
                'snapshot_max_score'     => $max_score,
                'snapshot_is_numeric'    => strcasecmp( $is_numeric_str, 'Yes' ) === 0 ? 1 : 0,
                'snapshot_no_right_left' => strcasecmp( $no_rl_str,      'Yes' ) === 0 ? 1 : 0,
                'display_order'          => (int) $index + 1,
            ),
            array( '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d' )
        );
        if ( $ok === false ) {
            wp_die( 'INSERT comp_competition_rolls failed at index ' . (int) $index . ': '
                . esc_html( $wpdb->last_error ) );
        }
        $inserted += (int) $ok;
    }

    return array(
        'status'   => "snapshotted {$inserted} rolls",
        'comp_id'  => ROLLSM_RESCUE_TARGET_COMP,
        'class_id' => $junior_class_id,
    );
}

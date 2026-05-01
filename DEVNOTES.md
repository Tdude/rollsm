# RollSM Competitors — Developer Notes

Working notes for future development. Reference for the architecture, the
migration story, deployment, and known follow-ups. Companion to `README.md`,
which is user-facing.

## Architecture overview

The plugin runs in **two coexisting layers** during the migration period:

1. **Legacy layer**: WordPress CPTs (`competitors`, `sent_emails`),
   `competitors_options` array, and a few top-level `competitors_*` options.
   Source: `admin-page.php`, `public-page.php`. Still loaded.
2. **Custom-tables layer**: `comp_*` tables (10 of them) plus repository,
   service, and AJAX handler classes under `includes/`. Active when
   `comp_migration_complete` option is true.

Most read paths now hit the custom-tables layer. Writes still flow through the
legacy layer for several flows, with `SettingsSync` and `CptSync` mirroring
changes into the custom tables.

### Why both still exist

The legacy CPT data is the safety net. We never destroy it during migration —
the "Clean Up Old CPT Data" admin button is a separate, explicit action that
should only run after a full event cycle of confidence in the new path.

## Database schema

10 tables created by `Competitors_Database::create_tables()`. Schema in
`includes/Database.php`.

```
comp_competitions       — events (date, slug, is_current, is_locked)
comp_classes            — open / championship / amateur (+display_order)
comp_rolls              — master roll definitions per class
comp_competition_rolls  — per-event snapshot of rolls (immutable once locked)
comp_competitors        — registrations (links to wp_post_id for legacy compat)
comp_selected_rolls     — which rolls each competitor will perform
comp_scores             — one row per (competitor, competition_roll)
comp_timers             — start/stop/elapsed per competitor
comp_emails             — replaces sent_emails CPT
comp_email_recipients   — recipient list per email
```

No FK constraints — `dbDelta()` doesn't support them reliably. Cascades happen
in application code, e.g. `Competitors_CompetitorRepository::delete()`.

`DB_VERSION` (in `Database.php`) is the schema version. Bump it to force
`needs_upgrade()` to fire `create_tables()` on next admin page load.

## Migration tooling

Three buttons appear on **Competitors → Settings**:

| Button | What it does | Destructive? |
|---|---|---|
| Migrate Data Now | One-shot copy from CPT/options → custom tables | No (transactional) |
| Re-run Migration | Clears custom tables and re-runs migration | Yes (custom tables only) |
| Rescue Missing Scores | Backfills missing master rolls + snapshots + scores | No (insert-only) |
| Clean Up Old CPT Data | Permanently deletes CPT posts and legacy options | Yes — irreversible |

### Visibility gate (v2.0+)

The migration UI and all four AJAX handlers (`handle_ajax_migration`,
`handle_ajax_revert`, `handle_ajax_cleanup_cpt`, `handle_ajax_rescue_scores`)
are gated behind `Competitors_MigrationAdmin::is_migration_admin()` rather
than the generic `manage_options` capability. Default check: **user ID 1**
(the original site admin).

Other admins on the site keep full access to the rest of the plugin
(scoring, email blasts, settings) but never see the migration buttons and
cannot fire the destructive endpoints even with a crafted request.

To allow a different user, edit `is_migration_admin()` in
`includes/MigrationAdmin.php`:
```php
return $user && (int) $user->ID === 1;        // user ID 1 only
return $user && $user->user_email === 'me@example.com';  // by email
return $user && in_array($user->ID, [1, 7], true);       // explicit allowlist
```

### Why the rescue tool exists

The original `migrate_rolls()` reads from `competitors_options['custom_values_*']`
(nested array). Older sites stored the same data as **top-level options**
(`competitors_custom_values_open`, etc.) — those classes never made it to
master `comp_rolls`. The rescue tool's `seed_missing_master_rolls()` reads the
top-level options for any class with zero master rolls, then snapshot-backfills
and re-imports scores from postmeta.

Implementation: `includes/MigrationRescue.php`.

### The score postmeta format

Old `competitor_scores` postmeta is a serialized PHP array keyed by 0-based
roll index. Each value is `{left_group, right_group, left_score, right_score, total_score}`.
The migration maps roll_index → `competition_roll_id` via `display_order` (1-based).
Mismatch causes silent skip — that's why we now have the rescue tool.

## Public/admin sync flow for roll edits

User edits rolls in admin Roll Settings (`render_competitors_roll_field`):

```
Admin form save
  └─→ update_option('competitors_options', ...)
        └─→ SettingsSync::sync_on_save (action: update_option_competitors_options)
              ├─→ sync_classes()       — mirrors to comp_classes
              ├─→ sync_competitions()  — mirrors to comp_competitions
              ├─→ sync_rolls()         — wipes + re-inserts comp_rolls per class
              └─→ refresh_unlocked_snapshots()  — drops comp_competition_rolls
                                                  for any is_locked=0 comp,
                                                  re-snapshots from master
```

**Trade-off**: `refresh_unlocked_snapshots()` clobbers any per-event roll
customization on unlocked competitions. To preserve a custom snapshot, lock
the competition (`UPDATE comp_competitions SET is_locked = 1 WHERE id = ?`).

## Public scoreboard

Frontend (`assets/script.js`) calls **v2 AJAX actions** registered by
`Competitors_Ajax_PublicAjaxHandler`:

- `load_competitors_list_v2` — list with filters
- `load_competitor_details_v2` — per-roll detail (reads from comp_scores)
- `get_performing_rolls_v2` — registration form's rolls fieldset

The legacy `load_competitors_list`, `load_competitor_details`,
`get_performing_rolls` actions still exist in `public-page.php` but are
unused. They have a 12–24h transient cache and read from CPT/postmeta.
Delete them when the CPT cleanup button is finally pushed.

## Plesk deployment

The repo root **is** the plugin folder. Deploy target on Plesk:

```
/rollsm.se/wp-content/plugins/competitors
```

No "Additional deployment actions" needed. Auto-deploy on push to `main`.

### History (in case it breaks again)

Original setup had the plugin nested at `plugins/competitors/`. Plesk's
deploy model copies the repo root to the deploy target, which produced
`wp-content/plugins/plugins/competitors/...` — a path WordPress doesn't load.

We tried a `cp -a` post-deploy action with a staging directory at
`.git-deploy/` — works but fragile (timing races on first deploy, OPcache
issues). Final fix: flatten the repo so the plugin **is** the root, drop
the staging dir and the shell hook entirely.

If you ever need a staging directory + post-deploy copy again:
```bash
mkdir -p /var/www/vhosts/rugd.se/rollsm.se/.git-deploy
# Plesk Server path: /rollsm.se/.git-deploy
# Additional deployment action:
cp -a /var/www/vhosts/rugd.se/rollsm.se/.git-deploy/plugins/competitors/. \
      /var/www/vhosts/rugd.se/rollsm.se/wp-content/plugins/competitors/
```
(Plesk's restricted shell blocks `rsync`, hence `cp -a`.)

### Verifying a deploy

```bash
grep "Version:" /var/www/vhosts/rugd.se/rollsm.se/wp-content/plugins/competitors/competitors-settings.php
```

If the version on disk doesn't match the latest commit's bump, OPcache is
serving stale bytecode. Reset via Plesk → PHP Settings → Reset OPcache, or
deactivate-reactivate the plugin.

## Useful one-shot patterns

### mu-plugin for a one-time admin trigger

When you need to run something once on prod without wp-cli:

```php
// wp-content/mu-plugins/oneshot.php
<?php
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['run_oneshot'])) return;
    // ... do the thing ...
    wp_die('Done. Delete this file now.');
});
```

Visit `/wp-admin/?run_oneshot=1` while logged in as admin, then `rm` the file.

### Force snapshot re-creation for one event from SQL

```sql
DELETE FROM `Z8NK3nsyu_comp_competition_rolls` WHERE competition_id = 3;
INSERT INTO `Z8NK3nsyu_comp_competition_rolls`
  (competition_id, class_id, roll_id, snapshot_name, snapshot_max_score,
   snapshot_is_numeric, snapshot_no_right_left, display_order)
SELECT 3, class_id, id, name, max_score, is_numeric, no_right_left, display_order
FROM `Z8NK3nsyu_comp_rolls`
ORDER BY class_id, display_order;
```

## Known follow-ups

- **`theme-functions.php` is dead code** — never `include`d anywhere. Snippets
  for the funktionär CF7 form, intended to live in the theme. Safe to delete.
- **`public-page.php` legacy AJAX handlers** — unused since v1.6 frontend
  cutover. Worth removing alongside the CPT cleanup.
- **Score-by-name rescue path** — if `display_order` mapping ever drifts,
  add a name-match fallback to `MigrationRescue::reimport_missing_scores()`.
- **Per-event roll customization** — currently all-or-nothing (lock the
  competition or accept master overwrite). Consider a `comp_competition_rolls.is_pinned`
  flag if event organizers want partial overrides on unlocked events.
- **Local Docker bind mount** still expects the old nested path. Update
  `docker-compose.yml` (or wherever the mount is) to bind the repo root to
  `wp-content/plugins/competitors/` instead of binding `plugins/competitors/`.
- **Logo SVG**: animated `#rolling` CSS lives in the SVG itself. Don't run
  through SVGO with default settings — it strips `<style>` tags.

## Production environment reference

- WP root: `/var/www/vhosts/rugd.se/rollsm.se/`
- DB: `wp_cjemf`
- Table prefix: `Z8NK3nsyu_`
- Repo: `git@github.com:Tdude/rollsm.git`, `main` branch auto-deployed by Plesk

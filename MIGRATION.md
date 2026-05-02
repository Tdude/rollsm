# Migration to Custom Tables — Notes

A focused record of the CPT → custom-tables migration for the RollSM
Competitors plugin. Written after the migration shipped and a follow-up
session resolved the bugs that surfaced. For architecture, schema, deploy,
and ongoing dev info see `DEVNOTES.md`.

## Why migrate

The old design stored competitors as `competitors` CPT posts with all data in
postmeta:

- Per-roll scores serialized into a `competitor_scores` postmeta key
- Roll definitions per class scattered across `competitors_options['custom_values_*']`
  and top-level `competitors_custom_values_<class>` options
- Per-event roll snapshots stored as `competitors_roll_definitions_<slug>` options
- Sent emails as a separate `sent_emails` CPT

That worked for one event but had problems at three:

- Reading scoreboards required unserializing postmeta for every competitor on
  every request
- Roll-definition edits silently changed historical events' rendering
- Filtering by competition / class / gender / date couldn't use indexes
- Exporting to CSV / reporting was painful

The migration moves all of this into 10 normalised `comp_*` tables (schema in
`includes/Database.php`). The old data is preserved verbatim during and after
the migration — it's the safety net.

## What got migrated

`Competitors_Migration::run()` runs in a single transaction, in this order:

1. **Classes** — `competitors_options['available_competition_classes']` → `comp_classes`
2. **Competitions** — `competitors_options['available_competition_dates']` → `comp_competitions`
   (the *first* entry is marked `is_current=1`, the rest `is_locked=1`)
3. **Master rolls** — `competitors_options['custom_values_<class>']` → `comp_rolls`
4. **Per-event roll snapshots** — `competitors_roll_definitions_<slug>` options → `comp_competition_rolls`
5. **Competitors** — every `competitors` CPT post → `comp_competitors` (with `wp_post_id` retained as a backlink)
6. **Selected rolls** — `selected_rolls` postmeta arrays → `comp_selected_rolls`
   (mapped from 0-based roll index → `competition_roll_id` via `display_order`)
7. **Scores** — `competitor_scores` postmeta → `comp_scores`
8. **Sent emails** — `sent_emails` CPT + recipients postmeta → `comp_emails` + `comp_email_recipients`

Each step is idempotent: it skips if its target table already has rows. So
rerunning the migration is a no-op once data is in place.

The migration is triggered from the WP admin (yellow "Migrate Data Now"
notice on **Competitors → Settings**). Wraps in a transaction; rolls back on
any exception.

## What broke and how it was fixed

The migration ran cleanly on a freshly seeded environment, but on the real
production data several gaps surfaced.

### Gap 1 — Master rolls only seeded for the open class

`Migration::migrate_rolls()` reads `competitors_options['custom_values_<class>']`
(nested in the array). Older sites stored rolls at the **top level** as
`competitors_custom_values_<class>` instead. The migration didn't find them,
so master `comp_rolls` ended up with rolls only for the `open` class.

Symptom: 31 of 32 competitors at the 2024 Göteborg event ended up with no
per-roll scores after migration — only the 1 competitor in the `open` class
had matching rolls to map their score postmeta against.

Fix: `Competitors_MigrationRescue::seed_missing_master_rolls()` reads from
the top-level `competitors_custom_values_<class>` (and the parallel
`numeric_values_*`, `is_numeric_field_*`, `no_right_left_*`) options for any
class with zero master rolls. Seeds them, then continues to backfill snapshots
and re-import scores.

### Gap 2 — `competition_rolls` snapshots silently incomplete

The migration's snapshot step (`migrate_competition_roll_snapshots`) depends
on `competitors_roll_definitions_<slug>` options existing for each event. For
older events those options often only held a subset of classes. When a
(competition, class) combo had no snapshot, the score-import step silently
skipped every score for those competitors — no error, just zero scores.

Fix: `MigrationRescue` finds (competition, class) combos that have
competitors but no `comp_competition_rolls` rows, and snapshots them from
master rolls. Then re-imports `competitor_scores` postmeta for any competitor
that still has zero rows in `comp_scores`.

### Gap 3 — Public scoreboard kept showing CPT data

The migration succeeded but the public scoreboard kept rendering from CPT
postmeta with a 24-hour transient cache (`load_competitors_list` and
`load_competitor_details` in `public-page.php`). Frontend `script.js` was
still calling those legacy actions.

Fix: `script.js` switched to the v2 endpoints (`load_competitors_list_v2`,
`load_competitor_details_v2`, `get_performing_rolls_v2`) which read from
`comp_*` tables and don't cache. The detail endpoint also changed its
response shape from raw HTML to a JSON envelope, so the JS handler updated
to parse `result.data.html`.

### Gap 4 — Trash sync hole

Migrated competitors had `wp_post_id` linking back to CPT, but
`CptSync::sync_to_custom_table` only handled `save_post`, not deletion.
Trashing a competitor in WP admin removed the post but left the `comp_competitors`
row, so deleted competitors kept showing on the public scoreboard.

Fix: `CptSync::sync_delete` on `before_delete_post` and `wp_trash_post`
mirrors the deletion. `CompetitorRepository::delete` cascades to
`comp_selected_rolls`, `comp_scores`, `comp_timers`.

### Gap 5 — Snapshot edits didn't propagate

Editing a roll definition in admin updated master `comp_rolls` (via
`SettingsSync::sync_rolls`), but not the per-event snapshot in
`comp_competition_rolls`. Active events scored against the stale snapshot.

Fix: `SettingsSync::refresh_unlocked_snapshots()` rebuilds
`comp_competition_rolls` for any competition with `is_locked=0` after every
roll edit. Locked events keep their snapshot — that's the audit trail for
historical scoring.

### Gap 6 — Public registration created duplicates

Two compounding bugs:

- `handle_form_submit` created a `comp_competitors` row, then `wp_insert_post`
  fired `save_post_competitors`, which re-triggered `CptSync::sync_to_custom_table`,
  which (not yet seeing the `wp_post_id` link) created a *second* row.
- The frontend JS submitted to the legacy `competitors_form_submit` action
  instead of `_v2`, because `formData.append("action", ...)` left two
  `action` keys and `Object.fromEntries` collapsed to the legacy one. The
  legacy handler had no recent-duplicate guard; rapid double-clicks
  produced two CPT posts.

Fixes:
- `handle_form_submit` now `remove_action` / `add_action`'s the CptSync hook
  around its `wp_insert_post` so the explicit create is the only writer.
- JS uses `formData.set("action", "competitors_form_submit_v2")` so a second
  `action` key cannot exist.
- `handle_form_submit` checks for the same `(competition_id, email, name)`
  created within the last 30 seconds and short-circuits on a second click.

## The rescue tool, end to end

`includes/MigrationRescue.php`. Triggered by the **"Rescue Missing Scores"**
button on the post-migration admin notice.

```
run()
 ├─ Step 0: seed_missing_master_rolls()
 │    For each class with 0 rows in comp_rolls, read the legacy top-level
 │    options and seed.
 ├─ Step 1: backfill snapshots
 │    For each (competition, class) that has competitors but no
 │    competition_rolls, snapshot from master comp_rolls.
 │    Tracks classes still empty after seeding (returned as
 │    missing_master_rolls in the response).
 └─ Step 2: reimport_missing_scores()
      For every competitor with no scores in comp_scores, read
      competitor_scores postmeta and map by display_order to
      competition_roll_id.
```

Non-destructive — only `INSERT`. Existing data stays. Idempotent — second
run is a no-op.

## Operational learnings

- **OPcache caches bytecode aggressively on Plesk.** Even after a Git pull,
  the on-disk plugin file may not be the served version until OPcache resets
  or PHP-FPM restarts. Workflow used during this migration: bump
  `Version:` in the plugin header on every meaningful push, then verify on
  prod with `grep "Version:" .../competitors-settings.php`.
- **`needs_upgrade()` is gated by `Database::DB_VERSION`.** Bumping the
  plugin header version doesn't trigger table creation; bumping
  `DB_VERSION` does. Used once during the migration push to force
  `create_tables()` on prod's first admin page load.
- **`update_option` triggers `update_option_<name>` action, which
  `SettingsSync` listens on.** Useful: a one-shot mu-plugin that just
  calls `update_option('competitors_options', $modified)` flows through
  the whole sync chain — master rolls, snapshots, etc. — for free.
- **Don't trust the rescue counts on the second click.** The first
  successful run mutates state. Second click reports `0,0,0,0` because
  there's nothing left to rescue. Always read the *first* response for
  the real counts.
- **Verify the migration completion flag on prod with**:
  ```sql
  SELECT option_value FROM wp_options WHERE option_name = 'comp_migration_complete';
  ```
  Returns `1` post-migration. Deleting this row reverts the plugin to legacy
  code paths without touching the custom-table data.

## Pending — what hasn't been cleaned up yet

- **CPT posts and `sent_emails` posts**: roughly 60 records still in
  `wp_posts` and their postmeta. The "Clean Up Old CPT Data" button on the
  post-migration notice would delete them. Deferred deliberately until
  after at least one full event cycle on the new path. Keep them for now.
- **`public-page.php`**: legacy AJAX handlers (`load_competitors_list`,
  `load_competitor_details`, `get_performing_rolls`,
  `handle_competitor_form_submission`) still registered, no longer called.
  Worth removing alongside the CPT cleanup.
- **`theme-functions.php`** at the repo root: dead reference doc for the
  funktionär CF7 form. Never `include`d.

## Lessons for next time (if there is a next time)

1. **Sweep both option formats** when reading legacy data. Sites that
   started early collect cruft — the same data exists nested, top-level,
   and per-slug. Check all three before assuming a class has no rolls.
2. **Make the migration's silent-skip into a noisy failure.** The score
   import skipping competitors with no matching snapshot was the single
   biggest debugging sink. Logging skipped rows would have made the rescue
   tool self-evident on day one.
3. **Switch the frontend to new endpoints in the same release as the
   backend.** Leaving the JS pointing at legacy actions made the migration
   feel broken even though the data was correct.
4. **Build the post-migration cleanup tool with the same care as the
   migration itself.** The CPT cleanup button has been "ready to click"
   for a year — but it's also *destructive and irreversible*, and the
   safety from deferring it is real.

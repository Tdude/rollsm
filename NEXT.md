# NEXT — RollSM Competitors, where to pick up

Working notes that capture an in-flight investigation and a two-phase plan.
Read this with `MIGRATION.md` (the original migration story) and `DEVNOTES.md`
(architecture + deploy) open in the next tab. This doc is the entry point for
the next coding session.

## Status at a glance

| Item | Status | Phase |
|---|---|---|
| Investigation: missing 2025 Junior scores | Done | Phase 1 |
| Rescue script (`tools/rescue-2025-unclassified.php` + `.sql`) | Drafted, ready to run | Phase 1 |
| Run rescue on local | **Not done yet** | Phase 1 |
| Run rescue on prod | **Not done yet** | Phase 1 |
| `SettingsSync` deletes classes/competitions safely | Pending design | Phase 2 |
| Admin notice for "silent skip" diagnostics | Pending design | Phase 2 |
| Admin-editable registration form fields | Pending design | Phase 2 |

## The problem that triggered all this

Three things came up after the CPT → custom-tables migration shipped 3 weeks ago.

1. **Rumer F. and Kaia W. appeared "missing" from 2025 results.**
   Investigation showed only Rumer (and Idun W.) are actually broken;
   Kaia is correct. See Phase 1 below.
2. **Removing the "Ungdom" class from settings does not remove it from the
   public registration form.** Root cause: `SettingsSync::sync_classes` is
   additive only — no delete path. Item 6 in the task list.
3. **Want to make registration form fields admin-editable.** Spec drafted in
   the original investigation, see "Phase 2 — Admin-editable form fields"
   below. Defer until Phase 1 + the deletion fix are done.

---

## Phase 1 — Restore the 2025 Junior data

### What the DB says (no interpretation)

`Z8NK3nsyu_comp_competitors`:

| id | comp | class_id | post | name | gender | comp_scores rows |
|---|---|---|---|---|---|---|
| 38 | 2 (2025 Västervik) | **0** | 2271 | Idun W. | woman | **0** |
| 41 | 2 (2025 Västervik) | **0** | 2277 | Rumer F. | woman | **0** |
| 45 | 2 (2025 Västervik) | 2 (championship) | 2285 | Kaia W. | woman | 36 |

The legacy CPT postmeta (still intact, never deleted) shows the original class:

| post_id | name | `participation_class` postmeta | `competitor_scores` postmeta |
|---|---|---|---|
| 2271 | Idun W. | **`junior`** | 37 entries, mostly zeros |
| 2277 | Rumer F. | **`junior`** | 37 entries, many real scores up to 20 |
| 2285 | Kaia W. | `championship` | 36 entries with substantial scores |

`Z8NK3nsyu_comp_classes` (today): `open`, `championship`, `amateur`, `ungdom`.
There is **no `junior`** row, and there hasn't been since the migration.

The legacy option `competitors_roll_definitions_2025-09-13` (still present)
contains FOUR top-level class keys: `championship` (36 rolls), `open` (36),
`amateur` (17), and **`junior` (37 rolls)** — the actual list used to judge
Rumer and Idun in 2025.

### Why the migration dropped this on the floor

In `includes/Migration.php`:

- `migrate_competition_roll_snapshots()` (line 349) iterates the top-level
  class keys in the legacy option, looks up `$class_map[$class_name]`, and
  silently skips any key without a match. `$class_map['junior']` returned
  nothing → the entire 37-roll Junior subarray was skipped.
- `migrate_competitors()` (line 441) reads `participation_class` postmeta,
  falls back to `class_id=0` if the slug is unknown. Rumer and Idun got
  `class_id=0`.
- `migrate_scores()` (line 552) joins on `(competition_id, class_id)` against
  `comp_competition_rolls`, which returns empty for `class_id=0`. Every
  score postmeta was skipped silently.

Same shape as Gap 1/2 in `MIGRATION.md`, just with a different trigger
(slug rename instead of nested-vs-top-level options).

### The rescue: `tools/rescue-2025-unclassified.php`

Idempotent, dry-runnable, scoped to the two broken rows. Does exactly four
things, all reversible:

1. INSERT `comp_classes (name='junior', comment='Junior', display_order=5)`
   if it doesn't already exist.
2. UPDATE `comp_competitors.class_id` for posts 2277 and 2271 from `0` to
   the new junior class id. Refuses to overwrite a non-zero class_id.
3. Read `competitors_roll_definitions_2025-09-13['junior']` from `wp_options`
   and snapshot the 37 rolls into `comp_competition_rolls` for
   `(competition_id=2, class_id=junior)`. Skipped if snapshot exists.
4. Call `Competitors_MigrationRescue::run()` to import the postmeta scores.
   With the snapshot now in place, the existing rescue tool succeeds.

**Kaia is not in the mapping. Her row is left exactly as it is.**

### How to run

```
1. Copy tools/rescue-2025-unclassified.php → wp-content/mu-plugins/
   (via Plesk File Manager: navigate to wp-content/plugins/competitors/tools/,
    download the file, upload it into wp-content/mu-plugins/ — create the
    folder first if it doesn't exist.)
2. As admin user ID 1, visit:
     /wp-admin/?rescue_2025=1&dry_run=1        ← preview only, no writes
     /wp-admin/?rescue_2025=1                    ← actually run
3. Read the result page (printed report + post_state block at the bottom).
4. Click the "🗑 Delete this mu-plugin file" button on the result page
   (or visit /wp-admin/?rescue_2025_cleanup=1 directly). The file
   removes itself — no Plesk File Manager step needed for cleanup.
```

The `.sql` companion at `tools/rescue-2025-unclassified.sql` does the same
thing as raw SQL — useful for inspecting before running, or if you'd rather
edit the DB directly. After running the SQL you still click "Rescue Missing
Scores" in the admin to import the postmeta into `comp_scores`.

### What to verify after running

- `post_state` in the report shows `class_id = <new junior id>` for both
  Rumer (id=41) and Idun (id=38), and `score_count > 0`, and `total_score > 0`.
- Public scoreboard with filter "2025 Västervik + Junior" lists both.
- Click each → per-roll detail shows roll names from the Junior list,
  including "5. Fjärilsroll (Butterfly)" (which is NOT in Open's list — this
  is the cleanest tell that the snapshot is correct).
- Kaia regression check: her championship totals match what they were before
  the rescue.

### How to reverse if it goes wrong

The original `competitor_scores` postmeta is never touched. Roll back by:

```sql
DELETE FROM `Z8NK3nsyu_comp_scores`
  WHERE competitor_id IN (38, 41);
DELETE FROM `Z8NK3nsyu_comp_competition_rolls`
  WHERE competition_id = 2 AND class_id = (
    SELECT id FROM `Z8NK3nsyu_comp_classes` WHERE name = 'junior'
  );
UPDATE `Z8NK3nsyu_comp_competitors`
  SET class_id = 0
  WHERE wp_post_id IN (2271, 2277);
DELETE FROM `Z8NK3nsyu_comp_classes` WHERE name = 'junior';
```

Then you're back to today's broken-but-intact state.

### Known side-effects after running

- `junior` will appear in the public **scoreboard filter** dropdown.
  This is good — 2025 Junior becomes a filterable view.
- `junior` will also appear in the public **registration form** class radios.
  This is unintentional, but it's the same orphan problem as `ungdom` today.
  Both are caused by the issue Phase 2 task #6 fixes (class deletion).
  Until then: ignore, or manage by editing `available_competition_classes`
  in `competitors_options` to include `junior` so admin can see it.

---

## Phase 2 — Make the foundation solid

Remember from history, code for the future. Three follow-ups in order:

### #6 — `SettingsSync` handles deletions safely

File: `includes/SettingsSync.php`.

- `sync_classes()` and `sync_competitions()` are currently INSERT-or-UPDATE
  only. Removing a class from `competitors_options['available_competition_classes']`
  leaves orphans in `comp_classes` forever (today's `ungdom`, tomorrow's
  `junior`).
- Add a diff-and-delete step. **Refuse** to hard-delete a class if any of
  the following exist:
  - `comp_competitors.class_id = <id>`
  - `comp_rolls.class_id = <id>`
  - `comp_competition_rolls.class_id = <id>`
  Surface a clear admin notice instead. "Class 'X' is referenced by 12
  historical competitors — archive it or reassign first."
- Consider adding an `is_archived` column to `comp_classes` so historically-
  used-but-no-longer-offered classes (like `junior`) can be hidden from the
  public registration form but kept in scoreboard filters. This is the
  cleanest fix for the rescue's side-effect above.
- Same logic for `comp_competitions` deletion (less urgent — competitions
  are append-only in practice).

### #7 — Make the silent skip noisy

The single biggest debugging sink across this whole story has been migrations
that silently skip rows when their join keys don't resolve. Future-proof:

- Add an admin notice at the top of Competitors → Settings that lists any
  competitor where `class_id = 0`, OR where `competitor_scores` postmeta
  exists but `comp_scores` has zero rows for that competitor.
- Log every skipped insert in `Migration::migrate_scores` and
  `MigrationRescue::reimport_missing_scores` with the reason (no matching
  snapshot, missing class, etc.). Store in `comp_migration_log` (new
  three-column table: id, level, message, created_at) or just `error_log`
  if a table feels heavy.

### #8 — Admin-editable registration form fields

Deferred spec from the original investigation. Schema sketch:

```
comp_form_fields
  id, scope (global|competition), competition_id (nullable),
  field_key, label, field_type (text|textarea|email|tel|checkbox|radio|select),
  options_json, is_required, is_public, display_order, created_at, updated_at

comp_competitor_field_values
  id, competitor_id, field_key, value (longtext)
```

Built-in fields (name, email, phone, club, gender, license, dinner, consent)
stay as first-class columns on `comp_competitors`. Everything new flows
through the dynamic layer. Admin UI under Competitors → Settings, mirroring
the plus/minus row UX of Roll Settings (`competitors-settings.php:1284`).

Snapshot field set when a competition is locked, same way roll snapshots
work today. Per-class field visibility via `class_ids_json` is a stretch
goal.

**Blocked by #6** so we build it on a foundation where class lifecycle
works correctly.

---

## Files touched in this work cycle

```
tools/rescue-2025-unclassified.php    ← mu-plugin, primary rescue tool
tools/rescue-2025-unclassified.sql    ← SQL companion, same operations
NEXT.md                                ← this file
.gitignore                             ← excludes *.sql / *.sql.zip dumps (PII)
```

No plugin code in `includes/` was modified. The rescue is intentionally a
one-shot mu-plugin outside the plugin tree — easier to remove cleanly after
running, won't show up in plugin updates, no risk of accidentally shipping
the rescue to other sites.

## DB dumps in the working tree

Two zipped SQL dumps exist locally but are now `.gitignore`d:

- `ROLLSM_wp_cjemf_2026-05-24_12-01-35.sql.zip` — current state, used for
  this investigation.
- `wp_cjemf_2025-09-19_21-37-43.sql.zip` — older backup from right after
  the 2025 competition. Useful cross-check if anything in the current dump
  looks suspect.

Keep them locally as safety nets. Never commit dumps — they contain emails,
phone numbers, ICE contacts.

## Open questions for next session

- Should `is_archived` on `comp_classes` be added as part of #6, or is a
  separate task? Leaning: include in #6 since it's the cleanest fix for
  the "orphan class on public form" side-effect.
- Does the Item-3 form-fields work want to live on `competitors_options`
  (matching today's classes/dates pattern) or in dedicated tables (matching
  the post-migration architecture)? Leaning: dedicated tables — the option
  bloat is exactly what the migration set out to eliminate.

## Reference — the migration's third silent-skip pattern

For the `MIGRATION.md` "Lessons for next time" list, add a third lesson:

> 4. **Sweep both option formats AND watch for slug renames.** Rumer and
>    Idun's `participation_class='junior'` no longer mapped to any row in
>    `comp_classes` because the slug had been renamed to `ungdom` before
>    the migration ran. The migration silently dropped both their roll
>    snapshot (in step 4) and their score postmeta (in step 6). Either
>    keep a slug-rename map in the migration, or surface unmapped slugs
>    in a noisy admin notice (task #7).

Update `MIGRATION.md` with this lesson after Phase 1 completes.

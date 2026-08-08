# NEXT — RollSM Competitors, where to pick up

Working notes that capture what's been done and what's left.
Read alongside `MIGRATION.md` (the original migration story) and
`DEVNOTES.md` (architecture + deploy).

## Status at a glance

| Item | Status |
|---|---|
| Investigation: missing 2025 Junior scores | Done |
| Rescue script (`tools/rescue-2025-unclassified.{php,sql}`) | Drafted, run on local + prod |
| Archive button for classes (replaces broken Remove) | Done |
| Orphan class surfacing + inline rename in admin | Done |
| Empty-archived classes hidden from scoreboard filter | Done |
| Form Settings tab — toggle/relabel optional fields | Done |
| Duplicate-row race fix via MySQL GET_LOCK mutex | Done |
| 2026 personal fields: address, next-of-kin, special diet | Done |
| Per-event custom fields engine (typed, self-describing) | Done |
| Dinner → per-event headcount custom field (no fee) | Done |
| **Silent-skip diagnostic admin notice** | **Pending — next up** |
| Class relabels + maxtid copy for 2026 | Manual (wp-admin / page content) |

## 2026 registration changes (2026-06-01)

The arranging club's 2026 form requests, implemented in three tiers
(see commits `6c6b621`, `c5ce41c`, `1a8493a` on `main`):

- **Stable personal fields as real columns** — `address`,
  `emergency_contact` (next of kin + phone), `special_diet` on
  `comp_competitors`. Toggleable/relabelable from the Form Settings tab
  (one-line additions to `field_defaults()`); shown in the admin list,
  admin email, and GDPR-relevant export. `DB_VERSION` → 1.3.0.
- **Per-event custom-fields engine** — `Competitors_CustomFieldRepository`.
  Definitions stored per competition under
  `competitors_options[custom_fields][<id>]`; submitted values stored
  self-describing (`key+label+type+value` JSON) on
  `comp_competitors.extra_fields` (`DB_VERSION` → 1.4.0). Types: text,
  textarea, number, yesno, select. Managed in the new **Custom Fields**
  admin tab (`manage_options`), scoped to the current event, pre-filled
  on first visit with the 2026 set (dinner headcount, planned rolls,
  distance paddle).
- **Dinner retired** as a fixed yes/no field — now a per-event headcount
  custom field with no fee (attendees pay the restaurant). Old `dinner`
  column kept for 2024/2025 history.
- **Bug fix:** `CompetitorRepository::create()` format array was one
  short of the column count — corrupted the JSON column and silently
  coerced `fee` to int. Now 19/19, verified in Docker.

The design rationale (which fields are hardcoded vs. configurable, and
why a full form-builder was rejected) is the answer to "should each
year's changes be hardcoded or fully customisable": structural fields
that scoring/dedup/fee depend on stay hardcoded; volatile yearly
logistics fields go through the custom-fields engine so organizers edit
them in wp-admin.

**Still manual for 2026:** relabel the four classes (Open *(intl.)* /
Championship / Motion / Junior) in Classes & Dates, and put the "Maxtid
30 min" note in the page content (it's event-volatile copy — was 20 min
last year — so deliberately not hardcoded in the plugin).

## What got done in this session (2026-05-25 → 2026-05-26)

Eight commits, all on `main`, all live on prod.

```
8a3547e docs: rescue script + NEXT.md roadmap for 2025 Junior class scores
9485b05 feat: archive classes instead of removing them
730c870 feat: inline-edit class comments and surface orphaned classes
8249e60 fix: bump plugin version for JS cache bust, soften orphan badge
6897932 fix: hide empty archived classes from scoreboard filter
8b51ed9 feat: self-delete URL for rescue mu-plugin (Plesk-friendly)
0738ee5 fix: prevent duplicate competitor rows via DB-level mutex
a06cec6 feat: admin-editable visibility and labels for registration form fields
```

Concrete outcomes:

- **2025 historical results restored.** Rumer F. (197 pts) and Idun W.
  (30 pts) now display under 2025 Västervik → Junior with the
  37-roll Junior snapshot they were judged against. Kaia W.'s
  championship data was untouched. Junior class exists in
  `comp_classes` as id=5 and shows in scoreboard filters but only
  when it has competitors.
- **Class lifecycle is sane.** Replaced the broken "Remove" button
  (which orphaned rows in `comp_classes`) with a proper
  Archive/Unarchive flow. Archived classes hide from the public
  registration form, stay visible in admin and (when they have
  data) in the scoreboard filter. Inline edit for comments lets
  admin rename labels without DB touching.
- **Form Settings tab is live.** Five optional fields (`club`,
  `sponsors`, `speaker_info`, `license`, `dinner`) can each be
  hidden, relabelled, or have their required marker toggled (just
  speaker_info has that) — all from `wp-admin → Competitors →
  Form Settings`.
- **Duplicate registrations can't happen any more.** A real
  reproducer (single user click producing two concurrent POSTs)
  was caught locally. Fix uses MySQL `GET_LOCK` to serialise
  registrations on `(competition_id, email, name)`. Bounded in
  time by the PHP request lifecycle, auto-releases on connection
  close. No schema change.

## Phase 1 — original rescue (reference, no action needed)

The mu-plugin at `tools/rescue-2025-unclassified.php` is now obsolete
for routine work but kept in-tree as the audit trail for what was
done to the 2025 data. The same rescue ran cleanly on local and
prod with identical results (74 scores added, 2 competitors
rescued, 37-roll Junior snapshot materialised). If a similar gap
ever surfaces for a different class slug, the structure of that
script is a good template: insert missing class → reclassify rows
→ snapshot historical roll set from the legacy
`competitors_roll_definitions_{slug}` option → re-run
`MigrationRescue::reimport_missing_scores()`.

The `.sql` companion (`tools/rescue-2025-unclassified.sql`) has the
same operations as raw SQL — useful for inspecting before running,
or for a phpMyAdmin-only path.

If you ever need to reverse the rescue:

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

## Phase 2 — the one remaining task

### #7 — Make the silent skip noisy

The single biggest debugging sink across the whole migration story
has been steps that silently skip rows when their join keys don't
resolve. The 2025 Junior rescue happened because two competitors
were sitting with `class_id=0` and zero scores for three weeks
without anyone noticing.

What to build:

- An **admin notice** at the top of Competitors → Settings (or a
  small "Diagnostics" panel on its own page) that lists, in order:
  1. Any competitor with `class_id = 0` (orphaned from migration
     or from a class slug that was never recreated).
  2. Any competitor whose `wp_post_id` points at CPT postmeta
     with `competitor_scores` data but whose row in
     `comp_scores` is empty — i.e. data exists in the safety-net
     layer but didn't make it across.
  3. Any `(competition_id, class_id)` pair that has competitors
     but no rows in `comp_competition_rolls` — the precondition
     for silent score-skip during migration / rescue.

- For each finding, link to the relevant admin page (e.g. the
  Competitor List filtered to that competitor) and to a one-shot
  "Re-import this row's scores" button that calls
  `MigrationRescue::run()` scoped to that competitor.

- Only visible to migration admin (user ID 1) — same gating as
  `MigrationAdmin::is_migration_admin()`.

Implementation sketch:

```
includes/Admin/DiagnosticsNotice.php
  - class with init() registering admin_notices hook
  - query helpers returning the three lists above
  - render method outputting the notice with link/button per item
  - dismiss-once-per-finding semantics? probably yes, so it isn't
    nagging admin who knows about a specific orphan
```

No schema changes. Pure read-side diagnostics. Estimated 1-2 hours.

## Architecture quick-reference (as of 2026-05-26)

```
wp-admin → Competitors → submenu pages:
  Rolls & Points        ← role: roll definitions per class
  Classes & Dates       ← role: class lifecycle (add/archive/rename)
  Form Settings         ← role: per-field visibility + labels  [NEW]
  Competitor List       ← role: admin view of registrations
  Judges Scoring        ← role: enter/edit scores
  Personal Data         ← role: GDPR export

Custom tables (comp_*):
  comp_classes          ← + is_archived column [NEW, DB_VERSION 1.2.0]
  comp_competitions
  comp_rolls
  comp_competition_rolls
  comp_competitors
  comp_selected_rolls
  comp_scores
  comp_timers
  comp_emails
  comp_email_recipients

Source-of-truth pattern:
  Most admin UI writes to wp_options['competitors_options'].
  SettingsSync mirrors to comp_* tables on every save. Reads
  always go to comp_*.

  Form Settings stores under
  competitors_options['form_field_settings'][<key>][visible|label|required]
  Reads via Competitors_Public_RegistrationForm::field_setting().
```

## DB dumps in the working tree

Two zipped SQL dumps exist locally but are `.gitignore`d:

- `ROLLSM_wp_cjemf_2026-05-24_12-01-35.sql.zip` — pre-rescue state.
- `wp_cjemf_2025-09-19_21-37-43.sql.zip` — right after the 2025
  competition.

Keep them. Never commit dumps — they contain emails, phone numbers,
ICE contacts. Last names of minors are also visible (Rumer, Idun,
Kaia were 15-17 years old in 2025).

## Open questions for whenever

- Should `comp_classes` get a `display_label` column separate from
  the slug-derived `name`/`comment`? Right now the admin Form
  Settings page renames in-place by editing `comment`. Works fine
  but the slug stays as the original (e.g. `amateur` → display
  "Motion"). A separate label column would let the slug stay
  semantically meaningful while the display string drifts freely.
- The 8-bucket scoreboard (4 classes × 2 genders) discussed
  earlier — currently filterable via the two existing dropdowns.
  Could be more prominent as 8 always-visible sections. Backlog
  item, no rush.
- Per-event field settings (dinner on for Kalmar 2026, off for
  some future event). Today's Form Settings is global. If this
  becomes painful, add a `competition_id` dimension to the
  settings option array and a per-event override UI. Defer until
  the pain is real.
- Multi-class registration (one person, several classes at the same
  event) — researched but **not confirmed as needed and not started**.
  See `MULTI_CLASS_REGISTRATION.md` for the architecture writeup and a
  latent idempotency-key bug it surfaced. Get explicit confirmation
  from the competition director before picking this up.

## Lessons folded back into MIGRATION.md (TODO)

Add this fourth lesson next time MIGRATION.md gets touched:

> 4. **Sweep both option formats AND watch for slug renames.**
>    Rumer F. and Idun W.'s `participation_class='junior'` no longer
>    mapped to any row in `comp_classes` because the slug had been
>    renamed (or was never present) before the migration ran. The
>    migration silently dropped both their roll snapshot (in step
>    4) and their score postmeta (in step 6). Either keep a
>    slug-rename map in the migration, or surface unmapped slugs
>    in a noisy admin notice (the work for task #7 above).

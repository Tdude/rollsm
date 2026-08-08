# Multi-class registration — research notes (not implemented)

**Status: research only. No code has changed. Do not start work on this
without an explicit "yes, we need one person to register for more than one
class" confirmation from whoever owns the registration rules (the arranging
club / competition director).** This doc exists so that confirmation doesn't
have to be re-derived from scratch later — read this first, then go ask.

## The question to get answered before touching anything

Today the form's "Participation in Class" field is a single radio group —
one person, one class, per registration. Before any change: **does a
competitor ever legitimately need to compete in more than one class at the
same event?** If the real ask is something else — e.g. "let people *switch*
class after registering" (that's an admin edit, already possible), or "show
someone's results across multiple past events" (already how the scoreboard
works) — this doc doesn't apply and nothing below is needed.

If the answer is genuinely yes, two viable approaches exist (see
"Recommended approaches" below), of very different size.

## Current architecture (as of 2026-07-18)

One competitor row = exactly one class. This is load-bearing in five places:

1. **Schema** — `comp_competitors.class_id` is a scalar
   `bigint(20) unsigned` column (`includes/Database.php:166`), not a join
   table. `comp_scores` is uniquely keyed on
   `(competitor_id, competition_roll_id)` (`includes/Database.php:216`), and
   `competition_roll_id` itself already points at a class-specific roll set
   (`comp_competition_rolls.class_id`, `includes/Database.php:149`) — so the
   scores table doesn't block a competitor scoring in two classes, but the
   competitor row's single `class_id` does.
2. **Form** (`includes/Public/RegistrationForm.php:285-301`) — radio
   buttons, mutually exclusive by construction, one `participation_class`
   value.
3. **Client JS** (`assets/script.js`) —
   `validateRadioSection()` (line 237) requires exactly one checked radio;
   `toggleLicenseCheckbox()` (line 318) hardcodes `#championship` as the
   only class needing the license checkbox; `updatePerformingRolls()`
   (line 381) replaces the single rolls fieldset wholesale on class change
   rather than accumulating one per class.
4. **AJAX handler** (`includes/Ajax/PublicAjaxHandler.php::handle_form_submit`,
   line 42) — resolves one `$class_id` (line 112-113), does one fee lookup
   (line 166-170), makes one `CompetitorRepository::create()` call
   (line 184), one `set_selected_rolls()` call (line 211), one CPT
   `wp_insert_post()` (line 227), sends one pair of emails (line 264-269).
5. **Admin / scoreboard** — class filters and the CPT's `participation_class`
   postmeta are scalar throughout `admin-page.php`.

## The non-obvious finding: this is already partially possible today, and it's landmined

There is **no unique constraint on email** in `comp_competitors`. Nothing
stops the same person submitting the registration form twice — once per
class — producing two independent competitor rows. That's actually the
cheapest path to "one person, multiple classes": no radio→checkbox rewrite,
no schema change.

But `PublicAjaxHandler::handle_form_submit` has a 30-second idempotency guard
that will silently eat the second submission:

```
// includes/Ajax/PublicAjaxHandler.php:146-154
$existing = $wpdb->get_row( $wpdb->prepare(
    "SELECT id FROM comp_competitors
     WHERE competition_id = %d AND email = %s AND name = %s
       AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
     LIMIT 1",
    $competition_id, $email, $name
), ARRAY_A );
```

`class_id` is not part of that key (and it's likewise absent from the
`GET_LOCK` mutex key one block above, `includes/Ajax/PublicAjaxHandler.php:127`).
So: person submits for Championship, then within 30 seconds submits again
for Amateur — the second request matches the "already registered" check,
gets `wp_send_json_success` with a friendly "already in" message, and
**silently creates nothing**: no row, no fee, no confirmation email, no
error surfaced to the user or admin. This bug exists right now, independent
of any UI change, the moment two submissions from the same person within 30
seconds become a real workflow rather than a double-click guard.

## What a real checkbox-based multi-class UI would additionally require

If the product decision is "one registration flow, select N classes,
combined confirmation and fee," beyond the idempotency-key fix above:

- `render_class_field()` → checkboxes; `render_rolls_fieldset()` → render
  one roll table per *selected* class instead of swapping a single table.
- `validateRadioSection` → checkbox-group equivalent ("at least one
  checked").
- `toggleLicenseCheckbox` → "any selected class requires license" instead
  of one hardcoded id.
- `handle_form_submit` → loop over selected classes: sum fees
  (`get_competitor_price_list()` currently returns a flat 600 SEK for all
  three classes, so summing is trivial today but not guaranteed to stay
  that way), create one `comp_competitors` row per class (keeps the
  existing one-row-per-class invariant that scoring/scoreboard/admin all
  rely on — much less invasive than turning `class_id` into a many-to-many
  join), one `set_selected_rolls()` call per row.
- `send_admin_email` / `send_confirmation_email` → currently take a single
  `$participation_class` string; would need a combined summary or one email
  per class.
- Admin list / scoreboard would then show the same person as N rows (one
  per class) — consistent with how class-scoped filtering already works,
  but worth confirming that's the desired display, not confusing to
  organizers reconciling headcounts.

## Recommended approaches, cheapest first

1. **Fix the idempotency-key bug alone** (add `class_id`/`participation_class`
   to the SELECT at `PublicAjaxHandler.php:146` and to the `GET_LOCK` key at
   line 127). Unlocks "submit the form twice" as a working, if unpolished,
   path to multi-class registration. Small, low-risk, arguably worth doing
   regardless of the multi-class question since it's a latent data-loss bug.
2. **Full checkbox flow** as scoped above — meaningfully bigger, touches
   form rendering, JS validation, fee calc, email content, and admin
   expectations. Only worth it if the UX of two separate submissions is
   genuinely unacceptable to the club.

Do not start on either without the confirmation described at the top of
this file.

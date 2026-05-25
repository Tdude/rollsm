-- RollSM One-Shot — Rescue 2025 Junior Competitors (SQL companion)
--
-- This is the SQL equivalent of tools/rescue-2025-unclassified.php. Use it
-- if you'd rather poke the DB directly than load a mu-plugin.
--
-- Steps performed:
--   1. INSERT the 'junior' class slug into comp_classes (idempotent).
--   2. Reassign Rumer Fenn (post 2277) and Idun Weidmert (post 2271) from
--      class_id=0 to class_id=<junior>.
--   3. INSERT the 37-roll historical Junior snapshot into
--      comp_competition_rolls for (competition_id=2, class_id=junior).
--      Values transcribed verbatim from the legacy option
--      `competitors_roll_definitions_2025-09-13`['junior'].
--   4. After commit: wp-admin → Competitors → Settings →
--      "Rescue Missing Scores" to import the postmeta into comp_scores.
--      Kaia Weidmert (already correct with 36 scores) is untouched.
--
-- Replace the `Z8NK3nsyu_` prefix if your install uses a different one.
-- ALWAYS run inside a transaction, against a backup, on local first.

START TRANSACTION;

-- ─── Sanity ──────────────────────────────────────────────────────────────
-- Expect 2 rows with class_id=0 BEFORE the update.
SELECT id, name, wp_post_id, competition_id, class_id
FROM `Z8NK3nsyu_comp_competitors`
WHERE wp_post_id IN (2271, 2277);

-- ─── Step 1: ensure 'junior' class. Idempotent via UNIQUE KEY on name. ───
INSERT IGNORE INTO `Z8NK3nsyu_comp_classes` (name, comment, display_order)
VALUES ('junior', 'Junior', 5);

SET @class_junior := (SELECT id FROM `Z8NK3nsyu_comp_classes` WHERE name = 'junior' LIMIT 1);

SELECT IFNULL(@class_junior, (SELECT 'FATAL: junior class still missing' FROM DUAL WHERE 1/0)) AS junior_id;

-- ─── Step 2: reclassify (only touches class_id=0 rows). ──────────────────
UPDATE `Z8NK3nsyu_comp_competitors`
SET class_id = @class_junior
WHERE wp_post_id = 2277 AND class_id = 0;   -- Rumer Fenn

UPDATE `Z8NK3nsyu_comp_competitors`
SET class_id = @class_junior
WHERE wp_post_id = 2271 AND class_id = 0;   -- Idun Weidmert

-- ─── Step 3: snapshot the 37-roll Junior set for (comp=2, class=junior). ─
-- Only inserts if no rows already exist for this (comp, class) pair.
-- Guard: a single SELECT-driven INSERT below; we use a temp condition row.
SET @existing_snapshot := (
    SELECT COUNT(*) FROM `Z8NK3nsyu_comp_competition_rolls`
    WHERE competition_id = 2 AND class_id = @class_junior
);

-- If snapshot already populated, stop here without inserting anything.
SELECT IF(@existing_snapshot = 0, 'will insert 37 junior rolls',
                                  CONCAT('snapshot already populated (', @existing_snapshot, ' rows), skipping inserts')) AS snapshot_plan;

-- The 37 INSERTs below are wrapped in a `WHERE @existing_snapshot = 0`
-- guard. If the snapshot exists already, every INSERT becomes a no-op.
INSERT INTO `Z8NK3nsyu_comp_competition_rolls`
    (competition_id, class_id, roll_id, snapshot_name, snapshot_max_score, snapshot_is_numeric, snapshot_no_right_left, display_order)
SELECT 2, @class_junior, 0, t.snapshot_name, t.snapshot_max_score, t.snapshot_is_numeric, t.snapshot_no_right_left, t.display_order
FROM (
    SELECT  1 AS display_order, '1. Vricka på rygg (Side Sculling)' AS snapshot_name, 2 AS snapshot_max_score, 0 AS snapshot_is_numeric, 0 AS snapshot_no_right_left UNION ALL
    SELECT  2, '2. Vricka på mage (Chest Sculling)', 2, 0, 0 UNION ALL
    SELECT  3, '3. Standard Grönlandsroll (Standard G-L roll)', 2, 0, 0 UNION ALL
    SELECT  4, '4. Standardroll med paddeln i armvecket (Crook of elbow)', 3, 0, 0 UNION ALL
    SELECT  5, '5. Fjärilsroll (Butterfly)', 3, 0, 0 UNION ALL
    SELECT  6, '6. Stormroll (Storm roll)', 3, 0, 0 UNION ALL
    SELECT  7, '7. Roll med svep från aktern till fören (Reverse sweep roll)', 3, 0, 0 UNION ALL
    SELECT  8, '8. Ryggradsroll (Spine Roll)', 3, 0, 0 UNION ALL
    SELECT  9, '9. Roll med svep från aktern till fören med paddeln bakom ryggen (R-S Paddle behind back)', 4, 0, 0 UNION ALL
    SELECT 10, '10. Standardroll med paddeln bakom nacken (Std. roll, paddle behind neck)', 4, 0, 0 UNION ALL
    SELECT 11, '11. Roll m. svep från aktern till fören, paddeln bakom nacken (R-S roll, paddle behind neck)', 4, 0, 0 UNION ALL
    SELECT 12, '12. Standardroll med paddeln mot axeln (Shotgun, Armpit roll)', 3, 0, 0 UNION ALL
    SELECT 13, '13. Lodrät/Vertikal vrickningsroll (Vertical sculling)', 4, 0, 0 UNION ALL
    SELECT 14, '14. Vrickningsroll med paddeln på fördäck (Sculling roll with paddle on foredeck)', 4, 0, 0 UNION ALL
    SELECT 15, '15. Vrickningsroll med paddeln över Isserfik (Sculling roll with paddle on Isserfik)', 5, 0, 0 UNION ALL
    SELECT 16, '16. Stormroll med korsade armar (Storm roll with the arms crossed)', 5, 0, 0 UNION ALL
    SELECT 17, '17. Vrickninsroll med paddeln under kajaken (Sculling roll with paddle held under the kayak)', 5, 0, 0 UNION ALL
    SELECT 18, '18. Hastighetsroll – Storm roll (Quick succession of storm rolls) in 10s.', 0, 1, 0 UNION ALL
    SELECT 19, '19. Hastighetsroll – Standard roll (Quick succession of standard rolls) in 10s.', 0, 1, 0 UNION ALL
    SELECT 20, '20. Standardroll med Avataq (Roll with hunting float)', 5, 0, 0 UNION ALL
    SELECT 21, '21. Norsaqroll från för till för (Norsaq F2F)', 6, 0, 0 UNION ALL
    SELECT 22, '22. Norsaqroll från akter till för (Norsaq A2F)', 6, 0, 0 UNION ALL
    SELECT 23, '23. Norsaqroll från för till akter (Norsaq F2A)', 6, 0, 0 UNION ALL
    SELECT 24, '24. Handroll från för till för (Handroll F2F)', 7, 0, 0 UNION ALL
    SELECT 25, '25. Handroll från akter till för (Handroll A2F)', 7, 0, 0 UNION ALL
    SELECT 26, '26. Handroll från för till akter (Handroll A2F)', 7, 0, 0 UNION ALL
    SELECT 27, '27. Knuten näve från för till för (Clenched fist F2F)', 8, 0, 0 UNION ALL
    SELECT 28, '28. Knuten näve från akter till för (Clenched fist A2F)', 8, 0, 0 UNION ALL
    SELECT 29, '29. Knuten näve från akter till för (Clenched fist A2F)', 8, 0, 0 UNION ALL
    SELECT 30, '30. Roll med sten från för till för (Rock roll F2F)', 9, 0, 0 UNION ALL
    SELECT 31, '31. Roll med sten från akter till för (Rock roll A2F)', 9, 0, 0 UNION ALL
    SELECT 32, '32. Roll med sten från för till akter (Rock roll F2A)', 9, 0, 0 UNION ALL
    SELECT 33, '33. Armbågsroll (Elbow roll)', 10, 0, 0 UNION ALL
    SELECT 34, '34. Tvångströjaroll (Straight jacket roll)', 11, 0, 0 UNION ALL
    SELECT 35, '35. Distanspaddling upp och ner (Paddle upside down)', 0, 1, 1 UNION ALL
    SELECT 36, '36. Handbojeroll (Handcuff roll)', 10, 0, 0 UNION ALL
    SELECT 37, '37. Armågsroll från akter till för (Elbow roll from aft to fore)', 12, 0, 0
) AS t
WHERE @existing_snapshot = 0;

-- ─── Verify ──────────────────────────────────────────────────────────────
SELECT id, name, wp_post_id, competition_id, class_id
FROM `Z8NK3nsyu_comp_competitors`
WHERE wp_post_id IN (2271, 2277);

SELECT COUNT(*) AS junior_snapshot_rows
FROM `Z8NK3nsyu_comp_competition_rolls`
WHERE competition_id = 2 AND class_id = @class_junior;
-- Expect 37.

-- If both look right:
COMMIT;
-- otherwise: ROLLBACK;

-- AFTER COMMIT: wp-admin → Competitors → Settings → "Rescue Missing Scores"
-- to import competitor_scores postmeta into comp_scores. Rumer and Idun will
-- be picked up automatically because (comp=2, class=junior) now has the
-- 37-roll snapshot to map their score arrays against.

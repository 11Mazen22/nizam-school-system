-- Nizam School Management System -- Seed: grades
--
-- IMPLEMENTATION CHOICE (see 005_subjects.sql's note -- same reasoning applies, and
-- is spelled out in full in the Phase 3 verification report): the frozen blueprint
-- never pins an exact grade list either. The original brief's own grade examples
-- (item 6) name exactly three -- "First Grade, Second Grade, Third Grade" -- clearly
-- a truncated illustration, not a claim about how many grades a real school has.
-- Rather than invent a school-stage structure (elementary/prep/secondary counts vary
-- by country and by school) with no textual basis, this seed sticks to precisely
-- what the brief itself names, nothing more -- a minimal, honestly-incomplete
-- starter set the admin extends via the Grades module (Phase 6) to match their
-- actual school. grades is NOT UNIQUE-keyed on name (§D), so this uses a
-- NOT EXISTS guard for idempotency instead of ON DUPLICATE KEY UPDATE.

INSERT INTO grades (name_en, name_ar, sort_order, is_active)
SELECT * FROM (SELECT 'First Grade'  AS name_en, 'الصف الأول'  AS name_ar, 1 AS sort_order, 1 AS is_active) AS s
WHERE NOT EXISTS (SELECT 1 FROM grades WHERE sort_order = 1);

INSERT INTO grades (name_en, name_ar, sort_order, is_active)
SELECT * FROM (SELECT 'Second Grade' AS name_en, 'الصف الثاني' AS name_ar, 2 AS sort_order, 1 AS is_active) AS s
WHERE NOT EXISTS (SELECT 1 FROM grades WHERE sort_order = 2);

INSERT INTO grades (name_en, name_ar, sort_order, is_active)
SELECT * FROM (SELECT 'Third Grade'  AS name_en, 'الصف الثالث' AS name_ar, 3 AS sort_order, 1 AS is_active) AS s
WHERE NOT EXISTS (SELECT 1 FROM grades WHERE sort_order = 3);

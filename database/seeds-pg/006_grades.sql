-- Nizam School Management System (Postgres/Supabase) -- Seed: grades
-- Same starter list and same NOT EXISTS idempotency guard as
-- database/seeds/006_grades.sql (portable ANSI SQL -- unchanged by the
-- Postgres translation) -- see that file's docblock for the full "why only
-- these three, from the original brief item 6" reasoning.

INSERT INTO grades (name_en, name_ar, sort_order, is_active)
SELECT * FROM (SELECT 'First Grade'  AS name_en, 'الصف الأول'  AS name_ar, 1 AS sort_order, 1 AS is_active) AS s
WHERE NOT EXISTS (SELECT 1 FROM grades WHERE sort_order = 1);

INSERT INTO grades (name_en, name_ar, sort_order, is_active)
SELECT * FROM (SELECT 'Second Grade' AS name_en, 'الصف الثاني' AS name_ar, 2 AS sort_order, 1 AS is_active) AS s
WHERE NOT EXISTS (SELECT 1 FROM grades WHERE sort_order = 2);

INSERT INTO grades (name_en, name_ar, sort_order, is_active)
SELECT * FROM (SELECT 'Third Grade'  AS name_en, 'الصف الثالث' AS name_ar, 3 AS sort_order, 1 AS is_active) AS s
WHERE NOT EXISTS (SELECT 1 FROM grades WHERE sort_order = 3);

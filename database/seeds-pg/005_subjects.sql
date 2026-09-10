-- Nizam School Management System (Postgres/Supabase) -- Seed: subjects
-- Same starter list as database/seeds/005_subjects.sql -- see that file's
-- docblock for the full "why these seven, from the original brief item 5"
-- reasoning. Trivially editable afterward via the Subjects module.

INSERT INTO subjects (code, name_en, name_ar, is_active) VALUES
  ('AR',   'Arabic',           'اللغة العربية',       1),
  ('EN',   'English',          'اللغة الإنجليزية',   1),
  ('MATH', 'Mathematics',      'الرياضيات',           1),
  ('SCI',  'Science',          'العلوم',              1),
  ('SOC',  'Social Studies',   'الدراسات الاجتماعية', 1),
  ('CS',   'Computer',         'الحاسوب',             1),
  ('REL',  'Religious Studies','التربية الدينية',     1)
ON CONFLICT (code) DO UPDATE SET
  name_en = EXCLUDED.name_en,
  name_ar = EXCLUDED.name_ar;

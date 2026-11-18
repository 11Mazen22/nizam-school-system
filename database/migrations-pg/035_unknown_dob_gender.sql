-- Nizam School Management System (Postgres/Supabase)
-- Migration 035: nullable roster fields on students, timetable group note.
-- Postgres translation of database/migrations/026_unknown_dob_gender.sql.
--
-- All 17 roster pages were checked: no birth date and no gender column exists
-- on any of them (the PDF text contains zero date-like tokens and only the
-- اسم الطالب / الديانة\اللغة الثانية headers), and the religion column is
-- ambiguous shorthand for some rows. Rather than invent any of these values,
-- a roster-imported student may store NULL and be surfaced as "not recorded"
-- until the school supplies it. The manual student form still requires all
-- three fields -- this nullability exists for the import path, and the
-- students-list data-quality counters read it.

ALTER TABLE students
  ALTER COLUMN gender        DROP NOT NULL,
  ALTER COLUMN date_of_birth DROP NOT NULL,
  ALTER COLUMN religion      DROP NOT NULL;

COMMENT ON COLUMN students.gender        IS 'NULL = not recorded (official roster prints no gender)';
COMMENT ON COLUMN students.date_of_birth IS 'NULL = not recorded (official roster prints no birth date)';
COMMENT ON COLUMN students.religion      IS 'NULL = not recorded (the printed attribute column is ambiguous)';

-- Group-language slots (printed "G+F" or "G+A") carry two teachers in one
-- cell. timetables allows one teacher per slot, so the slot stores the first
-- teacher and the printed pair in note.
ALTER TABLE timetables ADD COLUMN IF NOT EXISTS note VARCHAR(190) NULL;
COMMENT ON COLUMN timetables.note IS 'Source annotation, e.g. G+F group pair: خالد / إيناس';

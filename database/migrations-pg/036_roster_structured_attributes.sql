-- Migration 036: source-backed structured roster attributes
--
-- Keep the combined printed cell verbatim in class_attribute_raw and expose
-- only source-proved structured values. No default is used: an absent value is
-- unknown, never an inferred religion/language.

ALTER TABLE students ADD COLUMN IF NOT EXISTS gender_source VARCHAR(40) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS religion_source VARCHAR(40) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS second_language VARCHAR(20) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS second_language_source VARCHAR(40) NULL;

ALTER TABLE students DROP CONSTRAINT IF EXISTS chk_students_second_language;
ALTER TABLE students ADD CONSTRAINT chk_students_second_language
  CHECK (second_language IS NULL OR second_language IN ('german', 'french', 'spanish'));

COMMENT ON COLUMN students.gender_source IS 'manual, imported_csv, or official_roster_layout';
COMMENT ON COLUMN students.religion_source IS 'manual/imported_csv or an official roster decoding rule';
COMMENT ON COLUMN students.second_language IS 'Normalized language only when proved by the printed cell or class heading';
COMMENT ON COLUMN students.second_language_source IS 'printed_cell, class_heading, imported_csv, or manual';

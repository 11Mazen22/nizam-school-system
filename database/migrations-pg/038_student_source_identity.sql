-- Migration 038: source identity for students
--
-- PostgreSQL counterpart of MySQL 034. The national ID is unique when present;
-- NULLs are allowed and are not treated as duplicates.

ALTER TABLE students ADD COLUMN IF NOT EXISTS national_id VARCHAR(30) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS source_student_code VARCHAR(30) NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_students_national_id ON students (national_id);
CREATE INDEX IF NOT EXISTS idx_students_source_code ON students (source_student_code);

COMMENT ON COLUMN students.national_id IS 'National ID from the official source spreadsheet';
COMMENT ON COLUMN students.source_student_code IS 'Student code as printed by the school source';

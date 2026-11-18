-- Migration 034: source identity for students
--
-- The third-secondary spreadsheet carries a school student code and the
-- national ID for every student. Store them so imported students can be
-- matched idempotently on later runs and the source record stays traceable.

ALTER TABLE students
  ADD COLUMN national_id VARCHAR(30) NULL DEFAULT NULL AFTER student_code,
  ADD COLUMN source_student_code VARCHAR(30) NULL DEFAULT NULL AFTER national_id,
  ADD UNIQUE KEY uq_students_national_id (national_id),
  ADD KEY idx_students_source_code (source_student_code);

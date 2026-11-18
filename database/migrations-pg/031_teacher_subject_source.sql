-- Nizam -- PostgreSQL twin of database/migrations/029_teacher_subject_source.sql.
-- Only 'declared' rows satisfy the assignment qualification warning; rows the
-- timetable sync writes are evidence of teaching and carry source 'timetable'.

ALTER TABLE teacher_subjects
  ADD COLUMN IF NOT EXISTS source VARCHAR(10) NOT NULL DEFAULT 'declared';

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'chk_teacher_subjects_source'
  ) THEN
    ALTER TABLE teacher_subjects
      ADD CONSTRAINT chk_teacher_subjects_source
      CHECK (source IN ('declared','timetable'));
  END IF;
END
$$;

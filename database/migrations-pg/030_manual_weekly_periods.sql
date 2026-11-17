-- Nizam -- PostgreSQL twin of database/migrations/028_manual_weekly_periods.sql.
-- Existing rows default to 'manual' so the timetable sync never overwrites a
-- load that may have been entered by the school before this column existed.

ALTER TABLE teacher_assignments
  ADD COLUMN IF NOT EXISTS weekly_periods_source VARCHAR(10) NOT NULL DEFAULT 'manual';

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'chk_teacher_assignments_periods_source'
  ) THEN
    ALTER TABLE teacher_assignments
      ADD CONSTRAINT chk_teacher_assignments_periods_source
      CHECK (weekly_periods_source IN ('manual','timetable'));
  END IF;
END
$$;

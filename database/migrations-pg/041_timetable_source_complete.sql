-- Nizam -- the timetable must hold every cell the school actually prints.
-- See database/migrations/041_timetable_source_complete.sql for the full
-- reasoning: activity periods are printed without a teacher, and the paper
-- itself double-books a few first names, so the table must be able to store
-- both faithfully instead of leaving holes in the class timetable.

ALTER TABLE timetables ALTER COLUMN teacher_id DROP NOT NULL;

DROP INDEX IF EXISTS uq_timetable_teacher_slot;

CREATE INDEX IF NOT EXISTS idx_timetable_teacher_slot
  ON timetables (academic_year_id, teacher_id, day_of_week, period_number);

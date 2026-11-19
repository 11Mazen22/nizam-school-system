-- Nizam -- restore the database guarantee that a teacher is never booked in
-- two rooms in the same period, without losing what the paper printed.
-- See database/migrations/042_timetable_teacher_slot_guarantee.sql.

ALTER TABLE timetables ADD COLUMN printed_teacher VARCHAR(150);

WITH ranked AS (
  SELECT id,
         ROW_NUMBER() OVER (
           PARTITION BY academic_year_id, teacher_id, day_of_week, period_number
           ORDER BY class_id, id
         ) AS position
  FROM timetables
  WHERE teacher_id IS NOT NULL
)
UPDATE timetables t
SET printed_teacher = (SELECT te.full_name FROM teachers te WHERE te.id = t.teacher_id),
    teacher_id      = NULL
FROM ranked r
WHERE r.id = t.id AND r.position > 1;

DROP INDEX IF EXISTS idx_timetable_teacher_slot;

CREATE UNIQUE INDEX uq_timetable_teacher_slot
  ON timetables (academic_year_id, teacher_id, day_of_week, period_number);

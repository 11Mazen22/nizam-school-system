-- Nizam -- restore the database guarantee that a teacher is never booked in
-- two rooms in the same period, without losing what the paper printed.
--
-- Migration 041 relaxed the teacher/slot unique key so the printed grids could
-- load faithfully. That was the wrong trade: the class timetable must be
-- complete, but the database must not be able to record a double-booking.
--
-- For each printed clash exactly one class keeps the teacher (the lower class
-- id); the other lesson keeps the printed name in printed_teacher and no
-- teacher_id, so the class timetable still says "إيمان" and marks it as a
-- paper clash. The unique teacher/slot key is then restored, so even a raw
-- fixture insert is rejected by the database itself.
--
-- An installation that ran an earlier draft of this file (which cleared both
-- sides of a clash) should run `php bin/repair-printed-clashes.php` once; it is
-- idempotent and puts the teacher back on the first class of each clash.

ALTER TABLE timetables ADD COLUMN printed_teacher VARCHAR(150) NULL AFTER teacher_id;

CREATE TEMPORARY TABLE nz_clashing_lessons (id INT UNSIGNED NOT NULL PRIMARY KEY);

INSERT INTO nz_clashing_lessons (id)
SELECT id FROM (
  SELECT id,
         ROW_NUMBER() OVER (
           PARTITION BY academic_year_id, teacher_id, day_of_week, period_number
           ORDER BY class_id, id
         ) AS position
  FROM timetables
  WHERE teacher_id IS NOT NULL
) ranked
WHERE position > 1;

UPDATE timetables t
JOIN nz_clashing_lessons c ON c.id = t.id
SET t.printed_teacher = (SELECT te.full_name FROM teachers te WHERE te.id = t.teacher_id),
    t.teacher_id      = NULL;

DROP TEMPORARY TABLE nz_clashing_lessons;

ALTER TABLE timetables DROP INDEX idx_timetable_teacher_slot;

ALTER TABLE timetables
  ADD UNIQUE KEY uq_timetable_teacher_slot (academic_year_id, teacher_id, day_of_week, period_number);

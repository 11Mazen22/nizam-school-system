-- Nizam -- distinguish a declared qualification from evidence of teaching.
--
-- teacher_subjects began as the list of subjects a teacher is qualified for;
-- the timetable load sync then wrote every taught pair into the same table,
-- which made the "not qualified" warning on the assignment screen meaningless.
-- Every row now records its origin:
--   declared  = entered on the teacher form (a real qualification claim)
--   timetable = derived from a printed lesson (evidence of teaching, not a claim)
-- Only declared rows satisfy the qualification check.

ALTER TABLE teacher_subjects
  ADD COLUMN source ENUM('declared','timetable') NOT NULL DEFAULT 'declared'
    COMMENT 'declared = set on the teacher form; timetable = derived from a printed lesson'
    AFTER subject_id;

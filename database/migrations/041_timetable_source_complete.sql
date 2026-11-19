-- Nizam -- the timetable must hold every cell the school actually prints.
--
-- Two facts about the printed 2026/2027 grids forced this migration:
--
--  1. Activity cells (sports, financial literacy, chemistry / business,
--     psychology / second language) are printed with a subject and no teacher
--     at all. They are real scheduled periods, not missing data, so the class
--     timetable has to be able to store them without inventing a staff member.
--
--  2. The grids print first names only. The same first name appears in two
--     classes during the same period in a handful of cells, which the paper
--     itself double-books. Refusing to import those cells left holes in the
--     class timetable that the school could see but not explain.
--
-- Nullable teacher_id + a non-unique teacher/slot index keeps the class grid
-- complete and faithful to the printed page. Manual editing still refuses a
-- double-booked teacher (TimetableService::detectConflicts); the timetable
-- screen reports imported paper clashes as warnings instead of hiding them.

ALTER TABLE timetables MODIFY teacher_id INT UNSIGNED NULL;

ALTER TABLE timetables DROP INDEX uq_timetable_teacher_slot;

ALTER TABLE timetables
  ADD INDEX idx_timetable_teacher_slot (academic_year_id, teacher_id, day_of_week, period_number);

-- Nizam -- remember whether a teaching load was entered by the school or
-- derived from the printed timetable.
--
-- The timetable sync corrects weekly_periods from the grid. A value the school
-- edits by hand (a split class, a cover arrangement, a corrected load) must
-- not be silently overwritten on the next sync. Every assignment records where
-- its weekly periods came from: 'manual' (the assignment screen, including any
-- later edit) or 'timetable' (derived from the printed cells).
--
-- Existing assignments default to 'manual': the conservative choice, because
-- a row that pre-dates this column may have been entered by the school.

ALTER TABLE teacher_assignments
  ADD COLUMN weekly_periods_source ENUM('manual','timetable') NOT NULL DEFAULT 'manual'
    COMMENT 'manual = entered or edited on the assignment screen; timetable = derived from printed cells'
    AFTER weekly_periods;

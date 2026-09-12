-- Nizam School Management System
-- Migration 016: timetables
-- One slot = one class at one time (day+period). Conflict detection enforced
-- at the DB level via two UNIQUE constraints:
--   1. uq_timetable_class_slot  -- a class can't have two subjects at the same time
--   2. uq_timetable_teacher_slot -- a teacher can't be in two places at once
-- Application layer checks first (TimetableService::detectConflicts) to give
-- a friendly error; these UNIQUEs are the hard backstop.

CREATE TABLE timetables (
  id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED    NOT NULL,
  class_id         INT UNSIGNED    NOT NULL,
  day_of_week      TINYINT UNSIGNED NOT NULL COMMENT '1=Saturday 7=Friday (school week)',
  period_number    TINYINT UNSIGNED NOT NULL COMMENT '1-based period number',
  subject_id       INT UNSIGNED    NOT NULL,
  teacher_id       INT UNSIGNED    NOT NULL,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_timetable_class_slot (academic_year_id, class_id, day_of_week, period_number),
  UNIQUE KEY uq_timetable_teacher_slot (academic_year_id, teacher_id, day_of_week, period_number),
  KEY idx_timetable_year_class (academic_year_id, class_id),
  CONSTRAINT fk_timetable_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_class
    FOREIGN KEY (class_id) REFERENCES classes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_timetable_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

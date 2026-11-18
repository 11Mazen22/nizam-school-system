-- Nizam -- substitute cover for a single dated lesson.
--
-- HR already records approved leave, but there was no way to say who teaches
-- a lesson while its regular teacher is away without editing the home
-- timetable. A cover row leaves the timetable untouched and applies only to
-- one date, so the weekly grid stays the school's official schedule.
--
-- One cover per lesson per date; assigning again replaces the cover teacher.

CREATE TABLE IF NOT EXISTS lesson_covers (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id    INT UNSIGNED NOT NULL,
  cover_date          DATE NOT NULL,
  timetable_id        INT UNSIGNED NOT NULL,
  class_id            INT UNSIGNED NOT NULL,
  period_number       TINYINT UNSIGNED NOT NULL,
  original_teacher_id INT UNSIGNED NOT NULL,
  cover_teacher_id    INT UNSIGNED NOT NULL,
  note                VARCHAR(255) NULL,
  created_by          INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cover_lesson_date (cover_date, timetable_id),
  KEY idx_cover_date_period (cover_date, period_number),
  KEY idx_cover_teacher_date (cover_teacher_id, cover_date),
  CONSTRAINT fk_cover_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cover_timetable
    FOREIGN KEY (timetable_id) REFERENCES timetables(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cover_class
    FOREIGN KEY (class_id) REFERENCES classes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cover_original_teacher
    FOREIGN KEY (original_teacher_id) REFERENCES teachers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cover_teacher
    FOREIGN KEY (cover_teacher_id) REFERENCES teachers(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_cover_user
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

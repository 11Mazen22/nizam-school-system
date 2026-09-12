-- Nizam School Management System
-- Migration 013: attendance_records
-- Tracks daily student attendance per class. One record per student per date
-- (uq_attendance_student_date). Cascade-deletes with students and classes.

CREATE TABLE attendance_records (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  record_date      DATE         NOT NULL,
  status           ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
  notes            VARCHAR(255) NULL,
  recorded_by      INT UNSIGNED NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attendance_student_date (student_id, record_date),
  KEY idx_attendance_class_date (class_id, record_date),
  KEY idx_attendance_year_date (academic_year_id, record_date),
  CONSTRAINT fk_attendance_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_attendance_class
    FOREIGN KEY (class_id) REFERENCES classes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_attendance_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_attendance_recorder
    FOREIGN KEY (recorded_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Nizam School Management System
-- Migration 015: disciplinary_records, health_records
-- Student welfare module. Discipline covers infractions and rewards with
-- severity levels. Health covers allergies, medications, and clinic visits.

CREATE TABLE disciplinary_records (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  incident_date    DATE         NOT NULL,
  type             ENUM('infraction','reward') NOT NULL DEFAULT 'infraction',
  severity         ENUM('low','medium','high') NOT NULL DEFAULT 'low',
  title            VARCHAR(150) NOT NULL,
  description      TEXT         NOT NULL,
  action_taken     VARCHAR(255) NULL,
  reported_by      INT UNSIGNED NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_discipline_student_year (student_id, academic_year_id),
  KEY idx_discipline_year_date (academic_year_id, incident_date),
  CONSTRAINT fk_discipline_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_discipline_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_discipline_reporter
    FOREIGN KEY (reported_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE health_records (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id  INT UNSIGNED NOT NULL,
  record_type ENUM('allergy','medication','clinic_visit','condition') NOT NULL,
  date_logged DATE         NOT NULL,
  title       VARCHAR(150) NOT NULL,
  details     TEXT         NOT NULL,
  logged_by   INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_health_student_type (student_id, record_type),
  CONSTRAINT fk_health_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_health_logger
    FOREIGN KEY (logged_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

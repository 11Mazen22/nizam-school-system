-- Migration 035: per-enrollment academic track and year-work entries
--
-- Egyptian secondary education splits into General Secondary (ثانوية عامة)
-- and Baccalaureate (بكالوريا). The track belongs to the enrollment, because a
-- student can follow a different track in a different academic year. Year-work
-- (أعمال السنة) entries are stored separately from final exam scores so they
-- are never derived from attendance.

ALTER TABLE student_enrollments
  ADD COLUMN academic_track VARCHAR(30) NULL DEFAULT NULL AFTER class_id,
  ADD KEY idx_enrollment_track (academic_track);

CREATE TABLE year_work_entries (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  entry_date       DATE NOT NULL,
  category         VARCHAR(40) NOT NULL COMMENT 'weekly_assessment | monthly_assessment | class_work | homework',
  label            VARCHAR(150) NULL,
  source_key       VARCHAR(80) NULL COMMENT 'Idempotency key from the source (e.g. WEEK-2026-12-A1)',
  score            DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  max_score        DECIMAL(6,2) NOT NULL DEFAULT 100.00,
  source           VARCHAR(40) NOT NULL DEFAULT 'manual',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_year_work_source (academic_year_id, student_id, source_key),
  KEY idx_year_work_student (student_id, academic_year_id),
  CONSTRAINT fk_year_work_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT,
  CONSTRAINT fk_year_work_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

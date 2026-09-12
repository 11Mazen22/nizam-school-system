-- Nizam School Management System
-- Migration 014: exams, exam_scores
-- Supports multiple exam terms per year (Term 1, Term 2, ...).
-- Scores are per student per subject per exam. UNIQUE prevents duplicates.

CREATE TABLE exams (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED  NOT NULL,
  name_en          VARCHAR(100)  NOT NULL,
  name_ar          VARCHAR(100)  NOT NULL,
  term             TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Term 1, 2 = Term 2, etc.',
  max_score        DECIMAL(5,2)  NOT NULL DEFAULT 100.00,
  weight           DECIMAL(5,2)  NOT NULL DEFAULT 100.00 COMMENT 'Percentage weight in final GPA calculation',
  is_active        TINYINT(1)    NOT NULL DEFAULT 1,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_exams_year_term (academic_year_id, term),
  CONSTRAINT fk_exams_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE exam_scores (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id     INT UNSIGNED NOT NULL,
  student_id  INT UNSIGNED NOT NULL,
  subject_id  INT UNSIGNED NOT NULL,
  score       DECIMAL(5,2) NULL COMMENT 'NULL = not yet recorded',
  notes       VARCHAR(255) NULL,
  recorded_by INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_score_exam_student_subject (exam_id, student_id, subject_id),
  KEY idx_scores_student (student_id),
  KEY idx_scores_exam (exam_id),
  CONSTRAINT fk_scores_exam
    FOREIGN KEY (exam_id) REFERENCES exams(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_scores_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_scores_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_scores_recorder
    FOREIGN KEY (recorded_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

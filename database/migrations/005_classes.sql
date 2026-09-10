-- Nizam School Management System
-- Migration 005: classes
-- Blueprint reference: §D, §S-1/§S-2 (composite foreign keys on the child tables
-- created in migrations 006 and 007 -- both reference this table's composite keys)

CREATE TABLE classes (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  grade_id          INT UNSIGNED NOT NULL,
  academic_year_id  INT UNSIGNED NOT NULL,
  name              VARCHAR(30)  NOT NULL COMMENT 'e.g. A, B',
  capacity          INT UNSIGNED NULL,
  is_active         TINYINT(1)   NOT NULL DEFAULT 1,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_classes_grade_year_name (grade_id, academic_year_id, name),
  -- The two indexes below both exist purely so child tables can hold a composite
  -- foreign key against "this class's own grade/year" (§S-1, §S-2). MySQL/MariaDB
  -- require the referenced columns to be the LEFTMOST columns of some index on the
  -- parent table -- and the two children need different shapes (3 columns for
  -- student_enrollments, 2 for teacher_assignments), so both are declared explicitly
  -- rather than relying on one wider index to serve both.
  UNIQUE KEY uq_classes_id_grade_year (id, grade_id, academic_year_id),
  UNIQUE KEY uq_classes_id_year (id, academic_year_id),
  CONSTRAINT fk_classes_grade
    FOREIGN KEY (grade_id) REFERENCES grades(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_classes_academic_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

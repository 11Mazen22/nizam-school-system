-- Nizam School Management System
-- Migration 006: students, student_enrollments
-- Blueprint reference: §D ("Why student_enrollments exists"), §O-3 (graduated status),
-- §O-7 (ID generation), §S-1 (composite FK), §S-7 (immutability)

CREATE TABLE students (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_code   VARCHAR(20)  NOT NULL COMMENT 'Assigned after insert from the row''s own id -- §O-7, never precomputed',
  full_name      VARCHAR(150) NOT NULL,
  gender         ENUM('m','f') NOT NULL,
  date_of_birth  DATE         NOT NULL,
  religion       ENUM('muslim','christian','other') NOT NULL,
  phone          VARCHAR(30)  NULL,
  guardian_phone VARCHAR(30)  NULL,
  address        VARCHAR(255) NULL,
  photo_path     VARCHAR(255) NULL,
  notes          TEXT         NULL,
  status         ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_students_student_code (student_code),
  KEY idx_students_status (status),
  KEY idx_students_full_name (full_name)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- idx_students_full_name: justified directly by the Phase 3 kickoff's own indexing
-- checklist ("student search"). idx_students_status: §D's index strategy, explicit.

CREATE TABLE student_enrollments (
  id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id              INT UNSIGNED NOT NULL,
  academic_year_id        INT UNSIGNED NOT NULL,
  grade_id                INT UNSIGNED NOT NULL,
  class_id                INT UNSIGNED NULL COMMENT 'NULL = promoted but not yet placed in a class, §I.1 step 4',
  enrollment_date         DATE         NOT NULL,
  status                  ENUM('active','promoted','repeated','graduated','transferred','withdrawn')
                           NOT NULL DEFAULT 'active',
  previous_enrollment_id  INT UNSIGNED NULL,
  created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enrollments_student_year (student_id, academic_year_id),
  KEY idx_enrollments_year_class (academic_year_id, class_id),
  CONSTRAINT fk_enrollments_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  -- Baseline referential integrity for grade_id/academic_year_id on their own --
  -- needed in addition to the composite FK below, because a composite FK with any
  -- NULL member (class_id, when unassigned) is skipped ENTIRELY by MySQL/MariaDB,
  -- which would otherwise leave grade_id/academic_year_id completely unchecked
  -- against any table whenever a promoted student has no class yet. This closes
  -- that gap; it is a refinement of §S-1 required to fully deliver what §S-1 itself
  -- already intended -- see the Phase 3 verification report.
  CONSTRAINT fk_enrollments_grade
    FOREIGN KEY (grade_id) REFERENCES grades(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_enrollments_academic_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  -- §S-1: this composite FK is the actual cross-consistency guarantee -- the
  -- database rejects an enrollment whose grade/year disagrees with the class it
  -- names. NULL class_id (the unassigned state above) is simply not checked by
  -- ordinary FK semantics -- exactly the case that must stay legal.
  CONSTRAINT fk_enrollments_class_grade_year
    FOREIGN KEY (class_id, grade_id, academic_year_id)
    REFERENCES classes(id, grade_id, academic_year_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_enrollments_previous
    FOREIGN KEY (previous_enrollment_id) REFERENCES student_enrollments(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- §S-7 (immutability) and the Reassign-Class action (§I.9) are application-layer
-- rules enforced in the service layer built in later phases -- there is no database
-- trigger preventing an UPDATE to a non-active row, matching the same "no triggers"
-- discipline already established for teacher-qualification checking (§O-9).

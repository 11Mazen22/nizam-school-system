-- Nizam School Management System
-- Migration 007: teachers, teacher_subjects, teacher_assignments
-- Blueprint reference: §D, §O-9 (qualification is a soft/application-layer check,
-- not a trigger), §S-2 (composite FK)

CREATE TABLE teachers (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  teacher_code VARCHAR(20)  NOT NULL COMMENT 'Assigned after insert from the row''s own id -- §O-7',
  full_name    VARCHAR(150) NOT NULL,
  phone        VARCHAR(30)  NULL,
  email        VARCHAR(100) NULL,
  photo_path   VARCHAR(255) NULL,
  status       ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_teachers_teacher_code (teacher_code),
  KEY idx_teachers_full_name (full_name)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- idx_teachers_full_name: justified directly by the Phase 3 kickoff's indexing
-- checklist ("teacher search").

CREATE TABLE teacher_subjects (
  teacher_id  INT UNSIGNED NOT NULL,
  subject_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (teacher_id, subject_id),
  CONSTRAINT fk_teacher_subjects_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_teacher_subjects_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Pure join table (qualifications) -- CASCADE, matching role_permissions/§D.

CREATE TABLE teacher_assignments (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  teacher_id        INT UNSIGNED NOT NULL,
  subject_id        INT UNSIGNED NOT NULL,
  class_id          INT UNSIGNED NOT NULL,
  academic_year_id  INT UNSIGNED NOT NULL,
  weekly_periods    SMALLINT UNSIGNED NOT NULL COMMENT 'Validated >0 in the service layer, not a CHECK constraint -- §O-22',
  status            ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assignments_teacher_subject_class_year (teacher_id, subject_id, class_id, academic_year_id),
  KEY idx_assignments_year_teacher (academic_year_id, teacher_id),
  CONSTRAINT fk_assignments_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_assignments_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  -- §S-2: class_id and academic_year_id are NOT NULL here (an assignment is always
  -- created fully-formed, unlike an enrollment, which can be promoted-but-unassigned)
  -- so this single composite FK is sufficient on its own -- it is always checked,
  -- never skipped, and transitively guarantees academic_year_id is valid too.
  CONSTRAINT fk_assignments_class_year
    FOREIGN KEY (class_id, academic_year_id)
    REFERENCES classes(id, academic_year_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Teacher-qualification checking (§O-9) is an application-layer warn-not-block rule
-- in AssignmentService (Phase 6) -- no trigger, by design.

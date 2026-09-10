-- Nizam School Management System (Postgres/Supabase)
-- Migration 007: teachers, teacher_subjects, teacher_assignments
-- Postgres translation of database/migrations/007_teachers_assignments.sql
-- (§O-9 qualification is a soft/application-layer check, not a trigger; §S-2 composite FK)

CREATE TABLE teachers (
  id           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  teacher_code VARCHAR(20)  NOT NULL UNIQUE,  -- assigned after insert from the row's own id, §O-7
  full_name    VARCHAR(150) NOT NULL,
  phone        VARCHAR(30)  NULL,
  email        VARCHAR(100) NULL,
  photo_path   VARCHAR(255) NULL,
  status       VARCHAR(10)  NOT NULL DEFAULT 'active' CHECK (status IN ('active','archived')),
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_teachers_full_name ON teachers (full_name);
CREATE TRIGGER trg_teachers_updated_at
  BEFORE UPDATE ON teachers
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE teacher_subjects (
  teacher_id  INTEGER NOT NULL,
  subject_id  INTEGER NOT NULL,
  PRIMARY KEY (teacher_id, subject_id),
  CONSTRAINT fk_teacher_subjects_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_teacher_subjects_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE teacher_assignments (
  id                INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  teacher_id        INTEGER  NOT NULL,
  subject_id        INTEGER  NOT NULL,
  class_id          INTEGER  NOT NULL,
  academic_year_id  INTEGER  NOT NULL,
  weekly_periods    SMALLINT NOT NULL,  -- validated >0 in the service layer, not a CHECK constraint -- §O-22
  status            VARCHAR(10) NOT NULL DEFAULT 'active' CHECK (status IN ('active','archived')),
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_assignments_teacher_subject_class_year UNIQUE (teacher_id, subject_id, class_id, academic_year_id),
  CONSTRAINT fk_assignments_teacher
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_assignments_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  -- §S-2: class_id/academic_year_id are NOT NULL here (an assignment is
  -- always created fully-formed) so this one composite FK is sufficient --
  -- always checked, never skipped.
  CONSTRAINT fk_assignments_class_year
    FOREIGN KEY (class_id, academic_year_id)
    REFERENCES classes(id, academic_year_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);
CREATE INDEX idx_assignments_year_teacher ON teacher_assignments (academic_year_id, teacher_id);
CREATE TRIGGER trg_assignments_updated_at
  BEFORE UPDATE ON teacher_assignments
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

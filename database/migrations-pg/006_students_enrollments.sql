-- Nizam School Management System (Postgres/Supabase)
-- Migration 006: students, student_enrollments
-- Postgres translation of database/migrations/006_students_enrollments.sql
-- (§O-3 graduated status, §O-7 ID generation, §S-1 composite FK, §S-7 immutability)

CREATE TABLE students (
  id             INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  student_code   VARCHAR(20)  NOT NULL UNIQUE,  -- assigned after insert from the row's own id, §O-7 -- never precomputed
  full_name      VARCHAR(150) NOT NULL,
  gender         VARCHAR(1)   NOT NULL CHECK (gender IN ('m','f')),
  date_of_birth  DATE         NOT NULL,
  religion       VARCHAR(10)  NOT NULL CHECK (religion IN ('muslim','christian','other')),
  phone          VARCHAR(30)  NULL,
  guardian_phone VARCHAR(30)  NULL,
  address        VARCHAR(255) NULL,
  photo_path     VARCHAR(255) NULL,
  notes          TEXT         NULL,
  status         VARCHAR(10)  NOT NULL DEFAULT 'active' CHECK (status IN ('active','archived')),
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_students_status ON students (status);
CREATE INDEX idx_students_full_name ON students (full_name);
CREATE TRIGGER trg_students_updated_at
  BEFORE UPDATE ON students
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE student_enrollments (
  id                      INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  student_id              INTEGER   NOT NULL,
  academic_year_id        INTEGER   NOT NULL,
  grade_id                INTEGER   NOT NULL,
  class_id                INTEGER   NULL,  -- NULL = promoted but not yet placed in a class, §I.1 step 4
  enrollment_date         DATE      NOT NULL,
  status                  VARCHAR(11) NOT NULL DEFAULT 'active'
                             CHECK (status IN ('active','promoted','repeated','graduated','transferred','withdrawn')),
  previous_enrollment_id  INTEGER   NULL,
  created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_enrollments_student_year UNIQUE (student_id, academic_year_id),
  CONSTRAINT fk_enrollments_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  -- Baseline FKs on grade_id/academic_year_id alone, in addition to the
  -- composite FK below: a composite FK with any NULL member (class_id, when
  -- unassigned) is skipped entirely by ordinary FK semantics in Postgres
  -- exactly as in MySQL, which would otherwise leave these two completely
  -- unchecked whenever a promoted student has no class yet.
  CONSTRAINT fk_enrollments_grade
    FOREIGN KEY (grade_id) REFERENCES grades(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_enrollments_academic_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  -- §S-1: the actual cross-consistency guarantee -- rejects an enrollment
  -- whose grade/year disagrees with the class it names. NULL class_id is
  -- simply not checked, exactly the case that must stay legal.
  CONSTRAINT fk_enrollments_class_grade_year
    FOREIGN KEY (class_id, grade_id, academic_year_id)
    REFERENCES classes(id, grade_id, academic_year_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_enrollments_previous
    FOREIGN KEY (previous_enrollment_id) REFERENCES student_enrollments(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);
CREATE INDEX idx_enrollments_year_class ON student_enrollments (academic_year_id, class_id);
-- §S-7 (immutability) and Reassign-Class (§I.9) remain application-layer
-- rules enforced in the service layer, matching the MySQL version exactly --
-- no database trigger preventing an UPDATE to a non-active row here either.

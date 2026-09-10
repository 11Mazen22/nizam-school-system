-- Nizam School Management System (Postgres/Supabase)
-- Migration 005: classes
-- Postgres translation of database/migrations/005_classes.sql
-- (§S-1/§S-2 composite FK support -- children reference this table's
-- (id, grade_id, academic_year_id) and (id, academic_year_id) tuples)

CREATE TABLE classes (
  id                INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  grade_id          INTEGER      NOT NULL,
  academic_year_id  INTEGER      NOT NULL,
  name              VARCHAR(30)  NOT NULL,  -- e.g. A, B
  capacity          INTEGER      NULL,
  is_active         SMALLINT     NOT NULL DEFAULT 1,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_classes_grade_year_name UNIQUE (grade_id, academic_year_id, name),
  -- Both exist purely so child tables can hold a composite FK against "this
  -- class's own grade/year" (§S-1, §S-2) -- Postgres has the same
  -- requirement MySQL does: the referenced columns must be covered by a
  -- unique constraint/index on the parent, and the two children need
  -- different shapes (3 columns vs 2).
  CONSTRAINT uq_classes_id_grade_year UNIQUE (id, grade_id, academic_year_id),
  CONSTRAINT uq_classes_id_year UNIQUE (id, academic_year_id),
  CONSTRAINT fk_classes_grade
    FOREIGN KEY (grade_id) REFERENCES grades(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_classes_academic_year
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TRIGGER trg_classes_updated_at
  BEFORE UPDATE ON classes
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- Nizam School Management System (Postgres/Supabase)
-- Migration 019: disciplinary_records, health_records

CREATE TABLE disciplinary_records (
  id               INTEGER      GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id INTEGER      NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
  student_id       INTEGER      NOT NULL REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
  incident_date    DATE         NOT NULL,
  type             VARCHAR(20)  NOT NULL DEFAULT 'infraction' CHECK (type IN ('infraction','reward')),
  severity         VARCHAR(20)  NOT NULL DEFAULT 'low' CHECK (severity IN ('low','medium','high')),
  title            VARCHAR(150) NOT NULL,
  description      TEXT         NOT NULL,
  action_taken     VARCHAR(255) NULL,
  reported_by      INTEGER      NULL REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_discipline_student_year ON disciplinary_records (student_id, academic_year_id);
CREATE INDEX idx_discipline_year_date    ON disciplinary_records (academic_year_id, incident_date);

CREATE TABLE health_records (
  id          INTEGER      GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  student_id  INTEGER      NOT NULL REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
  record_type VARCHAR(30)  NOT NULL CHECK (record_type IN ('allergy','medication','clinic_visit','condition')),
  date_logged DATE         NOT NULL,
  title       VARCHAR(150) NOT NULL,
  details     TEXT         NOT NULL,
  logged_by   INTEGER      NULL REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_health_student_type ON health_records (student_id, record_type);

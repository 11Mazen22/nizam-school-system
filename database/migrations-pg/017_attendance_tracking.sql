-- Nizam School Management System (Postgres/Supabase)
-- Migration 017: attendance_records

CREATE TABLE attendance_records (
  id               INTEGER       GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id INTEGER       NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
  class_id         INTEGER       NOT NULL REFERENCES classes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  student_id       INTEGER       NOT NULL REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE,
  record_date      DATE          NOT NULL,
  status           VARCHAR(20)   NOT NULL DEFAULT 'present' CHECK (status IN ('present','absent','late','excused')),
  notes            VARCHAR(255)  NULL,
  recorded_by      INTEGER       NULL REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX uq_attendance_student_date ON attendance_records (student_id, record_date);
CREATE INDEX idx_attendance_class_date ON attendance_records (class_id, record_date);
CREATE INDEX idx_attendance_year_date  ON attendance_records (academic_year_id, record_date);

CREATE TRIGGER trg_attendance_updated_at
  BEFORE UPDATE ON attendance_records
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

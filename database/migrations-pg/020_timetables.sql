-- Nizam School Management System (Postgres/Supabase)
-- Migration 020: timetables

CREATE TABLE timetables (
  id               INTEGER  GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id INTEGER  NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
  class_id         INTEGER  NOT NULL REFERENCES classes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  day_of_week      SMALLINT NOT NULL,
  period_number    SMALLINT NOT NULL,
  subject_id       INTEGER  NOT NULL REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  teacher_id       INTEGER  NOT NULL REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX uq_timetable_class_slot   ON timetables (academic_year_id, class_id, day_of_week, period_number);
CREATE UNIQUE INDEX uq_timetable_teacher_slot ON timetables (academic_year_id, teacher_id, day_of_week, period_number);
CREATE INDEX idx_timetable_year_class         ON timetables (academic_year_id, class_id);

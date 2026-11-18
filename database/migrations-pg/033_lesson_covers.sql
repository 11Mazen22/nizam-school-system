-- Nizam -- PostgreSQL twin of database/migrations/030_lesson_covers.sql.

CREATE TABLE IF NOT EXISTS lesson_covers (
  id                  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id    INTEGER NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
  cover_date          DATE NOT NULL,
  timetable_id        INTEGER NOT NULL REFERENCES timetables(id) ON DELETE CASCADE ON UPDATE CASCADE,
  class_id            INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  period_number       SMALLINT NOT NULL,
  original_teacher_id INTEGER NOT NULL REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  cover_teacher_id    INTEGER NOT NULL REFERENCES teachers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  note                VARCHAR(255) NULL,
  created_by          INTEGER NULL REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_cover_lesson_date UNIQUE (cover_date, timetable_id)
);

CREATE INDEX IF NOT EXISTS idx_cover_date_period  ON lesson_covers (cover_date, period_number);
CREATE INDEX IF NOT EXISTS idx_cover_teacher_date ON lesson_covers (cover_teacher_id, cover_date);

CREATE TRIGGER trg_lesson_covers_updated_at
  BEFORE UPDATE ON lesson_covers
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.lesson_covers TO nizam_app;

DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
    REVOKE ALL ON TABLE public.lesson_covers FROM anon;
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
    REVOKE ALL ON TABLE public.lesson_covers FROM authenticated;
  END IF;
END
$$;

ALTER TABLE public.lesson_covers ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.lesson_covers;
CREATE POLICY nizam_app_full_access ON public.lesson_covers
  FOR ALL TO nizam_app USING (true) WITH CHECK (true);

-- Nizam -- roster verification, printed-value expansion and timetable gaps
-- (PostgreSQL twin of database/migrations/027_...).
--
-- The Postgres set never received the MySQL roster-provenance migration 025,
-- so the three columns that migration adds are created here as well; the
-- expansion and the verification screen both read them. Columns, indexes and
-- tables are created only when missing, because an existing Supabase install
-- may already have some of them from a manual correction.

ALTER TABLE students ADD COLUMN IF NOT EXISTS class_attribute_raw VARCHAR(120) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS import_source VARCHAR(40) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS class_attribute_source VARCHAR(20) NULL;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'chk_students_class_attribute_source'
  ) THEN
    ALTER TABLE students
      ADD CONSTRAINT chk_students_class_attribute_source
      CHECK (class_attribute_source IN ('printed','carried_forward'));
  END IF;
END
$$;

CREATE TABLE IF NOT EXISTS roster_verifications (
  id               INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id INTEGER NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
  class_id         INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  status           VARCHAR(10) NOT NULL DEFAULT 'verified' CHECK (status IN ('verified')),
  student_count    SMALLINT NOT NULL DEFAULT 0,
  fingerprint      CHAR(64) NULL,
  note             VARCHAR(500) NULL,
  verified_by      INTEGER NULL REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  verified_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_roster_verification_class UNIQUE (academic_year_id, class_id)
);

CREATE INDEX IF NOT EXISTS idx_roster_verification_status ON roster_verifications (status);

CREATE TRIGGER trg_roster_verifications_updated_at
  BEFORE UPDATE ON roster_verifications
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- The roster prints a first name only ("مروة", "إيمان"). The staff record
-- keeps that printed text as an alias so a timetable cell can be attached to
-- the real person without renaming the teacher or guessing between two
-- teachers who share a first name.
CREATE TABLE IF NOT EXISTS teacher_aliases (
  id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  teacher_id INTEGER NOT NULL REFERENCES teachers(id) ON DELETE CASCADE ON UPDATE CASCADE,
  alias      VARCHAR(150) NOT NULL,
  source     VARCHAR(40) NOT NULL DEFAULT 'timetable',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_teacher_alias UNIQUE (teacher_id, alias)
);

CREATE INDEX IF NOT EXISTS idx_teacher_alias_text ON teacher_aliases (alias);

CREATE TABLE IF NOT EXISTS timetable_gaps (
  id                    INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id      INTEGER NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
  class_id              INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  day_of_week           SMALLINT NOT NULL,
  period_number         SMALLINT NOT NULL,
  subject_id            INTEGER NULL REFERENCES subjects(id) ON DELETE SET NULL ON UPDATE CASCADE,
  subject_raw           VARCHAR(120) NOT NULL,
  printed_teacher       VARCHAR(190) NULL,
  reason                VARCHAR(20) NOT NULL
                          CHECK (reason IN ('no_teacher','teacher_conflict','unmapped_teacher','absence','other')),
  status                VARCHAR(10) NOT NULL DEFAULT 'open'
                          CHECK (status IN ('open','resolved','ignored')),
  resolved_teacher_id   INTEGER NULL REFERENCES teachers(id) ON DELETE SET NULL ON UPDATE CASCADE,
  resolved_subject_id   INTEGER NULL REFERENCES subjects(id) ON DELETE SET NULL ON UPDATE CASCADE,
  resolved_timetable_id INTEGER NULL,
  note                  VARCHAR(255) NULL,
  source_document       VARCHAR(190) NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_timetable_gap_slot UNIQUE (academic_year_id, class_id, day_of_week, period_number)
);

CREATE INDEX IF NOT EXISTS idx_timetable_gap_status  ON timetable_gaps (status, reason);
CREATE INDEX IF NOT EXISTS idx_timetable_gap_teacher ON timetable_gaps (printed_teacher);

CREATE TRIGGER trg_timetable_gaps_updated_at
  BEFORE UPDATE ON timetable_gaps
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- Same grant/RLS pattern as every other table (014: a migration adding a new
-- table adds its policy in the same file). The anon/authenticated REVOKE is
-- guarded for vanilla Postgres, which has no such roles.
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.roster_verifications TO nizam_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.teacher_aliases TO nizam_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.timetable_gaps TO nizam_app;

DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
    REVOKE ALL ON TABLE public.roster_verifications FROM anon;
    REVOKE ALL ON TABLE public.teacher_aliases FROM anon;
    REVOKE ALL ON TABLE public.timetable_gaps FROM anon;
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
    REVOKE ALL ON TABLE public.roster_verifications FROM authenticated;
    REVOKE ALL ON TABLE public.teacher_aliases FROM authenticated;
    REVOKE ALL ON TABLE public.timetable_gaps FROM authenticated;
  END IF;
END
$$;

ALTER TABLE public.roster_verifications ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.roster_verifications;
CREATE POLICY nizam_app_full_access ON public.roster_verifications
  FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.teacher_aliases ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.teacher_aliases;
CREATE POLICY nizam_app_full_access ON public.teacher_aliases
  FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.timetable_gaps ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.timetable_gaps;
CREATE POLICY nizam_app_full_access ON public.timetable_gaps
  FOR ALL TO nizam_app USING (true) WITH CHECK (true);

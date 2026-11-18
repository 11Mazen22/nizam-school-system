-- Nizam -- PostgreSQL twin of database/migrations/024_school_package_import.sql.
--
-- The PostgreSQL set never received migration 024, so /assessments and any
-- screen reading classes.pathway_ar failed on the online deployment with a
-- missing table/column. This migration adds the same package schema:
-- the official Arabic pathway label, the 45 weekly assessment rows, and the
-- import-batch provenance record.

ALTER TABLE classes ADD COLUMN IF NOT EXISTS pathway_ar VARCHAR(80) NULL;

CREATE TABLE IF NOT EXISTS assessment_schedules (
  id               INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id INTEGER NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE ON UPDATE CASCADE,
  class_id         INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  day_of_week      SMALLINT NOT NULL,
  sequence         SMALLINT NOT NULL,
  subjects_raw     VARCHAR(255) NOT NULL,
  no_assessment    SMALLINT NOT NULL DEFAULT 0 CHECK (no_assessment IN (0,1)),
  source_document  VARCHAR(190) NOT NULL,
  source_class     VARCHAR(10) NOT NULL,
  source_version   VARCHAR(40) NOT NULL DEFAULT '2026/2027',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_assessment_class_day UNIQUE (academic_year_id, class_id, day_of_week)
);

CREATE INDEX IF NOT EXISTS idx_assessment_class ON assessment_schedules (class_id);

CREATE TRIGGER trg_assessment_schedules_updated_at
  BEFORE UPDATE ON assessment_schedules
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE IF NOT EXISTS import_batches (
  id             INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  source_package VARCHAR(190) NOT NULL,
  source_version VARCHAR(40) NOT NULL,
  summary_json   JSONB NOT NULL,
  imported_by    VARCHAR(60) NOT NULL DEFAULT 'cli',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.assessment_schedules TO nizam_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.import_batches TO nizam_app;

DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
    REVOKE ALL ON TABLE public.assessment_schedules FROM anon;
    REVOKE ALL ON TABLE public.import_batches FROM anon;
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
    REVOKE ALL ON TABLE public.assessment_schedules FROM authenticated;
    REVOKE ALL ON TABLE public.import_batches FROM authenticated;
  END IF;
END
$$;

ALTER TABLE public.assessment_schedules ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.assessment_schedules;
CREATE POLICY nizam_app_full_access ON public.assessment_schedules
  FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.import_batches ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.import_batches;
CREATE POLICY nizam_app_full_access ON public.import_batches
  FOR ALL TO nizam_app USING (true) WITH CHECK (true);

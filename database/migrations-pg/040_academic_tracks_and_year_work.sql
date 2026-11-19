-- Migration 039: per-enrollment academic track and year-work entries
--
-- PostgreSQL counterpart of MySQL 035. Tracks belong to an enrollment because
-- a student may follow a different track in a different academic year.

ALTER TABLE public.student_enrollments ADD COLUMN IF NOT EXISTS academic_track VARCHAR(30) NULL;
CREATE INDEX IF NOT EXISTS idx_enrollment_track ON public.student_enrollments (academic_track);

CREATE TABLE IF NOT EXISTS year_work_entries (
  id               BIGSERIAL PRIMARY KEY,
  academic_year_id INTEGER NOT NULL REFERENCES public.academic_years(id) ON DELETE RESTRICT,
  student_id       INTEGER NOT NULL REFERENCES public.students(id) ON DELETE RESTRICT,
  entry_date       DATE NOT NULL,
  category         VARCHAR(40) NOT NULL,
  label            VARCHAR(150) NULL,
  source_key       VARCHAR(80) NULL,
  score            NUMERIC(6,2) NOT NULL DEFAULT 0.00,
  max_score        NUMERIC(6,2) NOT NULL DEFAULT 100.00,
  source           VARCHAR(40) NOT NULL DEFAULT 'manual',
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_year_work_source
  ON public.year_work_entries (academic_year_id, student_id, source_key);
CREATE INDEX IF NOT EXISTS idx_year_work_student
  ON public.year_work_entries (student_id, academic_year_id);

COMMENT ON COLUMN public.student_enrollments.academic_track IS 'GENERAL_SECONDARY or BACCALAUREATE for this year';

DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
    REVOKE ALL ON TABLE public.year_work_entries FROM anon;
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
    REVOKE ALL ON TABLE public.year_work_entries FROM authenticated;
  END IF;
END
$$;

ALTER TABLE public.year_work_entries ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.year_work_entries;
CREATE POLICY nizam_app_full_access ON public.year_work_entries
  FOR ALL TO nizam_app USING (true) WITH CHECK (true);

-- Nizam School Management System (Postgres)
-- Migration 028: close the post-010 grant/RLS gap for every later module.
--
-- Migration 010 secured the 20 tables that existed at that point. Migration
-- 014 documented the rule for every later CREATE TABLE: Supabase's platform
-- event trigger automatically enables RLS with zero policies, so the new
-- table is default-deny even to nizam_app until its own migration adds the
-- app policy. Migrations 017-023 predated that rule being consistently
-- enforced and created 28 tables without those policies. The result on
-- Supabase was a schema that existed but whose newer modules could not read
-- or write their own tables. On vanilla Postgres (Railway) RLS is not
-- auto-enabled, but explicit grants are still required for the least-
-- privilege runtime role.
--
-- Repair both targets in one idempotent pass. The statements are explicit,
-- not a dynamic EXECUTE loop: migration 010 records why bare ALTER TABLE
-- statements were the only form verified to persist reliably through the
-- Supabase pooler used by the original online target.

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO nizam_app;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO nizam_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO nizam_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO nizam_app;

-- Supabase creates these Data API roles; vanilla Postgres does not. Guard
-- them independently so the migration works on either target (and even on a
-- customised server that happens to have only one of the two roles).
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
    REVOKE ALL ON ALL TABLES IN SCHEMA public FROM anon;
    REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM anon;
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
    REVOKE ALL ON ALL TABLES IN SCHEMA public FROM authenticated;
    REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM authenticated;
  END IF;
END
$$;

ALTER TABLE public.academic_terms ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.academic_terms;
CREATE POLICY nizam_app_full_access ON public.academic_terms FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.admissions_applications ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.admissions_applications;
CREATE POLICY nizam_app_full_access ON public.admissions_applications FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.attendance_codes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.attendance_codes;
CREATE POLICY nizam_app_full_access ON public.attendance_codes FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.attendance_records ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.attendance_records;
CREATE POLICY nizam_app_full_access ON public.attendance_records FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.campuses ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.campuses;
CREATE POLICY nizam_app_full_access ON public.campuses FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.disciplinary_records ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.disciplinary_records;
CREATE POLICY nizam_app_full_access ON public.disciplinary_records FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.educational_stages ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.educational_stages;
CREATE POLICY nizam_app_full_access ON public.educational_stages FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.exam_scores ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.exam_scores;
CREATE POLICY nizam_app_full_access ON public.exam_scores FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.exams ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.exams;
CREATE POLICY nizam_app_full_access ON public.exams FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.fee_categories ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.fee_categories;
CREATE POLICY nizam_app_full_access ON public.fee_categories FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.fee_structures ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.fee_structures;
CREATE POLICY nizam_app_full_access ON public.fee_structures FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.grading_scale_bands ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.grading_scale_bands;
CREATE POLICY nizam_app_full_access ON public.grading_scale_bands FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.grading_scales ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.grading_scales;
CREATE POLICY nizam_app_full_access ON public.grading_scales FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.guardians ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.guardians;
CREATE POLICY nizam_app_full_access ON public.guardians FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.health_records ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.health_records;
CREATE POLICY nizam_app_full_access ON public.health_records FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.installed_modules ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.installed_modules;
CREATE POLICY nizam_app_full_access ON public.installed_modules FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.invoice_payments ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.invoice_payments;
CREATE POLICY nizam_app_full_access ON public.invoice_payments FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.library_books ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.library_books;
CREATE POLICY nizam_app_full_access ON public.library_books FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.library_borrows ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.library_borrows;
CREATE POLICY nizam_app_full_access ON public.library_borrows FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.notifications ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.notifications;
CREATE POLICY nizam_app_full_access ON public.notifications FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.staff_departments ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.staff_departments;
CREATE POLICY nizam_app_full_access ON public.staff_departments FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.staff_designations ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.staff_designations;
CREATE POLICY nizam_app_full_access ON public.staff_designations FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.staff_leaves ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.staff_leaves;
CREATE POLICY nizam_app_full_access ON public.staff_leaves FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.student_guardians ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.student_guardians;
CREATE POLICY nizam_app_full_access ON public.student_guardians FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.student_invoices ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.student_invoices;
CREATE POLICY nizam_app_full_access ON public.student_invoices FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.student_transport ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.student_transport;
CREATE POLICY nizam_app_full_access ON public.student_transport FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.timetables ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.timetables;
CREATE POLICY nizam_app_full_access ON public.timetables FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.transport_routes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.transport_routes;
CREATE POLICY nizam_app_full_access ON public.transport_routes FOR ALL TO nizam_app USING (true) WITH CHECK (true);

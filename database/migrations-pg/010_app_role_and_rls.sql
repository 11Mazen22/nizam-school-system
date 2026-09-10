-- Nizam School Management System (Postgres/Supabase)
-- Migration 010: dedicated least-privilege application role + RLS on every table.
--
-- WHY THIS EXISTS (has no MySQL equivalent -- this is new, Postgres/Supabase-
-- specific security infrastructure, not a translation of anything):
-- Supabase automatically exposes every table in the public schema through
-- its PostgREST API. Nizam never uses that API or Supabase Auth at all --
-- the PHP backend is the SOLE database client, connecting once per request
-- and doing its own complete authorization in App\Middleware\RoleGuardMiddleware
-- before any query runs. Without RLS, anyone holding this project's public
-- "anon" API key (meant to be embedded in client-side code in Supabase's
-- normal usage pattern -- Nizam has no such client, but the key still
-- exists and still works against the REST API) could read or write every
-- table directly over HTTPS, completely bypassing the PHP app.
--
-- The fix: enable RLS on every table, with a policy granting the app's own
-- dedicated role (nizam_app, NOT the postgres superuser) full access -- the
-- app already re-implements real per-user authorization at the PHP layer,
-- so this policy is deliberately permissive for that one role. Supabase's
-- built-in anon/authenticated roles get NO policy at all, which under RLS
-- means default-deny: PostgREST requests using the anon key see zero rows,
-- can write zero rows, on every single table.
--
-- Password is set separately (see packaging-online/README.md) -- never
-- hardcoded in a version-controlled migration file.

DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'nizam_app') THEN
    CREATE ROLE nizam_app WITH LOGIN PASSWORD 'CHANGE_ME_AT_APPLY_TIME';
  END IF;
END
$$;

GRANT USAGE ON SCHEMA public TO nizam_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO nizam_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO nizam_app;
-- Applies the same grants automatically to any table a future migration adds.
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO nizam_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO nizam_app;
-- No CREATE/DROP/ALTER/TRUNCATE privilege at all -- schema changes only
-- ever happen via a migration run as the postgres superuser, never as the
-- app's own runtime connection. Least privilege, per the brief.
REVOKE ALL ON SCHEMA public FROM PUBLIC;

-- The app itself never reads or writes the migrations bookkeeping table
-- (only MigrationService, run as the postgres superuser during setup, ever
-- touches it) -- revoked from the blanket ALL TABLES grant above so
-- nizam_app's actual privileges match what the running application
-- genuinely needs, not just what's harmless to leave in place.
REVOKE ALL ON TABLE public.migrations FROM nizam_app;

-- Found live: a brand-new Supabase project provisions its OWN default
-- grants directly to the anon/authenticated roles on every public-schema
-- table (confirmed via information_schema.role_table_grants -- e.g. INSERT
-- and SELECT on "subjects" were already present before this migration ever
-- ran). "REVOKE ALL ... FROM PUBLIC" above only undoes what was granted to
-- the PUBLIC pseudo-role; it does nothing to a grant made directly to a
-- named role like anon/authenticated, so it must be revoked from each of
-- them explicitly. This, together with RLS being enabled with no policy for
-- either role (above), is what actually closes the PostgREST-exposure gap:
-- even if a future default-privilege grant reappeared, RLS alone (default
-- deny under RLS unless a matching policy exists) is the real backstop --
-- this explicit revoke removes the more obvious, unnecessary privilege
-- outright rather than relying on RLS as the only line of defense.
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM anon, authenticated;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM anon, authenticated;
REVOKE ALL ON SCHEMA public FROM anon, authenticated;
ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM anon, authenticated;
ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON SEQUENCES FROM anon, authenticated;

-- One explicit statement per table, deliberately not a DO-block loop with
-- dynamic EXECUTE format(): found live against Supabase's transaction-mode
-- pooler that ALTER TABLE ... ENABLE ROW LEVEL SECURITY run that way
-- reported success but did not durably persist (confirmed by checking
-- pg_tables.rowsecurity from a separate connection immediately after,
-- repeatably, even wrapped in an explicit transaction). The identical bare
-- statement, not inside a DO block, was stable and reproducible. More
-- verbose; verified correct, which is what matters here.
ALTER TABLE public.academic_years ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.academic_years;
CREATE POLICY nizam_app_full_access ON public.academic_years FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.activity_logs ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.activity_logs;
CREATE POLICY nizam_app_full_access ON public.activity_logs FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.backups ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.backups;
CREATE POLICY nizam_app_full_access ON public.backups FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.classes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.classes;
CREATE POLICY nizam_app_full_access ON public.classes FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.grades ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.grades;
CREATE POLICY nizam_app_full_access ON public.grades FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.login_attempts ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.login_attempts;
CREATE POLICY nizam_app_full_access ON public.login_attempts FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.permissions ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.permissions;
CREATE POLICY nizam_app_full_access ON public.permissions FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.promotion_locks ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.promotion_locks;
CREATE POLICY nizam_app_full_access ON public.promotion_locks FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.role_permissions ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.role_permissions;
CREATE POLICY nizam_app_full_access ON public.role_permissions FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.roles ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.roles;
CREATE POLICY nizam_app_full_access ON public.roles FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.schools ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.schools;
CREATE POLICY nizam_app_full_access ON public.schools FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.settings ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.settings;
CREATE POLICY nizam_app_full_access ON public.settings FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.student_enrollments ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.student_enrollments;
CREATE POLICY nizam_app_full_access ON public.student_enrollments FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.students ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.students;
CREATE POLICY nizam_app_full_access ON public.students FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.subject_staffing_requirements ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.subject_staffing_requirements;
CREATE POLICY nizam_app_full_access ON public.subject_staffing_requirements FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.subjects ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.subjects;
CREATE POLICY nizam_app_full_access ON public.subjects FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.teacher_assignments ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.teacher_assignments;
CREATE POLICY nizam_app_full_access ON public.teacher_assignments FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.teacher_subjects ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.teacher_subjects;
CREATE POLICY nizam_app_full_access ON public.teacher_subjects FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.teachers ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.teachers;
CREATE POLICY nizam_app_full_access ON public.teachers FOR ALL TO nizam_app USING (true) WITH CHECK (true);

ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.users;
CREATE POLICY nizam_app_full_access ON public.users FOR ALL TO nizam_app USING (true) WITH CHECK (true);
-- migrations table deliberately excluded: it is only ever touched by the
-- postgres superuser running a migration, never by the app's own runtime
-- connection, so it needs no nizam_app policy -- and RLS on it would only
-- add a policy that's never exercised. It is still protected from
-- anon/authenticated PostgREST access by the schema-level REVOKE above and
-- by never granting SELECT on it to nizam_app.

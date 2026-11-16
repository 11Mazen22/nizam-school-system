-- Nizam -- offline queue idempotency (PostgreSQL twin of migration 022).
-- See database/migrations/022_request_replays.sql for the rationale.

CREATE TABLE IF NOT EXISTS request_replays (
  replay_key VARCHAR(64) PRIMARY KEY,
  user_id INTEGER NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_request_replays_created ON request_replays (created_at);

-- Complete the grant/RLS pattern every other migration that creates a
-- table follows (014's own rule: "A migration adding a new table should add
-- its policy in the same migration file"). Without the nizam_app policy
-- below, Supabase's ensure_rls trigger leaves this table RLS-enabled with
-- zero policies -- default-deny for every role, including the app's own --
-- so ReplayGuard's inserts silently fail there. The anon/authenticated
-- REVOKE is guarded for targets (vanilla Postgres) that have no such roles.
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.request_replays TO nizam_app;
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
    REVOKE ALL ON TABLE public.request_replays FROM anon;
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
    REVOKE ALL ON TABLE public.request_replays FROM authenticated;
  END IF;
END
$$;
ALTER TABLE public.request_replays ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.request_replays;
CREATE POLICY nizam_app_full_access ON public.request_replays
  FOR ALL TO nizam_app USING (true) WITH CHECK (true);

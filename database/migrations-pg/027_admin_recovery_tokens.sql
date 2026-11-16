-- Nizam School Management System (Postgres/Supabase)
-- Migration 027: database-backed administrator recovery tokens.
-- PostgreSQL twin of database/migrations/023_admin_recovery_tokens.sql.

CREATE TABLE admin_recovery_tokens (
  slot_id       SMALLINT PRIMARY KEY DEFAULT 1 CHECK (slot_id = 1),
  user_id       INTEGER NOT NULL,
  token_digest  CHAR(64) NOT NULL UNIQUE,
  token_hash    VARCHAR(255) NOT NULL,
  issued_at     TIMESTAMPTZ NOT NULL,
  expires_at    TIMESTAMPTZ NOT NULL,
  CONSTRAINT fk_admin_recovery_tokens_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- PostgreSQL does not automatically index foreign-key columns.
CREATE INDEX idx_admin_recovery_tokens_user ON admin_recovery_tokens (user_id);

-- Nizam's PHP backend is the only database client. Keep the table completely
-- unavailable to Supabase's Data API roles, matching migration 010's model.
-- The anon/authenticated REVOKE is role-existence guarded for the same
-- reason 010's are: a non-Supabase Postgres (Railway's own) has no such
-- role, and revoking from a missing role aborts the whole migration.
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.admin_recovery_tokens TO nizam_app;
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
    REVOKE ALL ON TABLE public.admin_recovery_tokens FROM anon;
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
    REVOKE ALL ON TABLE public.admin_recovery_tokens FROM authenticated;
  END IF;
END
$$;
ALTER TABLE public.admin_recovery_tokens ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.admin_recovery_tokens;
CREATE POLICY nizam_app_full_access ON public.admin_recovery_tokens
  FOR ALL TO nizam_app USING (true) WITH CHECK (true);

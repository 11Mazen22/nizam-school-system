-- Nizam -- PostgreSQL twin of database/migrations/031_user_mfa.sql.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS mfa_secret     VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS mfa_enabled_at TIMESTAMP   NULL;

CREATE TABLE IF NOT EXISTS mfa_recovery_codes (
  id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  code_hash  VARCHAR(255) NOT NULL,
  used_at    TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_mfa_codes_user ON mfa_recovery_codes (user_id, used_at);

GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.mfa_recovery_codes TO nizam_app;

DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
    REVOKE ALL ON TABLE public.mfa_recovery_codes FROM anon;
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
    REVOKE ALL ON TABLE public.mfa_recovery_codes FROM authenticated;
  END IF;
END
$$;

ALTER TABLE public.mfa_recovery_codes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS nizam_app_full_access ON public.mfa_recovery_codes;
CREATE POLICY nizam_app_full_access ON public.mfa_recovery_codes
  FOR ALL TO nizam_app USING (true) WITH CHECK (true);

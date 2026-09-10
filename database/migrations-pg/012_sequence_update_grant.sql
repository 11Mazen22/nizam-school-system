-- Nizam School Management System (Postgres/Supabase)
-- Migration 012: grant nizam_app UPDATE on sequences (needed for restore's
-- sequence resync only -- ordinary auto-increment inserts keep working on
-- USAGE alone, as they always have).
--
-- WHY THIS EXISTS: migration 010 granted nizam_app "USAGE, SELECT" on every
-- sequence -- correct and sufficient for ordinary INSERTs, since nextval()
-- (what a plain "INSERT INTO students (...) VALUES (...)" relies on for its
-- identity column default) only requires USAGE. It does NOT cover setval(),
-- which Postgres treats as a distinct UPDATE-privileged operation.
--
-- RestoreService's data-only replay (§I.3, Postgres path) inserts rows with
-- their original id values (OVERRIDING SYSTEM VALUE) and then must resync
-- each sequence to MAX(id) afterward, or the next ordinary insert could
-- collide with a restored row's id. That resync calls setval() over the
-- app's own runtime connection (nizam_app) -- the one genuinely new need
-- this migration exists for. No other current feature needs it.
GRANT UPDATE ON ALL SEQUENCES IN SCHEMA public TO nizam_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT UPDATE ON SEQUENCES TO nizam_app;

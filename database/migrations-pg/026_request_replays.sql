-- Nizam -- offline queue idempotency (PostgreSQL twin of migration 022).
-- See database/migrations/022_request_replays.sql for the rationale.

CREATE TABLE IF NOT EXISTS request_replays (
  replay_key VARCHAR(64) PRIMARY KEY,
  user_id INTEGER NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_request_replays_created ON request_replays (created_at);

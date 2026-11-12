-- Nizam School Management System
-- Migration 022: request_replays (offline queue idempotency)
--
-- When a school loses connectivity mid-submit, the browser cannot tell
-- whether the request reached the server. The offline queue therefore
-- replays it with a client-generated X-Nizam-Queue-Id; the first arrival
-- claims the key here and any later arrival with the same key is answered
-- 204 without executing the handler again. Without this, a lost response
-- would mean a duplicated student, invoice or payment record.
--
-- Rows are disposable: they exist only to de-duplicate retries, and old
-- keys are pruned opportunistically (30 days, roughly 1 in 50 claims).

CREATE TABLE IF NOT EXISTS request_replays (
  replay_key VARCHAR(64) NOT NULL,
  user_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (replay_key),
  KEY idx_request_replays_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

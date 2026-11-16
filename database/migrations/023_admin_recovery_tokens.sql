-- Nizam School Management System
-- Migration 023: reliable, database-backed administrator recovery tokens.
--
-- Recovery codes used to live in recovery-token.json. In the packaged Windows
-- build Apache reads mutable data from ProgramData while a manually-run PHP CLI
-- process defaults to the application directory, so the CLI and browser could
-- silently read different files. Keeping the single active token beside the
-- account it protects removes that split-brain failure and lets password update,
-- account unlock, and token consumption happen in one database transaction.
-- Only a password_hash() and a SHA-256 lookup digest are stored; the code itself
-- never enters the database.

CREATE TABLE admin_recovery_tokens (
  slot_id       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  user_id       INT UNSIGNED NOT NULL,
  token_digest  CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  token_hash    VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  issued_at     DATETIME NOT NULL,
  expires_at    DATETIME NOT NULL,
  PRIMARY KEY (slot_id),
  UNIQUE KEY uq_admin_recovery_token_digest (token_digest),
  KEY idx_admin_recovery_tokens_user (user_id),
  CONSTRAINT chk_admin_recovery_single_slot CHECK (slot_id = 1),
  CONSTRAINT fk_admin_recovery_tokens_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

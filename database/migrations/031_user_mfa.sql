-- Nizam -- optional TOTP two-factor authentication for staff accounts.
--
-- The secret is only written after the user proves one generated code, so a
-- half-finished setup can never lock anyone out. Recovery codes are stored as
-- bcrypt hashes, never in readable form, and each one can be used once.

ALTER TABLE users
  ADD COLUMN mfa_secret     VARCHAR(64) NULL AFTER last_login_at,
  ADD COLUMN mfa_enabled_at DATETIME    NULL AFTER mfa_secret;

CREATE TABLE IF NOT EXISTS mfa_recovery_codes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  code_hash  VARCHAR(255) NOT NULL,
  used_at    DATETIME     NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mfa_codes_user (user_id, used_at),
  CONSTRAINT fk_mfa_codes_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

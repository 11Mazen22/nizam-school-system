-- Nizam School Management System
-- Migration 008: activity_logs, backups, login_attempts
-- Blueprint reference: §D, §O-28 (loose entity_id reference, by necessity),
-- §O-16 (dual login-throttle thresholds)

CREATE TABLE activity_logs (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NULL,
  action       VARCHAR(60)  NOT NULL,
  entity_type  VARCHAR(60)  NULL,
  entity_id    INT UNSIGNED NULL COMMENT 'Loose reference by necessity, §O-28 -- no FK, spans many tables',
  description  VARCHAR(255) NULL,
  metadata     JSON         NULL,
  ip_address   VARCHAR(45)  NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_logs_created_at (created_at),
  KEY idx_activity_logs_user_id (user_id),
  CONSTRAINT fk_activity_logs_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- user_id SET NULL (not RESTRICT): a user is never hard-deleted in practice (O-10),
-- but the column is explicitly nullable in §D, signalling the log must survive even
-- a hypothetical DBA-level removal rather than block it.

CREATE TABLE backups (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename    VARCHAR(150) NOT NULL,
  file_size   BIGINT UNSIGNED NULL,
  type        ENUM('manual','auto','pre_restore') NOT NULL,
  status      ENUM('success','failed') NOT NULL,
  created_by  INT UNSIGNED NULL,
  notes       VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_backups_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL,
  ip_address    VARCHAR(45)  NOT NULL,
  attempted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  success       TINYINT(1)   NOT NULL,
  PRIMARY KEY (id),
  KEY idx_login_attempts_username_time (username, attempted_at),
  KEY idx_login_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Both indexes exist for §O-16's dual throttle: per-username+IP AND a secondary
-- IP-wide threshold across different usernames -- both need a fast recent-attempts
-- lookup, justified directly by the Phase 3 kickoff's indexing checklist
-- ("authentication/login-attempt tracking").

-- Bookkeeping table for the migration runner itself. Not one of the eight numbered
-- migrations -- it is bootstrapped by database/migrate.php before anything else runs,
-- since the runner needs it to exist before it can even ask "what has already been
-- applied?" (a standard chicken-and-egg resolution, documented in the Phase 3
-- verification report). Included here in writing so its definition lives in version
-- control, even though migrate.php issues this exact statement itself at startup.
CREATE TABLE IF NOT EXISTS migrations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration   VARCHAR(191) NOT NULL,
  applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_migrations_migration (migration)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

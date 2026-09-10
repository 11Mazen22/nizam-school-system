-- Nizam School Management System (Postgres/Supabase)
-- Migration 008: activity_logs, backups, login_attempts, migrations
-- Postgres translation of database/migrations/008_activity_backups_login_attempts.sql
-- (§O-28 loose entity_id reference by necessity, §O-16 dual login-throttle thresholds)

CREATE TABLE activity_logs (
  id           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id      INTEGER      NULL,
  action       VARCHAR(60)  NOT NULL,
  entity_type  VARCHAR(60)  NULL,
  entity_id    INTEGER      NULL,  -- loose reference by necessity, §O-28 -- no FK, spans many tables
  description  VARCHAR(255) NULL,
  metadata     JSONB        NULL,  -- JSONB, not JSON: Postgres's indexable/queryable variant; PHP's json_encode()/json_decode() usage is unaffected either way
  ip_address   VARCHAR(45)  NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_logs_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
);
CREATE INDEX idx_activity_logs_created_at ON activity_logs (created_at);
CREATE INDEX idx_activity_logs_user_id ON activity_logs (user_id);

CREATE TABLE backups (
  id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  filename    VARCHAR(150) NOT NULL,
  file_size   BIGINT       NULL,
  type        VARCHAR(12)  NOT NULL CHECK (type IN ('manual','auto','pre_restore')),
  status      VARCHAR(10)  NOT NULL CHECK (status IN ('success','failed')),
  created_by  INTEGER      NULL,
  notes       VARCHAR(255) NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_backups_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE login_attempts (
  id            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL,
  ip_address    VARCHAR(45)  NOT NULL,
  attempted_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  success       SMALLINT     NOT NULL
);
CREATE INDEX idx_login_attempts_username_time ON login_attempts (username, attempted_at);
CREATE INDEX idx_login_attempts_ip_time ON login_attempts (ip_address, attempted_at);

-- Bookkeeping table for the migration runner itself, same role as the MySQL
-- version -- MigrationService::bootstrapMigrationsTable() issues an
-- engine-appropriate version of this same statement itself at startup;
-- included here in writing so its definition lives in version control too.
CREATE TABLE IF NOT EXISTS migrations (
  id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  migration   VARCHAR(191) NOT NULL UNIQUE,
  applied_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Nizam School Management System (Postgres/Supabase)
-- Migration 021: notifications

CREATE TABLE notifications (
  id         INTEGER      GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id    INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  title_en   VARCHAR(150) NOT NULL,
  title_ar   VARCHAR(150) NOT NULL,
  body_en    TEXT         NOT NULL,
  body_ar    TEXT         NOT NULL,
  link       VARCHAR(255) NULL,
  is_read    SMALLINT     NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notifications_user_read ON notifications (user_id, is_read);
CREATE INDEX idx_notifications_user_time ON notifications (user_id, created_at);

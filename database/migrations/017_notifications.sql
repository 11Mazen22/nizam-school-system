-- Nizam School Management System
-- Migration 017: notifications
-- In-app notification store. Per-user, per-notification read state.
-- title_en/title_ar + body_en/body_ar support full bilingual display.
-- link is an optional internal URL (/students/42, /attendance, etc.).

CREATE TABLE notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  title_en   VARCHAR(150) NOT NULL,
  title_ar   VARCHAR(150) NOT NULL,
  body_en    TEXT         NOT NULL,
  body_ar    TEXT         NOT NULL,
  link       VARCHAR(255) NULL,
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user_read (user_id, is_read),
  KEY idx_notifications_user_time (user_id, created_at),
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

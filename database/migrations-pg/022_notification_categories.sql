-- Keep the notification contract identical for PostgreSQL deployments.
ALTER TABLE notifications
  ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'system',
  ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'normal';

CREATE INDEX idx_notifications_category ON notifications (user_id, category);
CREATE INDEX idx_notifications_priority ON notifications (user_id, priority);

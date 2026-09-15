-- Nizam School Management System
-- Migration 018: notification_categories
-- Adds category and priority to notifications table for better filtering and display

ALTER TABLE notifications
ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'system' AFTER user_id,
ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'normal' AFTER category;

-- Indexes for filtering
ALTER TABLE notifications
ADD INDEX idx_notifications_category (user_id, category),
ADD INDEX idx_notifications_priority (user_id, priority);

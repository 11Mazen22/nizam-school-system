-- Nizam School Management System -- Migration: users.email
-- Adds an optional email address per user account, needed for the
-- automation system's admin-notification emails (birthday reminders, year
-- rollover warnings, backup/report notifications). Nullable: an account
-- created before this migration, or one nobody has bothered to add an
-- email for, is still a perfectly valid login -- email is only required to
-- actually RECEIVE those notifications, never to sign in.

ALTER TABLE users
  ADD COLUMN email VARCHAR(255) NULL AFTER full_name;

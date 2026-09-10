-- Nizam School Management System -- Seed: settings
-- Every key from §J's Settings catalog EXCEPT school.name / school.name_ar
-- AND school.logo_path, which all live on the single schools row instead
-- (decision #6; migration 010's docblock has the full reasoning for logo_path
-- specifically) -- seeding placeholders here would be exactly the "fake
-- school data" §N forbids, and would resurrect the two-storage-location
-- ambiguity migration 010 just closed.
--
-- INSERT IGNORE, deliberately NOT "ON DUPLICATE KEY UPDATE value = VALUES(value)":
-- unlike roles/permissions (fixed, code-defined, meant to always match this file),
-- a setting's VALUE is user-editable state (that is the entire point of a settings
-- table). Re-running this seed after an admin has changed
-- security.login_max_attempts from 5 to something else must never silently reset
-- it back to the default -- "idempotent" here means "insert it once if missing,"
-- not "keep re-asserting the default."

INSERT IGNORE INTO settings (setting_key, value, value_type) VALUES
  ('app.default_language',                'ar',   'string'),
  ('academic.expected_weekly_capacity',   '20',   'int'),
  ('academic.student_id_pattern',         'STU-{year}-{seq:6}', 'string'),
  ('academic.teacher_id_pattern',         'TCH-{seq:6}',        'string'),
  ('backup.default_folder',               'database/backups',  'string'),
  ('security.login_max_attempts',         '5',    'int'),
  ('security.lockout_minutes',            '15',   'int'),
  ('security.session_timeout_minutes',    '60',   'int'),
  ('reports.school_footer_text',          NULL,   'string');

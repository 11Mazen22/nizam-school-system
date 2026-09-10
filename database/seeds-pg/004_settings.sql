-- Nizam School Management System (Postgres/Supabase) -- Seed: settings
-- Every key from §J's Settings catalog EXCEPT school.name / school.name_ar /
-- school.logo_path, which all live on the single schools row instead
-- (decision #6; see database/migrations/010_settings_logo_cleanup.sql's
-- docblock in the MySQL tree for the full reasoning -- carried forward here
-- from the start rather than seeded then cleaned up, since this is a fresh
-- database with no prior history to reconcile).
--
-- ON CONFLICT DO NOTHING, deliberately not DO UPDATE: a setting's VALUE is
-- user-editable state, not a fixed reference list like roles/permissions --
-- re-running this seed must never silently reset an admin's changes back to
-- the default.

INSERT INTO settings (setting_key, value, value_type) VALUES
  ('app.default_language',                'ar',   'string'),
  ('academic.expected_weekly_capacity',   '20',   'int'),
  ('academic.student_id_pattern',         'STU-{year}-{seq:6}', 'string'),
  ('academic.teacher_id_pattern',         'TCH-{seq:6}',        'string'),
  ('backup.default_folder',               'database/backups',  'string'),
  ('security.login_max_attempts',         '5',    'int'),
  ('security.lockout_minutes',            '15',   'int'),
  ('security.session_timeout_minutes',    '60',   'int'),
  ('reports.school_footer_text',          NULL,   'string')
ON CONFLICT (setting_key) DO NOTHING;

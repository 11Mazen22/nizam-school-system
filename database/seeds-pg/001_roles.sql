-- Nizam School Management System (Postgres/Supabase) -- Seed: roles
-- Idempotent by unique code -- safe to re-run.

INSERT INTO roles (code, name_en, name_ar) VALUES
  ('admin', 'Administrator', 'مدير النظام'),
  ('staff', 'Staff', 'موظف')
ON CONFLICT (code) DO UPDATE SET
  name_en = EXCLUDED.name_en,
  name_ar = EXCLUDED.name_ar;

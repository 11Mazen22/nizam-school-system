-- Nizam School Management System -- Seed: roles
-- Idempotent by unique code (§D migration strategy) -- safe to re-run.

INSERT INTO roles (code, name_en, name_ar) VALUES
  ('admin', 'Administrator', 'مدير النظام'),
  ('staff', 'Staff', 'موظف')
ON DUPLICATE KEY UPDATE
  name_en = VALUES(name_en),
  name_ar = VALUES(name_ar);

-- Nizam School Management System
-- Migration 025: admin_full_permissions (PostgreSQL)
--
-- Same defect and same fix as MySQL migration 021: the `admin` role is
-- defined as holding every permission, but its grants were a frozen list
-- written before the admissions, guardians, finance, HR, library, transport
-- and portal permissions existed. A fresh PostgreSQL install therefore had
-- an administrator who could sign in but was denied six modules.
--
-- ON CONFLICT DO NOTHING mirrors INSERT IGNORE: the composite primary key is
-- the entire row, so a duplicate is a no-op rather than an update.

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.code = 'admin'
ON CONFLICT DO NOTHING;

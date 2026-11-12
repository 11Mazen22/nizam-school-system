-- Nizam School Management System
-- Migration 021: admin_full_permissions
--
-- The `admin` role is defined as holding every permission in the catalogue
-- (§J). seeds/003_role_permissions.sql granted it a fixed, hand-written list
-- as of the phase it was written in, so every permission added by a later
-- module (admissions, guardians, finance, HR, library, transport, portals)
-- was never granted to it.
--
-- Found by tests/http/run.php against a database built only from migrations
-- and seeds: a freshly installed administrator could sign in, but was denied
-- /finance, /library, /transport, /hr, /admissions and /guardians. The role
-- existed; the grants did not. Live installations only worked because those
-- permissions had been granted by hand.
--
-- A cross join makes this self-maintaining: any permission that exists now or
-- is added by a future migration is granted to admin without another patch.
-- INSERT IGNORE keeps re-runs harmless (the composite primary key is the
-- whole row, so there is nothing to update).

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.code = 'admin';

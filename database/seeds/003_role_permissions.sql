-- Nizam School Management System -- Seed: role_permissions
-- Grants exactly as tabulated in §J (Permission catalog) and clarified by §S-12
-- (Undo Promotion rides on students.promote; Reassign Class rides on students.edit --
-- no new permission codes needed for either). Looked up by CODE, never by assumed
-- auto-increment id. INSERT IGNORE: the composite PK has nothing else to update on
-- a re-run, so "ignore the duplicate" is the correct idempotency mechanism here
-- (as opposed to ON DUPLICATE KEY UPDATE, used where there is a non-key column to
-- refresh).

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM (
  -- Administrator: every permission in the catalog.
  SELECT 'admin' AS role_code, 'students.view' AS perm_code UNION ALL
  SELECT 'admin', 'students.create' UNION ALL
  SELECT 'admin', 'students.edit' UNION ALL
  SELECT 'admin', 'students.archive' UNION ALL
  SELECT 'admin', 'students.restore' UNION ALL
  SELECT 'admin', 'students.promote' UNION ALL
  SELECT 'admin', 'teachers.view' UNION ALL
  SELECT 'admin', 'teachers.create' UNION ALL
  SELECT 'admin', 'teachers.edit' UNION ALL
  SELECT 'admin', 'teachers.archive' UNION ALL
  SELECT 'admin', 'subjects.view' UNION ALL
  SELECT 'admin', 'subjects.manage' UNION ALL
  SELECT 'admin', 'classes.view' UNION ALL
  SELECT 'admin', 'classes.manage' UNION ALL
  SELECT 'admin', 'grades.view' UNION ALL
  SELECT 'admin', 'grades.manage' UNION ALL
  SELECT 'admin', 'assignments.view' UNION ALL
  SELECT 'admin', 'assignments.manage' UNION ALL
  SELECT 'admin', 'academic_years.view' UNION ALL
  SELECT 'admin', 'academic_years.manage' UNION ALL
  SELECT 'admin', 'academic_years.close' UNION ALL
  SELECT 'admin', 'reports.view' UNION ALL
  SELECT 'admin', 'reports.export' UNION ALL
  SELECT 'admin', 'backups.run' UNION ALL
  SELECT 'admin', 'backups.restore' UNION ALL
  SELECT 'admin', 'settings.manage' UNION ALL
  SELECT 'admin', 'users.manage' UNION ALL
  SELECT 'admin', 'activity_log.view' UNION ALL
  -- Staff: the twelve view/create/edit-level permissions §J grants them.
  SELECT 'staff', 'students.view' UNION ALL
  SELECT 'staff', 'students.create' UNION ALL
  SELECT 'staff', 'students.edit' UNION ALL
  SELECT 'staff', 'teachers.view' UNION ALL
  SELECT 'staff', 'subjects.view' UNION ALL
  SELECT 'staff', 'classes.view' UNION ALL
  SELECT 'staff', 'grades.view' UNION ALL
  SELECT 'staff', 'assignments.view' UNION ALL
  SELECT 'staff', 'academic_years.view' UNION ALL
  SELECT 'staff', 'reports.view' UNION ALL
  SELECT 'staff', 'reports.export' UNION ALL
  SELECT 'staff', 'activity_log.view'
) AS mapping
JOIN roles r ON r.code = mapping.role_code
JOIN permissions p ON p.code = mapping.perm_code;

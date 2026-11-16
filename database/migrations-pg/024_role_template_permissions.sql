-- Nizam School Management System
-- Migration 024: role_template_permissions (PostgreSQL track)
--
-- PostgreSQL mirror of migrations/020_role_template_permissions.sql.
-- Migration 023 created the seventeen role templates but granted permissions
-- to `admin` only, leaving every other role able to sign in and reach nothing.

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code IN ('owner', 'principal', 'auditor') AND p.code LIKE '%.view'
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'owner' AND p.code IN ('reports.export', 'backups.run')
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'principal' AND p.code IN ('reports.export')
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'director' AND p.code IN (
  'academic_years.manage', 'grades.manage', 'subjects.manage', 'classes.manage',
  'assignments.manage', 'reports.export', 'teachers.view', 'students.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'registrar' AND p.code IN (
  'students.view', 'students.create', 'students.edit', 'students.archive', 'students.restore',
  'guardians.view', 'guardians.manage', 'classes.view', 'grades.view',
  'academic_years.view', 'reports.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'admissions' AND p.code IN (
  'admissions.view', 'admissions.manage', 'admissions.convert',
  'guardians.view', 'guardians.manage', 'students.view', 'grades.view', 'academic_years.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'teacher' AND p.code IN (
  'students.view', 'classes.view', 'subjects.view', 'grades.view',
  'attendance.view', 'attendance.manage', 'exams.view', 'exams.manage',
  'assignments.view', 'timetable.view', 'reports.view', 'welfare.view', 'portal.teacher'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'class_teacher' AND p.code IN (
  'welfare.manage', 'guardians.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'attendance_officer' AND p.code IN (
  'attendance.view', 'attendance.manage', 'students.view', 'classes.view',
  'grades.view', 'timetable.view', 'reports.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'accountant' AND p.code IN (
  'finance.view', 'finance.manage', 'students.view', 'reports.view', 'reports.export'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'hr_officer' AND p.code IN (
  'hr.view', 'hr.manage', 'teachers.view', 'teachers.create', 'teachers.edit',
  'attendance.view', 'reports.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'student_affairs' AND p.code IN (
  'welfare.view', 'welfare.manage', 'students.view', 'students.edit',
  'guardians.view', 'attendance.view', 'reports.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'health_officer' AND p.code IN (
  'welfare.view', 'welfare.manage', 'students.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'librarian' AND p.code IN (
  'library.view', 'library.manage', 'students.view', 'teachers.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'transport_manager' AND p.code IN (
  'transport.view', 'transport.manage', 'students.view'
)
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'parent' AND p.code IN ('portal.family')
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'student' AND p.code IN ('portal.student')
ON CONFLICT (role_id, permission_id) DO NOTHING;

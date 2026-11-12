-- Nizam School Management System
-- Migration 020: role_template_permissions
--
-- Migration 019 created seventeen standard role templates (owner, principal,
-- teacher, accountant, parent, ...) but granted permissions to `admin` only.
-- Every one of those templates therefore existed as a selectable role that
-- could sign in and reach nothing -- a role shell, not a role. This migration
-- gives each template the starter grant set its own responsibilities imply.
--
-- Deliberately grants are additive and idempotent: an installation that
-- already customised a template keeps its custom grants, it only gains the
-- baseline it was missing.

-- Read-only templates: every *.view permission in the catalogue.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code LIKE '%.view'
WHERE r.code IN ('owner', 'principal', 'auditor');

-- Owner additionally sees exports and can generate backups.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'owner' AND p.code IN ('reports.export', 'backups.run');

-- Principal reviews and exports reports.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'principal' AND p.code IN ('reports.export');

-- Academic director: the whole academic configuration surface.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'director' AND p.code IN (
  'academic_years.manage', 'grades.manage', 'subjects.manage', 'classes.manage',
  'assignments.manage', 'reports.export', 'teachers.view', 'students.view'
);

-- Registrar: student records, families, class placement.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'registrar' AND p.code IN (
  'students.view', 'students.create', 'students.edit', 'students.archive', 'students.restore',
  'guardians.view', 'guardians.manage', 'classes.view', 'grades.view',
  'academic_years.view', 'reports.view'
);

-- Admissions officer: the admissions pipeline and the family record it creates.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'admissions' AND p.code IN (
  'admissions.view', 'admissions.manage', 'admissions.convert',
  'guardians.view', 'guardians.manage', 'students.view', 'grades.view', 'academic_years.view'
);

-- Teacher: own classes, attendance, marks, assigned subjects.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'teacher' AND p.code IN (
  'students.view', 'classes.view', 'subjects.view', 'grades.view',
  'attendance.view', 'attendance.manage', 'exams.view', 'exams.manage',
  'assignments.view', 'timetable.view', 'reports.view', 'welfare.view', 'portal.teacher'
);

-- Class teacher: a teacher who also owns behaviour follow-up for the class.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'class_teacher' AND p.code IN (
  'welfare.manage', 'guardians.view'
);

-- Attendance supervisor: attendance only, across classes.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'attendance_officer' AND p.code IN (
  'attendance.view', 'attendance.manage', 'students.view', 'classes.view',
  'grades.view', 'timetable.view', 'reports.view'
);

-- Accountant: billing and the student identity an invoice needs.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'accountant' AND p.code IN (
  'finance.view', 'finance.manage', 'students.view', 'reports.view', 'reports.export'
);

-- HR manager: staff records, leave, staff attendance.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'hr_officer' AND p.code IN (
  'hr.view', 'hr.manage', 'teachers.view', 'teachers.create', 'teachers.edit',
  'attendance.view', 'reports.view'
);

-- Student affairs: wellbeing, discipline, the records those cases reference.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'student_affairs' AND p.code IN (
  'welfare.view', 'welfare.manage', 'students.view', 'students.edit',
  'guardians.view', 'attendance.view', 'reports.view'
);

-- Health officer: welfare/medical records only.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'health_officer' AND p.code IN (
  'welfare.view', 'welfare.manage', 'students.view'
);

-- Librarian: the catalogue plus who may borrow.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'librarian' AND p.code IN (
  'library.view', 'library.manage', 'students.view', 'teachers.view'
);

-- Transport supervisor: routes and allocations.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'transport_manager' AND p.code IN (
  'transport.view', 'transport.manage', 'students.view'
);

-- Family and student portals are single-purpose by design.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'parent' AND p.code IN ('portal.family');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'student' AND p.code IN ('portal.student');

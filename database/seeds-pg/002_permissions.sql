-- Nizam School Management System (Postgres/Supabase) -- Seed: permissions
-- Flat expansion of §J's permission catalog -- a faithful transcription of
-- database/seeds/002_permissions.sql, idempotent by unique code.

INSERT INTO permissions (code, name_en, name_ar, module) VALUES
  ('students.view',        'View Students',           'عرض الطلاب',                 'students'),
  ('students.create',      'Add Students',            'إضافة طلاب',                 'students'),
  ('students.edit',        'Edit Students',           'تعديل بيانات الطلاب',        'students'),
  ('students.archive',     'Archive Students',        'أرشفة الطلاب',               'students'),
  ('students.restore',     'Restore Students',        'استعادة الطلاب',             'students'),
  ('students.promote',     'Promote Students',        'ترفيع الطلاب',               'students'),
  ('teachers.view',        'View Teachers',            'عرض المعلمين',              'teachers'),
  ('teachers.create',      'Add Teachers',              'إضافة معلمين',             'teachers'),
  ('teachers.edit',        'Edit Teachers',             'تعديل بيانات المعلمين',   'teachers'),
  ('teachers.archive',     'Archive Teachers',          'أرشفة المعلمين',           'teachers'),
  ('subjects.view',        'View Subjects',             'عرض المواد',                'subjects'),
  ('subjects.manage',      'Manage Subjects',           'إدارة المواد',              'subjects'),
  ('classes.view',         'View Classes',              'عرض الفصول',                'classes'),
  ('classes.manage',       'Manage Classes',            'إدارة الفصول',              'classes'),
  ('grades.view',          'View Grades',               'عرض الصفوف',                'grades'),
  ('grades.manage',        'Manage Grades',             'إدارة الصفوف',              'grades'),
  ('assignments.view',     'View Assignments',          'عرض التكليفات',            'assignments'),
  ('assignments.manage',   'Manage Assignments',        'إدارة التكليفات',          'assignments'),
  ('academic_years.view',    'View Academic Years',     'عرض الأعوام الدراسية',      'academic_years'),
  ('academic_years.manage',  'Manage Academic Years',   'إدارة الأعوام الدراسية',    'academic_years'),
  ('academic_years.close',   'Close Academic Years',    'إغلاق الأعوام الدراسية',    'academic_years'),
  ('reports.view',         'View Reports',              'عرض التقارير',              'reports'),
  ('reports.export',       'Export Reports',            'تصدير التقارير',           'reports'),
  ('backups.run',          'Run Backups',                'إنشاء نسخ احتياطية',       'backups'),
  ('backups.restore',      'Restore Backups',            'استعادة النسخ الاحتياطية', 'backups'),
  ('settings.manage',      'Manage Settings',            'إدارة الإعدادات',          'settings'),
  ('users.manage',         'Manage Users',                'إدارة المستخدمين',        'users'),
  ('activity_log.view',    'View Activity Log',           'عرض سجل النشاط',          'activity_log')
ON CONFLICT (code) DO UPDATE SET
  name_en = EXCLUDED.name_en,
  name_ar = EXCLUDED.name_ar,
  module  = EXCLUDED.module;

-- Nizam School Management System (Postgres/Supabase)
-- Migration 023: universal_platform_foundation

-- 1. Extend schools profile
ALTER TABLE schools
  ADD COLUMN email VARCHAR(100) NULL,
  ADD COLUMN website VARCHAR(150) NULL,
  ADD COLUMN country VARCHAR(10) NOT NULL DEFAULT 'EG',
  ADD COLUMN region VARCHAR(60) NULL,
  ADD COLUMN timezone VARCHAR(50) NOT NULL DEFAULT 'Africa/Cairo',
  ADD COLUMN default_locale VARCHAR(10) NOT NULL DEFAULT 'ar',
  ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'EGP',
  ADD COLUMN date_format VARCHAR(20) NOT NULL DEFAULT 'YYYY-MM-DD',
  ADD COLUMN number_format VARCHAR(20) NOT NULL DEFAULT 'standard',
  ADD COLUMN school_week VARCHAR(50) NOT NULL DEFAULT '0,1,2,3,4',
  ADD COLUMN school_type VARCHAR(50) NOT NULL DEFAULT 'language_school',
  ADD COLUMN school_structure VARCHAR(50) NOT NULL DEFAULT 'k12_combined',
  ADD COLUMN tax_number VARCHAR(50) NULL,
  ADD COLUMN affiliation_code VARCHAR(50) NULL;

-- 2. Installed Modules Registry
CREATE TABLE IF NOT EXISTS installed_modules (
  id             INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  module_key     VARCHAR(50) NOT NULL UNIQUE,
  name_en        VARCHAR(100) NOT NULL,
  name_ar        VARCHAR(100) NOT NULL,
  description_en VARCHAR(255) NULL,
  description_ar VARCHAR(255) NULL,
  category       VARCHAR(50) NOT NULL DEFAULT 'operations',
  is_enabled     BOOLEAN NOT NULL DEFAULT TRUE,
  is_core        BOOLEAN NOT NULL DEFAULT FALSE,
  settings       TEXT NULL,
  installed_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 3. Campuses
CREATE TABLE IF NOT EXISTS campuses (
  id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  school_id  INTEGER NOT NULL DEFAULT 1 REFERENCES schools(id) ON DELETE CASCADE,
  code       VARCHAR(20) NOT NULL UNIQUE,
  name_en    VARCHAR(100) NOT NULL,
  name_ar    VARCHAR(100) NOT NULL,
  address    VARCHAR(255) NULL,
  phone      VARCHAR(30) NULL,
  is_main    BOOLEAN NOT NULL DEFAULT FALSE,
  is_active  BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 4. Educational Stages
CREATE TABLE IF NOT EXISTS educational_stages (
  id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  code       VARCHAR(30) NOT NULL UNIQUE,
  name_en    VARCHAR(60) NOT NULL,
  name_ar    VARCHAR(60) NOT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 1,
  is_active  BOOLEAN NOT NULL DEFAULT TRUE
);

ALTER TABLE grades
  ADD COLUMN stage_id INTEGER NULL REFERENCES educational_stages(id) ON DELETE SET NULL;

-- 5. Academic Terms
CREATE TABLE IF NOT EXISTS academic_terms (
  id               INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id INTEGER NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE,
  name_en          VARCHAR(60) NOT NULL,
  name_ar          VARCHAR(60) NOT NULL,
  term_number      SMALLINT NOT NULL DEFAULT 1,
  start_date       DATE NOT NULL,
  end_date         DATE NOT NULL,
  is_active        BOOLEAN NOT NULL DEFAULT FALSE,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 6. Grading Scales & Bands
CREATE TABLE IF NOT EXISTS grading_scales (
  id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name_en     VARCHAR(100) NOT NULL,
  name_ar     VARCHAR(100) NOT NULL,
  scale_type  VARCHAR(20) NOT NULL DEFAULT 'percentage' CHECK (scale_type IN ('percentage', 'letter', 'gpa', 'points')),
  is_default  BOOLEAN NOT NULL DEFAULT FALSE,
  description VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS grading_scale_bands (
  id               INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  grading_scale_id INTEGER NOT NULL REFERENCES grading_scales(id) ON DELETE CASCADE,
  band_label       VARCHAR(20) NOT NULL,
  min_score        NUMERIC(5,2) NOT NULL,
  max_score        NUMERIC(5,2) NOT NULL,
  gpa_point        NUMERIC(3,2) NULL,
  is_passing       BOOLEAN NOT NULL DEFAULT TRUE,
  description_en   VARCHAR(100) NULL,
  description_ar   VARCHAR(100) NULL
);

-- 7. Attendance Codes
CREATE TABLE IF NOT EXISTS attendance_codes (
  id                INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  code              VARCHAR(10) NOT NULL UNIQUE,
  name_en           VARCHAR(50) NOT NULL,
  name_ar           VARCHAR(50) NOT NULL,
  type              VARCHAR(20) NOT NULL CHECK (type IN ('present', 'absent', 'late', 'excused')),
  counts_as_present BOOLEAN NOT NULL DEFAULT FALSE,
  affects_truancy   BOOLEAN NOT NULL DEFAULT TRUE,
  is_active         BOOLEAN NOT NULL DEFAULT TRUE
);

-- 8. Guardians & Student Guardians
CREATE TABLE IF NOT EXISTS guardians (
  id                INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  full_name         VARCHAR(150) NOT NULL,
  phone             VARCHAR(30) NOT NULL,
  email             VARCHAR(100) NULL,
  national_id       VARCHAR(30) NULL,
  relationship_type VARCHAR(30) NOT NULL DEFAULT 'father',
  occupation        VARCHAR(100) NULL,
  address           VARCHAR(255) NULL,
  user_id           INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
  notes             TEXT NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_guardians_phone ON guardians(phone);
CREATE INDEX idx_guardians_national_id ON guardians(national_id);

CREATE TABLE IF NOT EXISTS student_guardians (
  id               INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  student_id       INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  guardian_id      INTEGER NOT NULL REFERENCES guardians(id) ON DELETE CASCADE,
  relationship     VARCHAR(30) NOT NULL DEFAULT 'father',
  is_primary       BOOLEAN NOT NULL DEFAULT TRUE,
  is_emergency     BOOLEAN NOT NULL DEFAULT TRUE,
  can_pickup       BOOLEAN NOT NULL DEFAULT TRUE,
  receives_reports BOOLEAN NOT NULL DEFAULT TRUE,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_student_guardian UNIQUE (student_id, guardian_id)
);

-- 9. Admissions Applications Pipeline
CREATE TABLE IF NOT EXISTS admissions_applications (
  id                    INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  application_number    VARCHAR(30) NOT NULL UNIQUE,
  applicant_name        VARCHAR(150) NOT NULL,
  gender                VARCHAR(5) NOT NULL CHECK (gender IN ('m', 'f')),
  date_of_birth         DATE NOT NULL,
  grade_id              INTEGER NOT NULL REFERENCES grades(id),
  academic_year_id      INTEGER NOT NULL REFERENCES academic_years(id),
  guardian_name         VARCHAR(150) NOT NULL,
  guardian_phone        VARCHAR(30) NOT NULL,
  guardian_email        VARCHAR(100) NULL,
  guardian_relationship VARCHAR(30) NOT NULL DEFAULT 'father',
  status                VARCHAR(20) NOT NULL DEFAULT 'inquiry' CHECK (status IN ('inquiry', 'application', 'review', 'assessment', 'decision', 'offer', 'enrolled', 'rejected', 'waitlisted')),
  assessment_date       DATE NULL,
  assessment_score      NUMERIC(5,2) NULL,
  assessment_notes      TEXT NULL,
  decision_date         DATE NULL,
  decision_notes        TEXT NULL,
  enrolled_student_id   INTEGER NULL REFERENCES students(id) ON DELETE SET NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_admissions_status ON admissions_applications(status);

-- 10. Finance Subsystem
CREATE TABLE IF NOT EXISTS fee_categories (
  id        INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  code      VARCHAR(30) NOT NULL UNIQUE,
  name_en   VARCHAR(100) NOT NULL,
  name_ar   VARCHAR(100) NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS fee_structures (
  id               INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  academic_year_id INTEGER NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE,
  grade_id         INTEGER NOT NULL REFERENCES grades(id) ON DELETE CASCADE,
  fee_category_id  INTEGER NOT NULL REFERENCES fee_categories(id) ON DELETE CASCADE,
  name_en          VARCHAR(150) NOT NULL,
  name_ar          VARCHAR(150) NOT NULL,
  amount           NUMERIC(10,2) NOT NULL,
  due_date         DATE NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS student_invoices (
  id               INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  invoice_number   VARCHAR(30) NOT NULL UNIQUE,
  student_id       INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  academic_year_id INTEGER NOT NULL REFERENCES academic_years(id) ON DELETE CASCADE,
  title            VARCHAR(150) NOT NULL,
  total_amount     NUMERIC(10,2) NOT NULL,
  discount_amount  NUMERIC(10,2) NOT NULL DEFAULT 0.00,
  paid_amount      NUMERIC(10,2) NOT NULL DEFAULT 0.00,
  status           VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'partial', 'paid', 'cancelled')),
  due_date         DATE NOT NULL,
  notes            TEXT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_invoices_student ON student_invoices(student_id);
CREATE INDEX idx_invoices_status ON student_invoices(status);

CREATE TABLE IF NOT EXISTS invoice_payments (
  id               INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  invoice_id       INTEGER NOT NULL REFERENCES student_invoices(id) ON DELETE CASCADE,
  receipt_number   VARCHAR(30) NOT NULL UNIQUE,
  amount           NUMERIC(10,2) NOT NULL,
  payment_date     DATE NOT NULL,
  payment_method   VARCHAR(20) NOT NULL DEFAULT 'cash' CHECK (payment_method IN ('cash', 'bank_transfer', 'card', 'cheque')),
  reference_number VARCHAR(60) NULL,
  notes            VARCHAR(255) NULL,
  recorded_by      INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 11. Human Resources Subsystem
CREATE TABLE IF NOT EXISTS staff_departments (
  id        INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  code      VARCHAR(30) NOT NULL UNIQUE,
  name_en   VARCHAR(100) NOT NULL,
  name_ar   VARCHAR(100) NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS staff_designations (
  id            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  department_id INTEGER NOT NULL REFERENCES staff_departments(id) ON DELETE CASCADE,
  title_en      VARCHAR(100) NOT NULL,
  title_ar      VARCHAR(100) NOT NULL,
  is_active     BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS staff_leaves (
  id           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  teacher_id   INTEGER NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
  leave_type   VARCHAR(20) NOT NULL DEFAULT 'annual' CHECK (leave_type IN ('annual', 'sick', 'emergency', 'unpaid')),
  start_date   DATE NOT NULL,
  end_date     DATE NOT NULL,
  reason       TEXT NOT NULL,
  status       VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
  approved_by  INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
  action_notes VARCHAR(255) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_leaves_teacher ON staff_leaves(teacher_id);
CREATE INDEX idx_leaves_status ON staff_leaves(status);

-- 12. Transport Operations
CREATE TABLE IF NOT EXISTS transport_routes (
  id             INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  route_name     VARCHAR(100) NOT NULL,
  vehicle_number VARCHAR(30) NOT NULL,
  driver_name    VARCHAR(100) NOT NULL,
  driver_phone   VARCHAR(30) NOT NULL,
  capacity       INTEGER NOT NULL DEFAULT 30,
  fee_amount     NUMERIC(10,2) NOT NULL DEFAULT 0.00,
  is_active      BOOLEAN NOT NULL DEFAULT TRUE,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS student_transport (
  id            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  student_id    INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
  route_id      INTEGER NOT NULL REFERENCES transport_routes(id) ON DELETE CASCADE,
  pickup_point  VARCHAR(150) NULL,
  dropoff_point VARCHAR(150) NULL,
  status        VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_student_route UNIQUE (student_id, route_id)
);

-- 13. Library Operations
CREATE TABLE IF NOT EXISTS library_books (
  id               INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  isbn             VARCHAR(30) NULL,
  title            VARCHAR(200) NOT NULL,
  author           VARCHAR(150) NOT NULL,
  category         VARCHAR(60) NOT NULL DEFAULT 'General',
  total_copies     INTEGER NOT NULL DEFAULT 1,
  available_copies INTEGER NOT NULL DEFAULT 1,
  shelf_location   VARCHAR(50) NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_books_title ON library_books(title);

CREATE TABLE IF NOT EXISTS library_borrows (
  id            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  book_id       INTEGER NOT NULL REFERENCES library_books(id) ON DELETE CASCADE,
  borrower_type VARCHAR(20) NOT NULL DEFAULT 'student' CHECK (borrower_type IN ('student', 'teacher')),
  student_id    INTEGER NULL REFERENCES students(id) ON DELETE SET NULL,
  teacher_id    INTEGER NULL REFERENCES teachers(id) ON DELETE SET NULL,
  borrow_date   DATE NOT NULL,
  due_date      DATE NOT NULL,
  return_date   DATE NULL,
  status        VARCHAR(20) NOT NULL DEFAULT 'borrowed' CHECK (status IN ('borrowed', 'returned', 'overdue')),
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 14. Initial Seeds (Postgres)
INSERT INTO installed_modules (module_key, name_en, name_ar, category, is_enabled, is_core) VALUES
  ('core', 'Core Foundation', 'المنظومة الأساسية', 'core', true, true),
  ('people', 'People & SIS', 'شؤون الطلاب والأسر', 'sis', true, true),
  ('academics', 'Academic Management', 'الإدارة الأكاديمية', 'academics', true, true),
  ('attendance', 'Attendance Management', 'إدارة الحضور والغياب', 'operations', true, true),
  ('exams', 'Exams & Assessment', 'الامتحانات والتقييم', 'assessment', true, true),
  ('welfare', 'Student Wellbeing', 'الرعاية والسلوك المدرسي', 'wellbeing', true, true),
  ('reports', 'Reports & Intelligence', 'التقارير وذكاء البيانات', 'intelligence', true, true),
  ('admissions', 'Admissions & Enrollment', 'القبول والتسجيل', 'admissions', true, false),
  ('finance', 'Finance & Billing', 'المالية والحسابات', 'erp', true, false),
  ('hr', 'Human Resources', 'الموارد البشرية وشؤون الموظفين', 'erp', true, false),
  ('transport', 'Transportation Operations', 'النقل والمواصلات', 'operations', true, false),
  ('library', 'Library Operations', 'المكتبة المدرسية', 'operations', true, false),
  ('communication', 'Communication & Notices', 'التواصل والإعلانات', 'community', true, true)
ON CONFLICT (module_key) DO UPDATE SET updated_at = CURRENT_TIMESTAMP;

INSERT INTO educational_stages (code, name_en, name_ar, sort_order, is_active) VALUES
  ('primary', 'Primary Stage', 'المرحلة الابتدائية', 1, true),
  ('preparatory', 'Preparatory Stage', 'المرحلة الإعدادية', 2, true),
  ('secondary', 'Secondary Stage', 'المرحلة الثانوية', 3, true)
ON CONFLICT (code) DO UPDATE SET name_en = EXCLUDED.name_en, name_ar = EXCLUDED.name_ar;

UPDATE grades SET stage_id = (SELECT id FROM educational_stages WHERE code = 'secondary' LIMIT 1) WHERE stage_id IS NULL;

INSERT INTO campuses (school_id, code, name_en, name_ar, address, phone, is_main, is_active)
SELECT id, 'MAIN', name, name_ar, address, phone, true, true FROM schools LIMIT 1
ON CONFLICT (code) DO UPDATE SET is_main = true;

INSERT INTO grading_scales (name_en, name_ar, scale_type, is_default, description) VALUES
  ('Standard 100-Point Scale', 'مقياس الدرجات المئوي القياسي', 'percentage', true, 'Standard 100-point scale with letter band conversions');

INSERT INTO fee_categories (code, name_en, name_ar, is_active) VALUES
  ('TUITION', 'Tuition Fees', 'المصروفات الدراسية', true),
  ('TRANSPORT', 'Transportation', 'رسوم النقل المدرسي', true),
  ('ACTIVITIES', 'Activities and Sports', 'الأنشطة المدرسية', true),
  ('BOOKS', 'Books and Materials', 'الكتب والموارد', true)
ON CONFLICT (code) DO UPDATE SET name_en = EXCLUDED.name_en;

INSERT INTO attendance_codes (code, name_en, name_ar, type, counts_as_present, affects_truancy, is_active) VALUES
  ('P', 'Present', 'حاضر', 'present', true, false, true),
  ('A', 'Absent (Unexcused)', 'غائب (بدون عذر)', 'absent', false, true, true),
  ('L', 'Late Arrival', 'متأخر', 'late', true, false, true),
  ('E', 'Excused Absence', 'غائب بعذر مقبول', 'excused', false, false, true)
ON CONFLICT (code) DO UPDATE SET name_en = EXCLUDED.name_en, name_ar = EXCLUDED.name_ar;

INSERT INTO roles (code, name_en, name_ar) VALUES
  ('owner', 'School Owner / Board', 'مالك المدرسة / مجلس الإدارة'),
  ('principal', 'School Principal', 'مدير المدرسة'),
  ('director', 'Academic Director', 'المشرف الأكاديمي'),
  ('registrar', 'Registrar', 'مسجل شؤون الطلاب'),
  ('admissions', 'Admissions Officer', 'مسؤول القبول والتسجيل'),
  ('teacher', 'Teacher', 'معلم'),
  ('class_teacher', 'Class Teacher', 'رائد الفصل'),
  ('attendance_officer', 'Attendance Supervisor', 'مسؤول الغياب والحضور'),
  ('accountant', 'Accountant', 'المحاسب المالي'),
  ('hr_officer', 'HR Manager', 'مسؤول الموارد البشرية'),
  ('student_affairs', 'Student Affairs Officer', 'أخصائي شؤون الطلاب'),
  ('health_officer', 'Health Officer', 'الزائرة الصحية / الطبيب'),
  ('librarian', 'Librarian', 'أمين المكتبة'),
  ('transport_manager', 'Transport Supervisor', 'مشرف النقل والمواصلات'),
  ('parent', 'Parent / Guardian', 'ولي الأمر'),
  ('student', 'Student', 'الطالب'),
  ('auditor', 'Read-only Auditor', 'مدقق / مراجع خارجي')
ON CONFLICT (code) DO UPDATE SET name_en = EXCLUDED.name_en, name_ar = EXCLUDED.name_ar;

INSERT INTO permissions (code, name_en, name_ar, module) VALUES
  ('guardians.view', 'View Guardians', 'عرض أولياء الأمور', 'people'),
  ('guardians.manage', 'Manage Guardians', 'إدارة أولياء الأمور', 'people'),
  ('admissions.view', 'View Admissions', 'عرض طلبات القبول', 'admissions'),
  ('admissions.manage', 'Manage Admissions', 'إدارة القبول والتسجيل', 'admissions'),
  ('admissions.convert', 'Convert Applicants to Students', 'تحويل المتقدمين لطلاب رسميين', 'admissions'),
  ('finance.view', 'View Invoices & Fees', 'عرض الفواتير والرسوم', 'finance'),
  ('finance.manage', 'Manage Fees & Invoices', 'إدارة الرسوم وتسجيل الدفعات', 'finance'),
  ('hr.view', 'View Staff HR & Leaves', 'عرض شؤون الموظفين والإجازات', 'hr'),
  ('hr.manage', 'Manage Staff & Leaves', 'إدارة شؤون الموظفين والإجازات', 'hr'),
  ('transport.view', 'View Transport Routes', 'عرض خطوط النقل والحافلات', 'transport'),
  ('transport.manage', 'Manage Transport Routes', 'إدارة خطوط النقل والحافلات', 'transport'),
  ('library.view', 'View Library Catalog', 'عرض كتب المكتبة والإعارات', 'library'),
  ('library.manage', 'Manage Library Catalog', 'إدارة كتب المكتبة وتسجيل الإعارات', 'library'),
  ('modules.manage', 'Manage Installed Modules', 'إدارة تفعيل وحدات المنصة', 'settings'),
  ('portal.family', 'Access Family Portal', 'دخول بوابة أولياء الأمور', 'portal'),
  ('portal.teacher', 'Access Teacher Workspace', 'دخول مساحة المعلم الأكاديمية', 'portal'),
  ('portal.student', 'Access Student Portal', 'دخول بوابة الطالب', 'portal')
ON CONFLICT (code) DO UPDATE SET name_en = EXCLUDED.name_en, name_ar = EXCLUDED.name_ar, module = EXCLUDED.module;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'admin'
ON CONFLICT (role_id, permission_id) DO NOTHING;

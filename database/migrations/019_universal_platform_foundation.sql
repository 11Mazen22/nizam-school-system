-- Nizam School Management System
-- Migration 019: universal_platform_foundation
-- Extends Nizam into a configurable, modular universal school platform.

-- 1. Extend schools profile
ALTER TABLE schools
  ADD COLUMN email VARCHAR(100) NULL AFTER phone,
  ADD COLUMN website VARCHAR(150) NULL AFTER email,
  ADD COLUMN country VARCHAR(10) NOT NULL DEFAULT 'EG' AFTER website,
  ADD COLUMN region VARCHAR(60) NULL AFTER country,
  ADD COLUMN timezone VARCHAR(50) NOT NULL DEFAULT 'Africa/Cairo' AFTER region,
  ADD COLUMN default_locale VARCHAR(10) NOT NULL DEFAULT 'ar' AFTER timezone,
  ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'EGP' AFTER default_locale,
  ADD COLUMN date_format VARCHAR(20) NOT NULL DEFAULT 'YYYY-MM-DD' AFTER currency,
  ADD COLUMN number_format VARCHAR(20) NOT NULL DEFAULT 'standard' AFTER date_format,
  ADD COLUMN school_week VARCHAR(50) NOT NULL DEFAULT '0,1,2,3,4' AFTER number_format,
  ADD COLUMN school_type VARCHAR(50) NOT NULL DEFAULT 'language_school' AFTER school_week,
  ADD COLUMN school_structure VARCHAR(50) NOT NULL DEFAULT 'k12_combined' AFTER school_type,
  ADD COLUMN tax_number VARCHAR(50) NULL AFTER school_structure,
  ADD COLUMN affiliation_code VARCHAR(50) NULL AFTER tax_number;

-- 2. Installed Modules Registry
CREATE TABLE IF NOT EXISTS installed_modules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module_key VARCHAR(50) NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100) NOT NULL,
  description_en VARCHAR(255) NULL,
  description_ar VARCHAR(255) NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'operations',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  is_core TINYINT(1) NOT NULL DEFAULT 0,
  settings LONGTEXT NULL,
  installed_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_module_key (module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Campuses
CREATE TABLE IF NOT EXISTS campuses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL DEFAULT 1,
  code VARCHAR(20) NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100) NOT NULL,
  address VARCHAR(255) NULL,
  phone VARCHAR(30) NULL,
  is_main TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_campuses_code (code),
  CONSTRAINT fk_campuses_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Educational Stages
CREATE TABLE IF NOT EXISTS educational_stages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL,
  name_en VARCHAR(60) NOT NULL,
  name_ar VARCHAR(60) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_stage_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE grades
  ADD COLUMN stage_id INT UNSIGNED NULL AFTER id,
  ADD CONSTRAINT fk_grades_stage FOREIGN KEY (stage_id) REFERENCES educational_stages(id) ON DELETE SET NULL;

-- 5. Academic Terms
CREATE TABLE IF NOT EXISTS academic_terms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NOT NULL,
  name_en VARCHAR(60) NOT NULL,
  name_ar VARCHAR(60) NOT NULL,
  term_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_terms_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Grading Scales & Bands
CREATE TABLE IF NOT EXISTS grading_scales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_en VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100) NOT NULL,
  scale_type ENUM('percentage', 'letter', 'gpa', 'points') NOT NULL DEFAULT 'percentage',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grading_scale_bands (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  grading_scale_id INT UNSIGNED NOT NULL,
  band_label VARCHAR(20) NOT NULL,
  min_score DECIMAL(5,2) NOT NULL,
  max_score DECIMAL(5,2) NOT NULL,
  gpa_point DECIMAL(3,2) NULL,
  is_passing TINYINT(1) NOT NULL DEFAULT 1,
  description_en VARCHAR(100) NULL,
  description_ar VARCHAR(100) NULL,
  CONSTRAINT fk_bands_scale FOREIGN KEY (grading_scale_id) REFERENCES grading_scales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Attendance Codes
CREATE TABLE IF NOT EXISTS attendance_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL,
  name_en VARCHAR(50) NOT NULL,
  name_ar VARCHAR(50) NOT NULL,
  type ENUM('present', 'absent', 'late', 'excused') NOT NULL,
  counts_as_present TINYINT(1) NOT NULL DEFAULT 0,
  affects_truancy TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_attendance_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Guardians & Student Guardians (SIS Single Source of Truth)
CREATE TABLE IF NOT EXISTS guardians (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(100) NULL,
  national_id VARCHAR(30) NULL,
  relationship_type VARCHAR(30) NOT NULL DEFAULT 'father',
  occupation VARCHAR(100) NULL,
  address VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_guardians_phone (phone),
  INDEX idx_guardians_national_id (national_id),
  CONSTRAINT fk_guardians_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_guardians (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  guardian_id INT UNSIGNED NOT NULL,
  relationship VARCHAR(30) NOT NULL DEFAULT 'father',
  is_primary TINYINT(1) NOT NULL DEFAULT 1,
  is_emergency TINYINT(1) NOT NULL DEFAULT 1,
  can_pickup TINYINT(1) NOT NULL DEFAULT 1,
  receives_reports TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_student_guardian (student_id, guardian_id),
  CONSTRAINT fk_sg_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_sg_guardian FOREIGN KEY (guardian_id) REFERENCES guardians(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Admissions Applications Pipeline
CREATE TABLE IF NOT EXISTS admissions_applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_number VARCHAR(30) NOT NULL,
  applicant_name VARCHAR(150) NOT NULL,
  gender ENUM('m', 'f') NOT NULL,
  date_of_birth DATE NOT NULL,
  grade_id INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  guardian_name VARCHAR(150) NOT NULL,
  guardian_phone VARCHAR(30) NOT NULL,
  guardian_email VARCHAR(100) NULL,
  guardian_relationship VARCHAR(30) NOT NULL DEFAULT 'father',
  status ENUM('inquiry', 'application', 'review', 'assessment', 'decision', 'offer', 'enrolled', 'rejected', 'waitlisted') NOT NULL DEFAULT 'inquiry',
  assessment_date DATE NULL,
  assessment_score DECIMAL(5,2) NULL,
  assessment_notes TEXT NULL,
  decision_date DATE NULL,
  decision_notes TEXT NULL,
  enrolled_student_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_admission_app_no (application_number),
  INDEX idx_admissions_status (status),
  CONSTRAINT fk_admissions_grade FOREIGN KEY (grade_id) REFERENCES grades(id),
  CONSTRAINT fk_admissions_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id),
  CONSTRAINT fk_admissions_enrolled_student FOREIGN KEY (enrolled_student_id) REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Finance Subsystem (Modular & Optional)
CREATE TABLE IF NOT EXISTS fee_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_fee_cat_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fee_structures (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NOT NULL,
  grade_id INT UNSIGNED NOT NULL,
  fee_category_id INT UNSIGNED NOT NULL,
  name_en VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  due_date DATE NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_fee_struct_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
  CONSTRAINT fk_fee_struct_grade FOREIGN KEY (grade_id) REFERENCES grades(id) ON DELETE CASCADE,
  CONSTRAINT fk_fee_struct_cat FOREIGN KEY (fee_category_id) REFERENCES fee_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(30) NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('pending', 'partial', 'paid', 'cancelled') NOT NULL DEFAULT 'pending',
  due_date DATE NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_invoice_number (invoice_number),
  INDEX idx_invoices_student (student_id),
  INDEX idx_invoices_status (status),
  CONSTRAINT fk_invoices_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_invoices_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT UNSIGNED NOT NULL,
  receipt_number VARCHAR(30) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_date DATE NOT NULL,
  payment_method ENUM('cash', 'bank_transfer', 'card', 'cheque') NOT NULL DEFAULT 'cash',
  reference_number VARCHAR(60) NULL,
  notes VARCHAR(255) NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_receipt_number (receipt_number),
  CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES student_invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Human Resources Subsystem (Modular & Optional)
CREATE TABLE IF NOT EXISTS staff_departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_department_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_designations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  title_en VARCHAR(100) NOT NULL,
  title_ar VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_designation_dept FOREIGN KEY (department_id) REFERENCES staff_departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_leaves (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT UNSIGNED NOT NULL,
  leave_type ENUM('annual', 'sick', 'emergency', 'unpaid') NOT NULL DEFAULT 'annual',
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  approved_by INT UNSIGNED NULL,
  action_notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_leaves_teacher (teacher_id),
  INDEX idx_leaves_status (status),
  CONSTRAINT fk_leaves_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  CONSTRAINT fk_leaves_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Transport Operations (Modular & Optional)
CREATE TABLE IF NOT EXISTS transport_routes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_name VARCHAR(100) NOT NULL,
  vehicle_number VARCHAR(30) NOT NULL,
  driver_name VARCHAR(100) NOT NULL,
  driver_phone VARCHAR(30) NOT NULL,
  capacity INT UNSIGNED NOT NULL DEFAULT 30,
  fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_transport (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  route_id INT UNSIGNED NOT NULL,
  pickup_point VARCHAR(150) NULL,
  dropoff_point VARCHAR(150) NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_student_route (student_id, route_id),
  CONSTRAINT fk_trans_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_trans_route FOREIGN KEY (route_id) REFERENCES transport_routes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Library Operations (Modular & Optional)
CREATE TABLE IF NOT EXISTS library_books (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  isbn VARCHAR(30) NULL,
  title VARCHAR(200) NOT NULL,
  author VARCHAR(150) NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'General',
  total_copies INT UNSIGNED NOT NULL DEFAULT 1,
  available_copies INT UNSIGNED NOT NULL DEFAULT 1,
  shelf_location VARCHAR(50) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_books_title (title),
  INDEX idx_books_isbn (isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_borrows (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id INT UNSIGNED NOT NULL,
  borrower_type ENUM('student', 'teacher') NOT NULL DEFAULT 'student',
  student_id INT UNSIGNED NULL,
  teacher_id INT UNSIGNED NULL,
  borrow_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE NULL,
  status ENUM('borrowed', 'returned', 'overdue') NOT NULL DEFAULT 'borrowed',
  created_at DATETIME NOT NULL,
  INDEX idx_borrows_status (status),
  CONSTRAINT fk_borrows_book FOREIGN KEY (book_id) REFERENCES library_books(id) ON DELETE CASCADE,
  CONSTRAINT fk_borrows_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
  CONSTRAINT fk_borrows_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Initial Seed Data for Universal Foundation
-- Modules
INSERT INTO installed_modules (module_key, name_en, name_ar, category, is_enabled, is_core, installed_at, updated_at) VALUES
  ('core', 'Core Foundation', 'المنظومة الأساسية', 'core', 1, 1, NOW(), NOW()),
  ('people', 'People & SIS', 'شؤون الطلاب والأسر', 'sis', 1, 1, NOW(), NOW()),
  ('academics', 'Academic Management', 'الإدارة الأكاديمية', 'academics', 1, 1, NOW(), NOW()),
  ('attendance', 'Attendance Management', 'إدارة الحضور والغياب', 'operations', 1, 1, NOW(), NOW()),
  ('exams', 'Exams & Assessment', 'الامتحانات والتقييم', 'assessment', 1, 1, NOW(), NOW()),
  ('welfare', 'Student Wellbeing', 'الرعاية والسلوك المدرسي', 'wellbeing', 1, 1, NOW(), NOW()),
  ('reports', 'Reports & Intelligence', 'التقارير وذكاء البيانات', 'intelligence', 1, 1, NOW(), NOW()),
  ('admissions', 'Admissions & Enrollment', 'القبول والتسجيل', 'admissions', 1, 0, NOW(), NOW()),
  ('finance', 'Finance & Billing', 'المالية والحسابات', 'erp', 1, 0, NOW(), NOW()),
  ('hr', 'Human Resources', 'الموارد البشرية وشؤون الموظفين', 'erp', 1, 0, NOW(), NOW()),
  ('transport', 'Transportation Operations', 'النقل والمواصلات', 'operations', 1, 0, NOW(), NOW()),
  ('library', 'Library Operations', 'المكتبة المدرسية', 'operations', 1, 0, NOW(), NOW()),
  ('communication', 'Communication & Notices', 'التواصل والإعلانات', 'community', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Educational Stages
INSERT INTO educational_stages (code, name_en, name_ar, sort_order, is_active) VALUES
  ('primary', 'Primary Stage', 'المرحلة الابتدائية', 1, 1),
  ('preparatory', 'Preparatory Stage', 'المرحلة الإعدادية', 2, 1),
  ('secondary', 'Secondary Stage', 'المرحلة الثانوية', 3, 1)
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_ar = VALUES(name_ar);

-- Link existing grades to stages
UPDATE grades SET stage_id = (SELECT id FROM educational_stages WHERE code = 'secondary' LIMIT 1) WHERE stage_id IS NULL;

-- Default Main Campus
INSERT INTO campuses (school_id, code, name_en, name_ar, address, phone, is_main, is_active, created_at)
SELECT id, 'MAIN', name, name_ar, address, phone, 1, 1, NOW() FROM schools LIMIT 1
ON DUPLICATE KEY UPDATE is_main = 1;

-- Default Grading Scale
INSERT INTO grading_scales (name_en, name_ar, scale_type, is_default, description, created_at) VALUES
  ('Standard 100-Point Scale', 'مقياس الدرجات المئوي القياسي', 'percentage', 1, 'Standard 100-point scale with letter band conversions', NOW());

SET @scale_id = LAST_INSERT_ID();
INSERT INTO grading_scale_bands (grading_scale_id, band_label, min_score, max_score, gpa_point, is_passing, description_en, description_ar) VALUES
  (@scale_id, 'A+', 95.00, 100.00, 4.00, 1, 'Excellent with Honors', 'ممتاز مع مرتبة الشرف'),
  (@scale_id, 'A',  85.00, 94.99,  3.75, 1, 'Excellent', 'ممتاز'),
  (@scale_id, 'B',  75.00, 84.99,  3.00, 1, 'Very Good', 'جيد جداً'),
  (@scale_id, 'C',  65.00, 74.99,  2.00, 1, 'Good', 'جيد'),
  (@scale_id, 'D',  50.00, 64.99,  1.00, 1, 'Pass', 'مقبول'),
  (@scale_id, 'F',   0.00, 49.99,  0.00, 0, 'Fail', 'راسب');

-- Default Fee Categories
INSERT INTO fee_categories (code, name_en, name_ar, is_active) VALUES
  ('TUITION', 'Tuition Fees', 'المصروفات الدراسية', 1),
  ('TRANSPORT', 'Transportation', 'رسوم النقل المدرسي', 1),
  ('ACTIVITIES', 'Activities and Sports', 'الأنشطة المدرسية', 1),
  ('BOOKS', 'Books and Materials', 'الكتب والموارد', 1)
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en);

-- Attendance Codes
INSERT INTO attendance_codes (code, name_en, name_ar, type, counts_as_present, affects_truancy, is_active) VALUES
  ('P', 'Present', 'حاضر', 'present', 1, 0, 1),
  ('A', 'Absent (Unexcused)', 'غائب (بدون عذر)', 'absent', 0, 1, 1),
  ('L', 'Late Arrival', 'متأخر', 'late', 1, 0, 1),
  ('E', 'Excused Absence', 'غائب بعذر مقبول', 'excused', 0, 0, 1)
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_ar = VALUES(name_ar);

-- New Role Templates
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
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_ar = VALUES(name_ar);

-- New Permissions
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
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_ar = VALUES(name_ar), module = VALUES(module);

-- Grant all permissions to admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'admin'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

$pdo = App\Database::connection();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('This fixture requires MySQL/MariaDB');
}
$pdo->exec('CREATE TEMPORARY TABLE academic_years (id INT PRIMARY KEY, label VARCHAR(30), start_date DATE, end_date DATE, is_active TINYINT, is_closed TINYINT)');
$pdo->exec("CREATE TEMPORARY TABLE students (id INT PRIMARY KEY, student_code VARCHAR(20), full_name VARCHAR(150), gender CHAR(1), date_of_birth DATE, religion VARCHAR(20), phone VARCHAR(30), guardian_phone VARCHAR(30), address VARCHAR(255), photo_path VARCHAR(255), notes TEXT, status VARCHAR(20))");
$pdo->exec('CREATE TEMPORARY TABLE student_enrollments (id INT PRIMARY KEY, student_id INT, academic_year_id INT, grade_id INT, class_id INT, enrollment_date DATE, status VARCHAR(20), previous_enrollment_id INT NULL)');
$pdo->exec('CREATE TEMPORARY TABLE activity_logs (user_id INT, action VARCHAR(100), entity_type VARCHAR(100), entity_id INT, description TEXT, metadata TEXT, ip_address VARCHAR(64))');
$pdo->exec("INSERT INTO academic_years VALUES (1,'Open','2026-01-01','2026-12-31',1,0),(2,'Closed','2025-01-01','2025-12-31',0,1)");
$pdo->exec("INSERT INTO students VALUES (1,'S1','Student One','m','2010-01-01','muslim',NULL,NULL,NULL,NULL,NULL,'active'),(2,'S2','Student Two','f','2010-01-01','muslim',NULL,NULL,NULL,NULL,NULL,'active')");
$pdo->exec("INSERT INTO student_enrollments VALUES (1,1,1,1,1,'2026-01-01','active',NULL),(2,2,2,1,1,'2025-01-01','active',NULL)");
$service = new App\Services\StudentService();
$check = static function (bool $condition, string $message): void { if (!$condition) throw new LogicException($message); };
App\Middleware\AcademicYearContext::set(['id' => 1, 'is_closed' => false]);
$service->archive(1);
$check($pdo->query('SELECT status FROM students WHERE id=1')->fetchColumn() === 'archived', 'Student archive did not persist');
$check($pdo->query('SELECT status FROM student_enrollments WHERE id=1')->fetchColumn() === 'withdrawn', 'Enrollment was not withdrawn');
$service->restore(1);
$check($pdo->query('SELECT status FROM students WHERE id=1')->fetchColumn() === 'active', 'Student restore did not persist');
$check($pdo->query('SELECT status FROM student_enrollments WHERE id=1')->fetchColumn() === 'active', 'Enrollment was not restored');
App\Middleware\AcademicYearContext::set(['id' => 2, 'is_closed' => true]);
try {
    $service->archive(2);
    throw new LogicException('Closed-year archive unexpectedly succeeded');
} catch (RuntimeException $e) {
    $check($e->getMessage() === 'year_closed', 'Unexpected closed-year error');
}
$check($pdo->query('SELECT status FROM students WHERE id=2')->fetchColumn() === 'active', 'Closed-year archive changed student');
echo "PASS: student/enrollment archive symmetry and closed-year protection\n";

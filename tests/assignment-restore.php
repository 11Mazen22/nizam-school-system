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
$pdo->exec('CREATE TEMPORARY TABLE teacher_assignments (id INT PRIMARY KEY, teacher_id INT, subject_id INT, class_id INT, academic_year_id INT, weekly_periods INT, status VARCHAR(20))');
$pdo->exec('CREATE TEMPORARY TABLE users (id INT PRIMARY KEY)');
$pdo->exec('CREATE TEMPORARY TABLE activity_logs (user_id INT, action VARCHAR(100), entity_type VARCHAR(100), entity_id INT, description TEXT, metadata TEXT, ip_address VARCHAR(64))');
$pdo->exec("INSERT INTO academic_years VALUES (1,'Open','2026-01-01','2026-12-31',1,0),(2,'Closed','2025-01-01','2025-12-31',0,1)");
$pdo->exec("INSERT INTO teacher_assignments VALUES
 (1,1,1,1,1,4,'active'), (2,2,2,2,1,4,'archived'), (3,3,3,3,2,4,'active'),
 (4,4,4,4,1,4,'archived'), (5,4,4,4,1,4,'active')");
$service = new App\Services\AssignmentService();
$check = static function (bool $condition, string $message): void { if (!$condition) throw new LogicException($message); };
$service->archive(1);
$check($pdo->query('SELECT status FROM teacher_assignments WHERE id=1')->fetchColumn() === 'archived', 'Archive did not persist');
$service->restore(1);
$check($pdo->query('SELECT status FROM teacher_assignments WHERE id=1')->fetchColumn() === 'active', 'Restore did not persist');
foreach ([[3, 'archive', 'year_closed'], [1, 'restore', 'not_archived'], [4, 'restore', 'duplicate']] as [$id, $operation, $error]) {
    try {
        $service->{$operation}($id);
        throw new LogicException("{$operation} unexpectedly succeeded");
    } catch (RuntimeException $e) {
        $check($e->getMessage() === $error, "Unexpected {$operation} error");
    }
}
$check($pdo->query('SELECT status FROM teacher_assignments WHERE id=3')->fetchColumn() === 'active', 'Closed-year archive changed data');
$check($pdo->query('SELECT status FROM teacher_assignments WHERE id=4')->fetchColumn() === 'archived', 'Duplicate restore changed data');
echo "PASS: archive/restore persistence, closed-year and duplicate safeguards\n";

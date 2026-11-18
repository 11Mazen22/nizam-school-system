<?php

declare(strict_types=1);

/**
 * Nizam -- import the official third-secondary (الصف الثالث) spreadsheet.
 *
 * Canonical source: database/official/third_secondary_students.xlsx, with the
 * reviewed derivations in database/official/third-secondary-audit.json.
 * Names come from the spreadsheet, gender/date of birth from the national ID,
 * and religion from the confirmed name-based classification. Every run is
 * idempotent: students are matched by national ID or school student code.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Repositories\ClassRepository;
use App\Services\StudentService;

$root = dirname(__DIR__);
$xlsx = $root . '/database/official/third_secondary_students.xlsx';
$auditFile = $root . '/database/official/third-secondary-audit.json';
foreach ([$xlsx, $auditFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing source file: {$file}\n");
        exit(1);
    }
}

$audit = json_decode((string) file_get_contents($auditFile), true, 512, JSON_THROW_ON_ERROR);
if ((int) ($audit['version'] ?? 0) !== 1
    || (int) ($audit['totals']['students'] ?? 0) !== 540
    || hash_file('sha256', $xlsx) !== $audit['source_sha256']) {
    fwrite(STDERR, "Third-secondary audit does not match the committed spreadsheet.\n");
    exit(1);
}

$pdo = Database::connection();
$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$year = $pdo->query("SELECT id, label, is_closed FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetch();
if ($year === false || (string) $year['label'] !== '2026/2027') {
    fwrite(STDERR, "The active academic year must be 2026/2027.\n");
    exit(1);
}
$yearId = (int) $year['id'];
AcademicYearContext::set([
    'id' => $yearId,
    'label' => (string) $year['label'],
    'is_closed' => (int) $year['is_closed'],
]);

// Reuse the grade that already exists in the school structure, or create it.
$gradeStmt = $pdo->query("SELECT id FROM grades WHERE name_ar = 'الصف الثالث الثانوي' OR sort_order = 3 ORDER BY id LIMIT 1");
$gradeId = (int) ($gradeStmt->fetchColumn() ?: 0);
if ($gradeId === 0) {
    $gradeId = Database::insert(
        $pdo,
        "INSERT INTO grades (name_en, name_ar, sort_order, is_active) VALUES ('Third Secondary Grade', 'الصف الثالث الثانوي', 3, 1)"
    );
    echo "Created third-secondary grade #{$gradeId}\n";
}

$classesRepo = new ClassRepository();
$classIds = [];
foreach ((array) ($audit['class_counts'] ?? []) as $className => $expectedCount) {
    $className = (string) $className;
    $stmt = $pdo->prepare('SELECT id FROM classes WHERE academic_year_id = :y AND grade_id = :g AND name = :n');
    $stmt->execute(['y' => $yearId, 'g' => $gradeId, 'n' => $className]);
    $classId = (int) ($stmt->fetchColumn() ?: 0);
    if ($classId === 0) {
        $classId = $classesRepo->create($gradeId, $yearId, $className, 50);
        echo "Created class {$className}\n";
    }
    $classIds[$className] = $classId;
}

$service = new StudentService();
$created = 0;
$updated = 0;
$enrolled = 0;

foreach ((array) ($audit['students'] ?? []) as $record) {
    $nationalId = (string) ($record['national_id'] ?? '');
    $sourceCode = (string) ($record['source_code'] ?? '');
    $className = (string) ($record['class'] ?? '');
    $classId = (int) ($classIds[$className] ?? 0);
    if ($nationalId === '' || $classId === 0) {
        throw new RuntimeException('Invalid third-secondary source row.');
    }

    $lookup = $pdo->prepare('SELECT id FROM students WHERE national_id = :nid OR source_student_code = :code ORDER BY id LIMIT 1');
    $lookup->execute(['nid' => $nationalId, 'code' => $sourceCode]);
    $studentId = (int) ($lookup->fetchColumn() ?: 0);

    if ($studentId > 0) {
        $update = $pdo->prepare(
            'UPDATE students
                SET full_name = :name, gender = :gender, date_of_birth = :dob,
                    religion = :religion, religion_source = :religion_source,
                    gender_source = :gender_source, national_id = :nid,
                    source_student_code = :code, status = \'active\'
              WHERE id = :id'
        );
        $update->execute([
            'name' => (string) $record['full_name'],
            'gender' => (string) $record['gender'],
            'dob' => (string) $record['date_of_birth'],
            'religion' => (string) $record['religion'],
            'religion_source' => (string) $record['religion_source'],
            'gender_source' => (string) $record['gender_source'],
            'nid' => $nationalId,
            'code' => $sourceCode,
            'id' => $studentId,
        ]);
        $updated++;
    } else {
        $studentId = $service->create(
            (string) $record['full_name'],
            (string) $record['gender'],
            (string) $record['date_of_birth'],
            (string) $record['religion'],
            null, null, null, null,
            $gradeId,
            $classId
        );
        $extra = $pdo->prepare(
            'UPDATE students
                SET national_id = :nid, source_student_code = :code,
                    religion_source = :religion_source, gender_source = :gender_source,
                    second_language_source = NULL
              WHERE id = :id'
        );
        $extra->execute([
            'nid' => $nationalId,
            'code' => $sourceCode,
            'religion_source' => (string) $record['religion_source'],
            'gender_source' => (string) $record['gender_source'],
            'id' => $studentId,
        ]);
        $created++;
    }

    $enrollment = $pdo->prepare(
        "SELECT id FROM student_enrollments WHERE student_id = :s AND academic_year_id = :y AND status = 'active'"
    );
    $enrollment->execute(['s' => $studentId, 'y' => $yearId]);
    $enrollmentId = (int) ($enrollment->fetchColumn() ?: 0);
    if ($enrollmentId === 0) {
        $insert = $driver === 'pgsql'
            ? 'INSERT INTO student_enrollments (student_id, academic_year_id, grade_id, class_id, enrollment_date, status) VALUES (:s, :y, :g, :c, :date, \'active\') RETURNING id'
            : 'INSERT INTO student_enrollments (student_id, academic_year_id, grade_id, class_id, enrollment_date, status) VALUES (:s, :y, :g, :c, :date, \'active\')';
        $insertStmt = $pdo->prepare($insert);
        $insertStmt->execute([
            's' => $studentId, 'y' => $yearId, 'g' => $gradeId,
            'c' => $classId, 'date' => '2026-09-01',
        ]);
        $enrolled++;
    } else {
        $reassign = $pdo->prepare(
            "UPDATE student_enrollments SET grade_id = :g, class_id = :c WHERE id = :id AND status = 'active'"
        );
        $reassign->execute(['g' => $gradeId, 'c' => $classId, 'id' => $enrollmentId]);
    }
}

// ------------------------------------------------------------- verification
$count = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
$total = (int) ($audit['totals']['students'] ?? 0);
$checks = [
    'classes' => $count('SELECT COUNT(*) FROM classes WHERE academic_year_id = ' . $yearId . ' AND grade_id = ' . $gradeId),
    'students' => $count('SELECT COUNT(*) FROM students WHERE national_id IS NOT NULL AND national_id <> \'\''),
    'enrollments' => $count('SELECT COUNT(*) FROM student_enrollments e JOIN students s ON s.id = e.student_id WHERE e.academic_year_id = ' . $yearId . ' AND e.status = \'active\' AND s.national_id IS NOT NULL'),
    'gender' => $count("SELECT COUNT(*) FROM students WHERE national_id IS NOT NULL AND gender IN ('m','f') AND gender_source = 'national_id_digit_13'"),
    'dob' => $count('SELECT COUNT(*) FROM students WHERE national_id IS NOT NULL AND date_of_birth IS NOT NULL'),
    'religion' => $count('SELECT COUNT(*) FROM students WHERE national_id IS NOT NULL AND religion IN (\'muslim\',\'christian\') AND religion_source LIKE \'name_inference_%\''),
];
$problems = [];
foreach (['classes' => 12, 'students' => $total, 'enrollments' => $total, 'gender' => $total, 'dob' => $total, 'religion' => $total] as $key => $expected) {
    if ($checks[$key] !== $expected) {
        $problems[] = "{$key}: {$checks[$key]} of {$expected}";
    }
}
if ($problems !== []) {
    throw new RuntimeException('Third-secondary verification failed: ' . implode('; ', $problems));
}

echo "Third secondary: {$created} created, {$updated} updated, {$enrolled} enrollments added.\n";
echo 'Verified: ' . json_encode($checks) . "\n";

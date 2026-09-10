<?php
/**
 * Nizam -- Phase 3 runtime verification. Phase 3 scope: this is verification
 * tooling for the database layer (explicitly in-scope per the Phase 3 kickoff:
 * "database verification needed to prove Phase 3 is complete"), not Phase 4
 * application code.
 *
 * Usage: php database/verify.php
 *
 * Checks, in order: schema (tables/columns), foreign keys, unique constraints
 * and indexes, seed data, then seven integrity tests. Every integrity test
 * runs inside its own transaction that is ALWAYS rolled back, win or lose --
 * nothing it does is left behind, per this phase's explicit "do not damage or
 * modify production data" instruction. Exits 0 if everything passes, 1 otherwise.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';

if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config/config.php. Copy config/config.example.php and fill in real values first.\n");
    exit(1);
}

$config = require $configPath;
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to the database: {$e->getMessage()}\n");
    exit(1);
}

$dbName = $config['database'];
/** @var array<int, array{section:string, check:string, pass:bool, detail:string}> $results */
$results = [];

function record(array &$results, string $section, string $check, bool $pass, string $detail = ''): void
{
    $results[] = ['section' => $section, 'check' => $check, 'pass' => $pass, 'detail' => $detail];
    $mark = $pass ? 'PASS' : 'FAIL';
    echo "  [$mark] $check" . ($detail !== '' ? " -- $detail" : '') . "\n";
}

// ---------------------------------------------------------------------------
echo "== Environment ==\n";
$serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
$sqlMode = $pdo->query('SELECT @@sql_mode AS m')->fetch()['m'];
echo "  Server version string: $serverVersion\n";
echo "  Default sql_mode: $sqlMode\n";
$strict = str_contains($sqlMode, 'STRICT_TRANS_TABLES') || str_contains($sqlMode, 'STRICT_ALL_TABLES');
echo "  Strict mode active: " . ($strict ? 'yes' : 'no') . "\n";
if (!$strict) {
    echo "  NOTE: without strict mode, an invalid ENUM value is silently stored as ''\n";
    echo "  with a warning instead of being rejected. This session forces\n";
    echo "  STRICT_ALL_TABLES for the integrity tests below so they mean something\n";
    echo "  regardless of the server's own my.ini default -- flagged here as a real\n";
    echo "  consideration for Phase 4's own PDO connection layer, not something Phase\n";
    echo "  3 changes on the server itself.\n";
}
echo "\n";

// ---------------------------------------------------------------------------
echo "== Schema: tables ==\n";
$expectedTables = [
    'schools', 'settings', 'roles', 'permissions', 'role_permissions', 'users',
    'academic_years', 'grades', 'subjects', 'subject_staffing_requirements',
    'classes', 'students', 'student_enrollments', 'teachers', 'teacher_subjects',
    'teacher_assignments', 'activity_logs', 'backups', 'login_attempts', 'migrations',
    'promotion_locks',
];
$stmt = $pdo->prepare('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db');
$stmt->execute(['db' => $dbName]);
$actualTables = array_column($stmt->fetchAll(), 'TABLE_NAME');
foreach ($expectedTables as $table) {
    record($results, 'schema', "table `$table` exists", in_array($table, $actualTables, true));
}
$extra = array_diff($actualTables, $expectedTables);
if (!empty($extra)) {
    record($results, 'schema', 'no unexpected tables', false, 'unexpected: ' . implode(', ', $extra));
} else {
    record($results, 'schema', 'no unexpected tables', true);
}

// ---------------------------------------------------------------------------
echo "\n== Schema: columns (name presence per table) ==\n";
$expectedColumns = [
    'schools' => ['id', 'name', 'name_ar', 'logo_path', 'address', 'phone', 'created_at'],
    'settings' => ['id', 'setting_key', 'value', 'value_type', 'updated_at'],
    'roles' => ['id', 'code', 'name_en', 'name_ar'],
    'permissions' => ['id', 'code', 'name_en', 'name_ar', 'module'],
    'role_permissions' => ['role_id', 'permission_id'],
    'users' => ['id', 'username', 'password_hash', 'full_name', 'role_id', 'is_active', 'failed_attempts', 'locked_until', 'last_login_at', 'created_at', 'updated_at'],
    'academic_years' => ['id', 'label', 'start_date', 'end_date', 'is_active', 'is_closed', 'expected_weekly_capacity', 'active_flag', 'created_at'],
    'grades' => ['id', 'name_en', 'name_ar', 'sort_order', 'is_active'],
    'subjects' => ['id', 'code', 'name_en', 'name_ar', 'is_active'],
    'subject_staffing_requirements' => ['id', 'subject_id', 'academic_year_id', 'required_teachers'],
    'classes' => ['id', 'grade_id', 'academic_year_id', 'name', 'capacity', 'is_active', 'created_at', 'updated_at'],
    'students' => ['id', 'student_code', 'full_name', 'gender', 'date_of_birth', 'religion', 'phone', 'guardian_phone', 'address', 'photo_path', 'notes', 'status', 'created_at', 'updated_at'],
    'student_enrollments' => ['id', 'student_id', 'academic_year_id', 'grade_id', 'class_id', 'enrollment_date', 'status', 'previous_enrollment_id', 'created_at'],
    'teachers' => ['id', 'teacher_code', 'full_name', 'phone', 'email', 'photo_path', 'status', 'created_at', 'updated_at'],
    'teacher_subjects' => ['teacher_id', 'subject_id'],
    'teacher_assignments' => ['id', 'teacher_id', 'subject_id', 'class_id', 'academic_year_id', 'weekly_periods', 'status', 'created_at', 'updated_at'],
    'activity_logs' => ['id', 'user_id', 'action', 'entity_type', 'entity_id', 'description', 'metadata', 'ip_address', 'created_at'],
    'backups' => ['id', 'filename', 'file_size', 'type', 'status', 'created_by', 'notes', 'created_at'],
    'login_attempts' => ['id', 'username', 'ip_address', 'attempted_at', 'success'],
    'migrations' => ['id', 'migration', 'applied_at'],
    'promotion_locks' => ['academic_year_id', 'locked_at'],
];
$colStmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t');
foreach ($expectedColumns as $table => $columns) {
    if (!in_array($table, $actualTables, true)) {
        continue; // already reported missing above
    }
    $colStmt->execute(['db' => $dbName, 't' => $table]);
    $actualCols = array_column($colStmt->fetchAll(), 'COLUMN_NAME');
    $missing = array_diff($columns, $actualCols);
    $unexpected = array_diff($actualCols, $columns);
    $pass = empty($missing) && empty($unexpected);
    $detail = [];
    if (!empty($missing)) $detail[] = 'missing: ' . implode(',', $missing);
    if (!empty($unexpected)) $detail[] = 'unexpected: ' . implode(',', $unexpected);
    record($results, 'schema', "`$table` columns match", $pass, implode('; ', $detail));
}

// ---------------------------------------------------------------------------
echo "\n== Schema: type-critical columns ==\n";
function columnInfo(PDO $pdo, string $db, string $table, string $column): ?array
{
    $stmt = $pdo->prepare('SELECT IS_NULLABLE, COLUMN_TYPE, COLUMN_DEFAULT, GENERATION_EXPRESSION
        FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t AND COLUMN_NAME=:c');
    $stmt->execute(['db' => $db, 't' => $table, 'c' => $column]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$info = columnInfo($pdo, $dbName, 'student_enrollments', 'status');
record($results, 'schema', "student_enrollments.status includes 'graduated'",
    $info !== null && str_contains($info['COLUMN_TYPE'], "'graduated'"), $info['COLUMN_TYPE'] ?? 'column not found');

$info = columnInfo($pdo, $dbName, 'student_enrollments', 'class_id');
record($results, 'schema', 'student_enrollments.class_id is nullable',
    $info !== null && $info['IS_NULLABLE'] === 'YES', $info['IS_NULLABLE'] ?? 'column not found');

$info = columnInfo($pdo, $dbName, 'academic_years', 'active_flag');
record($results, 'schema', 'academic_years.active_flag is a generated column',
    $info !== null && !empty($info['GENERATION_EXPRESSION']), $info['GENERATION_EXPRESSION'] ?? 'column not found');

$info = columnInfo($pdo, $dbName, 'settings', 'setting_key');
record($results, 'schema', "settings uses setting_key, not the reserved word 'key'", $info !== null);

// ---------------------------------------------------------------------------
echo "\n== Foreign keys ==\n";
function fkExists(PDO $pdo, string $db, string $childTable, string $constraintName, array $expectedChildCols, string $expectedParentTable, array $expectedParentCols): array
{
    $stmt = $pdo->prepare("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, ORDINAL_POSITION
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND CONSTRAINT_NAME = :c
        ORDER BY ORDINAL_POSITION");
    $stmt->execute(['db' => $db, 't' => $childTable, 'c' => $constraintName]);
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        return [false, 'constraint not found'];
    }
    $childCols = array_column($rows, 'COLUMN_NAME');
    $parentCols = array_column($rows, 'REFERENCED_COLUMN_NAME');
    $parentTable = $rows[0]['REFERENCED_TABLE_NAME'];
    $ok = $childCols === $expectedChildCols && $parentCols === $expectedParentCols && $parentTable === $expectedParentTable;
    return [$ok, "columns=" . implode(',', $childCols) . " -> $parentTable(" . implode(',', $parentCols) . ")"];
}

$fkChecks = [
    // [child table, constraint name, child cols, parent table, parent cols]
    ['student_enrollments', 'fk_enrollments_class_grade_year', ['class_id', 'grade_id', 'academic_year_id'], 'classes', ['id', 'grade_id', 'academic_year_id']],
    ['student_enrollments', 'fk_enrollments_grade', ['grade_id'], 'grades', ['id']],
    ['student_enrollments', 'fk_enrollments_academic_year', ['academic_year_id'], 'academic_years', ['id']],
    ['student_enrollments', 'fk_enrollments_student', ['student_id'], 'students', ['id']],
    ['student_enrollments', 'fk_enrollments_previous', ['previous_enrollment_id'], 'student_enrollments', ['id']],
    ['teacher_assignments', 'fk_assignments_class_year', ['class_id', 'academic_year_id'], 'classes', ['id', 'academic_year_id']],
    ['teacher_assignments', 'fk_assignments_teacher', ['teacher_id'], 'teachers', ['id']],
    ['teacher_assignments', 'fk_assignments_subject', ['subject_id'], 'subjects', ['id']],
    ['classes', 'fk_classes_grade', ['grade_id'], 'grades', ['id']],
    ['classes', 'fk_classes_academic_year', ['academic_year_id'], 'academic_years', ['id']],
    ['users', 'fk_users_role', ['role_id'], 'roles', ['id']],
    ['role_permissions', 'fk_role_permissions_role', ['role_id'], 'roles', ['id']],
    ['role_permissions', 'fk_role_permissions_permission', ['permission_id'], 'permissions', ['id']],
    ['teacher_subjects', 'fk_teacher_subjects_teacher', ['teacher_id'], 'teachers', ['id']],
    ['teacher_subjects', 'fk_teacher_subjects_subject', ['subject_id'], 'subjects', ['id']],
    ['subject_staffing_requirements', 'fk_ssr_subject', ['subject_id'], 'subjects', ['id']],
    ['subject_staffing_requirements', 'fk_ssr_academic_year', ['academic_year_id'], 'academic_years', ['id']],
    ['activity_logs', 'fk_activity_logs_user', ['user_id'], 'users', ['id']],
    ['backups', 'fk_backups_created_by', ['created_by'], 'users', ['id']],
    ['promotion_locks', 'fk_promotion_locks_year', ['academic_year_id'], 'academic_years', ['id']],
];
foreach ($fkChecks as [$table, $name, $childCols, $parentTable, $parentCols]) {
    [$ok, $detail] = fkExists($pdo, $dbName, $table, $name, $childCols, $parentTable, $parentCols);
    record($results, 'foreign keys', "$table.$name", $ok, $detail);
}

// ---------------------------------------------------------------------------
echo "\n== Unique constraints & indexes ==\n";
function indexExists(PDO $pdo, string $db, string $table, string $indexName, bool $expectUnique): array
{
    $stmt = $pdo->prepare('SELECT NON_UNIQUE FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t AND INDEX_NAME=:i LIMIT 1');
    $stmt->execute(['db' => $db, 't' => $table, 'i' => $indexName]);
    $row = $stmt->fetch();
    if (!$row) {
        return [false, 'not found'];
    }
    $isUnique = ((int) $row['NON_UNIQUE']) === 0;
    return [$isUnique === $expectUnique, $isUnique ? 'unique' : 'non-unique'];
}

$indexChecks = [
    // [table, index name, expect unique]
    ['students', 'uq_students_student_code', true],
    ['students', 'idx_students_status', false],
    ['students', 'idx_students_full_name', false],
    ['teachers', 'uq_teachers_teacher_code', true],
    ['teachers', 'idx_teachers_full_name', false],
    ['classes', 'uq_classes_grade_year_name', true],
    ['classes', 'uq_classes_id_grade_year', true],
    ['classes', 'uq_classes_id_year', true],
    ['academic_years', 'uq_academic_years_label', true],
    ['academic_years', 'uq_academic_years_active_flag', true],
    ['student_enrollments', 'uq_enrollments_student_year', true],
    ['student_enrollments', 'idx_enrollments_year_class', false],
    ['teacher_assignments', 'uq_assignments_teacher_subject_class_year', true],
    ['teacher_assignments', 'idx_assignments_year_teacher', false],
    ['settings', 'uq_settings_setting_key', true],
    ['roles', 'uq_roles_code', true],
    ['permissions', 'uq_permissions_code', true],
    ['subjects', 'uq_subjects_code', true],
    ['users', 'uq_users_username', true],
    ['migrations', 'uq_migrations_migration', true],
    ['subject_staffing_requirements', 'uq_ssr_subject_year', true],
    ['activity_logs', 'idx_activity_logs_created_at', false],
    ['activity_logs', 'idx_activity_logs_user_id', false],
    ['login_attempts', 'idx_login_attempts_username_time', false],
    ['login_attempts', 'idx_login_attempts_ip_time', false],
];
foreach ($indexChecks as [$table, $name, $expectUnique]) {
    [$ok, $detail] = indexExists($pdo, $dbName, $table, $name, $expectUnique);
    record($results, 'indexes', "$table.$name", $ok, $detail);
}

// ---------------------------------------------------------------------------
echo "\n== Seed data ==\n";
function scalar(PDO $pdo, string $sql): mixed
{
    return $pdo->query($sql)->fetchColumn();
}

record($results, 'seeds', 'roles: exactly 2 rows (admin, staff)',
    (int) scalar($pdo, "SELECT COUNT(*) FROM roles WHERE code IN ('admin','staff')") === 2);
record($results, 'seeds', 'permissions: exactly 28 rows',
    (int) scalar($pdo, 'SELECT COUNT(*) FROM permissions') === 28, (string) scalar($pdo, 'SELECT COUNT(*) FROM permissions'));
$adminGrants = (int) scalar($pdo, "SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='admin'");
$staffGrants = (int) scalar($pdo, "SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id WHERE r.code='staff'");
record($results, 'seeds', 'admin has all 28 permission grants', $adminGrants === 28, "found $adminGrants");
record($results, 'seeds', 'staff has exactly the 12 §J-listed grants', $staffGrants === 12, "found $staffGrants");
record($results, 'seeds', 'settings: exactly 10 rows (school.name/name_ar intentionally absent)',
    (int) scalar($pdo, 'SELECT COUNT(*) FROM settings') === 10, (string) scalar($pdo, 'SELECT COUNT(*) FROM settings'));
record($results, 'seeds', 'settings: school.name / school.name_ar NOT seeded (Setup Wizard writes these)',
    (int) scalar($pdo, "SELECT COUNT(*) FROM settings WHERE setting_key IN ('school.name','school.name_ar')") === 0);
record($results, 'seeds', 'subjects: exactly 7 rows',
    (int) scalar($pdo, 'SELECT COUNT(*) FROM subjects') === 7, (string) scalar($pdo, 'SELECT COUNT(*) FROM subjects'));
record($results, 'seeds', 'grades: exactly 3 rows',
    (int) scalar($pdo, 'SELECT COUNT(*) FROM grades') === 3, (string) scalar($pdo, 'SELECT COUNT(*) FROM grades'));
record($results, 'seeds', 'no fake/demo students were seeded',
    (int) scalar($pdo, 'SELECT COUNT(*) FROM students') === 0);
record($results, 'seeds', 'no fake/demo teachers were seeded',
    (int) scalar($pdo, 'SELECT COUNT(*) FROM teachers') === 0);
record($results, 'seeds', 'no academic year was seeded (Setup Wizard creates the first one)',
    (int) scalar($pdo, 'SELECT COUNT(*) FROM academic_years') === 0);
record($results, 'seeds', 'no school row was seeded (Setup Wizard creates it)',
    (int) scalar($pdo, 'SELECT COUNT(*) FROM schools') === 0);

// ---------------------------------------------------------------------------
echo "\n== Integrity tests (each rolled back unconditionally -- nothing is kept) ==\n";

/**
 * Runs $setup then $attempt inside one transaction, expects $attempt to throw
 * a PDOException (the invalid state must be rejected), and ALWAYS rolls back --
 * regardless of whether the attempt threw or unexpectedly succeeded.
 */
function expectRejected(PDO $pdo, array &$results, string $name, callable $body): void
{
    $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
    $pdo->beginTransaction();
    try {
        $body($pdo);
        // If we get here, the invalid statement did NOT throw -- that's a failure.
        record($results, 'integrity', $name, false, 'invalid state was NOT rejected');
    } catch (PDOException $e) {
        record($results, 'integrity', $name, true, substr($e->getMessage(), 0, 120));
    } catch (RuntimeException $e) {
        // A test precondition wasn't met (e.g. an expected seed row is missing) --
        // this is NOT the same as "the constraint worked," so it must not be
        // reported as a pass. Distinguished from PDOException deliberately.
        record($results, 'integrity', $name, false, 'INCONCLUSIVE: ' . $e->getMessage());
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

// Shared fixtures used by several tests, inserted once and rolled back at the very end
// of the whole script (a single wrapping transaction) so they never touch real data,
// while still being visible to each individual test's own nested logic. PDO/MySQL do
// not support real nested transactions, so instead each test below creates and tears
// down what it needs within its OWN transaction, self-contained.

expectRejected($pdo, $results, '1. enrollment referencing a class from the WRONG GRADE', function (PDO $pdo) {
    $pdo->exec("INSERT INTO academic_years (label, start_date, end_date) VALUES ('TEST-1', '2099-09-01', '2100-06-30')");
    $yearId = (int) $pdo->lastInsertId();
    $gradeIds = array_column($pdo->query('SELECT id FROM grades ORDER BY sort_order LIMIT 2')->fetchAll(), 'id');
    if (count($gradeIds) < 2) {
        throw new RuntimeException('test needs at least 2 seeded grades to run');
    }
    [$gradeA, $gradeB] = $gradeIds;
    $pdo->exec("INSERT INTO classes (grade_id, academic_year_id, name) VALUES ($gradeA, $yearId, 'T1')");
    $classId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO students (student_code, full_name, gender, date_of_birth, religion) VALUES ('TESTSTU1','Test Student','m','2015-01-01','other')");
    $studentId = (int) $pdo->lastInsertId();
    // class belongs to $gradeA, but this enrollment claims $gradeB -- must be rejected.
    $pdo->exec("INSERT INTO student_enrollments (student_id, academic_year_id, grade_id, class_id, enrollment_date, status)
        VALUES ($studentId, $yearId, $gradeB, $classId, '2099-09-01', 'active')");
});

expectRejected($pdo, $results, '2. teacher assignment referencing a class from the WRONG ACADEMIC YEAR', function (PDO $pdo) {
    $pdo->exec("INSERT INTO academic_years (label, start_date, end_date) VALUES ('TEST-2A', '2099-09-01', '2100-06-30')");
    $yearA = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO academic_years (label, start_date, end_date) VALUES ('TEST-2B', '2100-09-01', '2101-06-30')");
    $yearB = (int) $pdo->lastInsertId();
    $gradeId = (int) $pdo->query('SELECT id FROM grades ORDER BY sort_order LIMIT 1')->fetchColumn();
    $pdo->exec("INSERT INTO classes (grade_id, academic_year_id, name) VALUES ($gradeId, $yearA, 'T2')");
    $classId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO teachers (teacher_code, full_name) VALUES ('TESTTCH1','Test Teacher')");
    $teacherId = (int) $pdo->lastInsertId();
    $subjectId = (int) $pdo->query('SELECT id FROM subjects LIMIT 1')->fetchColumn();
    // class belongs to $yearA, but this assignment claims $yearB -- must be rejected.
    $pdo->exec("INSERT INTO teacher_assignments (teacher_id, subject_id, class_id, academic_year_id, weekly_periods)
        VALUES ($teacherId, $subjectId, $classId, $yearB, 5)");
});

expectRejected($pdo, $results, '3. duplicate unique value (two roles with the same code)', function (PDO $pdo) {
    $pdo->exec("INSERT INTO roles (code, name_en, name_ar) VALUES ('admin', 'Duplicate Admin', 'مدير مكرر')");
});

expectRejected($pdo, $results, '4. invalid foreign-key reference (enrollment -> nonexistent student)', function (PDO $pdo) {
    $pdo->exec("INSERT INTO academic_years (label, start_date, end_date) VALUES ('TEST-4', '2099-09-01', '2100-06-30')");
    $yearId = (int) $pdo->lastInsertId();
    $gradeId = (int) $pdo->query('SELECT id FROM grades ORDER BY sort_order LIMIT 1')->fetchColumn();
    $pdo->exec("INSERT INTO student_enrollments (student_id, academic_year_id, grade_id, class_id, enrollment_date, status)
        VALUES (999999999, $yearId, $gradeId, NULL, '2099-09-01', 'active')");
});

expectRejected($pdo, $results, '5. invalid enrollment status (value outside the ENUM)', function (PDO $pdo) {
    $pdo->exec("INSERT INTO academic_years (label, start_date, end_date) VALUES ('TEST-5', '2099-09-01', '2100-06-30')");
    $yearId = (int) $pdo->lastInsertId();
    $gradeId = (int) $pdo->query('SELECT id FROM grades ORDER BY sort_order LIMIT 1')->fetchColumn();
    $pdo->exec("INSERT INTO students (student_code, full_name, gender, date_of_birth, religion) VALUES ('TESTSTU5','Test Student','f','2015-01-01','other')");
    $studentId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO student_enrollments (student_id, academic_year_id, grade_id, class_id, enrollment_date, status)
        VALUES ($studentId, $yearId, $gradeId, NULL, '2099-09-01', 'not_a_real_status')");
});

expectRejected($pdo, $results, '6. multiple active academic years', function (PDO $pdo) {
    $pdo->exec("INSERT INTO academic_years (label, start_date, end_date, is_active) VALUES ('TEST-6A', '2099-09-01', '2100-06-30', 1)");
    $pdo->exec("INSERT INTO academic_years (label, start_date, end_date, is_active) VALUES ('TEST-6B', '2100-09-01', '2101-06-30', 1)");
});

expectRejected($pdo, $results, '7. enrollment referencing a class from the WRONG ACADEMIC YEAR (same grade)', function (PDO $pdo) {
    $pdo->exec("INSERT INTO academic_years (label, start_date, end_date) VALUES ('TEST-7A', '2099-09-01', '2100-06-30')");
    $yearA = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO academic_years (label, start_date, end_date) VALUES ('TEST-7B', '2100-09-01', '2101-06-30')");
    $yearB = (int) $pdo->lastInsertId();
    $gradeId = (int) $pdo->query('SELECT id FROM grades ORDER BY sort_order LIMIT 1')->fetchColumn();
    $pdo->exec("INSERT INTO classes (grade_id, academic_year_id, name) VALUES ($gradeId, $yearA, 'T7')");
    $classId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO students (student_code, full_name, gender, date_of_birth, religion) VALUES ('TESTSTU7','Test Student','m','2015-01-01','other')");
    $studentId = (int) $pdo->lastInsertId();
    // class belongs to $yearA, but this enrollment claims $yearB (grade matches) -- must be rejected.
    $pdo->exec("INSERT INTO student_enrollments (student_id, academic_year_id, grade_id, class_id, enrollment_date, status)
        VALUES ($studentId, $yearB, $gradeId, $classId, '2099-09-01', 'active')");
});

// ---------------------------------------------------------------------------
echo "\n== Summary ==\n";
$total = count($results);
$passed = count(array_filter($results, static fn ($r) => $r['pass']));
$failed = $total - $passed;
echo "  $passed / $total checks passed.\n";
if ($failed > 0) {
    echo "\n  Failing checks:\n";
    foreach ($results as $r) {
        if (!$r['pass']) {
            echo "    - [{$r['section']}] {$r['check']}" . ($r['detail'] !== '' ? " ({$r['detail']})" : '') . "\n";
        }
    }
}
exit($failed > 0 ? 1 : 0);

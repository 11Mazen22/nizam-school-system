<?php

declare(strict_types=1);

/**
 * Nizam -- read-only production status.
 *
 *   php bin/production-status.php
 *
 * Prints the live schema version and the row counts of the records a school
 * actually recognises (classes, students, teachers, timetable slots, weekly
 * assessments). Used to answer "is this deployment really carrying the
 * school's data?" without opening the database to the network and without
 * touching a single row: every statement here is a SELECT.
 *
 * It reads the same generated config as the application, so in the container
 * it is run through packaging-online/entrypoint.sh with
 * NIZAM_MIGRATE_ON_START=0 -- that writes the least-privilege runtime config
 * and then hands over to this script.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Services\MigrationService;

$pdo = Database::connection();
$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$migrationsDir = dirname(__DIR__) . '/database/' . ($driver === 'pgsql' ? 'migrations-pg' : 'migrations');

$counts = [
    'academic_years' => 'academic years',
    'grades' => 'grades',
    'classes' => 'classes',
    'subjects' => 'subjects',
    'teachers' => 'teachers',
    'students' => 'students (all statuses)',
    'student_enrollments' => 'enrollments',
    'timetables' => 'timetable slots',
    'assessment_schedules' => 'weekly assessment rows',
    'exam_scores' => 'exam scores',
    'attendance_records' => 'attendance records',
    'users' => 'user accounts',
];

printf("Driver: %s\n", $driver);
try {
    $version = (string) $pdo->query('SELECT MAX(migration) FROM migrations')->fetchColumn();
    $applied = count((new MigrationService())->getAppliedMigrations($pdo));
    printf("Schema: %d migrations applied, latest %s\n", $applied, $version !== '' ? $version : '(none)');
} catch (Throwable) {
    echo "Schema: no migrations table (fresh database)\n";
}

$year = null;
try {
    $year = $pdo->query(
        'SELECT label, is_active FROM academic_years ORDER BY is_active DESC, id DESC LIMIT 1'
    )->fetch();
} catch (Throwable) {
    // Reported by the counts below as zero rows.
}

if ($year !== false && $year !== null) {
    printf("Academic year: %s%s\n", (string) $year['label'], ((int) $year['is_active'] === 1 ? ' (active)' : ''));
}

echo "\n";
$width = max(array_map('strlen', array_values($counts)));
foreach ($counts as $table => $label) {
    try {
        $total = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        printf("  %-{$width}s : %d\n", $label, $total);
    } catch (Throwable) {
        printf("  %-{$width}s : (table missing)\n", $label);
    }
}

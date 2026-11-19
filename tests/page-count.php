<?php

declare(strict_types=1);

/**
 * Pagination contract: the number stamped on the last designed page must be
 * the number of physical pages in the PDF Chromium actually produced.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Services\Documents\DocumentEngine;
use App\Services\Documents\DocumentIdentity;
use App\Services\Documents\PdfPageCount;

$pdo = Database::connection();
$year = $pdo->query('SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1')->fetch();
if ($year === false) {
    fwrite(STDERR, "BLOCKED: no active academic year\n");
    exit(2);
}
AcademicYearContext::set($year);
$scalar = static fn (string $sql) => (string) ($pdo->query($sql)->fetchColumn() ?: '');

$classId = $scalar('SELECT id FROM classes WHERE academic_year_id = ' . (int) $year['id'] . ' LIMIT 1');
$studentId = $scalar('SELECT student_id FROM student_enrollments WHERE academic_year_id = ' . (int) $year['id'] . " AND status = 'active' LIMIT 1");
$teacherId = $scalar('SELECT teacher_id FROM teacher_assignments WHERE academic_year_id = ' . (int) $year['id'] . " AND status = 'active' LIMIT 1");
$invoiceId = $scalar('SELECT id FROM student_invoices LIMIT 1');

$params = [
    'students' => ['class_id' => $classId],
    'student' => ['id' => $studentId],
    'teachers' => [],
    'teacher' => ['id' => $teacherId],
    'timetable-class' => ['class_id' => $classId],
    'timetable-teacher' => ['teacher_id' => $teacherId],
    'timetable-master' => [],
    'report' => ['report' => 'religion', 'year' => (string) $year['id']],
    'attendance' => ['class_id' => $classId],
    'finance' => [],
    'invoice' => $invoiceId !== '' ? ['id' => $invoiceId] : null,
    'report-card' => ['student_id' => $studentId],
    'ranking' => ['class_id' => $classId],
    'timetable-gaps' => [],
    'timetable-covers' => ['date' => date('Y-m-d')],
    'promotion' => null, // needs two real academic years
];

$identity = new DocumentIdentity(
    nameAr: 'مدرسة اختبار', nameEn: 'Test School', logoDataUri: null,
    ministry: '', directorate: '', administration: '', address: '', phone: '', email: '',
    yearLabel: (string) $year['label'], issueDate: date('d/m/Y'),
    locale: 'ar', showSignatures: false, showWatermark: false,
);

$engine = new DocumentEngine();
$passed = 0;
$failed = 0;
$skipped = 0;
foreach ($params as $key => $documentParams) {
    if ($documentParams === null) {
        $skipped++;
        echo "  SKIP  {$key} (no real source records)\n";
        continue;
    }
    try {
        $built = $engine->build($key, $documentParams, ['signatures' => false]);
        $composed = $engine->previewPages($key, $documentParams, ['signatures' => false])['pages'];
        $pdf = $engine->pdfBytes($built['document'], $built['identity'], ['signatures' => false]);
        $actual = PdfPageCount::fromBytes($pdf['bytes']);
        $ok = $composed === $actual;
        $ok ? $passed++ : $failed++;
        printf("  %s  %-20s composed=%d renderer=%d pdf=%d\n", $ok ? 'ok  ' : 'FAIL', $key, $composed, $pdf['pages'], $actual);
    } catch (Throwable $e) {
        $failed++;
        echo "  FAIL  {$key}: " . $e->getMessage() . "\n";
    }
}

echo "\nPage count: {$passed} passed, {$failed} failed, {$skipped} skipped\n";
exit($failed === 0 ? 0 : 1);

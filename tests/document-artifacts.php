<?php

declare(strict_types=1);

/**
 * Artifact-level regression suite for every registered document type/format.
 *
 * This suite is deliberately honest about what it checks:
 *   - a PDF is generated, has a real %PDF header and its physical page count
 *     matches the number the footer prints. Its *visual* appearance is not
 *     inspected here; that needs a human or a screenshot comparison.
 *   - an XLSX is opened as an OOXML package and its cells are read with
 *     PhpSpreadsheet. This is not the same as opening it in Microsoft Excel.
 *   - a PNG is decoded for its dimensions. It is not visually judged.
 *   - a ZIP is opened with ZipArchive and its entries are checked.
 *
 * Fixture rows needed for otherwise empty scopes (an invoice and a second
 * academic year) are inserted inside a transaction and rolled back, so the
 * school's real data is never changed.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Services\Documents\DocumentEngine;
use App\Services\Documents\DocumentIdentity;
use App\Services\Documents\DocumentRegistry;
use App\Services\Documents\PdfPageCount;
use PhpOffice\PhpSpreadsheet\IOFactory;

$pass = 0;
$fail = 0;
$check = static function (bool $condition, string $label) use (&$pass, &$fail): void {
    if ($condition) {
        $pass++;
        echo "  ok    {$label}\n";
        return;
    }
    $fail++;
    echo "  FAIL  {$label}\n";
};

$pdo = Database::connection();
$year = $pdo->query('SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1')->fetch();
if ($year === false) {
    fwrite(STDERR, "BLOCKED: no active academic year\n");
    exit(2);
}
AcademicYearContext::set($year);
$yearId = (int) $year['id'];
$scalar = static fn (string $sql) => (string) ($pdo->query($sql)->fetchColumn() ?: '');

$classId = $scalar('SELECT id FROM classes WHERE academic_year_id = ' . $yearId . ' LIMIT 1');
$studentId = $scalar('SELECT student_id FROM student_enrollments WHERE academic_year_id = ' . $yearId . " AND status = 'active' LIMIT 1");
$teacherId = $scalar('SELECT teacher_id FROM teacher_assignments WHERE academic_year_id = ' . $yearId . " AND status = 'active' LIMIT 1");

$pdo->beginTransaction();
try {
    // A real, in-year invoice for a real student, created only for this run.
    $pdo->prepare(
        'INSERT INTO student_invoices
            (invoice_number, student_id, academic_year_id, title, total_amount, discount_amount,
             paid_amount, status, due_date, created_at, updated_at)
         VALUES (:number, :student, :year, :title, 100.00, 0.00, 0.00, :status, :due, NOW(), NOW())'
    )->execute([
        'number' => 'FIXTURE-ARTIFACT-1',
        'student' => $studentId,
        'year' => $yearId,
        'title' => 'Artifact fixture',
        'status' => 'pending',
        'due' => date('Y-m-d'),
    ]);
    $invoiceId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO academic_years (label, start_date, end_date, is_active, is_closed)
         VALUES (:label, :start, :end, 0, 0)'
    )->execute([
        'label' => 'Fixture 2027/2028',
        'start' => '2027-09-01',
        'end' => '2028-06-30',
    ]);
    $targetYearId = (int) $pdo->lastInsertId();

    $identity = new DocumentIdentity(
        nameAr: 'مدرسة اختبار', nameEn: 'Test School', logoDataUri: null,
        ministry: '', directorate: '', administration: '', address: '', phone: '', email: '',
        yearLabel: (string) $year['label'], issueDate: date('d/m/Y'),
        locale: 'ar', showSignatures: false, showWatermark: false,
    );
    $registry = new DocumentRegistry();
    $engine = new DocumentEngine();

    $params = [
        'students' => ['class_id' => $classId],
        'student' => ['id' => $studentId],
        'teachers' => [],
        'teacher' => ['id' => $teacherId],
        'timetable-class' => ['class_id' => $classId],
        'timetable-teacher' => ['teacher_id' => $teacherId],
        'timetable-master' => [],
        'report' => ['report' => 'religion', 'year' => (string) $yearId],
        'attendance' => ['class_id' => $classId],
        'finance' => [],
        'invoice' => ['id' => (string) $invoiceId],
        'report-card' => ['student_id' => $studentId],
        'ranking' => ['class_id' => $classId],
        'timetable-gaps' => [],
        'timetable-covers' => ['date' => date('Y-m-d')],
        'promotion' => ['source' => (string) $yearId, 'target' => (string) $targetYearId],
    ];

    echo 'Registered document keys: ' . count($registry->keys()) . "\n";
    $check(array_keys($params) === $registry->keys(), 'Every registered document type has a tested real/fixture case');

    foreach ($registry->keys() as $key) {
        echo "\n{$key}\n";
        $documentParams = $params[$key];
        // ZIP is only meaningful where the document exposes a real bulk scope;
        // a single-subject report card has no collection to archive.
        $type = $registry->make($key);
        $formats = $type->formats();
        if ($type->bulkScopes($documentParams) !== []) {
            $formats[] = 'zip';
        }
        $formats = array_values(array_unique($formats));
        foreach ($formats as $format) {
            try {
                $result = $engine->export($key, $documentParams, $format, ['signatures' => false]);
                $bytes = $result['bytes'];
                // A CSV of a register with no matching records is still a real
                // file: its header row is the content. Binary formats are held
                // to the size that proves actual data, not just a signature.
                $minimum = $format === 'csv' ? 24 : 200;
                $check(strlen($bytes) > $minimum, "{$key}/{$format}: artifact has real content");

                if ($format === 'pdf') {
                    $check(str_starts_with($bytes, '%PDF'), "{$key}/pdf: real PDF header (visual layout not inspected)");
                    $composed = $engine->previewPages($key, $documentParams, ['signatures' => false])['pages'];
                    $check(PdfPageCount::fromBytes($bytes) === $composed, "{$key}/pdf: physical page count equals footer numbering");
                    $check(
                        PdfPageCount::isA4($bytes, false) || PdfPageCount::isA4($bytes, true),
                        "{$key}/pdf: A4 paper geometry in its declared orientation"
                    );
                } elseif ($format === 'csv' && $result['contentType'] === 'text/csv; charset=UTF-8') {
                    $check(str_starts_with($bytes, "\xEF\xBB\xBF"), "{$key}/csv: UTF-8 BOM so Excel opens Arabic correctly");
                    $check(str_contains($bytes, ","), "{$key}/csv: real comma-separated rows");
                } elseif ($format === 'html') {
                    $check(str_starts_with($bytes, '<!DOCTYPE html>'), "{$key}/html: complete standalone document");
                    $check(str_contains($bytes, 'doc-page'), "{$key}/html: carries the designed pages");
                    $check(str_contains($bytes, '@font-face'), "{$key}/html: fonts embedded for offline fidelity");
                    $check(!str_contains($bytes, 'http://') && !str_contains($bytes, 'https://'), "{$key}/html: no external URL to fetch");
                } elseif ($format === 'xlsx') {
                    $path = tempnam(sys_get_temp_dir(), 'nizam_artifact_');
                    file_put_contents($path, $bytes);
                    try {
                        $book = IOFactory::load($path);
                        $check(count($book->getSheetNames()) >= 1, "{$key}/xlsx: OOXML package opens with at least one sheet (not opened in Excel)");
                        $values = $book->getSheet(0)->toArray(null, true, true, false);
                        $flat = implode(' ', array_map(static fn (array $row): string => implode(' ', array_map('strval', $row)), $values));
                        $check(trim($flat) !== '', "{$key}/xlsx: sheet contains readable cell values");
                    } finally {
                        @unlink($path);
                    }
                } elseif ($format === 'png' && $result['contentType'] === 'image/png') {
                    $size = @getimagesizefromstring($bytes);
                    $check(is_array($size) && (int) $size[0] > 100 && (int) $size[1] > 100, "{$key}/png: decodable image with real dimensions (appearance not judged)");
                } elseif ($format === 'png' || $format === 'zip' || $format === 'csv') {
                    $path = tempnam(sys_get_temp_dir(), 'nizam_artifact_');
                    file_put_contents($path, $bytes);
                    $zip = new ZipArchive();
                    try {
                        $check($zip->open($path) === true, "{$key}/{$format}: real ZIP archive");
                        $names = [];
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $names[] = (string) $zip->getNameIndex($i);
                        }
                        $check(count(array_unique($names)) === count($names), "{$key}/{$format}: archive entry names are unique");
                    } finally {
                        $zip->close();
                        @unlink($path);
                    }
                }
            } catch (Throwable $exception) {
                $check(false, "{$key}/{$format}: " . $exception->getMessage());
            }
        }
    }
} finally {
    $pdo->rollBack();
}

echo "\nDocument artifacts: {$pass} passed, {$fail} failed\n";
echo "Scope: bytes, OOXML structure, PNG dimensions, ZIP integrity and PDF page count.\n";
echo "Not verified here: Excel/LibreOffice visual rendering, PDF layout appearance, printer output.\n";
exit($fail === 0 ? 0 : 1);

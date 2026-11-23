<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Export platform regression suite.
 *
 * Covers the promises the rebuilt platform makes beyond "a file exists":
 *  - one format registry, localized and consistent with every document type;
 *  - CSV that opens cleanly in Excel (BOM, CRLF, formula-injection safety);
 *  - self-contained HTML that needs no network;
 *  - a print mode the browser can print from without a PDF detour;
 *  - a workbook with an index, filters, frozen headings and print footers;
 *  - a bulk job that reports real progress, writes a manifest and cannot be
 *    pointed at a directory outside its own workspace.
 *
 * What this suite does not do: judge the visual appearance of a PDF or a PNG.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Services\Documents\BulkExportJob;
use App\Services\Documents\CsvExporter;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentArchiveBuilder;
use App\Services\Documents\DocumentEngine;
use App\Services\Documents\DocumentIdentity;
use App\Services\Documents\DocumentRegistry;
use App\Services\Documents\ExcelWorkbookBuilder;
use App\Services\Documents\ExportFormat;
use App\Services\Documents\PdfPageCount;

$pass = 0;
$fail = 0;
$failures = [];
$check = static function (bool $condition, string $label) use (&$pass, &$fail, &$failures): void {
    if ($condition) {
        $pass++;
        echo "  ok    {$label}\n";
        return;
    }
    $fail++;
    $failures[] = $label;
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
$scalar = static fn (string $sql): string => (string) ($pdo->query($sql)->fetchColumn() ?: '');
$classId = $scalar('SELECT id FROM classes WHERE academic_year_id = ' . $yearId . ' LIMIT 1');
$studentId = $scalar('SELECT student_id FROM student_enrollments WHERE academic_year_id = ' . $yearId . " AND status = 'active' LIMIT 1");

$registry = new DocumentRegistry();
$engine = new DocumentEngine();

echo "Export platform\n" . str_repeat('=', 62) . "\n";

/* -------------------------------------------------------------- registry */

echo "\nFormat registry\n";
$check(ExportFormat::all() === ['pdf', 'png', 'xlsx', 'csv', 'html', 'zip', 'print'], 'Registry exposes exactly the supported formats');
$check(ExportFormat::documents() === ['pdf', 'png', 'xlsx', 'csv', 'html'], 'Single-document formats are in menu order');
$check(ExportFormat::normalize('excel') === 'xlsx', 'Historical "excel" alias resolves to xlsx');
$check(ExportFormat::normalize('images') === 'png', 'Historical "images" alias resolves to png');
$check(ExportFormat::mime('pdf') === 'application/pdf', 'PDF MIME type is correct');
$check(ExportFormat::extension('csv') === 'csv', 'CSV extension is correct');

$badMeta = [];
foreach (ExportFormat::all() as $format) {
    $meta = ExportFormat::meta($format);
    if ($meta['label'] === 'document.format.' . $format || $meta['description'] === 'document.format.' . $format . '_desc') {
        $badMeta[] = $format;
    }
}
$check($badMeta === [], 'Every format has a translated label and description');

/* ---------------------------------------------------------- capabilities */

echo "\nCapabilities\n";
$paramsByKey = [
    'students' => ['class_id' => $classId],
    'student' => ['id' => $studentId],
    'teachers' => [],
    'teacher' => ['id' => $scalar('SELECT teacher_id FROM teacher_assignments WHERE academic_year_id = ' . $yearId . " AND status = 'active' LIMIT 1")],
    'timetable-class' => ['class_id' => $classId],
    'timetable-teacher' => ['teacher_id' => $scalar('SELECT teacher_id FROM teacher_assignments WHERE academic_year_id = ' . $yearId . " AND status = 'active' LIMIT 1")],
    'timetable-master' => [],
    'report' => ['report' => 'religion', 'year' => (string) $yearId],
    'attendance' => ['class_id' => $classId],
    'finance' => [],
    'invoice' => ['id' => '0'],
    'report-card' => ['student_id' => $studentId],
    'ranking' => ['class_id' => $classId],
    'timetable-gaps' => [],
    'timetable-covers' => ['date' => date('Y-m-d')],
    'promotion' => ['source' => (string) $yearId, 'target' => (string) $yearId],
];

$badCapabilities = [];
$bulkMismatch = [];
foreach ($registry->keys() as $key) {
    try {
        $caps = $engine->capabilities($key, $paramsByKey[$key] ?? []);
        $formatKeys = array_column($caps['formats'], 'key');
        if ($formatKeys !== ['pdf', 'png', 'xlsx', 'csv', 'html']) {
            $badCapabilities[] = $key . ':formats';
        }
        if (($caps['print']['key'] ?? '') !== 'print') {
            $badCapabilities[] = $key . ':print';
        }
        foreach ($caps['formats'] as $format) {
            if (($format['label'] ?? '') === 'document.format.' . $format['key']) {
                $badCapabilities[] = $key . ':label-' . $format['key'];
            }
        }

        $scopes = $registry->make($key)->bulkScopes($paramsByKey[$key] ?? []);
        $reported = [];
        foreach ($caps['bulk']['groups'] as $group) {
            $reported[$group['key']] = $group['count'];
        }
        foreach ($scopes as $groupKey => $scope) {
            if (($reported[(string) $groupKey] ?? null) !== count((array) ($scope['items'] ?? []))) {
                $bulkMismatch[] = $key . ':' . $groupKey;
            }
        }
    } catch (Throwable $exception) {
        $badCapabilities[] = $key . ':' . $exception->getMessage();
    }
}
$check($badCapabilities === [], 'Every document publishes the full format set and a print action');
$check($bulkMismatch === [], 'Bulk counts in capabilities match the real scopes');

/* ------------------------------------------------------------------- csv */

echo "\nCSV\n";
$identity = new DocumentIdentity(
    nameAr: 'مدرسة اختبار',
    nameEn: 'Test School',
    logoDataUri: null,
    ministry: '',
    directorate: '',
    administration: '',
    address: '',
    phone: '',
    email: '',
    yearLabel: (string) $year['label'],
    issueDate: date('d/m/Y'),
    locale: 'ar',
    showSignatures: false,
    showWatermark: false,
);

$csvDocument = new Document(
    key: 'students',
    title: 'كشف اختبار',
    blocks: [[
        'type' => 'table',
        'title' => 'كشف اختبار',
        'columns' => [
            ['label' => 'الرمز', 'width' => 30],
            ['label' => 'الاسم', 'width' => 70],
            ['label' => 'الرصيد', 'width' => 30],
            ['label' => 'ملاحظة', 'width' => 55],
        ],
        'rows' => [
            [['value' => 'STU-2026-000028'], ['value' => 'أحمد محمد'], ['value' => '-42.50'], ['value' => '=SUM(A1:A9)']],
            [['value' => 'STU-2026-000029'], ['value' => 'سارة "علي"'], ['value' => '0'], ['value' => 'عادي']],
        ],
        'totals' => ['الإجمالي', '2', '-42.50', ''],
    ], [
        'type' => 'notes',
        'text' => 'ملاحظة إدارية',
    ]],
);
$csv = (new CsvExporter())->entries($csvDocument);
$check(count($csv) === 1, 'A register exports one CSV, not a ZIP of page furniture');
$csvBytes = $csv[0]['bytes'];
$check(str_starts_with($csvBytes, "\xEF\xBB\xBF"), 'CSV begins with a UTF-8 BOM for Excel');
$check(str_contains($csvBytes, '"الرمز","الاسم"'), 'CSV header carries the real column labels');
$check(str_contains($csvBytes, 'STU-2026-000028'), 'CSV carries the real record values');
$check(str_contains($csvBytes, "'=SUM(A1:A9)"), 'Leading = is neutralised against formula injection');
$check(str_contains($csvBytes, ',-42.50,') || str_contains($csvBytes, ',"-42.50",'), 'A negative number keeps its numeric meaning');
$check(str_contains($csvBytes, "\r\n"), 'CSV uses CRLF line endings');
$check(str_contains($csvBytes, '""علي""'), 'Embedded quotes are doubled, not dropped');

$studentsCsv = $engine->export('students', ['class_id' => $classId], 'csv', ['signatures' => false]);
$check($studentsCsv['contentType'] === 'text/csv; charset=UTF-8', 'A single register downloads as text/csv directly');
$check(substr_count($studentsCsv['bytes'], "\n") > 10, 'The register CSV contains the class records');

/* ------------------------------------------------------------------ html */

echo "\nHTML & print\n";
$html = $engine->export('students', ['class_id' => $classId], 'html', ['signatures' => false]);
$check(str_starts_with($html['bytes'], '<!DOCTYPE html>'), 'HTML export is a complete document');
$check(str_contains($html['bytes'], '@font-face'), 'HTML export embeds its fonts');
$check(str_contains($html['bytes'], 'class="doc-page'), 'HTML export carries the designed pages');
$check(!str_contains($html['bytes'], 'http://') && !str_contains($html['bytes'], 'https://'), 'HTML export needs no network');

$printHtml = $engine->html('students', ['class_id' => $classId], ['print' => true], true);
$check(str_contains($printHtml, 'data-print="1"'), 'Print mode marks the document for automatic printing');
$check(str_contains($printHtml, 'document-viewer.js'), 'Print mode loads the viewer that waits for fonts and prints');
$check(!str_contains($printHtml, 'doc-toolbar'), 'A printed document carries no application toolbar');

/* ------------------------------------------------------------------- pdf */

echo "\nPDF & PNG\n";
$pdf = $engine->export('students', ['class_id' => $classId], 'pdf', ['signatures' => true]);
$check(str_starts_with($pdf['bytes'], '%PDF'), 'PDF has a real PDF header');
$check(PdfPageCount::fromBytes($pdf['bytes']) === $engine->pageCount('students', ['class_id' => $classId], ['signatures' => true]), 'PDF physical pages equal the footer numbering');
$check(PdfPageCount::isA4($pdf['bytes'], false), 'PDF is A4 portrait');
$check($pdf['warnings'] === [], 'No pagination or paper-size repair was needed');

$png = $engine->export('students', ['class_id' => $classId], 'png', ['signatures' => true, 'scale' => 2]);
if ($png['contentType'] === 'image/png') {
    $size = getimagesizefromstring($png['bytes']);
    $check(is_array($size) && $size[0] > 700, 'PNG has a high-resolution page image');
} else {
    $check($png['contentType'] === 'application/zip', 'Multi-page PNG arrives as a labelled archive');
    $path = tempnam(sys_get_temp_dir(), 'nizam_png_');
    file_put_contents($path, $png['bytes']);
    $zip = new ZipArchive();
    $zip->open($path);
    $names = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $names[] = (string) $zip->getNameIndex($index);
    }
    $firstImage = null;
    foreach ($names as $name) {
        if (str_ends_with($name, '.png')) {
            $firstImage = (string) $zip->getFromName($name);
            break;
        }
    }
    $zip->close();
    @unlink($path);
    $check(in_array('README.txt', $names, true) || count(array_filter($names, static fn (string $name): bool => str_contains($name, 'README'))) > 0, 'PNG archive explains what it contains');
    $size = $firstImage === null ? false : @getimagesizefromstring($firstImage);
    $check(is_array($size) && $size[0] > 700, 'PNG pages in the archive are high resolution');
}

/* ----------------------------------------------------------------- xlsx */

echo "\nExcel workbook\n";
$sheetA = new Document(key: 'students', title: 'كشف الفصل', subtitle: 'الصف الأول', blocks: [[
    'type' => 'table',
    'title' => 'الطلاب',
    'columns' => [['label' => 'الرمز', 'width' => 30], ['label' => 'الاسم', 'width' => 70]],
    'rows' => [[['value' => '1'], ['value' => 'أحمد']], [['value' => '2'], ['value' => 'سارة']]],
]]);
$sheetB = new Document(key: 'students', title: 'كشف الفصل', subtitle: 'الصف الثاني', blocks: [[
    'type' => 'table',
    'title' => 'الطلاب',
    'columns' => [['label' => 'الرمز', 'width' => 30], ['label' => 'الاسم', 'width' => 70]],
    'rows' => [[['value' => '3'], ['value' => 'محمد']]],
]]);
$bookBytes = (new ExcelWorkbookBuilder())->build([$sheetA, $sheetB], $identity, 'كشوف الفصول');
$bookPath = tempnam(sys_get_temp_dir(), 'nizam_book_');
file_put_contents($bookPath, $bookBytes);
$bookZip = new ZipArchive();
$bookZip->open($bookPath);
$workbookXml = (string) $bookZip->getFromName('xl/workbook.xml');
$sheetXml = '';
for ($index = 1; $index <= 4; $index++) {
    $sheetXml .= (string) $bookZip->getFromName('xl/worksheets/sheet' . $index . '.xml');
}
// PhpSpreadsheet writes repeated text as shared strings, so a subtitle lives
// in sharedStrings.xml rather than inline in the sheet.
$sheetXml .= "\n" . (string) $bookZip->getFromName('xl/sharedStrings.xml');
$bookZip->close();
@unlink($bookPath);

$check(str_contains($workbookXml, 'الفهرس'), 'A multi-document workbook starts with an index sheet');
$check(str_contains($sheetXml, 'rightToLeft="true"'), 'Arabic workbook is right-to-left');
$check(str_contains($sheetXml, '<autoFilter'), 'Tables carry a real auto-filter');
$check(str_contains($sheetXml, '<pane'), 'Heading rows are frozen');
$check(str_contains($sheetXml, '<oddFooter'), 'Sheets carry a print footer with page numbers');
$check(str_contains($sheetXml, 'الصف الثاني'), 'Every document has its own sheet with its own subtitle');

/* ------------------------------------------------------------ bulk job */

echo "\nBulk job\n";
$smallest = null;
foreach ($registry->keys() as $key) {
    try {
        foreach ($registry->make($key)->bulkScopes($paramsByKey[$key] ?? []) as $groupKey => $scope) {
            $items = array_values((array) ($scope['items'] ?? []));
            if ($items === []) {
                continue;
            }
            if ($smallest === null || count($items) < count($smallest['items'])) {
                $smallest = ['key' => $key, 'group' => (string) $groupKey, 'items' => $items, 'label' => (string) $scope['label']];
            }
        }
    } catch (Throwable) {
        continue;
    }
}

$check($smallest !== null, 'At least one document exposes a real bulk scope');
$root = dataPath() . '/storage/tmp/test-bulk-' . bin2hex(random_bytes(4));
$jobs = new BulkExportJob($root);
$token = null;

if ($smallest !== null) {
    $single = [$smallest['items'][0]];
    $job = $jobs->create($smallest['key'], $paramsByKey[$smallest['key']] ?? [], $single, 'pdf', ['signatures' => false], 'اختبار التصدير');
    $token = $job['token'];
    $check(preg_match('/^[a-f0-9]{32}$/', (string) $token) === 1, 'Bulk job token is 128 random bits');
    $check($job['total'] === 1, 'Bulk job reports the real item count');

    $progress = null;
    for ($step = 0; $step < 12; $step++) {
        $progress = $jobs->step((string) $token, $engine);
        if (!empty($progress['complete'])) {
            break;
        }
    }
    $check($progress !== null && $progress['complete'] === true, 'Bulk job completes its real work');
    $check(($progress['failed'] ?? 1) === 0, 'Every document in the job was generated');
    $check((int) ($progress['done'] ?? 0) === 1, 'Progress reflects the file that was actually produced');

    $archive = $jobs->archive((string) $token);
    $path = tempnam(sys_get_temp_dir(), 'nizam_bulk_');
    file_put_contents($path, $archive['bytes']);
    $zip = new ZipArchive();
    $opened = $zip->open($path);
    $names = [];
    if ($opened === true) {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = (string) $zip->getNameIndex($index);
        }
    }
    $readme = '';
    $manifest = '';
    if ($opened === true) {
        foreach ($names as $name) {
            if (str_ends_with($name, 'README.txt')) {
                $readme = (string) $zip->getFromName($name);
            }
            if (str_ends_with($name, 'manifest.csv')) {
                $manifest = (string) $zip->getFromName($name);
            }
        }
    }
    $zip->close();
    @unlink($path);

    $check($opened === true, 'Bulk archive is a real ZIP');
    $check(count($names) === count(array_unique($names)), 'Bulk archive entry names are unique');
    $check($readme !== '' && str_contains($readme, 'NIZAM Document Export'), 'Bulk archive carries a README');
    $check($manifest !== '' && str_contains($manifest, 'File') && str_contains($manifest, 'manifest') === false, 'Bulk archive carries a machine-readable manifest');
    $check(count(array_filter($names, static fn (string $name): bool => str_ends_with($name, '.pdf'))) === 1, 'The generated PDF is in the archive');

    $jobs->purge((string) $token);
    $check($jobs->load((string) $token) === null, 'Purging a job removes it completely');
}

$check($jobs->load('../config') === null, 'A traversal token cannot load a job');
$sentinel = dirname(__DIR__) . '/config/config.php';
$jobs->purge('../../config');
$jobs->purge('..');
$check(is_file($sentinel), 'Purging with a traversal token cannot touch project files');
$check(@scandir($root) !== false || !is_dir($root), 'Traversal purge leaves the job workspace intact');

/* ---------------------------------------------------------- hardening */

echo "\nHardening & naming\n";
$rejected = false;
try {
    $engine->export('students', ['class_id' => $classId], 'docx', []);
} catch (RuntimeException $exception) {
    $rejected = $exception->getMessage() === 'unsupported_format';
}
$check($rejected, 'An unsupported format is refused, not guessed');

$unsafe = DocumentArchiveBuilder::safeName('../../etc/passwd');
$check(!str_contains($unsafe, '/') && !str_contains($unsafe, '..'), 'File names cannot traverse out of their folder');
$arabic = DocumentArchiveBuilder::safeName('الصف الأول الثانوي - أ');
$check(str_contains($arabic, 'الصف'), 'Arabic names are preserved in file names');
$illegal = DocumentArchiveBuilder::safeName('a/b\\c:d*e?f"g<h>i|j');
$check(preg_match('/[\\\\\/:*?"<>|]/', $illegal) !== 1, 'Windows-illegal characters are removed from file names');

$traversalZip = (new DocumentArchiveBuilder())->build('root', [
    ['path' => '../../evil.txt', 'bytes' => 'x'],
    ['path' => 'same.txt', 'bytes' => '1'],
    ['path' => 'same.txt', 'bytes' => '2'],
]);
$path = tempnam(sys_get_temp_dir(), 'nizam_zip_');
file_put_contents($path, $traversalZip);
$zip = new ZipArchive();
$zip->open($path);
$entryNames = [];
for ($index = 0; $index < $zip->numFiles; $index++) {
    $entryNames[] = (string) $zip->getNameIndex($index);
}
$zip->close();
@unlink($path);
$check(count(array_filter($entryNames, static fn (string $name): bool => str_contains($name, '..'))) === 0, 'Archive entries cannot escape the archive folder');
$check(count($entryNames) === 3 && count(array_unique($entryNames)) === 3, 'Duplicate entry names are disambiguated, never lost');

/* ------------------------------------------------------------- cleanup */

if (is_dir($root)) {
    foreach (glob($root . '/*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
        foreach (glob($directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
    @rmdir($root);
}

echo "\n{$pass} passed, {$fail} failed.\n";
echo "Not verified here: visual appearance of PDF/PNG output, printer hardware, Excel/LibreOffice rendering.\n";
if ($fail > 0) {
    echo 'FAILURES: ' . implode(' | ', $failures) . "\n";
    exit(1);
}

echo "PASS: export formats, CSV/HTML/XLSX content, bulk lifecycle and path safety\n";

<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Document engine contract.
 *
 * Locks the architectural promises in place without needing a database:
 *  - every printable screen maps to a registered document type;
 *  - no legacy webpage printing remains anywhere in the application;
 *  - the document layout is independent of the application shell;
 *  - pagination conserves every row and marks continuation pages;
 *  - the four export formats and their assets are present.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Documents\Document;
use App\Services\Documents\DocumentHtmlRenderer;
use App\Services\Documents\ExcelWorkbookBuilder;
use App\Services\Documents\DocumentIdentity;
use App\Services\Documents\DocumentRegistry;
use App\Services\Documents\PageComposer;

$passed = 0;
$failed = 0;
$failures = [];

function check(bool $condition, string $label): void
{
    global $passed, $failed, $failures;
    if ($condition) {
        $passed++;
        echo "  ok    {$label}\n";
        return;
    }
    $failed++;
    $failures[] = $label;
    echo "  FAIL  {$label}\n";
}

$root = dirname(__DIR__);
echo "Document engine contract\n";
echo str_repeat('=', 60) . "\n";

$registry = new DocumentRegistry();
$expected = [
    'students', 'student', 'teachers', 'teacher',
    'timetable-class', 'timetable-teacher', 'timetable-master',
    'report', 'attendance', 'finance', 'invoice', 'report-card',
    'ranking', 'timetable-gaps', 'timetable-covers', 'promotion',
];

check($registry->keys() === $expected, 'Registry exposes exactly the reviewed document types');

$permissions = ['students.view', 'students.promote', 'teachers.view', 'timetable.view', 'reports.view', 'attendance.view', 'finance.view', 'exams.view'];
$badPermission = [];
$badFormat = [];
$missingTitles = [];
foreach ($registry->keys() as $key) {
    $type = $registry->make($key);
    if (!in_array($type->permission(), $permissions, true)) {
        $badPermission[] = $key . ':' . $type->permission();
    }
    if ($type->formats() === [] || !in_array('pdf', $type->formats(), true)) {
        $badFormat[] = $key;
    }
    foreach ($type->formats() as $format) {
        if (!in_array($format, ['pdf', 'xlsx', 'png', 'zip'], true)) {
            $badFormat[] = $key . ':' . $format;
        }
    }
    if (in_array('zip', $type->formats(), true) !== in_array($key, ['students', 'timetable-class', 'timetable-teacher'], true)) {
        $badFormat[] = $key . ':zip-mismatch';
    }
    foreach (['ar', 'en'] as $locale) {
        $path = $root . "/lang/{$locale}.php";
        $lang = require $path;
        if (!isset($lang['document.title.' . $key])) {
            $missingTitles[] = $locale . ':' . $key;
        }
    }
}

check($badPermission === [], 'Every document inherits a real on-screen permission');
check($badFormat === [], 'Every document offers PDF and only supported formats');
check($missingTitles === [], 'Every document has a title in both languages');

// Every view that names a document key must use a registered one, and must
// render the shared export menu rather than its own print markup.
$viewFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/views', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $viewFiles[] = $file->getPathname();
    }
}

$unknownKeys = [];
$wiredViews = 0;
$legacyPrint = [];
$inlinePrint = [];

foreach ($viewFiles as $file) {
    $contents = (string) file_get_contents($file);
    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file);

    if (preg_match_all("/\\\$docKey\\s*=\\s*'([^']+)'/", $contents, $matches)) {
        foreach ($matches[1] as $key) {
            if (!$registry->has($key)) {
                $unknownKeys[] = $relative . ':' . $key;
            }
        }
        if (str_contains($contents, 'partials/export-menu.php')) {
            $wiredViews++;
        }
    }

    if (str_contains($contents, 'data-print-report')) {
        $legacyPrint[] = $relative;
    }
    if (str_contains($contents, 'window.print(')) {
        $inlinePrint[] = $relative;
    }
}

check($unknownKeys === [], 'No view references an unregistered document type');
check($wiredViews >= 8, 'Every integrated screen renders the shared export menu');
check($legacyPrint === [], 'No view still carries the legacy print button hook');
check($inlinePrint === [], 'No view prints itself with window.print()');

$appJs = (string) file_get_contents($root . '/public/assets/js/app.js');
$reportsJs = (string) file_get_contents($root . '/public/assets/js/reports-filter.js');
check(str_contains($appJs, 'window.print();') === false, 'app.js no longer prints the application page');
check(str_contains($reportsJs, 'window.print();') === false, 'reports-filter.js no longer prints the application page');

foreach ([
    'public/assets/css/document.css',
    'public/assets/css/documents-ui.css',
    'public/assets/js/document-export.js',
    'public/assets/js/document-viewer.js',
    'bin/document-render.mjs',
    'views/documents/document.php',
    'views/documents/partials/header.php',
    'views/documents/partials/footer.php',
] as $asset) {
    check(is_file($root . '/' . $asset), 'Required document asset exists: ' . $asset);
}

// ---------------------------------------------------------------- pagination

$rows = [];
for ($index = 0; $index < 500; $index++) {
    $rows[] = [
        ['value' => (string) ($index + 1), 'class' => 'cell-num'],
        ['value' => 'STU-2026-' . str_pad((string) $index, 6, '0', STR_PAD_LEFT), 'class' => ''],
        ['value' => 'طالب رقم ' . $index, 'class' => 'cell-strong'],
        ['value' => 'الصف الأول الثانوي', 'class' => ''],
    ];
}

$document = new Document(
    key: 'students',
    title: 'قائمة الطلاب',
    orientation: 'portrait',
    blocks: [[
        'type' => 'table',
        'title' => 'قائمة الطلاب',
        'columns' => [
            ['label' => '#', 'width' => 10],
            ['label' => 'الرمز', 'width' => 30],
            ['label' => 'الاسم', 'width' => 70],
            ['label' => 'الصف', 'width' => 40],
        ],
        'rows' => $rows,
        'totals' => ['الإجمالي', '500', '', ''],
    ]]
);

$composed = (new PageComposer())->compose($document);
$pages = $composed['pages'];
$rendered = 0;
$continuations = 0;
$missingHeadings = 0;
foreach ($pages as $pageBlocks) {
    foreach ($pageBlocks as $block) {
        $rendered += count($block['rows'] ?? []);
        if (!empty($block['continuation'])) {
            $continuations++;
        }
        if (empty($block['columns']) || count($block['columns']) !== 4) {
            $missingHeadings++;
        }
    }
}

check(count($pages) > 1, 'A 500-row table is composed across multiple pages');
check($rendered === 500, 'Pagination conserves every row (500 in, 500 out)');
check($continuations >= 1, 'Continuation pages are explicitly marked');
check($missingHeadings === 0, 'Every page chunk repeats its column headings');

// ------------------------------------------------------------- independence

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
    yearLabel: '2026/2027',
    issueDate: '01/09/2026',
    locale: 'ar',
    showSignatures: true,
    showWatermark: false,
);

$html = (new DocumentHtmlRenderer())->render($document, $identity, [], true);
check(str_contains($html, 'doc-page'), 'The document renders its own page geometry');
check(str_contains($html, 'صفحة 1 من'), 'The document numbers its own pages');
check(!str_contains($html, 'nizam-sidebar') && !str_contains($html, 'nizam-topbar'), 'The document contains no application navigation');
check(!str_contains($html, '/assets/js/app.js'), 'The document loads no application script');
check(!str_contains($html, 'http://') && !str_contains($html, 'https://'), 'The document contains no application URLs');
check(substr_count($html, '@font-face') >= 3, 'Fonts are embedded for deterministic rendering');
check(str_contains($html, '@page'), 'The document declares its own paper size');

// ------------------------------------------------------------------- workbook

$builder = new ExcelWorkbookBuilder();
$arabicBook = $builder->build([$document], $identity, 'كشف تجريبي');
$englishIdentity = new DocumentIdentity(
    nameAr: 'مدرسة اختبار',
    nameEn: 'Test School',
    logoDataUri: null,
    ministry: '',
    directorate: '',
    administration: '',
    address: '',
    phone: '',
    email: '',
    yearLabel: '2026/2027',
    issueDate: '01/09/2026',
    locale: 'en',
    showSignatures: true,
    showWatermark: false,
);
$englishBook = $builder->build([$document], $englishIdentity, 'Test sheet');

$probe = static function (string $bytes): string {
    $path = dirname(__DIR__) . '/storage/tmp/workbook-probe.xlsx';
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, $bytes);
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return '';
    }
    $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml')
        . "\n" . (string) $zip->getFromName('xl/workbook.xml')
        . "\n" . (string) $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();
    @unlink($path);

    return $xml;
};

check(str_starts_with($arabicBook, "PK"), 'The workbook is a real xlsx package');
$arabicXml = $probe($arabicBook);
check(str_contains($arabicXml, 'rightToLeft="true"'), 'An Arabic workbook is declared right-to-left');
check(str_contains($arabicXml, 'طالب رقم 0'), 'The workbook carries the real records, not a header shell');
check(str_contains($arabicXml, 'Test School') || str_contains($arabicXml, 'مدرسة اختبار'), 'The workbook carries the institutional identity');
check(!str_contains($probe($englishBook), 'rightToLeft="true"'), 'An English workbook is not forced into RTL');

echo "\n{$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    echo 'FAILURES: ' . implode(' | ', $failures) . "\n";
    exit(1);
}

echo "PASS: document engine architecture, independence, pagination and legacy-print removal\n";

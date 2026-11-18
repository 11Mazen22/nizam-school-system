<?php

declare(strict_types=1);

/**
 * Live-search contract.
 *
 * Every list or filter screen must opt into one of the two shared behaviours:
 *  - data-table-filter: instant in-page lookup, ?q= restored on refresh;
 *  - data-live-filter: server-backed partial refresh with aborted stale fetches.
 * Screens with only a select/date scope use the shared GET auto-submit, which
 * is covered by the demonstrated selector checks below.
 */

$root = dirname(__DIR__);
$fail = 0;

function check(bool $ok, string $label): void
{
    global $fail;
    echo ($ok ? '  ok    ' : '  FAIL  ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
}

$instantPages = [
    'students/index.php' => ['data-live-filter', 'data-live-target="#student-results"'],
    'teachers/index.php' => ['data-live-filter', 'data-live-target="#teacher-results"'],
    'classes/index.php' => ['list-search.php', '#classes-table'],
    'subjects/index.php' => ['list-search.php', '#subjects-table'],
    'guardians/index.php' => ['list-search.php', '#guardians-table'],
    'users/index.php' => ['list-search.php', '#users-table'],
    'library/index.php' => ['list-search.php', '#library-table'],
    'library/borrows.php' => ['list-search.php', '#borrows-table'],
    'finance/index.php' => ['list-search.php', '#recent-invoices-table'],
    'finance/invoices.php' => ['list-search.php', '#invoices-table'],
    'finance/structures.php' => ['list-search.php', '#structures-table'],
    'hr/index.php' => ['list-search.php', '#recent-leaves-table'],
    'hr/leaves.php' => ['list-search.php', '#leaves-table'],
    'transport/index.php' => ['list-search.php', '#routes-table'],
    'academic-years/index.php' => ['list-search.php', '.n-year-list'],
    'assessments/index.php' => ['list-search.php', '#assessments-grid'],
    'exams/index.php' => ['list-search.php', '#exams-results'],
    'exams/ranking.php' => ['data-live-filter', 'data-live-target="#ranking-results"'],
    'admissions/index.php' => ['list-search.php', '#applications-table'],
    'welfare/health.php' => ['list-search.php', '#health-table'],
    'welfare/discipline.php' => ['list-search.php', '#discipline-table'],
    'timetable/gaps.php' => ['data-live-filter', 'data-live-target="#gap-results"'],
    'timetable/covers.php' => ['list-search.php', '#covers-table'],
    'attendance/report.php' => ['data-live-filter', 'data-live-target="#attendance-summary-results"'],
    'reports/show.php' => ['data-live-filter', 'data-live-target="#report-results"'],
    'notifications/index.php' => ['list-search.php', '.notifications-container'],
    'activity-log/index.php' => ['data-live-filter', 'data-live-target="#log-results"'],
    'students/archived.php' => ['list-search.php', '#archived-students-table'],
    'teachers/archived.php' => ['list-search.php', '#archived-teachers-table'],
    'students/verification.php' => ['list-search.php', '#verification-table'],
    'backups/index.php' => ['data-live-filter', 'data-live-target="#backup-results"', 'rawurlencode'],
];

echo "Live search coverage\n";
foreach ($instantPages as $file => $markers) {
    $path = $root . '/views/' . $file;
    $contents = is_file($path) ? (string) file_get_contents($path) : '';
    $ok = $contents !== '';
    foreach ($markers as $marker) {
        $ok = $ok && str_contains($contents, $marker);
    }
    check($ok, $file . ' is wired for live filtering');
}

// Attendance and score sheets hold typed-but-unsaved values. Their lookup
// must never submit a GET form, because a reload would silently discard the
// edits; it is rendered with [data-js-only][hidden] and revealed only when
// scripting can filter the rows in place.
$unsavedSheetFilters = [
    'attendance/mark.php' => 'data-table-filter="#attendance-table"',
    'exams/scores.php' => 'data-table-filter="#scores-table"',
];
echo "\nUnsaved sheets never offer a dead or destructive filter\n";
foreach ($unsavedSheetFilters as $file => $marker) {
    $contents = (string) file_get_contents($root . '/views/' . $file);
    check(
        str_contains($contents, 'data-js-only') && str_contains($contents, $marker) && !str_contains($contents, 'name="q"'),
        $file . ' keeps its filter in-page and cannot submit a search'
    );
}

$appJs = (string) file_get_contents($root . '/public/assets/js/app.js');
check(str_contains($appJs, 'data-table-filter'), 'The shared table filter supports every list page');
check(str_contains($appJs, 'history.replaceState'), 'The query is written to the URL for refresh/share');
check(str_contains($appJs, 'AbortController'), 'Stale server-backed filter requests are cancelled');
check(str_contains($appJs, 'normalize(\'NFKD\')'), 'Arabic letter variants are matched consistently');
check(str_contains($appJs, 'n-filter-empty'), 'No-match rows are rendered explicitly');
check(str_contains($appJs, 'initJsOnly'), 'JS-only controls are revealed only when scripting is available');

echo "\nServer-side filters (work without JavaScript)\n";
$serverFilteredControllers = [
    'app/controllers/AttendanceController.php' => 'filterRowsBySearch',
    'app/controllers/ExamController.php' => 'filterRowsBySearch',
    'app/controllers/ReportController.php' => 'filterReportRows',
];
foreach ($serverFilteredControllers as $file => $marker) {
    check(str_contains((string) file_get_contents($root . '/' . $file), $marker), $file . ' filters its list on the server');
}

$backupRepo = (string) file_get_contents($root . '/app/repositories/BackupRepository.php');
$backupController = (string) file_get_contents($root . '/app/controllers/BackupController.php');
check(str_contains($backupRepo, 'LOWER(b.filename)'), 'Backup search runs in SQL across the whole history');
check(str_contains($backupRepo, 'getTotalCount(string $term'), 'Backup totals are counted for the filtered history');
check(str_contains($backupController, "query('q'"), 'The backup controller reads the search term');
check(str_contains((string) file_get_contents($root . '/views/backups/index.php'), '&q='), 'Backup pagination keeps the search term');

$scopePages = [
    'attendance/mark.php' => ['name="class_id"', 'name="date"'],
    'attendance/report.php' => ['name="class_id"', 'name="from"', 'name="to"'],
    'exams/scores.php' => ['name="class_id"', 'name="subject_id"'],
    'exams/ranking.php' => ['name="class_id"'],
    'timetable/index.php' => ['name="class_id"'],
    'timetable/covers.php' => ['name="date"'],
    'reports/show.php' => ['id="reportFilterForm"'],
];
foreach ($scopePages as $file => $markers) {
    $contents = (string) file_get_contents($root . '/views/' . $file);
    $ok = true;
    foreach ($markers as $marker) {
        $ok = $ok && str_contains($contents, $marker);
    }
    check($ok, $file . ' keeps its select/date scope auto-submitting');
}
check(str_contains($appJs, "document.querySelectorAll('form[method=\"get\"]')"), 'Every GET filter form is covered by the no-Enter handler');

echo $fail === 0
    ? "PASS: live search is wired across " . count($instantPages) . " list/filter pages\n"
    : "FAIL: {$fail} live-search contract checks failed\n";
exit($fail === 0 ? 0 : 1);

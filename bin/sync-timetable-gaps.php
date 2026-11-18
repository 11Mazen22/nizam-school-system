<?php

declare(strict_types=1);

/**
 * Nizam -- rebuild the timetable gap list from the official dataset.
 *
 * The 17 grids print 558 lessons; 507 loaded. The rest are printed lessons
 * that could not become rows: no teacher printed, a first name shared by two
 * teachers, or an absence-check cell. This command records them in
 * timetable_gaps, with the printed subject and teacher text intact, so the
 * school can see and fill them from /timetable/gaps.
 *
 * Idempotent: resolved gaps stay resolved, ignored gaps stay ignored, and a
 * gap whose lesson has since been loaded is closed.
 *
 * Usage: php bin/sync-timetable-gaps.php [--dry-run]
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Services\TimetableGapService;

$dryRun = in_array('--dry-run', $argv, true);
$pdo = Database::connection();

$year = $pdo->query('SELECT id, label FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();
if ($year === false) {
    fwrite(STDERR, "No active academic year.\n");
    exit(1);
}

$service = new TimetableGapService();
$result = $service->sync(
    (int) $year['id'],
    dirname(__DIR__) . '/database/official/timetables_17.json',
    $dryRun
);

fwrite(STDOUT, 'academic year: ' . $year['label'] . "\n");
fwrite(STDOUT, 'printed lessons already loaded: ' . $result['loaded'] . "\n");
fwrite(STDOUT, 'gaps: ' . $result['gaps'] . "\n");
foreach (['no_teacher', 'teacher_conflict', 'unmapped_teacher', 'absence', 'other'] as $reason) {
    if (($result['by_reason'][$reason] ?? 0) > 0) {
        fwrite(STDOUT, '  ' . $reason . ': ' . $result['by_reason'][$reason] . "\n");
    }
}
fwrite(STDOUT, $dryRun ? "DRY RUN -- nothing written\n" : "Gap list synchronised.\n");

<?php

declare(strict_types=1);

/**
 * Nizam -- rebuild the timetable gap list from the official dataset.
 *
 * Loads every printed lesson the class timetable is still missing. A cell
 * printed without a teacher (an activity period) loads without one, and a
 * cell the paper itself double-books loads with its printed teacher; the
 * timetable screen reports those clashes. Only a genuinely unrepresentable
 * cell -- an unknown subject, or a teacher name with no staff record -- stays
 * in timetable_gaps for the school to map.
 *
 * Idempotent: loaded cells are left alone, resolved gaps stay resolved,
 * ignored gaps stay ignored, and a gap whose lesson now exists is closed.
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
fwrite(STDOUT, 'printed lessons loaded now: ' . ($result['imported'] ?? 0) . "\n");
fwrite(STDOUT, 'remaining gaps: ' . $result['gaps'] . "\n");
foreach (['no_teacher', 'teacher_conflict', 'unmapped_teacher', 'absence', 'other'] as $reason) {
    if (($result['by_reason'][$reason] ?? 0) > 0) {
        fwrite(STDOUT, '  ' . $reason . ': ' . $result['by_reason'][$reason] . "\n");
    }
}
fwrite(STDOUT, $dryRun ? "DRY RUN -- nothing written\n" : "Gap list synchronised.\n");

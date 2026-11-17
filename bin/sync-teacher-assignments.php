<?php

declare(strict_types=1);

/**
 * Nizam -- rebuild staff teaching load from the loaded timetable.
 *
 * The package prints timetables, not an assignment register. This command
 * turns every distinct class/subject/teacher triple in the grid into one
 * teacher assignment whose weekly_periods is the number of printed cells,
 * and links the teacher to that subject. Without it /assignments, workload,
 * and the teacher workspace stay empty even though the timetable is full.
 *
 * Safe to re-run: existing assignments are kept, only their weekly periods
 * are corrected, and archived assignments are not reactivated.
 *
 * Usage: php bin/sync-teacher-assignments.php [--dry-run]
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Services\TeacherAssignmentSyncService;

$dryRun = in_array('--dry-run', $argv, true);
$pdo = Database::connection();

$year = $pdo->query('SELECT id, label FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();
if ($year === false) {
    fwrite(STDERR, "No active academic year.\n");
    exit(1);
}

$result = (new TeacherAssignmentSyncService())->sync((int) $year['id'], $dryRun);

echo ($dryRun ? 'DRY RUN -- nothing written' : 'Committed') . ' for ' . $year['label'] . "\n";
echo "assignments created: {$result['created']}\n";
echo "assignments corrected: {$result['updated']}\n";
echo "hand-edited loads kept: {$result['manual_kept']}\n";
echo "assignments unchanged: {$result['unchanged']}\n";
echo "teacher-subject links added: {$result['subjects_linked']}\n";
echo "classes covered: {$result['classes']}\n";

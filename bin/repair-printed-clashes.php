<?php

declare(strict_types=1);

/**
 * Nizam -- reconcile printed timetable clashes after the unique teacher/slot
 * guarantee was restored (migration 042).
 *
 * A cell the paper double-books has no teacher_id and keeps the printed name
 * in printed_teacher. Exactly one class in each clash group should still hold
 * the real teacher; the other keeps the printed name and shows the clash. If a
 * migration pass cleared both sides (the same teacher reused in more than one
 * class), this puts the teacher back on the first class and leaves the rest
 * flagged, so the teacher's own timetable is not short of lessons.
 *
 * Idempotent, and it never creates a double-booking: a candidate slot is only
 * filled when the unique index would accept it.
 *
 * Usage: php bin/repair-printed-clashes.php [--dry-run]
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Repositories\TeacherAliasRepository;

$dryRun = in_array('--dry-run', $argv, true);
$pdo = Database::connection();

$year = $pdo->query('SELECT id, label FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();
if ($year === false) {
    fwrite(STDERR, "No active academic year.\n");
    exit(1);
}
$yearId = (int) $year['id'];

$teacherIds = [];
foreach ($pdo->query("SELECT id, full_name FROM teachers WHERE status = 'active'") as $row) {
    $teacherIds[(string) $row['full_name']] = (int) $row['id'];
}
$aliases = (new TeacherAliasRepository())->all();

$rows = $pdo->prepare(
    'SELECT id, class_id, day_of_week, period_number, printed_teacher
     FROM timetables
     WHERE academic_year_id = :year AND teacher_id IS NULL AND printed_teacher IS NOT NULL
     ORDER BY printed_teacher, day_of_week, period_number, class_id, id'
);
$rows->execute(['year' => $yearId]);

$groups = [];
foreach ($rows->fetchAll() as $row) {
    $key = $row['printed_teacher'] . '|' . $row['day_of_week'] . '|' . $row['period_number'];
    $groups[$key][] = $row;
}

$slotTaken = $pdo->prepare(
    'SELECT COUNT(*) FROM timetables
     WHERE academic_year_id = :year AND teacher_id = :teacher AND day_of_week = :day AND period_number = :period'
);
$restore = $pdo->prepare(
    'UPDATE timetables SET teacher_id = :teacher, printed_teacher = NULL WHERE id = :id'
);

$restored = 0;
$unresolved = [];

foreach ($groups as $key => $members) {
    $printed = (string) $members[0]['printed_teacher'];
    $teacherId = $teacherIds[$printed] ?? (int) ($aliases[$printed] ?? 0);
    if ($teacherId <= 0) {
        $unresolved[] = $printed . ' (no staff record)';
        continue;
    }
    $target = $members[0];
    $slotTaken->execute([
        'year' => $yearId,
        'teacher' => $teacherId,
        'day' => (int) $target['day_of_week'],
        'period' => (int) $target['period_number'],
    ]);
    if ((int) $slotTaken->fetchColumn() > 0) {
        // Another class already owns this teacher in this period -- a genuine
        // remaining double-booking; leave every cell flagged.
        $unresolved[] = $printed . ' (slot already held)';
        continue;
    }

    echo sprintf(
        "%s %s p%d: restore %s to class #%d, keep %d clash flagged\n",
        $dryRun ? 'would' : 'restored',
        $key,
        (int) $target['period_number'],
        $printed,
        (int) $target['class_id'],
        count($members) - 1
    );
    if (!$dryRun) {
        $restore->execute(['teacher' => $teacherId, 'id' => (int) $target['id']]);
    }
    $restored++;
}

echo "\nacademic year: {$year['label']}\n";
echo 'clash groups: ' . count($groups) . "\n";
echo ($dryRun ? 'would restore: ' : 'restored: ') . $restored . "\n";
echo 'unresolved: ' . count($unresolved) . "\n";
foreach ($unresolved as $reason) {
    echo '  - ' . $reason . "\n";
}
echo $dryRun ? "DRY RUN -- nothing written\n" : "Done.\n";

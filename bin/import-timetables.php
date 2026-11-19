<?php

declare(strict_types=1);

/**
 * Nizam -- load the official 2026/2027 class timetables.
 *
 * Source: database/official/timetables_17.json, produced by
 * tools/extract_timetables.py from the 17 PDFs (exact word coordinates).
 *
 * Rules, all deliberate:
 *  - Subjects are matched to the existing subject rows or their stable code;
 *    the printed subjects the catalogue does not have yet (financial culture,
 *    sports, activity, physics, psychology / second language, chemistry /
 *    business, German, Spanish) are created once.
 *  - Teacher records are created from the names printed in the grid, deduped
 *    by exact name. The timetable prints first names only.
 *  - "G+F" / "G+A" cells are the school's split-group language lesson: two
 *    teachers share one period. timetables allows a single teacher per slot,
 *    so the slot is stored with the first printed teacher, subject
 *    "اللغة الثانية", and the printed pair is preserved in timetables.note.
 *  - The single "الغياب" cell is an absence-check period, not a lesson; it is
 *    skipped and reported rather than forced into a subject.
 *  - Cells whose "subject" is actually a teacher name (printing error in the
 *    source) are skipped and reported.
 *
 * Idempotency and provenance:
 *  - No flags: if the year already has slots, nothing is written (deployment
 *    seed behaviour).
 *  - --sync: re-run the import safely. Existing imported cells are compared
 *    and updated only when the printed grid actually differs; every cell a
 *    human edited (manual_override) and every cell a human deleted
 *    (timetable_import_exclusions) is preserved and reported.
 *  - --dry-run: prints the exact comparison (insert / update / preserve /
 *    skip) without writing anything.
 *  - --replace: removes only imported cells (manual corrections survive),
 *    clears exclusions, then loads the printed grid again.
 *
 * Usage: php bin/import-timetables.php [--dry-run] [--sync] [--replace]
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Repositories\TimetableRepository;
use App\Services\TeacherAssignmentSyncService;

$dryRun = in_array('--dry-run', $argv, true);
$replace = in_array('--replace', $argv, true);
$sync = in_array('--sync', $argv, true);
$pdo = Database::connection();
$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

$year = $pdo->query('SELECT id, label FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();
if ($year === false) {
    fwrite(STDERR, "No active academic year.\n");
    exit(1);
}
$yearId = (int) $year['id'];

$existingSlots = (int) $pdo->query('SELECT COUNT(*) FROM timetables WHERE academic_year_id = ' . $yearId)->fetchColumn();
if ($existingSlots > 0 && !$replace && !$sync && !$dryRun) {
    echo "Timetables already loaded for {$year['label']} ({$existingSlots} slots); nothing to do.\n";
    echo "Pass --sync to reconcile against the printed grid, or --dry-run to preview it.\n";
    exit(0);
}

$data = json_decode((string) file_get_contents(dirname(__DIR__) . '/database/official/timetables_17.json'), true);
if (!is_array($data) || !isset($data['classes'])) {
    fwrite(STDERR, "timetables_17.json is missing or unreadable.\n");
    exit(1);
}

$subjectIds = [];
$subjectCodes = [];
foreach ($pdo->query('SELECT id, code, name_ar FROM subjects') as $row) {
    $subjectIds[(string) $row['name_ar']] = (int) $row['id'];
    $subjectCodes[strtoupper((string) $row['code'])] = (int) $row['id'];
}

/** Printed label => [existing Arabic name | new subject definition]. */
$subjectMap = [
    'عربي' => ['existing' => 'اللغة العربية', 'code' => 'AR'],
    'إنجليزي' => ['existing' => 'اللغة الإنجليزية', 'code' => 'EN'],
    'رياضيات' => ['existing' => 'الرياضيات', 'code' => 'MATH'],
    'علوم' => ['existing' => 'العلوم', 'code' => 'SCI'],
    'تاريخ' => ['existing' => 'التاريخ', 'code' => 'HI'],
    'برمجة' => ['existing' => 'الحاسوب', 'code' => 'CS'],
    'دين' => ['existing' => 'التربية الدينية', 'code' => 'REL'],
    'فلسفة' => ['existing' => 'الفلسفة', 'code' => 'PHIL'],
    'ألماني/فرنساوي' => ['existing' => 'اللغة الثانية', 'code' => 'LANG2'],
    'G+F' => ['existing' => 'اللغة الثانية', 'code' => 'LANG2'],
    'G+A' => ['existing' => 'اللغة الثانية', 'code' => 'LANG2'],
    'ثقافة مالية' => ['new' => ['FIN_LIT', 'الثقافة المالية', 'Financial Literacy']],
    'ألعاب' => ['new' => ['SPORTS', 'الألعاب الرياضية', 'Sports']],
    'نشاط' => ['new' => ['ACTIVITY', 'النشاط المدرسي', 'School Activity']],
    'فيزياء' => ['new' => ['PHYSICS', 'الفيزياء', 'Physics']],
    'كيمياء/إدارة أعمال' => ['new' => ['CHEM_BUS', 'الكيمياء / إدارة أعمال', 'Chemistry / Business Administration']],
    'علم نفس/لغة ثانية' => ['new' => ['PSY_LANG2', 'علم النفس / لغة ثانية', 'Psychology / Second Language']],
    'ألماني' => ['new' => ['GERMAN', 'اللغة الألمانية', 'German']],
    'إسباني' => ['new' => ['SPANISH', 'اللغة الإسبانية', 'Spanish']],
];
$skipSubjects = ['الغياب'];

$dayMap = ['الأحد' => 2, 'الاثنين' => 3, 'الثلاثاء' => 4, 'الأربعاء' => 5, 'الخميس' => 6];

$createdSubjects = 0;
$createdTeachers = 0;
$inserted = 0;
$updated = 0;
$preserved = 0;
$excluded = 0;
$unchanged = 0;
$skipped = [];
$teacherIds = [];
$existingTeachers = [];
foreach ($pdo->query('SELECT id, full_name FROM teachers') as $row) {
    $existingTeachers[(string) $row['full_name']] = (int) $row['id'];
}

function subjectId(string $label): ?int
{
    global $subjectMap, $subjectIds, $subjectCodes, $pdo, $dryRun, $createdSubjects;

    if (!isset($subjectMap[$label])) {
        return null;
    }
    $map = $subjectMap[$label];
    if (isset($map['existing'])) {
        if (isset($subjectIds[$map['existing']])) {
            return $subjectIds[$map['existing']];
        }
        // The catalogue may name the same subject differently (e.g. a fresh
        // install starts with الحاسوب for CS). The code is the stable key.
        $code = strtoupper((string) ($map['code'] ?? ''));
        if ($code !== '' && isset($subjectCodes[$code])) {
            return $subjectCodes[$code];
        }
    }
    [$code, $nameAr, $nameEn] = $map['new'] ?? [$map['code'] ?? '', $map['existing'] ?? '', $map['existing'] ?? ''];
    if ($code === '') {
        return null;
    }
    $code = strtoupper($code);
    if (isset($subjectCodes[$code])) {
        return $subjectCodes[$code];
    }
    if ($dryRun) {
        $createdSubjects++;
        return -1;
    }
    $find = $pdo->prepare('SELECT id FROM subjects WHERE code = :code');
    $find->execute(['code' => $code]);
    $id = $find->fetchColumn();
    if ($id !== false) {
        $subjectCodes[$code] = (int) $id;
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO subjects (code, name_en, name_ar, is_active) VALUES (:c, :en, :ar, 1)')
        ->execute(['c' => $code, 'en' => $nameEn, 'ar' => $nameAr]);
    $numeric = (int) $pdo->lastInsertId();
    if ($numeric === 0) {
        $find->execute(['code' => $code]);
        $numeric = (int) ($find->fetchColumn() ?: 0);
    }
    $subjectIds[$nameAr] = $numeric;
    $subjectCodes[$code] = $numeric;
    $createdSubjects++;
    return $numeric;
}

function teacherId(string $name): int
{
    global $existingTeachers, $teacherIds, $pdo, $dryRun, $createdTeachers;

    $name = trim($name);
    if (isset($teacherIds[$name])) {
        return $teacherIds[$name];
    }
    if (isset($existingTeachers[$name])) {
        return $teacherIds[$name] = $existingTeachers[$name];
    }
    if ($dryRun) {
        $createdTeachers++;
        return -$createdTeachers;
    }
    $pdo->prepare('INSERT INTO teachers (teacher_code, full_name, status) VALUES (:code, :name, \'active\')')
        ->execute(['code' => 'TMP-' . bin2hex(random_bytes(6)), 'name' => $name]);
    $id = (int) $pdo->lastInsertId();
    if ($id === 0) {
        $find = $pdo->prepare('SELECT id FROM teachers WHERE full_name = :name ORDER BY id DESC LIMIT 1');
        $find->execute(['name' => $name]);
        $id = (int) ($find->fetchColumn() ?: 0);
    }
    if ($id === 0) {
        throw new RuntimeException("Could not resolve the teacher row for '{$name}'.");
    }
    $pdo->prepare('UPDATE teachers SET teacher_code = :code WHERE id = :id')
        ->execute(['code' => 'TCH-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), 'id' => $id]);
    $createdTeachers++;
    $existingTeachers[$name] = $id;
    return $teacherIds[$name] = $id;
}

$classRows = [];
$classStmt = $pdo->prepare('SELECT id, name FROM classes WHERE academic_year_id = :year');
$classStmt->execute(['year' => $yearId]);
foreach ($classStmt->fetchAll() as $row) {
    $classRows[(string) $row['name']] = (int) $row['id'];
}

// Existing cells and their provenance. The importer never deletes a manual
// correction, and a human deletion is remembered as an exclusion.
$existing = [];
$existingStmt = $pdo->prepare(
    'SELECT t.*, c.name AS class_name FROM timetables t
     JOIN classes c ON c.id = t.class_id
     WHERE t.academic_year_id = :year'
);
$existingStmt->execute(['year' => $yearId]);
foreach ($existingStmt->fetchAll() as $row) {
    $key = (int) $row['class_id'] . '|' . (int) $row['day_of_week'] . '|' . (int) $row['period_number'];
    $existing[$key] = $row;
}
$exclusions = (new TimetableRepository())->importExclusions($yearId);

// Slots already taken in this run, keyed by teacher and by class. The printed
// grid lists teachers by first name only, so a handful of cells book the same
// name in two classes during one period. A teacher cannot be in two rooms at
// once (uq_timetable_teacher_slot), so the later cell is reported and skipped
// -- which is exactly what the MySQL build has always done. Checking before
// the INSERT keeps that behaviour identical on PostgreSQL, where one failed
// statement aborts the whole transaction instead of letting the importer
// carry on.
$teacherSlotUse = [];
$classSlotUse = [];
$paperClashes = [];
$preservedCells = [];
$excludedCells = [];
$updatedCells = [];

if (!$dryRun) {
    $pdo->beginTransaction();
}

try {
    if ($replace && !$dryRun) {
        $repository = new TimetableRepository();
        $removed = $repository->deleteImportedForYear($yearId);
        $repository->clearImportExclusions($yearId);
        $existing = [];
        $exclusions = [];
        echo "Removed {$removed} imported slots for {$year['label']}; manual corrections were kept.\n";
    }

    // Seed availability with the cells that are already real. An imported
    // cell is removed from the map when the loop reaches it, so it may be
    // compared; a manual cell keeps its booking for the whole run.
    foreach ($existing as $existingRow) {
        $slotKey = (int) $existingRow['class_id'] . '|' . (int) $existingRow['day_of_week'] . '|' . (int) $existingRow['period_number'];
        $slotLabel = (string) $existingRow['class_name'] . ' #' . (int) $existingRow['id'];
        $classSlotUse[$slotKey] = ['id' => (int) $existingRow['id'], 'label' => $slotLabel];
        if ($existingRow['teacher_id'] !== null) {
            $teacherKey = (int) $existingRow['teacher_id'] . '|' . (int) $existingRow['day_of_week'] . '|' . (int) $existingRow['period_number'];
            $teacherSlotUse[$teacherKey] = ['id' => (int) $existingRow['id'], 'label' => $slotLabel];
        }
    }

    foreach ($data['classes'] as $class) {
        $classId = $classRows[(string) $class['class_id']] ?? 0;
        if ($classId === 0) {
            $skipped[] = $class['class_id'] . ': class row not found';
            continue;
        }
        $perClass = 0;
        foreach ($class['rows'] as $cell) {
            $label = (string) $cell['subject'];
            if ($label === '') {
                continue;
            }
            if (in_array($label, $skipSubjects, true)) {
                $skipped[] = "{$class['class_id']} {$cell['day']} p{$cell['period']}: الغياب (not a lesson)";
                continue;
            }
            $day = $dayMap[$cell['day']] ?? 0;
            if ($day === 0) {
                $skipped[] = "{$class['class_id']} {$cell['day']} p{$cell['period']}: unknown day";
                continue;
            }
            $subjectId = subjectId($label);
            if ($subjectId === null) {
                $skipped[] = "{$class['class_id']} {$cell['day']} p{$cell['period']}: unrecognised subject '{$label}'";
                continue;
            }
            // Activity periods (sports, financial literacy, chemistry /
            // business) are printed with no teacher. They are still real
            // scheduled lessons, so they load with no teacher rather than
            // leaving a hole in the class timetable.
            $teacherName = trim((string) $cell['teacher']);
            $names = array_values(array_filter(array_map('trim', explode('/', $teacherName))));
            $note = count($names) > 1 ? $label . ' group pair: ' . implode(' / ', $names) : null;
            if (in_array($label, ['G+F', 'G+A', 'ألماني/فرنساوي'], true) && $note === null) {
                $note = $label . ' split-group language lesson';
            }

            $period = (int) $cell['period'];
            $classKey = $classId . '|' . $day . '|' . $period;
            $existingSlot = $existing[$classKey] ?? null;

            // A cell a human edited is the school's decision. Re-importing the
            // printed grid must never silently replace it.
            if ($existingSlot !== null && dbBool($existingSlot['manual_override'] ?? 0)) {
                $preserved++;
                $preservedCells[] = "{$class['class_id']} {$cell['day']} p{$period}: manual correction kept";
                continue;
            }

            // A cell a human deleted stays deleted until someone clears the
            // exclusion explicitly (--replace does that).
            if ($existingSlot === null && isset($exclusions[$classKey])) {
                $excluded++;
                $excludedCells[] = "{$class['class_id']} {$cell['day']} p{$period}: manually removed ({$exclusions[$classKey]})";
                continue;
            }

            $classSlotUse[$classKey] = [
                'id' => $existingSlot !== null ? (int) $existingSlot['id'] : null,
                'label' => "{$class['class_id']} {$label}",
            ];

            // The row being compared must not count itself as busy when we
            // choose the teacher for that same row. A row that was already a
            // printed clash stays a printed clash: re-importing must not
            // silently "fix" the paper's contradiction or erase its evidence.
            $preservePrintedClash = $existingSlot !== null
                && $existingSlot['teacher_id'] === null
                && $teacherName !== ''
                && trim((string) ($existingSlot['printed_teacher'] ?? '')) === $teacherName;

            if ($preservePrintedClash) {
                $teacherId = null;
                $printedTeacher = $teacherName;
            } else {
                if ($existingSlot !== null && $existingSlot['teacher_id'] !== null) {
                    $oldTeacherKey = (int) $existingSlot['teacher_id'] . '|' . $day . '|' . $period;
                    unset($teacherSlotUse[$oldTeacherKey]);
                }

                // A shared cell may print two names. Use the first printed teacher
                // who is free in this period, exactly as the gap sync does, so a
                // pair covering two classes is not reported as a clash.
                $teacherId = null;
                foreach ($names as $name) {
                    $candidate = teacherId($name);
                    if ($candidate !== null && !isset($teacherSlotUse[$candidate . '|' . $day . '|' . $period])) {
                        $teacherId = $candidate;
                        break;
                    }
                }
                $printedTeacher = null;
                if ($teacherId === null) {
                    foreach ($names as $name) {
                        $candidate = teacherId($name);
                        if ($candidate !== null) {
                            // The paper places this teacher in two classes at once.
                            // The database must never record a real double-booking,
                            // so the lesson keeps the printed name in
                            // printed_teacher and no teacher id: the class
                            // timetable stays complete and the clash stays visible.
                            $heldBy = $teacherSlotUse[$candidate . '|' . $day . '|' . $period]['label'] ?? 'another class';
                            $paperClashes[] = "{$class['class_id']} {$cell['day']} p{$period}: {$name} already printed in {$heldBy}";
                            $printedTeacher = $teacherName;
                            break;
                        }
                    }
                }
            }
            $teacherKey = $teacherId . '|' . $day . '|' . $period;
            if ($teacherId !== null) {
                $teacherSlotUse[$teacherKey] = [
                    'id' => $existingSlot !== null ? (int) $existingSlot['id'] : null,
                    'label' => "{$class['class_id']} {$label}",
                ];
            }

            // The grouping note is explanatory, not source data. Keep the one
            // already stored when the subject has not changed, so a re-import
            // does not churn identical lessons over wording alone.
            if ($existingSlot !== null && (int) $existingSlot['subject_id'] === $subjectId
                && trim((string) ($existingSlot['note'] ?? '')) !== '') {
                $note = (string) $existingSlot['note'];
            }

            $same = $existingSlot !== null
                && (int) $existingSlot['subject_id'] === $subjectId
                && (int) ($existingSlot['teacher_id'] ?? 0) === (int) ($teacherId ?? 0)
                && trim((string) ($existingSlot['note'] ?? '')) === trim((string) ($note ?? ''))
                && trim((string) ($existingSlot['printed_teacher'] ?? '')) === trim((string) ($printedTeacher ?? ''));

            try {
                if ($dryRun) {
                    if ($existingSlot === null) {
                        $inserted++;
                    } elseif ($same) {
                        $unchanged++;
                    } else {
                        $updated++;
                        $updatedCells[] = "{$class['class_id']} {$cell['day']} p{$period}: would update";
                    }
                    $perClass++;
                    continue;
                }

                if ($same) {
                    $unchanged++;
                    $perClass++;
                    foreach (array_slice($names, 1) as $extra) {
                        teacherId($extra);
                    }
                    continue;
                }

                // A savepoint keeps one rejected row from aborting the whole
                // PostgreSQL transaction (MySQL simply continues after a
                // failed statement, so it needs none).
                if ($driver === 'pgsql') {
                    $pdo->exec('SAVEPOINT timetable_slot');
                }
                if ($existingSlot !== null) {
                    (new TimetableRepository())->updateImported(
                        (int) $existingSlot['id'],
                        $subjectId,
                        $teacherId,
                        $note,
                        $printedTeacher,
                        'timetables_17.json'
                    );
                    $updated++;
                    $updatedCells[] = "{$class['class_id']} {$cell['day']} p{$period}: updated from printed grid";
                } else {
                    $pdo->prepare(
                        'INSERT INTO timetables
                            (academic_year_id, class_id, day_of_week, period_number, subject_id, teacher_id,
                             note, printed_teacher, origin, import_source, imported_at)
                         VALUES (:year, :class, :day, :period, :subject, :teacher, :note, :printed,
                                 \'imported\', \'timetables_17.json\', CURRENT_TIMESTAMP)'
                    )->execute([
                        'year' => $yearId,
                        'class' => $classId,
                        'day' => $day,
                        'period' => $period,
                        'subject' => $subjectId,
                        'teacher' => $teacherId,
                        'note' => $note,
                        'printed' => $printedTeacher,
                    ]);
                    $inserted++;
                }
                if ($driver === 'pgsql') {
                    $pdo->exec('RELEASE SAVEPOINT timetable_slot');
                }
                foreach (array_slice($names, 1) as $extra) {
                    teacherId($extra); // keep the second group teacher on record
                }
                $perClass++;
            } catch (PDOException $e) {
                if ($driver === 'pgsql') {
                    try {
                        $pdo->exec('ROLLBACK TO SAVEPOINT timetable_slot');
                        $pdo->exec('RELEASE SAVEPOINT timetable_slot');
                    } catch (Throwable) {
                        // The outer handler reports a genuinely dead transaction.
                    }
                }
                unset($teacherSlotUse[$teacherKey], $classSlotUse[$classKey]);
                $skipped[] = "{$class['class_id']} {$cell['day']} p{$period}: database rejected the slot ({$label}, {$teacherName}): " . $e->getMessage();
            }
        }
        echo $class['class_id'] . ': ' . $perClass . " slots\n";
    }

    if (!$dryRun) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if (!$dryRun && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . "\n");
    exit(1);
}

if (!$dryRun) {
    // A printed lesson is evidence of the teaching assignment behind it;
    // without this pass the timetable is full but /assignments and the
    // teacher workspace have nothing to show.
    $load = (new TeacherAssignmentSyncService())->sync($yearId);
}

echo "\n" . ($dryRun ? 'DRY RUN -- nothing written (comparison only)' : 'Committed') . "\n";
echo "slots inserted: {$inserted}\n";
echo "slots updated from the printed grid: {$updated}\n";
echo "slots already correct: {$unchanged}\n";
echo "manual corrections preserved: {$preserved}\n";
echo "manual deletions preserved: {$excluded}\n";
if (!$dryRun) {
    echo "assignments created: {$load['created']}, corrected: {$load['updated']}\n";
}
echo "subjects created: {$createdSubjects}\n";
echo "teachers created: {$createdTeachers}\n";
echo 'skipped: ' . count($skipped) . "\n";
foreach ($skipped as $reason) {
    echo '  - ' . $reason . "\n";
}
foreach ($updatedCells as $reason) {
    echo '  ~ ' . $reason . "\n";
}
foreach ($preservedCells as $reason) {
    echo '  = ' . $reason . "\n";
}
foreach ($excludedCells as $reason) {
    echo '  x ' . $reason . "\n";
}
if ($paperClashes !== []) {
    echo 'printed clashes loaded as printed: ' . count($paperClashes) . "\n";
    foreach ($paperClashes as $reason) {
        echo '  - ' . $reason . "\n";
    }
}

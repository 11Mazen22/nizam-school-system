<?php

declare(strict_types=1);

/**
 * Nizam -- load the official 2026/2027 class timetables.
 *
 * Source: docs/source/data/timetables_17.json, produced by
 * tools/extract_timetables.py from the 17 PDFs (exact word coordinates).
 *
 * Rules, all deliberate:
 *  - Subjects are matched to the existing subject rows; the new printed
 *    subjects (financial culture, sports, activity, physics, psychology /
 *    second language, chemistry / business, German, Spanish) are created once.
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
 * Usage: php bin/import-timetables.php [--dry-run]
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Services\TeacherAssignmentSyncService;

$dryRun = in_array('--dry-run', $argv, true);
$pdo = Database::connection();

$year = $pdo->query("SELECT id, label FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetch();
if ($year === false) {
    fwrite(STDERR, "No active academic year.\n");
    exit(1);
}
$yearId = (int) $year['id'];

$data = json_decode((string) file_get_contents(dirname(__DIR__) . '/docs/source/data/timetables_17.json'), true);
if (!is_array($data) || !isset($data['classes'])) {
    fwrite(STDERR, "timetables_17.json is missing or unreadable.\n");
    exit(1);
}

$subjectIds = [];
foreach ($pdo->query('SELECT id, name_ar FROM subjects') as $row) {
    $subjectIds[(string) $row['name_ar']] = (int) $row['id'];
}

/** Printed label => [existing Arabic name | new subject definition]. */
$subjectMap = [
    'عربي' => ['existing' => 'اللغة العربية'],
    'إنجليزي' => ['existing' => 'اللغة الإنجليزية'],
    'رياضيات' => ['existing' => 'الرياضيات'],
    'علوم' => ['existing' => 'العلوم'],
    'تاريخ' => ['existing' => 'التاريخ'],
    'برمجة' => ['existing' => 'الحاسوب'],
    'دين' => ['existing' => 'التربية الدينية'],
    'فلسفة' => ['existing' => 'الفلسفة'],
    'ألماني/فرنساوي' => ['existing' => 'اللغة الثانية'],
    'G+F' => ['existing' => 'اللغة الثانية'],
    'G+A' => ['existing' => 'اللغة الثانية'],
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
$skipped = [];
$teacherIds = [];
$existingTeachers = [];
foreach ($pdo->query('SELECT id, full_name FROM teachers') as $row) {
    $existingTeachers[(string) $row['full_name']] = (int) $row['id'];
}

function subjectId(string $label): ?int
{
    global $subjectMap, $subjectIds, $pdo, $dryRun, $createdSubjects;

    if (!isset($subjectMap[$label])) {
        return null;
    }
    $map = $subjectMap[$label];
    if (isset($map['existing'])) {
        return $subjectIds[$map['existing']] ?? null;
    }
    [$code, $nameAr, $nameEn] = $map['new'];
    if ($dryRun) {
        $createdSubjects++;
        return -1;
    }
    $find = $pdo->prepare('SELECT id FROM subjects WHERE code = :code');
    $find->execute(['code' => $code]);
    $id = $find->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO subjects (code, name_en, name_ar, is_active) VALUES (:c, :en, :ar, 1)')
        ->execute(['c' => $code, 'en' => $nameEn, 'ar' => $nameAr]);
    $numeric = (int) $pdo->lastInsertId();
    $subjectIds[$nameAr] = $numeric;
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
    $pdo->prepare('UPDATE teachers SET teacher_code = :code WHERE id = :id')
        ->execute(['code' => 'TCH-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), 'id' => $id]);
    $createdTeachers++;
    $existingTeachers[$name] = $id;
    return $teacherIds[$name] = $id;
}

$classRows = [];
foreach ($pdo->query("SELECT id, name FROM classes WHERE academic_year_id = " . $yearId) as $row) {
    $classRows[(string) $row['name']] = (int) $row['id'];
}

if (!$dryRun) {
    $pdo->beginTransaction();
}

try {
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
            $teacherName = trim((string) $cell['teacher']);
            if ($teacherName === '') {
                $skipped[] = "{$class['class_id']} {$cell['day']} p{$cell['period']}: no teacher printed for '{$label}'";
                continue;
            }
            $names = array_values(array_filter(array_map('trim', explode('/', $teacherName))));
            $primary = $names[0] ?? '';
            if ($primary === '') {
                $skipped[] = "{$class['class_id']} {$cell['day']} p{$cell['period']}: empty teacher for '{$label}'";
                continue;
            }
            $note = count($names) > 1 ? $label . ' group pair: ' . implode(' / ', $names) : null;
            if (in_array($label, ['G+F', 'G+A', 'ألماني/فرنساوي'], true) && $note === null) {
                $note = $label . ' split-group language lesson';
            }

            try {
                if ($dryRun) {
                    $inserted++;
                    $perClass++;
                    continue;
                }
                $pdo->prepare(
                    'INSERT INTO timetables (academic_year_id, class_id, day_of_week, period_number, subject_id, teacher_id, note)
                     VALUES (:year, :class, :day, :period, :subject, :teacher, :note)'
                )->execute([
                    'year' => $yearId,
                    'class' => $classId,
                    'day' => $day,
                    'period' => (int) $cell['period'],
                    'subject' => $subjectId,
                    'teacher' => teacherId($primary),
                    'note' => $note,
                ]);
                foreach (array_slice($names, 1) as $extra) {
                    teacherId($extra); // keep the second group teacher on record
                }
                $inserted++;
                $perClass++;
            } catch (PDOException $e) {
                $skipped[] = "{$class['class_id']} {$cell['day']} p{$cell['period']}: slot conflict ({$label}, {$teacherName})";
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

echo "\n" . ($dryRun ? 'DRY RUN -- nothing written' : 'Committed') . "\n";
echo "slots inserted: {$inserted}\n";
if (!$dryRun) {
    echo "assignments created: {$load['created']}, corrected: {$load['updated']}\n";
}
echo "subjects created: {$createdSubjects}\n";
echo "teachers created: {$createdTeachers}\n";
echo 'skipped: ' . count($skipped) . "\n";
foreach ($skipped as $reason) {
    echo '  - ' . $reason . "\n";
}

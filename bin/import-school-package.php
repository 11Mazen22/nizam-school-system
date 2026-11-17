<?php

declare(strict_types=1);

/**
 * Import the official 2026/2027 school package (Hadaba Al-Ahram).
 *
 *   php bin/import-school-package.php --source="<package dir>" --dry-run
 *   php bin/import-school-package.php --source="<package dir>"
 *
 * What this imports, and only this:
 *   - the 17 canonical classes A1-A9 / B1-B8 with their exact Arabic pathway
 *     labels, mapped to the first/second secondary grade;
 *   - the nine A1-A9 weekly assessment distributions (45 weekday rows);
 *   - the subject catalogue those rows reference.
 *
 * What it deliberately does NOT import:
 *   - student names. The roster PDFs have a broken font-to-Unicode map and the
 *     brief requires every name to be read from the rendered page and verified.
 *     Inventing them is worse than leaving the roster empty.
 *   - class timetables. The package ships them as PDFs and as scrambled page
 *     text; no structured cell data exists, so a blind parse would fabricate
 *     teacher/period assignments.
 *
 * Every written row records its source document, class and version, and the
 * run itself is recorded in import_batches.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Services\MigrationService;

$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
        $options[$key] = $value;
    }
}

$source = (string) ($options['source'] ?? 'C:/Users/mazen/Downloads/Nizam_Complete_Prompt_and_Source_Data_2026-2027/Nizam_Complete_Prompt_Package');
$dryRun = array_key_exists('dry-run', $options);

if (!is_dir($source)) {
    fwrite(STDERR, "Package directory not found: {$source}\n");
    exit(1);
}

$promptFile = $source . '/Nizam_Master_Prompt_Full.md';
$assessmentsFile = $source . '/data/weekly_assessments_A1_A9.json';
foreach ([$promptFile, $assessmentsFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing source file: {$file}\n");
        exit(1);
    }
}

// ---------------------------------------------------------------- pathways
// The canonical table lives in §2.2 of the package prompt. Parsing it keeps
// the labels source-derived instead of retyped by hand.
$prompt = (string) file_get_contents($promptFile);
// The supplied prompt file stores a literal \n inside most lines (on top of
// the real line endings), which keeps its Markdown tables on one physical
// line. Normalize both forms so the tables can be parsed.
$prompt = str_replace('\\n', "\n", $prompt);
$prompt = (string) preg_replace("/\n{3,}/", "\n\n", $prompt);
$pathways = [];
if (preg_match('/### 2\.2[^\n]*\n(.*?)### 2\.3/s', $prompt, $section) === 1) {
    preg_match_all('/^\|\s*(A\d|B\d)\s*\|\s*([^|]+?)\s*\|/mu', $section[1], $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $pathways[$match[1]] = trim($match[2]);
    }
}
ksort($pathways);
if (count($pathways) !== 17) {
    fwrite(STDERR, 'Expected 17 pathway labels in the prompt, found ' . count($pathways) . ".\n");
    exit(1);
}

// -------------------------------------------------------------- assessments
$assessmentData = json_decode((string) file_get_contents($assessmentsFile), true, 512, JSON_THROW_ON_ERROR);
$classes = $assessmentData['classes'] ?? [];
if (count($classes) !== 9) {
    fwrite(STDERR, 'Expected 9 assessment classes, found ' . count($classes) . ".\n");
    exit(1);
}

$weekdays = ['الأحد' => 2, 'الاثنين' => 3, 'الثلاثاء' => 4, 'الأربعاء' => 5, 'الخميس' => 6];
$noAssessmentMarker = 'لا يوجد تقييم مقرر';

// ------------------------------------------------------------------ subjects
$subjectDefinitions = [
    'عربي' => ['AR', 'اللغة العربية', 'Arabic Language'],
    'إنجليزي' => ['EN', 'اللغة الإنجليزية', 'English Language'],
    'رياضيات' => ['MATH', 'الرياضيات', 'Mathematics'],
    'علوم' => ['SCI', 'العلوم', 'Science'],
    'تاريخ' => ['HI', 'التاريخ', 'History'],
    'برمجة' => ['CS', 'الحاسوب والبرمجة', 'Computer Science'],
    'دين' => ['REL', 'التربية الدينية', 'Religious Education'],
    'فلسفة' => ['PHIL', 'الفلسفة', 'Philosophy'],
    'لغة ثانية' => ['LANG2', 'اللغة الثانية', 'Second Language'],
];

$pdo = Database::connection();

if (!$dryRun) {
    // Apply pending migrations so a fresh install gets the tables too.
    $migration = new MigrationService();
    $migration->bootstrapMigrationsTable($pdo);
    $applied = $migration->run($pdo, dirname(__DIR__) . '/database/migrations');
    if (($applied['applied'] ?? []) !== []) {
        echo 'Applied migrations: ' . implode(', ', $applied['applied']) . "\n";
    }
}

$year = $pdo->query("SELECT id, label FROM academic_years WHERE is_closed = 0 ORDER BY is_active DESC, id DESC LIMIT 1")->fetch();
if ($year === false) {
    fwrite(STDERR, "No open academic year found. Create 2026/2027 first.\n");
    exit(1);
}
$yearId = (int) $year['id'];

$grades = [];
foreach ($pdo->query('SELECT id, name_en FROM grades') as $row) {
    $grades[(string) $row['name_en']] = (int) $row['id'];
}
$firstSecondary = null;
$secondSecondary = null;
foreach ($grades as $nameEn => $id) {
    if (str_starts_with($nameEn, 'First')) {
        $firstSecondary = $id;
    }
    if (str_starts_with($nameEn, 'Second')) {
        $secondSecondary = $id;
    }
}
if ($firstSecondary === null || $secondSecondary === null) {
    fwrite(STDERR, "Could not resolve the first/second secondary grade rows.\n");
    exit(1);
}

$report = [
    'source_package' => $source,
    'academic_year' => $year['label'],
    'pathways_parsed' => count($pathways),
    'classes_created' => 0,
    'classes_updated' => 0,
    'subjects_created' => [],
    'assessment_rows' => 0,
    'no_assessment_rows' => 0,
    'unmapped_subjects' => [],
];

echo "Package: {$source}\n";
echo "Academic year: {$year['label']} (id {$yearId})\n";
echo 'Classes/pathways: ' . count($pathways) . "\n";
echo $dryRun ? "MODE: dry run (nothing is written)\n\n" : "MODE: import\n\n";

// ---------------------------------------------------------------- subjects
$existingSubjects = [];
$existingByCode = [];
foreach ($pdo->query('SELECT id, code, name_ar FROM subjects') as $row) {
    $existingSubjects[(string) $row['name_ar']] = (int) $row['id'];
    $existingByCode[strtoupper((string) $row['code'])] = (int) $row['id'];
}

/**
 * Find or create a subject, returning its id (null on dry run when missing).
 */
$resolveSubject = static function (string $label) use (&$existingSubjects, &$existingByCode, $subjectDefinitions, $pdo, $dryRun, &$report): ?int {
    $definition = $subjectDefinitions[$label] ?? null;
    if ($definition === null) {
        if (!in_array($label, $report['unmapped_subjects'], true)) {
            $report['unmapped_subjects'][] = $label;
        }
        return null;
    }
    [$code, $nameAr, $nameEn] = $definition;

    if (isset($existingSubjects[$nameAr])) {
        return $existingSubjects[$nameAr];
    }
    // The existing catalogue may already carry this subject under a shorter
    // Arabic name (e.g. الحاسوب for CS); reuse it rather than duplicating.
    if (isset($existingByCode[strtoupper($code)])) {
        return $existingByCode[strtoupper($code)];
    }

    if ($dryRun) {
        $label = "{$nameAr} ({$code})";
        if (!in_array($label, $report['subjects_created'], true)) {
            $report['subjects_created'][] = $label;
        }
        return null;
    }

    $stmt = $pdo->prepare('INSERT INTO subjects (code, name_en, name_ar, is_active) VALUES (:c, :en, :ar, 1)');
    $stmt->execute(['c' => $code, 'en' => $nameEn, 'ar' => $nameAr]);
    $id = (int) $pdo->lastInsertId();
    $existingSubjects[$nameAr] = $id;
    $report['subjects_created'][] = "{$nameAr} ({$code})";

    return $id;
};

// Resolve the catalogue up front so the summary is complete before writing.
foreach ($subjectDefinitions as $label => [$code]) {
    $resolveSubject($label);
}

// ----------------------------------------------------------------- classes
// A dry run skips migrations, so the new column may not exist yet.
$hasPathway = false;
foreach ($pdo->query('SHOW COLUMNS FROM classes') as $column) {
    if ((string) $column['Field'] === 'pathway_ar') {
        $hasPathway = true;
        break;
    }
}
$classSelect = $hasPathway ? 'SELECT id, pathway_ar FROM classes' : 'SELECT id, NULL AS pathway_ar FROM classes';

$classIds = [];
foreach ($pathways as $className => $pathway) {
    $gradeId = $className[0] === 'A' ? $firstSecondary : $secondSecondary;

    $stmt = $pdo->prepare(
        str_replace('FROM classes', 'FROM classes WHERE academic_year_id = :y AND grade_id = :g AND name = :n', $classSelect)
    );
    $stmt->execute(['y' => $yearId, 'g' => $gradeId, 'n' => $className]);
    $existing = $stmt->fetch();

    if ($existing !== false) {
        $classIds[$className] = (int) $existing['id'];
        if ((string) ($existing['pathway_ar'] ?? '') !== $pathway) {
            if (!$dryRun) {
                $update = $pdo->prepare('UPDATE classes SET pathway_ar = :p WHERE id = :id');
                $update->execute(['p' => $pathway, 'id' => $existing['id']]);
            }
            $report['classes_updated']++;
            echo "  update {$className}: pathway => {$pathway}\n";
        }
        continue;
    }

    if ($dryRun) {
        $report['classes_created']++;
        echo "  create {$className}: {$pathway}\n";
        continue;
    }

    $insert = $pdo->prepare(
        'INSERT INTO classes (grade_id, academic_year_id, name, pathway_ar, is_active)
         VALUES (:g, :y, :n, :p, 1)'
    );
    $insert->execute(['g' => $gradeId, 'y' => $yearId, 'n' => $className, 'p' => $pathway]);
    $classIds[$className] = (int) $pdo->lastInsertId();
    $report['classes_created']++;
    echo "  create {$className}: {$pathway}\n";
}

// ------------------------------------------------------------- assessments
$sourceDocument = 'جداول التقييمات للفصول A1-A9 - 2026-2027.zip';
foreach ($classes as $className => $rows) {
    $classId = $classIds[$className] ?? null;
    if ($classId === null && !$dryRun) {
        fwrite(STDERR, "Class {$className} is missing after import; assessment rows skipped.\n");
        continue;
    }

    foreach ($rows as $row) {
        $weekday = (string) $row['weekday'];
        $day = $weekdays[$weekday] ?? null;
        if ($day === null) {
            fwrite(STDERR, "Unknown weekday '{$weekday}' for {$className}; row skipped.\n");
            continue;
        }

        $subjects = array_values(array_filter(array_map('trim', (array) ($row['subjects_exact'] ?? []))));
        $raw = (string) ($row['subjects_cell_exact'] ?? implode(' • ', $subjects));
        $noAssessment = in_array($noAssessmentMarker, $subjects, true) ? 1 : 0;
        $report['assessment_rows']++;
        $report['no_assessment_rows'] += $noAssessment;

        foreach ($subjects as $label) {
            if ($label === $noAssessmentMarker) {
                continue; // an explicit no-assessment marker, not a subject
            }
            $resolveSubject($label);
        }

        if ($dryRun) {
            continue;
        }

        $upsert = $pdo->prepare(
            'INSERT INTO assessment_schedules
                (academic_year_id, class_id, day_of_week, sequence, subjects_raw, no_assessment, source_document, source_class, source_version)
             VALUES (:y, :c, :d, :s, :raw, :none, :doc, :sc, :v)
             ON DUPLICATE KEY UPDATE
                sequence = VALUES(sequence),
                subjects_raw = VALUES(subjects_raw),
                no_assessment = VALUES(no_assessment),
                source_document = VALUES(source_document),
                source_class = VALUES(source_class),
                source_version = VALUES(source_version)'
        );
        $upsert->execute([
            'y' => $yearId,
            'c' => $classId,
            'd' => $day,
            's' => (int) $row['sequence'],
            'raw' => $raw,
            'none' => $noAssessment,
            'doc' => $sourceDocument,
            'sc' => $className,
            'v' => '2026/2027',
        ]);
    }
}

echo "\n";
echo "Classes created: {$report['classes_created']}, updated: {$report['classes_updated']}\n";
echo "Assessment rows: {$report['assessment_rows']} ({$report['no_assessment_rows']} explicit no-assessment)\n";
echo 'Subjects created: ' . (count($report['subjects_created']) ? implode(', ', $report['subjects_created']) : 'none') . "\n";
if ($report['unmapped_subjects'] !== []) {
    echo 'Unmapped subject labels: ' . implode(', ', $report['unmapped_subjects']) . " (kept verbatim, not linked)\n";
}

if (!$dryRun) {
    $insert = $pdo->prepare(
        'INSERT INTO import_batches (source_package, source_version, summary_json, imported_by) VALUES (:p, :v, :j, :by)'
    );
    $insert->execute([
        'p' => basename($source),
        'v' => '2026/2027',
        'j' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        'by' => 'cli',
    ]);
    echo "\nImport recorded as batch #" . $pdo->lastInsertId() . ".\n";
    echo "Student rosters and class timetables were NOT imported: the package ships them as PDFs/images only.\n";
}

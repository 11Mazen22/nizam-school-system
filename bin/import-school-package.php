<?php

declare(strict_types=1);

/**
 * Import the official 2026/2027 school package (Hadaba Al-Ahram).
 *
 *   php bin/import-school-package.php
 *   php bin/import-school-package.php --dry-run
 *   php bin/import-school-package.php --pathways=<file> --assessments=<file>
 *   php bin/import-school-package.php --source="<original package dir>"
 *
 * What this imports, and only this:
 *   - the 17 canonical classes A1-A9 / B1-B8 with their exact Arabic pathway
 *     labels, mapped to the first/second secondary grade, and the school's
 *     confirmed maximum capacity of 50 students per class;
 *   - the nine A1-A9 weekly assessment distributions (45 weekday rows);
 *   - the subject catalogue those rows reference.
 *
 * The canonical inputs ship with the application in database/official/:
 * pathways.json and weekly_assessments_A1_A9.json. The older --source form is
 * still accepted for the original package layout, where the pathway table
 * lives in the master prompt and the assessments file sits under data/.
 *
 * What it deliberately does NOT import:
 *   - student names. The roster PDFs have a broken font-to-Unicode map and the
 *     brief requires every name to be read from the rendered page and verified.
 *     Inventing them is worse than leaving the roster empty. The reviewed CSV
 *     rosters are imported by bin/import-roster-csv.php.
 *   - class timetables. The package ships them as PDFs and as scrambled page
 *     text; no structured cell data exists, so a blind parse would fabricate
 *     teacher/period assignments. The reviewed grid is imported by
 *     bin/import-timetables.php.
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

$root = dirname(__DIR__);
$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
        $options[$key] = $value;
    }
}

$dryRun = array_key_exists('dry-run', $options);
$skipMigrations = array_key_exists('skip-migrations', $options);
$source = (string) ($options['source'] ?? '');
$pathwaysFile = (string) ($options['pathways'] ?? '');
$assessmentsFile = (string) ($options['assessments'] ?? '');

if ($source !== '') {
    if (!is_dir($source)) {
        fwrite(STDERR, "Package directory not found: {$source}\n");
        exit(1);
    }
    // The original package keeps the pathway table in the master prompt and
    // the assessments under data/. Use those when the caller did not name an
    // explicit canonical file.
    if ($pathwaysFile === '') {
        $candidate = $source . '/Nizam_Master_Prompt_Full.md';
        if (is_file($candidate)) {
            $pathwaysFile = $candidate;
        }
    }
    if ($assessmentsFile === '') {
        $candidate = $source . '/data/weekly_assessments_A1_A9.json';
        if (is_file($candidate)) {
            $assessmentsFile = $candidate;
        }
    }
}

if ($pathwaysFile === '') {
    $pathwaysFile = $root . '/database/official/pathways.json';
}
if ($assessmentsFile === '') {
    $assessmentsFile = $root . '/database/official/weekly_assessments_A1_A9.json';
}
foreach ([$pathwaysFile, $assessmentsFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing source file: {$file}\n");
        exit(1);
    }
}

// ---------------------------------------------------------------- pathways
$pathways = [];
$classCapacity = null;
if (str_ends_with(strtolower($pathwaysFile), '.md')) {
    // The supplied prompt file stores a literal \n inside most lines (on top
    // of the real line endings), which keeps its Markdown tables on one
    // physical line. Normalize both forms so the tables can be parsed.
    $prompt = (string) file_get_contents($pathwaysFile);
    $prompt = str_replace('\\n', "\n", $prompt);
    $prompt = (string) preg_replace("/\n{3,}/", "\n\n", $prompt);
    if (preg_match('/### 2\.2[^\n]*\n(.*?)### 2\.3/s', $prompt, $section) === 1) {
        preg_match_all('/^\|\s*(A\d|B\d)\s*\|\s*([^|]+?)\s*\|/mu', $section[1], $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $pathways[strtoupper($match[1])] = trim($match[2]);
        }
    }
} else {
    try {
        $decoded = json_decode((string) file_get_contents($pathwaysFile), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        fwrite(STDERR, "Could not read {$pathwaysFile}: {$e->getMessage()}\n");
        exit(1);
    }
    $rows = is_array($decoded) ? ($decoded['classes'] ?? $decoded) : [];
    $capacityRaw = is_array($decoded) ? ($decoded['class_capacity'] ?? null) : null;
    $classCapacity = is_numeric($capacityRaw) ? max(1, (int) $capacityRaw) : null;
    if (!is_array($rows)) {
        fwrite(STDERR, "{$pathwaysFile} does not contain a class => pathway map.\n");
        exit(1);
    }
    foreach ($rows as $name => $label) {
        $name = strtoupper(trim((string) $name));
        $label = trim((string) $label);
        if (preg_match('/^[AB][1-9]$/', $name) === 1 && $label !== '') {
            $pathways[$name] = $label;
        }
    }
}
ksort($pathways);
if (count($pathways) !== 17) {
    fwrite(STDERR, 'Expected 17 pathway labels, found ' . count($pathways) . " in {$pathwaysFile}.\n");
    exit(1);
}

// -------------------------------------------------------------- assessments
try {
    $assessmentData = json_decode((string) file_get_contents($assessmentsFile), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, "Could not read {$assessmentsFile}: {$e->getMessage()}\n");
    exit(1);
}
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
$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if (!$dryRun && !$skipMigrations) {
    // Apply pending migrations so a fresh install gets the tables too. The
    // online entrypoint already migrated, so the seed runner passes
    // --skip-migrations and never needs CREATE privileges.
    $migrationsDir = $root . '/database/' . ($driver === 'pgsql' ? 'migrations-pg' : 'migrations');
    $migration = new MigrationService();
    $migration->bootstrapMigrationsTable($pdo);
    $applied = $migration->run($pdo, $migrationsDir);
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
    'source_package' => $source !== '' ? basename($source) : 'database/official',
    'academic_year' => $year['label'],
    'pathways_parsed' => count($pathways),
    'classes_created' => 0,
    'classes_updated' => 0,
    'subjects_created' => [],
    'assessment_rows' => 0,
    'no_assessment_rows' => 0,
    'unmapped_subjects' => [],
];

echo "Pathways: {$pathwaysFile}\n";
echo "Assessments: {$assessmentsFile}\n";
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
    $existingByCode[strtoupper($code)] = $id;
    $report['subjects_created'][] = "{$nameAr} ({$code})";

    return $id;
};

// Resolve the catalogue up front so the summary is complete before writing.
foreach ($subjectDefinitions as $label => [$code]) {
    $resolveSubject($label);
}

// ----------------------------------------------------------------- classes
// A dry run skips migrations, so the new column may not exist yet. Asking for
// the column is portable across MySQL and PostgreSQL; SHOW COLUMNS is not.
$hasPathway = true;
try {
    $probe = $pdo->query('SELECT pathway_ar FROM classes WHERE 1 = 0');
    $hasPathway = $probe !== false;
} catch (Throwable) {
    $hasPathway = false;
}

$classSelect = $hasPathway
    ? 'SELECT id, pathway_ar, capacity FROM classes WHERE academic_year_id = :y AND grade_id = :g AND name = :n'
    : 'SELECT id, NULL AS pathway_ar, capacity FROM classes WHERE academic_year_id = :y AND grade_id = :g AND name = :n';

$classIds = [];
foreach ($pathways as $className => $pathway) {
    $gradeId = $className[0] === 'A' ? $firstSecondary : $secondSecondary;

    $stmt = $pdo->prepare($classSelect);
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
        if ($classCapacity !== null && (int) ($existing['capacity'] ?? 0) !== $classCapacity) {
            if (!$dryRun) {
                $update = $pdo->prepare('UPDATE classes SET capacity = :cap WHERE id = :id');
                $update->execute(['cap' => $classCapacity, 'id' => $existing['id']]);
            }
            $report['classes_updated']++;
            echo "  update {$className}: capacity => {$classCapacity}\n";
        }
        continue;
    }

    if ($dryRun) {
        $report['classes_created']++;
        echo "  create {$className}: {$pathway}\n";
        continue;
    }

    $insert = $pdo->prepare(
        'INSERT INTO classes (grade_id, academic_year_id, name, pathway_ar, capacity, is_active)
         VALUES (:g, :y, :n, :p, :cap, 1)'
    );
    $insert->execute(['g' => $gradeId, 'y' => $yearId, 'n' => $className, 'p' => $pathway, 'cap' => $classCapacity]);
    $classIds[$className] = (int) $pdo->lastInsertId();
    $report['classes_created']++;
    echo "  create {$className}: {$pathway}\n";
}

// ------------------------------------------------------------- assessments
$sourceDocument = 'جداول التقييمات للفصول A1-A9 - 2026-2027.zip';
$upsert = null;
if (!$dryRun) {
    $sql = 'INSERT INTO assessment_schedules
                (academic_year_id, class_id, day_of_week, sequence, subjects_raw, no_assessment, source_document, source_class, source_version)
            VALUES (:y, :c, :d, :s, :raw, :none, :doc, :sc, :v)';
    $sql .= $driver === 'pgsql'
        ? ' ON CONFLICT (academic_year_id, class_id, day_of_week) DO UPDATE SET
                sequence = EXCLUDED.sequence,
                subjects_raw = EXCLUDED.subjects_raw,
                no_assessment = EXCLUDED.no_assessment,
                source_document = EXCLUDED.source_document,
                source_class = EXCLUDED.source_class,
                source_version = EXCLUDED.source_version'
        : ' ON DUPLICATE KEY UPDATE
                sequence = VALUES(sequence),
                subjects_raw = VALUES(subjects_raw),
                no_assessment = VALUES(no_assessment),
                source_document = VALUES(source_document),
                source_class = VALUES(source_class),
                source_version = VALUES(source_version)';
    $upsert = $pdo->prepare($sql);
}

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
echo 'Class capacity: ' . ($classCapacity !== null ? "{$classCapacity} students (school-confirmed)" : 'not declared in this package') . "\n";
echo "Assessment rows: {$report['assessment_rows']} ({$report['no_assessment_rows']} explicit no-assessment)\n";
echo 'Subjects created: ' . (count($report['subjects_created']) ? implode(', ', $report['subjects_created']) : 'none') . "\n";
if ($report['unmapped_subjects'] !== []) {
    echo 'Unmapped subject labels: ' . implode(', ', $report['unmapped_subjects']) . " (kept verbatim, not linked)\n";
}

if (!$dryRun) {
    $summary = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $batchSql = 'INSERT INTO import_batches (source_package, source_version, summary_json, imported_by)
                 VALUES (:p, :v, ' . ($driver === 'pgsql' ? 'CAST(:j AS JSONB)' : ':j') . ', :by)';
    $insert = $pdo->prepare($batchSql);
    $insert->execute([
        'p' => $report['source_package'],
        'v' => '2026/2027',
        'j' => $summary,
        'by' => 'cli',
    ]);
    echo "\nImport recorded as batch #" . $pdo->lastInsertId() . ".\n";
    echo "Rosters and timetables are imported separately by bin/seed-official-data.php.\n";
}

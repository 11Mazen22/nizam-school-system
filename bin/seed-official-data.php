<?php

declare(strict_types=1);

/**
 * Nizam -- load the official 2026/2027 school data.
 *
 *   php bin/seed-official-data.php
 *   php bin/seed-official-data.php --dry-run
 *   php bin/seed-official-data.php --force --marker=<file>
 *
 * This is the one command that turns a freshly migrated installation into the
 * real school: 17 classes A1-A9 / B1-B8 with their Arabic pathway labels, the
 * 17 reviewed student rosters (778 source students + enrollments), the 45 weekly
 * assessment rows, and the 17 printed class timetables (507 slots) with the
 * teaching load derived from them.
 *
 * Every step is idempotent:
 *   - classes are matched by year/grade/name and their pathway and the
 *     school-confirmed 50-student capacity are applied;
 *   - subjects are matched by Arabic name or stable code;
 *   - assessments upsert on (year, class, weekday);
 *   - an already-enrolled student is enriched, never duplicated;
 *   - a year that already has timetable slots is left untouched (the
 *     timetable importer reports and exits zero).
 *
 * The canonical inputs ship in database/official/ and are the same files the
 * offline system was built from, so the online deployment carries exactly the
 * same school data. A marker file records the successful run; the production
 * entrypoint uses it to seed once and never again on later boots.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;

$root = dirname(__DIR__);
$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
        $options[$key] = $value;
    }
}

$dryRun = array_key_exists('dry-run', $options);
$force = array_key_exists('force', $options);
$replaceTimetables = array_key_exists('replace-timetables', $options);
$dataDir = rtrim((string) ($options['data-dir'] ?? $root . '/database/official'), '/\\');
$markerPath = (string) ($options['marker'] ?? dataPath() . '/storage/official-data-seed.json');
$officialDataVersion = 7;

/**
 * Run one importer as a child process, streaming its output into this log.
 *
 * @param list<string> $arguments
 */
function runStep(string $label, array $arguments): void
{
    $command = escapeshellarg(PHP_BINARY);
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }

    echo "\n==> {$label}\n";
    $started = microtime(true);
    passthru($command, $status);
    $elapsed = microtime(true) - $started;

    if ($status !== 0) {
        throw new RuntimeException("{$label} failed with exit code {$status}.");
    }
    echo sprintf("    [ok] %.1fs\n", $elapsed);
}

/** @return array<string,int> */
function recordCounts(PDO $pdo, int $yearId): array
{
    // The first/second-secondary package is verified against its own 778-row
    // audit; third-secondary students are counted separately by their importer.
    $abJoin = " FROM students s
                JOIN student_enrollments e ON e.student_id = s.id AND e.academic_year_id = {$yearId} AND e.status = 'active'
                JOIN classes c ON c.id = e.class_id
                WHERE (c.name LIKE 'A%' OR c.name LIKE 'B%')";
    $count = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
    return [
        'classes' => $count('SELECT COUNT(*) FROM classes WHERE academic_year_id = ' . $yearId . " AND (name LIKE 'A%' OR name LIKE 'B%')"),
        'classes_with_capacity' => $count('SELECT COUNT(*) FROM classes WHERE academic_year_id = ' . $yearId . " AND capacity = 50 AND (name LIKE 'A%' OR name LIKE 'B%')"),
        'third_secondary_classes' => $count("SELECT COUNT(*) FROM classes WHERE academic_year_id = {$yearId} AND name LIKE 'C%'"),
        'third_secondary_students' => $count("SELECT COUNT(*) FROM students s JOIN student_enrollments e ON e.student_id = s.id AND e.academic_year_id = {$yearId} WHERE s.national_id IS NOT NULL"),
        'students_in_year' => $count('SELECT COUNT(DISTINCT student_id) FROM student_enrollments WHERE academic_year_id = ' . $yearId),
        'enrollments' => $count('SELECT COUNT(*) FROM student_enrollments WHERE academic_year_id = ' . $yearId),
        'teachers' => $count('SELECT COUNT(*) FROM teachers'),
        'subjects' => $count('SELECT COUNT(*) FROM subjects'),
        'timetables' => $count('SELECT COUNT(*) FROM timetables WHERE academic_year_id = ' . $yearId),
        'assessment_schedules' => $count('SELECT COUNT(*) FROM assessment_schedules WHERE academic_year_id = ' . $yearId),
        'gender_confirmed' => $count('SELECT COUNT(DISTINCT s.id)' . $abJoin . ' AND s.gender IS NOT NULL'),
        'religion_confirmed' => $count('SELECT COUNT(DISTINCT s.id)' . $abJoin . ' AND s.religion IS NOT NULL'),
        'second_language_confirmed' => $count('SELECT COUNT(DISTINCT s.id)' . $abJoin . ' AND s.second_language IS NOT NULL'),
        'gender_provenance' => $count('SELECT COUNT(DISTINCT s.id)' . $abJoin . " AND s.gender_source = 'official_roster_layout'"),
        'religion_provenance' => $count('SELECT COUNT(DISTINCT s.id)' . $abJoin . " AND s.religion_source = 'official_roster_rule_v2'"),
        'religion_unconfirmed' => $count('SELECT COUNT(DISTINCT s.id)' . $abJoin . " AND s.religion IS NULL AND s.religion_source = 'official_roster_unconfirmed_v2'"),
        'second_language_provenance' => $count('SELECT COUNT(DISTINCT s.id)' . $abJoin . " AND s.second_language_source IN ('printed_cell', 'class_heading', 'source_typo_corrected')"),
    ];
}
/** @param array<string,int> $counts */
function printCounts(string $label, array $counts): void
{
    echo $label . "\n";
    foreach ($counts as $name => $value) {
        echo '  ' . str_pad(str_replace('_', ' ', $name), 24) . ': ' . $value . "\n";
    }
}

/**
 * Remove the generic starter subject the official catalogue does not contain,
 * but only while nothing references it. A fresh install seeds "Social Studies"
 * (SOC) as part of the generic starter list; the school's official 17-subject
 * catalogue does not include it, and the offline system has no such row. This
 * keeps the two installations identical without ever deleting a subject the
 * school has actually used.
 */
function pruneUnusedStarterSubject(PDO $pdo, string $code, string $arabicName): ?string
{
    $find = $pdo->prepare('SELECT id FROM subjects WHERE code = :code LIMIT 1');
    $find->execute(['code' => $code]);
    $id = (int) ($find->fetchColumn() ?: 0);
    if ($id === 0) {
        return null;
    }

    $references = 0;
    $columns = [
        ['teacher_subjects', 'subject_id'],
        ['teacher_assignments', 'subject_id'],
        ['subject_staffing_requirements', 'subject_id'],
        ['timetables', 'subject_id'],
        ['exam_scores', 'subject_id'],
        ['timetable_gaps', 'subject_id'],
        ['timetable_gaps', 'resolved_subject_id'],
    ];
    foreach ($columns as [$table, $column]) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :id");
            $stmt->execute(['id' => $id]);
            $references += (int) $stmt->fetchColumn();
        } catch (Throwable) {
            // Older schemas may not have the table yet.
        }
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM assessment_schedules WHERE subjects_raw LIKE :name');
    $stmt->execute(['name' => '%' . $arabicName . '%']);
    $references += (int) $stmt->fetchColumn();

    if ($references > 0) {
        return "kept (still referenced {$references} time(s))";
    }

    $pdo->prepare('DELETE FROM subjects WHERE id = :id')->execute(['id' => $id]);

    return 'removed (unused starter row; not part of the official catalogue)';
}

try {
    if (is_file($markerPath) && !$force && !$dryRun) {
        $existingMarker = json_decode((string) file_get_contents($markerPath), true);
        if (is_array($existingMarker) && (int) ($existingMarker['official_data_version'] ?? 1) >= $officialDataVersion) {
            echo "Official data version {$officialDataVersion} was already seeded.\n";
            echo "Marker: {$markerPath}\n";
            echo "Nothing to do. Pass --force to run the importers again.\n";
            exit(0);
        }
        echo "Updating the official data marker to version {$officialDataVersion}...\n";
    }

    // ----------------------------------------------------- input validation
    $required = [
        'pathways' => $dataDir . '/pathways.json',
        'assessments' => $dataDir . '/weekly_assessments_A1_A9.json',
        'timetables' => $dataDir . '/timetables_17.json',
        'roster_audit' => $dataDir . '/roster-data-audit.json',
        'third_secondary_xlsx' => $dataDir . '/third_secondary_students.xlsx',
        'third_secondary_audit' => $dataDir . '/third-secondary-audit.json',
    ];
    foreach ($required as $label => $file) {
        if (!is_file($file)) {
            throw new RuntimeException("Missing official data file: {$file}");
        }
    }

    $rosterFiles = glob($dataDir . '/roster_*.csv') ?: [];
    sort($rosterFiles);
    if (count($rosterFiles) !== 17) {
        throw new RuntimeException('Expected 17 roster CSVs in ' . $dataDir . ', found ' . count($rosterFiles) . '.');
    }

    $pathways = json_decode((string) file_get_contents($required['pathways']), true, 512, JSON_THROW_ON_ERROR);
    $pathwayRows = is_array($pathways) ? ($pathways['classes'] ?? $pathways) : [];
    if (!is_array($pathwayRows) || count($pathwayRows) !== 17) {
        throw new RuntimeException('pathways.json must contain exactly 17 class labels.');
    }

    $assessments = json_decode((string) file_get_contents($required['assessments']), true, 512, JSON_THROW_ON_ERROR);
    $assessmentCount = 0;
    foreach (($assessments['classes'] ?? []) as $rows) {
        $assessmentCount += count((array) $rows);
    }
    if ($assessmentCount !== 45) {
        throw new RuntimeException('The assessment file must contain 45 weekday rows, found ' . $assessmentCount . '.');
    }

    $timetables = json_decode((string) file_get_contents($required['timetables']), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($timetables['classes'] ?? null) || count($timetables['classes']) !== 17) {
        throw new RuntimeException('The timetable file must contain 17 classes.');
    }

    $rosterAudit = json_decode((string) file_get_contents($required['roster_audit']), true, 512, JSON_THROW_ON_ERROR);
    if ((int) ($rosterAudit['version'] ?? 0) !== $officialDataVersion
        || (int) ($rosterAudit['totals']['students'] ?? 0) !== 778
        || (int) ($rosterAudit['totals']['gender_confirmed'] ?? 0) !== 778) {
        throw new RuntimeException('roster-data-audit.json does not describe the reviewed 778-student version-7 package.');
    }

    $rosterRows = 0;
    foreach ($rosterFiles as $file) {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not open {$file}.");
        }
        $line = 0;
        while (fgetcsv($handle, 0, ',', '"', '') !== false) {
            $line++;
        }
        fclose($handle);
        $rosterRows += max(0, $line - 1); // header
    }
    if ($rosterRows !== (int) ($rosterAudit['totals']['students'] ?? 0)) {
        throw new RuntimeException("Roster CSV rows ({$rosterRows}) do not match the reviewed roster audit.");
    }

    $pdo = Database::connection();
    $year = $pdo->query('SELECT id, label, is_closed FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();
    if ($year === false || (string) $year['label'] !== '2026/2027') {
        // The official package belongs to 2026/2027. A freshly provisioned
        // database may carry a different default year (the generic seed
        // derives it from the current date), which would otherwise leave the
        // live site empty under the wrong label. Activate/create the official
        // year, but only on an installation that holds no school data at all;
        // a school that is already operating is never rewritten.
        $schoolRows = 0;
        foreach (['classes', 'students', 'student_enrollments', 'timetables', 'exam_scores'] as $table) {
            try {
                $schoolRows += (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            } catch (Throwable) {
                // Table absent on a very old schema; the migrations just ran.
            }
        }
        if ($schoolRows > 0) {
            $current = $year === false ? 'none' : (string) $year['label'];
            throw new RuntimeException("The active academic year is {$current}, not 2026/2027, and the installation already holds school data. Activate the official year first.");
        }
        if ($dryRun) {
            throw new RuntimeException('The active academic year is not 2026/2027. Run the import (without --dry-run) on this empty installation to activate it.');
        }

        echo "Activating the official 2026/2027 academic year on this empty installation...\n";
        $findYear = $pdo->prepare("SELECT id FROM academic_years WHERE label = '2026/2027' LIMIT 1");
        $findYear->execute();
        $officialYearId = (int) ($findYear->fetchColumn() ?: 0);

        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE academic_years SET is_active = 0');
            if ($officialYearId === 0) {
                $officialYearId = Database::insert(
                    $pdo,
                    "INSERT INTO academic_years (label, start_date, end_date, is_active, is_closed, expected_weekly_capacity)
                     VALUES ('2026/2027', '2026-09-01', '2027-06-30', 1, 0, 35)"
                );
            } else {
                $pdo->prepare('UPDATE academic_years SET is_active = 1, is_closed = 0 WHERE id = :id')
                    ->execute(['id' => $officialYearId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $year = $pdo->query('SELECT id, label, is_closed FROM academic_years WHERE id = ' . $officialYearId)->fetch();
        if ($year === false || (string) $year['label'] !== '2026/2027') {
            throw new RuntimeException('Could not activate the official 2026/2027 academic year.');
        }
    }
    if ((int) $year['is_closed'] === 1) {
        throw new RuntimeException('The active academic year is closed; reopen it before seeding.');
    }
    $yearId = (int) $year['id'];

    echo "Nizam -- official school data\n";
    echo str_repeat('=', 60) . "\n";
    echo "Academic year: {$year['label']} (id {$yearId})\n";
    echo 'Data: ' . $dataDir . "\n";
    echo 'Rosters: ' . count($rosterFiles) . " files, {$rosterRows} students\n";
    echo 'Assessments: ' . $assessmentCount . " weekday rows\n";
    echo $dryRun ? "MODE: dry run\n" : "MODE: import\n";

    printCounts("\nBefore:", recordCounts($pdo, $yearId));

    // ------------------------------------------------------------ importers
    $packageArgs = [
        $root . '/bin/import-school-package.php',
        '--pathways=' . $required['pathways'],
        '--assessments=' . $required['assessments'],
        '--skip-migrations',
    ];
    if ($dryRun) {
        $packageArgs[] = '--dry-run';
    }
    runStep('Classes, pathways, subjects and weekly assessments', $packageArgs);

    if (!$dryRun) {
        $catalogueNote = pruneUnusedStarterSubject($pdo, 'SOC', 'الدراسات الاجتماعية');
        if ($catalogueNote !== null) {
            echo "Official subject catalogue: Social Studies (SOC) {$catalogueNote}.\n";
        }
    }

    foreach ($rosterFiles as $rosterFile) {
        $rosterArgs = [$root . '/bin/import-roster-csv.php', $rosterFile];
        if ($dryRun) {
            $rosterArgs[] = '--dry-run';
        }
        runStep('Roster ' . basename($rosterFile), $rosterArgs);
    }

    if (!$dryRun) {
        runStep('Third secondary (الصف الثالث) spreadsheet', [$root . '/bin/import-third-secondary.php']);
    }

    $timetableArgs = [$root . '/bin/import-timetables.php'];
    if ($dryRun) {
        $timetableArgs[] = '--dry-run';
    }
    if ($replaceTimetables) {
        $timetableArgs[] = '--replace';
    }
    runStep('Class timetables and teaching load', $timetableArgs);

    if ($dryRun) {
        echo "\nDRY RUN -- nothing was written and no marker was created.\n";
        exit(0);
    }

    // ------------------------------------------------------------- verification
    $counts = recordCounts($pdo, $yearId);
    printCounts("\nAfter:", $counts);

    $problems = [];
    if ($counts['classes'] < 17) {
        $problems[] = "only {$counts['classes']} of the 17 classes exist";
    }
    if ($counts['classes_with_capacity'] !== 17) {
        $problems[] = "{$counts['classes_with_capacity']} of 17 classes carry the school-confirmed 50-student capacity";
    }
    if ($counts['third_secondary_classes'] !== 12) {
        $problems[] = "{$counts['third_secondary_classes']} of 12 third-secondary classes exist";
    }
    if ($counts['third_secondary_students'] !== 540) {
        $problems[] = "{$counts['third_secondary_students']} of 540 third-secondary students exist";
    }
    if ($counts['subjects'] < 17) {
        $problems[] = "only {$counts['subjects']} of the 17 official subjects exist";
    }
    if ($counts['students_in_year'] < $rosterRows) {
        $problems[] = "{$counts['students_in_year']} of {$rosterRows} students are enrolled";
    }
    if ($counts['enrollments'] < $rosterRows) {
        $problems[] = "{$counts['enrollments']} of {$rosterRows} enrollments exist";
    }
    if ($counts['gender_confirmed'] !== (int) ($rosterAudit['totals']['gender_confirmed'] ?? 0)) {
        $problems[] = "{$counts['gender_confirmed']} of {$rosterRows} reviewed genders exist";
    }
    if ($counts['religion_confirmed'] !== (int) ($rosterAudit['totals']['religion_confirmed'] ?? 0)) {
        $problems[] = "{$counts['religion_confirmed']} source-confirmed religions exist; the audit requires " . (int) ($rosterAudit['totals']['religion_confirmed'] ?? 0);
    }
    if ($counts['second_language_confirmed'] !== (int) ($rosterAudit['totals']['second_language_confirmed'] ?? 0)) {
        $problems[] = "{$counts['second_language_confirmed']} source-confirmed second languages exist; the audit requires " . (int) ($rosterAudit['totals']['second_language_confirmed'] ?? 0);
    }
    if ($counts['gender_provenance'] !== $rosterRows) {
        $problems[] = "{$counts['gender_provenance']} of {$rosterRows} reviewed genders carry official provenance";
    }
    $sourceReligionProvenance = 0;
    foreach ((array) ($rosterAudit['totals']['religion_source'] ?? []) as $source => $count) {
        if (in_array($source, ['combined_marker', 'page_convention', 'page_default', 'printed_explicit'], true)) {
            $sourceReligionProvenance += (int) $count;
        }
    }
    if ($counts['religion_provenance'] !== $sourceReligionProvenance) {
        $problems[] = "{$counts['religion_provenance']} confirmed religions carry official provenance (expected {$sourceReligionProvenance})";
    }
    $expectedUnconfirmedReligion = 0; // All 75 previously-unconfirmed rows were name-inferred in v4
    if ($counts['religion_unconfirmed'] !== $expectedUnconfirmedReligion) {
        $problems[] = "{$counts['religion_unconfirmed']} of {$expectedUnconfirmedReligion} unconfirmed religions remain";
    }
    if ($counts['second_language_provenance'] !== (int) ($rosterAudit['totals']['second_language_confirmed'] ?? 0)) {
        $problems[] = "{$counts['second_language_provenance']} confirmed second languages carry source evidence";
    }
    if ($counts['assessment_schedules'] !== 45) {
        $problems[] = "{$counts['assessment_schedules']} of 45 assessment rows exist";
    }
    if ($counts['timetables'] < 500) {
        $problems[] = "only {$counts['timetables']} timetable slots were loaded (expected 507)";
    }
    if ($problems !== []) {
        throw new RuntimeException('The official data is incomplete: ' . implode('; ', $problems) . '.');
    }

    $markerDir = dirname($markerPath);
    if (!is_dir($markerDir) && !mkdir($markerDir, 0770, true) && !is_dir($markerDir)) {
        throw new RuntimeException("Could not create the marker directory {$markerDir}.");
    }
    $marker = [
        'official_data_version' => $officialDataVersion,
        'seeded_at' => gmdate('c'),
        'academic_year' => (string) $year['label'],
        'source' => 'database/official',
        'files' => [
            'pathways' => basename($required['pathways']),
            'assessments' => basename($required['assessments']),
            'timetables' => basename($required['timetables']),
            'rosters' => count($rosterFiles),
            'roster_audit' => basename($required['roster_audit']),
        ],
        'expected_students' => $rosterRows,
        'counts' => $counts,
    ];
    $payload = json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('Could not encode the seed marker.');
    }
    $temp = $markerPath . '.tmp';
    if (file_put_contents($temp, $payload . "\n", LOCK_EX) === false || !rename($temp, $markerPath)) {
        throw new RuntimeException("Could not write the seed marker {$markerPath}.");
    }
    @chmod($markerPath, 0640);

    echo "\nOfficial data loaded and verified.\n";
    echo "Marker: {$markerPath}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nFATAL: " . $e->getMessage() . "\n");
    exit(1);
}

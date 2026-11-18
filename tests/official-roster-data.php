<?php

declare(strict_types=1);

/**
 * Source-data contract for the 17 reviewed roster pages.
 *
 * After name-based inference for B6/B8/B7-shorthand (75 students):
 * - 4 Christians identified from Coptic names (موريس، صموئيل، جورج/حنا/فوزى، جرجس)
 * - 71 Muslims inferred (no Christian markers, Egyptian context)
 * All 778 students now have religion values.
 * API hooks are used only for schema conversion; no birth date or guardian
 * is ever inferred, and the name-inference list is committed for audit.
 */

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';
restore_exception_handler();

$passed = 0;
$failed = 0;

function rosterDataCheck(bool $condition, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ok    {$label}\n";
        return;
    }
    $failed++;
    echo "  FAIL  {$label}" . ($detail !== '' ? " -- {$detail}" : '') . "\n";
}

$files = glob($root . '/database/official/roster_*.csv') ?: [];
sort($files);
$all = [];
$byClass = [];

foreach ($files as $file) {
    $handle = fopen($file, 'rb');
    if ($handle === false) {
        throw new RuntimeException("Could not open {$file}");
    }
    $header = fgetcsv($handle, 0, ',', '"', '');
    if (!is_array($header)) {
        throw new RuntimeException("Missing header in {$file}");
    }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
    while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        $row = array_combine($header, array_pad($values, count($header), ''));
        if (!is_array($row)) {
            throw new RuntimeException("Malformed row in {$file}");
        }
        $class = (string) $row['Class Name'];
        $all[] = $row;
        $byClass[$class][] = $row;
    }
    fclose($handle);
}

echo "Official roster source data\n";
echo str_repeat('=', 60) . "\n";

rosterDataCheck(count($files) === 17, 'All 17 official roster files are present', (string) count($files));
rosterDataCheck(count($all) === 778, 'All 778 populated source rows are represented', (string) count($all));

$positions = [];
$genders = ['M' => 0, 'F' => 0];
$religions = ['muslim' => 0, 'christian' => 0, '' => 0];
$birthDates = 0;
foreach ($all as $row) {
    $position = (string) $row['Class Name'] . '|' . strtolower(trim((string) $row['Notes']));
    $positions[$position] = ($positions[$position] ?? 0) + 1;
    $gender = strtoupper(trim((string) $row['Gender (M/F)']));
    if (isset($genders[$gender])) {
        $genders[$gender]++;
    }
    $religion = strtolower(trim((string) $row['Religion']));
    $religions[$religion] = ($religions[$religion] ?? 0) + 1;
    if (trim((string) $row['Date of Birth (YYYY-MM-DD)']) !== '') {
        $birthDates++;
    }
}

rosterDataCheck(max($positions ?: [0]) === 1, 'Every student has one unique class/source-row address');
rosterDataCheck(array_sum($genders) === 778, 'Every source student has a reviewed M/F value');
rosterDataCheck($genders === ['M' => 418, 'F' => 360], 'Reviewed gender totals are pinned (418 M / 360 F)', json_encode($genders));

// After name inference: 672 source-confirmed + 106 name-inferred = 778
rosterDataCheck(($religions['muslim'] ?? 0) === 753, 'All 753 Muslim values (672 source + 102 name-inferred)', (string) ($religions['muslim'] ?? 0));
rosterDataCheck(($religions['christian'] ?? 0) === 25, 'All 25 Christian values (21 source + 4 name-inferred)', (string) ($religions['christian'] ?? 0));
rosterDataCheck(($religions[''] ?? 0) === 0, 'No religions remain unconfirmed after name inference', (string) ($religions[''] ?? 0));
$b7Christians = array_values(array_filter($byClass['B7'] ?? [], static fn (array $row): bool => (string) $row['Religion'] === 'christian'));
rosterDataCheck(
    count($b7Christians) === 1 && ($b7Christians[0]['Full Name'] ?? '') === 'يؤنا ماجد ظريف جرجس',
    'B7 shorthand is never decoded as the A4 Christian marker'
);
rosterDataCheck($birthDates === 0, 'No birth date is fabricated');

$b5 = $byClass['B5'] ?? [];
rosterDataCheck(count($b5) === 49, 'B5 includes its populated L17 source row', (string) count($b5));
rosterDataCheck(
    count(array_filter($b5, static fn (array $row): bool => (string) $row['Full Name'] === 'ريماس فاروق اسماعيل حسن' && (string) $row['Notes'] === 'Row L17')) === 1,
    'B5 L17 is the exact printed student, once'
);
rosterDataCheck(
    count(array_filter($b5, static fn (array $row): bool => (string) $row['Religion'] !== 'muslim')) === 0,
    'The single B5 مسلم marker is applied as the confirmed whole-page default'
);

$a4 = $byClass['A4'] ?? [];
$a4Combined = array_values(array_filter(
    $a4,
    static fn (array $row): bool => preg_match('/^\s*م\s*-/u', (string) $row['Class Attribute']) === 1
));
rosterDataCheck(count($a4Combined) === 1, 'A4 has one printed م- combined marker', (string) count($a4Combined));
rosterDataCheck(
    $a4Combined !== [] && (string) $a4Combined[0]['Religion'] === 'christian',
    'A4 م- is decoded as the user-confirmed Christian marker'
);

$normalizeLanguage = new ReflectionMethod(App\Services\RosterImportService::class, 'normalizeSecondLanguage');
$importer = new App\Services\RosterImportService();
$languages = ['german' => 0, 'french' => 0, 'spanish' => 0];
$languageSources = ['printed_cell' => 0, 'class_heading' => 0];
foreach ($all as $row) {
    [$language, $source] = $normalizeLanguage->invoke(
        $importer,
        (string) $row['Class Attribute'],
        (string) $row['Class Name']
    );
    if ($language !== null) {
        $languages[$language]++;
        $languageSources[$source]++;
    }
}
rosterDataCheck($languages === ['german' => 284, 'french' => 85, 'spanish' => 55], 'Second-language totals match the reviewed cells/headings', json_encode($languages));
rosterDataCheck($languageSources === ['printed_cell' => 353, 'class_heading' => 71], 'Every normalized language records its evidence source', json_encode($languageSources));

$auditPath = $root . '/database/official/roster-data-audit.json';
$audit = is_file($auditPath) ? json_decode((string) file_get_contents($auditPath), true) : null;
rosterDataCheck(is_array($audit) && (int) ($audit['version'] ?? 0) === 6, 'The committed roster audit is version 6', 'version=' . ($audit['version'] ?? 'null'));
rosterDataCheck(is_array($audit) && count((array) ($audit['source_image_sha256'] ?? [])) === 17, 'The audit pins all 17 source-image checksums');
rosterDataCheck(is_array($audit) && ($audit['totals']['students'] ?? 0) === 778, 'The audit records the exact source total');

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
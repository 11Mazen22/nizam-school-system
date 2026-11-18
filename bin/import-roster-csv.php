<?php

declare(strict_types=1);

/**
 * Nizam -- import a roster CSV from the command line.
 *
 * Uses exactly the same RosterImportService path as the browser flow
 * (parse -> validate -> commit), so a file that imports here would also pass
 * the preview screen. Gender and religion are confirmed for every official
 * student by the reviewed enrichment; date of birth stays NULL because the
 * rosters never printed it.
 *
 * Usage: php bin/import-roster-csv.php database/official/roster_A1.csv [--dry-run]
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Services\RosterImportService;

$path = null;
$dryRun = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } else {
        $path = $arg;
    }
}
if ($path === null || !is_file($path)) {
    fwrite(STDERR, "Usage: php bin/import-roster-csv.php <file.csv> [--dry-run]\n");
    exit(1);
}

$pdo = Database::connection();
$year = $pdo->query("SELECT id, label, is_closed FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetch();
if ($year === false) {
    fwrite(STDERR, "No active academic year.\n");
    exit(1);
}
AcademicYearContext::set([
    'id' => (int) $year['id'],
    'label' => (string) $year['label'],
    'is_closed' => (int) $year['is_closed'],
]);

$service = new RosterImportService();
$parsed = $service->parse($path);
$officialDir = realpath(dirname(__DIR__) . '/database/official');
$realPath = realpath($path);
$officialReviewed = is_string($officialDir) && is_string($realPath)
    && str_starts_with($realPath, $officialDir . DIRECTORY_SEPARATOR);

// The official rosters do not print B6/B8 religions (their cells carry
// specializations). The reviewed enrichment documented which rows were
// name-inferred; feed that committed map to the validator so a re-seed
// reproduces the exact same provenance instead of mislabeling them.
$nameInference = [];
if ($officialReviewed) {
    $auditPath = dirname((string) $realPath) . '/roster-data-audit.json';
    $audit = is_file($auditPath) ? json_decode((string) file_get_contents($auditPath), true) : null;
    foreach ((array) ($audit['name_inference_students'] ?? []) as $entry) {
        $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) ($entry['full_name'] ?? ''))) ?? '')
            . '|' . strtoupper(trim((string) ($entry['class'] ?? '')));
        if ($key !== '|') {
            $nameInference[$key] = (string) ($entry['religion_source'] ?? 'name_inference_muslim');
        }
    }
}

$result = $service->validate($parsed, $officialReviewed, $nameInference);

echo basename($path) . ': ' . count($result['rows']) . " rows\n";
echo '  ready: ' . $result['counts']['ok']
    . ', already enrolled: ' . $result['counts']['duplicate']
    . ', error: ' . $result['counts']['error'] . "\n";
foreach ($result['rows'] as $row) {
    if ($row['status'] === 'error') {
        echo '  line ' . $row['line'] . ' ' . $row['name'] . ': ' . implode(', ', $row['messages']) . "\n";
    }
}
if ($result['counts']['error'] > 0) {
    fwrite(STDERR, "Aborted: fix the {$result['counts']['error']} invalid row(s) above.\n");
    exit(1);
}

if ($dryRun) {
    echo "  DRY RUN -- nothing written\n";
    exit(0);
}

$commit = $service->commit($result['rows']);
echo '  created: ' . $commit['created'] . ', updated: ' . $commit['updated'] . ', skipped: ' . $commit['skipped'] . "\n";
foreach ($commit['errors'] as $error) {
    echo '  ' . $error . "\n";
}
if ($commit['errors'] !== []) {
    fwrite(STDERR, 'Aborted: ' . count($commit['errors']) . " row(s) could not be committed.\n");
    exit(1);
}

<?php

declare(strict_types=1);

/**
 * Nizam -- expand the rosters' "printed once" attribute column.
 *
 * The school's roster pages print a repeated value once and leave the rows
 * below blank (ten Muslims: "مسلمة" on the first row, the rest blank; a
 * Christian: the one row that says "مسيحية"). Blank therefore means "same as
 * the row above", not "not recorded". This command applies that page rule to
 * every student in the active year, keeping the printed text verbatim and
 * recording whether the row printed the value or inherited it.
 *
 * Usage:
 *   php bin/expand-roster-attributes.php --all [--dry-run]
 *   php bin/expand-roster-attributes.php database/official/roster_A1.csv [--dry-run]
 *
 * A UTF-8 JSON report (per class, including any unmatched printed name) is
 * written to storage/logs/ so the result can be inspected without a terminal
 * that renders Arabic correctly.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Services\RosterAttributeService;

$dryRun = in_array('--dry-run', $argv, true);
$all = in_array('--all', $argv, true);
$paths = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--dry-run' && $arg !== '--all') {
        $paths[] = $arg;
    }
}
if ($all || $paths === []) {
    $paths = glob(dirname(__DIR__) . '/database/official/roster_*.csv') ?: [];
}
if ($paths === []) {
    fwrite(STDERR, "No roster CSV files found.\n");
    exit(1);
}

$pdo = Database::connection();
$year = $pdo->query('SELECT id, label FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();
if ($year === false) {
    fwrite(STDERR, "No active academic year.\n");
    exit(1);
}
$yearId = (int) $year['id'];

$service = new RosterAttributeService();
$report = [
    'academic_year' => (string) $year['label'],
    'generated_at' => date('c'),
    'dry_run' => $dryRun,
    'files' => [],
    'totals' => ['rows' => 0, 'printed' => 0, 'carried' => 0, 'updated' => 0, 'religion' => 0, 'unmatched' => 0],
];

foreach ($paths as $path) {
    $result = $service->apply($path, $yearId, $dryRun);
    $report['files'][] = ['file' => basename($path)] + $result;
    $report['totals']['rows'] += array_sum(array_column($result['classes'], 'rows'));
    $report['totals']['printed'] += array_sum(array_column($result['classes'], 'printed'));
    $report['totals']['carried'] += array_sum(array_column($result['classes'], 'carried'));
    $report['totals']['updated'] += $result['updated'];
    $report['totals']['religion'] += $result['religion'];
    $report['totals']['unmatched'] += $result['unmatched'];

    fwrite(STDOUT, sprintf(
        "%-22s rows=%-3d printed=%-3d carried=%-3d updated=%-3d religion=%-3d unmatched=%d\n",
        basename($path),
        array_sum(array_column($result['classes'], 'rows')),
        array_sum(array_column($result['classes'], 'printed')),
        array_sum(array_column($result['classes'], 'carried')),
        $result['updated'],
        $result['religion'],
        $result['unmatched']
    ));
}

$reportDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0777, true);
}
$reportPath = $reportDir . '/roster-expansion-' . date('Ymd-His') . '.json';
file_put_contents($reportPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

fwrite(STDOUT, sprintf(
    "\nTOTAL rows=%d printed=%d carried=%d updated=%d religion=%d unmatched=%d%s\n",
    $report['totals']['rows'],
    $report['totals']['printed'],
    $report['totals']['carried'],
    $report['totals']['updated'],
    $report['totals']['religion'],
    $report['totals']['unmatched'],
    $dryRun ? ' (DRY RUN)' : ''
));
fwrite(STDOUT, 'Report: ' . $reportPath . "\n");

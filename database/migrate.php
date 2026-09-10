<?php
/**
 * Nizam -- database migration runner CLI. Phase 3 scope originally; as of
 * Phase 5 this is a thin wrapper around App\Services\MigrationService, which
 * the Setup Wizard's "schema install" step also calls -- one implementation
 * instead of two. Behavior is unchanged from the original Phase 3 tool and
 * was re-verified (108/108) after this refactor.
 *
 * Usage:
 *   php database/migrate.php            Apply every pending migration.
 *   php database/migrate.php --status   Report applied/pending, apply nothing.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\MigrationService;

$root = dirname(__DIR__);
$configPath = $root . '/config/config.php';

if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config/config.php.\n");
    fwrite(STDERR, "Copy config/config.example.php to config/config.php and fill in real values first.\n");
    exit(1);
}

$config = require $configPath;

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['host'],
    $config['port'],
    $config['database'],
    $config['charset']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to the database: {$e->getMessage()}\n");
    fwrite(STDERR, "Check config/config.php and confirm MySQL/MariaDB is running.\n");
    exit(1);
}

$migrationsDir = __DIR__ . '/migrations';
$service = new MigrationService();

if (in_array('--status', $argv, true)) {
    $service->bootstrapMigrationsTable($pdo);
    $applied = $service->getAppliedMigrations($pdo);
    $files = glob($migrationsDir . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    echo "Nizam migration runner\n" . str_repeat('-', 40) . "\n";
    foreach ($files as $file) {
        $name = basename($file);
        $mark = in_array($name, $applied, true) ? '[applied]' : '[pending]';
        echo "  $mark $name\n";
    }
    exit(0);
}

$result = $service->run($pdo, $migrationsDir);

echo "Nizam migration runner\n" . str_repeat('-', 40) . "\n";
echo count($result['alreadyApplied']) . " already applied, " . (count($result['applied']) + ($result['failure'] ? 1 : 0)) . " attempted this run.\n\n";

foreach ($result['applied'] as $name) {
    echo "Applying $name ... OK\n";
}

if ($result['failure'] !== null) {
    $f = $result['failure'];
    echo "Applying {$f['migration']} ... FAILED\n\n";
    fwrite(STDERR, "Migration {$f['migration']} failed:\n  {$f['statement']}\n");
    fwrite(STDERR, "Database error: {$f['error']}\n\n");
    fwrite(STDERR, "Stopped. No further migrations were attempted. This migration was NOT recorded\n");
    fwrite(STDERR, "as applied. If any earlier statement in this same file already ran (CREATE TABLE,\n");
    fwrite(STDERR, "etc.), it has already taken effect -- MySQL/MariaDB auto-commits DDL and there was\n");
    fwrite(STDERR, "never a transaction actually protecting it.\n");
    fwrite(STDERR, "Inspect the database, fix the migration file, and rerun.\n");
    exit(1);
}

if (empty($result['applied'])) {
    echo "Nothing to do -- database is already up to date.\n";
} else {
    echo "\nAll pending migrations applied successfully.\n";
}
exit(0);

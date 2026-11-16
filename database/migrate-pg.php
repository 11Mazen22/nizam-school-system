<?php

declare(strict_types=1);

/**
 * Nizam -- Postgres (online) migration + seed runner CLI.
 *
 * The offline world has database/migrate.php (MySQL) and database/seed.php.
 * The online world had NO equivalent: packaging-online/README.md step 1 told
 * the operator to apply database/migrations-pg/*.sql by hand, "via
 * App\Services\MigrationService ... point it at database/migrations-pg and a
 * session-mode PDO connection instead" -- a manual procedure repeated on
 * every fresh deployment, easy to run against the wrong database, and never
 * once verified by the test suite (the offline path's own tests cover the
 * MySQL twin only). This file is that pointer, as an actual runner:
 *
 *   php database/migrate-pg.php            Apply pending migrations, then seeds.
 *   php database/migrate-pg.php --status   Report applied/pending, apply nothing.
 *
 * packaging-online/entrypoint.sh calls it on every container start, so a
 * fresh Railway deployment provisions its own schema exactly the same way
 * every time -- the same "provisioning writes the database state" pattern
 * the Windows installer uses for its MySQL path, and the reason
 * SetupStatusService::currentStep() skips the schema step for pgsql.
 *
 * Behavior is unchanged from migrate.php's own contract: pending migrations
 * are tracked by filename, a failure stops the run and exits non-zero, and
 * seeds re-run every time (idempotent per-file SQL, SeedService's contract).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\MigrationService;
use App\Services\SeedService;

$root = dirname(__DIR__);
$configPath = dataPath() . '/config/config.php';

if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config/config.php.\n");
    fwrite(STDERR, "On the online deployment this is written by packaging-online/entrypoint.sh; locally, copy config/config.example.php.\n");
    exit(1);
}

$config = require $configPath;

if (($config['driver'] ?? 'mysql') !== 'pgsql') {
    fwrite(STDERR, "This runner is for the pgsql (online) deployment. Use database/migrate.php for the MySQL (offline) one.\n");
    exit(1);
}

$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s',
    $config['host'],
    (int) $config['port'],
    $config['database']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to the database: {$e->getMessage()}\n");
    exit(1);
}

// More than one replica may boot from the same release at once. Serialize
// schema/seed work at the database itself rather than hoping Railway starts
// containers one by one. The session-level lock is released automatically
// when this short-lived CLI process exits, including on a fatal error.
$pdo->query("SELECT pg_advisory_lock(hashtext('nizam-schema-migrations'))");

$service = new MigrationService();
$migrationsDir = __DIR__ . '/migrations-pg';

if (in_array('--status', $argv, true)) {
    $service->bootstrapMigrationsTable($pdo);
    $applied = $service->getAppliedMigrations($pdo);
    $files = glob($migrationsDir . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    echo "Nizam Postgres migration runner\n" . str_repeat('-', 40) . "\n";
    foreach ($files as $file) {
        $name = basename($file);
        $mark = in_array($name, $applied, true) ? '[applied]' : '[pending]';
        echo "  $mark $name\n";
    }
    exit(0);
}

$result = $service->run($pdo, $migrationsDir);

echo "Nizam Postgres migration runner\n" . str_repeat('-', 40) . "\n";
echo count($result['alreadyApplied']) . " already applied, " . (count($result['applied']) + ($result['failure'] ? 1 : 0)) . " attempted this run.\n\n";

foreach ($result['applied'] as $name) {
    echo "Applying $name ... OK\n";
}

if ($result['failure'] !== null) {
    $failure = $result['failure'];
    fwrite(STDERR, "Applying {$failure['migration']} ... FAILED\n\n");
    fwrite(STDERR, "Migration {$failure['migration']} failed:\n  {$failure['statement']}\n");
    fwrite(STDERR, "Database error: {$failure['error']}\n\n");
    fwrite(STDERR, "Stopped. This migration was NOT recorded as applied; the attempt rolled back.\n");
    exit(1);
}

// Migration 010 creates the least-privilege runtime role with a deliberately
// unusable placeholder. Rotate it to the deployment's generated secret every
// boot, using the administrator connection that just applied the schema.
// The role name is intentionally fixed, never interpolated from arbitrary
// input; DB_USERNAME is the username written into the app's runtime config.
$runtimeUsername = getenv('DB_USERNAME');
$runtimePassword = getenv('DB_PASSWORD');
if (!is_string($runtimeUsername) || $runtimeUsername !== 'nizam_app'
    || !is_string($runtimePassword) || $runtimePassword === '') {
    fwrite(STDERR, "DB_USERNAME must be nizam_app and DB_PASSWORD must be a non-empty generated secret.\n");
    exit(1);
}
$pdo->exec('ALTER ROLE nizam_app WITH LOGIN PASSWORD ' . $pdo->quote($runtimePassword));
echo "Runtime database role password synchronized.\n";

if (empty($result['applied'])) {
    echo "Nothing to do -- database schema is already up to date.\n";
} else {
    echo "\nAll pending migrations applied successfully.\n";
}

// Seeds run every time, by their own contract: idempotency comes from each
// file's SQL (ON CONFLICT DO NOTHING / DO UPDATE), never from a bookkeeping
// table -- so they also repair reference data (roles, permissions, settings
// rows) a partial restore or a deleted row may have damaged.
$seeds = (new SeedService())->run($pdo, __DIR__ . '/seeds-pg');

foreach ($seeds['ran'] as $name) {
    echo "Seed $name ... OK\n";
}

if ($seeds['failure'] !== null) {
    $failure = $seeds['failure'];
    fwrite(STDERR, "Seed {$failure['seed']} ... FAILED\n\n");
    fwrite(STDERR, "Seed {$failure['seed']} failed:\n  {$failure['statement']}\n");
    fwrite(STDERR, "Database error: {$failure['error']}\n");
    fwrite(STDERR, "Reference data is not up to date. Fix the seed file and re-run; schema migrations are unaffected.\n");
    exit(1);
}

if (!empty($seeds['ran'])) {
    echo "\nSeeds applied.\n";
}

exit(0);

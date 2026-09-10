<?php
/**
 * Nizam -- database seed runner CLI. As of Phase 5, a thin wrapper around
 * App\Services\SeedService (also used by the Setup Wizard's schema-install
 * step). Behavior unchanged from the original Phase 3 tool, re-verified
 * (108/108) after this refactor.
 *
 * Usage: php database/seed.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\SeedService;

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
    exit(1);
}

$seedsDir = __DIR__ . '/seeds';
$result = (new SeedService())->run($pdo, $seedsDir);

echo "Nizam seed runner\n" . str_repeat('-', 40) . "\n";

foreach ($result['ran'] as $name) {
    echo "Seeding $name ... OK\n";
}

if ($result['failure'] !== null) {
    $f = $result['failure'];
    echo "Seeding {$f['seed']} ... FAILED\n\n";
    fwrite(STDERR, "Seed {$f['seed']} failed:\n  {$f['statement']}\n");
    fwrite(STDERR, "Database error: {$f['error']}\n\n");
    fwrite(STDERR, "Stopped. No further seed files were attempted.\n");
    exit(1);
}

echo "\nAll seed files ran successfully.\n";
exit(0);

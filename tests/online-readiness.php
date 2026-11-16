<?php

declare(strict_types=1);

/**
 * Static deployment-contract checks.
 *
 * These deliberately do not need a Railway account or a live Postgres. They
 * catch the exact class of drift that previously made the cloud path look
 * documented while remaining undeployable: a new table with no nizam_app
 * RLS policy, Supabase made mandatory for a Railway-native deployment, a
 * mutable base-image tag, or the online migration runner disappearing.
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function onlineCheck(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ok    {$label}\n";
        return;
    }
    $failed++;
    echo "  FAIL  {$label}\n";
}

require $root . '/app/bootstrap.php';

echo "Online production-readiness contract\n";
echo str_repeat('=', 60) . "\n";

$migrationFiles = glob($root . '/database/migrations-pg/*.sql') ?: [];
sort($migrationFiles, SORT_STRING);
onlineCheck(count($migrationFiles) >= 28, 'Postgres migration set is present');

$allMigrationSql = '';
$createdTables = [];
foreach ($migrationFiles as $file) {
    $sql = (string) file_get_contents($file);
    $allMigrationSql .= "\n" . $sql;
    $statements = App\Services\MigrationService::splitStatements($sql);
    onlineCheck($statements !== [], basename($file) . ' splits into executable statements');
    if (preg_match_all('/^CREATE TABLE(?: IF NOT EXISTS)? ([a-z_]+)/mi', $sql, $matches)) {
        foreach ($matches[1] as $table) {
            $createdTables[$table] = true;
        }
    }
}

onlineCheck(
    preg_match('/FROM roles r JOIN permissions p(?:\s|$)/i', $allMigrationSql) !== 1,
    'Postgres role templates use explicit CROSS JOIN syntax'
);

unset($createdTables['migrations']); // intentionally admin-only bookkeeping
foreach (array_keys($createdTables) as $table) {
    $quoted = preg_quote($table, '/');
    onlineCheck(
        preg_match('/ALTER TABLE public\.' . $quoted . ' ENABLE ROW LEVEL SECURITY/i', $allMigrationSql) === 1,
        "{$table} enables RLS"
    );
    onlineCheck(
        preg_match('/CREATE POLICY nizam_app_full_access ON public\.' . $quoted . '/i', $allMigrationSql) === 1,
        "{$table} grants the nizam_app RLS policy"
    );
}

$entrypoint = (string) file_get_contents($root . '/packaging-online/entrypoint.sh');
onlineCheck(str_contains($entrypoint, 'database/migrate-pg.php'), 'Container applies Postgres migrations on start');
onlineCheck(str_contains($entrypoint, 'DB_ADMIN_USERNAME'), 'Migration and runtime DB identities are separate');
onlineCheck(str_contains($entrypoint, 'NIZAM_DATA_PATH'), 'Container uses the persistent data root');
onlineCheck(!preg_match('/required_vars=.*SUPABASE/', $entrypoint), 'Supabase is optional, not a startup requirement');

$dockerfile = (string) file_get_contents($root . '/packaging-online/Dockerfile');
$fromLines = preg_grep('/^FROM /', preg_split('/\R/', $dockerfile) ?: []) ?: [];
$externalFromLines = array_values(array_filter(
    $fromLines,
    static fn (string $line): bool => preg_match('/^FROM (?:php-base|composer-bin)(?:\s|$)/', $line) !== 1
));
onlineCheck(
    count($externalFromLines) === 2
        && count(array_filter($externalFromLines, static fn (string $line): bool => str_contains($line, '@sha256:'))) === 2,
    'Every external container base is digest-pinned'
);
onlineCheck(str_contains($dockerfile, 'composer install'), 'Online vendor tree is rebuilt from composer.lock');
onlineCheck(str_contains($dockerfile, 'opcache'), 'Production image enables OPcache');

$uploadService = (string) file_get_contents($root . '/app/services/UploadService.php');
onlineCheck(str_contains($uploadService, 'SupabaseStorageClient::configured()'), 'Upload storage is selected by storage configuration');
onlineCheck(!str_contains($uploadService, 'isPgsql()'), 'Upload storage is not coupled to the DB driver');

$backupService = (string) file_get_contents($root . '/app/services/BackupService.php');
onlineCheck(str_contains($backupService, 'FROM pg_tables'), 'Postgres backups discover the live table catalog');
onlineCheck(str_contains($backupService, "'admin_recovery_tokens'"), 'Backups exclude short-lived recovery credentials');

$configExample = (string) file_get_contents($root . '/config/config.example.php');
onlineCheck(
    preg_match("/'password'\\s*=>\\s*'[^']{6,}'/", $configExample) !== 1,
    'Example configuration contains no credential-like password literal'
);

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);

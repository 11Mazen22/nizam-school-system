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

onlineCheck(
    dbBool(true) && dbBool(1) && dbBool('1') && dbBool('t') && dbBool('true')
        && !dbBool(false) && !dbBool(0) && !dbBool('0') && !dbBool('f') && !dbBool(null),
    'Database boolean normalization covers MySQL and PostgreSQL representations'
);

$runtimeSqlFiles = [
    $root . '/app/controllers/PortalController.php',
    $root . '/app/services/DataQualityService.php',
    $root . '/app/repositories/FinanceRepository.php',
    $root . '/app/repositories/GuardianRepository.php',
    $root . '/app/repositories/TransportRepository.php',
];
$runtimeSql = implode("\n", array_map(static fn (string $file): string => (string) file_get_contents($file), $runtimeSqlFiles));
onlineCheck(
    preg_match('/\bstatus\s*(?:=|!=)\s*"[a-z_]+"/i', $runtimeSql) !== 1,
    'Cross-database runtime SQL uses standard single-quoted text literals'
);
onlineCheck(
    !str_contains((string) file_get_contents($root . '/app/repositories/ModuleRepository.php'), 'is_core = 0'),
    'Module updates use native-boolean SQL literals'
);

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

$seedFiles = glob($root . '/database/seeds-pg/*.sql') ?: [];
sort($seedFiles, SORT_STRING);
$allSeedSql = '';
foreach ($seedFiles as $file) {
    $sql = (string) file_get_contents($file);
    $allSeedSql .= "\n" . $sql;
    onlineCheck(
        App\Services\MigrationService::splitStatements($sql) !== [],
        basename($file) . ' splits into executable seed statements'
    );
}
onlineCheck(
    preg_match('/\b(?:INSERT\s+IGNORE|ON\s+DUPLICATE\s+KEY|REPLACE\s+INTO)\b|`/i', $allSeedSql) !== 1,
    'Postgres seeds contain no MySQL-only write syntax'
);
onlineCheck(
    preg_match('/academic_years[\s\S]*?is_active\s*=\s*(?:true|false)/i', $allSeedSql) !== 1,
    'Academic-year seeds honor the PostgreSQL SMALLINT flag contract'
);
onlineCheck(
    str_contains($allSeedSql, "WHERE r.code = 'admin'\nON CONFLICT (role_id, permission_id) DO NOTHING"),
    'Fresh Postgres administrators receive the complete permission catalogue'
);
onlineCheck(
    is_file($root . '/database/seeds-pg/008_role_template_permissions.sql'),
    'Role templates are re-applied after the full fresh-install catalogue exists'
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
onlineCheck(str_contains($entrypoint, 'bin/production-smoke.php'), 'Container runs live PostgreSQL smoke checks before Apache');
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

$apacheConfig = (string) file_get_contents($root . '/packaging-online/apache-nizam.conf');
onlineCheck(
    str_contains($apacheConfig, 'Header onsuccess unset X-Content-Type-Options'),
    'Online Apache normalizes dynamic/static security headers without duplicates'
);

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

// The readiness probe runs as the least-privilege runtime role. It must never
// issue DDL -- pendingCount() bootstraps the table it counts, which turned a
// healthy production database into a 503 until this was separated.
$frontController = (string) file_get_contents($root . '/public/index.php');
onlineCheck(
    str_contains($frontController, 'pendingCountReadOnly')
        && !str_contains($frontController, '->pendingCount('),
    'The readiness probe checks the schema without needing CREATE privileges'
);

// The official school package must ship with the cloud image, and the
// entrypoint must load it. Without these checks a build can be green while
// the live site comes up as an empty shell with different data from offline.
$officialDir = $root . '/database/official';
$officialRosters = glob($officialDir . '/roster_*.csv') ?: [];
$pathwaysJson = json_decode((string) @file_get_contents($officialDir . '/pathways.json'), true);
$timetablesJson = json_decode((string) @file_get_contents($officialDir . '/timetables_17.json'), true);
$assessmentsJson = json_decode((string) @file_get_contents($officialDir . '/weekly_assessments_A1_A9.json'), true);
$rosterAuditJson = json_decode((string) @file_get_contents($officialDir . '/roster-data-audit.json'), true);
$assessmentRows = 0;
foreach (($assessmentsJson['classes'] ?? []) as $rows) {
    $assessmentRows += count((array) $rows);
}
onlineCheck(
    is_array($pathwaysJson) && count((array) ($pathwaysJson['classes'] ?? [])) === 17,
    'Official package ships the 17 class pathway labels'
);
onlineCheck(count($officialRosters) === 17, 'Official package ships all 17 student rosters');
onlineCheck(
    is_array($rosterAuditJson)
        && (int) ($rosterAuditJson['version'] ?? 0) === 4
        && (int) ($rosterAuditJson['totals']['students'] ?? 0) === 778
        && (int) ($rosterAuditJson['totals']['religion_confirmed'] ?? 0) === 778
        && (int) ($rosterAuditJson['totals']['gender_confirmed'] ?? 0) === 778,
    'Official package ships the versioned 778-row roster enrichment audit'
);
onlineCheck(
    is_array($timetablesJson) && count((array) ($timetablesJson['classes'] ?? [])) === 17,
    'Official package ships all 17 class timetables'
);
onlineCheck($assessmentRows === 45, 'Official package ships the 45 weekly assessment rows');

$dockerIgnore = (string) file_get_contents($root . '/.dockerignore');
$officialDataExcluded = false;
foreach (preg_split('/\R/', $dockerIgnore) ?: [] as $ignoreLine) {
    $ignoreLine = trim($ignoreLine);
    if (in_array($ignoreLine, [
        'database', 'database/', 'database/**',
        'database/official', 'database/official/', 'database/official/**',
        '**/database', '**/database/official',
    ], true)) {
        $officialDataExcluded = true;
    }
}
onlineCheck(
    !$officialDataExcluded,
    'The official data directory is not excluded from the cloud image'
);
onlineCheck(
    str_contains($entrypoint, 'NIZAM_SEED_OFFICIAL_DATA')
        && str_contains($entrypoint, 'bin/seed-official-data.php'),
    'Container can seed the official school data on first boot'
);
onlineCheck(
    str_contains($entrypoint, '$DATA_PATH/storage/official-data-seed.json'),
    'Official seed marker lives on the persistent volume'
);
onlineCheck(
    str_contains($entrypoint, "disable_functions=exec,shell_exec,system,proc_open,popen")
        && str_contains(
            (string) file_get_contents($root . '/packaging-online/php-production.ini'),
            'disable_functions = exec,passthru,shell_exec,system,proc_open,popen'
        ),
    'Only the trusted seed CLI receives passthru; Apache keeps process spawning disabled'
);
onlineCheck(
    strpos($entrypoint, 'bin/seed-official-data.php') !== false
        && strpos($entrypoint, 'bin/seed-official-data.php') < strpos($entrypoint, 'bin/production-smoke.php'),
    'Official data is seeded before the production smoke check'
);

$seedRunner = (string) file_get_contents($root . '/bin/seed-official-data.php');
onlineCheck(
    str_contains($seedRunner, "'2026/2027'") && str_contains($seedRunner, 'official-data-seed.json'),
    'The seed runner pins the official year and writes its completion marker'
);
$timetableImporter = (string) file_get_contents($root . '/bin/import-timetables.php');
onlineCheck(
    str_contains($timetableImporter, 'SAVEPOINT timetable_slot'),
    'Timetable import keeps one rejected slot from aborting a PostgreSQL transaction'
);

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);

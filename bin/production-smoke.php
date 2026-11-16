<?php

declare(strict_types=1);

/**
 * Read-only production boot smoke test.
 *
 * Unit/HTTP CI runs against disposable MySQL; this script executes the
 * production query surface against the actual PostgreSQL schema with the
 * least-privilege runtime role. entrypoint.sh runs it before Apache starts,
 * so Railway never promotes a release whose dashboard or core modules would
 * immediately return 500. It performs no writes and prints no credentials.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Repositories\AcademicConfigRepository;
use App\Repositories\GuardianRepository;
use App\Repositories\ModuleRepository;
use App\Repositories\TransportRepository;
use App\Services\BackupService;
use App\Services\DashboardStatsService;

try {
    $pdo = Database::connection();
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
        throw new RuntimeException('production smoke requires pgsql');
    }
    if ((string) $pdo->query('SELECT current_user')->fetchColumn() !== 'nizam_app') {
        throw new RuntimeException('unexpected runtime database role');
    }

    $migrationCount = (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
    $expectedMigrations = count(glob(dirname(__DIR__) . '/database/migrations-pg/*.sql') ?: []);
    if ($migrationCount !== $expectedMigrations) {
        throw new RuntimeException("migration count mismatch ({$migrationCount}/{$expectedMigrations})");
    }

    // Simulate an administrator's read permissions so DashboardStatsService
    // takes every enabled branch (finance, admissions, HR, quality, etc.).
    $_SESSION['role_code'] = 'admin';
    $_SESSION['user_id'] = 0;
    $_SESSION['permissions'] = $pdo->query('SELECT code FROM permissions ORDER BY code')->fetchAll(PDO::FETCH_COLUMN);

    $activeYear = $pdo->query('SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($activeYear === false) {
        throw new RuntimeException('no active academic year after seeding');
    }
    AcademicYearContext::set($activeYear);

    (new DashboardStatsService())->build();

    $academic = new AcademicConfigRepository();
    $academic->stages();
    $academic->terms();
    $academic->scales();

    (new ModuleRepository())->all();
    (new GuardianRepository())->getStudentsForGuardian(0);
    (new TransportRepository())->allRoutes();

    $tables = BackupService::orderedTables($pdo);
    if (count($tables) < 40) {
        throw new RuntimeException('backup table discovery returned an incomplete catalogue');
    }

    echo 'Production PostgreSQL smoke checks passed (' . count($tables) . " durable tables).\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FATAL: production PostgreSQL smoke failed: ' . $error->getMessage() . "\n");
    exit(1);
}

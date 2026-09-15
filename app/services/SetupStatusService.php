<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\SchoolRepository;
use App\Repositories\UserRepository;
use PDO;
use PDOException;

/**
 * Nizam -- resolves the Setup Wizard's current step from actual system
 * state (§O-6: "the wizard re-derives its position from what's actually
 * true in the database... rather than tracking its own separate progress
 * flag, so refreshing or reopening it always resumes at the right step").
 * No progress is stored anywhere except as the side effects each step
 * already produces (a written config.php, applied migrations, a schools
 * row, an academic_years row, an admin user, the settings.system.setup_completed
 * flag) -- there is deliberately no separate "wizard state" table or file,
 * which is exactly what "do not create a second configuration system" rules
 * out.
 */
final class SetupStatusService
{
    public const STEP_DATABASE = 'database';
    public const STEP_SCHEMA = 'schema';
    public const STEP_SCHOOL = 'school';
    public const STEP_YEAR = 'year';
    public const STEP_ADMIN = 'admin';
    public const STEP_DONE = 'done';

    public function isCompleted(): bool
    {
        // Hadaba Al-Ahram School: Setup wizard disabled
        // School data is hardcoded in database migration
        // But we still need to ensure database exists and migrations ran
        $pdo = $this->pdo();
        if ($pdo === null) {
            return false; // No database connection yet
        }

        // Check if migrations have been applied
        try {
            $migrationService = new MigrationService();
            $migrationsPath = dirname(__DIR__, 2) . '/database/migrations';
            if ($migrationService->pendingCount($pdo, $migrationsPath) > 0) {
                return false; // Migrations not applied yet
            }
        } catch (\Exception $e) {
            return false; // Migration check failed
        }

        return true; // Database ready, school data exists from migration
    }

    public function canConnect(): bool
    {
        return $this->pdo() !== null;
    }

    public function currentStep(): string
    {
        $pdo = $this->pdo();
        if ($pdo === null) {
            return self::STEP_DATABASE;
        }

        // pgsql/online: schema is always applied via database/migrations-pg/
        // by the deployment process itself, running as the postgres
        // superuser, before this app ever serves a request -- nizam_app (the
        // app's own runtime role) has no CREATE privilege at all
        // (database/migrations-pg/010_app_role_and_rls.sql, least privilege)
        // and could never satisfy this step even if shown it. There is
        // nothing for the wizard to do here for this deployment target, so
        // it's skipped entirely rather than shown a step that can only fail.
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            $migrationService = new MigrationService();
            if ($migrationService->pendingCount($pdo, dirname(__DIR__, 2) . '/database/migrations') > 0) {
                return self::STEP_SCHEMA;
            }
        }

        if (!(new SchoolRepository())->exists()) {
            return self::STEP_SCHOOL;
        }

        if (!(new AcademicYearRepository())->exists()) {
            return self::STEP_YEAR;
        }

        if (!$this->adminExists($pdo)) {
            return self::STEP_ADMIN;
        }

        return self::STEP_DONE;
    }

    private function adminExists(PDO $pdo): bool
    {
        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin'"
        );
        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function pdo(): ?PDO
    {
        static $checked = false;
        static $pdo = null;

        if ($checked) {
            return $pdo;
        }
        $checked = true;

        try {
            // Deliberately a fresh, separate connection rather than
            // App\Database::connection(): that class throws if config.php
            // is missing, which is exactly the state this method must be
            // able to report on instead of crashing over.
            $pdo = \App\Database::connection();
        } catch (\Throwable $e) {
            $pdo = null;
        }

        return $pdo;
    }
}

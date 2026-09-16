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

    /**
     * Delegates to currentStep() rather than duplicating its logic: an
     * earlier version of this method re-implemented the "any migrations
     * pending?" check by itself, always against database/migrations/ (the
     * MySQL folder) regardless of driver. On the pgsql/online deployment
     * that check can never pass -- Postgres schema is tracked entirely
     * under database/migrations-pg/ by the deployment process itself
     * (currentStep()'s own comment explains why the pgsql branch skips
     * this check entirely) -- so isCompleted() was permanently returning
     * false in production, and SetupGateMiddleware (which calls only this
     * method, not currentStep()) redirected every single request, from
     * every visitor, authenticated or not, back to the setup wizard.
     * Found live: /login on production bounced to the "setup complete"
     * confirmation screen instead of the login form.
     */
    public function isCompleted(): bool
    {
        return $this->currentStep() === self::STEP_DONE;
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

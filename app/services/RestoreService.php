<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Nizam -- §I.3 restore engine (§Q "RestoreService | validate, execute").
 * validate() is pure file+schema validation (no writes); execute() is the
 * actual guarded replay. Split the same way the contract names them, so a
 * caller can validate a file (e.g. to preview/confirm) without it having
 * any side effect.
 */
final class RestoreService
{
    public function __construct(
        private readonly BackupService $backups = new BackupService(),
    ) {
    }

    /**
     * §I.3 step 1, entirely before any database work: the file's own
     * signature/checksum (delegated to BackupService::verify(), which
     * knows the file format) plus the one check that needs a live
     * connection -- the backup's declared schema version must match what's
     * actually applied here right now (§O-4: "so a restore can detect a
     * version mismatch instead of silently applying an incompatible
     * file"). A newer or older schema is refused outright rather than
     * attempted, since there is no supported upgrade/downgrade path.
     *
     * @return array{sql: string, schemaVersion: int}
     */
    public function validate(string $filepath): array
    {
        $result = $this->backups->verify($filepath);

        $pdo = Database::connection();
        $currentSchema = count((new MigrationService())->getAppliedMigrations($pdo));
        if ($result['schemaVersion'] !== $currentSchema) {
            throw new RuntimeException('schema_version_mismatch');
        }

        return $result;
    }

    /**
     * §I.3: validate -> mandatory pre-restore emergency backup -> replay
     * with FOREIGN_KEY_CHECKS suspended -> structural sanity check ->
     * activity log. There is deliberately no surrounding PDO transaction --
     * see the blueprint's own risk-note: DDL auto-commits in MySQL/MariaDB,
     * so the emergency backup from step 2 is the actual safety net, not a
     * ROLLBACK this replay could never fully honor anyway (§O-5).
     */
    public function execute(string $filepath, ?int $userId): void
    {
        $validated = $this->validate($filepath);

        // The pre-restore backup's filename is captured here, in memory,
        // and threaded through the failure path below -- not re-looked-up
        // from the backups table afterward. Found live: the replay a few
        // lines down can itself overwrite the backups table with an older
        // snapshot that predates this very row, so a post-replay lookup by
        // id can come back empty at exactly the moment it matters most. The
        // .sql file on disk is never touched by a restore, so the filename
        // captured now stays valid regardless of what happens to the table.
        $preRestore = $this->backups->run('pre_restore', $userId, 'Automatic emergency backup before restore');

        $pdo = Database::connection();
        $originalFkChecks = (int) $pdo->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn();

        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdo->exec($validated['sql']);
            $this->runStructuralChecks($pdo);

            ActivityLogger::log('backup.restore', 'backups', $preRestore['id'], 'Database restored from uploaded backup');
        } catch (Throwable $e) {
            error_log('[Nizam] Restore failed: ' . $e->getMessage());
            ActivityLogger::log('backup.restore_failed', 'backups', $preRestore['id'], 'Restore failed -- see storage/logs for detail');

            throw new RuntimeException('restore_failed:' . $preRestore['filename']);
        } finally {
            $pdo->exec("SET FOREIGN_KEY_CHECKS={$originalFkChecks}");
        }
    }

    /**
     * §O-5 base checks (at least one active Administrator, at least one
     * academic year) plus §S-9's extension: FOREIGN_KEY_CHECKS=0 means
     * MySQL/MariaDB never retroactively validates rows once checks are
     * re-enabled, so a corrupted dump could otherwise import silently --
     * spot-check the same composite-FK invariants §S-1/§S-2 enforce live.
     * Any failure here treats the restore as failed even though the SQL
     * itself executed without a database error (§I.3 step 5).
     */
    private function runStructuralChecks(PDO $pdo): void
    {
        $hasAdmin = (bool) $pdo->query("
            SELECT 1 FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE r.code = 'admin' AND u.is_active = 1
            LIMIT 1
        ")->fetchColumn();
        if (!$hasAdmin) {
            throw new RuntimeException('structural_no_admin');
        }

        $hasYear = (bool) $pdo->query('SELECT 1 FROM academic_years LIMIT 1')->fetchColumn();
        if (!$hasYear) {
            throw new RuntimeException('structural_no_year');
        }

        $badEnrollment = $pdo->query('
            SELECT 1 FROM student_enrollments e
            JOIN classes c ON c.id = e.class_id
            WHERE e.grade_id != c.grade_id OR e.academic_year_id != c.academic_year_id
            LIMIT 1
        ')->fetchColumn();
        if ($badEnrollment) {
            throw new RuntimeException('structural_bad_enrollment');
        }

        $badAssignment = $pdo->query('
            SELECT 1 FROM teacher_assignments a
            JOIN classes c ON c.id = a.class_id
            WHERE a.academic_year_id != c.academic_year_id
            LIMIT 1
        ')->fetchColumn();
        if ($badAssignment) {
            throw new RuntimeException('structural_bad_assignment');
        }
    }
}

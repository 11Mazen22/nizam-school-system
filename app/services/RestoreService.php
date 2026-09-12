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
     * HADABA AL-AHRAM ENHANCEMENT: Auto-converts between PostgreSQL and MySQL
     * formats so production backups work on local installs and vice versa.
     * Schema version check is skipped for cross-database restores since they
     * have different migration counts.
     *
     * @return array{sql: string, schemaVersion: int}
     */
    public function validate(string $filepath): array
    {
        $result = $this->backups->verify($filepath);

        // Auto-convert between database types if needed
        $sourceDb = SqlDialectConverter::detectSource($result['sql']);
        $pdo = Database::connection();
        $targetDb = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME); // 'mysql' or 'pgsql'
        
        if ($sourceDb && $sourceDb !== $targetDb) {
            $result['sql'] = SqlDialectConverter::convert($result['sql'], $sourceDb, $targetDb);
            // Skip schema version check for cross-database restores
            // (PostgreSQL and MySQL have different migration counts)
        } else {
            // Same database type: enforce schema version match
            $currentSchema = count((new MigrationService())->getAppliedMigrations($pdo));
            if ($result['schemaVersion'] !== $currentSchema) {
                throw new RuntimeException('schema_version_mismatch');
            }
        }

        return $result;
    }

    /**
     * §I.3: validate -> mandatory pre-restore emergency backup -> replay ->
     * structural sanity check -> activity log.
     *
     * mysql: replay with FOREIGN_KEY_CHECKS suspended, deliberately no
     * surrounding PDO transaction -- see the blueprint's own risk-note: DDL
     * auto-commits in MySQL/MariaDB, so the emergency backup from step 2 is
     * the actual safety net, not a ROLLBACK this replay could never fully
     * honor anyway (§O-5). Unchanged from the original implementation.
     *
     * pgsql: see executePgsql() -- a genuinely safer replay is possible
     * here (no DDL at all, nizam_app's data-only privileges are naturally
     * transaction-friendly), so it gets a real rollback-on-failure instead
     * of relying solely on the pre-restore backup.
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
        // backup file itself is never touched by a restore, so the filename
        // captured now stays valid regardless of what happens to the table.
        $preRestore = $this->backups->run('pre_restore', $userId, 'Automatic emergency backup before restore');

        $pdo = Database::connection();
        $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';

        try {
            if ($isPgsql) {
                $this->executePgsql($pdo, $validated['sql']);
            } else {
                $originalFkChecks = (int) $pdo->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn();
                try {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                    $pdo->exec($validated['sql']);
                } finally {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS={$originalFkChecks}");
                }
                $this->runStructuralChecks($pdo);
            }

            ActivityLogger::log('backup.restore', 'backups', $preRestore['id'], 'Database restored from uploaded backup');
        } catch (Throwable $e) {
            error_log('[Hadaba] Restore failed: ' . $e->getMessage());
            ActivityLogger::log('backup.restore_failed', 'backups', $preRestore['id'], 'Restore failed -- see storage/logs for detail');

            throw new RuntimeException('restore_failed:' . $preRestore['filename']);
        }
    }

    /**
     * pgsql replay: no FOREIGN_KEY_CHECKS session variable exists in
     * Postgres, and nizam_app has no privilege to ALTER TABLE ... DISABLE
     * TRIGGER ALL even if that were the equivalent (least-privilege, no
     * ALTER grant at all -- database/migrations-pg/010_app_role_and_rls.sql).
     * Instead: delete every table's rows in reverse dependency order
     * (children before parents, BackupService::TABLE_ORDER_PG) so no FK is
     * ever violated, replay the dump's INSERTs (already forward-ordered by
     * the same list) the same way, then resync every identity sequence to
     * MAX(id) so the next ordinary insert can't collide with a just-restored
     * row's id (OVERRIDING SYSTEM VALUE replayed the id but never advances
     * the sequence backing it).
     *
     * All of it, including the structural sanity checks, runs inside one
     * explicit transaction. Found live during the RLS work (010's own note)
     * that this pooler durably honors an explicit transaction around
     * catalog-affecting statements; ordinary DML is unaffected either way.
     * This also makes pgsql restore strictly safer than mysql's: a failure
     * at any point here rolls the whole attempt back, leaving the database
     * exactly as it was -- mysql's DDL auto-commits and can never offer that,
     * which is the entire reason the pre-restore backup exists as its real
     * safety net (see execute()'s docblock).
     */
    private function executePgsql(PDO $pdo, string $sql): void
    {
        $pdo->beginTransaction();
        try {
            foreach (array_reverse(BackupService::TABLE_ORDER_PG) as $table) {
                $pdo->exec('DELETE FROM "' . $table . '"');
            }
            // One call, not split statement-by-statement: the dump's INSERT
            // values are real user data and can legitimately contain ';' or
            // '--' inside a quoted string -- splitting client-side the way
            // MigrationService::splitStatements() does (built for
            // hand-authored DDL text, never audited against arbitrary data)
            // would corrupt those rows. Postgres's own simple-query protocol
            // already executes a ';'-separated batch correctly, respecting
            // quoting, inside this same transaction.
            $pdo->exec($sql);
            foreach (BackupService::TABLE_ORDER_PG as $table) {
                $this->resyncSequence($pdo, $table);
            }
            $this->runStructuralChecks($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Advances $table's identity sequence to MAX(id) (or resets it to 1 for
     * an empty table) so the next ordinary auto-increment insert can't
     * collide with a row this restore just replayed with an explicit id.
     * Three tables (promotion_locks, teacher_subjects, role_permissions)
     * have composite natural-key primary keys and no "id" column at all --
     * found live: pg_get_serial_sequence('table', 'id') doesn't return NULL
     * for those, it THROWS ("column ... does not exist"), unlike its
     * documented NULL-for-no-sequence behavior when the column exists but
     * isn't identity-backed. Checking column existence first avoids ever
     * calling it in the case that actually errors.
     */
    private function resyncSequence(PDO $pdo, string $table): void
    {
        $hasIdColumn = $pdo->prepare(
            "SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = 'id'"
        );
        $hasIdColumn->execute([$table]);
        if ($hasIdColumn->fetchColumn() === false) {
            return;
        }

        $seq = $pdo->query("SELECT pg_get_serial_sequence('{$table}', 'id')")->fetchColumn();
        if (!$seq) {
            return;
        }
        $max = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM "' . $table . '"')->fetchColumn();
        // setval(regclass, bigint, boolean) -- literal true/false, not a
        // bound param: PDO's emulated-prepare layer stringifies a bound PHP
        // bool as '1'/'' rather than a SQL boolean literal, which
        // setval()'s third argument would reject outright.
        $pdo->exec('SELECT setval(' . $pdo->quote($seq) . ', ' . max($max, 1) . ', ' . ($max > 0 ? 'true' : 'false') . ')');
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

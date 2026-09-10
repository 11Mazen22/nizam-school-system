<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Repositories\BackupRepository;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Nizam -- §I.2 backup engine (§Q "BackupService | run, verify"). Pure PHP,
 * no shell_exec/mysqldump (decision #8) -- streams schema+data for every
 * table, in dependency-safe order, straight to disk with a rolling SHA-256,
 * so the database is never held fully in memory even for the "thousands of
 * students" scale §A requires.
 */
final class BackupService
{
    public const FORMAT_VERSION = 'v1';

    /**
     * A valid topological order for the FK graph in §D -- not copied
     * verbatim from §I.2's prose list (which is one valid ordering, not the
     * only one), but every table here still appears after everything it
     * references. Order only matters for a human reading the dump by eye;
     * the replay itself runs with FOREIGN_KEY_CHECKS=0 (§O-5) so it isn't
     * order-dependent.
     */
    private const TABLE_ORDER = [
        'schools', 'settings', 'migrations', 'roles', 'permissions', 'role_permissions',
        'academic_years', 'grades', 'subjects', 'users', 'classes',
        'subject_staffing_requirements', 'students', 'teachers',
        'student_enrollments', 'teacher_subjects', 'teacher_assignments',
        'promotion_locks', 'activity_logs', 'backups', 'login_attempts',
    ];

    public function __construct(
        private readonly BackupRepository $repo = new BackupRepository(),
        private readonly MigrationService $migrations = new MigrationService(),
    ) {
        $dir = self::directory();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /** §J backup.default_folder -- previously seeded but never actually read anywhere; now the live source of truth, defaulting to the original hardcoded location. */
    public static function directory(): string
    {
        $folder = SettingsService::get('backup.default_folder', 'database/backups');
        return self::resolveDirectory((string) $folder);
    }

    /**
     * Pure path resolution, split out from directory() so Settings -> Backup
     * can validate a CANDIDATE value (does the resulting folder exist or can
     * it be created? is it writable?) before ever saving it as the active
     * setting. An absolute path (Windows drive letter or POSIX leading
     * slash) is used as-is -- a school keeping backups on a second drive is
     * a legitimate, still-local choice (decision #8); anything else resolves
     * against the project root, the same convention storage/uploads and
     * vendor-assets/fonts already use.
     */
    public static function resolveDirectory(string $folder): string
    {
        $folder = trim($folder);
        if (preg_match('#^(?:[a-zA-Z]:[\\\\/]|/)#', $folder) === 1) {
            return rtrim($folder, '\\/');
        }
        return dataPath() . '/' . trim($folder, '\\/');
    }

    /**
     * §I.2: dumps schema (CREATE TABLE) + data for every table, prefixed
     * with the app's own signature line (§O-4: "-- NIZAM-BACKUP v1
     * schema=NNNN", schema being how many migrations are applied right now
     * -- reusing MigrationService rather than re-deriving that number) and
     * suffixed with a SHA-256 checksum (§S-8) computed incrementally so the
     * full dump text is never held as one giant in-memory string.
     *
     * Records a backups row either way (§I.2 step 5): success with the
     * final file size, or failed with the partial file removed and a short,
     * non-technical note -- the actual exception detail goes to storage/logs
     * via the caller's own error handling, never into a user-facing string.
     *
     * Returns the filename alongside the id rather than just the id: a
     * restore can replay an older snapshot of the backups table itself
     * (found live, see RestoreService) which may no longer list a row
     * created after that snapshot was taken -- the filename captured here,
     * in memory, at creation time stays valid regardless, since the actual
     * .sql file on disk is never touched by a later restore.
     *
     * @return array{id: int, filename: string}
     */
    public function run(string $type, ?int $userId = null, ?string $notes = null): array
    {
        $pdo = Database::connection();

        // backups.created_by is a nullable FK to users.id. Found live: the
        // automatic pre-restore emergency backup (§I.3 step 3) can itself be
        // triggered at a moment when the *current* database's users table no
        // longer contains the acting session's own row -- specifically, a
        // second restore attempted right after a first one just wiped it.
        // ON DELETE SET NULL never fires here (a restore drops and recreates
        // the table wholesale, it doesn't delete the row through a trigger),
        // so the same defensive check is done explicitly before the insert.
        if ($userId !== null) {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn() === false) {
                $userId = null;
            }
        }

        $filename = 'nizam_backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
        $filepath = self::directory() . '/' . $filename;
        $handle = false;

        try {
            $handle = fopen($filepath, 'wb');
            if ($handle === false) {
                throw new RuntimeException("Could not open {$filepath} for writing.");
            }

            $hashCtx = hash_init('sha256');
            $write = static function (string $chunk) use ($handle, $hashCtx): void {
                fwrite($handle, $chunk);
                hash_update($hashCtx, $chunk);
            };

            $schemaVersion = count($this->migrations->getAppliedMigrations($pdo));
            $write(sprintf("-- NIZAM-BACKUP %s schema=%04d\n", self::FORMAT_VERSION, $schemaVersion));
            $write("-- Nizam Backup\n-- Created: " . date('Y-m-d H:i:s') . "\n-- Type: {$type}\n\n");

            $actualTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $tablesToDump = array_values(array_intersect(self::TABLE_ORDER, $actualTables));
            foreach ($actualTables as $table) {
                if (!in_array($table, $tablesToDump, true)) {
                    $tablesToDump[] = $table;
                }
            }

            foreach ($tablesToDump as $table) {
                $this->dumpTable($pdo, $table, $write);
            }

            $checksum = hash_final($hashCtx);
            fwrite($handle, "-- SHA256: {$checksum}\n");
            fclose($handle);

            $size = (int) filesize($filepath);
            $id = $this->repo->create([
                'filename'   => $filename,
                'file_size'  => $size,
                'type'       => $type,
                'status'     => 'success',
                'created_by' => $userId,
                'notes'      => $notes,
            ]);
            ActivityLogger::log('backup.created', 'backups', $id, "Created {$type} backup: {$filename} ({$size} bytes)");

            return ['id' => $id, 'filename' => $filename];
        } catch (Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($filepath)) {
                unlink($filepath);
            }
            error_log('[Nizam] Backup failed: ' . $e->getMessage());
            $this->repo->create([
                'filename'   => $filename,
                'file_size'  => 0,
                'type'       => $type,
                'status'     => 'failed',
                'created_by' => $userId,
                'notes'      => 'Backup failed -- see storage/logs for detail.',
            ]);
            throw new RuntimeException('backup_failed', 0, $e);
        }
    }

    /** @param callable(string):void $write */
    private function dumpTable(PDO $pdo, string $table, callable $write): void
    {
        $write("-- Table: {$table}\n");
        $write("DROP TABLE IF EXISTS `{$table}`;\n");

        $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC)['Create Table'];
        $write($createTable . ";\n\n");

        // student_enrollments self-references via previous_enrollment_id;
        // inserting in id order means a row's own previous_enrollment_id
        // target is already present when FK checks are on (defense in
        // depth -- the replay itself also runs with checks suspended, O-5).
        $orderClause = $table === 'student_enrollments' ? ' ORDER BY id ASC' : '';

        // A generated column (e.g. academic_years.active_flag, §O-2) can't
        // be written by INSERT at all -- MySQL/MariaDB recomputes it from
        // the row's other columns. Found live: SELECT * pulls its value
        // like any other column, and INSERTing that value back raised
        // "1906 ... has been ignored" under this connection's strict
        // sql_mode, aborting the whole replay. mysqldump itself has the
        // same exclusion for the same reason.
        $generated = [];
        $colStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            if (stripos($col['Extra'], 'GENERATED') !== false) {
                $generated[] = $col['Field'];
            }
        }

        $stmt = $pdo->query("SELECT * FROM `{$table}`" . $orderClause);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($generated as $col) {
                unset($row[$col]);
            }
            $cols = array_map(static fn (string $k): string => "`$k`", array_keys($row));
            $vals = array_map(
                static fn (mixed $v): string => $v === null ? 'NULL' : $pdo->quote((string) $v),
                array_values($row)
            );
            $write('INSERT INTO `' . $table . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n");
        }
        $write("\n");
    }

    /**
     * Reads a backup file and validates it structurally -- signature,
     * supported format version, and checksum (§I.3 step 1) -- entirely
     * independent of any live database. RestoreService layers the
     * schema-version-compatibility and structural-sanity checks on top of
     * this, since those need a database connection this method doesn't.
     *
     * Throws RuntimeException with a short reason code on failure
     * (established convention, see ReportService's missing_filter/
     * invalid_filter) so callers can map it to a precise, translated
     * message rather than a generic one (§Q error catalog: "the specific
     * reason ... named in plain language").
     *
     * @return array{sql: string, schemaVersion: int}
     */
    public function verify(string $filepath): array
    {
        if (!is_file($filepath)) {
            throw new RuntimeException('file_not_found');
        }

        $content = file_get_contents($filepath);
        if ($content === false || $content === '') {
            throw new RuntimeException('file_not_found');
        }

        if (!preg_match('/^-- NIZAM-BACKUP (\S+) schema=(\d+)\r?\n/', $content, $sig)) {
            throw new RuntimeException('invalid_signature');
        }
        if ($sig[1] !== self::FORMAT_VERSION) {
            throw new RuntimeException('unsupported_format_version');
        }
        $schemaVersion = (int) $sig[2];

        if (!preg_match('/-- SHA256: ([a-f0-9]{64})\r?\n?$/', $content, $sum)) {
            throw new RuntimeException('invalid_signature');
        }
        $declared = $sum[1];
        $sql = substr($content, 0, -strlen($sum[0]));

        if (!hash_equals($declared, hash('sha256', $sql))) {
            throw new RuntimeException('checksum_mismatch');
        }

        return ['sql' => $sql, 'schemaVersion' => $schemaVersion];
    }
}

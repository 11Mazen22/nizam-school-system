<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

/**
 * Nizam -- migration-running logic, extracted from database/migrate.php
 * during Phase 5 so the Setup Wizard's "schema install" step and the CLI
 * tool share one implementation instead of two (Phase 5's own "do not
 * duplicate existing mechanisms" rule, applied to this mechanism too, not
 * just the security ones it was written about). database/migrate.php now
 * calls this class; its own behavior is unchanged and was re-verified
 * (108/108) after the refactor.
 *
 * Same transaction philosophy as before, upgraded from the CLI tool's own
 * hard-won lesson (found by actually running it in Phase 3): MySQL/MariaDB
 * auto-commits DDL, so no PDO transaction wraps these statements -- see
 * database/migrate.php's original header for the full explanation.
 */
final class MigrationService
{
    /** @return array{applied: string[], alreadyApplied: string[], failure: ?array{migration: string, statement: string, error: string}} */
    public function run(PDO $pdo, string $migrationsDir): array
    {
        $this->bootstrapMigrationsTable($pdo);

        $applied = [];
        $alreadyApplied = $this->getAppliedMigrations($pdo);

        $files = glob(rtrim($migrationsDir, '/\\') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $alreadyApplied, true)) {
                continue;
            }

            $statements = self::splitStatements((string) file_get_contents($file));
            $currentStatement = '(recording this migration as applied)';
            // Postgres only: found live applying migration 010 against
            // Supabase's transaction-mode pooler -- a catalog-level DDL
            // statement (ALTER TABLE ... ENABLE ROW LEVEL SECURITY) executed
            // via a bare autocommit exec() reported success but did not
            // durably persist, confirmed by checking pg_tables from a
            // separate connection immediately after. An explicit
            // transaction around the same statement was stable and
            // reproducible. Ordinary data INSERT/UPDATE was independently
            // confirmed NOT to have this problem (Postgres migrations 001-009
            // and the seed/data-copy work that ran before this fix was found
            // were re-verified afterward and are durably correct) -- this
            // is specific to catalog-affecting DDL under this pooler, not a
            // general autocommit failure. MySQL already auto-commits DDL
            // with no transaction wrapping it (see bootstrapMigrationsTable's
            // own note) and is completely unaffected by this branch.
            $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
            try {
                if ($isPgsql) {
                    $pdo->beginTransaction();
                }
                foreach ($statements as $statement) {
                    $currentStatement = $statement;
                    $pdo->exec($statement);
                }
                $currentStatement = '(recording this migration as applied)';
                $stmt = $pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
                $stmt->execute(['migration' => $name]);
                if ($isPgsql) {
                    $pdo->commit();
                }
                $applied[] = $name;
            } catch (PDOException $e) {
                if ($isPgsql && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return [
                    'applied' => $applied,
                    'alreadyApplied' => $alreadyApplied,
                    'failure' => [
                        'migration' => $name,
                        'statement' => self::truncate($currentStatement, 200),
                        'error' => $e->getMessage(),
                    ],
                ];
            }
        }

        return ['applied' => $applied, 'alreadyApplied' => $alreadyApplied, 'failure' => null];
    }

    public function pendingCount(PDO $pdo, string $migrationsDir): int
    {
        $this->bootstrapMigrationsTable($pdo);
        $applied = $this->getAppliedMigrations($pdo);
        $files = glob(rtrim($migrationsDir, '/\\') . '/*.sql') ?: [];
        $total = count(array_map('basename', $files));
        return $total - count($applied);
    }

    public function bootstrapMigrationsTable(PDO $pdo): void
    {
        // Driver read straight from the PDO handle itself (PDO::ATTR_DRIVER_NAME
        // is always populated correctly by the driver, e.g. via App\Database's
        // pgsql: vs mysql: DSN) -- no separate config threading needed, and this
        // is the one place in the migration runner that ever needed to know.
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS migrations (
                  id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                  migration   VARCHAR(191) NOT NULL UNIQUE,
                  applied_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL);
            return;
        }

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migrations (
              id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
              migration   VARCHAR(191) NOT NULL,
              applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_migrations_migration (migration)
            ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            SQL);
    }

    /** @return string[] */
    public function getAppliedMigrations(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT migration FROM migrations ORDER BY migration');
        return array_column($stmt->fetchAll(), 'migration');
    }

    /**
     * @return string[]
     *
     * Two things a plain explode(';') can't handle, both needed once the
     * Postgres migrations exist (MySQL files never trigger either path --
     * the split result for them is byte-for-byte identical to before):
     *   - Dollar-quoted function bodies ($$ ... $$ / $tag$ ... $tag$, used by
     *     the Postgres migrations' set_updated_at() trigger function): a
     *     semicolon inside one is literal function-body text, never a
     *     statement separator.
     *   - A "--" line comment: everything from it to the next newline is
     *     inert, including any semicolon it happens to contain -- this
     *     codebase's migrations are comment-heavy prose, and an explanatory
     *     comment containing a semicolon is a realistic, recurring thing to
     *     write, not a one-off edge case worth leaving unhandled.
     */
    public static function splitStatements(string $sql): array
    {
        $parts = [];
        $current = '';
        $inDollarQuote = false;
        $tag = '';
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            if (!$inDollarQuote && $sql[$i] === '-' && ($sql[$i + 1] ?? '') === '-') {
                $end = strpos($sql, "\n", $i);
                $i = ($end === false ? $length : $end) - 1;
                continue;
            }
            if (!$inDollarQuote && $sql[$i] === ';') {
                $parts[] = $current;
                $current = '';
                continue;
            }
            if ($sql[$i] === '$' && preg_match('/\G\$(\w*)\$/', $sql, $m, 0, $i)) {
                $current .= $m[0];
                if (!$inDollarQuote) {
                    $inDollarQuote = true;
                    $tag = $m[1];
                } elseif ($m[1] === $tag) {
                    $inDollarQuote = false;
                }
                $i += strlen($m[0]) - 1;
                continue;
            }
            $current .= $sql[$i];
        }
        $parts[] = $current;

        $parts = array_map('trim', $parts);
        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    public static function truncate(string $text, int $length): string
    {
        $collapsed = preg_replace('/\s+/', ' ', $text) ?? $text;
        return mb_strlen($collapsed) > $length ? mb_substr($collapsed, 0, $length) . '…' : $collapsed;
    }
}

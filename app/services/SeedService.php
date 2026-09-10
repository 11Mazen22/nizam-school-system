<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

/**
 * Nizam -- seed-running logic, extracted from database/seed.php during
 * Phase 5 for the same reason as MigrationService. Seeds are not tracked in
 * a bookkeeping table (§D is explicit: idempotency comes from each seed
 * file's own SQL) -- every file just runs, every time, in order.
 */
final class SeedService
{
    /** @return array{ran: string[], failure: ?array{seed: string, statement: string, error: string}} */
    public function run(PDO $pdo, string $seedsDir): array
    {
        $ran = [];
        $files = glob(rtrim($seedsDir, '/\\') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $name = basename($file);
            $statements = MigrationService::splitStatements((string) file_get_contents($file));
            $currentStatement = '';
            try {
                foreach ($statements as $statement) {
                    $currentStatement = $statement;
                    $pdo->exec($statement);
                }
                $ran[] = $name;
            } catch (PDOException $e) {
                return [
                    'ran' => $ran,
                    'failure' => [
                        'seed' => $name,
                        'statement' => MigrationService::truncate($currentStatement, 200),
                        'error' => $e->getMessage(),
                    ],
                ];
            }
        }

        return ['ran' => $ran, 'failure' => null];
    }
}

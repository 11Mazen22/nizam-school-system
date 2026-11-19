<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class YearWorkRepository
{
    /** @return array<int,array<string,mixed>> */
    public function entriesFor(int $studentId, int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, entry_date, category, label, source_key, score, max_score, source
               FROM year_work_entries
              WHERE student_id = :student AND academic_year_id = :year
              ORDER BY entry_date ASC, id ASC'
        );
        $stmt->execute(['student' => $studentId, 'year' => $yearId]);
        return $stmt->fetchAll();
    }

    /**
     * Insert or update one year-work entry. A non-null source_key is the
     * idempotency key, so re-importing the same week never doubles a mark.
     *
     * @return int the entry id
     */
    public function record(
        int $yearId,
        int $studentId,
        string $entryDate,
        string $category,
        ?string $label,
        ?string $sourceKey,
        float $score,
        float $maxScore,
        string $source = 'manual'
    ): int {
        $pdo = Database::connection();
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $params = [
            'year' => $yearId, 'student' => $studentId, 'date' => $entryDate,
            'category' => $category, 'label' => $label, 'key' => $sourceKey,
            'score' => $score, 'max' => $maxScore, 'source' => $source,
        ];

        if ($sourceKey !== null && $sourceKey !== '') {
            $find = $pdo->prepare(
                'SELECT id FROM year_work_entries
                  WHERE academic_year_id = :year AND student_id = :student AND source_key = :key'
            );
            $find->execute(['year' => $yearId, 'student' => $studentId, 'key' => $sourceKey]);
            $existing = (int) ($find->fetchColumn() ?: 0);
            if ($existing > 0) {
                $update = $pdo->prepare(
                    'UPDATE year_work_entries
                        SET entry_date = :date, category = :category, label = :label,
                            score = :score, max_score = :max, source = :source
                      WHERE id = :id'
                );
                $update->execute($params + ['id' => $existing]);
                return $existing;
            }
        }

        $sql = 'INSERT INTO year_work_entries
                    (academic_year_id, student_id, entry_date, category, label, source_key, score, max_score, source)
                VALUES (:year, :student, :date, :category, :label, :key, :score, :max, :source)';
        if ($driver === 'pgsql') {
            $sql .= ' RETURNING id';
        }
        $insert = $pdo->prepare($sql);
        $insert->execute($params);
        return (int) ($driver === 'pgsql' ? $insert->fetchColumn() : $pdo->lastInsertId());
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM year_work_entries WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}

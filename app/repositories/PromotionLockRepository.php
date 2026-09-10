<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDOException;

/** Nizam -- §O-17's app-level promotion mutex (see migration 009's own comment for the full rationale). */
final class PromotionLockRepository
{
    /** @throws PDOException (23000, PK collision) if this year is already locked -- PromotionService translates this into §Q's "already running" message. */
    public function acquire(int $academicYearId): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO promotion_locks (academic_year_id) VALUES (:year)');
        $stmt->execute(['year' => $academicYearId]);
    }

    public function release(int $academicYearId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM promotion_locks WHERE academic_year_id = :year');
        $stmt->execute(['year' => $academicYearId]);
    }
}

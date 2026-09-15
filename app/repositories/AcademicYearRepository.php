<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Services\SettingsService;

/**
 * Nizam -- academic-year reads/writes. createFirst() (Phase 5, Setup Wizard)
 * stays as-is: it only ever runs once, when no year exists at all, so it
 * doesn't need any of the activate/close machinery below. The rest of this
 * class is Phase 6's Academic Years module (§I.4, §E).
 */
final class AcademicYearRepository
{
    public function findActive(): ?array
    {
        $stmt = Database::connection()->query(
            'SELECT id, label, is_closed FROM academic_years WHERE is_active = 1 LIMIT 1'
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function exists(): bool
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM academic_years')->fetchColumn() > 0;
    }

    public function createFirst(string $label, string $startDate, string $endDate): void
    {
        $capacity = SettingsService::get('academic.expected_weekly_capacity', 20);
        $stmt = Database::connection()->prepare(
            'INSERT INTO academic_years (label, start_date, end_date, is_active, is_closed, expected_weekly_capacity)
             VALUES (:label, :start, :end, 1, 0, :capacity)'
        );
        $stmt->execute(['label' => $label, 'start' => $startDate, 'end' => $endDate, 'capacity' => $capacity]);
    }

    /** @return array<int, array<string, mixed>> newest first */
    public function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM academic_years ORDER BY start_date DESC, id DESC')
            ->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM academic_years WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByLabel(string $label): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM academic_years WHERE label = :label');
        $stmt->execute(['label' => $label]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Inserted inactive/open; AcademicYearService::activate() is a separate, explicit step (§I.4). */
    public function create(string $label, string $startDate, string $endDate): int
    {
        $capacity = SettingsService::get('academic.expected_weekly_capacity', 20);
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO academic_years (label, start_date, end_date, is_active, is_closed, expected_weekly_capacity)
             VALUES (:label, :start, :end, 0, 0, :capacity)'
        );
        $stmt->execute(['label' => $label, 'start' => $startDate, 'end' => $endDate, 'capacity' => $capacity]);
        return (int) $pdo->lastInsertId();
    }

    /** §I.4 "Activate": deactivates whatever was active, in the same transaction as the caller. */
    public function deactivateAll(): void
    {
        Database::connection()->exec('UPDATE academic_years SET is_active = 0 WHERE is_active = 1');
    }

    public function setActive(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE academic_years SET is_active = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function setClosed(int $id, bool $closed): void
    {
        $stmt = Database::connection()->prepare('UPDATE academic_years SET is_closed = :closed WHERE id = :id');
        $stmt->execute(['closed' => $closed ? 1 : 0, 'id' => $id]);
    }

    /**
     * §I.4 "Close": every write site that touches student_enrollments,
     * teacher_assignments, or classes for a specific year calls this first --
     * "the service layer rejects any INSERT/UPDATE... scoped to that year"
     * once is_closed=1. True (closed) if the year doesn't even exist, which
     * is never a state a write should proceed from either.
     */
    public function isClosed(int $yearId): bool
    {
        $year = $this->find($yearId);
        return $year === null || (int) $year['is_closed'] === 1;
    }

    /**
     * Update the label and date range of an existing academic year.
     * Does NOT touch is_active or is_closed -- those have dedicated methods.
     * Uniqueness of label is validated by the caller (excluding this year's
     * own current label from the duplicate check).
     */
    public function update(int $id, string $label, string $startDate, string $endDate): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE academic_years SET label = :label, start_date = :start, end_date = :end WHERE id = :id'
        );
        $stmt->execute(['label' => $label, 'start' => $startDate, 'end' => $endDate, 'id' => $id]);
    }
}

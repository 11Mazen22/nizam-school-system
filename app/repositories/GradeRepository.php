<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/**
 * Nizam -- grades (§D). No archive workflow of its own in §Q's screen
 * inventory ("Grades list" only, no separate Add/Edit screen) -- is_active
 * is still a real column (§D), toggled from the list itself rather than
 * through a dedicated form, matching how small a grade record is (name_en,
 * name_ar, sort_order).
 */
final class GradeRepository
{
    /** @return array<int, array<string, mixed>> ordered by sort_order, the same order promotion (§I.1) walks them */
    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM grades' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC';
        return Database::connection()->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM grades WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** The grade with the next-higher sort_order, or null if this is the final grade (§I.1 step 3: "graduate -- forced when a grade has no defined successor"). */
    public function nextGrade(int $currentSortOrder): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM grades WHERE sort_order > :sort AND is_active = 1 ORDER BY sort_order ASC LIMIT 1'
        );
        $stmt->execute(['sort' => $currentSortOrder]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** nextGrade(), starting from a grade_id rather than an already-known sort_order -- what PromotionService actually has on hand for each enrollment. */
    public function nextGradeAfter(int $gradeId): ?array
    {
        $current = $this->find($gradeId);
        return $current === null ? null : $this->nextGrade((int) $current['sort_order']);
    }

    public function create(string $nameEn, string $nameAr, int $sortOrder): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO grades (name_en, name_ar, sort_order, is_active) VALUES (:en, :ar, :sort, 1)'
        );
        $stmt->execute(['en' => $nameEn, 'ar' => $nameAr, 'sort' => $sortOrder]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, string $nameEn, string $nameAr, int $sortOrder): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE grades SET name_en = :en, name_ar = :ar, sort_order = :sort WHERE id = :id'
        );
        $stmt->execute(['en' => $nameEn, 'ar' => $nameAr, 'sort' => $sortOrder, 'id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE grades SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }
}

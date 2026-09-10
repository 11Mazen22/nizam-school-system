<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\GradeRepository;

final class GradeService
{
    public function __construct(private readonly GradeRepository $grades = new GradeRepository())
    {
    }

    public function create(string $nameEn, string $nameAr, int $sortOrder): int
    {
        $id = $this->grades->create($nameEn, $nameAr, $sortOrder);
        ActivityLogger::log('grade.create', 'grades', $id, "Grade '{$nameEn}' created");
        return $id;
    }

    public function update(int $id, string $nameEn, string $nameAr, int $sortOrder): void
    {
        $this->grades->update($id, $nameEn, $nameAr, $sortOrder);
        ActivityLogger::log('grade.update', 'grades', $id, "Grade '{$nameEn}' updated");
    }

    /** §O-13: the only deletion path exposed anywhere for grades is archiving (is_active=0) -- no delete button, ever. */
    public function setActive(int $id, bool $active): void
    {
        $this->grades->setActive($id, $active);
        ActivityLogger::log($active ? 'grade.restore' : 'grade.archive', 'grades', $id, null);
    }
}

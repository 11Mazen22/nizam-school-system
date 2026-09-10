<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\ClassRepository;
use RuntimeException;

/**
 * Nizam -- §I Grades & Classes module. Validation rule (§Q): "classes.name
 * required, unique within (grade, year) -- the DB constraint is the
 * backstop, but the form checks first for a friendly message." The check
 * here is exactly that first line of defense; uq_classes_grade_year_name
 * (migration 005) is the backstop if a race ever slips past it. §I.4 "Close":
 * a closed year also rejects any INSERT/UPDATE against classes.
 */
final class ClassService
{
    public function __construct(
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
    ) {
    }

    /** @throws RuntimeException 'duplicate_name'|'year_closed' */
    public function create(int $gradeId, int $academicYearId, string $name, ?int $capacity): int
    {
        if ($this->years->isClosed($academicYearId)) {
            throw new RuntimeException('year_closed');
        }
        if ($this->classes->findByName($gradeId, $academicYearId, $name) !== null) {
            throw new RuntimeException('duplicate_name');
        }

        $id = $this->classes->create($gradeId, $academicYearId, $name, $capacity);
        ActivityLogger::log('class.create', 'classes', $id, "Class '{$name}' created");
        return $id;
    }

    /** @throws RuntimeException 'duplicate_name'|'year_closed' */
    public function update(int $id, int $gradeId, int $academicYearId, string $name, ?int $capacity): void
    {
        if ($this->years->isClosed($academicYearId)) {
            throw new RuntimeException('year_closed');
        }
        $existing = $this->classes->findByName($gradeId, $academicYearId, $name);
        if ($existing !== null && (int) $existing['id'] !== $id) {
            throw new RuntimeException('duplicate_name');
        }

        $this->classes->update($id, $name, $capacity);
        ActivityLogger::log('class.update', 'classes', $id, "Class '{$name}' updated");
    }

    /** §O-13: archiving is the only deletion path exposed for classes. */
    public function setActive(int $id, bool $active): void
    {
        $this->classes->setActive($id, $active);
        ActivityLogger::log($active ? 'class.restore' : 'class.archive', 'classes', $id, null);
    }
}

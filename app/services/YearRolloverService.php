<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClassRepository;
use App\Repositories\TeacherAssignmentRepository;

/**
 * Nizam -- §O-11 rollover: without this, every new year starts with zero
 * classes and zero teacher assignments, and promotion (§I.1) has nowhere to
 * place students. Clones are new rows (classes/teacher_assignments are
 * scoped by academic_year_id) that the admin is free to edit before
 * promotion runs -- nothing is locked in by the clone itself.
 */
final class YearRolloverService
{
    public function __construct(
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly TeacherAssignmentRepository $assignments = new TeacherAssignmentRepository(),
    ) {
    }

    /** @return array<int,int> old_class_id => new_class_id -- the caller must pass this into cloneAssignments() so classes aren't cloned twice. */
    public function cloneClasses(int $fromYearId, int $toYearId): array
    {
        return $this->classes->cloneActiveForRollover($fromYearId, $toYearId);
    }

    /** @param array<int,int> $classMap from a cloneClasses() call already made against the same $fromYearId/$toYearId */
    public function cloneAssignments(int $fromYearId, int $toYearId, array $classMap): void
    {
        $this->assignments->cloneActiveForRollover($fromYearId, $toYearId, $classMap);
    }
}

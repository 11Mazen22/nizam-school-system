<?php

declare(strict_types=1);

namespace App\Services;

use App\Middleware\AcademicYearContext;
use App\Repositories\AcademicYearRepository;
use App\Repositories\ClassRepository;
use App\Repositories\TeacherAssignmentRepository;
use App\Repositories\TimetableRepository;
use RuntimeException;

final class TimetableService
{
    private const MAX_PERIODS = 8;   // periods per day
    private const MAX_DAYS    = 6;   // Saturday(1) → Thursday(6), typical MENA school week

    public function __construct(
        private readonly TimetableRepository $repo = new TimetableRepository(),
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly TeacherAssignmentRepository $assignments = new TeacherAssignmentRepository(),
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
    ) {
    }

    /**
     * Add a slot after strict conflict detection.
     *
     * @throws RuntimeException 'no_active_year' | 'teacher_conflict' | 'class_conflict' | 'validation_error'
     */
    public function addSlot(int $classId, int $day, int $period, int $subjectId, int $teacherId): int
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) { throw new RuntimeException('no_active_year'); }

        $this->validateClassAndAssignment($classId, $yearId, $subjectId, $teacherId);
        $this->validateSlot($day, $period, $subjectId, $teacherId);
        $this->detectConflicts($yearId, $classId, $teacherId, $day, $period);

        $id = $this->repo->create($yearId, $classId, $day, $period, $subjectId, $teacherId);
        ActivityLogger::log('timetable.add', 'classes', $classId,
            "Slot added: day={$day} period={$period}");
        return $id;
    }

    /**
     * Update an existing slot's subject/teacher (day+period fixed — drag-and-drop
     * moves are handled as delete + add to preserve the unique constraints cleanly).
     *
     * @throws RuntimeException 'not_found' | 'teacher_conflict' | 'validation_error'
     */
    public function updateSlot(int $id, int $subjectId, int $teacherId): void
    {
        $slot = $this->repo->find($id);
        if ($slot === null) { throw new RuntimeException('not_found'); }

        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null || (int) $slot['academic_year_id'] !== $yearId || $this->years->isClosed($yearId)) {
            throw new RuntimeException('year_closed');
        }

        if ($subjectId <= 0 || $teacherId <= 0) { throw new RuntimeException('validation_error'); }
        $this->validateClassAndAssignment((int) $slot['class_id'], $yearId, $subjectId, $teacherId);

        // Conflict check excludes current row
        if ($this->repo->teacherSlotTaken(
            (int)$slot['academic_year_id'], $teacherId,
            (int)$slot['day_of_week'], (int)$slot['period_number'], $id
        )) {
            throw new RuntimeException('teacher_conflict');
        }

        $this->repo->update($id, $subjectId, $teacherId);
        ActivityLogger::log('timetable.update', 'timetables', $id, null);
    }

    public function deleteSlot(int $id): void
    {
        $slot = $this->repo->find($id);
        $yearId = AcademicYearContext::activeYearId();
        if ($slot === null) { throw new RuntimeException('not_found'); }
        if ($yearId === null || (int) $slot['academic_year_id'] !== $yearId || $this->years->isClosed($yearId)) {
            throw new RuntimeException('year_closed');
        }
        $this->repo->delete($id);
        ActivityLogger::log('timetable.delete', 'timetables', $id, null);
    }

    public function clearClass(int $classId): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) { throw new RuntimeException('no_active_year'); }
        if ($this->years->isClosed($yearId) || !$this->isActiveClassForYear($classId, $yearId)) {
            throw new RuntimeException('year_closed');
        }
        $this->repo->clearForClass($classId, $yearId);
        ActivityLogger::log('timetable.clear', 'classes', $classId, "Timetable cleared");
    }

    public function getGrid(int $classId): array
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) { return []; }
        return $this->repo->gridForClass($classId, $yearId);
    }

    public function getDayLabels(): array
    {
        // Saturday=1 … Thursday=6 (MENA school week)
        return [
            1 => __('timetable.day_sat'),
            2 => __('timetable.day_sun'),
            3 => __('timetable.day_mon'),
            4 => __('timetable.day_tue'),
            5 => __('timetable.day_wed'),
            6 => __('timetable.day_thu'),
        ];
    }

    public function getMaxPeriods(): int { return self::MAX_PERIODS; }
    public function getMaxDays(): int    { return self::MAX_DAYS; }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function validateSlot(int $day, int $period, int $subjectId, int $teacherId): void
    {
        if ($day < 1 || $day > self::MAX_DAYS || $period < 1 || $period > self::MAX_PERIODS
            || $subjectId <= 0 || $teacherId <= 0) {
            throw new RuntimeException('validation_error');
        }
    }

    private function validateClassAndAssignment(int $classId, int $yearId, int $subjectId, int $teacherId): void
    {
        if ($this->years->isClosed($yearId)) { throw new RuntimeException('year_closed'); }
        if (!$this->isActiveClassForYear($classId, $yearId)
            || !$this->assignments->existsForTuple($teacherId, $subjectId, $classId, $yearId)) {
            throw new RuntimeException('validation_error');
        }
    }

    private function isActiveClassForYear(int $classId, int $yearId): bool
    {
        $class = $this->classes->find($classId);
        return $class !== null
            && (int) $class['academic_year_id'] === $yearId
            && (int) $class['is_active'] === 1;
    }

    private function detectConflicts(int $yearId, int $classId, int $teacherId, int $day, int $period): void
    {
        if ($this->repo->classSlotTaken($yearId, $classId, $day, $period)) {
            throw new RuntimeException('class_conflict');
        }
        if ($this->repo->teacherSlotTaken($yearId, $teacherId, $day, $period)) {
            throw new RuntimeException('teacher_conflict');
        }
    }
}

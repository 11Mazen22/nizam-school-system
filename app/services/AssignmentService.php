<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\TeacherAssignmentRepository;
use RuntimeException;

/**
 * Nizam -- Assignments module (§E, §O-9). Teacher-qualification checking is
 * deliberately a soft, application-layer warn-not-block rule -- a real
 * school genuinely uses substitute teachers outside their primary subject
 * (§O-9's own reasoning); nothing here ever refuses an assignment for lack
 * of a matching teacher_subjects row, it only tells the caller so the UI can
 * show a warning. §I.4 "Close": a closed year also rejects any INSERT/UPDATE
 * against teacher_assignments.
 */
final class AssignmentService
{
    public function __construct(
        private readonly TeacherAssignmentRepository $assignments = new TeacherAssignmentRepository(),
        private readonly SubjectRepository $subjects = new SubjectRepository(),
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
    ) {
    }

    /** §O-9: true if the teacher has NO listed qualification for this subject -- the caller shows a warning, never a block. */
    public function checkQualification(int $teacherId, int $subjectId): bool
    {
        return !$this->subjects->teacherIsQualified($teacherId, $subjectId);
    }

    /**
     * @throws RuntimeException 'duplicate'|'invalid_periods'|'year_closed'
     */
    public function create(int $teacherId, int $subjectId, int $classId, int $academicYearId, int $weeklyPeriods): int
    {
        if ($weeklyPeriods <= 0) {
            throw new RuntimeException('invalid_periods');
        }
        if ($this->years->isClosed($academicYearId)) {
            throw new RuntimeException('year_closed');
        }
        if ($this->assignments->existsForTuple($teacherId, $subjectId, $classId, $academicYearId)) {
            throw new RuntimeException('duplicate');
        }

        $id = $this->assignments->create($teacherId, $subjectId, $classId, $academicYearId, $weeklyPeriods);
        ActivityLogger::log('assignment.create', 'teacher_assignments', $id, null);
        return $id;
    }

    /** @throws RuntimeException 'invalid_periods'|'not_found'|'year_closed' */
    public function update(int $id, int $weeklyPeriods): void
    {
        if ($weeklyPeriods <= 0) {
            throw new RuntimeException('invalid_periods');
        }
        $assignment = $this->assignments->find($id);
        if ($assignment === null) {
            throw new RuntimeException('not_found');
        }
        if ($this->years->isClosed((int) $assignment['academic_year_id'])) {
            throw new RuntimeException('year_closed');
        }
        $this->assignments->update($id, $weeklyPeriods);
        ActivityLogger::log('assignment.update', 'teacher_assignments', $id, null);
    }

    /** §O-13: archiving is the only deletion path exposed for assignments. */
    public function archive(int $id): void
    {
        $assignment = $this->assignments->find($id);
        if ($assignment === null) {
            throw new RuntimeException('not_found');
        }
        if ($this->years->isClosed((int) $assignment['academic_year_id'])) {
            throw new RuntimeException('year_closed');
        }
        if ($assignment['status'] !== 'active') {
            throw new RuntimeException('not_active');
        }
        $this->assignments->setStatus($id, 'archived');
        ActivityLogger::log('assignment.archive', 'teacher_assignments', $id, null);
    }

    /**
     * Restore a previously archived assignment back to active status.
     * Guards against restoring into a closed year — the same safety rule as archive().
     * Duplicate-check: if an identical active assignment already exists
     * (same teacher+subject+class+year tuple), restoration is blocked to avoid conflicts.
     * @throws RuntimeException 'not_found'|'year_closed'|'duplicate'
     */
    public function restore(int $id): void
    {
        $assignment = $this->assignments->find($id);
        if ($assignment === null) {
            throw new RuntimeException('not_found');
        }
        if ($assignment['status'] !== 'archived') {
            throw new RuntimeException('not_archived');
        }
        if ($this->years->isClosed((int) $assignment['academic_year_id'])) {
            throw new RuntimeException('year_closed');
        }
        // Prevent creating a duplicate active assignment for the same tuple
        if ($this->assignments->existsForTuple(
            (int) $assignment['teacher_id'],
            (int) $assignment['subject_id'],
            (int) $assignment['class_id'],
            (int) $assignment['academic_year_id']
        )) {
            throw new RuntimeException('duplicate');
        }
        $this->assignments->setStatus($id, 'active');
        ActivityLogger::log('assignment.restore', 'teacher_assignments', $id, null);
    }
}

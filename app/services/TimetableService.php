<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Repositories\AcademicYearRepository;
use App\Repositories\ClassRepository;
use App\Repositories\TeacherAssignmentRepository;
use App\Repositories\TeacherRepository;
use App\Repositories\TimetableGapRepository;
use App\Repositories\TimetableRepository;
use RuntimeException;

final class TimetableService
{
    /** A school day is never shorter than this, so an empty grid still shows a full day. */
    private const MIN_PERIODS = 8;
    /** Hard ceiling for a posted period number; a ninth period is normal in secondary schools. */
    private const MAX_PERIODS = 12;
    private const MAX_DAYS    = 6;   // Saturday(1) → Thursday(6), typical MENA school week

    public function __construct(
        private readonly TimetableRepository $repo = new TimetableRepository(),
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly TeacherAssignmentRepository $assignments = new TeacherAssignmentRepository(),
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
        private readonly TeacherRepository $teachers = new TeacherRepository(),
        private readonly TimetableGapRepository $gaps = new TimetableGapRepository(),
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

        $id = $this->repo->create(
            $yearId, $classId, $day, $period, $subjectId, $teacherId,
            null, null, 'manual', null, $this->userId()
        );
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

        $this->repo->update($id, $subjectId, $teacherId, $this->userId());
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
        // A human deleting a printed cell is a decision, not a gap for the
        // next import to fill back in.
        $this->repo->excludeFromImport(
            $yearId,
            (int) $slot['class_id'],
            (int) $slot['day_of_week'],
            (int) $slot['period_number'],
            'manual_delete',
            $this->userId()
        );
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
        foreach ($this->repo->rowsForClass($classId, $yearId) as $slot) {
            $this->repo->excludeFromImport(
                $yearId,
                $classId,
                (int) $slot['day_of_week'],
                (int) $slot['period_number'],
                'manual_clear',
                $this->userId()
            );
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

    /**
     * How many period rows the grid must draw. The printed timetables use nine
     * periods; a fixed cap of eight silently hid every ninth-period lesson and
     * made a complete timetable look incomplete. The real year decides.
     */
    public function getMaxPeriods(): int
    {
        $yearId = AcademicYearContext::activeYearId();
        $used   = $yearId !== null ? $this->repo->maxPeriodForYear($yearId) : 0;

        return max(self::MIN_PERIODS, min(self::MAX_PERIODS, $used));
    }
    public function getMaxDays(): int    { return self::MAX_DAYS; }

    /**
     * The dedicated clash-resolution board.
     *
     * Every unresolved printed clash is paired with the teachers who are
     * actually free at that exact day+period. A candidate the school already
     * assigned to this subject/class is listed first, but availability is
     * always read from the current timetable -- the screen never offers a
     * teacher who is already in another room.
     *
     * @return array{clashes:list<array<string,mixed>>, resolutions:list<array<string,mixed>>}
     */
    public function clashBoard(int $yearId): array
    {
        $clashes = $this->repo->printedClashes($yearId);
        $bookings = $this->gaps->bookings($yearId);
        $teachers = $this->teachers->all('active');
        $assignments = $this->repo->assignmentTuples($yearId);
        $teacherIdByName = [];
        foreach ($teachers as $teacher) {
            $teacherIdByName[(string) $teacher['full_name']] = (int) $teacher['id'];
        }

        foreach ($clashes as &$clash) {
            $day = (int) $clash['day_of_week'];
            $period = (int) $clash['period_number'];
            $classId = (int) $clash['class_id'];
            $subjectId = (int) $clash['subject_id'];
            $busy = $bookings[$day][$period] ?? [];

            $printed = trim((string) ($clash['printed_teacher'] ?? ''));
            $printedId = $teacherIdByName[$printed] ?? 0;
            $held = $printedId > 0 && isset($busy[$printedId]) ? $busy[$printedId] : null;
            $clash['held_by'] = $held === null ? null : [
                'teacher' => (string) ($held['class_name'] !== '' ? $held['class_name'] : ''),
                'class' => (string) $held['class_name'],
                'subject_ar' => (string) $held['subject_ar'],
                'subject_en' => (string) $held['subject_en'],
            ];

            $candidates = [];
            foreach ($teachers as $teacher) {
                $teacherId = (int) $teacher['id'];
                if (isset($busy[$teacherId])) {
                    continue;
                }
                $candidates[] = [
                    'id' => $teacherId,
                    'name' => (string) $teacher['full_name'],
                    'code' => (string) ($teacher['teacher_code'] ?? ''),
                    'assigned' => isset($assignments[$teacherId][$classId][$subjectId]),
                ];
            }
            usort($candidates, static function (array $a, array $b): int {
                if ($a['assigned'] !== $b['assigned']) {
                    return $a['assigned'] ? -1 : 1;
                }
                return strcmp((string) $a['name'], (string) $b['name']);
            });
            $clash['candidates'] = $candidates;
        }
        unset($clash);

        return [
            'clashes' => $clashes,
            'resolutions' => $this->repo->resolutions($yearId),
        ];
    }

    /**
     * Resolve one printed clash. Availability is re-checked here, on the
     * server, in the same transaction that writes the lesson -- a candidate
     * list rendered minutes ago is a convenience, never an authorisation.
     *
     * @throws RuntimeException 'not_found'|'not_a_clash'|'year_closed'|'teacher_invalid'|'teacher_conflict'|'validation_error'
     */
    public function resolvePrintedClash(int $id, int $teacherId, ?string $note = null): void
    {
        $slot = $this->repo->find($id);
        if ($slot === null) {
            throw new RuntimeException('not_found');
        }
        if ((int) ($slot['teacher_id'] ?? 0) !== 0 || trim((string) ($slot['printed_teacher'] ?? '')) === '') {
            throw new RuntimeException('not_a_clash');
        }

        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null || (int) $slot['academic_year_id'] !== $yearId || $this->years->isClosed($yearId)) {
            throw new RuntimeException('year_closed');
        }

        $teacher = $this->teachers->find($teacherId);
        if ($teacher === null || (string) $teacher['status'] !== 'active') {
            throw new RuntimeException('teacher_invalid');
        }

        $day = (int) $slot['day_of_week'];
        $period = (int) $slot['period_number'];
        $classId = (int) $slot['class_id'];
        $subjectId = (int) $slot['subject_id'];

        if ($this->repo->teacherSlotTaken($yearId, $teacherId, $day, $period, $id)) {
            throw new RuntimeException('teacher_conflict');
        }

        $note = $note === null ? null : trim($note);
        if ($note === '') {
            $note = null;
        }
        if ($note !== null && (mb_strlen($note) > 255 || preg_match('/[\x00-\x1F\x7F]/u', $note) === 1)) {
            throw new RuntimeException('validation_error');
        }

        $userId = $this->userId();
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            if (!$this->assignments->existsForTuple($teacherId, $subjectId, $classId, $yearId)) {
                $this->assignments->create($teacherId, $subjectId, $classId, $yearId, 1);
            }
            $this->repo->markClashResolved($id, $teacherId, $userId, $note);
            $this->repo->recordResolution([
                'academic_year_id' => $yearId,
                'timetable_id' => $id,
                'class_id' => $classId,
                'day_of_week' => $day,
                'period_number' => $period,
                'printed_teacher' => (string) $slot['printed_teacher'],
                'previous_teacher_id' => null,
                'previous_subject_id' => $subjectId,
                'resolved_teacher_id' => $teacherId,
                'resolved_subject_id' => $subjectId,
                'note' => $note,
                'resolved_by' => $userId,
            ]);
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        ActivityLogger::log(
            'timetable.clash_resolved',
            'timetables',
            $id,
            'Printed clash resolved: ' . (string) $slot['printed_teacher'] . ' -> ' . (string) $teacher['full_name']
        );
    }

    /** @return list<array<string,mixed>> */
    public function resolutions(int $yearId): array
    {
        return $this->repo->resolutions($yearId);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

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

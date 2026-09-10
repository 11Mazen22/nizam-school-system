<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Repositories\AcademicYearRepository;
use App\Repositories\ClassRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PromotionLockRepository;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Nizam -- §I.1 student promotion, the single most sequence-sensitive
 * workflow in the whole system. preview() proposes defaults (step 2);
 * confirm() is steps 6-8 (lock, transaction, commit-or-roll-back);
 * undoLast() is step 9, the one sanctioned exception to §S-7's enrollment
 * immutability.
 */
final class PromotionService
{
    public function __construct(
        private readonly EnrollmentRepository $enrollments = new EnrollmentRepository(),
        private readonly GradeRepository $grades = new GradeRepository(),
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly PromotionLockRepository $locks = new PromotionLockRepository(),
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
    ) {
    }

    /** @return array<int, array<string, mixed>> one row per active source-year enrollment, with the proposed default action/grade and both candidate class lists already resolved. */
    public function preview(int $sourceYearId, int $targetYearId): array
    {
        $rows = [];
        foreach ($this->enrollments->activeInYear($sourceYearId) as $row) {
            $next = $this->grades->nextGradeAfter((int) $row['grade_id']);
            $rows[] = [
                'enrollment_id' => (int) $row['id'],
                'student_id' => (int) $row['student_id'],
                'student_name' => $row['full_name'],
                'student_code' => $row['student_code'],
                'current_grade_name_en' => $row['grade_name_en'],
                'current_grade_name_ar' => $row['grade_name_ar'],
                'current_class_name' => $row['class_name'],
                'forced_graduate' => $next === null,
                'default_action' => $next === null ? 'graduate' : 'promote',
                'next_grade_name_en' => $next['name_en'] ?? null,
                'next_grade_name_ar' => $next['name_ar'] ?? null,
                'promote_classes' => $next !== null
                    ? $this->classes->forGradeAndYear((int) $next['id'], $targetYearId) : [],
                'repeat_classes' => $this->classes->forGradeAndYear((int) $row['grade_id'], $targetYearId),
            ];
        }
        return $rows;
    }

    /**
     * @param array<int, array{action: string, class_id: ?int}> $decisions keyed by enrollment_id;
     *   action is one of promote|repeat|graduate|transferred|withdrawn.
     * @return array{counts: array<string,int>, skipped: int[]}
     * @throws RuntimeException 'lock_held'|'year_closed'
     */
    public function confirm(int $sourceYearId, int $targetYearId, array $decisions): array
    {
        // §I.4/§S-3: promotion writes to both years -- closing the source
        // year's enrollments and opening new ones in the target year -- so a
        // closed year on either side rejects the whole batch before it
        // starts. In the normal flow this can't actually happen (preview()
        // only ever lists a closed source year's zero remaining active rows,
        // and closing while genuinely mid-workflow is exactly what the
        // close-guard exists to prevent) -- this is the defensive backstop
        // for a stale/replayed submission racing a close in another tab.
        if ($this->years->isClosed($sourceYearId) || $this->years->isClosed($targetYearId)) {
            throw new RuntimeException('year_closed');
        }

        try {
            $this->locks->acquire($sourceYearId);
        } catch (PDOException $e) {
            throw new RuntimeException('lock_held');
        }

        try {
            return $this->runBatch($sourceYearId, $targetYearId, $decisions);
        } finally {
            $this->locks->release($sourceYearId);
        }
    }

    /** @param array<int, array{action: string, class_id: ?int}> $decisions */
    private function runBatch(int $sourceYearId, int $targetYearId, array $decisions): array
    {
        $counts = ['promoted' => 0, 'repeated' => 0, 'graduated' => 0, 'transferred' => 0, 'withdrawn' => 0];
        $skipped = [];

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            foreach ($decisions as $enrollmentId => $decision) {
                $enrollment = $this->enrollments->find((int) $enrollmentId);
                // Genuinely out of scope (foreign row, wrong year) -- defensive only,
                // the real form can't produce this; stays silent, not "skipped".
                if ($enrollment === null || (int) $enrollment['academic_year_id'] !== $sourceYearId) {
                    continue;
                }
                // Found live: this row is exactly step 8's "the batch ran twice" case --
                // a double-submit or back-button replay re-posts enrollment ids an earlier
                // run of this same batch already closed out (status left 'active' only by
                // the first run). Recording it in $skipped (not a silent continue) is what
                // makes the controller's existing "N students were skipped" warning fire
                // instead of a bare "0 promoted, 0 repeated..." that reads as if the
                // re-submit failed outright.
                if ($enrollment['status'] !== 'active') {
                    $skipped[] = (int) $enrollment['student_id'];
                    continue;
                }

                $studentId = (int) $enrollment['student_id'];
                $action = $decision['action'];

                if ($action === 'transferred' || $action === 'withdrawn') {
                    $this->enrollments->closeStatus((int) $enrollment['id'], $action);
                    $counts[$action]++;
                    continue;
                }

                // Recomputed here, authoritatively, from the enrollment's actual current
                // grade -- never trusts a client-submitted grade id (§Q "never trust
                // hidden form fields... for security"). If the next grade no longer
                // exists, graduate is forced regardless of what the client requested,
                // matching step 3's "forced when a grade has no defined successor."
                $next = $action === 'promote' ? $this->grades->nextGradeAfter((int) $enrollment['grade_id']) : null;
                if ($action === 'promote' && $next === null) {
                    $action = 'graduate';
                }

                if ($action === 'graduate') {
                    $this->enrollments->closeStatus((int) $enrollment['id'], 'graduated');
                    $counts['graduated']++;
                    continue;
                }

                // Step 8: a student who already has an enrollment in the target year
                // (the batch ran twice, or they were added there some other way) is
                // skipped with a friendly note, not a fatal error for the whole run.
                if ($this->enrollments->forStudentInYear($studentId, $targetYearId) !== null) {
                    $skipped[] = $studentId;
                    continue;
                }

                $targetGradeId = $action === 'promote' ? (int) $next['id'] : (int) $enrollment['grade_id'];
                $classId = $this->resolveTargetClass($decision['class_id'] ?? null, $targetGradeId, $targetYearId);

                $this->enrollments->closeStatus((int) $enrollment['id'], $action === 'promote' ? 'promoted' : 'repeated');
                $this->enrollments->createFromPromotion(
                    $studentId, $targetYearId, $targetGradeId, $classId, (int) $enrollment['id'], date('Y-m-d')
                );
                $counts[$action === 'promote' ? 'promoted' : 'repeated']++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $summary = "Promotion batch (year #{$sourceYearId} -> #{$targetYearId}): "
            . "{$counts['promoted']} promoted, {$counts['repeated']} repeated, {$counts['graduated']} graduated, "
            . "{$counts['transferred']} transferred, {$counts['withdrawn']} withdrawn"
            . (count($skipped) > 0 ? ', ' . count($skipped) . ' skipped (already enrolled in target year)' : '');
        // One summary entry for the whole batch, not one row per student (§I.1 step 7).
        ActivityLogger::log('student.promotion_batch', 'academic_years', $sourceYearId, $summary);

        return ['counts' => $counts, 'skipped' => $skipped];
    }

    /** A client-submitted class id is only ever honored if it genuinely belongs to the target grade/year -- otherwise the student is left unassigned rather than silently placed somewhere wrong. */
    private function resolveTargetClass(?int $classId, int $gradeId, int $academicYearId): ?int
    {
        if ($classId === null) {
            return null;
        }
        $class = $this->classes->find($classId);
        if ($class === null || (int) $class['grade_id'] !== $gradeId || (int) $class['academic_year_id'] !== $academicYearId) {
            return null;
        }
        return $classId;
    }

    /**
     * §I.1 step 9: reverts a single student's most recent promotion. $enrollmentId
     * is the STUDENT'S CURRENT (target-year) enrollment id -- the one the batch
     * just created. Available only while that row is still untouched (still
     * 'active', still has previous_enrollment_id set) and its source row is
     * still in the exact state promotion left it in ('promoted' or 'repeated') --
     * this is the single sanctioned exception to §S-7's enrollment immutability.
     *
     * @throws RuntimeException 'not_undoable'|'year_closed'
     */
    public function undoLast(int $enrollmentId): void
    {
        $current = $this->enrollments->find($enrollmentId);
        if ($current === null || $current['status'] !== 'active' || $current['previous_enrollment_id'] === null) {
            throw new RuntimeException('not_undoable');
        }

        $previous = $this->enrollments->find((int) $current['previous_enrollment_id']);
        if ($previous === null || !in_array($previous['status'], ['promoted', 'repeated'], true)) {
            throw new RuntimeException('not_undoable');
        }
        // §I.4: reopening the source enrollment is an UPDATE against
        // student_enrollments scoped to its year -- a closed year rejects it
        // like any other. Found live: an admin can close the source year
        // right after promotion succeeds (its own close-guard now passes,
        // since promotion is exactly what emptied it of active enrollments),
        // and undo must not silently reopen a row inside a year the admin
        // has since declared closed.
        if ($this->years->isClosed((int) $previous['academic_year_id'])) {
            throw new RuntimeException('year_closed');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->enrollments->deleteRow($enrollmentId);
            $this->enrollments->reopenToActive((int) $previous['id']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        ActivityLogger::log(
            'student.undo_promotion',
            'students',
            (int) $current['student_id'],
            "Promotion undone -- enrollment #{$previous['id']} reopened"
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Repositories\ExamRepository;
use RuntimeException;

/**
 * Nizam -- examination service.
 *
 * All score -> percentage -> grade resolution is delegated to
 * AcademicCalculationService. This class no longer carries a formula of its
 * own: it fetches rows, asks the calculator what they mean, and hands the
 * decorated rows to the view.
 */
final class ExamService
{
    public function __construct(
        private readonly ExamRepository $repo = new ExamRepository(),
        private readonly AcademicCalculationService $calculator = new AcademicCalculationService(),
    ) {
    }

    // ── Exam CRUD ────────────────────────────────────────────────────────────

    /** @throws RuntimeException 'no_active_year'|'validation_error' */
    public function createExam(string $nameEn, string $nameAr, int $term, float $maxScore, float $weight): int
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            throw new RuntimeException('no_active_year');
        }
        if ($nameEn === '' || $nameAr === '' || $term < 1 || $maxScore <= 0) {
            throw new RuntimeException('validation_error');
        }
        $id = $this->repo->create($yearId, $nameEn, $nameAr, $term, $maxScore, $weight);
        ActivityLogger::log('exam.create', 'exams', $id, "Exam '{$nameEn}' created");
        return $id;
    }

    public function updateExam(int $id, string $nameEn, string $nameAr, int $term, float $maxScore, float $weight): void
    {
        if ($nameEn === '' || $nameAr === '' || $term < 1 || $maxScore <= 0) {
            throw new RuntimeException('validation_error');
        }
        $this->repo->update($id, $nameEn, $nameAr, $term, $maxScore, $weight);
        ActivityLogger::log('exam.update', 'exams', $id, "Exam '{$nameEn}' updated");
    }

    public function deleteExam(int $id): void
    {
        $this->repo->delete($id);
        ActivityLogger::log('exam.delete', 'exams', $id, null);
    }

    // ── Scores ───────────────────────────────────────────────────────────────

    /**
     * Bulk-save scores for an exam+class from the grade-entry form.
     * $scores = [ studentId => ['subject_id' => int, 'score' => float|null, 'notes' => string|null] ]
     */
    public function saveScores(int $examId, int $classId, array $scores, ?int $userId): void
    {
        $exam = $this->repo->find($examId);
        if ($exam === null) {
            throw new RuntimeException('exam_not_found');
        }

        $yearId = (int) $exam['academic_year_id'];
        if ($yearId !== AcademicYearContext::activeYearId() || AcademicYearContext::isClosed()) {
            throw new RuntimeException('validation_error');
        }
        $class = (new \App\Repositories\ClassRepository())->find($classId);
        if ($class === null || (int) $class['academic_year_id'] !== $yearId) {
            throw new RuntimeException('validation_error');
        }
        $subjects = $this->repo->subjectsForClass($classId, $yearId);
        $subjectIds = array_map('intval', array_column($subjects, 'id'));
        $roster = $this->repo->scoresForExamClass($examId, $classId);
        $validated = [];

        foreach ($scores as $studentId => $entry) {
            if (!is_array($entry) || !isset($roster[$studentId])) {
                throw new RuntimeException('validation_error');
            }
            $subjectId = filter_var($entry['subject_id'] ?? null, FILTER_VALIDATE_INT);
            if (!in_array($subjectId, $subjectIds, true)) {
                throw new RuntimeException('validation_error');
            }
            $rawScore = $entry['score'] ?? '';
            if ($rawScore !== '' && $rawScore !== null &&
                (!is_scalar($rawScore) || !is_numeric($rawScore) || !is_finite((float) $rawScore)
                 || (float) $rawScore < 0 || (float) $rawScore > (float) $exam['max_score'])) {
                throw new RuntimeException('validation_error');
            }
            if (isset($entry['notes']) && !is_string($entry['notes'])) {
                throw new RuntimeException('validation_error');
            }
            $score = $rawScore !== '' && $rawScore !== null ? (float) $rawScore : null;
            $notes = isset($entry['notes']) && trim((string) $entry['notes']) !== ''
                ? trim((string) $entry['notes']) : null;
            $validated[] = [(int) $studentId, $subjectId, $score, $notes];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            foreach ($validated as [$studentId, $subjectId, $score, $notes]) {
                $this->repo->upsertScore($examId, $studentId, $subjectId, $score, $notes, $userId);
            }
            ActivityLogger::log('exam.scores_saved', 'exams', $examId,
                sprintf('Scores saved for exam #%d, class #%d', $examId, $classId));
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // ── Reporting ────────────────────────────────────────────────────────────

    public function getExamsForYear(int $yearId): array
    {
        return $this->repo->allForYear($yearId);
    }

    /** The grading scale currently in force, for the score-entry legend. */
    public function activeScale(): ?array
    {
        return $this->calculator->scale();
    }

    /**
     * Score-entry sheet with percentage and band resolved per student, so the
     * person entering marks can see what the mark they just typed means.
     * A student with no mark keeps a null percentage -- never a zero.
     */
    public function getScoreSheet(int $examId, int $classId, ?int $subjectId = null): array
    {
        $exam = $this->repo->find($examId);
        $sheet = $this->repo->scoresForExamClass($examId, $classId, $subjectId);
        if ($exam === null) {
            return $sheet;
        }

        $scale = $this->calculator->scale();
        foreach ($sheet as $studentId => $row) {
            $description = $this->calculator->describe($row['score'] ?? null, $exam['max_score'], $scale);
            $sheet[$studentId]['max_score'] = $exam['max_score'];
            $sheet[$studentId]['percentage'] = $description['percentage'];
            $sheet[$studentId]['band_label'] = $description['band_label'];
            $sheet[$studentId]['is_passing'] = $description['is_passing'];
        }

        return $sheet;
    }

    /**
     * Class ranking. The weighted average is computed by the shared calculator,
     * not by SQL, so a student's rank and their report-card average can never
     * disagree.
     */
    public function getClassRanking(int $classId, int $yearId): array
    {
        $scale = $this->calculator->scale();

        $grouped = [];
        $students = [];
        foreach ($this->repo->rankingRows($classId, $yearId) as $row) {
            $studentId = (int) $row['student_id'];
            $students[$studentId] = [
                'student_id'   => $studentId,
                'full_name'    => $row['full_name'],
                'student_code' => $row['student_code'],
            ];
            $grouped[$studentId][] = $row;
        }

        $ranking = [];
        foreach ($grouped as $studentId => $entries) {
            $percentage = $this->calculator->weightedPercentage($entries);
            $description = $this->calculator->describePercentage($percentage, $scale);
            $ranking[] = $students[$studentId] + [
                'weighted_avg' => $percentage,
                'band_label'   => $description['band_label'],
                'gpa_point'    => $description['gpa_point'],
                'is_passing'   => $description['is_passing'],
            ];
        }

        usort($ranking, static function (array $a, array $b): int {
            $left = $a['weighted_avg'] ?? -1.0;
            $right = $b['weighted_avg'] ?? -1.0;
            return $right <=> $left;
        });

        foreach ($ranking as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        return $ranking;
    }

    public function getStudentScoreGrid(int $studentId, int $yearId): array
    {
        return $this->repo->scoreGridForStudent($studentId, $yearId);
    }

    /**
     * Everything a report card or transcript prints, in one place:
     * per-exam rows, per-subject weighted results, and the overall result.
     * Both the PDF and any future transcript view read this shape, so the
     * printed grade and the stored configuration are always the same answer.
     *
     * @return array{
     *   grid: array<int, array<int, array<string,mixed>>>,
     *   subjects: array<int, array<string,mixed>>,
     *   overall: array<string,mixed>,
     *   scale: ?array
     * }
     */
    public function getStudentReport(int $studentId, int $yearId): array
    {
        $grid = $this->repo->scoreGridForStudent($studentId, $yearId);
        $scale = $this->calculator->scale();

        $subjects = [];
        $allEntries = [];

        foreach ($grid as $subjectId => $examRows) {
            $entries = [];
            $first = null;

            foreach ($examRows as $examId => $row) {
                $first ??= $row;
                $description = $this->calculator->describe(
                    $row['score'] ?? null,
                    $row['max_score'] ?? null,
                    $scale
                );
                $grid[$subjectId][$examId]['percentage'] = $description['percentage'];
                $grid[$subjectId][$examId]['band_label'] = $description['band_label'];
                $grid[$subjectId][$examId]['is_passing'] = $description['is_passing'];

                $entries[] = $row;
                $allEntries[] = $row;
            }

            $subjectPercentage = $this->calculator->weightedPercentage($entries);
            $subjectDescription = $this->calculator->describePercentage($subjectPercentage, $scale);

            $subjects[] = [
                'subject_id' => (int) $subjectId,
                'subject_en' => $first['subject_en'] ?? '',
                'subject_ar' => $first['subject_ar'] ?? '',
                'exam_count' => count($examRows),
                'percentage' => $subjectPercentage,
                'band_label' => $subjectDescription['band_label'],
                'gpa_point'  => $subjectDescription['gpa_point'],
                'is_passing' => $subjectDescription['is_passing'],
            ];
        }

        return [
            'grid'     => $grid,
            'subjects' => $subjects,
            'overall'  => $this->calculator->describePercentage(
                $this->calculator->weightedPercentage($allEntries),
                $scale
            ),
            'scale'    => $scale,
        ];
    }
}

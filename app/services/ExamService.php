<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Repositories\ExamRepository;
use RuntimeException;

final class ExamService
{
    public function __construct(
        private readonly ExamRepository $repo = new ExamRepository(),
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
     * $scores = [ studentId => ['score' => float|null, 'notes' => string|null] ]
     */
    public function saveScores(int $examId, int $classId, array $scores, ?int $userId): void
    {
        $exam = $this->repo->find($examId);
        if ($exam === null) {
            throw new RuntimeException('exam_not_found');
        }

        // Derive a single representative subject for the class/exam;
        // if multiple subjects exist the caller passes subject_id explicitly.
        // Fall back to first assigned subject.
        $yearId   = (int) $exam['academic_year_id'];
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
            $rawScore  = $entry['score'] ?? '';
            if ($rawScore !== '' && $rawScore !== null &&
                (!is_scalar($rawScore) || !is_numeric($rawScore) || !is_finite((float) $rawScore)
                 || (float) $rawScore < 0 || (float) $rawScore > (float) $exam['max_score'])) {
                throw new RuntimeException('validation_error');
            }
            if (isset($entry['notes']) && !is_string($entry['notes'])) {
                throw new RuntimeException('validation_error');
            }
            $score     = $rawScore !== '' && $rawScore !== null ? (float) $rawScore : null;
            $notes     = isset($entry['notes']) && trim((string)$entry['notes']) !== ''
                         ? trim((string)$entry['notes']) : null;
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

    public function getScoreSheet(int $examId, int $classId, ?int $subjectId = null): array
    {
        return $this->repo->scoresForExamClass($examId, $classId, $subjectId);
    }

    public function getClassRanking(int $classId, int $yearId): array
    {
        $rows = $this->repo->classRanking($classId, $yearId);
        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }
        return $rows;
    }

    public function getStudentScoreGrid(int $studentId, int $yearId): array
    {
        return $this->repo->scoreGridForStudent($studentId, $yearId);
    }
}

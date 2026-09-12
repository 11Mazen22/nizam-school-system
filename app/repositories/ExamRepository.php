<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class ExamRepository
{
    // ── Exams ────────────────────────────────────────────────────────────────

    public function allForYear(int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM exams WHERE academic_year_id = :year ORDER BY term ASC, id ASC'
        );
        $stmt->execute(['year' => $yearId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM exams WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $yearId, string $nameEn, string $nameAr, int $term, float $maxScore, float $weight): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO exams (academic_year_id, name_en, name_ar, term, max_score, weight)
             VALUES (:year, :en, :ar, :term, :max, :weight)'
        );
        $stmt->execute(['year' => $yearId, 'en' => $nameEn, 'ar' => $nameAr,
                        'term' => $term, 'max' => $maxScore, 'weight' => $weight]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, string $nameEn, string $nameAr, int $term, float $maxScore, float $weight): void
    {
        Database::connection()->prepare(
            'UPDATE exams SET name_en=:en, name_ar=:ar, term=:term, max_score=:max, weight=:weight WHERE id=:id'
        )->execute(['en' => $nameEn, 'ar' => $nameAr, 'term' => $term,
                    'max' => $maxScore, 'weight' => $weight, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM exams WHERE id = :id')->execute(['id' => $id]);
    }

    // ── Scores ───────────────────────────────────────────────────────────────

    /**
     * All scores for one exam + class, joined with student info.
     * Returns rows keyed by student_id for fast lookup.
     * @return array<int, array<string,mixed>>
     */
    public function scoresForExamClass(int $examId, int $classId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT es.*, s.full_name, s.student_code
             FROM student_enrollments e
             JOIN students s ON s.id = e.student_id
             LEFT JOIN exam_scores es ON es.student_id = e.student_id AND es.exam_id = :exam
             WHERE e.class_id = :class AND e.status = 'active'
             ORDER BY s.full_name ASC"
        );
        $stmt->execute(['exam' => $examId, 'class' => $classId]);
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['student_id']] = $row;
        }
        return $map;
    }

    /** Upsert a score (MySQL ON DUPLICATE KEY, Postgres ON CONFLICT). */
    public function upsertScore(int $examId, int $studentId, int $subjectId, ?float $score, ?string $notes, ?int $recordedBy): void
    {
        $pdo     = Database::connection();
        $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';

        if ($isPgsql) {
            $stmt = $pdo->prepare(
                'INSERT INTO exam_scores (exam_id, student_id, subject_id, score, notes, recorded_by)
                 VALUES (:exam, :student, :subject, :score, :notes, :by)
                 ON CONFLICT (exam_id, student_id, subject_id) DO UPDATE
                   SET score=EXCLUDED.score, notes=EXCLUDED.notes,
                       recorded_by=EXCLUDED.recorded_by, updated_at=CURRENT_TIMESTAMP'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO exam_scores (exam_id, student_id, subject_id, score, notes, recorded_by)
                 VALUES (:exam, :student, :subject, :score, :notes, :by)
                 ON DUPLICATE KEY UPDATE
                   score=VALUES(score), notes=VALUES(notes), recorded_by=VALUES(recorded_by)'
            );
        }
        $stmt->execute([
            'exam' => $examId, 'student' => $studentId, 'subject' => $subjectId,
            'score' => $score, 'notes' => $notes, 'by' => $recordedBy,
        ]);
    }

    /**
     * Full score grid for a student across all exams in a year per subject.
     * Returns [ subjectId => [ examId => scoreRow ] ]
     * @return array<int, array<int, array<string,mixed>>>
     */
    public function scoreGridForStudent(int $studentId, int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT es.*, e.name_en, e.name_ar, e.term, e.max_score, e.weight,
                    sub.name_en AS subject_en, sub.name_ar AS subject_ar
             FROM exam_scores es
             JOIN exams e ON e.id = es.exam_id
             JOIN subjects sub ON sub.id = es.subject_id
             WHERE es.student_id = :sid AND e.academic_year_id = :year
             ORDER BY sub.name_en ASC, e.term ASC'
        );
        $stmt->execute(['sid' => $studentId, 'year' => $yearId]);
        $grid = [];
        foreach ($stmt->fetchAll() as $row) {
            $grid[(int)$row['subject_id']][(int)$row['exam_id']] = $row;
        }
        return $grid;
    }

    /**
     * Class ranking: weighted average per student for a given year.
     * @return array<int, array<string,mixed>>  sorted best first
     */
    public function classRanking(int $classId, int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT s.id AS student_id, s.full_name, s.student_code,
                    ROUND(SUM(es.score * e.weight / e.max_score) / NULLIF(SUM(e.weight),0) * 100, 2) AS weighted_avg
             FROM student_enrollments en
             JOIN students s ON s.id = en.student_id
             JOIN exam_scores es ON es.student_id = s.id
             JOIN exams e ON e.id = es.exam_id
             WHERE en.class_id = :class AND en.status = 'active'
               AND e.academic_year_id = :year AND es.score IS NOT NULL
             GROUP BY s.id, s.full_name, s.student_code
             ORDER BY weighted_avg DESC"
        );
        $stmt->execute(['class' => $classId, 'year' => $yearId]);
        return $stmt->fetchAll();
    }

    /** Subjects for a class (via teacher_assignments). */
    public function subjectsForClass(int $classId, int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT sub.id, sub.name_en, sub.name_ar
             FROM teacher_assignments ta
             JOIN subjects sub ON sub.id = ta.subject_id
             WHERE ta.class_id = :class AND ta.academic_year_id = :year AND ta.status = 'active'
             ORDER BY sub.name_en ASC"
        );
        $stmt->execute(['class' => $classId, 'year' => $yearId]);
        return $stmt->fetchAll();
    }
}

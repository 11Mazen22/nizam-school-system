<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class AdmissionsRepository
{
    public function all(?string $status = null): array
    {
        $sql = 'SELECT a.*, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar,
                       y.label AS academic_year_label
                FROM admissions_applications a
                JOIN grades g ON g.id = a.grade_id
                JOIN academic_years y ON y.id = a.academic_year_id';
        $params = [];

        if ($status !== null) {
            $sql .= ' WHERE a.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY a.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.*, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar,
                    y.label AS academic_year_label
             FROM admissions_applications a
             JOIN grades g ON g.id = a.grade_id
             JOIN academic_years y ON y.id = a.academic_year_id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO admissions_applications (
                application_number, applicant_name, gender, date_of_birth,
                grade_id, academic_year_id, guardian_name, guardian_phone,
                guardian_email, guardian_relationship, status, created_at, updated_at
             ) VALUES (
                :app_no, :name, :gender, :dob, :grade_id, :year_id,
                :g_name, :g_phone, :g_email, :g_rel, :status, NOW(), NOW()
             )'
        );
        $stmt->execute([
            'app_no'   => $data['application_number'],
            'name'     => $data['applicant_name'],
            'gender'   => $data['gender'],
            'dob'      => $data['date_of_birth'],
            'grade_id' => $data['grade_id'],
            'year_id'  => $data['academic_year_id'],
            'g_name'   => $data['guardian_name'],
            'g_phone'  => $data['guardian_phone'],
            'g_email'  => $data['guardian_email'] ?? null,
            'g_rel'    => $data['guardian_relationship'] ?? 'father',
            'status'   => $data['status'] ?? 'inquiry',
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $notes = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE admissions_applications
             SET status = :status, assessment_notes = COALESCE(:notes, assessment_notes), updated_at = :now
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'notes'  => $notes,
            'now'    => date('Y-m-d H:i:s'),
            'id'     => $id,
        ]);
    }

    public function recordAssessment(int $id, string $date, float $score, ?string $notes = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE admissions_applications
             SET assessment_date = :adate, assessment_score = :score, assessment_notes = :notes,
                 status = \'review\', updated_at = :now
             WHERE id = :id'
        );
        $stmt->execute([
            'adate' => $date,
            'score' => $score,
            'notes' => $notes,
            'now'   => date('Y-m-d H:i:s'),
            'id'    => $id,
        ]);
    }

    public function recordDecision(int $id, string $decision, ?string $notes = null): void
    {
        $newStatus = ($decision === 'offer' || $decision === 'accepted') ? 'offer' : ($decision === 'waitlist' ? 'waitlisted' : 'rejected');
        $stmt = Database::connection()->prepare(
            'UPDATE admissions_applications
             SET decision_date = :ddate, decision_notes = :notes, status = :status, updated_at = :now
             WHERE id = :id'
        );
        $stmt->execute([
            'ddate'  => date('Y-m-d'),
            'notes'  => $notes,
            'status' => $newStatus,
            'now'    => date('Y-m-d H:i:s'),
            'id'     => $id,
        ]);
    }

    public function markEnrolled(int $id, int $studentId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE admissions_applications
             SET status = \'enrolled\', enrolled_student_id = :student_id, updated_at = :now
             WHERE id = :id'
        );
        $stmt->execute([
            'student_id' => $studentId,
            'now'        => date('Y-m-d H:i:s'),
            'id'         => $id,
        ]);
    }

    public function countByStatus(): array
    {
        $rows = Database::connection()
            ->query('SELECT status, COUNT(*) as cnt FROM admissions_applications GROUP BY status')
            ->fetchAll();
        $counts = [
            'inquiry'     => 0,
            'application' => 0,
            'review'      => 0,
            'assessment'  => 0,
            'offer'       => 0,
            'enrolled'    => 0,
            'waitlisted'  => 0,
            'rejected'    => 0,
        ];
        foreach ($rows as $r) {
            $counts[$r['status']] = (int) $r['cnt'];
        }
        return $counts;
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class WelfareRepository
{
    // ── Disciplinary ─────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string,mixed>>
     */
    public function disciplinaryForStudent(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.*, u.full_name AS reporter_name
             FROM disciplinary_records d
             LEFT JOIN users u ON u.id = d.reported_by
             WHERE d.student_id = :sid
             ORDER BY d.incident_date DESC'
        );
        $stmt->execute(['sid' => $studentId]);
        return $stmt->fetchAll();
    }

    public function allDisciplinary(int $yearId, ?string $type = null, ?string $severity = null): array
    {
        $sql    = 'SELECT d.*, s.full_name, s.student_code, u.full_name AS reporter_name
                   FROM disciplinary_records d
                   JOIN students s ON s.id = d.student_id
                   LEFT JOIN users u ON u.id = d.reported_by
                   WHERE d.academic_year_id = :year';
        $params = ['year' => $yearId];
        if ($type !== null)     { $sql .= ' AND d.type = :type';         $params['type']     = $type; }
        if ($severity !== null) { $sql .= ' AND d.severity = :severity'; $params['severity'] = $severity; }
        $sql .= ' ORDER BY d.incident_date DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createDisciplinary(
        int $yearId, int $studentId, string $date, string $type, string $severity,
        string $title, string $description, ?string $actionTaken, ?int $reportedBy
    ): int {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO disciplinary_records
             (academic_year_id, student_id, incident_date, type, severity, title, description, action_taken, reported_by)
             VALUES (:year, :sid, :date, :type, :sev, :title, :desc, :action, :by)'
        );
        $stmt->execute([
            'year' => $yearId, 'sid' => $studentId, 'date' => $date, 'type' => $type,
            'sev' => $severity, 'title' => $title, 'desc' => $description,
            'action' => $actionTaken, 'by' => $reportedBy,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function deleteDisciplinary(int $id): void
    {
        Database::connection()->prepare('DELETE FROM disciplinary_records WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public function findDisciplinary(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM disciplinary_records WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Health ───────────────────────────────────────────────────────────────

    public function healthForStudent(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT h.*, u.full_name AS logger_name
             FROM health_records h
             LEFT JOIN users u ON u.id = h.logged_by
             WHERE h.student_id = :sid
             ORDER BY h.date_logged DESC'
        );
        $stmt->execute(['sid' => $studentId]);
        return $stmt->fetchAll();
    }

    public function allHealth(?string $recordType = null): array
    {
        $sql = 'SELECT h.*, s.full_name, s.student_code, u.full_name AS logger_name
                FROM health_records h
                JOIN students s ON s.id = h.student_id
                LEFT JOIN users u ON u.id = h.logged_by
                WHERE 1=1';
        $params = [];
        if ($recordType !== null) { $sql .= ' AND h.record_type = :type'; $params['type'] = $recordType; }
        $sql .= ' ORDER BY h.date_logged DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createHealth(
        int $studentId, string $recordType, string $dateLogged,
        string $title, string $details, ?int $loggedBy
    ): int {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO health_records (student_id, record_type, date_logged, title, details, logged_by)
             VALUES (:sid, :type, :date, :title, :details, :by)'
        );
        $stmt->execute([
            'sid' => $studentId, 'type' => $recordType, 'date' => $dateLogged,
            'title' => $title, 'details' => $details, 'by' => $loggedBy,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function deleteHealth(int $id): void
    {
        Database::connection()->prepare('DELETE FROM health_records WHERE id = :id')
            ->execute(['id' => $id]);
    }
}

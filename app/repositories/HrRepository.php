<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class HrRepository
{
    public function allDepartments(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM staff_departments ORDER BY name_en ASC');
        return $stmt->fetchAll() ?: [];
    }

    public function createDepartment(string $code, string $nameEn, string $nameAr): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO staff_departments (code, name_en, name_ar) VALUES (:c, :en, :ar)');
        $stmt->execute(['c' => $code, 'en' => $nameEn, 'ar' => $nameAr]);
        return (int) $pdo->lastInsertId();
    }

    public function allDesignations(): array
    {
        $stmt = Database::connection()->query(
            'SELECT d.*, dept.name_en AS department_name_en, dept.name_ar AS department_name_ar
             FROM staff_designations d
             JOIN staff_departments dept ON dept.id = d.department_id
             ORDER BY d.title_en ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function createDesignation(int $departmentId, string $titleEn, string $titleAr): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO staff_designations (department_id, title_en, title_ar) VALUES (:dept, :en, :ar)'
        );
        $stmt->execute(['dept' => $departmentId, 'en' => $titleEn, 'ar' => $titleAr]);
        return (int) $pdo->lastInsertId();
    }

    public function allLeaves(?string $status = null): array
    {
        $sql = 'SELECT l.*, t.full_name AS teacher_name, t.teacher_code, t.phone AS teacher_phone,
                       u.full_name AS approved_by_name
                FROM staff_leaves l
                JOIN teachers t ON t.id = l.teacher_id
                LEFT JOIN users u ON u.id = l.approved_by';
        $params = [];
        if ($status !== null) {
            $sql .= ' WHERE l.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY l.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function createLeave(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO staff_leaves (
                teacher_id, leave_type, start_date, end_date, reason, status, created_at
             ) VALUES (
                :t_id, :type, :s_date, :e_date, :reason, "pending", :now
             )'
        );
        $stmt->execute([
            't_id'   => $data['teacher_id'],
            'type'   => $data['leave_type'] ?? 'annual',
            's_date' => $data['start_date'],
            'e_date' => $data['end_date'],
            'reason' => $data['reason'],
            'now'    => date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function updateLeaveStatus(int $id, string $status, int $approvedBy, ?string $notes = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE staff_leaves
             SET status = :status, approved_by = :approver, action_notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            'status'   => $status,
            'approver' => $approvedBy,
            'notes'    => $notes,
            'id'       => $id,
        ]);
    }
}

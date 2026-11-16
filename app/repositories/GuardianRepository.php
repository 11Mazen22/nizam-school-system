<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class GuardianRepository
{
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT g.*, 
                    (SELECT COUNT(*) FROM student_guardians sg WHERE sg.guardian_id = g.id) AS student_count
             FROM guardians g
             ORDER BY g.full_name ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM guardians WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByPhone(string $phone): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM guardians WHERE phone = :phone LIMIT 1');
        $stmt->execute(['phone' => $phone]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO guardians (
                full_name, phone, email, national_id, relationship_type,
                occupation, address, notes, created_at, updated_at
             ) VALUES (
                :full_name, :phone, :email, :national_id, :relationship_type,
                :occupation, :address, :notes, NOW(), NOW()
             )'
        );
        $stmt->execute([
            'full_name'         => $data['full_name'],
            'phone'             => $data['phone'],
            'email'             => $data['email'] ?? null,
            'national_id'       => $data['national_id'] ?? null,
            'relationship_type' => $data['relationship_type'] ?? 'father',
            'occupation'        => $data['occupation'] ?? null,
            'address'           => $data['address'] ?? null,
            'notes'             => $data['notes'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE guardians SET
                full_name = :full_name,
                phone = :phone,
                email = :email,
                national_id = :national_id,
                relationship_type = :relationship_type,
                occupation = :occupation,
                address = :address,
                notes = :notes,
                updated_at = :now
             WHERE id = :id'
        );
        $stmt->execute([
            'full_name'         => $data['full_name'],
            'phone'             => $data['phone'],
            'email'             => $data['email'] ?? null,
            'national_id'       => $data['national_id'] ?? null,
            'relationship_type' => $data['relationship_type'] ?? 'father',
            'occupation'        => $data['occupation'] ?? null,
            'address'           => $data['address'] ?? null,
            'notes'             => $data['notes'] ?? null,
            'now'               => date('Y-m-d H:i:s'),
            'id'                => $id,
        ]);
    }

    public function getForStudent(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT g.*, sg.relationship, sg.is_primary, sg.is_emergency, sg.can_pickup, sg.receives_reports
             FROM student_guardians sg
             JOIN guardians g ON g.id = sg.guardian_id
             WHERE sg.student_id = :student_id
             ORDER BY sg.is_primary DESC, g.full_name ASC'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll() ?: [];
    }

    public function getStudentsForGuardian(int $guardianId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.id, s.student_code, s.full_name, s.gender, s.date_of_birth, s.status,
                    sg.relationship, sg.is_primary,
                    c.name AS class_name, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar
             FROM student_guardians sg
             JOIN students s ON s.id = sg.student_id
             LEFT JOIN student_enrollments se ON se.student_id = s.id AND se.status = \'active\'
             LEFT JOIN classes c ON c.id = se.class_id
             LEFT JOIN grades g ON g.id = se.grade_id
             WHERE sg.guardian_id = :guardian_id
             ORDER BY s.full_name ASC'
        );
        $stmt->execute(['guardian_id' => $guardianId]);
        return $stmt->fetchAll() ?: [];
    }

    public function linkStudent(
        int $studentId,
        int $guardianId,
        string $relationship = 'father',
        bool $isPrimary = true,
        bool $isEmergency = true,
        bool $canPickup = true,
        bool $receivesReports = true
    ): void {
        $pdo = Database::connection();
        $primary = $isPrimary ? 'TRUE' : 'FALSE';
        $emergency = $isEmergency ? 'TRUE' : 'FALSE';
        $pickup = $canPickup ? 'TRUE' : 'FALSE';
        $reports = $receivesReports ? 'TRUE' : 'FALSE';
        $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
            ? 'INSERT INTO student_guardians (student_id, guardian_id, relationship, is_primary, is_emergency, can_pickup, receives_reports, created_at)
               VALUES (:s, :g, :rel, ' . $primary . ', ' . $emergency . ', ' . $pickup . ', ' . $reports . ', :now)
               ON CONFLICT (student_id, guardian_id) DO UPDATE SET
               relationship = EXCLUDED.relationship, is_primary = EXCLUDED.is_primary,
               is_emergency = EXCLUDED.is_emergency, can_pickup = EXCLUDED.can_pickup,
               receives_reports = EXCLUDED.receives_reports'
            : 'INSERT INTO student_guardians (student_id, guardian_id, relationship, is_primary, is_emergency, can_pickup, receives_reports, created_at)
               VALUES (:s, :g, :rel, ' . $primary . ', ' . $emergency . ', ' . $pickup . ', ' . $reports . ', :now)
               ON DUPLICATE KEY UPDATE
               relationship = VALUES(relationship), is_primary = VALUES(is_primary),
               is_emergency = VALUES(is_emergency), can_pickup = VALUES(can_pickup),
               receives_reports = VALUES(receives_reports)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            's'    => $studentId,
            'g'    => $guardianId,
            'rel'  => $relationship,
            'now'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function unlinkStudent(int $studentId, int $guardianId): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM student_guardians WHERE student_id = :s AND guardian_id = :g'
        );
        $stmt->execute(['s' => $studentId, 'g' => $guardianId]);
    }
}

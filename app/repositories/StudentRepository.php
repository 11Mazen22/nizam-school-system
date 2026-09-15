<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Services\IdPattern;

final class StudentRepository
{
    /** Maximum rows per page — O-25: cap is enforced here server-side; callers may not exceed this. */
    public const MAX_PER_PAGE = 20;

    /** @return array<int, array<string, mixed>> */
    public function all(string $status = 'active'): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM students WHERE status = :status ORDER BY full_name ASC');
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    /**
     * Return one page of students. The $perPage value is hard-capped at MAX_PER_PAGE
     * server-side regardless of what the caller supplies — this is the O-25 enforcement
     * point. Crafted values such as 999999 are silently reduced to MAX_PER_PAGE.
     *
     * @return array<int, array<string, mixed>>
     */
    public function paginate(int $page, int $perPage, string $status = 'active', string $term = '', int $academicYearId = 0, int $gradeId = 0, int $classId = 0): array
    {
        // O-25: cap regardless of caller-supplied value.
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $pdo = Database::connection();
        
        $sql = 'SELECT s.*, c.name as class_name, g.name_en as grade_name_en, g.name_ar as grade_name_ar 
                FROM students s 
                LEFT JOIN student_enrollments e ON e.student_id = s.id AND e.academic_year_id = :year_id
                LEFT JOIN classes c ON c.id = e.class_id
                LEFT JOIN grades g ON g.id = c.grade_id
                WHERE s.status = :status';
        
        if ($term !== '') {
            $sql .= ' AND (s.full_name LIKE :term1 OR s.student_code LIKE :term2)';
        }
        if ($gradeId > 0) {
            $sql .= ' AND c.grade_id = :grade_id';
        }
        if ($classId > 0) {
            $sql .= ' AND e.class_id = :class_id';
        }
        
        $sql .= ' ORDER BY s.full_name ASC LIMIT :limit OFFSET :offset';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':year_id', $academicYearId, \PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        if ($term !== '') {
            $like = '%' . $term . '%';
            $stmt->bindValue(':term1', $like);
            $stmt->bindValue(':term2', $like);
        }
        if ($gradeId > 0) {
            $stmt->bindValue(':grade_id', $gradeId, \PDO::PARAM_INT);
        }
        if ($classId > 0) {
            $stmt->bindValue(':class_id', $classId, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit',  $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Return the total count of students matching the same status + search filter used
     * by paginate() — ensures pagination math stays consistent with the displayed rows.
     */
    public function countFiltered(string $status = 'active', string $term = '', int $academicYearId = 0, int $gradeId = 0, int $classId = 0): int
    {
        $pdo = Database::connection();
        
        $sql = 'SELECT COUNT(s.id) 
                FROM students s 
                LEFT JOIN student_enrollments e ON e.student_id = s.id AND e.academic_year_id = :year_id
                LEFT JOIN classes c ON c.id = e.class_id
                WHERE s.status = :status';
                
        if ($term !== '') {
            $sql .= ' AND (s.full_name LIKE :term1 OR s.student_code LIKE :term2)';
        }
        if ($gradeId > 0) {
            $sql .= ' AND c.grade_id = :grade_id';
        }
        if ($classId > 0) {
            $sql .= ' AND e.class_id = :class_id';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':year_id', $academicYearId, \PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        if ($term !== '') {
            $like = '%' . $term . '%';
            $stmt->bindValue(':term1', $like);
            $stmt->bindValue(':term2', $like);
        }
        if ($gradeId > 0) {
            $stmt->bindValue(':grade_id', $gradeId, \PDO::PARAM_INT);
        }
        if ($classId > 0) {
            $stmt->bindValue(':class_id', $classId, \PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function search(string $term, string $status = 'active'): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM students WHERE status = :status
             AND (full_name LIKE :term1 OR student_code LIKE :term2)
             ORDER BY full_name ASC'
        );
        $like = '%' . $term . '%';
        $stmt->execute(['status' => $status, 'term1' => $like, 'term2' => $like]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM students WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array{id:int} §O-7: insert first (placeholder code), then UPDATE the code from the row's own new id -- never a precomputed MAX+1. */
    public function create(
        string $fullName,
        string $gender,
        string $dateOfBirth,
        string $religion,
        ?string $phone,
        ?string $guardianPhone,
        ?string $address,
        ?string $notes,
        string $codePattern,
        string $codeYear
    ): int {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO students (student_code, full_name, gender, date_of_birth, religion, phone, guardian_phone, address, notes, status)
             VALUES (\'\', :name, :gender, :dob, :religion, :phone, :guardian, :address, :notes, \'active\')'
        );
        $stmt->execute([
            'name' => $fullName, 'gender' => $gender, 'dob' => $dateOfBirth, 'religion' => $religion,
            'phone' => $phone, 'guardian' => $guardianPhone, 'address' => $address, 'notes' => $notes,
        ]);
        $id = (int) $pdo->lastInsertId();

        $code = IdPattern::render($codePattern, $id, $codeYear);
        $update = $pdo->prepare('UPDATE students SET student_code = :code WHERE id = :id');
        $update->execute(['code' => $code, 'id' => $id]);

        return $id;
    }

    public function update(
        int $id,
        string $fullName,
        string $gender,
        string $dateOfBirth,
        string $religion,
        ?string $phone,
        ?string $guardianPhone,
        ?string $address,
        ?string $notes
    ): void {
        $stmt = Database::connection()->prepare(
            'UPDATE students SET full_name = :name, gender = :gender, date_of_birth = :dob, religion = :religion,
             phone = :phone, guardian_phone = :guardian, address = :address, notes = :notes WHERE id = :id'
        );
        $stmt->execute([
            'name' => $fullName, 'gender' => $gender, 'dob' => $dateOfBirth, 'religion' => $religion,
            'phone' => $phone, 'guardian' => $guardianPhone, 'address' => $address, 'notes' => $notes, 'id' => $id,
        ]);
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE students SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** Decision #11 photo storage -- a separate, orthogonal write from update(), matching setStatus()'s own single-column pattern. */
    public function updatePhoto(int $id, ?string $photoPath): void
    {
        $stmt = Database::connection()->prepare('UPDATE students SET photo_path = :photo WHERE id = :id');
        $stmt->execute(['photo' => $photoPath, 'id' => $id]);
    }
}

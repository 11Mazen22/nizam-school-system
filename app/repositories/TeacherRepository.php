<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Services\IdPattern;

final class TeacherRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(string $status = 'active'): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM teachers WHERE status = :status ORDER BY full_name ASC');
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> case-insensitive full_name search, list screen's search box */
    public function search(string $term, string $status = 'active'): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM teachers WHERE status = :status AND full_name LIKE :term ORDER BY full_name ASC'
        );
        $stmt->execute(['status' => $status, 'term' => '%' . $term . '%']);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM teachers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** §O-7: insert first, then assign the code from the row's own id -- never a precomputed MAX+1. */
    public function create(string $fullName, ?string $phone, ?string $email, string $codePattern): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO teachers (teacher_code, full_name, phone, email, status)
             VALUES (:code, :name, :phone, :email, \'active\')'
        );
        // Placeholder code, immediately overwritten below once the real id exists (§O-7).
        $stmt->execute(['code' => '', 'name' => $fullName, 'phone' => $phone, 'email' => $email]);
        $id = (int) $pdo->lastInsertId();

        $code = IdPattern::render($codePattern, $id);
        $update = $pdo->prepare('UPDATE teachers SET teacher_code = :code WHERE id = :id');
        $update->execute(['code' => $code, 'id' => $id]);

        return $id;
    }

    public function update(int $id, string $fullName, ?string $phone, ?string $email): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE teachers SET full_name = :name, phone = :phone, email = :email WHERE id = :id'
        );
        $stmt->execute(['name' => $fullName, 'phone' => $phone, 'email' => $email, 'id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE teachers SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** Decision #11 photo storage -- a separate, orthogonal write from update(), matching setStatus()'s own single-column pattern. */
    public function updatePhoto(int $id, ?string $photoPath): void
    {
        $stmt = Database::connection()->prepare('UPDATE teachers SET photo_path = :photo WHERE id = :id');
        $stmt->execute(['photo' => $photoPath, 'id' => $id]);
    }

    /** True if this teacher has any active assignment in the given year (§I.8 archive-cascade check). */
    public function hasActiveAssignmentInYear(int $teacherId, int $academicYearId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM teacher_assignments
             WHERE teacher_id = :tid AND academic_year_id = :year AND status = 'active'"
        );
        $stmt->execute(['tid' => $teacherId, 'year' => $academicYearId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}

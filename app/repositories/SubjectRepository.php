<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class SubjectRepository
{
    /** @return array<int, array<string, mixed>> */
    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM subjects' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name_en ASC';
        return Database::connection()->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM subjects WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM subjects WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $code, string $nameEn, string $nameAr): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO subjects (code, name_en, name_ar, is_active) VALUES (:code, :en, :ar, 1)'
        );
        $stmt->execute(['code' => $code, 'en' => $nameEn, 'ar' => $nameAr]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, string $code, string $nameEn, string $nameAr): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE subjects SET code = :code, name_en = :en, name_ar = :ar WHERE id = :id'
        );
        $stmt->execute(['code' => $code, 'en' => $nameEn, 'ar' => $nameAr, 'id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE subjects SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    /** @return array<int, array<string, mixed>> subjects this teacher is qualified for (teacher_subjects, §O-9) */
    public function qualifiedForTeacher(int $teacherId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.* FROM subjects s
             JOIN teacher_subjects ts ON ts.subject_id = s.id
             WHERE ts.teacher_id = :tid ORDER BY s.name_en ASC'
        );
        $stmt->execute(['tid' => $teacherId]);
        return $stmt->fetchAll();
    }

    /** @param int[] $subjectIds */
    public function setTeacherQualifications(int $teacherId, array $subjectIds): void
    {
        $pdo = Database::connection();
        $del = $pdo->prepare('DELETE FROM teacher_subjects WHERE teacher_id = :tid');
        $del->execute(['tid' => $teacherId]);

        $ins = $pdo->prepare('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (:tid, :sid)');
        foreach (array_unique($subjectIds) as $subjectId) {
            $ins->execute(['tid' => $teacherId, 'sid' => $subjectId]);
        }
    }

    /** True if teacher_subjects has a row for this pair (§O-9 soft qualification check). */
    public function teacherIsQualified(int $teacherId, int $subjectId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = :tid AND subject_id = :sid'
        );
        $stmt->execute(['tid' => $teacherId, 'sid' => $subjectId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}

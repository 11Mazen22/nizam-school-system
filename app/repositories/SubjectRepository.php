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
            "SELECT s.* FROM subjects s
             JOIN teacher_subjects ts ON ts.subject_id = s.id
             WHERE ts.teacher_id = :tid AND ts.source = 'declared'
             ORDER BY s.name_en ASC"
        );
        $stmt->execute(['tid' => $teacherId]);
        return $stmt->fetchAll();
    }

    /**
     * Replace the teacher's declared qualifications without erasing what the
     * timetable says they teach. A pair can only occupy one row (the table's
     * primary key is teacher+subject), so a declared pair is stored as
     * 'declared' and every other taught pair is restored as 'timetable'.
     *
     * @param int[] $subjectIds
     */
    public function setTeacherQualifications(int $teacherId, array $subjectIds): void
    {
        $pdo = Database::connection();
        $taught = $pdo->prepare('SELECT DISTINCT subject_id FROM timetables WHERE teacher_id = :tid');
        $taught->execute(['tid' => $teacherId]);
        $taughtIds = array_map('intval', $taught->fetchAll(\PDO::FETCH_COLUMN));

        $declared = array_values(array_unique(array_map('intval', $subjectIds)));

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM teacher_subjects WHERE teacher_id = :tid')->execute(['tid' => $teacherId]);

            $insert = $pdo->prepare('INSERT INTO teacher_subjects (teacher_id, subject_id, source) VALUES (:tid, :sid, :source)');
            foreach ($declared as $subjectId) {
                $insert->execute(['tid' => $teacherId, 'sid' => $subjectId, 'source' => 'declared']);
            }
            foreach (array_diff($taughtIds, $declared) as $subjectId) {
                $insert->execute(['tid' => $teacherId, 'sid' => $subjectId, 'source' => 'timetable']);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** True if teacher_subjects has a row for this pair (§O-9 soft qualification check). */
    public function teacherIsQualified(int $teacherId, int $subjectId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM teacher_subjects
             WHERE teacher_id = :tid AND subject_id = :sid AND source = 'declared'"
        );
        $stmt->execute(['tid' => $teacherId, 'sid' => $subjectId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}

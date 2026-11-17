<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Nizam -- roster verification persistence.
 *
 * Class rosters are read in import order (`students.id` ascending): the CSV
 * pipeline inserted the students exactly as the page lists them (right-hand
 * table first, then the left), so the screen shows the same order the school
 * is looking at on paper.
 */
final class RosterVerificationRepository
{
    /** @return list<array<string,mixed>> */
    public function classesForYear(int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.id, c.name, c.pathway_ar, c.capacity,
                    g.name_ar AS grade_name_ar, g.name_en AS grade_name_en,
                    rv.verified_at, rv.student_count AS verified_count, rv.fingerprint,
                    rv.note, u.full_name AS verified_by_name
             FROM classes c
             JOIN grades g ON g.id = c.grade_id
             LEFT JOIN roster_verifications rv
                    ON rv.class_id = c.id AND rv.academic_year_id = c.academic_year_id
             LEFT JOIN users u ON u.id = rv.verified_by
             WHERE c.academic_year_id = :year AND c.is_active = 1
             ORDER BY c.name ASC'
        );
        $stmt->execute(['year' => $yearId]);

        return $stmt->fetchAll();
    }

    public function findClass(int $classId, int $yearId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.id, c.name, c.pathway_ar, c.capacity,
                    g.name_ar AS grade_name_ar, g.name_en AS grade_name_en
             FROM classes c
             JOIN grades g ON g.id = c.grade_id
             WHERE c.id = :id AND c.academic_year_id = :year AND c.is_active = 1'
        );
        $stmt->execute(['id' => $classId, 'year' => $yearId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function studentsForClass(int $classId, int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT s.id, s.student_code, s.full_name, s.gender, s.religion,
                    s.date_of_birth, s.class_attribute_raw, s.class_attribute_source
             FROM students s
             JOIN student_enrollments e ON e.student_id = s.id
             WHERE e.class_id = :class AND e.academic_year_id = :year
               AND e.status = 'active' AND s.status = 'active'
             ORDER BY s.id ASC"
        );
        $stmt->execute(['class' => $classId, 'year' => $yearId]);

        return $stmt->fetchAll();
    }

    public function verificationFor(int $classId, int $yearId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT rv.*, u.full_name AS verified_by_name
             FROM roster_verifications rv
             LEFT JOIN users u ON u.id = rv.verified_by
             WHERE rv.class_id = :class AND rv.academic_year_id = :year'
        );
        $stmt->execute(['class' => $classId, 'year' => $yearId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function save(int $classId, int $yearId, int $studentCount, string $fingerprint, ?string $note, ?int $userId): void
    {
        $pdo = Database::connection();
        // MySQL upsert syntax and Postgres ON CONFLICT are not interchangeable;
        // the rest of the repositories branch on the driver the same way.
        $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $sql = $isPgsql
            ? "INSERT INTO roster_verifications
                    (academic_year_id, class_id, status, student_count, fingerprint, note, verified_by, verified_at)
                 VALUES (:year, :class, 'verified', :count, :fingerprint, :note, :user, CURRENT_TIMESTAMP)
                 ON CONFLICT (academic_year_id, class_id) DO UPDATE SET
                    status = 'verified',
                    student_count = EXCLUDED.student_count,
                    fingerprint = EXCLUDED.fingerprint,
                    note = EXCLUDED.note,
                    verified_by = EXCLUDED.verified_by,
                    verified_at = CURRENT_TIMESTAMP"
            : "INSERT INTO roster_verifications
                    (academic_year_id, class_id, status, student_count, fingerprint, note, verified_by, verified_at)
                 VALUES (:year, :class, 'verified', :count, :fingerprint, :note, :user, CURRENT_TIMESTAMP)
                 ON DUPLICATE KEY UPDATE
                    status = 'verified',
                    student_count = VALUES(student_count),
                    fingerprint = VALUES(fingerprint),
                    note = VALUES(note),
                    verified_by = VALUES(verified_by),
                    verified_at = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'year' => $yearId,
            'class' => $classId,
            'count' => $studentCount,
            'fingerprint' => $fingerprint,
            'note' => $note,
            'user' => $userId,
        ]);
    }

    public function delete(int $classId, int $yearId): void
    {
        Database::connection()->prepare(
            'DELETE FROM roster_verifications WHERE class_id = :class AND academic_year_id = :year'
        )->execute(['class' => $classId, 'year' => $yearId]);
    }
}

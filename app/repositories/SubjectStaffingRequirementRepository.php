<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/** Nizam -- per-subject-per-year staffing targets (§I.6, §J's "lives in subject_staffing_requirements, not the settings table"). */
final class SubjectStaffingRequirementRepository
{
    public function find(int $subjectId, int $academicYearId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM subject_staffing_requirements WHERE subject_id = :s AND academic_year_id = :y'
        );
        $stmt->execute(['s' => $subjectId, 'y' => $academicYearId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> every subject THAT HAS a configured requirement this year (§I.6: no row = excluded, never "requires zero"). */
    public function allForYear(int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, s.name_en, s.name_ar, s.code
             FROM subject_staffing_requirements r JOIN subjects s ON s.id = r.subject_id
             WHERE r.academic_year_id = :y ORDER BY s.name_en ASC'
        );
        $stmt->execute(['y' => $academicYearId]);
        return $stmt->fetchAll();
    }

    /**
     * §K.6 Staffing shortage, §I.6's exact formula in one query (a
     * correlated subquery for the distinct-teacher count, not a per-subject
     * loop -- avoids N+1 across what could be dozens of subjects). Only
     * subjects with a configured requirement appear, per §I.6's own rule:
     * "a subject with no row here is excluded entirely, not treated as
     * requires zero." Sorted by shortage descending -- "worst gap first."
     *
     * @return array<int, array{subject_id:int, name_en:string, name_ar:string, required_teachers:int, available_teachers:int, shortage:int}>
     */
    public function shortageReportForYear(int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT r.subject_id, s.name_en, s.name_ar, r.required_teachers,
                    (SELECT COUNT(DISTINCT a.teacher_id) FROM teacher_assignments a
                     WHERE a.subject_id = r.subject_id AND a.academic_year_id = r.academic_year_id
                       AND a.status = 'active') AS available_teachers
             FROM subject_staffing_requirements r
             JOIN subjects s ON s.id = r.subject_id
             WHERE r.academic_year_id = :y
             ORDER BY (CAST(r.required_teachers AS SIGNED) -
                 (SELECT COUNT(DISTINCT a.teacher_id) FROM teacher_assignments a
                  WHERE a.subject_id = r.subject_id AND a.academic_year_id = r.academic_year_id
                    AND a.status = 'active')) DESC"
        );
        $stmt->execute(['y' => $academicYearId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['subject_id'] = (int) $row['subject_id'];
            $row['required_teachers'] = (int) $row['required_teachers'];
            $row['available_teachers'] = (int) $row['available_teachers'];
            $row['shortage'] = $row['required_teachers'] - $row['available_teachers'];
        }
        unset($row);
        return $rows;
    }

    public function upsert(int $subjectId, int $academicYearId, int $requiredTeachers): void
    {
        $pdo = Database::connection();
        // Same upsert, two dialects: MySQL's ON DUPLICATE KEY UPDATE has no
        // direct Postgres equivalent -- ON CONFLICT needs the actual
        // constraint's columns named explicitly (uq_ssr_subject_year).
        $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
            ? 'INSERT INTO subject_staffing_requirements (subject_id, academic_year_id, required_teachers)
               VALUES (:s, :y, :r)
               ON CONFLICT (subject_id, academic_year_id) DO UPDATE SET required_teachers = EXCLUDED.required_teachers'
            : 'INSERT INTO subject_staffing_requirements (subject_id, academic_year_id, required_teachers)
               VALUES (:s, :y, :r)
               ON DUPLICATE KEY UPDATE required_teachers = VALUES(required_teachers)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['s' => $subjectId, 'y' => $academicYearId, 'r' => $requiredTeachers]);
    }
}

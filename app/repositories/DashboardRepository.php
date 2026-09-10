<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/**
 * Nizam -- Phase 5 dashboard's read-only aggregate queries. Deliberately its
 * own repository, not StudentRepository/TeacherRepository/etc: those belong
 * to Phase 6's modules (§E), and creating them now -- even read-only --
 * would blur where that phase's real work starts. Every query here is a
 * COUNT/GROUP BY (§O-24: "dashboard and report aggregates must be computed
 * in GROUP BY SQL queries, never a PHP loop"), scoped to a specific academic
 * year id the caller supplies, never "every year at once."
 */
final class DashboardRepository
{
    public function totalActiveStudents(int $academicYearId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM student_enrollments
             WHERE academic_year_id = :year AND status = 'active'"
        );
        $stmt->execute(['year' => $academicYearId]);
        return (int) $stmt->fetchColumn();
    }

    public function totalActiveTeachers(): int
    {
        return (int) Database::connection()
            ->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")
            ->fetchColumn();
    }

    public function totalActiveClasses(int $academicYearId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM classes WHERE academic_year_id = :year AND is_active = 1'
        );
        $stmt->execute(['year' => $academicYearId]);
        return (int) $stmt->fetchColumn();
    }

    public function totalActiveSubjects(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM subjects WHERE is_active = 1')
            ->fetchColumn();
    }

    public function totalActiveGrades(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM grades WHERE is_active = 1')
            ->fetchColumn();
    }

    /** @return array<string, int> e.g. ['muslim' => 12, 'christian' => 3, 'other' => 1] */
    public function religionBreakdown(int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT s.religion, COUNT(*) AS c
             FROM student_enrollments se
             JOIN students s ON s.id = se.student_id
             WHERE se.academic_year_id = :year AND se.status = 'active'
             GROUP BY s.religion"
        );
        $stmt->execute(['year' => $academicYearId]);
        $counts = ['muslim' => 0, 'christian' => 0, 'other' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['religion']] = (int) $row['c'];
        }
        return $counts;
    }
}

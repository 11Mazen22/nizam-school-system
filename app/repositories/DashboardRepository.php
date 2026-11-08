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

    /**
     * Today's attendance tally for the year, one GROUP BY (A§O-24).
     *
     * @return array{present:int, absent:int, late:int, excused:int, total:int}
     */
    public function attendanceForDate(int $academicYearId, string $date): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT status, COUNT(*) AS c FROM attendance_records
             WHERE academic_year_id = :year AND record_date = :d GROUP BY status'
        );
        $stmt->execute(['year' => $academicYearId, 'd' => $date]);
        $out = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0];
        foreach ($stmt->fetchAll() as $row) {
            if (isset($out[$row['status']])) {
                $out[$row['status']] = (int) $row['c'];
                $out['total'] += (int) $row['c'];
            }
        }
        return $out;
    }

    /**
     * Active classes that have enrolled students but no attendance rows on
     * the given date -- the real "attendance not yet taken" signal.
     *
     * @return array<int, array{class_id:int, class_name:string, grade_name_en:string, grade_name_ar:string}>
     */
    public function classesWithoutAttendance(int $academicYearId, string $date): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.id AS class_id, c.name AS class_name,
                    g.name_en AS grade_name_en, g.name_ar AS grade_name_ar
             FROM classes c
             JOIN grades g ON g.id = c.grade_id
             WHERE c.academic_year_id = :year AND c.is_active = 1
               AND EXISTS (SELECT 1 FROM student_enrollments e WHERE e.class_id = c.id AND e.status = 'active')
               AND NOT EXISTS (SELECT 1 FROM attendance_records a WHERE a.class_id = c.id AND a.record_date = :d)
             ORDER BY g.sort_order ASC, c.name ASC"
        );
        $stmt->execute(['year' => $academicYearId, 'd' => $date]);
        return $stmt->fetchAll();
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

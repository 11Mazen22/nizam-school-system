<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class AttendanceRepository
{
    /**
     * Load all records for a class on a given date, keyed by student_id.
     * @return array<int, array<string,mixed>>
     */
    public function forClassDate(int $classId, string $date): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM attendance_records WHERE class_id = :class AND record_date = :date'
        );
        $stmt->execute(['class' => $classId, 'date' => $date]);
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['student_id']] = $row;
        }
        return $map;
    }

    /**
     * Upsert one attendance record (student + date uniqueness enforced by DB).
     */
    public function upsert(int $yearId, int $classId, int $studentId, string $date, string $status, ?string $notes, ?int $recordedBy): void
    {
        $pdo = Database::connection();
        $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';

        if ($isPgsql) {
            $stmt = $pdo->prepare(
                'INSERT INTO attendance_records (academic_year_id, class_id, student_id, record_date, status, notes, recorded_by)
                 VALUES (:year, :class, :student, :date, :status, :notes, :by)
                 ON CONFLICT (student_id, record_date) DO UPDATE
                   SET status = EXCLUDED.status, notes = EXCLUDED.notes,
                       recorded_by = EXCLUDED.recorded_by, updated_at = CURRENT_TIMESTAMP'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO attendance_records (academic_year_id, class_id, student_id, record_date, status, notes, recorded_by)
                 VALUES (:year, :class, :student, :date, :status, :notes, :by)
                 ON DUPLICATE KEY UPDATE
                   status = VALUES(status), notes = VALUES(notes), recorded_by = VALUES(recorded_by)'
            );
        }

        $stmt->execute([
            'year'    => $yearId,
            'class'   => $classId,
            'student' => $studentId,
            'date'    => $date,
            'status'  => $status,
            'notes'   => $notes,
            'by'      => $recordedBy,
        ]);
    }

    /**
     * Summary counts per student for a date range — used for absenteeism report.
     * @return array<int, array<string,mixed>>
     */
    public function summaryForClass(int $classId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT ar.student_id, s.full_name, s.student_code,
                    SUM(CASE WHEN ar.status='present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN ar.status='absent'  THEN 1 ELSE 0 END) AS absent_count,
                    SUM(CASE WHEN ar.status='late'    THEN 1 ELSE 0 END) AS late_count,
                    SUM(CASE WHEN ar.status='excused' THEN 1 ELSE 0 END) AS excused_count,
                    COUNT(*) AS total_records
             FROM attendance_records ar
             JOIN students s ON s.id = ar.student_id
             WHERE ar.class_id = :class AND ar.record_date BETWEEN :from AND :to
             GROUP BY ar.student_id, s.full_name, s.student_code
             ORDER BY s.full_name ASC"
        );
        $stmt->execute(['class' => $classId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    /**
     * Students with absence_count >= threshold in the active year — chronic absenteeism flag.
     * @return array<int, array<string,mixed>>
     */
    public function chronicAbsentees(int $yearId, int $threshold = 5): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT ar.student_id, s.full_name, s.student_code,
                    g.name_en AS grade_name_en, g.name_ar AS grade_name_ar,
                    cl.name AS class_name,
                    COUNT(*) AS absent_count
             FROM attendance_records ar
             JOIN students s  ON s.id  = ar.student_id
             JOIN classes  cl ON cl.id = ar.class_id
             JOIN grades   g  ON g.id  = cl.grade_id
             WHERE ar.academic_year_id = :year AND ar.status = 'absent'
             GROUP BY ar.student_id, s.full_name, s.student_code, g.name_en, g.name_ar, cl.name
             HAVING COUNT(*) >= :threshold
             ORDER BY absent_count DESC"
        );
        $stmt->execute(['year' => $yearId, 'threshold' => $threshold]);
        return $stmt->fetchAll();
    }

    /** Daily totals for a class between two dates — used to populate summary table. */
    public function dailyTotalsForClass(int $classId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT record_date,
                    SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN status='absent'  THEN 1 ELSE 0 END) AS absent_count,
                    SUM(CASE WHEN status='late'    THEN 1 ELSE 0 END) AS late_count,
                    COUNT(*) AS total
             FROM attendance_records
             WHERE class_id = :class AND record_date BETWEEN :from AND :to
             GROUP BY record_date
             ORDER BY record_date ASC"
        );
        $stmt->execute(['class' => $classId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }
}

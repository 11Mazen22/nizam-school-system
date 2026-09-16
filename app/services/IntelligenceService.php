<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Logger;
use App\Middleware\AcademicYearContext;

/**
 * Provides Intelligent Insights & Predictive Analytics.
 * Identifies students at risk based on attendance and disciplinary data.
 */
final class IntelligenceService
{
    /**
     * Get students at risk (High absences + Disciplinary issues)
     */
    public function getAtRiskStudents(int $limit = 5, ?int $yearId = null): array
    {
        $yearId = $yearId ?? AcademicYearContext::activeYearId();
        if (!$yearId) {
            return [];
        }

        try {
            $pdo = Database::connection();
            // Intelligent Query: Find students with >= 3 absences OR >= 2 discipline records
            // Wrapped in a derived table so we can filter/order by the computed aliases in both MySQL and PostgreSQL.
            $stmt = $pdo->prepare("
                SELECT * FROM (
                    SELECT s.id, s.full_name, s.student_code, c.name as class_name,
                           (SELECT COUNT(*) FROM attendance_records ar WHERE ar.student_id = s.id AND ar.academic_year_id = ? AND ar.status = 'absent') as total_absences,
                           (SELECT COUNT(*) FROM disciplinary_records dr WHERE dr.student_id = s.id AND dr.academic_year_id = ?) as total_incidents
                    FROM students s
                    JOIN student_enrollments e ON e.student_id = s.id
                    JOIN classes c ON e.class_id = c.id
                    WHERE e.academic_year_id = ?
                ) as insights
                WHERE total_absences >= 3 OR total_incidents >= 1
                ORDER BY (total_absences * 2 + total_incidents * 3) DESC
                LIMIT ?
            ");
            
            $stmt->bindValue(1, $yearId, \PDO::PARAM_INT);
            $stmt->bindValue(2, $yearId, \PDO::PARAM_INT);
            $stmt->bindValue(3, $yearId, \PDO::PARAM_INT);
            $stmt->bindValue(4, $limit, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            // This is a "nice to have" dashboard widget, not core functionality --
            // a query failure here (a missing table on an older install, a driver
            // quirk) must never take down the whole dashboard for every user.
            // Log it like every other caught exception in this app and degrade
            // to "no at-risk students to show" rather than crashing the request.
            Logger::error('IntelligenceService::getAtRiskStudents failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
            ]);
            return [];
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
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
    public function getAtRiskStudents(int $limit = 5): array
    {
        $yearId = AcademicYearContext::getId();
        if (!$yearId) {
            return [];
        }

        $pdo = Database::get();
        // Intelligent Query: Find students with >= 3 absences OR >= 2 discipline records
        // Wrapped in a derived table so we can filter/order by the computed aliases in both MySQL and PostgreSQL.
        $stmt = $pdo->prepare("
            SELECT * FROM (
                SELECT s.id, s.full_name, s.national_id, c.name as class_name,
                       (SELECT COUNT(*) FROM attendance_records ar WHERE ar.student_id = s.id AND ar.status = 'absent') as total_absences,
                       (SELECT COUNT(*) FROM disciplinary_records dr WHERE dr.student_id = s.id) as total_incidents
                FROM students s
                JOIN enrollments e ON e.student_id = s.id
                JOIN classes c ON e.class_id = c.id
                WHERE e.academic_year_id = ?
            ) as insights
            WHERE total_absences >= 3 OR total_incidents >= 1
            ORDER BY (total_absences * 2 + total_incidents * 3) DESC
            LIMIT ?
        ");
        
        $stmt->bindValue(1, $yearId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

final class DataQualityService
{
    /**
     * Scans the database and returns a list of actionable data quality issues.
     *
     * @return array<int, array{
     *   category: string,
     *   severity: 'high'|'medium'|'low',
     *   title_key: string,
     *   description: string,
     *   count: int,
     *   action_url: string,
     *   action_label_key: string
     * }>
     */
    public function audit(): array
    {
        $pdo = Database::connection();
        $issues = [];

        // 1. Students without linked guardians
        $stmt = $pdo->query(
            'SELECT COUNT(*) FROM students s
             WHERE s.status = "active"
               AND NOT EXISTS (SELECT 1 FROM student_guardians sg WHERE sg.student_id = s.id)'
        );
        $unlinkedCount = (int) $stmt->fetchColumn();
        if ($unlinkedCount > 0) {
            $issues[] = [
                'category'         => 'sis',
                'severity'         => 'high',
                'title_key'        => 'quality.students_without_guardians',
                'description'      => "Found {$unlinkedCount} active student(s) without any registered family or guardian relationship.",
                'count'            => $unlinkedCount,
                'action_url'       => '/students',
                'action_label_key' => 'quality.resolve_guardians',
            ];
        }

        // 2. Active students with incomplete national ID / phone / address
        $stmt = $pdo->query(
            'SELECT COUNT(*) FROM students s
             WHERE s.status = "active" AND (s.phone IS NULL OR s.phone = "" OR s.address IS NULL OR s.address = "")'
        );
        $missingContactCount = (int) $stmt->fetchColumn();
        if ($missingContactCount > 0) {
            $issues[] = [
                'category'         => 'sis',
                'severity'         => 'medium',
                'title_key'        => 'quality.students_missing_contact',
                'description'      => "Found {$missingContactCount} student(s) missing phone number or residential address.",
                'count'            => $missingContactCount,
                'action_url'       => '/students',
                'action_label_key' => 'quality.update_records',
            ];
        }

        // 3. Active classes over capacity
        $stmt = $pdo->query(
            'SELECT COUNT(*) FROM classes c
             JOIN (
                 SELECT class_id, COUNT(*) as enrolled
                 FROM student_enrollments
                 WHERE status = "active" AND class_id IS NOT NULL
                 GROUP BY class_id
             ) e ON e.class_id = c.id
             WHERE c.capacity IS NOT NULL AND e.enrolled > c.capacity'
        );
        $overCapacityCount = (int) $stmt->fetchColumn();
        if ($overCapacityCount > 0) {
            $issues[] = [
                'category'         => 'academics',
                'severity'         => 'high',
                'title_key'        => 'quality.classes_over_capacity',
                'description'      => "{$overCapacityCount} classroom section(s) currently exceed room physical capacity ceiling.",
                'count'            => $overCapacityCount,
                'action_url'       => '/classes',
                'action_label_key' => 'quality.rebalance_sections',
            ];
        }

        // 4. Subjects without teacher assignments
        $stmt = $pdo->query(
            'SELECT COUNT(*) FROM subjects s
             WHERE s.is_active = 1
               AND NOT EXISTS (SELECT 1 FROM teacher_assignments ta WHERE ta.subject_id = s.id AND ta.status = "active")'
        );
        $unassignedSubjects = (int) $stmt->fetchColumn();
        if ($unassignedSubjects > 0) {
            $issues[] = [
                'category'         => 'academics',
                'severity'         => 'medium',
                'title_key'        => 'quality.unassigned_subjects',
                'description'      => "{$unassignedSubjects} active subject(s) have no teacher assigned in the current session.",
                'count'            => $unassignedSubjects,
                'action_url'       => '/assignments',
                'action_label_key' => 'quality.assign_teachers',
            ];
        }

        // 5. Classes missing timetable schedules
        $stmt = $pdo->query(
            'SELECT COUNT(*) FROM classes c
             WHERE c.is_active = 1
               AND NOT EXISTS (SELECT 1 FROM timetables t WHERE t.class_id = c.id)'
        );
        $unscheduledClasses = (int) $stmt->fetchColumn();
        if ($unscheduledClasses > 0) {
            $issues[] = [
                'category'         => 'academics',
                'severity'         => 'low',
                'title_key'        => 'quality.unscheduled_classes',
                'description'      => "{$unscheduledClasses} class section(s) do not have an active timetable schedule.",
                'count'            => $unscheduledClasses,
                'action_url'       => '/timetable',
                'action_label_key' => 'quality.build_timetable',
            ];
        }

        return $issues;
    }
}

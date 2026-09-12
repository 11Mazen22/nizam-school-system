<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Repositories\AttendanceRepository;
use App\Repositories\EnrollmentRepository;
use RuntimeException;

final class AttendanceService
{
    private const VALID_STATUSES = ['present', 'absent', 'late', 'excused'];
    private const ABSENTEE_THRESHOLD = 5;

    public function __construct(
        private readonly AttendanceRepository $repo = new AttendanceRepository(),
        private readonly EnrollmentRepository $enrollments = new EnrollmentRepository(),
    ) {
    }

    /**
     * Save a full attendance sheet for one class on one date.
     * $records = [ studentId => ['status' => '...', 'notes' => '...'], ... ]
     *
     * @param array<int, array{status:string, notes:?string}> $records
     * @throws RuntimeException 'no_active_year'
     */
    public function saveSheet(int $classId, string $date, array $records, ?int $userId): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            throw new RuntimeException('no_active_year');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            foreach ($records as $studentId => $entry) {
                $status = $entry['status'] ?? 'present';
                if (!in_array($status, self::VALID_STATUSES, true)) {
                    $status = 'present';
                }
                $notes = isset($entry['notes']) && trim($entry['notes']) !== '' ? trim($entry['notes']) : null;
                $this->repo->upsert($yearId, $classId, (int) $studentId, $date, $status, $notes, $userId);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        ActivityLogger::log('attendance.save', 'classes', $classId,
            sprintf('Attendance saved for class #%d on %s', $classId, $date));

        // Trigger absentee notifications after saving
        $this->triggerAbsenteeNotifications($yearId, $classId, $records, $userId);
    }

    /**
     * Fire in-app notifications for students who just hit the chronic threshold.
     * @param array<int, array{status:string, notes:?string}> $records
     */
    private function triggerAbsenteeNotifications(int $yearId, int $classId, array $records, ?int $userId): void
    {
        $absentIds = [];
        foreach ($records as $studentId => $entry) {
            if (($entry['status'] ?? '') === 'absent') {
                $absentIds[] = (int) $studentId;
            }
        }
        if (empty($absentIds)) {
            return;
        }

        $absentees = $this->repo->chronicAbsentees($yearId, self::ABSENTEE_THRESHOLD);
        $absenteeStudentIds = array_column($absentees, 'student_id');

        $notifService = new InAppNotificationService();
        foreach ($absentees as $row) {
            if (!in_array((int) $row['student_id'], $absentIds, true)) {
                continue; // only fire for students marked absent today who also hit threshold
            }
            $notifService->notifyAdmins(
                'attendance.chronic_absent',
                'Chronic Absenteeism Alert',
                'تحذير غياب متكرر',
                sprintf("Student %s has been absent %d times this year.", $row['full_name'], $row['absent_count']),
                sprintf("الطالب %s غاب %d مرة هذا العام.", $row['full_name'], $row['absent_count']),
                '/attendance'
            );
        }
    }

    public function getSheetForClass(int $classId, string $date): array
    {
        return $this->repo->forClassDate($classId, $date);
    }

    public function getSummary(int $classId, string $from, string $to): array
    {
        return $this->repo->summaryForClass($classId, $from, $to);
    }

    public function getChronicAbsentees(int $yearId): array
    {
        return $this->repo->chronicAbsentees($yearId, self::ABSENTEE_THRESHOLD);
    }
}

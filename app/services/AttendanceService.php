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

    public function __construct(
        private readonly AttendanceRepository $repo = new AttendanceRepository(),
        private readonly EnrollmentRepository $enrollments = new EnrollmentRepository(),
        private readonly AttendancePolicyService $policy = new AttendancePolicyService(),
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
     * Fire in-app notifications when a student today reaches an Egyptian
     * Ministry-of-Education absence threshold. Thresholds come from the
     * configurable rules engine, never from a hardcoded constant.
     * @param array<int, array{status:string, notes:?string}> $records
     */
    private function triggerAbsenteeNotifications(int $yearId, int $classId, array $records, ?int $userId): void
    {
        $rules = AcademicRulesService::attendanceRules();
        $absentIds = [];
        foreach ($records as $studentId => $entry) {
            if (in_array((string) ($entry['status'] ?? ''), $rules['truancy_statuses'], true)) {
                $absentIds[] = (int) $studentId;
            }
        }
        if ($absentIds === []) {
            return;
        }

        $notifService = new InAppNotificationService();
        foreach ($absentIds as $studentId) {
            $status = $this->policy->studentStatus($studentId, $yearId);
            if ($status['level'] === 'ok') {
                continue;
            }
            $student = $this->repo->studentIdentity($studentId);
            $name = (string) ($student['full_name'] ?? ('#' . $studentId));
            $critical = $status['level'] === 'critical';
            $notifService->notifyAdmins(
                $critical ? 'attendance.absence_critical' : 'attendance.absence_warning',
                $critical ? __('attendance.absence_critical_subject') : __('attendance.absence_warning_subject'),
                $critical ? __('attendance.absence_critical_subject_ar') : __('attendance.absence_warning_subject_ar'),
                sprintf(
                    __('attendance.absence_alert_body'),
                    $name,
                    $status['consecutive'],
                    $status['absent'],
                    $critical ? $rules['critical_consecutive'] : $rules['warning_consecutive']
                ),
                sprintf(
                    __('attendance.absence_alert_body_ar'),
                    $name,
                    $status['consecutive'],
                    $status['absent'],
                    $critical ? $rules['critical_consecutive'] : $rules['warning_consecutive']
                ),
                '/attendance/report?class_id=' . $classId
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
        return $this->repo->chronicAbsentees($yearId);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Middleware\AcademicYearContext;
use App\Repositories\ActivityLogRepository;
use App\Repositories\AdmissionsRepository;
use App\Repositories\ClassRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\FinanceRepository;
use App\Repositories\HrRepository;

final class DashboardStatsService
{
    public function __construct(
        private readonly DashboardRepository $repository = new DashboardRepository(),
        private readonly ActivityLogRepository $activityLog = new ActivityLogRepository(),
    ) {
    }

    public function build(): array
    {
        $yearId = AcademicYearContext::activeYearId();
        $moduleManager = new ModuleManager();

        if ($yearId === null) {
            return [
                'hasActiveYear'     => false,
                'students'          => 0,
                'teachers'          => 0,
                'classes'           => 0,
                'subjects'          => 0,
                'grades'            => 0,
                'religion'          => ['muslim' => 0, 'christian' => 0, 'other' => 0, 'not_recorded' => 0],
                'activity'          => [],
                'attendance'        => null,
                'pendingAttendance' => [],
                'capacity'          => [],
                'actionItems'       => [],
                'qualityIssues'     => [],
                'financeSummary'    => null,
                'admissionsCounts'  => null,
            ];
        }

        $onlyUserId = ($_SESSION['role_code'] ?? null) === 'admin' ? null : (int) ($_SESSION['user_id'] ?? 0);

        // Core attendance and capacity. On a non-working day there is nothing
        // to record, so the "not taken yet" list stays empty instead of
        // flagging every class in the school.
        $attendance = hasPermission('attendance.view') ? $this->repository->attendanceForDate($yearId, date('Y-m-d')) : null;
        $pendingAttendance = [];
        if (hasPermission('attendance.view') && AttendancePolicyService::isSchoolDay()) {
            $pendingAttendance = $this->repository->classesWithoutAttendance($yearId, date('Y-m-d'));
        }
        $capacity = hasPermission('classes.view') ? (new ClassRepository())->densityForYear($yearId, null) : [];

        // Action Center Items. Missing attendance is deliberately not copied
        // into this array: the dashboard renders $pendingAttendance itself so
        // it can show the localized grade/class label and attendance action.
        // Adding the same rows here made every unrecorded class appear twice
        // and doubled the attention badge on a new school day.
        $actionItems = [];

        // Admissions pipeline alerts
        $admissionsCounts = null;
        if ($moduleManager->isEnabled('admissions') && hasPermission('admissions.view')) {
            $admRepo = new AdmissionsRepository();
            $admissionsCounts = $admRepo->countByStatus();
            $pendingApps = (int) ($admissionsCounts['application'] ?? 0) + (int) ($admissionsCounts['review'] ?? 0);
            if ($pendingApps > 0) {
                $actionItems[] = [
                    'type'        => 'admissions',
                    'severity'    => 'info',
                    'title_key'   => 'dashboard.action_pending_admissions',
                    'description' => "{$pendingApps} " . __('admissions.pending_review'),
                    'url'         => '/admissions?status=application',
                ];
            }
        }

        // HR staff leave requests
        if ($moduleManager->isEnabled('hr') && hasPermission('hr.manage')) {
            $hrRepo = new HrRepository();
            $pendingLeaves = count($hrRepo->allLeaves('pending'));
            if ($pendingLeaves > 0) {
                $actionItems[] = [
                    'type'        => 'hr',
                    'severity'    => 'warning',
                    'title_key'   => 'dashboard.action_pending_leaves',
                    'description' => "{$pendingLeaves} " . __('hr.status_pending'),
                    'url'         => '/hr/leaves?status=pending',
                ];
            }
        }

        // Finance summary
        $financeSummary = null;
        if ($moduleManager->isEnabled('finance') && hasPermission('finance.view')) {
            $finRepo = new FinanceRepository();
            $financeSummary = $finRepo->getSummary($yearId);
        }

        // Data Quality Auditor (for Admins / Principals)
        $qualityIssues = [];
        if (hasPermission('settings.manage') || hasPermission('reports.view')) {
            $qualityIssues = (new DataQualityService())->audit();
        }

        return [
            'hasActiveYear'     => true,
            'students'          => $this->repository->totalActiveStudents($yearId),
            'teachers'          => $this->repository->totalActiveTeachers(),
            'classes'           => $this->repository->totalActiveClasses($yearId),
            'subjects'          => $this->repository->totalActiveSubjects(),
            'grades'            => $this->repository->totalActiveGrades(),
            'religion'          => $this->repository->religionBreakdown($yearId),
            'activity'          => $this->activityLog->paginate(10, 0, $onlyUserId),
            'attendance'        => $attendance,
            'pendingAttendance' => $pendingAttendance,
            'capacity'          => $capacity,
            'actionItems'       => $actionItems,
            'qualityIssues'     => $qualityIssues,
            'financeSummary'    => $financeSummary,
            'admissionsCounts'  => $admissionsCounts,
        ];
    }
}

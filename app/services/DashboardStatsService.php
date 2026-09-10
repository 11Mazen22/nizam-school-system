<?php

declare(strict_types=1);

namespace App\Services;

use App\Middleware\AcademicYearContext;
use App\Repositories\ActivityLogRepository;
use App\Repositories\DashboardRepository;

/**
 * Nizam -- assembles the Phase 5 dashboard's data. Only the statistics
 * already named in the blueprint (§A: total students/teachers/grades/
 * classes/subjects, religion breakdown, recent activity) -- no invented
 * metrics. Everything year-scoped reads AcademicYearContext (populated once
 * per request by Phase 4's middleware), and gracefully returns zeroed/empty
 * data when there is no active year rather than erroring.
 */
final class DashboardStatsService
{
    public function __construct(
        private readonly DashboardRepository $repository = new DashboardRepository(),
        private readonly ActivityLogRepository $activityLog = new ActivityLogRepository(),
    ) {
    }

    /** @return array{hasActiveYear: bool, students: int, teachers: int, classes: int, subjects: int, grades: int, religion: array<string,int>, activity: array} */
    public function build(): array
    {
        $yearId = AcademicYearContext::activeYearId();

        if ($yearId === null) {
            return [
                'hasActiveYear' => false,
                'students' => 0, 'teachers' => 0, 'classes' => 0, 'subjects' => 0, 'grades' => 0,
                'religion' => ['muslim' => 0, 'christian' => 0, 'other' => 0],
                'activity' => [],
            ];
        }

        // §J: Staff hold activity_log.view "own actions only"; Administrator
        // sees everything. Enforced here in the query itself, not by hiding
        // rows client-side.
        $onlyUserId = ($_SESSION['role_code'] ?? null) === 'admin' ? null : (int) ($_SESSION['user_id'] ?? 0);

        return [
            'hasActiveYear' => true,
            'students' => $this->repository->totalActiveStudents($yearId),
            'teachers' => $this->repository->totalActiveTeachers(),
            'classes' => $this->repository->totalActiveClasses($yearId),
            'subjects' => $this->repository->totalActiveSubjects(),
            'grades' => $this->repository->totalActiveGrades(),
            'religion' => $this->repository->religionBreakdown($yearId),
            'activity' => $this->activityLog->paginate(10, 0, $onlyUserId),
        ];
    }
}

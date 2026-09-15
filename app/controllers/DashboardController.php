<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Request;
use App\Services\DashboardStatsService;
use App\Services\IntelligenceService;

/**
 * Phase 5 dashboard: the statistics §A actually specifies (student/teacher/
 * class/subject/grade totals, religion breakdown, recent activity),
 * respecting the active academic year and the viewer's own permissions.
 * NOT the full §K report system, no quick-action links to unbuilt modules
 * (Students/Backup/etc.) -- those are Phase 6+.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        error_log("ACTIVE YEAR ID: " . var_export(\App\Middleware\AcademicYearContext::activeYearId(), true));
        $stats = (new DashboardStatsService())->build();
        $insights = (new IntelligenceService())->getAtRiskStudents();
        $this->view('dashboard', ['stats' => $stats, 'insights' => $insights]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Repositories\ClassRepository;
use App\Repositories\EnrollmentRepository;
use App\Request;
use App\Services\AttendanceService;
use RuntimeException;

final class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService  $service     = new AttendanceService(),
        private readonly ClassRepository    $classes     = new ClassRepository(),
        private readonly EnrollmentRepository $enrollments = new EnrollmentRepository(),
    ) {
    }

    /** GET /attendance — pick a class + date, then mark. */
    public function index(Request $request): void
    {
        $yearId  = AcademicYearContext::activeYearId();
        $classes = $yearId ? $this->classes->allForYear($yearId) : [];

        $classId  = (int) ($request->query('class_id') ?? 0);
        $date     = $request->query('date') ?? date('Y-m-d');

        $students   = [];
        $existing   = [];
        $classRow   = null;

        if ($classId > 0 && $yearId) {
            $classRow = $this->classes->find($classId);
            $students = $this->enrollments->activeStudentsForClass($classId);
            $existing = $this->service->getSheetForClass($classId, $date);
        }

        $this->view('attendance/mark', [
            'classes'   => $classes,
            'classId'   => $classId,
            'classRow'  => $classRow,
            'date'      => $date,
            'students'  => $students,
            'existing'  => $existing,
            'yearId'    => $yearId,
        ]);
    }

    /** POST /attendance — save sheet. */
    public function store(Request $request): void
    {
        $classId  = (int) ($request->post('class_id') ?? 0);
        $date     = $request->post('date') ?? date('Y-m-d');
        $userId   = (int) ($_SESSION['user_id'] ?? 0);

        if ($classId <= 0) {
            Flash::set('danger', __('attendance.no_class'));
            $this->redirect('/attendance');
            return;
        }

        // Build records map from POST: statuses[studentId] + notes[studentId]
        $statuses = $_POST['statuses'] ?? [];
        $notes    = $_POST['notes']    ?? [];
        $records  = [];
        foreach ($statuses as $sid => $status) {
            $records[(int) $sid] = [
                'status' => $status,
                'notes'  => $notes[$sid] ?? null,
            ];
        }

        try {
            $this->service->saveSheet($classId, $date, $records, $userId ?: null);
            Flash::set('success', __('attendance.saved'));
        } catch (RuntimeException $e) {
            Flash::set('danger', __('attendance.save_failed'));
        }

        $this->redirect('/attendance?class_id=' . $classId . '&date=' . urlencode($date));
    }

    /** GET /attendance/report — absenteeism summary per class + date range. */
    public function report(Request $request): void
    {
        $yearId  = AcademicYearContext::activeYearId();
        $classes = $yearId ? $this->classes->allForYear($yearId) : [];

        $classId = (int) ($request->query('class_id') ?? 0);
        $from    = $request->query('from') ?? date('Y-m-01');
        $to      = $request->query('to')   ?? date('Y-m-d');

        $summary   = [];
        $chronic   = [];
        $classRow  = null;

        if ($classId > 0) {
            $classRow = $this->classes->find($classId);
            $summary  = $this->service->getSummary($classId, $from, $to);
        }

        if ($yearId) {
            $chronic = $this->service->getChronicAbsentees($yearId);
        }

        $this->view('attendance/report', [
            'classes'  => $classes,
            'classId'  => $classId,
            'classRow' => $classRow,
            'from'     => $from,
            'to'       => $to,
            'summary'  => $summary,
            'chronic'  => $chronic,
        ]);
    }
}

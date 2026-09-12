<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Request;
use App\Services\WelfareService;
use App\Repositories\StudentRepository;
use RuntimeException;

final class WelfareController extends Controller
{
    public function __construct(
        private readonly WelfareService    $service  = new WelfareService(),
        private readonly StudentRepository $students = new StudentRepository(),
    ) {
    }

    /** GET /welfare/discipline — school-wide discipline log. */
    public function discipline(Request $request): void
    {
        $type     = $request->query('type')     ?: null;
        $severity = $request->query('severity') ?: null;
        $records  = $this->service->getAllDisciplinary($type, $severity);
        $this->view('welfare/discipline', compact('records', 'type', 'severity'));
    }

    /** POST /welfare/discipline — log a new discipline record. */
    public function storeDiscipline(Request $request): void
    {
        $studentId  = (int) ($request->post('student_id', '0') ?? '0');
        $date       = $request->post('incident_date', date('Y-m-d')) ?? date('Y-m-d');
        $type       = $request->post('type', 'infraction') ?? 'infraction';
        $severity   = $request->post('severity', 'low') ?? 'low';
        $title      = $request->post('title', '') ?? '';
        $description= $request->post('description', '') ?? '';
        $action     = $request->post('action_taken', '') ?: null;
        $userId     = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $this->service->logDisciplinary($studentId, $date, $type, $severity,
                $title, $description, $action, $userId ?: null);
            Flash::set('success', __('welfare.discipline_logged'));
        } catch (RuntimeException $e) {
            Flash::set('danger', $e->getMessage() === 'no_active_year'
                ? __('students.no_active_year') : __('validation.required'));
        }
        $this->redirect('/welfare/discipline');
    }

    /** POST /welfare/discipline/{id}/delete */
    public function deleteDiscipline(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null) {
            $this->service->deleteDisciplinary($id);
            Flash::set('success', __('welfare.deleted'));
        }
        $this->redirect('/welfare/discipline');
    }

    /** GET /welfare/health — school-wide health log. */
    public function health(Request $request): void
    {
        $type    = $request->query('type') ?: null;
        $records = $this->service->getAllHealth($type);
        $this->view('welfare/health', compact('records', 'type'));
    }

    /** POST /welfare/health — log a new health record. */
    public function storeHealth(Request $request): void
    {
        $studentId  = (int) ($request->post('student_id', '0') ?? '0');
        $recordType = $request->post('record_type', 'clinic_visit') ?? 'clinic_visit';
        $date       = $request->post('date_logged', date('Y-m-d')) ?? date('Y-m-d');
        $title      = $request->post('title', '') ?? '';
        $details    = $request->post('details', '') ?? '';
        $userId     = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $this->service->logHealth($studentId, $recordType, $date, $title, $details, $userId ?: null);
            Flash::set('success', __('welfare.health_logged'));
        } catch (RuntimeException) {
            Flash::set('danger', __('validation.required'));
        }
        $this->redirect('/welfare/health');
    }

    /** POST /welfare/health/{id}/delete */
    public function deleteHealth(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null) {
            $this->service->deleteHealth($id);
            Flash::set('success', __('welfare.deleted'));
        }
        $this->redirect('/welfare/health');
    }
}

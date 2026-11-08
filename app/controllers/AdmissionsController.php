<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Request;
use App\Response;
use App\Repositories\AdmissionsRepository;
use App\Repositories\ClassRepository;
use App\Repositories\GradeRepository;
use App\Services\AdmissionsService;

final class AdmissionsController extends Controller
{
    private AdmissionsRepository $repository;
    private AdmissionsService $service;

    public function __construct(
        ?AdmissionsRepository $repository = null,
        ?AdmissionsService $service = null
    ) {
        $this->repository = $repository ?? new AdmissionsRepository();
        $this->service = $service ?? new AdmissionsService($this->repository);
    }

    public function index(Request $request): void
    {
        $status = $request->query('status');
        $applications = $this->repository->all($status ? (string) $status : null);
        $counts = $this->repository->countByStatus();
        $this->view('admissions/index', [
            'applications' => $applications,
            'counts'       => $counts,
            'activeStatus' => $status,
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $app = $this->repository->find((int) $id);
        if ($app === null) {
            Flash::error(__('admissions.not_found'));
            Response::redirect('/admissions');
            return;
        }

        $activeYearId = AcademicYearContext::id();
        $classes = $activeYearId !== null
            ? (new ClassRepository())->forGradeAndYear((int) $app['grade_id'], $activeYearId)
            : [];

        $this->view('admissions/show', ['application' => $app, 'classes' => $classes]);
    }

    public function create(Request $request): void
    {
        $grades = (new GradeRepository())->all();
        $activeYearId = AcademicYearContext::id();
        $this->view('admissions/create', ['grades' => $grades, 'academicYearId' => $activeYearId]);
    }

    public function store(Request $request): void
    {
        $activeYearId = AcademicYearContext::id();
        if ($activeYearId === null) {
            Flash::error(__('admissions.no_active_year'));
            Response::redirect('/admissions');
            return;
        }

        $result = $this->service->createApplication([
            'applicant_name'       => trim((string) $request->post('applicant_name')),
            'gender'               => (string) $request->post('gender', 'm'),
            'date_of_birth'        => (string) $request->post('date_of_birth'),
            'grade_id'             => (int) $request->post('grade_id'),
            'academic_year_id'     => $activeYearId,
            'guardian_name'        => trim((string) $request->post('guardian_name')),
            'guardian_phone'       => trim((string) $request->post('guardian_phone')),
            'guardian_email'       => trim((string) $request->post('guardian_email')) ?: null,
            'guardian_relationship'=> (string) $request->post('guardian_relationship', 'father'),
            'status'               => (string) $request->post('status', 'inquiry'),
        ]);

        if ($result['success']) {
            Flash::success(__('admissions.created_success', ['number' => $result['application_number']]));
            Response::redirect('/admissions/' . $result['id']);
        } else {
            Flash::error($result['error'] ?? __('admissions.create_error'));
            Response::redirect('/admissions/create');
        }
    }

    public function updateStatus(Request $request, string $id): void
    {
        $status = (string) $request->post('status');
        $notes = trim((string) $request->post('notes')) ?: null;

        $this->repository->updateStatus((int) $id, $status, $notes);
        Flash::success(__('admissions.status_updated'));
        Response::redirect('/admissions/' . $id);
    }

    public function recordAssessment(Request $request, string $id): void
    {
        $date = (string) $request->post('assessment_date', date('Y-m-d'));
        $score = (float) $request->post('assessment_score', 0.0);
        $notes = trim((string) $request->post('assessment_notes')) ?: null;

        $this->repository->recordAssessment((int) $id, $date, $score, $notes);
        Flash::success(__('admissions.assessment_recorded'));
        Response::redirect('/admissions/' . $id);
    }

    public function recordDecision(Request $request, string $id): void
    {
        $decision = (string) $request->post('decision');
        $notes = trim((string) $request->post('decision_notes')) ?: null;

        $this->repository->recordDecision((int) $id, $decision, $notes);
        Flash::success(__('admissions.decision_recorded'));
        Response::redirect('/admissions/' . $id);
    }

    public function convert(Request $request, string $id): void
    {
        $classId = $request->post('class_id') ? (int) $request->post('class_id') : null;
        $result = $this->service->convertApplicantToStudent((int) $id, $classId);

        if ($result['success']) {
            Flash::success(__('admissions.convert_success'));
            Response::redirect('/students/' . $result['student_id']);
        } else {
            Flash::error($result['error'] ?? __('admissions.convert_error'));
            Response::redirect('/admissions/' . $id);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Repositories\ClassRepository;
use App\Repositories\GradeRepository;
use App\Request;
use App\Services\ClassService;
use RuntimeException;

final class ClassController extends Controller
{
    public function __construct(
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly GradeRepository $grades = new GradeRepository(),
        private readonly ClassService $service = new ClassService(),
    ) {
    }

    public function index(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        $rows = $yearId === null ? [] : $this->classes->allForYear($yearId);
        foreach ($rows as &$row) {
            $row['enrolled'] = $this->classes->activeEnrollmentCount((int) $row['id']);
        }
        unset($row);
        $this->view('classes/index', ['classes' => $rows, 'hasActiveYear' => $yearId !== null]);
    }

    public function create(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            $this->redirect('/classes');
            return;
        }
        $this->view('classes/form', ['error' => null, 'class' => null, 'grades' => $this->grades->all(true)]);
    }

    public function store(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            $this->redirect('/classes');
            return;
        }

        [$gradeId, $name, $capacity, $error] = $this->fields($request);
        if ($error !== null) {
            $this->view('classes/form', ['error' => $error, 'class' => null, 'grades' => $this->grades->all(true)]);
            return;
        }

        try {
            $this->service->create($gradeId, $yearId, $name, $capacity);
        } catch (RuntimeException $e) {
            $this->view('classes/form', [
                'error' => $e->getMessage() === 'year_closed' ? __('academic_years.year_closed') : __('classes.duplicate_name'),
                'class' => null, 'grades' => $this->grades->all(true),
            ]);
            return;
        }
        Flash::set('success', __('classes.created'));
        $this->redirect('/classes');
    }

    public function edit(Request $request): void
    {
        $id = $request->paramInt('id');
        $class = $id === null ? null : $this->classes->find($id);
        if ($class === null) {
            $this->redirect('/classes');
            return;
        }
        $this->view('classes/form', ['error' => null, 'class' => $class, 'grades' => $this->grades->all(true)]);
    }

    public function update(Request $request): void
    {
        $id = $request->paramInt('id');
        $class = $id === null ? null : $this->classes->find($id);
        if ($class === null) {
            $this->redirect('/classes');
            return;
        }

        [$gradeId, $name, $capacity, $error] = $this->fields($request);
        if ($error !== null) {
            $this->view('classes/form', ['error' => $error, 'class' => $class, 'grades' => $this->grades->all(true)]);
            return;
        }

        // A class's grade/year are fixed at creation (student_enrollments' composite
        // FK is built against them) -- edit only ever touches name/capacity.
        try {
            $this->service->update($id, (int) $class['grade_id'], (int) $class['academic_year_id'], $name, $capacity);
        } catch (RuntimeException $e) {
            $this->view('classes/form', [
                'error' => $e->getMessage() === 'year_closed' ? __('academic_years.year_closed') : __('classes.duplicate_name'),
                'class' => $class, 'grades' => $this->grades->all(true),
            ]);
            return;
        }
        Flash::set('success', __('classes.updated'));
        $this->redirect('/classes');
    }

    public function archive(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null && $this->classes->find($id) !== null) {
            $this->service->setActive($id, false);
            Flash::set('success', __('classes.archived'));
        }
        $this->redirect('/classes');
    }

    public function restore(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null && $this->classes->find($id) !== null) {
            $this->service->setActive($id, true);
            Flash::set('success', __('classes.restored'));
        }
        $this->redirect('/classes');
    }

    /** @return array{0:int,1:string,2:?int,3:?string} */
    private function fields(Request $request): array
    {
        $gradeId = (int) ($request->post('grade_id', '') ?: 0);
        $name = $request->post('name', '') ?: '';
        $capacityRaw = $request->post('capacity', '') ?: '';
        $capacity = $capacityRaw !== '' ? (int) $capacityRaw : null;

        if ($gradeId <= 0 || $name === '') {
            return [$gradeId, $name, $capacity, __('validation.required')];
        }
        if ($capacity !== null && $capacity <= 0) {
            return [$gradeId, $name, $capacity, __('classes.invalid_capacity')];
        }
        return [$gradeId, $name, $capacity, null];
    }
}

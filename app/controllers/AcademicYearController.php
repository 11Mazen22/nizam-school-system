<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Repositories\AcademicYearRepository;
use App\Request;
use App\Services\AcademicYearService;
use DateTime;
use RuntimeException;

final class AcademicYearController extends Controller
{
    public function __construct(
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
        private readonly AcademicYearService $service = new AcademicYearService(),
    ) {
    }

    public function index(Request $request): void
    {
        $this->view('academic-years/index', ['years' => $this->years->all()]);
    }

    public function create(Request $request): void
    {
        $this->view('academic-years/create', ['error' => null, 'years' => $this->years->all()]);
    }

    public function store(Request $request): void
    {
        $label = $request->post('label', '') ?: '';
        $start = $request->post('start_date', '') ?: '';
        $end = $request->post('end_date', '') ?: '';
        $rolloverFrom = $request->post('rollover_from', '') ?: '';

        $error = $this->validate($label, $start, $end);
        if ($error !== null) {
            $this->view('academic-years/create', ['error' => $error, 'years' => $this->years->all()]);
            return;
        }

        $this->service->create($label, $start, $end, $rolloverFrom !== '' ? (int) $rolloverFrom : null);
        Flash::set('success', __('academic_years.created'));
        $this->redirect('/academic-years');
    }

    public function activate(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null || $this->years->find($id) === null) {
            $this->redirect('/academic-years');
            return;
        }
        $this->service->activate($id);
        Flash::set('success', __('academic_years.activated'));
        $this->redirect('/academic-years');
    }

    public function close(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null || $this->years->find($id) === null) {
            $this->redirect('/academic-years');
            return;
        }
        try {
            $this->service->close($id);
            Flash::set('success', __('academic_years.closed'));
        } catch (RuntimeException $e) {
            // §Q error catalog: exact remaining-count message, not a generic failure.
            Flash::set('danger', __('academic_years.close_blocked', ['count' => $e->getMessage()]));
        }
        $this->redirect('/academic-years');
    }

    public function reopen(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null || $this->years->find($id) === null) {
            $this->redirect('/academic-years');
            return;
        }
        $this->service->reopen($id);
        Flash::set('success', __('academic_years.reopened'));
        $this->redirect('/academic-years');
    }

    /** §Q validation rules: label required/unique/YYYY-YYYY+1 format, dates required, end > start. */
    private function validate(string $label, string $start, string $end): ?string
    {
        if ($label === '' || $start === '' || $end === '') {
            return __('validation.required');
        }
        if (!preg_match('/^(\d{4})\/(\d{4})$/', $label, $m) || ((int) $m[2]) !== ((int) $m[1]) + 1) {
            return __('validation.year_label_format');
        }
        if ($this->years->findByLabel($label) !== null) {
            return __('academic_years.duplicate_label');
        }
        $startDt = DateTime::createFromFormat('Y-m-d', $start);
        $endDt = DateTime::createFromFormat('Y-m-d', $end);
        if ($startDt === false || $endDt === false) {
            return __('validation.required');
        }
        if ($endDt <= $startDt) {
            return __('validation.date_order');
        }
        return null;
    }
}

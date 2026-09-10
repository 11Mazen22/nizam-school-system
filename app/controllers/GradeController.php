<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Repositories\GradeRepository;
use App\Request;
use App\Services\GradeService;

/** §Q screen inventory: "Grades list" only -- create/edit happen inline (modal) on that one screen, not a separate page. */
final class GradeController extends Controller
{
    public function __construct(
        private readonly GradeRepository $grades = new GradeRepository(),
        private readonly GradeService $service = new GradeService(),
    ) {
    }

    public function index(Request $request): void
    {
        $this->view('grades/index', ['grades' => $this->grades->all()]);
    }

    public function store(Request $request): void
    {
        $error = $this->save(null, $request);
        if ($error !== null) {
            Flash::set('danger', $error);
        } else {
            Flash::set('success', __('grades.created'));
        }
        $this->redirect('/grades');
    }

    public function update(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null || $this->grades->find($id) === null) {
            $this->redirect('/grades');
            return;
        }
        $error = $this->save($id, $request);
        if ($error !== null) {
            Flash::set('danger', $error);
        } else {
            Flash::set('success', __('grades.updated'));
        }
        $this->redirect('/grades');
    }

    public function archive(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null && $this->grades->find($id) !== null) {
            $this->service->setActive($id, false);
            Flash::set('success', __('grades.archived'));
        }
        $this->redirect('/grades');
    }

    public function restore(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null && $this->grades->find($id) !== null) {
            $this->service->setActive($id, true);
            Flash::set('success', __('grades.restored'));
        }
        $this->redirect('/grades');
    }

    private function save(?int $id, Request $request): ?string
    {
        $nameEn = $request->post('name_en', '') ?: '';
        $nameAr = $request->post('name_ar', '') ?: '';
        $sortOrder = (int) ($request->post('sort_order', '') ?: 0);

        if ($nameEn === '' || $nameAr === '') {
            return __('validation.required');
        }

        if ($id === null) {
            $this->service->create($nameEn, $nameAr, $sortOrder);
        } else {
            $this->service->update($id, $nameEn, $nameAr, $sortOrder);
        }
        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Repositories\SubjectRepository;
use App\Request;
use App\Services\SubjectService;

final class SubjectController extends Controller
{
    public function __construct(
        private readonly SubjectRepository $subjects = new SubjectRepository(),
        private readonly SubjectService $service = new SubjectService(),
    ) {
    }

    public function index(Request $request): void
    {
        $this->view('subjects/index', ['subjects' => $this->subjects->all()]);
    }

    public function create(Request $request): void
    {
        $this->view('subjects/form', ['error' => null, 'subject' => null]);
    }

    public function store(Request $request): void
    {
        [$code, $nameEn, $nameAr] = $this->fields($request);
        $error = $this->validateFields($code, $nameEn, $nameAr, null);
        if ($error !== null) {
            $this->view('subjects/form', ['error' => $error, 'subject' => null]);
            return;
        }
        $this->service->create($code, $nameEn, $nameAr);
        Flash::set('success', __('subjects.created'));
        $this->redirect('/subjects');
    }

    public function edit(Request $request): void
    {
        $id = $request->paramInt('id');
        $subject = $id === null ? null : $this->subjects->find($id);
        if ($subject === null) {
            $this->redirect('/subjects');
            return;
        }
        $this->view('subjects/form', ['error' => null, 'subject' => $subject]);
    }

    public function update(Request $request): void
    {
        $id = $request->paramInt('id');
        $subject = $id === null ? null : $this->subjects->find($id);
        if ($subject === null) {
            $this->redirect('/subjects');
            return;
        }

        [$code, $nameEn, $nameAr] = $this->fields($request);
        $error = $this->validateFields($code, $nameEn, $nameAr, $id);
        if ($error !== null) {
            $this->view('subjects/form', ['error' => $error, 'subject' => array_merge($subject, [
                'code' => $code, 'name_en' => $nameEn, 'name_ar' => $nameAr,
            ])]);
            return;
        }
        $this->service->update($id, $code, $nameEn, $nameAr);
        Flash::set('success', __('subjects.updated'));
        $this->redirect('/subjects');
    }

    public function archive(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null && $this->subjects->find($id) !== null) {
            $this->service->setActive($id, false);
            Flash::set('success', __('subjects.archived'));
        }
        $this->redirect('/subjects');
    }

    public function restore(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null && $this->subjects->find($id) !== null) {
            $this->service->setActive($id, true);
            Flash::set('success', __('subjects.restored'));
        }
        $this->redirect('/subjects');
    }

    /** @return array{0:string,1:string,2:string} */
    private function fields(Request $request): array
    {
        return [
            $request->post('code', '') ?: '',
            $request->post('name_en', '') ?: '',
            $request->post('name_ar', '') ?: '',
        ];
    }

    private function validateFields(string $code, string $nameEn, string $nameAr, ?int $editingId): ?string
    {
        if ($code === '' || $nameEn === '' || $nameAr === '') {
            return __('validation.required');
        }
        $existing = $this->subjects->findByCode($code);
        if ($existing !== null && (int) $existing['id'] !== $editingId) {
            return __('subjects.duplicate_code');
        }
        return null;
    }
}

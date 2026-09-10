<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Repositories\SubjectRepository;
use App\Repositories\TeacherRepository;
use App\Request;
use App\Services\TeacherService;
use App\Services\UploadService;
use RuntimeException;

final class TeacherController extends Controller
{
    public function __construct(
        private readonly TeacherRepository $teachers = new TeacherRepository(),
        private readonly SubjectRepository $subjects = new SubjectRepository(),
        private readonly TeacherService $service = new TeacherService(),
        private readonly UploadService $uploads = new UploadService(),
    ) {
    }

    public function index(Request $request): void
    {
        $term = $request->query('q', '') ?: '';
        $rows = $term !== '' ? $this->teachers->search($term) : $this->teachers->all('active');
        $this->view('teachers/index', ['teachers' => $rows, 'q' => $term]);
    }

    public function archived(Request $request): void
    {
        $this->view('teachers/archived', ['teachers' => $this->teachers->all('archived')]);
    }

    public function show(Request $request): void
    {
        $id = $request->paramInt('id');
        $teacher = $id === null ? null : $this->teachers->find($id);
        if ($teacher === null) {
            $this->redirect('/teachers');
            return;
        }
        $this->view('teachers/show', [
            'teacher' => $teacher,
            'qualifications' => $this->subjects->qualifiedForTeacher($id),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('teachers/form', [
            'error' => null, 'teacher' => null, 'subjects' => $this->subjects->all(true), 'selected' => [],
        ]);
    }

    public function store(Request $request): void
    {
        [$fullName, $phone, $email, $error] = $this->fields($request);
        $subjectIds = $this->subjectIds($request);
        if ($error !== null) {
            $this->view('teachers/form', [
                'error' => $error, 'teacher' => null, 'subjects' => $this->subjects->all(true), 'selected' => $subjectIds,
            ]);
            return;
        }

        $id = $this->service->create($fullName, $phone, $email);
        $this->subjects->setTeacherQualifications($id, $subjectIds);
        $this->handlePhotoUpload($request, $id);
        Flash::set('success', __('teachers.created'));
        $this->redirect('/teachers');
    }

    public function edit(Request $request): void
    {
        $id = $request->paramInt('id');
        $teacher = $id === null ? null : $this->teachers->find($id);
        if ($teacher === null) {
            $this->redirect('/teachers');
            return;
        }
        $qualified = array_column($this->subjects->qualifiedForTeacher($id), 'id');
        $this->view('teachers/form', [
            'error' => null, 'teacher' => $teacher, 'subjects' => $this->subjects->all(true), 'selected' => $qualified,
        ]);
    }

    public function update(Request $request): void
    {
        $id = $request->paramInt('id');
        $teacher = $id === null ? null : $this->teachers->find($id);
        if ($teacher === null) {
            $this->redirect('/teachers');
            return;
        }

        [$fullName, $phone, $email, $error] = $this->fields($request);
        $subjectIds = $this->subjectIds($request);
        if ($error !== null) {
            $this->view('teachers/form', [
                'error' => $error, 'teacher' => $teacher, 'subjects' => $this->subjects->all(true), 'selected' => $subjectIds,
            ]);
            return;
        }

        $this->service->update($id, $fullName, $phone, $email);
        $this->subjects->setTeacherQualifications($id, $subjectIds);
        $this->handlePhotoUpload($request, $id);
        Flash::set('success', __('teachers.updated'));
        $this->redirect('/teachers');
    }

    /** GET /teachers/{id}/photo -- resolved by database id (§S-10), never a client-supplied path. Gated by teachers.view on the route, same as viewing the teacher itself. */
    public function photo(Request $request): void
    {
        $id = $request->paramInt('id');
        $teacher = $id === null ? null : $this->teachers->find($id);
        $this->uploads->stream($teacher['photo_path'] ?? null);
    }

    public function archive(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null && $this->teachers->find($id) !== null) {
            $this->service->archive($id);
            Flash::set('success', __('teachers.archived'));
        }
        $this->redirect('/teachers');
    }

    public function restore(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null && $this->teachers->find($id) !== null) {
            $this->service->restore($id);
            Flash::set('success', __('teachers.restored'));
        }
        $this->redirect('/teachers/archived');
    }

    /** Optional on every save (§O-18) -- a missing/empty field is not an error. A checked "remove photo" wins over a simultaneously-chosen new file. */
    private function handlePhotoUpload(Request $request, int $teacherId): void
    {
        if ($request->post('remove_photo') === '1') {
            $this->service->removePhoto($teacherId);
            return;
        }

        $file = $request->file('photo');
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return;
        }

        try {
            $this->service->updatePhoto($teacherId, $file);
        } catch (RuntimeException $e) {
            Flash::set('warning', match ($e->getMessage()) {
                'too_large' => __('uploads.error.too_large'),
                'invalid_type', 'not_an_image' => __('uploads.error.invalid_type'),
                default => __('uploads.error.generic'),
            });
        }
    }

    /** @return array{0:string,1:?string,2:?string,3:?string} */
    private function fields(Request $request): array
    {
        $fullName = $request->post('full_name', '') ?: '';
        $phone = $request->post('phone', '') ?: null;
        $email = $request->post('email', '') ?: null;

        if ($fullName === '' || mb_strlen($fullName) < 2) {
            return [$fullName, $phone, $email, __('validation.required')];
        }
        if ($phone !== null && !preg_match('/^[\d\s+\-]{7,20}$/', $phone)) {
            return [$fullName, $phone, $email, __('validation.phone_format')];
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [$fullName, $phone, $email, __('validation.email_format')];
        }
        return [$fullName, $phone, $email, null];
    }

    /** @return int[] */
    private function subjectIds(Request $request): array
    {
        return array_values(array_unique(array_map('intval', $request->postArray('subject_ids'))));
    }
}

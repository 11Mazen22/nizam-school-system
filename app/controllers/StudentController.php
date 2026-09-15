<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Repositories\ClassRepository;
use App\Repositories\GradeRepository;
use App\Repositories\StudentRepository;
use App\Request;
use App\Services\EnrollmentService;
use App\Services\StudentService;
use App\Services\UploadService;
use RuntimeException;

final class StudentController extends Controller
{
    private const RELIGIONS = ['muslim', 'christian', 'other'];
    private const GENDERS = ['m', 'f'];
    // §Q validation rule: "sanity-checked against a configurable plausible age
    // range (catches a mistyped year, not a hard business rule)." §J's settings
    // catalog has no key for this and Settings management isn't Phase 6 scope,
    // so "configurable" is a code constant here rather than an admin-facing
    // toggle -- a K-12-plus-repetition range wide enough never to reject a real
    // student, narrow enough to still catch a fat-fingered birth year.
    private const MAX_PLAUSIBLE_AGE_YEARS = 22;

    public function __construct(
        private readonly StudentRepository $students = new StudentRepository(),
        private readonly GradeRepository $grades = new GradeRepository(),
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly StudentService $service = new StudentService(),
        private readonly EnrollmentService $enrollmentService = new EnrollmentService(),
        private readonly UploadService $uploads = new UploadService(),
    ) {
    }

    public function index(Request $request): void
    {
        $term = trim($request->query('q', '') ?? '');
        $gradeId = (int) $request->query('grade_id', '0');
        $classId = (int) $request->query('class_id', '0');
        
        $yearId = AcademicYearContext::activeYearId() ?? 0;

        // O-25: read caller-supplied values but cap on the server side before use.
        $requestedPerPage = max(1, (int) $request->query('per_page', (string) StudentRepository::MAX_PER_PAGE));
        $perPage = min($requestedPerPage, StudentRepository::MAX_PER_PAGE); // hard cap
        $page    = max(1, (int) $request->query('page', '1'));

        $total    = $this->students->countFiltered('active', $term, $yearId, $gradeId, $classId);
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page     = min($page, $lastPage); // clamp to valid range

        $students = $this->students->paginate($page, $perPage, 'active', $term, $yearId, $gradeId, $classId);
        
        $grades = $this->grades->all(true);
        $classes = $yearId > 0 ? $this->classes->allForYear($yearId) : [];

        $this->view('students/index', [
            'students'    => $students,
            'q'           => $term,
            'page'        => $page,
            'perPage'     => $perPage,
            'total'       => $total,
            'lastPage'    => $lastPage,
            'gradeId'     => $gradeId,
            'classId'     => $classId,
            'grades'      => $grades,
            'classes'     => $classes,
        ]);
    }

    public function archived(Request $request): void
    {
        $this->view('students/archived', ['students' => $this->students->all('archived')]);
    }

    public function show(Request $request): void
    {
        $id = $request->paramInt('id');
        $student = $id === null ? null : $this->students->find($id);
        if ($student === null) {
            $this->redirect('/students');
            return;
        }

        $currentEnrollment = $this->enrollmentService->currentEnrollmentFor($id);
        $availableClasses = [];
        if ($currentEnrollment !== null && $currentEnrollment['status'] === 'active') {
            $availableClasses = $this->classes->forGradeAndYear(
                (int) $currentEnrollment['grade_id'], (int) $currentEnrollment['academic_year_id']
            );
        }

        $this->view('students/show', [
            'student' => $student,
            'history' => $this->enrollmentService->historyFor($id),
            'currentEnrollment' => $currentEnrollment,
            'availableClasses' => $availableClasses,
        ]);
    }

    public function create(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            Flash::set('danger', __('students.no_active_year'));
            $this->redirect('/students');
            return;
        }
        // Every active class in the active year, not pre-filtered by grade --
        // the form has no grade selection yet on first load. students-form.js
        // filters the visible options client-side once a grade IS picked;
        // StudentService::create() re-validates class/grade/year server-side
        // regardless of what the client actually submitted.
        $this->view('students/form', [
            'error' => null, 'student' => null, 'grades' => $this->grades->all(true),
            'classes' => $this->classes->allForYear($yearId),
        ]);
    }

    public function store(Request $request): void
    {
        $fields = $this->fields($request);
        $gradeId = (int) ($request->post('grade_id', '') ?: 0);
        $classId = $request->post('class_id', '') ?: '';
        $yearId = AcademicYearContext::activeYearId();

        $error = $fields['error'] ?? ($gradeId <= 0 ? __('validation.required') : null);
        if ($error !== null) {
            $this->view('students/form', [
                'error' => $error, 'student' => null, 'grades' => $this->grades->all(true),
                'classes' => $yearId === null ? [] : $this->classes->allForYear($yearId),
            ]);
            return;
        }

        try {
            $studentId = $this->service->create(
                $fields['full_name'], $fields['gender'], $fields['dob'], $fields['religion'],
                $fields['phone'], $fields['guardian_phone'], $fields['address'], $fields['notes'],
                $gradeId, $classId !== '' ? (int) $classId : null
            );
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'wrong_scope' => __('students.reassign_failed'),
                'year_closed' => __('academic_years.year_closed'),
                default => __('students.no_active_year'),
            };
            $this->view('students/form', [
                'error' => $message, 'student' => null, 'grades' => $this->grades->all(true),
                'classes' => $yearId === null ? [] : $this->classes->allForYear($yearId),
            ]);
            return;
        }

        $this->handlePhotoUpload($request, $studentId);
        Flash::set('success', __('students.created'));
        $this->redirect('/students');
    }

    public function edit(Request $request): void
    {
        $id = $request->paramInt('id');
        $student = $id === null ? null : $this->students->find($id);
        if ($student === null) {
            $this->redirect('/students');
            return;
        }
        $currentEnrollment = $this->enrollmentService->currentEnrollmentFor($id);
        $yearId = AcademicYearContext::activeYearId();
        $classes = $currentEnrollment !== null && $currentEnrollment['status'] === 'active'
            ? $this->classes->forGradeAndYear((int) $currentEnrollment['grade_id'], (int) $currentEnrollment['academic_year_id'])
            : ($yearId === null ? [] : $this->classes->allForYear($yearId));
        $this->view('students/form', [
            'error' => null, 'student' => $student,
            'grades' => $currentEnrollment === null ? $this->grades->all(true) : [], 'classes' => $classes,
            'currentEnrollment' => $currentEnrollment,
        ]);
    }

    public function update(Request $request): void
    {
        $id = $request->paramInt('id');
        $student = $id === null ? null : $this->students->find($id);
        if ($student === null) {
            $this->redirect('/students');
            return;
        }

        $fields = $this->fields($request);
        if ($fields['error'] !== null) {
            $this->view('students/form', [
                'error' => $fields['error'], 'student' => array_merge($student, $fields), 'grades' => [], 'classes' => [],
                'currentEnrollment' => $this->enrollmentService->currentEnrollmentFor($id),
            ]);
            return;
        }

        $currentEnrollment = $this->enrollmentService->currentEnrollmentFor($id);
        $classId = $request->post('class_id');
        $gradeId = $request->post('grade_id');
        try {
            $this->service->update(
                $id, $fields['full_name'], $fields['gender'], $fields['dob'], $fields['religion'],
                $fields['phone'], $fields['guardian_phone'], $fields['address'], $fields['notes'],
                $classId === null || $classId === '' ? null : (int) $classId,
                $currentEnrollment === null && $gradeId !== null && $gradeId !== '' ? (int) $gradeId : null
            );
        } catch (RuntimeException $e) {
            $currentEnrollment = $this->enrollmentService->currentEnrollmentFor($id);
            $classes = $currentEnrollment !== null && $currentEnrollment['status'] === 'active'
                ? $this->classes->forGradeAndYear((int) $currentEnrollment['grade_id'], (int) $currentEnrollment['academic_year_id'])
                : (($yearId = AcademicYearContext::activeYearId()) === null ? [] : $this->classes->allForYear($yearId));
            $this->view('students/form', [
                'error' => $e->getMessage() === 'year_closed' ? __('academic_years.year_closed') : __('students.reassign_failed'),
                'student' => array_merge($student, $fields), 'grades' => $currentEnrollment === null ? $this->grades->all(true) : [], 'classes' => $classes,
                'currentEnrollment' => $currentEnrollment,
            ]);
            return;
        }
        $this->handlePhotoUpload($request, $id);
        Flash::set('success', __('students.updated'));
        $this->redirect('/students');
    }

    /** GET /students/{id}/photo -- resolved by database id (§S-10), never a client-supplied path. Gated by students.view on the route, same as viewing the student itself. */
    public function photo(Request $request): void
    {
        $id = $request->paramInt('id');
        $student = $id === null ? null : $this->students->find($id);
        $this->uploads->stream($student['photo_path'] ?? null);
    }

    public function archive(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null) {
            try {
                $this->service->archive($id);
                Flash::set('success', __('students.archived'));
            } catch (RuntimeException $e) {
                Flash::set('danger', $e->getMessage() === 'year_closed'
                    ? __('academic_years.year_closed')
                    : __('students.archive_failed'));
            }
        }
        $this->redirect('/students');
    }

    public function restore(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null) {
            try {
                $this->service->restore($id);
                Flash::set('success', __('students.restored'));
            } catch (RuntimeException) {
                Flash::set('danger', __('students.restore_failed'));
            }
        }
        $this->redirect('/students/archived');
    }

    /** §I.9: the Reassign Class modal's POST target. */
    public function reassignClass(Request $request): void
    {
        $id = $request->paramInt('id');
        $newClassId = (int) ($request->post('class_id', '') ?: 0);
        if ($id === null || $newClassId <= 0) {
            $this->redirect('/students');
            return;
        }

        try {
            $this->service->reassignClass($id, $newClassId);
            Flash::set('success', __('students.reassigned'));
        } catch (RuntimeException $e) {
            Flash::set('danger', $e->getMessage() === 'year_closed'
                ? __('academic_years.year_closed')
                : __('students.reassign_failed'));
        }
        $this->redirect("/students/{$id}");
    }

    /** Optional on every save (§O-18) -- a missing/empty field is not an error. A checked "remove photo" wins over a simultaneously-chosen new file, since a user clearing the photo almost certainly didn't also mean to replace it in the same submission. */
    private function handlePhotoUpload(Request $request, int $studentId): void
    {
        if ($request->post('remove_photo') === '1') {
            $this->service->removePhoto($studentId);
            return;
        }

        $file = $request->file('photo');
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return;
        }

        try {
            $this->service->updatePhoto($studentId, $file);
        } catch (RuntimeException $e) {
            Flash::set('warning', match ($e->getMessage()) {
                'too_large' => __('uploads.error.too_large'),
                'invalid_type', 'not_an_image' => __('uploads.error.invalid_type'),
                default => __('uploads.error.generic'),
            });
        }
    }

    /** @return array{full_name:string, gender:string, dob:string, religion:string, phone:?string, guardian_phone:?string, address:?string, notes:?string, error:?string} */
    private function fields(Request $request): array
    {
        $fullName = $request->post('full_name', '') ?: '';
        $gender = $request->post('gender', '') ?: '';
        $dob = $request->post('date_of_birth', '') ?: '';
        $religion = $request->post('religion', '') ?: '';
        $phone = $request->post('phone', '') ?: null;
        $guardianPhone = $request->post('guardian_phone', '') ?: null;
        $address = $request->post('address', '') ?: null;
        $notes = $request->post('notes', '') ?: null;

        $error = null;
        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150 || $dob === '' || $religion === '' || !in_array($gender, self::GENDERS, true)) {
            $error = __('validation.required');
        } elseif (!in_array($religion, self::RELIGIONS, true)) {
            $error = __('validation.religion_invalid');
        } else {
            $dobDate = \DateTime::createFromFormat('Y-m-d', $dob);
            $today = new \DateTime('today');
            if ($dobDate === false || $dobDate > $today) {
                $error = __('validation.dob_invalid');
            } elseif ($dobDate < (new \DateTime('-' . self::MAX_PLAUSIBLE_AGE_YEARS . ' years'))) {
                $error = __('validation.dob_implausible');
            } elseif ($phone !== null && !preg_match('/^[\d\s+\-]{7,20}$/', $phone)) {
                $error = __('validation.phone_format');
            } elseif ($guardianPhone !== null && !preg_match('/^[\d\s+\-]{7,20}$/', $guardianPhone)) {
                $error = __('validation.phone_format');
            }
        }

        return [
            'full_name' => $fullName, 'gender' => $gender, 'dob' => $dob, 'religion' => $religion,
            'phone' => $phone, 'guardian_phone' => $guardianPhone, 'address' => $address, 'notes' => $notes,
            'error' => $error,
        ];
    }
}

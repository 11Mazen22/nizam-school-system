<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Repositories\ClassRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\TeacherAssignmentRepository;
use App\Repositories\TeacherRepository;
use App\Request;
use App\Services\AssignmentService;
use App\Services\WorkloadService;
use RuntimeException;

final class AssignmentController extends Controller
{
    public function __construct(
        private readonly TeacherAssignmentRepository $assignments = new TeacherAssignmentRepository(),
        private readonly TeacherRepository $teachers = new TeacherRepository(),
        private readonly SubjectRepository $subjects = new SubjectRepository(),
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly AssignmentService $service = new AssignmentService(),
        private readonly WorkloadService $workload = new WorkloadService(),
    ) {
    }

    public function index(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        $this->view('assignments/index', [
            'assignments' => $yearId === null ? [] : $this->assignments->allForYear($yearId),
            'hasActiveYear' => $yearId !== null,
            'archivedCount' => $yearId === null ? 0 : $this->assignments->countArchivedForYear($yearId),
            'teachers' => $this->teachers->all('active'),
            'subjects' => $this->subjects->all(true),
            'classes' => $yearId === null ? [] : $this->classes->allForYear($yearId),
        ]);
    }

    public function archived(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        $this->view('assignments/archived', [
            'assignments' => $yearId === null ? [] : $this->assignments->archivedForYear($yearId),
        ]);
    }

    public function workloadSummary(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        $this->view('assignments/workload', [
            'summary' => $yearId === null ? null : $this->workload->calculateForYear($yearId),
            'hasActiveYear' => $yearId !== null,
        ]);
    }

    public function create(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            $this->redirect('/assignments');
            return;
        }
        $this->view('assignments/form', [
            'error' => null,
            'teachers' => $this->teachers->all('active'),
            'subjects' => $this->subjects->all(true),
            'classes' => $this->classes->allForYear($yearId),
            'qualificationWarning' => false,
        ]);
    }

    public function store(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            $this->redirect('/assignments');
            return;
        }

        [$teacherId, $subjectId, $classId, $periods, $error] = $this->fields($request);
        if ($error === null) {
            // Checked BEFORE create() so the admin sees it either way: on the
            // happy path as a flash alongside the success message (§O-9 is a
            // warn, not a block, so the save already happened by the time
            // they'd see a form re-render); the checkQualification() call
            // below the catch block covers the validation-failure re-render.
            $unqualified = $this->service->checkQualification($teacherId, $subjectId);
            try {
                $this->service->create($teacherId, $subjectId, $classId, $yearId, $periods);
                if ($unqualified) {
                    Flash::set('warning', __('assignments.qualification_warning'));
                }
                Flash::set('success', __('assignments.created'));
                $this->redirect('/assignments');
                return;
            } catch (RuntimeException $e) {
                $error = match ($e->getMessage()) {
                    'duplicate' => __('assignments.duplicate'),
                    'year_closed' => __('academic_years.year_closed'),
                    default => __('assignments.invalid_periods'),
                };
            }
        }

        $this->view('assignments/form', [
            'error' => $error,
            'teachers' => $this->teachers->all('active'),
            'subjects' => $this->subjects->all(true),
            'classes' => $this->classes->allForYear($yearId),
            'qualificationWarning' => $teacherId > 0 && $subjectId > 0 && $this->service->checkQualification($teacherId, $subjectId),
        ]);
    }

    public function update(Request $request): void
    {
        $id = $request->paramInt('id');
        $assignment = $id === null ? null : $this->assignments->find($id);
        if ($assignment === null) {
            $this->redirect('/assignments');
            return;
        }

        $periods = (int) ($request->post('weekly_periods', '') ?: 0);
        try {
            $this->service->update($id, $periods);
            Flash::set('success', __('assignments.updated'));
        } catch (RuntimeException $e) {
            Flash::set('danger', $e->getMessage() === 'year_closed'
                ? __('academic_years.year_closed')
                : __('assignments.invalid_periods'));
        }
        $this->redirect('/assignments');
    }

    public function archive(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null) {
            try {
                $this->service->archive($id);
                Flash::set('success', __('assignments.archived'));
            } catch (RuntimeException $e) {
                Flash::set('danger', $e->getMessage() === 'year_closed'
                    ? __('academic_years.year_closed')
                    : __('assignments.archive_failed'));
            }
        }
        $this->redirect('/assignments');
    }

    public function restore(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null) {
            $this->redirect('/assignments/archived');
            return;
        }
        try {
            $this->service->restore($id);
            Flash::set('success', __('assignments.restored'));
        } catch (RuntimeException $e) {
            Flash::set('danger', match ($e->getMessage()) {
                'year_closed' => __('academic_years.year_closed'),
                'duplicate'   => __('assignments.duplicate'),
                default       => __('assignments.restore_failed'),
            });
        }
        $this->redirect('/assignments/archived');
    }

    /** @return array{0:int,1:int,2:int,3:int,4:?string} */
    private function fields(Request $request): array
    {
        $teacherId = (int) ($request->post('teacher_id', '') ?: 0);
        $subjectId = (int) ($request->post('subject_id', '') ?: 0);
        $classId = (int) ($request->post('class_id', '') ?: 0);
        $periods = (int) ($request->post('weekly_periods', '') ?: 0);

        if ($teacherId <= 0 || $subjectId <= 0 || $classId <= 0) {
            return [$teacherId, $subjectId, $classId, $periods, __('validation.required')];
        }
        return [$teacherId, $subjectId, $classId, $periods, null];
    }
}

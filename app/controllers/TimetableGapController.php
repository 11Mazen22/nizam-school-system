<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Repositories\ClassRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\TeacherRepository;
use App\Repositories\TimetableGapRepository;
use App\Request;
use App\Services\TimetableGapService;
use RuntimeException;

/**
 * Nizam -- printed timetable cells that could not be loaded, and the mapping
 * between the grid's first names and real staff records.
 */
final class TimetableGapController extends Controller
{
    public function __construct(
        private readonly TimetableGapService $service = new TimetableGapService(),
        private readonly TimetableGapRepository $gaps = new TimetableGapRepository(),
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly TeacherRepository $teachers = new TeacherRepository(),
        private readonly SubjectRepository $subjects = new SubjectRepository(),
    ) {
    }

    /** GET /timetable/gaps */
    public function index(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            $this->redirect('/timetable');
            return;
        }

        $status = (string) ($request->query('status', 'open') ?? 'open');
        $status = in_array($status, ['open', 'resolved', 'ignored', 'all'], true) ? $status : 'open';
        $reason = (string) ($request->query('reason', '') ?? '');
        $reason = in_array($reason, ['no_teacher', 'teacher_conflict', 'unmapped_teacher', 'absence', 'other'], true) ? $reason : '';
        $classId = (int) ($request->query('class_id', '0') ?? '0');
        $teacher = (string) ($request->query('teacher', '') ?? '');

        $filters = [];
        if ($status !== 'all') {
            $filters['status'] = $status;
        }
        if ($reason !== '') {
            $filters['reason'] = $reason;
        }
        if ($classId > 0) {
            $filters['class_id'] = $classId;
        }
        if ($teacher !== '') {
            $filters['teacher'] = $teacher;
        }

        $gaps = $this->gaps->forYear($yearId, $filters);

        $this->view('timetable/gaps', [
            'gaps' => $gaps,
            'counts' => $this->gaps->counts($yearId),
            'classes' => $this->classes->allForYear($yearId),
            'teachers' => $this->teachers->all('active'),
            'subjects' => $this->subjects->all(true),
            'status' => $status,
            'reason' => $reason,
            'classId' => $classId,
            'teacher' => $teacher,
            'dayLabels' => (new \App\Services\TimetableService())->getDayLabels(),
        ]);
    }

    /** POST /timetable/gaps/{id}/resolve */
    public function resolve(Request $request): void
    {
        $id = $request->paramInt('id');
        $yearId = AcademicYearContext::activeYearId();
        if ($id === null || $yearId === null) {
            $this->redirect('/timetable/gaps');
            return;
        }

        $teacherId = (int) ($request->post('teacher_id', '0') ?? '0');
        $subjectId = (int) ($request->post('subject_id', '0') ?? '0');
        $note = $request->post('note');

        try {
            $this->service->resolve($id, $yearId, $teacherId, $subjectId, $note);
            Flash::set('success', __('timetable.gaps_resolved_ok'));
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'teacher_conflict' => __('timetable.gaps_error_conflict'),
                'class_conflict' => __('timetable.gaps_error_class'),
                'gap_closed' => __('timetable.gaps_error_closed'),
                'gap_not_found' => __('timetable.gaps_error_missing'),
                default => __('timetable.gaps_error'),
            };
            Flash::set('danger', $message);
        }

        $this->redirect('/timetable/gaps' . $this->returnQuery($request));
    }

    /** POST /timetable/gaps/{id}/ignore */
    public function ignore(Request $request): void
    {
        $id = $request->paramInt('id');
        $yearId = AcademicYearContext::activeYearId();
        if ($id !== null && $yearId !== null) {
            try {
                $this->service->ignore($id, $yearId);
                Flash::set('success', __('timetable.gaps_ignored_ok'));
            } catch (RuntimeException) {
                Flash::set('danger', __('timetable.gaps_error_missing'));
            }
        }
        $this->redirect('/timetable/gaps' . $this->returnQuery($request));
    }

    /** POST /timetable/gaps/{id}/reopen */
    public function reopen(Request $request): void
    {
        $id = $request->paramInt('id');
        $yearId = AcademicYearContext::activeYearId();
        if ($id !== null && $yearId !== null) {
            try {
                $this->service->reopen($id, $yearId);
                Flash::set('success', __('timetable.gaps_reopened'));
            } catch (RuntimeException) {
                Flash::set('danger', __('timetable.gaps_error_missing'));
            }
        }
        $this->redirect('/timetable/gaps' . $this->returnQuery($request));
    }

    /** GET /timetable/staff */
    public function staff(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            $this->redirect('/timetable');
            return;
        }

        $this->view('timetable/staff', [
            'rows' => $this->service->staffOverview($yearId),
            'teachers' => $this->teachers->all('active'),
            'openGaps' => $this->gaps->counts($yearId)['open'],
        ]);
    }

    /** POST /timetable/staff/rename */
    public function rename(Request $request): void
    {
        $teacherId = (int) ($request->post('teacher_id', '0') ?? '0');
        $fullName = (string) ($request->post('full_name', '') ?? '');
        $printed = (string) ($request->post('printed_name', '') ?? '');

        try {
            $this->service->renameTeacher($teacherId, $fullName, $printed);
            Flash::set('success', __('timetable.staff_renamed'));
        } catch (RuntimeException $e) {
            Flash::set('danger', match ($e->getMessage()) {
                'name_required' => __('timetable.staff_error_name'),
                'teacher_invalid' => __('timetable.staff_error_teacher'),
                default => __('validation.required'),
            });
        }

        $this->redirect('/timetable/staff');
    }

    /** POST /timetable/staff/attach */
    public function attach(Request $request): void
    {
        $teacherId = (int) ($request->post('teacher_id', '0') ?? '0');
        $alias = (string) ($request->post('alias', '') ?? '');

        try {
            $this->service->attachAlias($teacherId, $alias);
            Flash::set('success', __('timetable.staff_attached'));
        } catch (RuntimeException $e) {
            Flash::set('danger', match ($e->getMessage()) {
                'alias_required' => __('timetable.staff_error_name'),
                'teacher_invalid' => __('timetable.staff_error_teacher'),
                default => __('validation.required'),
            });
        }

        $this->redirect('/timetable/staff');
    }

    /** POST /timetable/staff/create */
    public function createTeacher(Request $request): void
    {
        $printed = (string) ($request->post('printed_name', '') ?? '');
        $fullName = (string) ($request->post('full_name', '') ?? '');

        try {
            $this->service->createTeacherFromPrinted($printed, $fullName);
            Flash::set('success', __('timetable.staff_created'));
        } catch (RuntimeException $e) {
            Flash::set('danger', match ($e->getMessage()) {
                'alias_required', 'name_required' => __('timetable.staff_error_name'),
                default => __('validation.required'),
            });
        }

        $this->redirect('/timetable/staff');
    }

    /** Preserve the operator's filters across a resolve/ignore POST. */
    private function returnQuery(Request $request): string
    {
        $params = [];
        foreach (['status', 'reason', 'class_id', 'teacher'] as $key) {
            $value = $request->post('return_' . $key);
            if ($value !== null && $value !== '') {
                $params[$key] = $value;
            }
        }

        return $params === [] ? '' : '?' . http_build_query($params);
    }
}

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
use App\Repositories\TimetableRepository;
use App\Request;
use App\Services\SchoolSettingsService;
use App\Services\TimetableService;
use RuntimeException;

final class TimetableController extends Controller
{
    public function __construct(
        private readonly TimetableService  $service  = new TimetableService(),
        private readonly ClassRepository   $classes  = new ClassRepository(),
        private readonly SubjectRepository $subjects = new SubjectRepository(),
        private readonly TeacherRepository $teachers = new TeacherRepository(),
    ) {
    }

    /** GET /timetable — class picker + grid view. */
    public function index(Request $request): void
    {
        $yearId  = AcademicYearContext::activeYearId();
        $classes = $yearId ? $this->classes->allForYear($yearId) : [];
        $classId = (int) ($request->query('class_id') ?? 0);

        $grid      = $classId > 0 ? $this->service->getGrid($classId) : [];
        $allowedDays = SchoolSettingsService::timetableDays();
        $dayLabels = array_filter(
            $this->service->getDayLabels(),
            static fn (int $day): bool => in_array($day, $allowedDays, true),
            ARRAY_FILTER_USE_KEY
        );
        $maxPeriods= $this->service->getMaxPeriods();

        $subjects  = $yearId ? $this->subjects->all(true) : [];
        $teachers  = $this->teachers->all('active');
        $openGaps  = $yearId !== null ? (new TimetableGapRepository())->counts($yearId)['open'] : 0;
        $openClashes = $yearId !== null ? (new TimetableRepository())->printedClashCount($yearId) : 0;

        // A cell the printed paper double-books carries its printed teacher in
        // printed_teacher and no teacher_id (the database still refuses any
        // real double-booking), so the grid keeps the printed name visible and
        // marks it as a paper clash.
        $this->view('timetable/index', compact(
            'classes', 'classId', 'grid', 'dayLabels', 'maxPeriods', 'subjects', 'teachers', 'openGaps', 'openClashes', 'allowedDays'
        ));
    }

    /** GET /timetable/clashes -- dedicated printed-clash resolution board. */
    public function clashes(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            $this->redirect('/timetable');
            return;
        }

        $board = $this->service->clashBoard($yearId);
        $this->view('timetable/clashes', [
            'clashes' => $board['clashes'],
            'resolutions' => $board['resolutions'],
            'dayLabels' => $this->service->getDayLabels(),
            'openGaps' => (new TimetableGapRepository())->counts($yearId)['open'],
        ]);
    }

    /** POST /timetable/clashes/{id}/resolve */
    public function resolveClash(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null) {
            $this->redirect('/timetable/clashes');
            return;
        }

        try {
            $this->service->resolvePrintedClash(
                $id,
                (int) ($request->post('teacher_id', '0') ?? '0'),
                $request->post('note')
            );
            Flash::set('success', __('timetable.clash_resolved_ok'));
        } catch (RuntimeException $exception) {
            $message = match ($exception->getMessage()) {
                'teacher_conflict' => __('timetable.gaps_error_conflict'),
                'teacher_invalid' => __('timetable.clash_error_teacher'),
                'not_a_clash' => __('timetable.clash_error_not_clash'),
                'not_found' => __('timetable.gaps_error_missing'),
                'year_closed' => __('academic_years.year_closed'),
                default => __('timetable.clash_error'),
            };
            Flash::set('danger', $message);
        }

        $this->redirect('/timetable/clashes');
    }

    /** POST /timetable - add a slot. */
    public function store(Request $request): void
    {
        $classId   = (int) ($request->post('class_id', '0') ?? '0');
        $day       = (int) ($request->post('day_of_week', '0') ?? '0');
        $period    = (int) ($request->post('period_number', '0') ?? '0');
        $subjectId = (int) ($request->post('subject_id', '0') ?? '0');
        $teacherId = (int) ($request->post('teacher_id', '0') ?? '0');

        if (!in_array($day, SchoolSettingsService::timetableDays(), true)) {
            Flash::set('danger', __('timetable.non_working_day'));
            $this->redirect('/timetable?class_id=' . $classId);
            return;
        }

        try {
            $this->service->addSlot($classId, $day, $period, $subjectId, $teacherId);
            Flash::set('success', __('timetable.slot_added'));
        } catch (RuntimeException $e) {
            $msg = match ($e->getMessage()) {
                'teacher_conflict' => __('timetable.error_teacher_conflict'),
                'class_conflict'   => __('timetable.error_class_conflict'),
                'no_active_year'   => __('students.no_active_year'),
                'year_closed'      => __('academic_years.year_closed'),
                'validation_error' => __('timetable.error_invalid_assignment'),
                default            => __('validation.required'),
            };
            Flash::set('danger', $msg);
        }
        $this->redirect('/timetable?class_id=' . $classId);
    }

    /** POST /timetable/{id}/delete — delete a slot. */
    public function delete(Request $request): void
    {
        $id      = $request->paramInt('id');
        $classId = (int) ($request->post('class_id', '0') ?? '0');
        if ($id !== null) {
            try {
                $this->service->deleteSlot($id);
                Flash::set('success', __('timetable.slot_deleted'));
            } catch (RuntimeException $e) {
                Flash::set('danger', $e->getMessage() === 'year_closed'
                    ? __('academic_years.year_closed')
                    : __('validation.required'));
            }
        }
        $this->redirect('/timetable?class_id=' . $classId);
    }

    /** POST /timetable/clear — wipe entire class timetable. */
    public function clear(Request $request): void
    {
        $classId = (int) ($request->post('class_id', '0') ?? '0');
        try {
            $this->service->clearClass($classId);
            Flash::set('success', __('timetable.cleared'));
        } catch (RuntimeException $e) {
            Flash::set('danger', $e->getMessage() === 'year_closed'
                ? __('academic_years.year_closed')
                : __('students.no_active_year'));
        }
        $this->redirect('/timetable?class_id=' . $classId);
    }
}

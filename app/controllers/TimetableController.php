<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Repositories\ClassRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\TeacherRepository;
use App\Request;
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
        $dayLabels = $this->service->getDayLabels();
        $maxPeriods= $this->service->getMaxPeriods();

        $subjects  = $yearId ? $this->subjects->all(true) : [];
        $teachers  = $this->teachers->all('active');

        $this->view('timetable/index', compact(
            'classes', 'classId', 'grid', 'dayLabels', 'maxPeriods', 'subjects', 'teachers'
        ));
    }

    /** POST /timetable — add a slot. */
    public function store(Request $request): void
    {
        $classId   = (int) ($request->post('class_id', '0') ?? '0');
        $day       = (int) ($request->post('day_of_week', '0') ?? '0');
        $period    = (int) ($request->post('period_number', '0') ?? '0');
        $subjectId = (int) ($request->post('subject_id', '0') ?? '0');
        $teacherId = (int) ($request->post('teacher_id', '0') ?? '0');

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

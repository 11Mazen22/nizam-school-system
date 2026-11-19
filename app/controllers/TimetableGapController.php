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

        // The screen, its document and the query share one filter definition.
        $filter = TimetableGapService::normalizeFilters([
            'status' => $request->query('status', 'open'),
            'reason' => $request->query('reason', ''),
            'class_id' => $request->query('class_id', '0'),
            'teacher' => $request->query('teacher', ''),
        ]);
        $filters = $filter['filters'];
        $status = $filter['status'];
        $reason = $filter['reason'];
        $classId = $filter['class_id'];
        $teacher = $filter['teacher'];

        $gaps = $this->gaps->forYear($yearId, $filters);
        $teachers = $this->teachers->all('active');

        // A reason with no rows in the selected status is not a useful filter.
        // The same reason can still be chosen under another status, so the
        // resolved history stays reachable instead of being quietly deleted.
        $reasonCounts = $this->gaps->reasonCountsByStatus($yearId);
        $visibleReasons = array_values(array_filter(
            TimetableGapService::REASONS,
            static function (string $value) use ($status, $reason, $reasonCounts): bool {
                if ($value === $reason) {
                    return true;
                }
                if ($status === 'all') {
                    foreach ($reasonCounts as $byReason) {
                        if (($byReason[$value] ?? 0) > 0) {
                            return true;
                        }
                    }
                    return false;
                }
                return ($reasonCounts[$status][$value] ?? 0) > 0;
            }
        ));

        // The printed grids use first names; resolving a collision needs the
        // class that already holds that teacher in this exact period, plus the
        // teachers who are actually free then.
        $teacherIdsByName = [];
        foreach ($teachers as $teacherRow) {
            $teacherIdsByName[(string) $teacherRow['full_name']] = (int) $teacherRow['id'];
        }

        $this->view('timetable/gaps', [
            'gaps' => $gaps,
            'counts' => $this->gaps->counts($yearId),
            'classes' => $this->classes->allForYear($yearId),
            'teachers' => $teachers,
            'bookings' => $this->gaps->bookings($yearId),
            'teacherIdsByName' => $teacherIdsByName,
            'subjects' => $this->subjects->all(true),
            'status' => $status,
            'reason' => $reason,
            'classId' => $classId,
            'teacher' => $teacher,
            'statusOptions' => array_map(
                static fn (string $value): array => ['value' => $value, 'label' => TimetableGapService::statusLabel($value)],
                TimetableGapService::STATUSES
            ),
            'reasonOptions' => array_map(
                static fn (string $value): array => ['value' => $value, 'label' => TimetableGapService::reasonLabel($value)],
                $visibleReasons
            ),
            'reasonLabels' => array_combine(
                TimetableGapService::REASONS,
                array_map([TimetableGapService::class, 'reasonLabel'], TimetableGapService::REASONS)
            ),
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

        $rows = $this->service->staffOverview($yearId);
        foreach ($rows as &$row) {
            $row['conflict'] = null;
            if ($row['teacher_id'] !== null && (int) $row['gaps'] > 0) {
                $row['conflict'] = $this->service->mappingConflicts(
                    $yearId,
                    (string) $row['printed_name'],
                    (int) $row['teacher_id']
                );
            }
        }
        unset($row);

        $this->view('timetable/staff', [
            'rows' => $rows,
            'teachers' => $this->teachers->all('active'),
            'openGaps' => $this->gaps->counts($yearId)['open'],
        ]);
    }

    /** GET /timetable/staff/check — live autocomplete + conflict check. */
    public function checkMapping(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['conflict' => false, 'alias_owner' => null, 'slot_conflicts' => [], 'suggestions' => []]);
            return;
        }

        $printed = trim((string) ($request->query('printed_name', '') ?? ''));
        $teacherId = (int) ($request->query('teacher_id', '0') ?? 0);
        $result = $this->service->mappingConflicts($yearId, $printed, $teacherId > 0 ? $teacherId : null);

        $suggestions = [];
        if ($printed !== '') {
            foreach ($this->teachers->search($printed) as $teacher) {
                $suggestions[] = [
                    'id' => (int) $teacher['id'],
                    'name' => (string) $teacher['full_name'],
                    'code' => (string) ($teacher['teacher_code'] ?? ''),
                ];
            }
        }
        $result['suggestions'] = array_slice($suggestions, 0, 8);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
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
                'name_required', 'alias_required' => __('timetable.staff_error_name'),
                'teacher_invalid' => __('timetable.staff_error_teacher'),
                'teacher_duplicate' => __('timetable.staff_error_duplicate'),
                'name_too_long', 'alias_too_long' => __('timetable.staff_error_too_long'),
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

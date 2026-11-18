<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Request;
use App\Services\LessonCoverService;
use App\Services\TimetableService;
use RuntimeException;

/**
 * Nizam -- date-specific substitute cover.
 *
 * One screen per date: every lesson of that weekday, its regular teacher, an
 * approved-leave marker, and only the teachers who are actually free in that
 * period. Assigning a cover never touches the weekly timetable.
 */
final class LessonCoverController extends Controller
{
    public function __construct(
        private readonly LessonCoverService $service = new LessonCoverService(),
    ) {
    }

    /** GET /timetable/covers */
    public function index(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            $this->redirect('/timetable');
            return;
        }

        $date = (string) ($request->query('date', '') ?? '');
        if ($date === '') {
            $date = date('Y-m-d');
        }

        try {
            $board = $this->service->board($yearId, $date);
        } catch (RuntimeException) {
            Flash::set('danger', __('timetable.covers_error_date'));
            $this->redirect('/timetable/covers');
            return;
        }

        $board['dayLabels'] = (new TimetableService())->getDayLabels();
        $this->view('timetable/covers', $board);
    }

    /** POST /timetable/covers/assign */
    public function assign(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            $this->redirect('/timetable');
            return;
        }

        $date = (string) ($request->post('date', '') ?? '');
        $timetableId = (int) ($request->post('timetable_id', '0') ?? '0');
        $teacherId = (int) ($request->post('cover_teacher_id', '0') ?? '0');
        $note = $request->post('note');
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        try {
            $this->service->assign($yearId, $date, $timetableId, $teacherId, $userId, $note);
            Flash::set('success', __('timetable.covers_saved'));
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'non_school_day' => __('timetable.covers_error_day'),
                'lesson_missing' => __('timetable.covers_error_lesson'),
                'teacher_invalid' => __('timetable.covers_error_teacher'),
                'same_teacher' => __('timetable.covers_error_same'),
                'teacher_on_leave' => __('timetable.covers_error_leave'),
                'teacher_busy' => __('timetable.covers_error_busy'),
                default => __('timetable.covers_error_date'),
            };
            Flash::set('danger', $message);
        }

        $this->redirect('/timetable/covers?date=' . rawurlencode($date));
    }

    /** POST /timetable/covers/remove */
    public function remove(Request $request): void
    {
        $date = (string) ($request->post('date', '') ?? '');
        $timetableId = (int) ($request->post('timetable_id', '0') ?? '0');

        try {
            $this->service->remove($date, $timetableId);
            Flash::set('success', __('timetable.covers_removed'));
        } catch (RuntimeException) {
            Flash::set('danger', __('timetable.covers_error_date'));
        }

        $this->redirect('/timetable/covers?date=' . rawurlencode($date));
    }
}

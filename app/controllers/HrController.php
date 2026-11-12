<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Request;
use App\Response;
use App\Repositories\HrRepository;
use App\Repositories\TeacherRepository;

final class HrController extends Controller
{
    private HrRepository $repository;

    public function __construct(?HrRepository $repository = null)
    {
        $this->repository = $repository ?? new HrRepository();
    }

    public function index(Request $request): void
    {
        $departments = $this->repository->allDepartments();
        $designations = $this->repository->allDesignations();
        $leaves = $this->repository->allLeaves();
        $teachers = (new TeacherRepository())->all();

        $this->view('hr/index', [
            'departments'  => $departments,
            'designations' => $designations,
            'leaves'       => $leaves,
            'teachers'     => $teachers,
        ]);
    }

    public function leaves(Request $request): void
    {
        $status = $request->query('status');
        $leaves = $this->repository->allLeaves($status ? (string) $status : null);
        $teachers = (new TeacherRepository())->all();

        $this->view('hr/leaves', [
            'leaves'       => $leaves,
            'teachers'     => $teachers,
            'activeStatus' => $status,
        ]);
    }

    public function storeLeave(Request $request): void
    {
        $teacherId = (int) $request->post('teacher_id');
        $type = (string) $request->post('leave_type', 'annual');
        $start = (string) $request->post('start_date');
        $end = (string) $request->post('end_date');
        $reason = trim((string) $request->post('reason'));

        if ($teacherId <= 0 || $start === '' || $end === '' || $reason === '') {
            Flash::error(__('hr.missing_leave_fields'));
            Response::redirect('/hr/leaves');
            return;
        }

        $this->repository->createLeave([
            'teacher_id' => $teacherId,
            'leave_type' => $type,
            'start_date' => $start,
            'end_date'   => $end,
            'reason'     => $reason,
        ]);

        Flash::success(__('hr.leave_requested_success'));
        Response::redirect('/hr/leaves');
    }

    public function actionLeave(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null) {
            Response::redirect('/hr/leaves');
            return;
        }
        $status = (string) $request->post('status'); // approved or rejected
        $notes = trim((string) $request->post('action_notes')) ?: null;
        $userId = (int) ($_SESSION['user_id'] ?? 1);

        if ($this->repository->updateLeaveStatus((int) $id, $status, $userId, $notes)) {
            Flash::success(__('hr.leave_status_updated'));
        } else {
            Flash::error(__('hr.leave_not_pending'));
        }
        Response::redirect('/hr/leaves');
    }

    public function storeDepartment(Request $request): void
    {
        $code = trim((string) $request->post('code'));
        $nameEn = trim((string) $request->post('name_en'));
        $nameAr = trim((string) $request->post('name_ar'));

        if ($code === '' || $nameEn === '' || $nameAr === '') {
            Flash::error(__('hr.missing_dept_fields'));
            Response::redirect('/hr');
            return;
        }

        $this->repository->createDepartment($code, $nameEn, $nameAr);
        Flash::success(__('hr.department_created_success'));
        Response::redirect('/hr');
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Request;
use App\Response;
use App\Repositories\StudentRepository;
use App\Repositories\TransportRepository;

final class TransportController extends Controller
{
    private TransportRepository $repository;

    public function __construct(?TransportRepository $repository = null)
    {
        $this->repository = $repository ?? new TransportRepository();
    }

    public function index(Request $request): void
    {
        $routes = $this->repository->allRoutes();
        $this->view('transport/index', ['routes' => $routes]);
    }

    public function show(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null) {
            Response::redirect('/transport');
            return;
        }
        $route = $this->repository->findRoute($id);
        if ($route === null) {
            Flash::error(__('transport.not_found'));
            Response::redirect('/transport');
            return;
        }

        $students = $this->repository->getStudentsForRoute((int) $id);
        $allStudents = (new StudentRepository())->all();

        $this->view('transport/show', [
            'route'       => $route,
            'students'    => $students,
            'allStudents' => $allStudents,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('transport/create');
    }

    public function store(Request $request): void
    {
        $name = trim((string) $request->post('route_name'));
        $vehicle = trim((string) $request->post('vehicle_number'));
        $driver = trim((string) $request->post('driver_name'));
        $phone = trim((string) $request->post('driver_phone'));

        if ($name === '' || $vehicle === '' || $driver === '' || $phone === '') {
            Flash::error(__('transport.missing_fields'));
            Response::redirect('/transport/create');
            return;
        }

        $id = $this->repository->createRoute([
            'route_name'     => $name,
            'vehicle_number' => $vehicle,
            'driver_name'    => $driver,
            'driver_phone'   => $phone,
            'capacity'       => (int) $request->post('capacity', 30),
            'fee_amount'     => (float) $request->post('fee_amount', 0.0),
        ]);

        Flash::success(__('transport.created_success'));
        Response::redirect('/transport/' . $id);
    }

    public function assign(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null) {
            Response::redirect('/transport');
            return;
        }
        $studentId = (int) $request->post('student_id');
        $pickup = trim((string) $request->post('pickup_point')) ?: null;
        $dropoff = trim((string) $request->post('dropoff_point')) ?: null;

        if ($studentId > 0) {
            try {
                $this->repository->assignStudent($studentId, (int) $id, $pickup, $dropoff);
                Flash::success(__('transport.assigned_success'));
            } catch (\RuntimeException $e) {
                $key = 'transport.' . $e->getMessage();
                $message = __($key);
                Flash::error($message === $key ? __('transport.missing_fields') : $message);
            }
        }

        Response::redirect('/transport/' . $id);
    }
}

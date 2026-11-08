<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Request;
use App\Response;
use App\Repositories\GuardianRepository;

final class GuardianController extends Controller
{
    private GuardianRepository $repository;

    public function __construct(?GuardianRepository $repository = null)
    {
        $this->repository = $repository ?? new GuardianRepository();
    }

    public function index(Request $request): void
    {
        $guardians = $this->repository->all();
        $this->view('guardians/index', ['guardians' => $guardians]);
    }

    public function show(Request $request, string $id): void
    {
        $guardian = $this->repository->find((int) $id);
        if ($guardian === null) {
            Flash::error(__('guardians.not_found'));
            Response::redirect('/guardians');
            return;
        }

        $students = $this->repository->getStudentsForGuardian((int) $id);
        $this->view('guardians/show', ['guardian' => $guardian, 'students' => $students]);
    }

    public function create(Request $request): void
    {
        $this->view('guardians/create');
    }

    public function store(Request $request): void
    {
        $fullName = trim((string) $request->post('full_name'));
        $phone = trim((string) $request->post('phone'));

        if ($fullName === '' || $phone === '') {
            Flash::error(__('guardians.required_fields_missing'));
            Response::redirect('/guardians/create');
            return;
        }

        $id = $this->repository->create([
            'full_name'         => $fullName,
            'phone'             => $phone,
            'email'             => trim((string) $request->post('email')) ?: null,
            'national_id'       => trim((string) $request->post('national_id')) ?: null,
            'relationship_type' => (string) $request->post('relationship_type', 'father'),
            'occupation'        => trim((string) $request->post('occupation')) ?: null,
            'address'           => trim((string) $request->post('address')) ?: null,
            'notes'             => trim((string) $request->post('notes')) ?: null,
        ]);

        Flash::success(__('guardians.create_success'));
        Response::redirect('/guardians/' . $id);
    }

    public function edit(Request $request, string $id): void
    {
        $guardian = $this->repository->find((int) $id);
        if ($guardian === null) {
            Flash::error(__('guardians.not_found'));
            Response::redirect('/guardians');
            return;
        }

        $this->view('guardians/edit', ['guardian' => $guardian]);
    }

    public function update(Request $request, string $id): void
    {
        $fullName = trim((string) $request->post('full_name'));
        $phone = trim((string) $request->post('phone'));

        if ($fullName === '' || $phone === '') {
            Flash::error(__('guardians.required_fields_missing'));
            Response::redirect('/guardians/' . $id . '/edit');
            return;
        }

        $this->repository->update((int) $id, [
            'full_name'         => $fullName,
            'phone'             => $phone,
            'email'             => trim((string) $request->post('email')) ?: null,
            'national_id'       => trim((string) $request->post('national_id')) ?: null,
            'relationship_type' => (string) $request->post('relationship_type', 'father'),
            'occupation'        => trim((string) $request->post('occupation')) ?: null,
            'address'           => trim((string) $request->post('address')) ?: null,
            'notes'             => trim((string) $request->post('notes')) ?: null,
        ]);

        Flash::success(__('guardians.update_success'));
        Response::redirect('/guardians/' . $id);
    }

    public function linkStudent(Request $request, string $studentId): void
    {
        $guardianId = (int) $request->post('guardian_id');
        $rel = (string) $request->post('relationship', 'father');
        $isPrimary = (bool) $request->post('is_primary', false);
        $isEmergency = (bool) $request->post('is_emergency', true);

        if ($guardianId > 0) {
            $this->repository->linkStudent((int) $studentId, $guardianId, $rel, $isPrimary, $isEmergency);
            Flash::success(__('guardians.link_success'));
        }

        Response::redirect('/students/' . $studentId . '#guardians');
    }

    public function unlinkStudent(Request $request, string $studentId, string $guardianId): void
    {
        $this->repository->unlinkStudent((int) $studentId, (int) $guardianId);
        Flash::success(__('guardians.unlink_success'));
        Response::redirect('/students/' . $studentId . '#guardians');
    }
}

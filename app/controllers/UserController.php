<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Repositories\UserRepository;
use App\Request;
use App\Services\UserService;
use RuntimeException;

/** §Q screen inventory: "Users | List, Add/Edit (Administrator only)" -- entirely gated on users.manage, no separate view permission (§J lists only the one code). */
final class UserController extends Controller
{
    private const ROLE_CODES = ['admin', 'staff'];

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly UserService $service = new UserService(),
    ) {
    }

    public function index(Request $request): void
    {
        $this->view('users/index', ['users' => $this->users->all(), 'roles' => $this->users->allRoles()]);
    }

    public function store(Request $request): void
    {
        $fields = $this->fields($request, requirePassword: true);
        if ($fields['error'] !== null) {
            Flash::set('danger', $fields['error']);
            $this->redirect('/users');
            return;
        }

        try {
            $this->service->create($fields['username'], $fields['password'], $fields['full_name'], $fields['role_code']);
            Flash::set('success', __('users.created'));
        } catch (RuntimeException $e) {
            Flash::set('danger', $this->errorMessage($e->getMessage()));
        }
        $this->redirect('/users');
    }

    public function update(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null || $this->users->find($id) === null) {
            $this->redirect('/users');
            return;
        }

        $fields = $this->fields($request, requirePassword: false);
        if ($fields['error'] !== null) {
            Flash::set('danger', $fields['error']);
            $this->redirect('/users');
            return;
        }

        try {
            $this->service->update($id, $fields['username'], $fields['full_name'], $fields['role_code'], $fields['password']);
            Flash::set('success', __('users.updated'));
        } catch (RuntimeException $e) {
            Flash::set('danger', $this->errorMessage($e->getMessage()));
        }
        $this->redirect('/users');
    }

    public function archive(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null && $this->users->find($id) !== null) {
            try {
                $this->service->archive($id);
                Flash::set('success', __('users.archived'));
            } catch (RuntimeException $e) {
                Flash::set('danger', $this->errorMessage($e->getMessage()));
            }
        }
        $this->redirect('/users');
    }

    public function restore(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null && $this->users->find($id) !== null) {
            $this->service->restore($id);
            Flash::set('success', __('users.restored'));
        }
        $this->redirect('/users');
    }

    /** @return array{username:string, password:string, full_name:string, role_code:string, error:?string} */
    private function fields(Request $request, bool $requirePassword): array
    {
        $username = $request->post('username', '') ?: '';
        $password = $request->post('password', '') ?: '';
        $fullName = $request->post('full_name', '') ?: '';
        $roleCode = $request->post('role_code', '') ?: '';

        $error = null;
        if (!preg_match('/^[a-zA-Z0-9_.\-]{3,50}$/', $username)) {
            $error = __('users.error.username_format');
        } elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150) {
            $error = __('validation.required');
        } elseif (!in_array($roleCode, self::ROLE_CODES, true)) {
            $error = __('users.error.invalid_role');
        } elseif ($requirePassword && $password === '') {
            $error = __('validation.required');
        }

        return ['username' => $username, 'password' => $password, 'full_name' => $fullName, 'role_code' => $roleCode, 'error' => $error];
    }

    private function errorMessage(string $code): string
    {
        return match ($code) {
            'username_taken' => __('users.error.username_taken'),
            'password_too_short' => __('users.error.password_too_short'),
            'invalid_role' => __('users.error.invalid_role'),
            'last_admin' => __('users.error.last_admin'),
            default => __('error.500.title'),
        };
    }
}

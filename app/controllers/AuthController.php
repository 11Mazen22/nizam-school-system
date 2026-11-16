<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\CsrfMiddleware;
use App\Request;
use App\Response;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        Response::noStore();

        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
            return;
        }

        // The login page has no app shell (layout/start.php), so it never
        // otherwise calls Flash::consume() -- a message set right before a
        // forced-logout redirect here (e.g. Phase 8 restore) would
        // otherwise sit unseen until whatever page the user lands on next
        // *after* logging back in. Only the first message is shown: nothing
        // currently sets more than one flash before landing on this page.
        $notice = Flash::consume()[0]['message'] ?? null;
        $username = isset($_SESSION['_recovered_username'])
            ? (string) $_SESSION['_recovered_username']
            : '';
        unset($_SESSION['_recovered_username']);

        $this->view('auth/login', [
            'error' => null,
            'notice' => $notice,
            'username' => $username,
            'recoveredUsername' => $username !== '' ? $username : null,
        ]);
    }

    public function login(Request $request): void
    {
        Response::noStore();

        $username = $request->post('username', '') ?? '';
        $password = $request->post('password', '') ?? '';

        $result = (new AuthService())->attempt($username, $password, $request->ip());

        if ($result['status'] === 'ok') {
            $this->redirect('/dashboard');
            return;
        }

        $error = match ($result['status']) {
            'locked' => __('auth.login.locked', ['minutes' => $this->minutesUntil($result['lockedUntil'] ?? null)]),
            'throttled' => __('auth.login.throttled'),
            default => __('auth.login.invalid'),
        };

        $this->view('auth/login', [
            'error' => $error,
            'notice' => null,
            'username' => $username,
            'recoveredUsername' => null,
        ]);
    }

    public function logout(Request $request): void
    {
        (new AuthService())->logout();
        $this->redirect('/login');
    }

    private function minutesUntil(?string $timestamp): int
    {
        if ($timestamp === null) {
            return 0;
        }
        return max(1, (int) ceil((strtotime($timestamp) - time()) / 60));
    }
}

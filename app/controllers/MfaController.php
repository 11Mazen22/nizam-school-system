<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Repositories\UserRepository;
use App\Request;
use App\Services\MfaService;
use RuntimeException;

/**
 * Nizam -- a staff member's own two-factor settings.
 *
 * Enrolment is opt-in and self-service. The secret is held in the session
 * only while the user proves one code, so abandoning setup changes nothing.
 */
final class MfaController extends Controller
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly MfaService $mfa = new MfaService(),
    ) {
    }

    /** GET /mfa */
    public function index(Request $request): void
    {
        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
            return;
        }

        $setup = $_SESSION['mfa_setup'] ?? null;
        $recoveryCodes = $_SESSION['mfa_recovery_codes'] ?? [];
        unset($_SESSION['mfa_recovery_codes']);

        $this->view('settings/mfa', [
            'user' => $user,
            'enabled' => $this->mfa->enabled($user),
            'setup' => is_array($setup) ? $setup : null,
            'recoveryCodes' => is_array($recoveryCodes) ? $recoveryCodes : [],
        ]);
    }

    /** POST /mfa/setup */
    public function start(Request $request): void
    {
        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
            return;
        }
        if ($this->mfa->enabled($user)) {
            $this->redirect('/mfa');
            return;
        }

        $_SESSION['mfa_setup'] = $this->mfa->startEnrollment((string) $user['username']);
        $this->redirect('/mfa');
    }

    /** POST /mfa/enable */
    public function enable(Request $request): void
    {
        $user = $this->currentUser();
        $setup = $_SESSION['mfa_setup'] ?? null;
        if ($user === null || !is_array($setup) || empty($setup['secret'])) {
            $this->redirect('/mfa');
            return;
        }

        try {
            $codes = $this->mfa->enable(
                (int) $user['id'],
                (string) $setup['secret'],
                (string) ($request->post('code', '') ?? '')
            );
            unset($_SESSION['mfa_setup']);
            $_SESSION['mfa_recovery_codes'] = $codes;
            Flash::set('success', __('mfa.enabled'));
        } catch (RuntimeException) {
            Flash::set('danger', __('mfa.invalid_code'));
        }

        $this->redirect('/mfa');
    }

    /** POST /mfa/disable */
    public function disable(Request $request): void
    {
        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
            return;
        }

        $ok = $this->mfa->disable(
            (int) $user['id'],
            (string) ($request->post('password', '') ?? ''),
            (string) ($request->post('code', '') ?? '')
        );
        if ($ok) {
            unset($_SESSION['mfa_setup'], $_SESSION['mfa_recovery_codes']);
            Flash::set('success', __('mfa.disabled'));
        } else {
            Flash::set('danger', __('mfa.disable_failed'));
        }

        $this->redirect('/mfa');
    }

    private function currentUser(): ?array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        return $this->users->find($userId);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

/**
 * Nizam -- authentication (§C: "password_hash() (bcrypt); session ID
 * regenerated on login"; §O-16's dual throttle via LoginThrottleService).
 *
 * Deliberately returns a generic "invalid" result for both a wrong password
 * AND a username that doesn't exist at all -- the two are indistinguishable
 * to the caller, which is what stops this endpoint being used to enumerate
 * valid usernames.
 */
final class AuthService
{
    /**
     * A precomputed bcrypt hash of a value nobody will ever type, used to
     * keep password_verify()'s cost constant when there is no real user row
     * to check against. Without this, "unknown username" returns almost
     * instantly while "known username, wrong password" takes the ~100ms
     * bcrypt costs -- a measurable timing side-channel that would let an
     * attacker enumerate valid usernames even though both cases return the
     * identical generic message. Found during the Phase 4 audit; the
     * docblock below already claimed this indistinguishability as the
     * design intent, but the implementation didn't fully deliver it.
     */
    private const DUMMY_HASH = '$2y$10$B.SHslrJtargRrLPpLjStOMLxoCa7Dc26ccDsiamW14pCj4L2pBZ.';

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly LoginThrottleService $throttle = new LoginThrottleService(),
    ) {
    }

    /** @return array{status: 'ok'|'invalid'|'locked'|'throttled', lockedUntil?: string} */
    public function attempt(string $username, string $password, string $ip): array
    {
        if ($this->throttle->isIpThrottled($ip)) {
            return ['status' => 'throttled'];
        }

        $user = $this->users->findByUsername($username);

        if ($user === null || (int) $user['is_active'] !== 1) {
            // Burn the same bcrypt cost an existing user's check would take,
            // so this branch and the "wrong password" branch below are not
            // distinguishable by response time -- see DUMMY_HASH's docblock.
            password_verify($password, self::DUMMY_HASH);
            $this->throttle->recordUnknownUsername($username, $ip);
            return ['status' => 'invalid'];
        }

        if ($this->throttle->isAccountLocked($user)) {
            return ['status' => 'locked', 'lockedUntil' => $this->throttle->accountLockedUntil($user)];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->throttle->recordFailure($user, $ip);
            return ['status' => 'invalid'];
        }

        $this->throttle->recordSuccess($user, $ip);
        $this->establishSession($user);

        return ['status' => 'ok'];
    }

    private function establishSession(array $user): void
    {
        // The session-fixation mitigation (§S-15): a new session ID is
        // issued at the exact moment privilege changes, so a session ID an
        // attacker fixed before login is worthless afterward.
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_code'] = $user['role_code'];
        $_SESSION['permissions'] = $this->users->permissionCodesFor((int) $user['id']);
        $_SESSION['locale'] = $_SESSION['locale'] ?? 'ar';

        ActivityLogger::log('login', 'users', (int) $user['id'], "User '{$user['username']}' signed in");
    }

    public function logout(): void
    {
        if (!empty($_SESSION['user_id'])) {
            ActivityLogger::log('logout', 'users', (int) $_SESSION['user_id'], "User '{$_SESSION['username']}' signed out");
        }

        $locale = $_SESSION['locale'] ?? 'ar';
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['locale'] = $locale;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LoginAttemptRepository;
use App\Repositories\UserRepository;

/**
 * Nizam -- login throttling (§O-16): per-(username,IP) exponential-ish
 * lockout via users.failed_attempts/locked_until, PLUS a secondary IP-wide
 * threshold across different usernames so spreading guesses across many
 * accounts from one IP doesn't dodge any single account's lock.
 *
 * §J's settings catalog only defines security.login_max_attempts and
 * security.lockout_minutes -- O-16 named an IP-wide threshold only as an
 * illustrative "e.g. 20," never as its own settings key. Rather than
 * silently extending the approved settings catalog with a new key, this
 * derives the IP-wide threshold from the existing per-account one (a fixed
 * multiplier), staying inside what §J already defines. Flagged here and in
 * the Phase 4 report as the conservative, documented resolution of that gap.
 */
final class LoginThrottleService
{
    private const IP_THRESHOLD_MULTIPLIER = 4;

    public function __construct(
        private readonly LoginAttemptRepository $attempts = new LoginAttemptRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    /** True if this IP alone has too many recent failures, regardless of which usernames were tried. */
    public function isIpThrottled(string $ip): bool
    {
        $perAccountMax = (int) SettingsService::get('security.login_max_attempts', 5);
        $lockoutMinutes = (int) SettingsService::get('security.lockout_minutes', 15);
        $ipMax = $perAccountMax * self::IP_THRESHOLD_MULTIPLIER;

        return $this->attempts->recentFailedCountForIp($ip, $lockoutMinutes) >= $ipMax;
    }

    /** True if this specific account is currently locked out. */
    public function isAccountLocked(array $user): bool
    {
        return !empty($user['locked_until']) && strtotime($user['locked_until']) > time();
    }

    public function accountLockedUntil(array $user): ?string
    {
        return $user['locked_until'] ?? null;
    }

    public function recordFailure(array $user, string $ip): void
    {
        $this->attempts->record($user['username'], $ip, false);

        $perAccountMax = (int) SettingsService::get('security.login_max_attempts', 5);
        $lockoutMinutes = (int) SettingsService::get('security.lockout_minutes', 15);

        $newCount = ((int) $user['failed_attempts']) + 1;
        $lockedUntil = $newCount >= $perAccountMax
            ? date('Y-m-d H:i:s', time() + $lockoutMinutes * 60)
            : null;

        $this->users->recordFailedAttempt((int) $user['id'], $newCount, $lockedUntil);
    }

    public function recordUnknownUsername(string $username, string $ip): void
    {
        // Still recorded against the IP (for the IP-wide threshold) even
        // though there is no account row to update -- deliberately does not
        // reveal to the caller whether the username existed.
        $this->attempts->record($username, $ip, false);
    }

    public function recordSuccess(array $user, string $ip): void
    {
        $this->attempts->record($user['username'], $ip, true);
        $this->users->recordSuccessfulLogin((int) $user['id']);
    }
}

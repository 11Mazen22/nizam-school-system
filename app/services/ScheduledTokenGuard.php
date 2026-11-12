<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Machine-to-machine auth for scheduled endpoints (backup, birthdays,
 * rollover, cleanup).
 *
 * A school that installs from the archive without editing config keeps a
 * token that is published in this repository. Comparing it like a real
 * secret would leave every scheduled endpoint open to anyone who read the
 * source. Placeholder values are therefore treated as "not configured"
 * (401), not as a valid password -- the endpoint stays closed until the
 * school sets its own random token.
 */
final class ScheduledTokenGuard
{
    /** Values shipped as documentation/defaults; never valid credentials. */
    private const PLACEHOLDERS = [
        'CHANGE_THIS_TO_A_RANDOM_SECRET',
        'dev-local-token-change-in-production',
    ];

    public static function isValid(?string $configured, string $provided): bool
    {
        if ($configured === null || $configured === '' || $provided === '') {
            return false;
        }

        if (in_array($configured, self::PLACEHOLDERS, true)) {
            return false;
        }

        return hash_equals($configured, $provided);
    }
}

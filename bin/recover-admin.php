<?php

declare(strict_types=1);

/**
 * Mint a one-time admin recovery token.
 *
 *   php bin/recover-admin.php <username>
 *
 * Prints a token valid for 20 minutes. Enter it with a new password at
 * /setup/recover-admin. The token works once and is deleted on use.
 *
 * Intentionally shell-only: no web route can mint a token, so possession of
 * the token proves access to the server, not just to the login page.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\RecoveryTokenService;

$username = $argv[1] ?? '';
if ($username === '' || $username === '--help' || $username === '-h') {
    fwrite(STDOUT, "Usage: php bin/recover-admin.php <username>\n");
    fwrite(STDOUT, "Creates a single-use recovery token for that admin account.\n");
    exit($username === '' ? 1 : 0);
}

$issued = RecoveryTokenService::issue((string) $username);
if ($issued === null) {
    fwrite(STDERR, "No active user named '{$username}'.\n");
    fwrite(STDERR, "Nothing was changed. Check the username, or run the setup wizard if no admin exists yet.\n");
    exit(1);
}

$minutes = (int) (RecoveryTokenService::TTL_SECONDS / 60);

fwrite(STDOUT, "\n");
fwrite(STDOUT, "Recovery token for {$issued['username']}\n");
fwrite(STDOUT, "  {$issued['token']}\n\n");
fwrite(STDOUT, "Valid for {$minutes} minutes, usable once.\n");
fwrite(STDOUT, "Open /setup/recover-admin, enter the token and the new password.\n\n");

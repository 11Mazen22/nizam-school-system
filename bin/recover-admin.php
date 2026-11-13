<?php

declare(strict_types=1);

/**
 * Mint a one-time admin recovery token.
 *
 *   php bin/recover-admin.php <username>
 *   php bin/recover-admin.php            (lists the accounts you can recover)
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

/**
 * @param list<array{id:int, username:string, full_name:string, role:?string}> $accounts
 */
function listAccounts(array $accounts): void
{
    if ($accounts === []) {
        fwrite(STDOUT, "No active users exist yet.\n");
        fwrite(STDOUT, "Open /setup to create the first admin account.\n");
        return;
    }

    fwrite(STDOUT, "Active accounts:\n");
    foreach ($accounts as $account) {
        $role = $account['role'] === null ? '' : ' (' . $account['role'] . ')';
        fwrite(STDOUT, "  - {$account['username']}{$role}  {$account['full_name']}\n");
    }
    fwrite(STDOUT, "\nRun again with one of those usernames:\n");
    fwrite(STDOUT, "  php bin/recover-admin.php <username>\n");
    fwrite(STDOUT, "\nالحسابات المتاحة أعلاه؛ أعد تنفيذ الأمر باسم المستخدم المطلوب.\n");
}

$username = isset($argv[1]) ? trim((string) $argv[1]) : '';

if ($username === '' || $username === '--list' || $username === '-l') {
    listAccounts(RecoveryTokenService::activeAdmins());
    exit(0);
}

if ($username === '--help' || $username === '-h') {
    fwrite(STDOUT, "Usage: php bin/recover-admin.php <username>\n");
    fwrite(STDOUT, "       php bin/recover-admin.php --list\n");
    exit(0);
}

$issued = RecoveryTokenService::issue($username);
if ($issued === null) {
    fwrite(STDERR, "No active user named '{$username}'.\n\n");

    $accounts = RecoveryTokenService::activeAdmins();
    $closest = [];
    foreach ($accounts as $account) {
        if (strcasecmp($account['username'], $username) === 0) {
            $closest[] = $account['username'];
        }
    }
    if ($closest !== []) {
        fwrite(STDERR, "An account named '{$closest[0]}' exists but is disabled.\n");
        fwrite(STDERR, "Re-enable it first, then run this again.\n");
        exit(1);
    }

    fwrite(STDERR, "Nothing was changed. Use one of these usernames:\n\n");
    listAccounts($accounts);
    exit(1);
}

$minutes = (int) (RecoveryTokenService::TTL_SECONDS / 60);

fwrite(STDOUT, "\n");
fwrite(STDOUT, "Recovery token for {$issued['username']}\n");
fwrite(STDOUT, "  {$issued['token']}\n\n");
fwrite(STDOUT, "Valid for {$minutes} minutes, usable once.\n");
fwrite(STDOUT, "Open /setup/recover-admin, enter the token and the new password.\n");
fwrite(STDOUT, "\nالرمز صالح 20 دقيقة ولمرة واحدة. افتح /setup/recover-admin وأدخله مع كلمة المرور الجديدة.\n\n");

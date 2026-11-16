<?php

declare(strict_types=1);

/**
 * Issue a one-time administrator recovery code from a trusted server shell.
 *
 *   php bin/recover-admin.php                 Interactive account chooser
 *   php bin/recover-admin.php --interactive   Force the guided chooser
 *   php bin/recover-admin.php --list          List recoverable administrators
 *   php bin/recover-admin.php <username>      Select by username
 *   php bin/recover-admin.php --id <user-id>  Select without a username
 *
 * The web page can consume a code but can never issue one. This keeps account
 * discovery and the recovery authority behind physical/server access while
 * still giving an operator a complete path when the username was forgotten.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\RecoveryTokenService;

/** Prevent stored names from emitting terminal control sequences. */
function terminalText(string $value): string
{
    return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));
}

/** @param list<array{id:int, username:string, full_name:string, role:string}> $accounts */
function listAccounts(array $accounts): void
{
    if ($accounts === []) {
        fwrite(STDOUT, "No active administrator accounts exist.\n");
        fwrite(STDOUT, "Open /setup to create the first administrator.\n");
        return;
    }

    fwrite(STDOUT, "Recoverable administrators:\n");
    foreach ($accounts as $index => $account) {
        $number = $index + 1;
        $fullName = terminalText($account['full_name']);
        $username = terminalText($account['username']);
        fwrite(STDOUT, "  [{$number}] {$fullName}\n");
        fwrite(STDOUT, "      Username: {$username}  |  Account ID: {$account['id']}\n");
    }
}

function isInteractiveTerminal(): bool
{
    if (function_exists('stream_isatty')) {
        return stream_isatty(STDIN);
    }
    if (function_exists('posix_isatty')) {
        return posix_isatty(STDIN);
    }
    return false;
}

/** @param list<array{id:int, username:string, full_name:string, role:string}> $accounts */
function chooseInteractively(array $accounts): ?int
{
    listAccounts($accounts);
    if ($accounts === []) {
        return null;
    }

    fwrite(STDOUT, "\nSelect an account number (or press Enter to cancel): ");
    $answer = fgets(STDIN);
    if ($answer === false || trim($answer) === '') {
        return null;
    }

    $index = filter_var(trim($answer), FILTER_VALIDATE_INT);
    if ($index === false || $index < 1 || $index > count($accounts)) {
        fwrite(STDERR, "Invalid selection. Nothing was changed.\n");
        return null;
    }

    return $accounts[$index - 1]['id'];
}

function printHelp(): void
{
    fwrite(STDOUT, "Administrator recovery\n\n");
    fwrite(STDOUT, "Usage:\n");
    fwrite(STDOUT, "  php bin/recover-admin.php                 Choose an account interactively\n");
    fwrite(STDOUT, "  php bin/recover-admin.php --interactive   Force the guided chooser\n");
    fwrite(STDOUT, "  php bin/recover-admin.php --list          List administrator accounts\n");
    fwrite(STDOUT, "  php bin/recover-admin.php <username>      Select by username\n");
    fwrite(STDOUT, "  php bin/recover-admin.php --id <user-id>  Select by account ID\n");
}

try {
    $args = array_values(array_slice($argv ?? [], 1));
    $first = $args[0] ?? '';

    if ($first === '--help' || $first === '-h') {
        printHelp();
        exit(0);
    }

    $accounts = RecoveryTokenService::activeAdmins();

    if ($first === '--list' || $first === '-l') {
        listAccounts($accounts);
        exit($accounts === [] ? 1 : 0);
    }

    $issued = null;
    if ($first === '--id') {
        $id = filter_var($args[1] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            fwrite(STDERR, "--id requires the numeric Account ID shown by --list.\n");
            exit(1);
        }
        $issued = RecoveryTokenService::issueForUserId((int) $id);
    } elseif ($first === '--interactive') {
        $selectedId = chooseInteractively($accounts);
        if ($selectedId === null) {
            fwrite(STDOUT, "\nCancelled. Nothing was changed.\n");
            exit(0);
        }
        $issued = RecoveryTokenService::issueForUserId($selectedId);
    } elseif ($first !== '') {
        $issued = RecoveryTokenService::issue($first);
    } elseif (isInteractiveTerminal()) {
        $selectedId = chooseInteractively($accounts);
        if ($selectedId === null) {
            fwrite(STDOUT, "\nCancelled. Nothing was changed.\n");
            exit(0);
        }
        $issued = RecoveryTokenService::issueForUserId($selectedId);
    } else {
        // Pipes/automation must never unexpectedly mint a credential. Listing
        // still solves a forgotten username and gives an unambiguous next step.
        listAccounts($accounts);
        if ($accounts !== []) {
            fwrite(STDOUT, "\nRun interactively and choose a number, or use:\n");
            fwrite(STDOUT, "  php bin/recover-admin.php --id <Account ID>\n");
        }
        exit($accounts === [] ? 1 : 0);
    }

    if ($issued === null) {
        fwrite(STDERR, "That account is not an active administrator. Nothing was changed.\n\n");
        listAccounts($accounts);
        exit(1);
    }

    $minutes = (int) (RecoveryTokenService::TTL_SECONDS / 60);

    fwrite(STDOUT, "\n============================================================\n");
    fwrite(STDOUT, "Administrator access recovery\n");
    fwrite(STDOUT, "============================================================\n");
    fwrite(STDOUT, 'Account:  ' . terminalText($issued['full_name']) . "\n");
    fwrite(STDOUT, 'Username: ' . terminalText($issued['username']) . "\n");
    fwrite(STDOUT, "Code:     {$issued['token']}\n");
    fwrite(STDOUT, "\nValid for {$minutes} minutes and usable once.\n");
    fwrite(STDOUT, "Open /setup/recover-admin, paste the code, and set a new password.\n");
    fwrite(STDOUT, "The sign-in page will remember and show the username after reset.\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Recovery could not start. Confirm the database is running and all migrations are applied.\n");
    fwrite(STDERR, "Technical detail: {$e->getMessage()}\n");
    exit(1);
}

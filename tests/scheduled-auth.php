<?php

declare(strict_types=1);

/**
 * Regression coverage for the machine-to-machine endpoints.
 *
 * Found live: App\Request::header() did not exist, so every /scheduled/*
 * route crashed with a 500 on its first line -- meaning birthday reminders,
 * year rollover and cleanup silently never ran on a deployed school server.
 * Separately, the placeholder tokens published in this repository were being
 * compared as if they were real secrets.
 *
 * Run: php tests/scheduled-auth.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Request;
use App\Services\ScheduledTokenGuard;

$failures = 0;

function check(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        echo "  ok    {$label}\n";
        return;
    }
    $failures++;
    echo "  FAIL  {$label}\n";
}

echo "Scheduled endpoint auth\n";

check(!ScheduledTokenGuard::isValid('dev-local-token-change-in-production', 'dev-local-token-change-in-production'), 'Repo default token is rejected');
check(!ScheduledTokenGuard::isValid('CHANGE_THIS_TO_A_RANDOM_SECRET', 'CHANGE_THIS_TO_A_RANDOM_SECRET'), 'Example placeholder token is rejected');
check(!ScheduledTokenGuard::isValid('', ''), 'Empty token is rejected');
check(!ScheduledTokenGuard::isValid(null, 'anything'), 'Unconfigured token is rejected');
check(ScheduledTokenGuard::isValid('a-real-random-secret', 'a-real-random-secret'), 'A configured secret authenticates');
check(!ScheduledTokenGuard::isValid('a-real-random-secret', 'wrong'), 'A wrong secret is rejected');

echo "\nRequest::header()\n";

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/scheduled/birthdays';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-token';
$_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom-value';
$_SERVER['CONTENT_TYPE'] = 'application/json';

$request = new Request();
check($request->header('Authorization') === 'Bearer test-token', 'Authorization header is readable');
check($request->header('X-Custom-Header') === 'custom-value', 'Hyphenated custom header is normalised');
check($request->header('Content-Type') === 'application/json', 'Content-Type is read from its server var');
check($request->header('X-Missing') === null, 'Absent header returns null');

echo $failures === 0 ? "\nPASS: scheduled auth\n" : "\n{$failures} CHECK(S) FAILED\n";
exit($failures === 0 ? 0 : 1);

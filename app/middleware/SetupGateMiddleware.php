<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Request;
use App\Response;
use App\Services\SetupStatusService;

/**
 * Nizam -- Setup Wizard gate, applied to EVERY route (§O-6 hardened):
 * while setup is incomplete, nothing except /setup/* is reachable; once
 * setup is complete, /setup/* itself becomes permanently unreachable, for
 * anyone, authenticated or not -- "no exceptions, no in-app redo-setup
 * button." A single middleware covers both directions rather than a
 * scattered check per route, matching this phase's "do not duplicate
 * existing mechanisms" rule applied to this one too.
 */
final class SetupGateMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        $completed = (new SetupStatusService())->isCompleted();
        $isSetupPath = $request->path() === '/setup' || str_starts_with($request->path(), '/setup/');

        if ($completed && $isSetupPath) {
            Response::redirect('/login');
            return false;
        }

        if (!$completed && !$isSetupPath && $request->path() !== '/lang') {
            Response::redirect('/setup');
            return false;
        }

        return true;
    }
}

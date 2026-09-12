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
 * 
 * Exception: /setup/recover-admin is allowed through regardless of
 * setup_completed flag — it gates itself on live ground truth (zero users
 * in database), not the flag. This narrow escape hatch handles production
 * deployments where setup_completed was manually set but no admin was
 * actually created.
 */
final class SetupGateMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        $path = $request->path();
        
        // Emergency admin recovery bypasses the setup gate entirely
        if ($path === '/setup/recover-admin') {
            return true;
        }
        
        $completed = (new SetupStatusService())->isCompleted();
        $isSetupPath = $path === '/setup' || str_starts_with($path, '/setup/');

        if ($completed && $isSetupPath) {
            Response::redirect('/login');
            return false;
        }

        if (!$completed && !$isSetupPath && $path !== '/lang') {
            Response::redirect('/setup');
            return false;
        }

        return true;
    }
}

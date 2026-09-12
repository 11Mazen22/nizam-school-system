<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Request;
use App\Response;
use App\Services\SetupStatusService;

/**
 * Nizam -- Setup Wizard gate (DISABLED for Hadaba Al-Ahram deployment).
 * 
 * This deployment is hardcoded for "هضبة الأهرام الثانوية / Hadaba Al-Ahram 
 * Language School" with no setup wizard. School data is pre-populated in
 * database migrations. Setup routes are blocked entirely - users go 
 * straight to /login.
 * 
 * Exception: /setup/recover-admin still works for emergency admin recovery.
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

        // Hadaba Al-Ahram: If database not ready, allow setup wizard to run migrations
        // Once complete, block setup routes and redirect to login
        if (!$completed && !$isSetupPath && $path !== '/lang') {
            Response::redirect('/setup');
            return false;
        }

        // Setup complete: block setup wizard, redirect to login
        if ($completed && $isSetupPath && $path !== '/setup/recover-admin') {
            Response::redirect('/login');
            return false;
        }

        return true;
    }
}

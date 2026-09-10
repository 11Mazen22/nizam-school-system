<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Request;

/**
 * Nizam -- middleware contract (§C: "Middleware chain: Session, CSRF,
 * RoleGuard, AcademicYearContext"). handle() returns true to let the pipeline
 * continue to the next middleware/controller, or false if it already sent a
 * complete response itself (a redirect, a 403 page) and the pipeline must
 * stop there -- fail-closed by construction: a middleware that returns
 * false is the ONLY way execution is short-circuited, so there is no path
 * that silently proceeds to the controller.
 */
interface MiddlewareInterface
{
    public function handle(Request $request): bool;
}

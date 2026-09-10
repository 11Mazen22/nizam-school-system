<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repositories\AcademicYearRepository;
use App\Request;

/**
 * Nizam -- academic-year context (§C: "Middleware resolves the active year
 * once per request; write operations against a closed year are rejected at
 * the service layer, not just hidden in the UI").
 *
 * This middleware's whole job is the "resolve once per request" half --
 * later phases' services read AcademicYearContext::activeYearId() /
 * isClosed() rather than each querying academic_years themselves. The
 * write-rejection half is intentionally NOT here: no Phase 4 route performs
 * a year-scoped write (those all belong to Phase 6+ modules), so there is
 * nothing yet that needs guarding, and building that guard now, ahead of
 * anything it protects, would be exactly the "placeholder for a future
 * phase" this phase's rules warn against.
 *
 * Never fails the request: a fresh install with no academic year yet (before
 * the Setup Wizard has run) is a completely valid state, not an error.
 *
 * The holder it populates, AcademicYearContext, lives in its own file
 * (AcademicYearContext.php) -- see that file's docblock for why.
 */
final class AcademicYearContextMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        $year = (new AcademicYearRepository())->findActive();
        AcademicYearContext::set($year);
        return true;
    }
}

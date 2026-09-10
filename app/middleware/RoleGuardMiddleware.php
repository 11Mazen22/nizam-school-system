<?php

declare(strict_types=1);

namespace App\Middleware;

use App\ErrorHandler;
use App\Request;
use App\Response;

/**
 * Nizam -- authorization (§C: "RoleGuard middleware checks the requesting
 * user's permission code against the route before the controller runs";
 * §Q: "unauthorized actions fail closed," "server-side authorization cannot
 * be bypassed by manipulating the UI/request").
 *
 * A route declares what it needs by attaching one of this class's two
 * factory instances -- requiresAuth() for "must be logged in, any role," or
 * requiresPermission($code) for "must be logged in AND hold this permission
 * code." Fail-closed by construction: any code path that isn't an explicit,
 * checked "yes" returns false and stops the pipeline (§I.19-equivalent
 * discipline carried from the data layer into the request layer).
 *
 * Permission codes are read from $_SESSION['permissions'], populated once at
 * login (AuthService) by joining role_permissions -- not re-queried on every
 * request. A permission a role never had cannot appear there no matter what
 * the client sends, which is what makes this unbypassable by manipulating
 * the request: nothing about the check reads anything the client controls.
 */
final class RoleGuardMiddleware implements MiddlewareInterface
{
    private ?string $requiredPermission;

    private function __construct(?string $requiredPermission)
    {
        $this->requiredPermission = $requiredPermission;
    }

    public static function requiresAuth(): self
    {
        return new self(null);
    }

    public static function requiresPermission(string $code): self
    {
        return new self($code);
    }

    public function handle(Request $request): bool
    {
        if (empty($_SESSION['user_id'])) {
            Response::redirect('/login');
            return false;
        }

        if ($this->requiredPermission === null) {
            return true;
        }

        $granted = $_SESSION['permissions'] ?? [];
        if (!in_array($this->requiredPermission, $granted, true)) {
            ErrorHandler::renderForbidden();
            return false;
        }

        return true;
    }
}

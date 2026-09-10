<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * Nizam -- request-scoped holder for the active academic year, populated
 * once per request by AcademicYearContextMiddleware. Split into its own
 * file during the Phase 4 audit: it originally lived inside
 * AcademicYearContextMiddleware.php alongside the middleware class, which
 * broke the one-class-per-file assumption the autoloader (app/bootstrap.php)
 * relies on -- referencing App\Middleware\AcademicYearContext before the
 * middleware class happened to be autoloaded first would have thrown a
 * "class not found" error. It worked in every test run so far only because
 * public/index.php always instantiates the middleware first.
 */
final class AcademicYearContext
{
    private static ?array $active = null;

    public static function set(?array $year): void
    {
        self::$active = $year;
    }

    public static function activeYearId(): ?int
    {
        return self::$active['id'] ?? null;
    }

    public static function isClosed(): bool
    {
        return (bool) (self::$active['is_closed'] ?? false);
    }

    public static function label(): ?string
    {
        return self::$active['label'] ?? null;
    }
}

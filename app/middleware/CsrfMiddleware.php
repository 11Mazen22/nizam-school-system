<?php

declare(strict_types=1);

namespace App\Middleware;

use App\ErrorHandler;
use App\Request;

/**
 * Nizam -- CSRF protection (§C: "One token per session, embedded in every
 * form, verified in middleware on every POST/PUT/DELETE using hash_equals()"
 * -- §O-19: a plain == comparison is vulnerable to a timing side-channel).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const FIELD = '_csrf_token';

    public function handle(Request $request): bool
    {
        if (empty($_SESSION[self::FIELD])) {
            $_SESSION[self::FIELD] = bin2hex(random_bytes(32));
        }

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            // POST body only, never the query string -- a CSRF token must
            // never be acceptable via a URL, where it would leak through
            // browser history, Referer headers, and server access logs.
            $submitted = $request->post(self::FIELD, '');
            $expected = $_SESSION[self::FIELD];
            if ($submitted === null || $submitted === '' || !hash_equals($expected, $submitted)) {
                ErrorHandler::renderExpired(); // distinct from a generic 403, matches §Q's error catalog message
                return false;
            }
        }

        return true;
    }

    /** The current session's token -- views call this to render the hidden field. */
    public static function token(): string
    {
        if (empty($_SESSION[self::FIELD])) {
            $_SESSION[self::FIELD] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::FIELD];
    }

    /** Ready-to-echo hidden input -- e() applied even though a random hex token needs no escaping, for consistency with §S-13's "always e(), no exceptions." */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . e(self::token()) . '">';
    }
}

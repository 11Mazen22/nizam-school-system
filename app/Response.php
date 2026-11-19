<?php

declare(strict_types=1);

namespace App;

/**
 * Nizam -- response helpers: redirect and view rendering. Views are plain
 * PHP templates (§C: "views/, PHP templates") -- no template engine, nothing
 * to add as a dependency for something require/include already does.
 */
final class Response
{
    public static function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302);
        exit;
    }

    /** @param array<string,mixed> $data */
    public static function view(string $name, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require dirname(__DIR__) . "/views/{$name}.php";
    }

    /**
     * Phase 7: streams a generated file (PDF/Excel report export) as an
     * attachment download. $filename is always built by the controller from
     * a known report key + timestamp (never a client-supplied value), so
     * there's no path/injection concern to sanitize against here.
     */
    public static function download(string $bytes, string $filename, string $contentType): void
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
    }

    /**
     * Streams a generated document.
     *
     * Sends both the legacy ASCII filename and the RFC 5987 UTF-8 form, so a
     * document whose natural name is Arabic (or whose export is an Arabic
     * file inside a ZIP) arrives with a readable name on every browser
     * instead of a percent-encoded or transliterated one.
     */
    public static function downloadFile(string $bytes, string $filename, string $contentType, bool $inline = false): void
    {
        $fallback = preg_replace('/[^A-Za-z0-9 ._\-]/', '_', $filename) ?: 'document';

        header('Content-Type: ' . $contentType);
        header(
            'Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
            . '; filename="' . $fallback . '"'
            . "; filename*=UTF-8''" . rawurlencode($filename)
        );
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
    }

    /**
     * Streams a file for inline display (an <img> tag's src, not a save-file
     * dialog) with an explicitly-set Content-Type (decision #11 / §O-18) --
     * used for student/teacher photos and the school logo. Short private
     * caching only: these are per-installation images, not shared across
     * schools, and may reasonably be treated as sensitive.
     */
    public static function inlineFile(string $bytes, string $contentType): void
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: inline');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, max-age=3600');
        echo $bytes;
    }

    /**
     * Authentication, setup, and recovery pages contain live CSRF/session
     * state and must never be replayed by a browser cache or service worker.
     */
    public static function noStore(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    public static function securityHeaders(): void
    {
        // §Q "Security must be implemented centrally" -- set once, here, not
        // scattered per-controller. Conservative set appropriate to an
        // offline LAN app (no CDN/external-origin content ever loaded, so a
        // strict default-src is free). style-src allows 'unsafe-inline'
        // specifically: every Phase 4 view uses inline <style>/style=""
        // (no external CSS exists yet -- that's Phase 5's vendored-asset
        // work), and under plain default-src 'self' a spec-compliant browser
        // blocks those entirely, silently leaving every page unstyled. No
        // user-controlled data is ever written into an inline style, so
        // relaxing style-src alone doesn't reopen the injection risk
        // default-src is there to prevent -- script-src stays at 'self'
        // with no relaxation, and nothing here uses an inline <script>.
        //
        // img-src 'self' data: -- found live in Phase 5 browser testing:
        // Bootstrap's own vendored CSS (btn-close, form-check, carets) draws
        // its icons as inline data:image/svg+xml background-images, not
        // files. Nothing in default-src 'self' admits a data: URI, so every
        // one of those icons was silently invisible. This is not a network
        // relaxation -- a data: URI is bytes already inside the CSS file,
        // never a fetch to anywhere -- so it doesn't reopen the "nothing
        // external, ever" guarantee the rest of this policy protects.
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=()');

        // HSTS only when this request really arrived through HTTPS. The
        // online container sees TLS terminated at Railway's edge, hence the
        // forwarded-proto check; direct HTTP/XAMPP must never receive HSTS.
        $isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strcasecmp(($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 'https') === 0;
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }
}

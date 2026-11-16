<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Request;
use App\Services\SettingsService;
use PDOException;

/**
 * Nizam -- session bootstrap and idle-timeout enforcement.
 *
 * §C: "session ID regenerated on login" (session-fixation mitigation --
 * §S-15 names this explicitly; the regeneration itself happens in
 * AuthService at the moment of login, not here, since it must happen exactly
 * at the privilege-change boundary). This middleware's job is the cookie
 * configuration and the idle-timeout check every request needs.
 *
 * Cookie flags: HttpOnly and SameSite=Lax always; Secure only when the
 * request actually arrived over HTTPS. §O-31 already documented why Secure
 * can't be forced unconditionally -- a LAN-only XAMPP install is typically
 * plain HTTP, and a cookie marked Secure over HTTP is simply never sent,
 * silently breaking every session. Detecting HTTPS here rather than
 * hardcoding either way is what makes both deployment modes work correctly.
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Behind Railway's edge (and any normal reverse proxy) TLS is
            // terminated upstream: the container itself only ever sees plain
            // HTTP, so $_SERVER['HTTPS'] is empty and SERVER_PORT is the
            // container's -- neither can ever say "https" there. The proxy
            // tells the truth in X-Forwarded-Proto instead; trusting it
            // marks the session cookie Secure on the online deployment
            // while leaving every direct-HTTP deployment (LAN XAMPP) exactly
            // as it was. A client forging that header on plain HTTP only
            // gets a Secure cookie its own browser will then never send
            // over HTTP -- harmless in the wrong direction.
            $isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? '') === '443'
                || strcasecmp(($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 'https') === 0;

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            ini_set('session.use_strict_mode', '1');
            // The Railway container's own /var/lib/php/sessions directory is
            // ephemeral. dataPath() is mounted on the persistent volume in
            // production, so sessions survive an ordinary restart/redeploy
            // instead of signing every member of staff out at once. Offline
            // installs get the same directory under their existing mutable
            // data root; no change to their deployment model.
            $sessionDir = dataPath() . '/storage/sessions';
            if ((!is_dir($sessionDir) && @mkdir($sessionDir, 0700, true)) || is_dir($sessionDir)) {
                session_save_path($sessionDir);
            }
            // Lazy write: a request that did not actually change the session
            // does not rewrite the session file at all. Combined with the
            // activity-stamp granularity below, this removes the lost-update
            // window in which a slow, parallel request could write back a
            // stale copy of the session and wipe a just-established login.
            ini_set('session.lazy_write', '1');
            session_name('nizam_session');
            session_start();
        }

        $this->enforceIdleTimeout();

        return true;
    }

    /**
     * Minimum seconds between two activity-stamp writes.
     *
     * The idle timeout only needs minute-level resolution, but the stamp
     * used to be rewritten on EVERY request. With file-backed sessions that
     * means dozens of parallel requests (page, fonts, notifications poll)
     * each rewrote the same session file, and a rewrite carrying a stale
     * snapshot could land after the login write and strip the authenticated
     * keys -- the user was then bounced back to the login page even though
     * the sign-in had succeeded. Writing the stamp at most once every 30
     * seconds keeps the timeout exact to the minute while making ordinary
     * requests no-ops that lazy_write simply skips.
     */
    private const ACTIVITY_STAMP_INTERVAL_SECONDS = 30;

    private function enforceIdleTimeout(): void
    {
        // Phase 5 found this the hard way (live-testing the Setup Wizard on
        // a genuinely fresh install, not just via code review): during the
        // wizard's database/schema steps, config/config.php may not exist
        // yet, or may not point at a reachable server yet. SettingsService
        // would throw, and since SessionMiddleware runs on every route
        // including /setup itself, that turned "load the wizard's first
        // page" into an uncaught 500 -- a genuine deadlock, not a corner
        // case. A session timeout is meaningless anyway before the database
        // (and therefore any real session) exists, so fall back to the
        // hardcoded default rather than let this be fatal.
        try {
            $timeoutMinutes = (int) SettingsService::get('security.session_timeout_minutes', 60);
        } catch (PDOException $e) {
            $timeoutMinutes = 60;
        }
        $now = time();
        $hasStamp = isset($_SESSION['_last_activity']);
        $lastActivity = $hasStamp ? (int) $_SESSION['_last_activity'] : $now;

        if (($now - $lastActivity) > ($timeoutMinutes * 60)) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            session_start();
            $hasStamp = false;
            $lastActivity = $now;
        }

        // A brand-new session always gets its first stamp (otherwise the
        // idle timeout could never start counting); after that the stamp is
        // refreshed at most once every 30 seconds.
        if (!$hasStamp || ($now - $lastActivity) >= self::ACTIVITY_STAMP_INTERVAL_SECONDS) {
            $_SESSION['_last_activity'] = $now;
        }
    }
}

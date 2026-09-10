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
            $isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? '') === '443';

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            ini_set('session.use_strict_mode', '1');
            session_name('nizam_session');
            session_start();
        }

        $this->enforceIdleTimeout();

        return true;
    }

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
        $lastActivity = $_SESSION['_last_activity'] ?? $now;

        if (($now - $lastActivity) > ($timeoutMinutes * 60)) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            session_start();
        }

        $_SESSION['_last_activity'] = $now;
    }
}

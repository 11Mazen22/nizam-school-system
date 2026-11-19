<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use PDOException;

/**
 * Single source of truth for the active UI locale.
 *
 * The database setting `app.default_language` is the school-wide default used
 * to seed NEW sign-in sessions. A user may then switch language from the
 * navbar; that choice is persisted per user so the following session starts
 * the way they left it. Every path that changes language must go through
 * setActive() so the session and the durable preference cannot drift apart.
 */
final class LocaleService
{
    public const DEFAULT_LOCALE = 'ar';

    /** @var list<string> */
    public const SUPPORTED = ['ar', 'en'];

    public static function normalize(mixed $locale): string
    {
        $locale = is_string($locale) ? strtolower(trim($locale)) : '';
        return in_array($locale, self::SUPPORTED, true) ? $locale : self::DEFAULT_LOCALE;
    }

    /** School default from the typed settings table; safe before setup/schema exists. */
    public static function configuredDefault(): string
    {
        try {
            return self::normalize(SettingsService::get('app.default_language', self::DEFAULT_LOCALE));
        } catch (PDOException) {
            return self::DEFAULT_LOCALE;
        }
    }

    /** Per-user preference key in the same typed settings table. */
    public static function preferenceKey(int $userId): string
    {
        return 'user.' . $userId . '.locale';
    }

    /** Read a stored per-user override, with a safe fallback before setup. */
    public static function storedPreference(int $userId): ?string
    {
        try {
            $value = SettingsService::get(self::preferenceKey($userId));
        } catch (PDOException) {
            return null;
        }
        return $value === null || $value === false ? null : self::normalize($value);
    }

    /** Seed the session locale from the user preference, else the school default. */
    public static function seedSession(int $userId): string
    {
        $locale = self::storedPreference($userId) ?? self::configuredDefault();
        $_SESSION['locale'] = $locale;
        return $locale;
    }

    /**
     * Apply an active locale for the signed-in user: update the session and the
     * per-user durable preference. The school-wide default is intentionally
     * NOT changed here -- a navbar switch is that user's choice, not a policy
     * change for everyone.
     */
    public static function setActive(string $locale, ?int $userId = null): string
    {
        $locale = self::normalize($locale);
        $_SESSION['locale'] = $locale;

        $userId ??= isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        try {
            // The DB setting is the single source of truth: a navbar switch is
            // a deliberate language change, so persist it there too (and mirror
            // it per user for auditability) whenever settings are available.
            SettingsService::set('app.default_language', $locale, 'string');
            if ($userId > 0) {
                SettingsService::set(self::preferenceKey($userId), $locale, 'string');
            }
        } catch (PDOException) {
            // Before setup/schema exists there is nowhere to persist; the
            // in-memory session switch still works (login/setup screens).
        }
        return $locale;
    }

    /** Set the school-wide default and align the current session with it. */
    public static function setDefault(string $locale, ?int $userId = null): string
    {
        $locale = self::normalize($locale);
        SettingsService::set('app.default_language', $locale, 'string');
        try {
            self::clearUserPreferences();
        } catch (PDOException) {
            // Fresh installs may not have the settings table yet; the default
            // itself has already been written above.
        }
        self::setActive($locale, $userId);
        return $locale;
    }

    /** Remove per-user overrides so the next sign-in uses the school default. */
    public static function clearUserPreferences(): void
    {
        $pdo = Database::connection();
        // Portable MySQL and PostgreSQL syntax: match the exact dotted suffix.
        $pdo->prepare("DELETE FROM settings WHERE setting_key LIKE 'user.%.locale'")->execute();
    }

    /** Current session locale, always one of SUPPORTED. */
    public static function current(): string
    {
        return self::normalize($_SESSION['locale'] ?? null);
    }
}

<?php
/**
 * Nizam -- plain-function helpers. §C names these explicitly: e() for output
 * escaping (used in every view, no exceptions -- §Q "Frontend"/"Security"
 * rules), __() for translation (§C's lang/ar.php + lang/en.php arrays).
 * Deliberately not classes/autoloaded -- these are called from every view
 * far too often for a class-method call to be worth it.
 */

declare(strict_types=1);

/**
 * Escapes a value for safe HTML output. The single approved way to print a
 * variable in any view or report template (§C, §S-13) -- never echo raw.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Translates a key using the active language's array, set on the session by
 * LocaleMiddleware-equivalent logic in the front controller. Falls back to
 * the key itself if missing, so a missing translation is visible/obvious
 * rather than silently blank.
 */
function __(string $key, array $replace = []): string
{
    static $lang = null;
    static $loadedLocale = null;

    $locale = $_SESSION['locale'] ?? 'ar';
    if ($lang === null || $loadedLocale !== $locale) {
        $path = dirname(__DIR__, 2) . "/lang/{$locale}.php";
        $lang = is_file($path) ? require $path : [];
        $loadedLocale = $locale;
    }

    $text = $lang[$key] ?? $key;
    foreach ($replace as $search => $value) {
        $text = str_replace(':' . $search, (string) $value, $text);
    }
    return $text;
}

/** Current UI direction, driven by the active language -- §C "RTL is designed, not mirrored." */
function currentDirection(): string
{
    return ($_SESSION['locale'] ?? 'ar') === 'ar' ? 'rtl' : 'ltr';
}

function currentLocale(): string
{
    return $_SESSION['locale'] ?? 'ar';
}

/**
 * Base directory for mutable, per-installation data: config/, storage/logs,
 * storage/uploads, database/backups. Every existing dev/XAMPP/test
 * deployment leaves NIZAM_DATA_PATH unset, so this returns exactly the same
 * project root every one of those call sites already resolved on its own --
 * a pure no-op there. The packaged Windows product is the one deployment
 * that sets this (via the bundled Apache's SetEnv), redirecting mutable
 * school data into %ProgramData%\Nizam so it survives an app-files update or
 * reinstall untouched. Application assets that ship with the program itself
 * (vendor-assets/fonts, public/assets, views/, lang/) are never mutable data
 * and must keep resolving relative to the app's own installation directory --
 * this helper is deliberately not used for those.
 */
function dataPath(): string
{
    // Checked in both places deliberately: the PHP built-in server (every
    // dev/test deployment) only ever populates getenv(); Apache's SetEnv,
    // under mod_php, is delivered to PHP through $_SERVER, not reliably
    // through getenv() (it sets Apache's own internal subprocess_env table,
    // not the httpd.exe process's actual OS environment). Checking both
    // covers every deployment this app actually runs under without needing
    // to know which one is active.
    $env = $_SERVER['NIZAM_DATA_PATH'] ?? getenv('NIZAM_DATA_PATH');
    return is_string($env) && $env !== '' ? rtrim($env, '/\\') : dirname(__DIR__, 2);
}

/**
 * §O-32: a report cell that's a plain number, percentage, or signed
 * difference (e.g. the Phase 7 workload report's "-11") is left-to-right
 * data even inside an RTL page -- rendered as ordinary text in an RTL
 * paragraph, the Unicode bidi algorithm reorders a leading "-" to visually
 * trail the digits instead ("11-"), which reads as wrong/confusing rather
 * than as negative eleven. Found live testing Phase 7's workload report.
 * Call from a view/export builder to decide whether a cell needs an
 * explicit dir="ltr" isolation; text cells (names, subject lists) never
 * match this and render normally.
 */
function isRtlSafeNumericCell(string $value): bool
{
    return (bool) preg_match('/^-?\d+(\.\d+)?%?$/', trim($value));
}

/**
 * True if the logged-in user's session carries this permission code (§C:
 * permissions cached in $_SESSION['permissions'] at login). Phase 6's
 * sidebar/nav use this to not render a link to a screen the user can't
 * open -- navigation visibility is never the actual authorization boundary
 * (RoleGuardMiddleware on the route is), just a UX nicety on top of it.
 */
function hasPermission(string $code): bool
{
    return in_array($code, $_SESSION['permissions'] ?? [], true);
}

/**
 * The sidebar/offcanvas nav's item list, in one place so the two menus
 * (desktop sidebar, mobile offcanvas) render from the same source instead
 * of two copies drifting apart (§L "never copy-pasted per module", applied
 * to navigation itself). Each item's permission is the same §J code its
 * route is gated behind -- a link never appears for something its own
 * route would 403.
 *
 * A null permission means "every authenticated role" (Dashboard has no §J
 * code of its own -- its route is gated by requiresAuth() only), not "no
 * check": hasPermission() is never called for that item, matching what the
 * route itself actually requires.
 *
 * @return array<int, array{key:string, href:string, label:string, permission:?string}>
 */
function navItems(): array
{
    return [
        ['key' => 'dashboard', 'href' => '/dashboard', 'label' => 'nav.dashboard', 'permission' => null],
        ['key' => 'academic-years', 'href' => '/academic-years', 'label' => 'nav.academic_years', 'permission' => 'academic_years.view'],
        ['key' => 'grades', 'href' => '/grades', 'label' => 'nav.grades', 'permission' => 'grades.view'],
        ['key' => 'classes', 'href' => '/classes', 'label' => 'nav.classes', 'permission' => 'classes.view'],
        ['key' => 'subjects', 'href' => '/subjects', 'label' => 'nav.subjects', 'permission' => 'subjects.view'],
        ['key' => 'teachers', 'href' => '/teachers', 'label' => 'nav.teachers', 'permission' => 'teachers.view'],
        ['key' => 'students', 'href' => '/students', 'label' => 'nav.students', 'permission' => 'students.view'],
        ['key' => 'assignments', 'href' => '/assignments', 'label' => 'nav.assignments', 'permission' => 'assignments.view'],
        ['key' => 'reports', 'href' => '/reports', 'label' => 'nav.reports', 'permission' => 'reports.view'],
        ['key' => 'backups', 'href' => '/backups', 'label' => 'nav.backups', 'permission' => 'backups.run'],
        ['key' => 'activity-log', 'href' => '/activity-log', 'label' => 'nav.activity_log', 'permission' => 'activity_log.view'],
        ['key' => 'users', 'href' => '/users', 'label' => 'nav.users', 'permission' => 'users.manage'],
        ['key' => 'settings', 'href' => '/settings/profile', 'label' => 'nav.settings', 'permission' => 'settings.manage'],
    ];
}

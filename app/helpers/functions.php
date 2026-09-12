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
 * Translated label for an activity-log action key (e.g. 'backup.created').
 * Found live: activity_logs.description is a free-text string baked in
 * English at the moment each ActivityLogger::log() call happens (§C's own
 * convention -- an audit trail row is a historical fact, not something
 * re-translated after it happened, so the raw column is deliberately left
 * as-is rather than restructured). The gap was purely on the DISPLAY side:
 * both the dashboard and the Activity Log screen printed that raw
 * English description directly, so an Arabic-locale session saw untranslated
 * English text where a proper label belongs. This maps the action KEY
 * (always a stable, non-free-text identifier) to a translated label via
 * lang/{locale}.php, exactly like every other UI string -- the stored
 * description, when present, is still shown too, as a secondary supporting
 * detail (a filename, a byte count, an entity name), never as the primary
 * label. Falls back to the raw key (matching __()'s own convention) for any
 * action not in the map, so a future action type not yet translated is
 * still visible rather than silently blank.
 */
function activityLabel(string $action): string
{
    $key = 'activity.action.' . $action;
    $translated = __($key);
    return $translated !== $key ? $translated : $action;
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
        ['key' => 'dashboard', 'href' => '/dashboard', 'label' => 'nav.dashboard', 'permission' => null, 'icon' => 'dashboard'],
        ['key' => 'academic-years', 'href' => '/academic-years', 'label' => 'nav.academic_years', 'permission' => 'academic_years.view', 'icon' => 'calendar'],
        ['key' => 'grades', 'href' => '/grades', 'label' => 'nav.grades', 'permission' => 'grades.view', 'icon' => 'layers'],
        ['key' => 'classes', 'href' => '/classes', 'label' => 'nav.classes', 'permission' => 'classes.view', 'icon' => 'chalkboard'],
        ['key' => 'subjects', 'href' => '/subjects', 'label' => 'nav.subjects', 'permission' => 'subjects.view', 'icon' => 'book'],
        ['key' => 'teachers', 'href' => '/teachers', 'label' => 'nav.teachers', 'permission' => 'teachers.view', 'icon' => 'graduation-cap'],
        ['key' => 'students', 'href' => '/students', 'label' => 'nav.students', 'permission' => 'students.view', 'icon' => 'users'],
        ['key' => 'assignments', 'href' => '/assignments', 'label' => 'nav.assignments', 'permission' => 'assignments.view', 'icon' => 'clipboard-list'],
        ['key' => 'reports', 'href' => '/reports', 'label' => 'nav.reports', 'permission' => 'reports.view', 'icon' => 'chart'],
        ['key' => 'backups', 'href' => '/backups', 'label' => 'nav.backups', 'permission' => 'backups.run', 'icon' => 'shield-check'],
        ['key' => 'activity-log', 'href' => '/activity-log', 'label' => 'nav.activity_log', 'permission' => 'activity_log.view', 'icon' => 'activity'],
        ['key' => 'users', 'href' => '/users', 'label' => 'nav.users', 'permission' => 'users.manage', 'icon' => 'users-cog'],
        ['key' => 'settings', 'href' => '/settings/profile', 'label' => 'nav.settings', 'permission' => 'settings.manage', 'icon' => 'settings'],
    ];
}

/**
 * Nizam design system -- inline SVG icon set (Phase 9 UI transformation).
 * Deliberately hand-authored, not an icon font or a vendored library: a
 * webfont icon set means either a CDN (explicitly ruled out) or bundling a
 * few hundred KB of glyphs this app uses maybe 40 of. Every icon here is a
 * plain 24x24 stroke-based glyph (Feather/Lucide-style proportions,
 * stroke-width 1.75, round caps/joins) so it inherits color via
 * `currentColor` and scales crisply at any size with zero extra requests --
 * it's already part of the HTML response, never a separate file.
 *
 * @param string $name one of the keys in the icon map below
 * @param string $class optional extra class(es), e.g. 'n-icon-lg'
 * @return string inline <svg>, or '' for an unknown name (fails visibly
 *     empty rather than throwing -- a missing icon is a small cosmetic gap,
 *     never worth a fatal error on a real screen)
 */
function icon(string $name, string $class = ''): string
{
    static $paths = [
        // Navigation / modules
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
        'layers' => '<path d="M12 3 2.5 8 12 13l9.5-5L12 3Z"/><path d="M2.5 13 12 18l9.5-5M2.5 10.5 12 15.5l9.5-5"/>',
        'grid' => '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
        'book' => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21.5V4.5Z"/><path d="M4 19a2.5 2.5 0 0 1 2.5-2.5H20"/>',
        'chalkboard' => '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M9 20h6M12 16v4M7 9l2.5 2.5L14 7"/>',
        'graduation-cap' => '<path d="M2 9.5 12 4l10 5.5-10 5.5L2 9.5Z"/><path d="M6.5 12v5c0 1.4 2.5 3 5.5 3s5.5-1.6 5.5-3v-5M21 9.5v6.5"/>',
        'clipboard-list' => '<rect x="5.5" y="4" width="13" height="17" rx="2"/><path d="M9 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1"/><path d="M9 11h6M9 15h6M9 7h.01"/>',
        'chart' => '<path d="M4 20V10M12 20V4M20 20v-7"/><path d="M2.5 20h19"/>',
        'shield-check' => '<path d="M12 2.5 4.5 5.5v6c0 5 3.2 8.4 7.5 10 4.3-1.6 7.5-5 7.5-10v-6L12 2.5Z"/><path d="m9 12 2 2 4-4.5"/>',
        'activity' => '<path d="M3 12h4l2.5-7 4 14L16 12h5"/>',
        'users-cog' => '<circle cx="9" cy="7" r="3.2"/><path d="M2.5 20c0-3.5 2.9-6 6.5-6 1 0 1.9.2 2.7.55"/><circle cx="18" cy="16.5" r="2.4"/><path d="M18 12.8v.9M18 19.3v.9M14.9 16.5h.9M20.2 16.5h.9M15.4 14l.65.65M20 18.85l.65.65M15.4 19l.65-.65M20 14.15l.65-.65"/>',
        'users' => '<circle cx="8.5" cy="7.5" r="3.3"/><path d="M2 20c0-3.6 2.9-6.2 6.5-6.2s6.5 2.6 6.5 6.2"/><path d="M15.5 5a3.3 3.3 0 0 1 0 6.5M17.5 13.8c2.4.5 4.5 2.6 4.5 6.2"/>',
        'settings' => '<circle cx="12" cy="12" r="3.2"/><path d="M12 3v2.2M12 18.8V21M4.9 4.9l1.55 1.55M17.55 17.55 19.1 19.1M3 12h2.2M18.8 12H21M4.9 19.1l1.55-1.55M17.55 6.45 19.1 4.9"/>',
        // Actions
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.5-4.5"/>',
        'plus' => '<path d="M12 4.5v15M4.5 12h15"/>',
        'edit' => '<path d="M14.5 4.5 19 9l-10 10H4.5v-4.5l10-10Z"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'archive' => '<rect x="3" y="3.5" width="18" height="4.5" rx="1"/><path d="M4.5 8v10a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V8"/><path d="M10 13h4"/>',
        'unarchive' => '<rect x="3" y="3.5" width="18" height="4.5" rx="1"/><path d="M4.5 8v10a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V8"/><path d="m10 14 2-2 2 2M12 12v5"/>',
        'trash' => '<path d="M4 6.5h16M9 6.5V4.2c0-.66.54-1.2 1.2-1.2h3.6c.66 0 1.2.54 1.2 1.2v2.3"/><path d="M6.5 6.5 7.3 20a2 2 0 0 0 2 1.8h5.4a2 2 0 0 0 2-1.8l.8-13.5"/>',
        'download' => '<path d="M12 3v13"/><path d="m6.5 11 5.5 5.5L17.5 11"/><path d="M4 20.5h16"/>',
        'upload' => '<path d="M12 20V7"/><path d="m6.5 12.5 5.5-5.5 5.5 5.5"/><path d="M4 20.5h16"/>',
        'close' => '<path d="m5 5 14 14M19 5 5 19"/>',
        'check' => '<path d="m4.5 12.5 5 5 10-11"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.6 2.6L16.5 9"/>',
        'chevron-left' => '<path d="M15 5.5 8.5 12l6.5 6.5"/>',
        'chevron-right' => '<path d="M9 5.5 15.5 12 9 18.5"/>',
        'chevron-down' => '<path d="m5.5 8.5 6.5 6.5 6.5-6.5"/>',
        'menu' => '<path d="M3.5 6.5h17M3.5 12h17M3.5 17.5h17"/>',
        'list' => '<circle cx="4" cy="6" r="1"/><path d="M8 6h12"/><circle cx="4" cy="12" r="1"/><path d="M8 12h12"/><circle cx="4" cy="18" r="1"/><path d="M8 18h12"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.8 5.7 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.7-3.8-9S9.5 5.5 12 3Z"/>',
        'logout' => '<path d="M9 20.5H6a2 2 0 0 1-2-2v-13a2 2 0 0 1 2-2h3"/><path d="M15.5 16.5 20 12l-4.5-4.5M20 12H8.5"/>',
        'alert-triangle' => '<path d="M12 3.5 2 20.5h20L12 3.5Z"/><path d="M12 10v4.2M12 17.5h.01"/>',
        'alert-circle' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.2M12 16.5h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.6h.01"/>',
        'file-text' => '<path d="M6.5 2.5h8l4.5 4.5V21a1 1 0 0 1-1 1h-11.5a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1Z"/><path d="M14 2.5V7h4.5M8.5 12h7M8.5 16h7"/>',
        'printer' => '<path d="M6.5 8.5V3h11v5.5"/><rect x="3.5" y="8.5" width="17" height="8" rx="1.5"/><path d="M6.5 15.5H17.5V21h-11v-5.5Z"/>',
        'filter' => '<path d="M3.5 4.5h17L14 13v6l-4 2v-8L3.5 4.5Z"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.75"/><path d="m5 18 5.5-5.5a1.8 1.8 0 0 1 2.5 0L19 18"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
        'mail' => '<rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="m3.5 6 8.5 7 8.5-7"/>',
        'phone' => '<path d="M6 3.5h3l1.3 4.5-2.2 1.7a12.5 12.5 0 0 0 5.7 5.7l1.7-2.2 4.5 1.3v3a2 2 0 0 1-2.2 2A17.5 17.5 0 0 1 4 5.7 2 2 0 0 1 6 3.5Z"/>',
        'map-pin' => '<path d="M12 21.5S5 15 5 10a7 7 0 0 1 14 0c0 5-7 11.5-7 11.5Z"/><circle cx="12" cy="10" r="2.5"/>',
        'inbox' => '<path d="M4 12.5h4.2l1.6 2.5h4.4l1.6-2.5H20"/><path d="m5.5 4.5 11 0 3.5 8v6.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 2 19V12.5l3.5-8Z"/>',
        'database' => '<ellipse cx="12" cy="5.5" rx="8" ry="3"/><path d="M4 5.5V18c0 1.66 3.6 3 8 3s8-1.34 8-3V5.5"/><path d="M4 12c0 1.66 3.6 3 8 3s8-1.34 8-3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.3l3.5 2"/>',
        'star' => '<path d="m12 3 2.7 5.9 6.3.7-4.7 4.4 1.2 6.3L12 17.2 6.5 20.3l1.2-6.3-4.7-4.4 6.3-.7L12 3Z"/>',
        'home' => '<path d="m3.5 11 8.5-8 8.5 8"/><path d="M5.5 9.5V20a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'arrow-forward' => '<path d="M4.5 12h15M13 5.5 19.5 12 13 18.5"/>',
        'sparkle' => '<path d="M12 3v3.5M12 17.5V21M3 12h3.5M17.5 12H21M6 6l2 2M16 16l2 2M18 6l-2 2M8 16l-2 2"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M8 7.5h1.5M8 11h1.5M8 14.5h1.5M14.5 7.5H16M14.5 11H16M14.5 14.5H16M10 21v-4h4v4"/>',
        'trending-up' => '<path d="M3 17 9 11 13 15 21 7"/><path d="M15 7h6v6"/>',
    ];

    if (!isset($paths[$name])) {
        return '';
    }
    $cls = 'n-icon' . ($class !== '' ? ' ' . $class : '');
    return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $paths[$name] . '</svg>';
}

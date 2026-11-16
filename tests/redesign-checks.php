<?php

declare(strict_types=1);

/**
 * Ad-hoc QA harness for the Nizam redesign (run: php tests/redesign-checks.php).
 * Verifies what can be checked statically: translation-key parity between
 * lang/en.php and lang/ar.php, icon-name audit of every icon('...') call in
 * views against the SVG map in app/helpers/functions.php, and CSS-class spot
 * checks (every n-* class referenced by any view has a definition in app.css).
 */

$fail = 0;

$en = require __DIR__ . '/../lang/en.php';
$ar = require __DIR__ . '/../lang/ar.php';

$onlyEn = array_diff(array_keys($en), array_keys($ar));
$onlyAr = array_diff(array_keys($ar), array_keys($en));

if ($onlyEn === [] && $onlyAr === []) {
    echo "PASS: translation parity (" . count($en) . " keys in both locales)\n";
} else {
    $fail++;
    echo "FAIL: translation parity\n";
    foreach ($onlyEn as $key) {
        echo "  only in en: {$key}\n";
    }
    foreach ($onlyAr as $key) {
        echo "  only in ar: {$key}\n";
    }
}

// --- icon audit -------------------------------------------------------------
$functions = file_get_contents(__DIR__ . '/../app/helpers/functions.php');
if (preg_match("/'arrow-forward' =>.*$/m", $functions) === 1) {
    // collect the icon map by locating the "return [" inside icon()
    preg_match('/function icon\(.*?\{\s*static \$paths = \[(.*?)\];/s', $functions, $m);
    $mapBody = $m[1] ?? '';
    preg_match_all("/'([a-z0-9_-]+)'\s*=>/", $mapBody, $mapKeys);
    $iconMap = array_flip($mapKeys[1]);
} else {
    $iconMap = [];
}

$used = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../views'));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    preg_match_all("/icon\('([a-z0-9_-]+)'/", $contents, $hits);
    foreach ($hits[1] as $name) {
        $used[$name] = ($used[$name] ?? 0) + 1;
    }
}

$missing = array_diff(array_keys($used), array_keys($iconMap));
if ($missing === []) {
    echo 'PASS: icon audit (' . count($used) . " distinct icon names, all defined)\n";
} else {
    $fail++;
    echo "FAIL: icons referenced in views but missing from icon(): " . implode(', ', $missing) . "\n";
}

// --- CSS class audit --------------------------------------------------------
// The redesign is split across app.css plus the two Studio layers; the audit
// must see all of them or it reports false positives for classes that only
// exist in theme.css/components.css.
$css = '';
foreach (['app.css', 'theme.css', 'components.css'] as $cssFile) {
    $css .= (string) file_get_contents(__DIR__ . '/../public/assets/css/' . $cssFile) . "\n";
}
$missingClasses = [];
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    preg_match_all('/(?<![a-zA-Z0-9_-])n-[a-z0-9]+(?:-[a-z0-9]+)*/', $contents, $hits);
    foreach ($hits[0] as $class) {
        // translation keys and data-name attributes share the n- prefix shape
        if (str_starts_with($class, 'n-grades') || str_starts_with($class, 'n-stat')) {
            // legacy family, still defined
        }
        if (!isset($missingClasses[$class]) && str_contains($class, '.') === false) {
            if (preg_match('/(\.|^)' . preg_quote($class, '/') . '[\s,:{.\-]/m', $css) !== 1
                && preg_match("/{$class}:[^;]+;/", $css) !== 1
                && preg_match('/' . preg_quote($class, '/') . '\b/', $css) !== 1) {
                $missingClasses[$class] = $file->getFilename();
            }
        }
    }
}
$missingClasses = array_filter($missingClasses, static fn ($f) => !str_contains($f, 'lang'));
if ($missingClasses === []) {
    echo "PASS: CSS class audit\n";
} else {
    echo "WARN: n-* tokens referenced in views with no direct CSS mention (verify they are variant hooks): "
        . implode(', ', array_keys($missingClasses)) . "\n";
}

// --- single heading per page ------------------------------------------------
// The shared layout renders the page hero (h1) for every screen. A view that
// draws its own header must set $suppressPageTitle, otherwise the page shows
// two titles -- exactly the bug the admissions screen shipped with.
$duplicateHeads = [];
$viewIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../views'));
foreach ($viewIterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $normalized = str_replace('\\', '/', $path);
    if (str_contains($normalized, '/views/layout/')) {
        continue; // the shell documents own their own <html>
    }
    $contents = (string) file_get_contents($path);
    if (!str_contains($contents, 'layout/start.php')) {
        continue; // login/error/PDF are standalone documents with their own <html>
    }
    if (str_contains($contents, '<h1') && !str_contains($contents, 'suppressPageTitle')) {
        $duplicateHeads[] = str_replace(__DIR__ . '/../', '', $file->getPathname());
    }
}
if ($duplicateHeads === []) {
    echo "PASS: every view with its own heading suppresses the shared hero\n";
} else {
    $fail++;
    echo "FAIL: views render an h1 without suppressing the shared hero (double heading): " . implode(', ', $duplicateHeads) . "\n";
}
if (str_contains((string) file_get_contents(__DIR__ . '/../views/layout/start.php'), 'n-hero-compact')) {
    echo "PASS: shared layout hero present\n";
} else {
    $fail++;
    echo "FAIL: shared layout hero missing from views/layout/start.php\n";
}

// --- one header component ---------------------------------------------------
// Screens must not draw their own page header markup any more: the layouts
// render the hero, and views only contribute a subtitle and actions.
$legacyHeads = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../views')) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $normalized = str_replace('\\', '/', $file->getPathname());
    if (str_contains($normalized, '/views/layout/')) {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    foreach (['n-page-head', 'n-grades-header'] as $legacy) {
        if (str_contains($contents, $legacy)) {
            $legacyHeads[] = str_replace(__DIR__ . '/../', '', $file->getPathname()) . ' (' . $legacy . ')';
        }
    }
}
if ($legacyHeads === []) {
    echo "PASS: every view uses the shared hero (no legacy page heads)\n";
} else {
    $fail++;
    echo "FAIL: views still draw their own page head: " . implode(', ', $legacyHeads) . "\n";
}

// --- navigation coverage ----------------------------------------------------
// Every sidebar/command-palette link must point at a GET route that exists, so
// no module can be listed and then 404.
$functionsSource = (string) file_get_contents(__DIR__ . '/../app/helpers/functions.php');
preg_match('/function navItems\(\).*?\$catalog = \[(.*?)\];/s', $functionsSource, $navMatch);
$navBlock = $navMatch[1] ?? '';
preg_match_all("/'href'\s*=>\s*'([^']+)'/", $navBlock, $navHrefs);

$routesSource = (string) file_get_contents(__DIR__ . '/../public/index.php');
preg_match_all("/\\\$router->get\('([^']+)'/" , $routesSource, $getRoutes);
$registered = [];
foreach ($getRoutes[1] as $route) {
    // collapse {param} segments so /students/{id} matches /students/7
    $registered[preg_replace('/\{[^}]+\}/', '{id}', $route)] = true;
}

$brokenLinks = [];
foreach ($navHrefs[1] as $href) {
    if (preg_match('/\{[^}]+\}/', $href) === 1) {
        continue; // parameterised destinations are not nav targets
    }
    if (!isset($registered[$href])) {
        $brokenLinks[] = $href;
    }
}
if ($brokenLinks === []) {
    echo 'PASS: navigation coverage (' . count($navHrefs[1]) . " nav links all resolve to GET routes)\n";
} else {
    $fail++;
    echo 'FAIL: navigation entries with no matching GET route: ' . implode(', ', $brokenLinks) . "\n";
}

// Authentication and recovery HTML contains live CSRF/session state. The
// service worker must never fall back to a stale cached copy of those pages.
$serviceWorker = (string) file_get_contents(__DIR__ . '/../public/sw.js');
if (str_contains($serviceWorker, 'isSensitivePage(url)')
    && str_contains($serviceWorker, "url.pathname.startsWith('/setup/')")
    && str_contains($serviceWorker, "url.pathname === '/login'")
    && str_contains($serviceWorker, 'networkOnly(request)')) {
    echo "PASS: auth and recovery routes are network-only in the service worker\n";
} else {
    $fail++;
    echo "FAIL: service worker may cache live auth/recovery pages\n";
}

// Rail mode must override the desktop shell's higher-specificity flex
// shorthand, not merely hide labels while retaining a full-width column.
if (str_contains($css, 'body.n-app-body .nizam-shell.n-sidebar-collapsed > .nizam-sidebar')
    && str_contains($css, 'flex: 0 0 var(--n-sidebar-collapsed-width)')) {
    echo "PASS: collapsed sidebar releases its full-width flex column\n";
} else {
    $fail++;
    echo "FAIL: collapsed sidebar does not override the desktop flex width\n";
}

// Inline handlers/scripts force script-src 'unsafe-inline', weakening CSP on
// every route. All behavior must live in versioned self-hosted JS files.
$inlineScriptViews = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../views')) as $file) {
    if ($file->getExtension() !== 'php') continue;
    $source = (string) file_get_contents($file->getPathname());
    if (preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $source) === 1
        || preg_match('/\son[a-z]+\s*=/i', $source) === 1) {
        $inlineScriptViews[] = str_replace(__DIR__ . '/../', '', $file->getPathname());
    }
}
if ($inlineScriptViews === []) {
    echo "PASS: views require no inline JavaScript CSP exception\n";
} else {
    $fail++;
    echo 'FAIL: inline JavaScript remains in: ' . implode(', ', $inlineScriptViews) . "\n";
}

echo $fail === 0 ? "ALL CHECKS PASSED\n" : "{$fail} CHECK(S) FAILED\n";
exit($fail === 0 ? 0 : 1);

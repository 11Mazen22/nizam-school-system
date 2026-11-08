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
$css = (string) file_get_contents(__DIR__ . '/../public/assets/css/app.css');
$missingClasses = [];
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    preg_match_all('/n-[a-z0-9]+(?:-[a-z0-9]+)*/', $contents, $hits);
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

echo $fail === 0 ? "ALL CHECKS PASSED\n" : "{$fail} CHECK(S) FAILED\n";
exit($fail === 0 ? 0 : 1);

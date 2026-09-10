<?php

declare(strict_types=1);

/**
 * Nizam -- application bootstrap. Own App\ classes use a plain
 * spl_autoload_register mapping the namespace onto app/ -- no Composer
 * needed for code this project wrote itself. Phase 7 is the first phase
 * with a real vendored dependency (mPDF, PhpSpreadsheet, per decisions
 * #3/#4), so Composer's own autoloader now loads alongside it, exactly the
 * moment Phase 4's original version of this file said it would.
 */

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = substr($class, strlen('App\\'));
    $segments = explode('\\', $relative);
    $className = array_pop($segments);
    // Directories are lowercase (app/middleware, app/services, ...); the
    // class name itself keeps its real casing to match the filename.
    $segments = array_map('strtolower', $segments);
    $path = dirname(__DIR__) . '/app/' . implode('/', $segments) . '/' . $className . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

require __DIR__ . '/helpers/functions.php';

App\ErrorHandler::register();

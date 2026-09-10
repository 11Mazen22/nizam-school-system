<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Nizam -- centralized error/exception handling (§C: "log the technical
 * detail to storage/logs and show the user a friendly, translated message --
 * never a stack trace"; §Q error catalog: "never a stack trace or SQL error
 * text," a reference id the user can quote back to a developer).
 */
final class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(Throwable $e): void
    {
        $ref = self::logAndGetReference($e);
        self::render(500, $ref);
    }

    /** Converts a PHP warning/notice into the same logged-and-continue path, per PHP's own error_reporting rules. */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false; // respects @-suppression and error_reporting() level
        }
        Logger::error("PHP error: $message", ['file' => $file, 'line' => $line, 'severity' => $severity]);
        return true;
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $ref = substr(bin2hex(random_bytes(4)), 0, 8);
            Logger::error('Fatal error', ['ref' => $ref, 'message' => $error['message'], 'file' => $error['file'], 'line' => $error['line']]);
            if (!headers_sent()) {
                self::render(500, $ref);
            }
        }
    }

    public static function renderNotFound(): void
    {
        self::render(404);
    }

    public static function renderForbidden(): void
    {
        self::render(403);
    }

    public static function renderExpired(): void
    {
        self::render(419);
    }

    private static function logAndGetReference(Throwable $e): string
    {
        $ref = substr(bin2hex(random_bytes(4)), 0, 8);
        Logger::error($e->getMessage(), [
            'ref' => $ref,
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        return $ref;
    }

    private static function render(int $status, ?string $ref = null): void
    {
        if (!headers_sent()) {
            http_response_code($status);
        }
        $view = dirname(__DIR__) . "/views/errors/{$status}.php";
        if (is_file($view)) {
            require $view;
        } else {
            echo "Error {$status}";
        }
    }
}

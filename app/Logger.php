<?php

declare(strict_types=1);

namespace App;

/**
 * Nizam -- minimal file logger (§C: "storage/logs", §Q: application errors
 * are logged, never shown raw to the user). One file per day, plain text,
 * newest line last -- deliberately not a logging library: a single
 * offline-only install has no need for log rotation policies, multiple
 * handlers, or PSR-3 abstraction it will never swap out.
 */
final class Logger
{
    private static function path(): string
    {
        $dir = dataPath() . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/' . date('Y-m-d') . '.log';
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            '[%s] %s: %s %s%s',
            date('Y-m-d H:i:s'),
            $level,
            $message,
            empty($context) ? '' : json_encode($context, JSON_UNESCAPED_UNICODE),
            PHP_EOL
        );
        // Never let a logging failure itself become a fatal error.
        @file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX);
    }
}

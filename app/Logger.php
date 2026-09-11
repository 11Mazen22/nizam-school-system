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

        // Found live: storage/logs/*.log sits on the online deployment's
        // ephemeral container disk -- invisible to the platform's own log
        // capture (which only sees stdout/stderr) and gone on the next
        // restart or redeploy, so a real production error left literally no
        // trace anywhere reachable. error_log() reaches Apache's own error
        // stream, which the platform DOES capture -- harmless extra output
        // on the offline/Windows target, where the file already suffices.
        error_log(trim($line));
    }
}

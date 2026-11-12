<?php

declare(strict_types=1);

namespace App;

/**
 * Nizam -- session-based flash messages (Phase 5 shell: "flash-message
 * area"). Consume-once: reading a message removes it, so a page refresh
 * never re-shows a stale success/error banner.
 */
final class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Convenience wrappers. Several controllers (finance, library, transport,
     * HR, admissions, guardians, modules) and ModuleGateMiddleware were written
     * against these helpers while only set()/consume() existed, so those paths
     * raised "Call to undefined method" at runtime instead of showing a
     * message. warning() specifically made every disabled module return 500
     * instead of the intended "this module is off" redirect.
     *
     * The full set is kept deliberately: a missing helper is a fatal error on
     * a user-facing path, and guessing which severity a future call site uses
     * is cheaper than a 500.
     */
    public static function success(string $message): void
    {
        self::set('success', $message);
    }

    public static function error(string $message): void
    {
        self::set('danger', $message);
    }

    public static function warning(string $message): void
    {
        self::set('warning', $message);
    }

    public static function info(string $message): void
    {
        self::set('info', $message);
    }

    /** @return array<int, array{type: string, message: string}> */
    public static function consume(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }
}

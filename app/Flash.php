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

    /** @return array<int, array{type: string, message: string}> */
    public static function consume(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }
}

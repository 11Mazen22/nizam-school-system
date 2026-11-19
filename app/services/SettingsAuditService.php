<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Settings changes are audit facts: record who changed what, the old and new
 * values, and when. The timestamp comes from activity_logs.created_at; the
 * actor from the session via ActivityLogger. Values are stored as a JSON
 * metadata object (old/new) so the Activity Log can show a precise diff
 * without leaking passwords or file bytes into free text.
 */
final class SettingsAuditService
{
    /**
     * @param array<string,mixed> $changes field => ['old'=>mixed,'new'=>mixed]
     */
    public static function record(string $area, array $changes, string $summary): void
    {
        if ($changes === []) {
            return;
        }
        ActivityLogger::log(
            'settings.update',
            'settings',
            null,
            $summary,
            [
                'area' => $area,
                'changes' => self::redact($changes),
                'time' => date('Y-m-d H:i:s'),
                'actor' => (string) ($_SESSION['username'] ?? ''),
            ]
        );
    }

    /** @param array<string,mixed> $changes */
    private static function redact(array $changes): array
    {
        foreach ($changes as $field => $pair) {
            if (!is_array($pair)) {
                continue;
            }
            foreach (['old', 'new'] as $side) {
                if (array_key_exists($side, $pair) && is_string($pair[$side]) && strlen($pair[$side]) > 300) {
                    $pair[$side] = substr($pair[$side], 0, 300) . '…';
                }
            }
            $changes[$field] = $pair;
        }
        return $changes;
    }
}

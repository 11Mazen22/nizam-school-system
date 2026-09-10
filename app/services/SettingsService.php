<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;

/**
 * Nizam -- typed settings access (§O-20: "settings.value_type isn't enforced
 * anywhere -- build one small typed SettingsService::get()/set() that casts
 * on the way in and out, rather than letting every caller handle raw
 * strings"). Every other Phase 4 piece that reads a settings.* value
 * (LoginThrottleService, SessionMiddleware) goes through this, never the
 * repository directly.
 */
final class SettingsService
{
    /**
     * @param bool|int|string|null $default used only if the key has no row --
     *   e.g. a fresh database before the Setup Wizard has written school.name.
     * @return bool|int|string|null
     */
    public static function get(string $key, bool|int|string|null $default = null): bool|int|string|null
    {
        $row = (new SettingsRepository())->find($key);
        if ($row === null) {
            return $default;
        }
        return self::cast($row['value'], $row['value_type']);
    }

    public static function set(string $key, bool|int|string $value, string $valueType): void
    {
        if (!in_array($valueType, ['string', 'int', 'bool'], true)) {
            throw new \InvalidArgumentException("Unknown settings value_type: $valueType");
        }
        $stored = match ($valueType) {
            'bool' => $value ? '1' : '0',
            default => (string) $value,
        };
        (new SettingsRepository())->set($key, $stored, $valueType);
    }

    private static function cast(?string $value, string $type): bool|int|string|null
    {
        if ($value === null) {
            return null;
        }
        return match ($type) {
            'int' => (int) $value,
            'bool' => $value === '1',
            default => $value,
        };
    }
}

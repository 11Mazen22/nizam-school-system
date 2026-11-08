<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class ModuleRepository
{
    /**
     * @return array<int, array{
     *   id: int,
     *   module_key: string,
     *   name_en: string,
     *   name_ar: string,
     *   description_en: ?string,
     *   description_ar: ?string,
     *   category: string,
     *   is_enabled: int|bool,
     *   is_core: int|bool,
     *   settings: ?string,
     *   installed_at: string,
     *   updated_at: string
     * }>
     */
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, module_key, name_en, name_ar, description_en, description_ar,
                    category, is_enabled, is_core, settings, installed_at, updated_at
             FROM installed_modules
             ORDER BY is_core DESC, id ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function find(string $moduleKey): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, module_key, name_en, name_ar, description_en, description_ar,
                    category, is_enabled, is_core, settings, installed_at, updated_at
             FROM installed_modules
             WHERE module_key = :key
             LIMIT 1'
        );
        $stmt->execute(['key' => $moduleKey]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function isEnabled(string $moduleKey): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT is_enabled FROM installed_modules WHERE module_key = :key LIMIT 1'
        );
        $stmt->execute(['key' => $moduleKey]);
        $val = $stmt->fetchColumn();
        return $val !== false && (bool) $val;
    }

    public function setEnabled(string $moduleKey, bool $enabled): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE installed_modules
             SET is_enabled = :enabled, updated_at = :now
             WHERE module_key = :key AND is_core = 0'
        );
        $stmt->execute([
            'enabled' => $enabled ? 1 : 0,
            'now'     => date('Y-m-d H:i:s'),
            'key'     => $moduleKey,
        ]);
    }
}

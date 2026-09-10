<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class SettingsRepository
{
    public function find(string $key): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT setting_key, value, value_type FROM settings WHERE setting_key = :key'
        );
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function set(string $key, string $value, string $valueType): void
    {
        $pdo = Database::connection();
        // Same upsert, two dialects -- see SubjectStaffingRequirementRepository::upsert()'s note.
        $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
            ? 'INSERT INTO settings (setting_key, value, value_type) VALUES (:key, :value, :type)
               ON CONFLICT (setting_key) DO UPDATE SET value = :value2, value_type = :type2'
            : 'INSERT INTO settings (setting_key, value, value_type) VALUES (:key, :value, :type)
               ON DUPLICATE KEY UPDATE value = :value2, value_type = :type2';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'key' => $key, 'value' => $value, 'type' => $valueType,
            'value2' => $value, 'type2' => $valueType,
        ]);
    }
}

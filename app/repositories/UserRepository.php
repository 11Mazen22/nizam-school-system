<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class UserRepository
{
    public function findByUsername(string $username): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.username, u.password_hash, u.full_name, u.role_id, u.is_active,
                    u.failed_attempts, u.locked_until, r.code AS role_code
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.username = :username'
        );
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return string[] permission codes granted to this user's role */
    public function permissionCodesFor(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.code
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN users u ON u.role_id = rp.role_id
             WHERE u.id = :id'
        );
        $stmt->execute(['id' => $userId]);
        return array_column($stmt->fetchAll(), 'code');
    }

    public function recordFailedAttempt(int $userId, int $newCount, ?string $lockedUntil): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET failed_attempts = :count, locked_until = :locked WHERE id = :id'
        );
        $stmt->execute(['count' => $newCount, 'locked' => $lockedUntil, 'id' => $userId]);
    }

    public function recordSuccessfulLogin(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
    }

    /** §Q "Users | List" -- newest first isn't meaningful for a handful of staff accounts; alphabetical by name is what a short admin-facing list actually wants. */
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT u.id, u.username, u.full_name, u.role_id, u.is_active, u.last_login_at, r.code AS role_code, r.name_en AS role_name_en, r.name_ar AS role_name_ar
             FROM users u JOIN roles r ON r.id = u.role_id
             ORDER BY u.full_name ASC'
        );
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.username, u.full_name, u.role_id, u.is_active, r.code AS role_code
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE username = :username';
        $params = ['username' => $username];
        if ($excludeId !== null) {
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = $excludeId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    public function create(string $username, string $passwordHash, string $fullName, int $roleId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (username, password_hash, full_name, role_id, is_active) VALUES (:username, :hash, :name, :role, 1)'
        );
        $stmt->execute(['username' => $username, 'hash' => $passwordHash, 'name' => $fullName, 'role' => $roleId]);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, string $username, string $fullName, int $roleId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET username = :username, full_name = :name, role_id = :role WHERE id = :id'
        );
        $stmt->execute(['username' => $username, 'name' => $fullName, 'role' => $roleId, 'id' => $id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $passwordHash, 'id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    /** O-5's own restore invariant ("at least one active Administrator") re-used here as the same guard on every Users write that could ever violate it -- archiving, or editing a role away from admin. */
    public function countActiveAdmins(?int $excludingUserId = null): int
    {
        $sql = "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
                WHERE r.code = 'admin' AND u.is_active = 1";
        $params = [];
        if ($excludingUserId !== null) {
            $sql .= ' AND u.id != :excludeId';
            $params['excludeId'] = $excludingUserId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array{id:int, code:string, name_en:string, name_ar:string}> the fixed, code-defined roles table (admin/staff) -- for the Add/Edit form's role dropdown. */
    public function allRoles(): array
    {
        return Database::connection()->query('SELECT id, code, name_en, name_ar FROM roles ORDER BY id ASC')->fetchAll();
    }

    public function roleIdForCode(string $code): ?int
    {
        $stmt = Database::connection()->prepare('SELECT id FROM roles WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }
}

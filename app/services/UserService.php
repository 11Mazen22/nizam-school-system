<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use RuntimeException;

/**
 * Nizam -- §Q "Users | List, Add/Edit (Administrator only)". The one rule
 * that doesn't exist anywhere else in the Users screen's own spec but is
 * load-bearing for the whole permission system: at least one active
 * Administrator must always exist, the same invariant RestoreService already
 * checks after a restore (O-5) -- enforced here on every write that could
 * violate it, not just recovered after the fact.
 */
final class UserService
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    /** @throws RuntimeException 'username_taken'|'password_too_short'|'invalid_role' */
    public function create(string $username, string $password, string $fullName, string $roleCode, ?string $email = null): int
    {
        if ($this->users->usernameExists($username)) {
            throw new RuntimeException('username_taken');
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new RuntimeException('password_too_short');
        }
        $roleId = $this->users->roleIdForCode($roleCode);
        if ($roleId === null) {
            throw new RuntimeException('invalid_role');
        }

        $id = $this->users->create($username, password_hash($password, PASSWORD_BCRYPT), $fullName, $roleId, $email);
        ActivityLogger::log('user.create', 'users', $id, "User '{$username}' created");
        return $id;
    }

    /**
     * Password is optional here -- an empty string means "leave the current
     * password unchanged," the standard edit-form convention for a secret
     * field that must never be redisplayed to confirm its current value.
     *
     * @throws RuntimeException 'username_taken'|'password_too_short'|'invalid_role'|'last_admin'
     */
    public function update(int $id, string $username, string $fullName, string $roleCode, string $newPassword, ?string $email = null): void
    {
        if ($this->users->usernameExists($username, $id)) {
            throw new RuntimeException('username_taken');
        }
        $roleId = $this->users->roleIdForCode($roleCode);
        if ($roleId === null) {
            throw new RuntimeException('invalid_role');
        }
        if ($roleCode !== 'admin' && $this->wouldRemoveLastAdmin($id)) {
            throw new RuntimeException('last_admin');
        }

        $this->users->update($id, $username, $fullName, $roleId, $email);

        if ($newPassword !== '') {
            if (mb_strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
                throw new RuntimeException('password_too_short');
            }
            $this->users->updatePassword($id, password_hash($newPassword, PASSWORD_BCRYPT));
        }

        ActivityLogger::log('user.update', 'users', $id, "User '{$username}' updated");
    }

    /** @throws RuntimeException 'last_admin' */
    public function archive(int $id): void
    {
        if ($this->wouldRemoveLastAdmin($id)) {
            throw new RuntimeException('last_admin');
        }
        $this->users->setActive($id, false);
        ActivityLogger::log('user.archive', 'users', $id, null);
    }

    public function restore(int $id): void
    {
        $this->users->setActive($id, true);
        ActivityLogger::log('user.restore', 'users', $id, null);
    }

    /** True only if $id is itself a currently-active Administrator and no OTHER active Administrator exists -- archiving/demoting anyone else, or a second admin, is always safe. */
    private function wouldRemoveLastAdmin(int $id): bool
    {
        $user = $this->users->find($id);
        if ($user === null || $user['role_code'] !== 'admin' || (int) $user['is_active'] !== 1) {
            return false;
        }
        return $this->users->countActiveAdmins(excludingUserId: $id) === 0;
    }
}

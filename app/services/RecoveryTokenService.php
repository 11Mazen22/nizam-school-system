<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use Throwable;

/**
 * One-time administrator recovery codes.
 *
 * The code is issued only by the server-side CLI. Its plain value is never
 * persisted: the database stores a fast SHA-256 lookup digest (safe because
 * codes carry roughly 100 bits of random entropy) plus password_hash() for
 * defence in depth. One database row is deliberately used as a single active
 * recovery slot, so issuing a new code immediately revokes the previous one.
 *
 * Password replacement, lockout reset, and code deletion are committed in one
 * short row-locked transaction. This avoids both historical failure modes:
 * CLI/web data-path mismatches and burning a valid code before the password
 * update was known to have succeeded.
 */
final class RecoveryTokenService
{
    public const TTL_SECONDS = 1200;

    private const SLOT_ID = 1;

    /** Unambiguous alphabet: no 0/O, 1/I/L. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Active administrator accounts available to the trusted CLI chooser.
     *
     * @return list<array{id:int, username:string, full_name:string, role:string}>
     */
    public static function activeAdmins(): array
    {
        $rows = Database::connection()->query(
            "SELECT u.id, u.username, u.full_name, r.code AS role
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = 1 AND r.code = 'admin'
             ORDER BY u.id"
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'full_name' => (string) $row['full_name'],
            'role' => (string) $row['role'],
        ], $rows);
    }

    /**
     * Mint a code for an active administrator selected by username.
     *
     * @return array{token:string, username:string, full_name:string, expires_at:int}|null
     *         null only when no matching active administrator exists.
     * @throws PDOException when recovery storage is unavailable.
     */
    public static function issue(string $username): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT u.id, u.username, u.full_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.username = :username AND u.is_active = 1 AND r.code = 'admin'
             LIMIT 1"
        );
        $stmt->execute(['username' => trim($username)]);
        $user = $stmt->fetch();

        return $user === false ? null : self::issueForUser($pdo, $user);
    }

    /**
     * Mint a code for the account selected by the CLI's numeric chooser.
     *
     * @return array{token:string, username:string, full_name:string, expires_at:int}|null
     */
    public static function issueForUserId(int $userId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT u.id, u.username, u.full_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id AND u.is_active = 1 AND r.code = 'admin'
             LIMIT 1"
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        return $user === false ? null : self::issueForUser($pdo, $user);
    }

    /**
     * Public-page status deliberately excludes account identity.
     *
     * @return array{status:'active'|'none'|'expired'|'unavailable', expires_at?:int}
     */
    public static function pendingStatus(): array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT expires_at FROM admin_recovery_tokens WHERE slot_id = :slot');
            $stmt->execute(['slot' => self::SLOT_ID]);
            $expiresAt = $stmt->fetchColumn();
            if ($expiresAt === false) {
                return ['status' => 'none'];
            }

            $expiresEpoch = strtotime((string) $expiresAt);
            if ($expiresEpoch === false || $expiresEpoch <= time()) {
                $delete = $pdo->prepare('DELETE FROM admin_recovery_tokens WHERE slot_id = :slot');
                $delete->execute(['slot' => self::SLOT_ID]);
                return ['status' => 'expired'];
            }

            return ['status' => 'active', 'expires_at' => $expiresEpoch];
        } catch (PDOException) {
            // Existing installations reach this state only before migration
            // 023/027 is applied. Render actionable guidance instead of a 500.
            return ['status' => 'unavailable'];
        }
    }

    public static function hasPending(): bool
    {
        return self::pendingStatus()['status'] === 'active';
    }

    /**
     * Verify a code and atomically replace the administrator password.
     *
     * @return array{status:'ok', user_id:int, username:string}
     *     |array{status:'invalid'|'expired'|'none'|'account_unavailable'|'storage_error'}
     */
    public static function resetWithToken(string $token, string $password): array
    {
        $normalized = self::normalize($token);
        if (strlen($normalized) !== 20) {
            return ['status' => 'invalid'];
        }

        $digest = hash('sha256', $normalized);

        try {
            $pdo = Database::connection();

            // The expensive password_verify()/password_hash() work happens
            // before taking a row lock; the transaction below stays tiny.
            $candidate = self::findByDigest($pdo, $digest, false);
            if ($candidate === null) {
                $pending = self::pendingStatus();
                return ['status' => match ($pending['status']) {
                    'none' => 'none',
                    'expired' => 'expired',
                    'unavailable' => 'storage_error',
                    default => 'invalid',
                }];
            }

            $expiresAt = strtotime((string) $candidate['expires_at']);
            if ($expiresAt === false || $expiresAt <= time()) {
                self::deleteDigest($pdo, $digest);
                return ['status' => 'expired'];
            }

            if (!password_verify($normalized, (string) $candidate['token_hash'])) {
                return ['status' => 'invalid'];
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            $locked = self::findByDigest($pdo, $digest, true);
            if ($locked === null
                || !hash_equals((string) $candidate['token_hash'], (string) $locked['token_hash'])) {
                $pdo->rollBack();
                return ['status' => 'invalid'];
            }

            $lockedExpiry = strtotime((string) $locked['expires_at']);
            if ($lockedExpiry === false || $lockedExpiry <= time()) {
                self::deleteDigest($pdo, $digest);
                $pdo->commit();
                return ['status' => 'expired'];
            }

            if ((int) $locked['is_active'] !== 1 || (string) $locked['role'] !== 'admin') {
                // Keep the still-valid code. An operator can re-enable the
                // intended administrator and submit the same code again.
                $pdo->rollBack();
                return ['status' => 'account_unavailable'];
            }

            $update = $pdo->prepare(
                'UPDATE users
                 SET password_hash = :hash, failed_attempts = 0, locked_until = NULL
                 WHERE id = :id AND is_active = 1'
            );
            $update->execute([
                'hash' => $passwordHash,
                'id' => (int) $locked['user_id'],
            ]);
            if ($update->rowCount() !== 1) {
                $pdo->rollBack();
                return ['status' => 'account_unavailable'];
            }

            self::deleteDigest($pdo, $digest);
            $pdo->commit();
            self::clearLegacyFile();

            return [
                'status' => 'ok',
                'user_id' => (int) $locked['user_id'],
                'username' => (string) $locked['username'],
            ];
        } catch (Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['status' => 'storage_error'];
        }
    }

    /** @return array{token:string, username:string, full_name:string, expires_at:int} */
    private static function issueForUser(PDO $pdo, array $user): array
    {
        $token = self::generateToken();
        $normalized = self::normalize($token);
        $now = time();
        $expiresAt = $now + self::TTL_SECONDS;
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $issuedSql = self::sqlTimestamp($now, $driver);
        $expiresSql = self::sqlTimestamp($expiresAt, $driver);

        $params = [
            'slot' => self::SLOT_ID,
            'user' => (int) $user['id'],
            'digest' => hash('sha256', $normalized),
            'hash' => password_hash($normalized, PASSWORD_DEFAULT),
            'issued' => $issuedSql,
            'expires' => $expiresSql,
        ];

        if ($driver === 'pgsql') {
            $sql = 'INSERT INTO admin_recovery_tokens
                        (slot_id, user_id, token_digest, token_hash, issued_at, expires_at)
                    VALUES (:slot, :user, :digest, :hash, :issued, :expires)
                    ON CONFLICT (slot_id) DO UPDATE SET
                        user_id = EXCLUDED.user_id,
                        token_digest = EXCLUDED.token_digest,
                        token_hash = EXCLUDED.token_hash,
                        issued_at = EXCLUDED.issued_at,
                        expires_at = EXCLUDED.expires_at';
        } else {
            $sql = 'INSERT INTO admin_recovery_tokens
                        (slot_id, user_id, token_digest, token_hash, issued_at, expires_at)
                    VALUES (:slot, :user, :digest, :hash, :issued, :expires)
                    ON DUPLICATE KEY UPDATE
                        user_id = VALUES(user_id),
                        token_digest = VALUES(token_digest),
                        token_hash = VALUES(token_hash),
                        issued_at = VALUES(issued_at),
                        expires_at = VALUES(expires_at)';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        self::clearLegacyFile();

        return [
            'token' => $token,
            'username' => (string) $user['username'],
            'full_name' => (string) $user['full_name'],
            'expires_at' => $expiresAt,
        ];
    }

    /** @return array<string,mixed>|null */
    private static function findByDigest(PDO $pdo, string $digest, bool $forUpdate): ?array
    {
        $sql = "SELECT t.user_id, t.token_hash, t.expires_at,
                       u.username, u.is_active, r.code AS role
                FROM admin_recovery_tokens t
                JOIN users u ON u.id = t.user_id
                JOIN roles r ON r.id = u.role_id
                WHERE t.slot_id = :slot AND t.token_digest = :digest";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['slot' => self::SLOT_ID, 'digest' => $digest]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private static function deleteDigest(PDO $pdo, string $digest): void
    {
        $stmt = $pdo->prepare(
            'DELETE FROM admin_recovery_tokens WHERE slot_id = :slot AND token_digest = :digest'
        );
        $stmt->execute(['slot' => self::SLOT_ID, 'digest' => $digest]);
    }

    /** Tokens are shown as five groups of four. */
    private static function generateToken(): string
    {
        $raw = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 20; $i++) {
            $raw .= self::ALPHABET[random_int(0, $max)];
        }

        return implode('-', str_split($raw, 4));
    }

    /** Case, spaces, and any style of pasted dash are intentionally forgiving. */
    private static function normalize(string $token): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $token));
    }

    private static function sqlTimestamp(int $timestamp, string $driver): string
    {
        if ($driver === 'pgsql') {
            return (new DateTimeImmutable('@' . $timestamp))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:sP');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /** Remove credentials created by releases that used the old file store. */
    private static function clearLegacyFile(): void
    {
        $path = rtrim(dataPath(), '/\\') . DIRECTORY_SEPARATOR . 'recovery-token.json';
        if (!is_file($path)) {
            return;
        }
        @file_put_contents($path, '{"revoked":true}', LOCK_EX);
        @unlink($path);
    }
}

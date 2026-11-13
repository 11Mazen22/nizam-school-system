<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

/**
 * Nizam -- one-time admin recovery tokens.
 *
 * The /setup/recover-admin screen can create the first admin only while the
 * users table is empty. On a live install every user row already exists, so
 * that screen used to redirect to /login and the "recover admin" button did
 * nothing at all. Recovery now runs through a token that only someone with
 * shell access to the server can mint:
 *
 *   php bin/recover-admin.php <username>
 *
 * The token is single use, expires after 20 minutes, is stored hashed next to
 * the app's data (never in the database and never in the repository), and is
 * consumed by the same screen staff already know.
 */
final class RecoveryTokenService
{
    public const TTL_SECONDS = 1200;

    private const FILE = 'recovery-token.json';

    /** Unambiguous alphabet: no 0/O, 1/I/L. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function path(): string
    {
        return rtrim(dataPath(), '/\\') . DIRECTORY_SEPARATOR . self::FILE;
    }

    /**
     * Mint a token for an active user.
     *
     * @return array{token: string, username: string, expires_at: int}|null
     *         null when the username does not exist or is not active.
     */
    public static function issue(string $username): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE username = :u AND is_active = 1 LIMIT 1');
        $stmt->execute(['u' => trim($username)]);
        $user = $stmt->fetch();
        if ($user === false) {
            return null;
        }

        $token = self::generateToken();
        $expiresAt = time() + self::TTL_SECONDS;

        $payload = [
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'token_hash' => password_hash(self::normalize($token), PASSWORD_DEFAULT),
            'expires_at' => $expiresAt,
        ];

        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        if (@file_put_contents($path, $json, LOCK_EX) === false) {
            return null;
        }
        @chmod($path, 0600);

        return [
            'token' => $token,
            'username' => (string) $user['username'],
            'expires_at' => $expiresAt,
        ];
    }

    /** True when a usable (unexpired) token file exists. */
    public static function hasPending(): bool
    {
        $payload = self::read();
        return $payload !== null && (int) $payload['expires_at'] > time();
    }

    /**
     * Validate and burn a token.
     *
     * @return array{user_id: int, username: string}|null
     */
    public static function consume(string $token): ?array
    {
        $payload = self::read();
        if ($payload === null) {
            return null;
        }

        if ((int) $payload['expires_at'] <= time()) {
            self::clear();
            return null;
        }

        if (!password_verify(self::normalize($token), (string) $payload['token_hash'])) {
            return null;
        }

        self::clear();

        return [
            'user_id' => (int) $payload['user_id'],
            'username' => (string) $payload['username'],
        ];
    }

    /** Replace a user's password. Returns false when the user is gone. */
    public static function resetPassword(int $userId, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id AND is_active = 1');
        $stmt->execute([
            'h' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function clear(): void
    {
        $path = self::path();
        if (!is_file($path)) {
            return;
        }

        // Overwrite before unlinking: on Windows a locked file can refuse the
        // delete, and a leftover file with a still-valid hash would make the
        // token replayable. An empty payload fails read()'s shape check, so the
        // token is dead either way.
        @file_put_contents($path, '{}');
        @unlink($path);
        if (!is_file($path)) {
            return;
        }
        @file_put_contents($path, '{"revoked":true}');
    }

    /** @return array<string, mixed>|null */
    private static function read(): ?array
    {
        $path = self::path();
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) && isset($data['token_hash'], $data['user_id'], $data['expires_at']) ? $data : null;
    }

    /** Tokens are shown as 5 groups of 4; typing is forgiving about case and dashes. */
    private static function generateToken(): string
    {
        $raw = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 20; $i++) {
            $raw .= self::ALPHABET[random_int(0, $max)];
        }

        return implode('-', str_split($raw, 4));
    }

    private static function normalize(string $token): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $token));
    }
}

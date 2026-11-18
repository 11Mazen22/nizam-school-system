<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use RuntimeException;

/**
 * Nizam -- enrolment, verification and recovery for staff two-factor login.
 *
 * A user is only marked as protected after they prove a code from their own
 * authenticator. Recovery codes are single-use bcrypt hashes; losing a phone
 * should not mean losing the account, and a database dump should not hand
 * someone a second factor.
 */
final class MfaService
{
    private const RECOVERY_COUNT = 8;
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly TotpService $totp = new TotpService(),
    ) {
    }

    public function enabled(array $user): bool
    {
        return !empty($user['mfa_enabled_at']) && !empty($user['mfa_secret']);
    }

    /** @return array{secret:string, uri:string} */
    public function startEnrollment(string $username): array
    {
        $secret = $this->totp->generateSecret();

        return [
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri($secret, $username),
        ];
    }

    /**
     * Confirm the first generated code, then persist the secret and issue
     * recovery codes. Nothing is written if the code does not match, so a
     * mistyped setup leaves the account exactly as it was.
     *
     * @return list<string> recovery codes in plaintext, shown once
     */
    public function enable(int $userId, string $secret, string $code): array
    {
        if (!$this->totp->verify($secret, $code)) {
            throw new RuntimeException('invalid_code');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET mfa_secret = :secret, mfa_enabled_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute(['secret' => $secret, 'id' => $userId]);
            $pdo->prepare('DELETE FROM mfa_recovery_codes WHERE user_id = :id')->execute(['id' => $userId]);

            $codes = [];
            $insert = $pdo->prepare('INSERT INTO mfa_recovery_codes (user_id, code_hash) VALUES (:id, :hash)');
            for ($i = 0; $i < self::RECOVERY_COUNT; $i++) {
                $plain = $this->newRecoveryCode();
                $codes[] = $plain;
                $insert->execute(['id' => $userId, 'hash' => password_hash($this->normalizeRecoveryCode($plain), PASSWORD_DEFAULT)]);
            }

            $pdo->commit();
            ActivityLogger::log('mfa.enabled', 'users', $userId, 'Two-factor authentication enabled');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $codes;
    }

    /**
     * Turning protection off needs the password AND a current code: an
     * attacker who walks up to an unlocked browser must not be able to strip
     * the second factor with the session alone.
     */
    public function disable(int $userId, string $password, string $code): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, password_hash, mfa_secret, mfa_enabled_at FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        if ($user === false || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        if (!$this->verify($user, $code)) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->prepare('UPDATE users SET mfa_secret = NULL, mfa_enabled_at = NULL WHERE id = :id')
            ->execute(['id' => $userId]);
        $pdo->prepare('DELETE FROM mfa_recovery_codes WHERE user_id = :id')->execute(['id' => $userId]);
        ActivityLogger::log('mfa.disabled', 'users', $userId, 'Two-factor authentication disabled');

        return true;
    }

    /** TOTP first, then a single-use recovery code. */
    public function verify(array $user, string $code): bool
    {
        $code = trim($code);
        if ($code === '' || empty($user['mfa_secret'])) {
            return false;
        }
        if ($this->totp->verify((string) $user['mfa_secret'], $code)) {
            return true;
        }

        return $this->consumeRecoveryCode((int) $user['id'], $code);
    }

    private function consumeRecoveryCode(int $userId, string $code): bool
    {
        $normalized = $this->normalizeRecoveryCode($code);
        if (strlen($normalized) < 6) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, code_hash FROM mfa_recovery_codes WHERE user_id = :id AND used_at IS NULL'
        );
        $stmt->execute(['id' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            if (password_verify($normalized, (string) $row['code_hash'])) {
                $mark = Database::connection()->prepare(
                    'UPDATE mfa_recovery_codes SET used_at = CURRENT_TIMESTAMP WHERE id = :id AND used_at IS NULL'
                );
                $mark->execute(['id' => (int) $row['id']]);
                if ($mark->rowCount() === 1) {
                    ActivityLogger::log('mfa.recovery_used', 'users', $userId, 'A recovery code was used to sign in');
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
    }

    private function newRecoveryCode(): string
    {
        $raw = '';
        for ($i = 0; $i < 8; $i++) {
            $raw .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
        }

        return substr($raw, 0, 4) . '-' . substr($raw, 4);
    }
}

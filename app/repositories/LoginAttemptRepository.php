<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/**
 * Nizam -- backs §O-16's dual login throttle: per-(username,IP) AND a
 * secondary IP-wide threshold across different usernames, closing the
 * account-enumeration gap a per-username lock alone leaves open.
 */
final class LoginAttemptRepository
{
    public function record(string $username, string $ip, bool $success): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO login_attempts (username, ip_address, success) VALUES (:u, :ip, :s)'
        );
        $stmt->execute(['u' => $username, 'ip' => $ip, 's' => $success ? 1 : 0]);
    }

    public function recentFailedCountForUsernameAndIp(string $username, string $ip, int $minutes): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE username = :u AND ip_address = :ip AND success = 0
               AND attempted_at > (NOW() - INTERVAL :m MINUTE)'
        );
        $stmt->execute(['u' => $username, 'ip' => $ip, 'm' => $minutes]);
        return (int) $stmt->fetchColumn();
    }

    public function recentFailedCountForIp(string $ip, int $minutes): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = :ip AND success = 0
               AND attempted_at > (NOW() - INTERVAL :m MINUTE)'
        );
        $stmt->execute(['ip' => $ip, 'm' => $minutes]);
        return (int) $stmt->fetchColumn();
    }
}

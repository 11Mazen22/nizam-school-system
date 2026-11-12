<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use Throwable;

/**
 * Idempotency for replayed offline submissions.
 *
 * A school laptop that loses Wi-Fi mid-submit cannot know whether the request
 * arrived. The offline queue therefore resends with the same
 * X-Nizam-Queue-Id; the first arrival claims that key, and a later arrival
 * with the same key is answered 204 without running the handler again. This
 * is what stops a lost response from creating a second student, invoice or
 * payment.
 *
 * The key is claimed BEFORE the controller runs, so a submission that fails
 * validation also consumes its key. That trade-off is deliberate: the queue
 * only stores requests that never left the device, and the alternative --
 * claiming after success -- would need the response to be buffered and
 * replayed, which this app's redirect-and-exit controllers do not support.
 */
final class ReplayGuard
{
    /**
     * @return bool true when the caller should process the request,
     *              false when this key was already handled
     */
    public static function claim(PDO $pdo, string $key, ?int $userId = null): bool
    {
        // Malformed keys are ignored rather than rejected: a client bug must
        // not turn a legitimate form submission into an error.
        if (preg_match('/^[A-Za-z0-9-]{8,64}$/', $key) !== 1) {
            return true;
        }

        try {
            $statement = $pdo->prepare('INSERT INTO request_replays (replay_key, user_id) VALUES (:key, :user)');
            $statement->execute(['key' => $key, 'user' => $userId]);
        } catch (PDOException $error) {
            // 23000 (MySQL) / 23505 (PostgreSQL): duplicate key -> already
            // applied. Any other failure (e.g. the migration has not run on
            // an older install) must not block the school's data entry.
            if ($error->getCode() === '23000' || $error->getCode() === '23505') {
                return false;
            }
            return true;
        }

        // Opportunistic pruning; the table only needs to outlive realistic
        // retry windows, not the school year.
        if (random_int(1, 50) === 1) {
            try {
                $cutoff = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
                    ? "CURRENT_TIMESTAMP - INTERVAL '30 days'"
                    : 'NOW() - INTERVAL 30 DAY';
                $pdo->exec('DELETE FROM request_replays WHERE created_at < ' . $cutoff);
            } catch (Throwable) {
                // Pruning is housekeeping; never surface it to a user.
            }
        }

        return true;
    }
}

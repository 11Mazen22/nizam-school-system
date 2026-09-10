<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class ActivityLogRepository
{
    /** O-25: cap enforced here server-side, same discipline as StudentRepository::MAX_PER_PAGE. */
    public const MAX_PER_PAGE = 50;

    /**
     * §Q "Activity Log | List" and the Dashboard's "recent activity" widget
     * both need exactly this query (rows, most-recent-first, optionally
     * scoped to one user) -- this is the one place it's written. $onlyUserId
     * is the §J activity_log.view "own actions only" scoping for Staff;
     * Administrator (who holds the full-visibility half of that same
     * permission) passes null.
     *
     * @return array<int, array{id:int, action:string, entity_type:?string, entity_id:?int, description:?string, created_at:string, full_name:?string}>
     */
    public function paginate(int $limit, int $offset, ?int $onlyUserId): array
    {
        $limit = max(1, min($limit, self::MAX_PER_PAGE));
        $offset = max(0, $offset);

        $sql = 'SELECT a.id, a.action, a.entity_type, a.entity_id, a.description, a.created_at, u.full_name
                FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id';
        $params = [];
        if ($onlyUserId !== null) {
            $sql .= ' WHERE a.user_id = :uid';
            $params['uid'] = $onlyUserId;
        }
        $sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT :limit OFFSET :offset';

        $stmt = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Same $onlyUserId scoping as paginate() -- kept consistent so pagination math never disagrees with what was actually fetched. */
    public function count(?int $onlyUserId): int
    {
        $sql = 'SELECT COUNT(*) FROM activity_logs' . ($onlyUserId !== null ? ' WHERE user_id = :uid' : '');
        $stmt = Database::connection()->prepare($sql);
        if ($onlyUserId !== null) {
            $stmt->bindValue(':uid', $onlyUserId);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * user_id is a nullable FK to users.id -- normally always valid (it's
     * read straight from the current session), except right after a Phase 8
     * restore that didn't include the acting session's own user row. A
     * restore replaces the users table wholesale rather than deleting a
     * row through it, so the FK's own ON DELETE SET NULL never has a chance
     * to fire; checked explicitly here instead so a log call in that
     * specific window degrades to an anonymous entry instead of throwing
     * and masking whatever it was trying to record in the first place.
     */
    public function insert(?int $userId, string $action, ?string $entityType, ?int $entityId, ?string $description, array $metadata, string $ip): void
    {
        if ($userId !== null) {
            $exists = Database::connection()->prepare('SELECT 1 FROM users WHERE id = ?');
            $exists->execute([$userId]);
            if ($exists->fetchColumn() === false) {
                $userId = null;
            }
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, metadata, ip_address)
             VALUES (:user_id, :action, :entity_type, :entity_id, :description, :metadata, :ip)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => empty($metadata) ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'ip' => $ip,
        ]);
    }
}

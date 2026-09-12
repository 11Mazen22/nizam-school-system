<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class NotificationRepository
{
    /** Insert one notification row for a single user. Returns new id. */
    public function create(int $userId, string $titleEn, string $titleAr, string $bodyEn, string $bodyAr, ?string $link): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, title_en, title_ar, body_en, body_ar, link)
             VALUES (:uid, :ten, :tar, :ben, :bar, :lnk)'
        );
        $stmt->execute([
            'uid' => $userId,
            'ten' => $titleEn,
            'tar' => $titleAr,
            'ben' => $bodyEn,
            'bar' => $bodyAr,
            'lnk' => $link,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** Unread count for the bell badge. */
    public function unreadCount(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Latest N notifications for the dropdown (read + unread).
     * @return array<int, array<string,mixed>>
     */
    public function recent(int $userId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM notifications WHERE user_id = :uid
             ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Mark one notification read. */
    public function markRead(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    /** Mark every unread notification for a user as read. */
    public function markAllRead(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->execute(['uid' => $userId]);
    }

    /** IDs of all active admin users — used by broadcast helpers. */
    public function adminUserIds(): array
    {
        $stmt = Database::connection()->query(
            "SELECT u.id FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.code = 'admin' AND u.is_active = 1"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** IDs of all active users (admin + staff). */
    public function allActiveUserIds(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id FROM users WHERE is_active = 1'
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

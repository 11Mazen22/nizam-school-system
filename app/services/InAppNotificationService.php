<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;

/**
 * In-app notification engine.
 * Every service that wants to fire a notification calls this class.
 * The bell dropdown in the layout reads from the same notifications table.
 */
final class InAppNotificationService
{
    public function __construct(
        private readonly NotificationRepository $repo = new NotificationRepository(),
    ) {
    }

    /** Send to one specific user. */
    public function notify(int $userId, string $titleEn, string $titleAr, string $bodyEn, string $bodyAr, ?string $link = null): void
    {
        $this->repo->create($userId, $titleEn, $titleAr, $bodyEn, $bodyAr, $link);
    }

    /** Broadcast to all active admin users. */
    public function notifyAdmins(string $key, string $titleEn, string $titleAr, string $bodyEn, string $bodyAr, ?string $link = null): void
    {
        foreach ($this->repo->adminUserIds() as $uid) {
            $this->repo->create((int) $uid, $titleEn, $titleAr, $bodyEn, $bodyAr, $link);
        }
    }

    /** Broadcast to every active user. */
    public function notifyAll(string $titleEn, string $titleAr, string $bodyEn, string $bodyAr, ?string $link = null): void
    {
        foreach ($this->repo->allActiveUserIds() as $uid) {
            $this->repo->create((int) $uid, $titleEn, $titleAr, $bodyEn, $bodyAr, $link);
        }
    }

    public function unreadCount(int $userId): int
    {
        return $this->repo->unreadCount($userId);
    }

    public function recent(int $userId, int $limit = 20): array
    {
        return $this->repo->recent($userId, $limit);
    }

    public function markRead(int $id, int $userId): void
    {
        $this->repo->markRead($id, $userId);
    }

    public function markAllRead(int $userId): void
    {
        $this->repo->markAllRead($userId);
    }
}

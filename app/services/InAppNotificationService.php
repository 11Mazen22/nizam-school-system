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
    private const CATEGORIES = ['system', 'academic', 'welfare'];
    private const PRIORITIES = ['normal', 'high'];
    public function __construct(
        private readonly NotificationRepository $repo = new NotificationRepository(),
    ) {
    }

    /** Send to one specific user. */
    public function notify(int $userId, string $titleEn, string $titleAr, string $bodyEn, string $bodyAr, ?string $link = null, string $category = 'system', string $priority = 'normal'): void
    {
        $this->repo->create($userId, $titleEn, $titleAr, $bodyEn, $bodyAr, self::internalLink($link), self::category($category), self::priority($priority));
    }

    /** Broadcast to all active admin users. */
    public function notifyAdmins(string $key, string $titleEn, string $titleAr, string $bodyEn, string $bodyAr, ?string $link = null, string $category = 'system', string $priority = 'normal'): void
    {
        foreach ($this->repo->adminUserIds() as $uid) {
            $this->notify((int) $uid, $titleEn, $titleAr, $bodyEn, $bodyAr, $link, $category, $priority);
        }
    }

    /** Broadcast to every active user. */
    public function notifyAll(string $titleEn, string $titleAr, string $bodyEn, string $bodyAr, ?string $link = null, string $category = 'system', string $priority = 'normal'): void
    {
        foreach ($this->repo->allActiveUserIds() as $uid) {
            $this->notify((int) $uid, $titleEn, $titleAr, $bodyEn, $bodyAr, $link, $category, $priority);
        }
    }

    public function unreadCount(int $userId): int
    {
        return $this->repo->unreadCount($userId);
    }

    public function recent(int $userId, int $limit = 20, ?string $category = null): array
    {
        return $this->repo->recent($userId, $limit, $category);
    }

    public function markRead(int $id, int $userId): void
    {
        $this->repo->markRead($id, $userId);
    }

    public function findForUser(int $id, int $userId): ?array
    {
        return $this->repo->findForUser($id, $userId);
    }

    /** A notification must only ever navigate inside this application. */
    public static function internalLink(?string $link): ?string
    {
        if ($link === null || $link === '') {
            return null;
        }
        if (strlen($link) > 255 || !preg_match('~^/(?!/)[^\\\\\x00-\x1F]*$~D', $link)) {
            return null;
        }
        $parts = parse_url($link);
        return is_array($parts) && !isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass']) ? $link : null;
    }

    public static function category(?string $category): string
    {
        return self::isCategory($category) ? $category : 'system';
    }

    public static function isCategory(?string $category): bool
    {
        return in_array($category, self::CATEGORIES, true);
    }

    private static function priority(string $priority): string
    {
        return in_array($priority, self::PRIORITIES, true) ? $priority : 'normal';
    }

    public function markAllRead(int $userId): void
    {
        $this->repo->markAllRead($userId);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Request;
use App\Response;
use App\Services\InAppNotificationService;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly InAppNotificationService $notif = new InAppNotificationService(),
    ) {
    }

    /** GET /notifications — full notification inbox view. */
    public function index(Request $request): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $notifications = $this->notif->recent($userId, 50);
        $this->view('notifications/index', ['notifications' => $notifications]);
    }

    /** POST /notifications/{id}/read — mark one as read, return to referrer or /notifications. */
    public function markRead(Request $request): void
    {
        $id     = $request->paramInt('id') ?? 0;
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $this->notif->markRead($id, $userId);

        // If there's a link on this notification, redirect there; else back.
        $back = $_SERVER['HTTP_REFERER'] ?? '/notifications';
        $host = explode(':', $_SERVER['HTTP_HOST'] ?? '', 2)[0];
        if (parse_url($back, PHP_URL_HOST) !== $host) { $back = '/notifications'; }
        $this->redirect($back);
    }

    /** POST /notifications/read-all — mark all unread as read. */
    public function markAllRead(Request $request): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $this->notif->markAllRead($userId);
        $this->redirect('/notifications');
    }

    /**
     * GET /notifications/api — JSON endpoint for the bell dropdown.
     * Returns unread count + recent 10 notifications.
     */
    public function api(Request $request): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $locale = $_SESSION['locale'] ?? 'ar';
        $items  = $this->notif->recent($userId, 10);

        $mapped = array_map(static function (array $n) use ($locale): array {
            return [
                'id'         => (int) $n['id'],
                'title'      => $locale === 'ar' ? $n['title_ar'] : $n['title_en'],
                'body'       => $locale === 'ar' ? $n['body_ar']  : $n['body_en'],
                'link'       => $n['link'],
                'is_read'    => (bool) $n['is_read'],
                'created_at' => $n['created_at'],
            ];
        }, $items);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'unread' => $this->notif->unreadCount($userId),
            'items'  => $mapped,
        ]);
        exit;
    }
}

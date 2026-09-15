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
        $category = $request->query('category');
        $category = InAppNotificationService::isCategory($category) ? $category : null;
        $notifications = $this->notif->recent($userId, 50, $category);
        foreach ($notifications as &$notification) {
            $notification['link'] = InAppNotificationService::internalLink($notification['link']);
        }
        unset($notification);
        $this->view('notifications/index', [
            'notifications' => $notifications,
            'currentCategory' => $category,
        ]);
    }

    /** POST /notifications/{id}/read — mark one as read, return to referrer or /notifications. */
    public function markRead(Request $request): void
    {
        $id     = $request->paramInt('id') ?? 0;
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $notification = $this->notif->findForUser($id, $userId);
        if ($notification === null) {
            $this->redirect('/notifications');
            return;
        }
        $this->notif->markRead($id, $userId);

        if ($request->post('follow') === '1') {
            $link = InAppNotificationService::internalLink($notification['link']);
            if ($link !== null) {
                $this->redirect($link);
                return;
            }
        }

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
                'category'   => $n['category'] ?? 'system',
                'priority'   => $n['priority'] ?? 'normal',
                'title'      => $locale === 'ar' ? $n['title_ar'] : $n['title_en'],
                'body'       => $locale === 'ar' ? $n['body_ar']  : $n['body_en'],
                'link'       => InAppNotificationService::internalLink($n['link']),
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

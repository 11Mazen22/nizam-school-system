<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Repositories\ActivityLogRepository;
use App\Request;

/**
 * Nizam -- §Q screen inventory: "Activity Log | List". Gated on
 * activity_log.view (§J), which both roles hold -- the scope difference
 * ("own actions only" for Staff, everything for Administrator) is a role
 * check, not a second permission code, because §J itself defines it as one
 * code with role-dependent scope rather than two codes.
 */
final class ActivityLogController extends Controller
{
    public function __construct(private readonly ActivityLogRepository $logs = new ActivityLogRepository())
    {
    }

    public function index(Request $request): void
    {
        $onlyUserId = ($_SESSION['role_code'] ?? null) === 'admin' ? null : (int) ($_SESSION['user_id'] ?? 0);

        $perPage = ActivityLogRepository::MAX_PER_PAGE;
        $total = $this->logs->count($onlyUserId);
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min(max(1, (int) ($request->query('page', '1') ?: 1)), $lastPage);

        $this->view('activity-log/index', [
            'entries' => $this->logs->paginate($perPage, ($page - 1) * $perPage, $onlyUserId),
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
            'scopedToSelf' => $onlyUserId !== null,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ActivityLogRepository;

/**
 * Nizam -- activity-log foundation (§C: "ActivityLogger, called by every
 * service" -- §A: "every consequential action... recorded with actor,
 * entity, and timestamp"). Phase 4 wires this up and uses it for the one
 * consequential action Phase 4 itself performs (login/logout); every later
 * phase's services call the same static log() rather than writing to
 * activity_logs directly.
 */
final class ActivityLogger
{
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null, array $metadata = []): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        (new ActivityLogRepository())->insert($userId, $action, $entityType, $entityId, $description, $metadata, $ip);
    }
}

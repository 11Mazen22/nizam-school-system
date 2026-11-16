<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Request;
use App\Services\NotificationService;
use App\Services\ScheduledTaskService;
use App\Services\BackupService;

/**
 * Controller for scheduled tasks triggered by cron jobs or GitHub Actions
 * All endpoints require Bearer token authentication
 */
final class ScheduledController extends Controller
{
    private function authenticate(Request $request): bool
    {
        // The token lives in the SCHEDULED_BACKUP_TOKEN environment variable
        // on the online deployment (packaging-online/entrypoint.sh never
        // writes config.php's 'scheduled_backup_token' key, and the container
        // has no hand-edited config file to read it from) -- the offline
        // Windows/Scheduled-Task target instead keeps it in config/config.php
        // like every other per-installation setting. Read the env var first,
        // fall back to the file; both go through the same timing-safe guard,
        // which also rejects the placeholder tokens published in this repo.
        $expectedToken = getenv('SCHEDULED_BACKUP_TOKEN');
        if (!is_string($expectedToken) || $expectedToken === '') {
            $configPath = dataPath() . '/config/config.php';
            if (!is_file($configPath)) {
                return false;
            }
            /** @var mixed $config */
            $config = require $configPath;
            $expectedToken = is_array($config) ? ($config['scheduled_backup_token'] ?? null) : null;
        }

        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }

        $token = substr($authHeader, 7);
        // Rejects empty values AND the placeholders published in this repo,
        // so a school that never edited config has these endpoints closed
        // rather than protected by a publicly known string.
        return \App\Services\ScheduledTokenGuard::isValid(is_string($expectedToken) ? $expectedToken : null, $token);
    }

    /**
     * Run daily birthday checks and notifications
     * Called by: GitHub Actions (daily at 08:00)
     */
    public function checkBirthdays(Request $request): void
    {
        if (!$this->authenticate($request)) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }
        
        $notifications = new NotificationService();
        $count = $notifications->checkBirthdays();
        
        $this->jsonResponse([
            'success' => true,
            'birthdays_found' => $count,
            'time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Run daily year rollover reminder check
     * Called by: GitHub Actions (daily at 09:00)
     */
    public function checkYearRollover(Request $request): void
    {
        if (!$this->authenticate($request)) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }
        
        $notifications = new NotificationService();
        $sent = $notifications->checkYearRolloverReminders();
        
        $this->jsonResponse([
            'success' => true,
            'reminders_sent' => $sent,
            'time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Run weekly database cleanup
     * Called by: GitHub Actions (weekly on Sunday at 03:00)
     */
    public function runCleanup(Request $request): void
    {
        if (!$this->authenticate($request)) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }
        
        $cleanup = new ScheduledTaskService();
        $results = $cleanup->runAllCleanupTasks();
        
        $this->jsonResponse([
            'success' => true,
            'results' => $results,
            'time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Test notification system
     * Called by: Admin manually to verify email setup
     */
    public function testNotification(Request $request): void
    {
        if (!$this->authenticate($request)) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }
        
        $email = $request->post('email', '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(['error' => 'Invalid email'], 400);
            return;
        }
        
        $notifications = new NotificationService();
        $sent = $notifications->sendTestNotification($email);
        
        $this->jsonResponse([
            'success' => $sent,
            'message' => $sent ? 'Test email sent' : 'Failed to send email',
        ]);
    }

    /**
     * Helper method for JSON responses
     */
    private function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

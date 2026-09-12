<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Service for running scheduled maintenance and cleanup tasks
 */
final class ScheduledTaskService
{
    /**
     * Clean up old activity logs (keep last 90 days)
     */
    public function cleanupActivityLogs(int $daysToKeep = 90): int
    {
        $pdo = Database::connection();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        $stmt = $pdo->prepare("
            DELETE FROM activity_logs
            WHERE created_at < :cutoff
        ");
        
        $stmt->execute(['cutoff' => $cutoffDate]);
        return $stmt->rowCount();
    }

    /**
     * Clean up old failed backup records (keep last 30 days)
     */
    public function cleanupFailedBackups(int $daysToKeep = 30): int
    {
        $pdo = Database::connection();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        $stmt = $pdo->prepare("
            DELETE FROM backups
            WHERE status = 'failed' AND created_at < :cutoff
        ");
        
        $stmt->execute(['cutoff' => $cutoffDate]);
        return $stmt->rowCount();
    }

    /**
     * Clear expired login lockouts. locked_until lives on users (set by the
     * login-throttle check on repeated failures), not login_attempts (which
     * only ever logs individual attempts, no lockout state of its own).
     */
    public function cleanupExpiredLockouts(): int
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            UPDATE users
            SET locked_until = NULL
            WHERE locked_until IS NOT NULL AND locked_until < :now
        ");

        $stmt->execute(['now' => $now]);
        return $stmt->rowCount();
    }

    /**
     * Clean up old session data (if stored in database)
     */
    public function cleanupOldSessions(int $hoursToKeep = 24): int
    {
        // This assumes sessions are stored in database
        // If using file-based sessions, this can be skipped
        $pdo = Database::connection();
        
        try {
            $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hoursToKeep} hours"));
            
            $stmt = $pdo->prepare("
                DELETE FROM sessions
                WHERE last_activity < :cutoff
            ");
            
            $stmt->execute(['cutoff' => $cutoffTime]);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            // Table might not exist if using file sessions
            return 0;
        }
    }

    /**
     * Archive old academic years (close years older than 2 years that are still open)
     */
    public function autoCloseOldYears(): int
    {
        $pdo = Database::connection();
        $cutoffDate = date('Y-m-d', strtotime('-2 years'));
        
        // Only close years that have no active enrollments
        $stmt = $pdo->prepare("
            UPDATE academic_years
            SET is_closed = 1
            WHERE end_date < :cutoff
            AND is_closed = 0
            AND is_active = 0
            AND NOT EXISTS (
                SELECT 1 FROM student_enrollments e
                WHERE e.academic_year_id = academic_years.id
                AND e.status = 'active'
            )
        ");
        
        $stmt->execute(['cutoff' => $cutoffDate]);
        return $stmt->rowCount();
    }

    /**
     * Run all cleanup tasks
     */
    public function runAllCleanupTasks(): array
    {
        return [
            'activity_logs_deleted' => $this->cleanupActivityLogs(),
            'failed_backups_deleted' => $this->cleanupFailedBackups(),
            'expired_lockouts_cleared' => $this->cleanupExpiredLockouts(),
            'old_sessions_deleted' => $this->cleanupOldSessions(),
            'old_years_closed' => $this->autoCloseOldYears(),
        ];
    }
}

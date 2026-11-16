<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use RuntimeException;

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
     * Delete successful backup files older than the retention window, while
     * always preserving at least the seven newest successful snapshots even
     * if a deployment was offline long enough for all of them to age past the
     * cutoff. Metadata is deleted only after the object/file delete succeeds;
     * an unavailable storage provider therefore leaves a visible retryable
     * row instead of silently orphaning the backup.
     */
    public function cleanupOldSuccessfulBackups(int $daysToKeep = 30, int $minimumToKeep = 7): int
    {
        $pdo = Database::connection();
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        $keep = $pdo->prepare(
            "SELECT id FROM backups WHERE status = 'success' ORDER BY created_at DESC, id DESC LIMIT :limit"
        );
        $keep->bindValue('limit', max(1, $minimumToKeep), PDO::PARAM_INT);
        $keep->execute();
        $keepIds = array_map('intval', $keep->fetchAll(PDO::FETCH_COLUMN));

        $candidates = $pdo->prepare(
            "SELECT id, filename FROM backups WHERE status = 'success' AND created_at < :cutoff ORDER BY id"
        );
        $candidates->execute(['cutoff' => $cutoff]);

        $backups = new BackupService();
        $deleted = 0;
        $deleteRow = $pdo->prepare('DELETE FROM backups WHERE id = :id');
        foreach ($candidates->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['id'];
            if (in_array($id, $keepIds, true)) {
                continue;
            }
            try {
                $backups->deleteStored((string) $row['filename']);
            } catch (RuntimeException $e) {
                error_log('[Hadaba] Backup retention could not delete ' . basename((string) $row['filename']) . ': ' . $e->getMessage());
                continue;
            }
            $deleteRow->execute(['id' => $id]);
            $deleted += $deleteRow->rowCount();
        }

        return $deleted;
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

    /** Remove expired PHP file sessions from the mutable data root. */
    public function cleanupOldSessions(int $hoursToKeep = 24): int
    {
        $directory = dataPath() . '/storage/sessions';
        if (!is_dir($directory)) {
            return 0;
        }

        $cutoff = time() - (max(1, $hoursToKeep) * 3600);
        $deleted = 0;
        foreach (glob($directory . '/sess_*') ?: [] as $file) {
            if (is_file($file) && (int) filemtime($file) < $cutoff && @unlink($file)) {
                $deleted++;
            }
        }
        return $deleted;
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
            'expired_backups_deleted' => $this->cleanupOldSuccessfulBackups(),
            'expired_lockouts_cleared' => $this->cleanupExpiredLockouts(),
            'old_sessions_deleted' => $this->cleanupOldSessions(),
            'old_years_closed' => $this->autoCloseOldYears(),
        ];
    }
}

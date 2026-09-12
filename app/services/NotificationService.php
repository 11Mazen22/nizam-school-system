<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Central notification service that handles all automated notifications
 */
final class NotificationService
{
    private EmailService $email;

    public function __construct(
        private readonly EmailService $emailService = new EmailService()
    ) {
        $this->email = $emailService;
    }

    /**
     * Check and send birthday notifications for today
     */
    public function checkBirthdays(): int
    {
        $pdo = Database::connection();
        $today = date('m-d'); // Format: 12-25 for December 25
        
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.student_code,
                s.full_name,
                s.date_of_birth,
                g.name_en as grade_name,
                c.name as class_name
            FROM students s
            LEFT JOIN student_enrollments e ON s.id = e.student_id AND e.status = 'active'
            LEFT JOIN classes c ON e.class_id = c.id
            LEFT JOIN grades g ON c.grade_id = g.id
            WHERE s.status = 'active'
            AND DATE_FORMAT(s.date_of_birth, '%m-%d') = :today
        ");
        
        $stmt->execute(['today' => $today]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($students as $student) {
            $this->email->sendBirthdayNotification($student);
        }
        
        return count($students);
    }

    /**
     * Check and send year rollover reminders
     */
    public function checkYearRolloverReminders(): int
    {
        $pdo = Database::connection();
        
        // Get active academic year
        $stmt = $pdo->query("
            SELECT id, label, start_date, end_date
            FROM academic_years
            WHERE is_active = 1 AND is_closed = 0
            LIMIT 1
        ");
        
        $year = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$year) {
            return 0;
        }
        
        $endDate = new \DateTime($year['end_date']);
        $today = new \DateTime();
        $daysUntilEnd = $today->diff($endDate)->days;
        
        // Send reminders at 60, 30, 14, and 7 days before end
        $reminderDays = [60, 30, 14, 7];
        
        if (in_array($daysUntilEnd, $reminderDays)) {
            $this->email->sendYearRolloverReminder($year, $daysUntilEnd);
            return 1;
        }
        
        return 0;
    }

    /**
     * Notify about backup completion
     */
    public function notifyBackupComplete(bool $success, string $filename, ?string $error = null): void
    {
        $this->email->sendBackupNotification($success, $filename, $error);
    }

    /**
     * Notify about report generation
     */
    public function notifyReportGenerated(string $reportName, string $downloadUrl, string $generatedBy): void
    {
        $this->email->sendReportGeneratedNotification($reportName, $downloadUrl, $generatedBy);
    }

    /**
     * Send test notification to verify email setup
     */
    public function sendTestNotification(string $email): bool
    {
        return $this->email->send(
            $email,
            'Hadaba Al-Ahram School — Email Test',
            '<h2>✅ Email Configuration Working!</h2><p>Hadaba Al-Ahram School management system can send emails successfully.</p>',
            true
        );
    }
}

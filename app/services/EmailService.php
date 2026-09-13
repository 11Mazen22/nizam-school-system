<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Email notification service using PHP's mail() function or SMTP.
 * 
 * IMPORTANT: Current implementation uses PHP's mail() function which will
 * NOT work on Railway/cloud hosting (no MTA configured, cloud IPs get spam-filtered).
 * For production email delivery, you MUST configure SMTP credentials in config.php:
 * 
 * 'email' => [
 *     'enabled' => true,
 *     'smtp_host' => 'smtp.gmail.com',
 *     'smtp_port' => 587,
 *     'smtp_user' => 'your-email@gmail.com',
 *     'smtp_password' => 'your-app-password',  // NOT your Gmail password!
 * ]
 * 
 * Then install PHPMailer: composer require phpmailer/phpmailer
 * And replace the send() method implementation below with PHPMailer SMTP.
 * 
 * Until SMTP is configured, automation workflows (birthdays, year reminders)
 * will RUN their checks correctly but emails will NOT be delivered.
 */
final class EmailService
{
    private bool $enabled;
    private string $fromEmail;
    private string $fromName;
    private ?string $smtpHost;
    private ?int $smtpPort;
    private ?string $smtpUser;
    private ?string $smtpPassword;

    public function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        
        $this->enabled = $config['email']['enabled'] ?? false;
        $this->fromEmail = $config['email']['from_email'] ?? 'noreply@school.local';
        $this->fromName = $config['email']['from_name'] ?? 'Hadaba Al-Ahram Language School';
        $this->smtpHost = $config['email']['smtp_host'] ?? null;
        $this->smtpPort = $config['email']['smtp_port'] ?? null;
        $this->smtpUser = $config['email']['smtp_user'] ?? null;
        $this->smtpPassword = $config['email']['smtp_password'] ?? null;
    }

    /**
     * Send an email notification
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        if (!$this->enabled) {
            // Silently skip if email is disabled
            return true;
        }

        // For now, use simple mail() function
        // TODO: Implement full SMTP support with PHPMailer or similar
        $headers = [
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: PHP/' . phpversion(),
        ];

        if ($isHtml) {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Send notification to admin users
     */
    public function notifyAdmins(string $subject, string $body): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->query("
            SELECT u.email, u.full_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE r.code = 'admin' AND u.is_active = 1 AND u.email IS NOT NULL AND u.email != ''
        ");
        
        while ($admin = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $personalizedBody = str_replace('{name}', $admin['full_name'], $body);
            $this->send($admin['email'], $subject, $personalizedBody);
        }
    }

    /**
     * Send birthday notification
     */
    public function sendBirthdayNotification(array $student): void
    {
        $subject = __('email.birthday_subject', ['name' => $student['full_name']]);
        $body = $this->renderTemplate('birthday', [
            'student_name' => $student['full_name'],
            'student_code' => $student['student_code'],
            'grade' => $student['grade_name'],
            'class' => $student['class_name'],
        ]);

        // Send to admins and staff
        $this->notifyAdmins($subject, $body);
    }

    /**
     * Send year rollover reminder
     */
    public function sendYearRolloverReminder(array $currentYear, int $daysUntilEnd): void
    {
        $subject = __('email.year_ending_subject', ['days' => $daysUntilEnd]);
        $body = $this->renderTemplate('year_rollover', [
            'year_label' => $currentYear['label'],
            'days_remaining' => $daysUntilEnd,
            'end_date' => $currentYear['end_date'],
        ]);

        $this->notifyAdmins($subject, $body);
    }

    /**
     * Send backup notification
     */
    public function sendBackupNotification(bool $success, string $filename, ?string $error = null): void
    {
        $subject = $success 
            ? __('email.backup_success_subject')
            : __('email.backup_failed_subject');
        
        $body = $this->renderTemplate('backup', [
            'success' => $success,
            'filename' => $filename,
            'error' => $error,
            'time' => date('Y-m-d H:i:s'),
        ]);

        $this->notifyAdmins($subject, $body);
    }

    /**
     * Send report generation notification
     */
    public function sendReportGeneratedNotification(string $reportName, string $downloadUrl, string $generatedBy): void
    {
        $subject = __('email.report_ready_subject', ['report' => $reportName]);
        $body = $this->renderTemplate('report', [
            'report_name' => $reportName,
            'download_url' => $downloadUrl,
            'generated_by' => $generatedBy,
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->notifyAdmins($subject, $body);
    }

    /**
     * Render an email template
     */
    private function renderTemplate(string $template, array $data): string
    {
        $templatePath = dirname(__DIR__, 2) . "/views/emails/{$template}.php";
        
        if (!file_exists($templatePath)) {
            // Fallback to simple text template
            return $this->renderSimpleTemplate($template, $data);
        }

        ob_start();
        extract($data);
        require $templatePath;
        return ob_get_clean();
    }

    /**
     * Simple text template fallback
     */
    private function renderSimpleTemplate(string $template, array $data): string
    {
        switch ($template) {
            case 'birthday':
                return "
                    <h2>🎂 Student Birthday Today!</h2>
                    <p><strong>{$data['student_name']}</strong> (Code: {$data['student_code']}) is celebrating their birthday today!</p>
                    <p>Grade: {$data['grade']} - Class: {$data['class']}</p>
                    <p>Consider sending them a birthday wish!</p>
                ";

            case 'year_rollover':
                return "
                    <h2>⚠️ Academic Year Ending Soon</h2>
                    <p>The current academic year <strong>{$data['year_label']}</strong> will end in <strong>{$data['days_remaining']} days</strong>.</p>
                    <p>End Date: {$data['end_date']}</p>
                    <p>Please prepare for:</p>
                    <ul>
                        <li>Student promotions</li>
                        <li>Creating the next academic year</li>
                        <li>Year rollover process</li>
                    </ul>
                ";

            case 'backup':
                if ($data['success']) {
                    return "
                        <h2>✅ Backup Completed Successfully</h2>
                        <p>A database backup was created successfully.</p>
                        <p><strong>Filename:</strong> {$data['filename']}</p>
                        <p><strong>Time:</strong> {$data['time']}</p>
                    ";
                } else {
                    return "
                        <h2>❌ Backup Failed</h2>
                        <p>The scheduled database backup failed.</p>
                        <p><strong>Error:</strong> {$data['error']}</p>
                        <p><strong>Time:</strong> {$data['time']}</p>
                        <p>Please check the system logs and try again.</p>
                    ";
                }

            case 'report':
                return "
                    <h2>📊 Report Generated</h2>
                    <p>The report <strong>{$data['report_name']}</strong> has been generated.</p>
                    <p><strong>Generated by:</strong> {$data['generated_by']}</p>
                    <p><strong>Time:</strong> {$data['generated_at']}</p>
                    <p><a href='{$data['download_url']}'>Download Report</a></p>
                ";

            default:
                return "Notification from Hadaba Al-Ahram Language School";
        }
    }
}

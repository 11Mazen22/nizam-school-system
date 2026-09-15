<?php

require __DIR__ . "/../app/bootstrap.php";

use App\Database;
use App\Services\EmailService;
use App\Services\IntelligenceService;

echo "Running Automated Weekly Digest...\n";

// Gather intelligence
$activeYear = (new \App\Repositories\AcademicYearRepository())->findActive();
$insights = new IntelligenceService();
$atRisk = $activeYear ? $insights->getAtRiskStudents(10, (int)$activeYear['id']) : [];

$html = "<h2>Weekly School Intelligence Digest</h2>";
$html .= "<p>Here is your automated report for the week.</p>";
$html .= "<h3>At-Risk Students (High Absence & Discipline)</h3><ul>";
foreach ($atRisk as $student) {
    $html .= "<li><strong>{$student["full_name"]}</strong> - {$student["class_name"]} (Absences: {$student["total_absences"]}, Incidents: {$student["total_incidents"]})</li>";
}
$html .= "</ul>";

$emailService = new EmailService();
$sent = $emailService->send(
    "admin@hadaba.local",
    "Hadaba Al-Ahram School - Weekly Intelligence Digest",
    $html,
    true
);

if ($sent) {
    echo "Digest sent successfully via EmailService!\n";
} else {
    echo "Failed to send digest. Ensure SMTP is configured.\n";
}


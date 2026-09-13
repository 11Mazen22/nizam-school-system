<?php

require __DIR__ . "/../vendor/autoload.php";
$config = require __DIR__ . "/../config.php";
\App\Database::init($config["database"]);

use App\Database;
use App\Services\EmailService;
use App\Services\IntelligenceService;

echo "Running Automated Weekly Digest...\n";

// Gather intelligence
$insights = new IntelligenceService();
$atRisk = $insights->getAtRiskStudents(10);

$html = "<h2>Weekly School Intelligence Digest</h2>";
$html .= "<p>Here is your automated report for the week.</p>";
$html .= "<h3>At-Risk Students (High Absence & Discipline)</h3><ul>";
foreach ($atRisk as $student) {
    $html .= "<li><strong>{$student["full_name"]}</strong> - {$student["class_name"]} (Absences: {$student["total_absences"]}, Incidents: {$student["total_incidents"]})</li>";
}
$html .= "</ul>";

$emailService = new EmailService($config);
$sent = $emailService->send(
    $config["email"]["from_email"] ?? "admin@hadaba.local",
    "Hadaba Al-Ahram School - Weekly Intelligence Digest",
    $html,
    true
);

if ($sent) {
    echo "Digest sent successfully via EmailService!\n";
} else {
    echo "Failed to send digest. Ensure SMTP is configured.\n";
}


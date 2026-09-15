<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

$pdo = App\Database::connection();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('This fixture requires MySQL/MariaDB');
}
$pdo->exec('CREATE TEMPORARY TABLE users (id INT PRIMARY KEY, is_active TINYINT, role_id INT)');
$pdo->exec('CREATE TEMPORARY TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, category VARCHAR(50) NOT NULL,
    priority VARCHAR(20) NOT NULL, title_en VARCHAR(150), title_ar VARCHAR(150),
    body_en TEXT, body_ar TEXT, link VARCHAR(255) NULL, is_read TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)');
$pdo->exec('INSERT INTO users VALUES (1,1,1),(2,1,2)');

$service = new App\Services\InAppNotificationService();
$service->notify(1, 'Unsafe', 'غير آمن', 'Body', 'النص', 'https://example.invalid', 'wrong', 'wrong');
$unsafe = $service->findForUser(1, 1);
if ($unsafe['link'] !== null || $unsafe['category'] !== 'system' || $unsafe['priority'] !== 'normal') {
    throw new LogicException('Unsafe notification values were retained');
}
$service->notify(1, 'Safe', 'آمن', 'Body', 'النص', '/students/1?tab=attendance', 'academic', 'high');
$safe = $service->findForUser(2, 1);
if ($safe['link'] !== '/students/1?tab=attendance' || $safe['category'] !== 'academic' || $safe['priority'] !== 'high') {
    throw new LogicException('Safe notification values were changed');
}
$service->markRead(2, 1);
if (!(bool) $service->findForUser(2, 1)['is_read'] || $service->findForUser(2, 2) !== null) {
    throw new LogicException('Read state or ownership check failed');
}
echo "PASS: internal links, category/priority normalization, owner-only read state\n";

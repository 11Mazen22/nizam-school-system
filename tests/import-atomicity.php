<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

$pdo = App\Database::connection();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('This fixture requires MySQL/MariaDB');
}
$pdo->exec('CREATE TEMPORARY TABLE grades (id INT AUTO_INCREMENT PRIMARY KEY, name_en VARCHAR(60), name_ar VARCHAR(60), sort_order INT, is_active TINYINT)');
$pdo->exec('CREATE TEMPORARY TABLE activity_logs (user_id INT, action VARCHAR(100), entity_type VARCHAR(100), entity_id INT, description TEXT, metadata TEXT, ip_address VARCHAR(64))');
$method = new ReflectionMethod(App\Controllers\ImportController::class, 'getEntityConfig');
$config = $method->invoke(new App\Controllers\ImportController(), 'grades');
$rows = [
    ['Name (English)' => 'Fixture Grade', 'Name (Arabic)' => 'صف تجريبي', 'Sort Order' => '1'],
    ['Name (English)' => '', 'Name (Arabic)' => 'غير صالح', 'Sort Order' => '2'],
];
$pdo->beginTransaction();
$errors = 0;
foreach ($rows as $row) {
    try {
        $config['process']($row);
    } catch (Throwable) {
        $errors++;
    }
}
if ($errors === 0) {
    throw new LogicException('Invalid row was accepted');
}
$pdo->rollBack();
if ((int) $pdo->query('SELECT COUNT(*) FROM grades')->fetchColumn() !== 0) {
    throw new LogicException('Invalid import left partial data');
}
echo "PASS: invalid import batch rolls back every prior row\n";

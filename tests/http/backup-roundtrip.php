<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('NIZAM_HTTP_TEST') !== '1') {
    fwrite(STDERR, "This fixture only runs inside tests/http/run.php.\n");
    exit(2);
}

require dirname(__DIR__, 2) . '/app/bootstrap.php';
restore_exception_handler();

use App\Database;
use App\Services\BackupService;
use App\Services\MigrationService;
use App\Services\RestoreService;

$pdo = Database::connection();
$backups = new BackupService();
$backup = $backups->run('manual', null, 'HTTP pipeline round-trip');
$path = BackupService::directory() . '/' . $backup['filename'];
$tampered = $path . '.tampered';

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $check(is_file($path) && filesize($path) > 1000, 'backup_file_missing');
    $verified = $backups->verify($path);
    $check(
        $verified['schemaVersion'] === count((new MigrationService())->getAppliedMigrations($pdo)),
        'schema_version_mismatch'
    );
    $check(str_starts_with($backups->retrieve($backup['filename']), '-- NIZAM-BACKUP'), 'retrieve_failed');

    copy($path, $tampered);
    file_put_contents($tampered, "tampered\n", FILE_APPEND);
    try {
        $backups->verify($tampered);
        throw new RuntimeException('tampered_backup_accepted');
    } catch (RuntimeException $e) {
        $check(in_array($e->getMessage(), ['invalid_signature', 'checksum_mismatch'], true), 'wrong_tamper_error');
    }

    $pdo->exec("INSERT INTO settings (setting_key, value, value_type) VALUES ('test.restore.marker', 'remove-me', 'string')");
    (new RestoreService())->execute($path, null);

    $marker = $pdo->query("SELECT value FROM settings WHERE setting_key = 'test.restore.marker'")->fetchColumn();
    $check($marker === false, 'restore_did_not_revert_marker');
    $activeAdmin = $pdo->query(
        "SELECT 1 FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.code = 'admin' AND u.is_active = 1 LIMIT 1"
    )->fetchColumn();
    $check($activeAdmin !== false, 'restore_removed_admin');

    fwrite(STDOUT, "PASS: backup and restore round-trip\n");
} finally {
    @unlink($tampered);
}

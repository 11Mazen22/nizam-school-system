<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();
$pdo = App\Database::connection();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('This fixture requires MySQL/MariaDB');
}
foreach ([
    'users' => 'id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(80) UNIQUE, full_name TEXT, email TEXT, role_id INT, is_active INT, password_hash TEXT',
    'roles' => 'id INT, code VARCHAR(30)',
    'permissions' => 'id INT, code VARCHAR(60)',
    'role_permissions' => 'role_id INT, permission_id INT',
    'activity_logs' => 'user_id INT, action TEXT, entity_type TEXT, entity_id INT, description TEXT, metadata TEXT, ip_address TEXT',
] as $table => $columns) {
    $pdo->exec("CREATE TEMPORARY TABLE {$table} ({$columns}) ENGINE=InnoDB");
}
$pdo->exec("INSERT INTO users VALUES (1,'test','Test',NULL,1,1,'unchanged')");
$pdo->exec("INSERT INTO roles VALUES (1,'admin'),(2,'staff')");
$pdo->exec("INSERT INTO permissions VALUES (1,'users.manage')");
$pdo->exec('INSERT INTO role_permissions VALUES (1,1)');
$_SESSION = ['user_id' => 1, 'permissions' => []];
$config = (new ReflectionMethod(App\Controllers\ImportController::class, 'getEntityConfig'))
    ->invoke(new App\Controllers\ImportController(), 'users');
$file = tempnam(sys_get_temp_dir(), 'school-user-import-');
try {
    file_put_contents($file, "Username,Password,Full Name,Role\nfixture_import,Fixture-Only-91,Imported Name,staff\n");
    $rows = (new App\Services\ImportService())->parseFile($file, $config['headers']);
    $config['process']($rows[0]);
    $user = $pdo->query("SELECT * FROM users WHERE username='fixture_import'")->fetch();
    if ($user['full_name'] !== 'Imported Name' || (int)$user['role_id'] !== 2 || !password_verify('Fixture-Only-91', $user['password_hash'])) {
        throw new LogicException('Imported user mapping or password hashing incorrect');
    }
    $before = $pdo->query('SELECT * FROM users WHERE id=1')->fetch();
    try {
        (new App\Services\UserService())->update(1,'changed','Changed','admin','short');
        throw new LogicException('Short password accepted');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'password_too_short') throw $e;
    }
    if ($before !== $pdo->query('SELECT * FROM users WHERE id=1')->fetch()) throw new LogicException('Invalid update changed profile');
} finally { unlink($file); }
$guard = App\Middleware\RoleGuardMiddleware::requiresAuth();
$request = new App\Request();
if (!$guard->handle($request) || $_SESSION['permissions'] !== ['users.manage']) {
    throw new LogicException('Current permission not loaded');
}
$pdo->exec('UPDATE users SET role_id=2 WHERE id=1');
if (!$guard->handle($request) || $_SESSION['permissions'] !== [] || $_SESSION['role_code'] !== 'staff') {
    throw new LogicException('Revoked permission survived in session');
}
// The archived-user branch redirects and exits; verify the session at shutdown.
$pdo->exec('UPDATE users SET is_active=0 WHERE id=1');
register_shutdown_function(static function (): void {
    if ($_SESSION !== []) {
        fwrite(STDERR, "FAIL: archived account retained session\n");
        exit(1);
    }
    echo "PASS: CSV user mapping/hash, invalid update unchanged, grants, role revocation, archived session invalidation\n";
});
$guard->handle($request);
throw new LogicException('Archived user was allowed');

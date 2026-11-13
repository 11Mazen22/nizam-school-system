<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * HTTP-level regression suite.
 *
 * Every other test in tests/ calls repository and service classes directly.
 * None of them exercises the request pipeline: routing, session boot, CSRF,
 * role guards, module gates, controller validation, flash messages and
 * redirects. That is the layer where a module can be "implemented and
 * tested" and still be unusable in a browser.
 *
 * This suite boots the real application under PHP's built-in server against a
 * throwaway MySQL database and a throwaway data path (NIZAM_DATA_PATH), then
 * talks to it with a cookie jar exactly like a browser would. Nothing in the
 * real school database is read, written or deleted.
 *
 * It reports BLOCKED (exit 2) rather than PASS when the environment cannot
 * support it, so a skipped run can never be mistaken for a green one.
 */

$root = dirname(__DIR__, 2);
require $root . '/app/bootstrap.php';
restore_exception_handler();

function http_out(string $line = ''): void
{
    fwrite(STDOUT, $line . "\n");
}

$passed = 0;
$failed = 0;
$skipped = 0;
$failures = [];

function ok(bool $condition, string $label): void
{
    global $passed, $failed, $failures;
    if ($condition) {
        $passed++;
        http_out('  ok    ' . $label);
        return;
    }
    $failed++;
    $failures[] = $label;
    http_out('  FAIL  ' . $label);
}

function same(mixed $expected, mixed $actual, string $label): void
{
    $match = $expected === $actual;
    ok($match, $match ? $label : $label . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

function skipped(string $label): void
{
    global $skipped;
    $skipped++;
    http_out('  SKIP  ' . $label);
}

/**
 * A single simulated browser: one cookie jar, no redirect following.
 */
final class Browser
{
    /** @var array<string,string> */
    private array $cookies = [];

    public function __construct(private readonly string $base, private readonly string $label)
    {
    }

    public function label(): string
    {
        return $this->label;
    }

    /** @return array{status:int, location:?string, body:string, headers:array<int,string>} */
    public function get(string $path, array $headers = []): array
    {
        return $this->send('GET', $path, null, $headers);
    }

    /** @return array{status:int, location:?string, body:string, headers:array<int,string>} */
    public function post(string $path, array $data, array $headers = []): array
    {
        return $this->send('POST', $path, http_build_query($data), $headers);
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /** First CSRF token rendered on a page, in either attribute order. */
    public function csrf(string $html): ?string
    {
        if (preg_match('/<input[^>]*name="([^"]*csrf[^"]*)"[^>]*value="([^"]+)"/i', $html, $m)) {
            return $m[2];
        }
        if (preg_match('/<input[^>]*value="([^"]+)"[^>]*name="([^"]*csrf[^"]*)"/i', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    /** @return array{status:int, location:?string, body:string, headers:array<int,string>} */
    private function send(string $method, string $path, ?string $body, array $headers): array
    {
        $lines = ['Host: ' . substr($this->base, strlen('http://')), 'Connection: close', 'User-Agent: NizamHttpSuite/1.0'];
        foreach ($headers as $header) {
            $lines[] = $header;
        }
        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            $lines[] = 'Cookie: ' . implode('; ', $pairs);
        }
        if ($body !== null) {
            $lines[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $context = stream_context_create(['http' => [
            'method'          => $method,
            'header'          => implode("\r\n", $lines),
            'content'         => $body ?? '',
            'follow_location' => 0,
            'max_redirects'   => 0,
            'ignore_errors'   => true,
            'timeout'         => 20,
            'protocol_version' => 1.0,
        ]]);

        $rawHeaders = [];
        $responseBody = @file_get_contents($this->base . $path, false, $context);
        $rawHeaders = $http_response_header ?? [];
        if ($responseBody === false && $rawHeaders === []) {
            throw new RuntimeException("No HTTP response for {$method} {$path} -- server not reachable");
        }

        $status = 0;
        if (isset($rawHeaders[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $rawHeaders[0], $m)) {
            $status = (int) $m[1];
        }

        $location = null;
        foreach ($rawHeaders as $header) {
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, strlen('Location:')));
            }
            if (stripos($header, 'Set-Cookie:') === 0) {
                $pair = explode(';', substr($header, strlen('Set-Cookie:')), 2)[0];
                $parts = explode('=', trim($pair), 2);
                if (count($parts) === 2 && $parts[0] !== '') {
                    $this->cookies[$parts[0]] = $parts[1];
                }
            }
        }

        return ['status' => $status, 'location' => $location, 'body' => $responseBody === false ? '' : $responseBody, 'headers' => $rawHeaders];
    }
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child)) {
            removeTree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}

// --------------------------------------------------------------- environment

$basePdo = App\Database::connection();
$driver = $basePdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver !== 'mysql') {
    fwrite(STDERR, "BLOCKED: the HTTP suite currently requires MySQL/MariaDB (driver is {$driver}).\n");
    exit(2);
}

// --keep preserves the throwaway database and data path instead of dropping
// them, so the exact build the assertions ran against can be opened in a
// browser for visual review. Without the flag the behaviour is unchanged.
$keep = in_array('--keep', $argv ?? [], true);

$suffix = bin2hex(random_bytes(4));
$database = 'nizam_http_test_' . $suffix;
$tempPath = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'nizam-http-' . $suffix;
$serverProcess = null;
$serverLog = $tempPath . DIRECTORY_SEPARATOR . 'server.err';
$serverLogTail = '';

try {
    foreach (['config', 'storage/logs', 'storage/uploads'] as $dir) {
        if (!mkdir($tempPath . DIRECTORY_SEPARATOR . $dir, 0777, true) && !is_dir($tempPath . DIRECTORY_SEPARATOR . $dir)) {
            throw new RuntimeException('Could not create temporary data directory');
        }
    }

    $sourceConfig = require $root . '/config/config.php';
    $testConfig = $sourceConfig;
    $testConfig['database'] = $database;
    $configDump = "<?php\n// Generated by tests/http/run.php for a throwaway database. Not committed.\nreturn " . var_export($testConfig, true) . ";\n";
    file_put_contents($tempPath . DIRECTORY_SEPARATOR . 'config/config.php', $configDump);

    $basePdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $testConfig['host'],
        $testConfig['port'],
        $database,
        $testConfig['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $testConfig['username'], $testConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    http_out('Nizam HTTP pipeline suite');
    http_out(str_repeat('=', 60));
    http_out('Database: ' . $database . ' (temporary, dropped on exit)');
    http_out();

    $migration = new \App\Services\MigrationService();
    $result = $migration->run($pdo, $root . '/database/migrations');
    if ($result['failure'] !== null) {
        fwrite(STDERR, "BLOCKED: migration {$result['failure']['migration']} failed: {$result['failure']['error']}\n");
        exit(2);
    }
    http_out('Applied ' . count($result['applied']) . ' migrations.');

    $seeds = new \App\Services\SeedService();
    $seedResult = $seeds->run($pdo, $root . '/database/seeds');
    if ($seedResult['failure'] !== null) {
        fwrite(STDERR, "BLOCKED: seed {$seedResult['failure']['seed']} failed: {$seedResult['failure']['error']}\n");
        exit(2);
    }
    http_out('Ran ' . count($seedResult['ran']) . ' seed files.');

    $adminRoleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'admin'")->fetchColumn();
    if ($adminRoleId === 0) {
        fwrite(STDERR, "BLOCKED: no 'admin' role after seeding.\n");
        exit(2);
    }

    $hash = password_hash('HttpSuite!2026', PASSWORD_BCRYPT);
    $insertUser = $pdo->prepare(
        'INSERT INTO users (username, password_hash, full_name, role_id, is_active) VALUES (:u, :h, :n, :r, 1)'
    );
    $insertUser->execute(['u' => 'http_admin', 'h' => $hash, 'n' => 'HTTP Admin', 'r' => $adminRoleId]);

    // A role with no permissions at all: proves the guard denies by default
    // rather than relying on a particular seeded role's template.
    $pdo->exec("INSERT INTO roles (code, name_en, name_ar) VALUES ('http_limited', 'Limited', 'محدود')");
    $limitedRoleId = (int) $pdo->lastInsertId();
    $insertUser->execute(['u' => 'http_limited', 'h' => $hash, 'n' => 'HTTP Limited', 'r' => $limitedRoleId]);

    http_out('Seeded 2 users (admin + zero-permission role).');
    http_out();

    // Static route-contract audit: every handler named in public/index.php
    // must exist and must accept exactly one argument (Request). The router
    // never passes route parameters positionally -- they live on the Request.
    // Found live: 22 handlers referenced methods that did not exist or took
    // positional $id parameters, which took out the entire library, transport,
    // admissions-workflow, guardian-link and HR-decision surface with 500s.
    $routerSource = (string) file_get_contents($root . '/public/index.php');
    preg_match_all('/\[\s*([A-Za-z0-9_\\\\]+)::class,\s*.([A-Za-z0-9_]+).\s*\]/', $routerSource, $handlerMatches, PREG_SET_ORDER);
    ok(count($handlerMatches) >= 150, 'Route table parsed (' . count($handlerMatches) . ' handlers)');
    $contractProblems = [];
    foreach ($handlerMatches as $handlerMatch) {
        $handlerClass = str_starts_with($handlerMatch[1], 'App\\') ? $handlerMatch[1] : 'App\\Controllers\\' . $handlerMatch[1];
        if (!class_exists($handlerClass) || !method_exists($handlerClass, $handlerMatch[2])) {
            $contractProblems[] = $handlerMatch[1] . '::' . $handlerMatch[2] . ' does not exist';
            continue;
        }
        if ((new ReflectionMethod($handlerClass, $handlerMatch[2]))->getNumberOfParameters() !== 1) {
            $contractProblems[] = $handlerMatch[1] . '::' . $handlerMatch[2] . ' does not accept exactly (Request)';
        }
    }
    foreach ($contractProblems as $contractProblem) {
        http_out('        ' . $contractProblem);
    }
    same([], $contractProblems, 'Every route handler exists and accepts (Request)');

    // ------------------------------------------------------------- server
    $port = 0;
    $env = getenv();
    $env['NIZAM_DATA_PATH'] = $tempPath;
    for ($attempt = 0; $attempt < 15; $attempt++) {
        $candidate = random_int(18200, 19400);
        $descriptors = [
            0 => ['file', $tempPath . '/server.in', 'a'],
            1 => ['file', $tempPath . '/server.out', 'a'],
            2 => ['file', $serverLog, 'a'],
        ];
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $candidate, $root . '/public/index.php'],
            $descriptors,
            $pipes,
            $root,
            $env
        );
        if (!is_resource($process)) {
            continue;
        }
        $ready = false;
        $deadline = microtime(true) + 10.0;
        while (microtime(true) < $deadline) {
            $probe = @file_get_contents(
                'http://127.0.0.1:' . $candidate . '/healthz',
                false,
                stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true, 'protocol_version' => 1.0]])
            );
            if ($probe === 'ok') {
                $ready = true;
                break;
            }
            usleep(120000);
        }
        if ($ready) {
            $port = $candidate;
            $serverProcess = $process;
            break;
        }
        proc_terminate($process);
        proc_close($process);
    }

    if ($port === 0 || !is_resource($serverProcess)) {
        fwrite(STDERR, "BLOCKED: could not start the PHP built-in server.\n");
        fwrite(STDERR, (string) @file_get_contents($serverLog));
        exit(2);
    }

    http_out('Server: http://127.0.0.1:' . $port);
    http_out();

    $base = 'http://127.0.0.1:' . $port;
    $password = 'HttpSuite!2026';

    // -------------------------------------------------------- anonymous
    http_out('Anonymous visitor');
    $anon = new Browser($base, 'anon');

    $health = $anon->get('/healthz');
    same(200, $health['status'], 'GET /healthz answers 200');
    same('ok', trim($health['body']), 'GET /healthz body is "ok"');

    $home = $anon->get('/');
    same(302, $home['status'], 'GET / redirects');
    ok(str_contains((string) $home['location'], '/login'), 'GET / redirects to /login');

    $loginPage = $anon->get('/login');
    same(200, $loginPage['status'], 'GET /login renders');
    $token = $anon->csrf($loginPage['body']);
    ok($token !== null && strlen($token) >= 32, 'Login form carries a CSRF token');

    $headers = array_map('strtolower', $loginPage['headers']);
    ok(in_array('x-frame-options: sameorigin', $headers, true), 'Security headers are present on the login page');

    $dash = $anon->get('/dashboard');
    same(302, $dash['status'], 'Unauthenticated GET /dashboard is not rendered');
    ok(str_contains((string) $dash['location'], '/login'), 'Unauthenticated GET /dashboard redirects to /login');

    $students = $anon->get('/students');
    same(302, $students['status'], 'Unauthenticated GET /students redirects to /login');

    $missing = $anon->get('/no-such-route');
    same(404, $missing['status'], 'Unknown route returns a real 404');

    $noToken = $anon->post('/login', ['username' => 'http_admin', 'password' => $password]);
    same(419, $noToken['status'], 'POST /login without a CSRF token is rejected (419)');

    $badToken = $anon->post('/login', ['username' => 'http_admin', 'password' => $password, '_csrf_token' => str_repeat('a', 64)]);
    same(419, $badToken['status'], 'POST /login with a forged CSRF token is rejected (419)');

    $wrong = $anon->post('/login', ['username' => 'http_admin', 'password' => 'not-the-password', '_csrf_token' => (string) $token]);
    same(200, $wrong['status'], 'Wrong password re-renders the login form');
    same(302, $anon->get('/dashboard')['status'], 'Wrong password does not establish a session');

    // ------------------------------------------------------------- admin
    http_out();
    http_out('Administrator session');

    $admin = new Browser($base, 'admin');
    $loginForm = $admin->get('/login');
    $adminToken = $admin->csrf($loginForm['body']);
    ok($adminToken !== null, 'Admin got a CSRF token');
    $cookieBeforeLogin = $admin->cookie('nizam_session');

    $signIn = $admin->post('/login', ['username' => 'http_admin', 'password' => $password, '_csrf_token' => (string) $adminToken]);
    same(302, $signIn['status'], 'Valid credentials redirect');
    ok(str_contains((string) $signIn['location'], '/dashboard'), 'Valid credentials redirect to /dashboard');

    $cookieAfterLogin = $admin->cookie('nizam_session');
    ok($cookieBeforeLogin !== null && $cookieAfterLogin !== null && $cookieBeforeLogin !== $cookieAfterLogin, 'Session ID is regenerated at login (fixation defence)');

    same(200, $admin->get('/dashboard')['status'], 'Admin can load /dashboard');
    same(200, $admin->get('/students')['status'], 'Admin can load /students (students.view granted)');
    same(200, $admin->get('/settings/profile')['status'], 'Admin can load /settings/profile');

    // The three academic-configuration screens from the previous slice,
    // reached through the real router and permission chain rather than
    // through their controller methods.
    same(200, $admin->get('/settings/structure')['status'], 'Admin can load /settings/structure');
    same(200, $admin->get('/settings/terms')['status'], 'Admin can load /settings/terms');
    same(200, $admin->get('/settings/grading')['status'], 'Admin can load /settings/grading');

    $structurePage = $admin->get('/settings/structure');
    $structureToken = $admin->csrf($structurePage['body']);
    ok($structureToken !== null, 'Structure form carries a CSRF token');

    $stagesBefore = (int) $pdo->query('SELECT COUNT(*) FROM educational_stages')->fetchColumn();

    $stageNoToken = $admin->post('/settings/structure/stages', [
        'code' => 'HTTPTEST', 'name_en' => 'HTTP Test Stage', 'name_ar' => 'مرحلة اختبار', 'sort_order' => '90',
    ]);
    same(419, $stageNoToken['status'], 'POST that writes data without a CSRF token is rejected (419)');
    same($stagesBefore, (int) $pdo->query('SELECT COUNT(*) FROM educational_stages')->fetchColumn(), 'Rejected POST wrote nothing');

    $stagePayload = [
        'code' => 'HTTPTEST', 'name_en' => 'HTTP Test Stage', 'name_ar' => 'مرحلة اختبار',
        'sort_order' => '90', '_csrf_token' => (string) $structureToken,
    ];
    $stageCreated = $admin->post('/settings/structure/stages', $stagePayload);
    same(302, $stageCreated['status'], 'Authenticated POST with a valid CSRF token is accepted');
    ok(str_contains((string) $stageCreated['location'], '/settings/structure'), 'Successful write redirects back to the screen (PRG)');
    same($stagesBefore + 1, (int) $pdo->query('SELECT COUNT(*) FROM educational_stages')->fetchColumn(), 'Accepted POST persisted exactly one stage');

    $duplicate = $admin->post('/settings/structure/stages', $stagePayload);
    same(302, $duplicate['status'], 'Duplicate stage code is rejected by redirecting with an error');
    same($stagesBefore + 1, (int) $pdo->query('SELECT COUNT(*) FROM educational_stages')->fetchColumn(), 'Duplicate stage code created no second row');

    $invalid = $admin->post('/settings/structure/stages', [
        'code' => '', 'name_en' => '', 'name_ar' => '', 'sort_order' => '0',
        '_csrf_token' => (string) $structureToken,
    ]);
    same(302, $invalid['status'], 'Invalid stage is rejected by redirecting with an error');
    same($stagesBefore + 1, (int) $pdo->query('SELECT COUNT(*) FROM educational_stages')->fetchColumn(), 'Invalid stage created no row');

    // Every module landing page, as the administrator. This is the assertion
    // that catches "the route exists but the role was never granted its
    // permission" -- a fresh install failed six of these before migration 021.
    http_out();
    http_out('Module landing pages (administrator)');
    $landingPages = [
        '/academic-years', '/grades', '/subjects', '/classes', '/teachers',
        '/students', '/assignments', '/attendance', '/exams', '/timetable',
        '/admissions', '/guardians', '/finance', '/hr', '/transport', '/library',
        '/welfare/discipline', '/welfare/health', '/reports', '/activity-log',
        '/users', '/settings/modules', '/settings/localization', '/settings/academic',
        '/settings/security', '/settings/backup', '/backups', '/notifications',
    ];
    foreach ($landingPages as $path) {
        same(200, $admin->get($path)['status'], 'Admin can load ' . $path);
    }

    // --------------------------------------------------- write paths (admin)
    // One create -> validate -> edit chain per module, driven through the real
    // routes. The admissions conversion is the chain's spine: it produces the
    // actual student that finance, transport and library then operate on, so
    // nothing here is inserted behind the application's back.
    http_out();
    http_out('Write paths (administrator)');

    $formToken = $admin->csrf($admin->get('/grades')['body']);
    ok($formToken !== null, 'Write forms carry a session CSRF token');
    $formToken = (string) $formToken;

    $count = static fn (string $table): int => (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    $scalar = static function (string $sql) use ($pdo): string {
        $value = $pdo->query($sql)->fetchColumn();
        return $value === false ? '' : (string) $value;
    };

    // Grades (create + edit)
    $before = $count('grades');
    same(302, $admin->post('/grades', ['name_en' => 'HTTP Grade', 'name_ar' => 'صف اختبار', 'sort_order' => '95', '_csrf_token' => $formToken])['status'], 'POST /grades redirects');

    same($before + 1, $count('grades'), 'POST /grades persisted one grade');
    same(302, $admin->post('/grades', ['name_en' => '', 'name_ar' => '', 'sort_order' => '0', '_csrf_token' => $formToken])['status'], 'Invalid grade is rejected');
    same($before + 1, $count('grades'), 'Invalid grade created no row');
    $gradeId = (int) $scalar("SELECT id FROM grades WHERE name_en = 'HTTP Grade' LIMIT 1");
    ok($gradeId > 0, 'Created grade is retrievable');
    same(302, $admin->post('/grades/' . $gradeId, ['name_en' => 'HTTP Grade Edited', 'name_ar' => 'صف اختبار', 'sort_order' => '95', '_csrf_token' => $formToken])['status'], 'Edit grade redirects');
    same('HTTP Grade Edited', $scalar("SELECT name_en FROM grades WHERE id = {$gradeId}"), 'Edit grade persisted');

    // ---------------------------------------------- offline queue replay
    http_out();
    http_out('Offline queue replay (idempotency)');

    $tokenResponse = $admin->get('/csrf-token', ['Accept: application/json']);
    same(200, $tokenResponse['status'], 'GET /csrf-token returns 200 for a signed-in user');
    $tokenBody = json_decode($tokenResponse['body'], true);
    ok(is_array($tokenBody) && isset($tokenBody['token']) && strlen((string) $tokenBody['token']) === 64, 'GET /csrf-token returns a 64-character token');
    same(302, $anon->get('/csrf-token')['status'], 'GET /csrf-token redirects an anonymous request');

    $queueId = 'queue-' . bin2hex(random_bytes(10));
    $queuedGrade = 'Queued Grade ' . bin2hex(random_bytes(3));
    $queuedPayload = ['name_en' => $queuedGrade, 'name_ar' => $queuedGrade, 'sort_order' => '96', '_csrf_token' => $formToken];

    same(302, $admin->post('/grades', $queuedPayload, ['X-Nizam-Queue-Id: ' . $queueId])['status'], 'First offline submission is processed normally');
    same(204, $admin->post('/grades', $queuedPayload, ['X-Nizam-Queue-Id: ' . $queueId])['status'], 'Replay with the same queue id is suppressed (204)');
    same(1, (int) $scalar("SELECT COUNT(*) FROM grades WHERE name_en = '{$queuedGrade}'"), 'Replay did not create a duplicate grade');

    $offlineScreen = $admin->get('/settings/offline');
    same(200, $offlineScreen['status'], 'GET /settings/offline renders the pending-sends screen');
    ok(strpos($offlineScreen['body'], 'data-queue-list') !== false, 'Pending-sends screen exposes the local queue list');
    same(302, $anon->get('/settings/offline')['status'], 'GET /settings/offline requires authentication');

    // Every screen must show exactly one page title: the shared hero or the
    // view's own header, never both.
    foreach (['/dashboard', '/students', '/finance', '/guardians', '/timetable', '/reports', '/exams'] as $screen) {
        same(1, substr_count($admin->get($screen)['body'], '<h1'), $screen . ' renders exactly one page title');
    }
    ok(strpos($admin->get('/activity-log')['body'], 'n-hero-compact') !== false, 'Shared layout hero renders on a screen that does not build its own header');

    // Subjects (create + duplicate rejection)
    $before = $count('subjects');
    same(302, $admin->post('/subjects', ['code' => 'HTTP-S1', 'name_en' => 'HTTP Subject', 'name_ar' => 'مادة اختبار', '_csrf_token' => $formToken])['status'], 'POST /subjects redirects');
    same($before + 1, $count('subjects'), 'POST /subjects persisted one subject');
    same(200, $admin->post('/subjects', ['code' => '', 'name_en' => '', 'name_ar' => '', '_csrf_token' => $formToken])['status'], 'Invalid subject re-renders the form');
    same($before + 1, $count('subjects'), 'Invalid subject created no row');
    same(200, $admin->post('/subjects', ['code' => 'HTTP-S1', 'name_en' => 'Duplicate', 'name_ar' => 'مكرر', '_csrf_token' => $formToken])['status'], 'Duplicate subject code re-renders the form');
    same($before + 1, $count('subjects'), 'Duplicate subject code created no row');
    $subjectId = (int) $scalar("SELECT id FROM subjects WHERE code = 'HTTP-S1' LIMIT 1");

    // Teachers (create)
    $before = $count('teachers');
    same(302, $admin->post('/teachers', ['full_name' => 'HTTP Teacher', 'phone' => '0100000000', 'email' => 'http.teacher@example.com', '_csrf_token' => $formToken])['status'], 'POST /teachers redirects');
    same($before + 1, $count('teachers'), 'POST /teachers persisted one teacher');
    same(200, $admin->post('/teachers', ['full_name' => '', 'phone' => '', 'email' => '', '_csrf_token' => $formToken])['status'], 'Invalid teacher re-renders the form');
    same($before + 1, $count('teachers'), 'Invalid teacher created no row');
    $teacherId = (int) $scalar("SELECT id FROM teachers WHERE full_name = 'HTTP Teacher' LIMIT 1");
    ok($teacherId > 0, 'Created teacher is retrievable');

    // Classes (create + duplicate + edit)
    $before = $count('classes');
    same(302, $admin->post('/classes', ['grade_id' => (string) $gradeId, 'name' => 'HTTP Class', 'capacity' => '30', '_csrf_token' => $formToken])['status'], 'POST /classes redirects');
    same($before + 1, $count('classes'), 'POST /classes persisted one class');
    same(302, $admin->post('/classes', ['grade_id' => (string) $gradeId, 'name' => 'HTTP Class', 'capacity' => '30', '_csrf_token' => $formToken])['status'], 'Duplicate class name is rejected');
    same($before + 1, $count('classes'), 'Duplicate class name created no row');
    $classId = (int) $scalar("SELECT id FROM classes WHERE name = 'HTTP Class' LIMIT 1");
    same(302, $admin->post('/classes/' . $classId, ['name' => 'HTTP Class Edited', 'capacity' => '28', '_csrf_token' => $formToken])['status'], 'Edit class redirects');
    same('HTTP Class Edited', $scalar("SELECT name FROM classes WHERE id = {$classId}"), 'Edit class persisted');

    // Admissions: create -> offer -> convert, which yields the student the
    // remaining modules need.
    $before = $count('admissions_applications');
    $applicationPayload = [
        'applicant_name' => 'HTTP Applicant', 'gender' => 'm', 'date_of_birth' => '2015-03-01',
        'grade_id' => (string) $gradeId, 'guardian_name' => 'HTTP Applicant Guardian',
        'guardian_phone' => '0111111111', 'guardian_email' => '', 'guardian_relationship' => 'father',
        'status' => 'inquiry', '_csrf_token' => $formToken,
    ];
    same(302, $admin->post('/admissions', $applicationPayload)['status'], 'POST /admissions redirects');
    same($before + 1, $count('admissions_applications'), 'POST /admissions persisted one application');
    $applicationId = (int) $scalar("SELECT id FROM admissions_applications WHERE applicant_name = 'HTTP Applicant' LIMIT 1");
    $missingName = $applicationPayload;
    $missingName['applicant_name'] = '';
    same(302, $admin->post('/admissions', $missingName)['status'], 'Application without a name is rejected');
    same($before + 1, $count('admissions_applications'), 'Rejected application created no row');

    same(302, $admin->post('/admissions/' . $applicationId . '/status', ['status' => 'offer', '_csrf_token' => $formToken])['status'], 'Application status update redirects');
    same('offer', $scalar("SELECT status FROM admissions_applications WHERE id = {$applicationId}"), 'Application status update persisted');

    $conversion = $admin->post('/admissions/' . $applicationId . '/convert', ['class_id' => (string) $classId, '_csrf_token' => $formToken]);
    same(302, $conversion['status'], 'Conversion redirects');
    ok((bool) preg_match('#^/students/(\d+)#', (string) $conversion['location'], $converted), 'Conversion redirects to the new student');
    $studentId = isset($converted[1]) ? (int) $converted[1] : 0;
    ok($studentId > 0, 'Conversion produced a student id');
    same('enrolled', $scalar("SELECT status FROM admissions_applications WHERE id = {$applicationId}"), 'Application is marked enrolled after conversion');

    // Guardians (create + edit + link to the converted student)
    $before = $count('guardians');
    same(302, $admin->post('/guardians', ['full_name' => 'HTTP Guardian Record', 'phone' => '0122222222', 'email' => 'guardian@example.com', 'national_id' => '', 'relationship_type' => 'father', 'occupation' => '', 'address' => '', 'notes' => '', '_csrf_token' => $formToken])['status'], 'POST /guardians redirects');
    same($before + 1, $count('guardians'), 'POST /guardians persisted one guardian');
    same(302, $admin->post('/guardians', ['full_name' => '', 'phone' => '', '_csrf_token' => $formToken])['status'], 'Guardian without name/phone is rejected');
    same($before + 1, $count('guardians'), 'Rejected guardian created no row');
    $guardianId = (int) $scalar("SELECT id FROM guardians WHERE full_name = 'HTTP Guardian Record' LIMIT 1");
    same(302, $admin->post('/guardians/' . $guardianId, ['full_name' => 'HTTP Guardian Edited', 'phone' => '0122222222', 'relationship_type' => 'father', 'occupation' => 'Engineer', '_csrf_token' => $formToken])['status'], 'Edit guardian redirects');
    same('Engineer', $scalar("SELECT occupation FROM guardians WHERE id = {$guardianId}"), 'Edit guardian persisted');
    same(302, $admin->post('/students/' . $studentId . '/guardians/link', ['guardian_id' => (string) $guardianId, 'relationship' => 'father', 'is_primary' => '1', '_csrf_token' => $formToken])['status'], 'Guardian link redirects');
    same(1, (int) $scalar("SELECT COUNT(*) FROM student_guardians WHERE student_id = {$studentId} AND guardian_id = {$guardianId}"), 'Guardian link persisted');

    // Finance: invoice + partial payment + refused overpayment
    $before = $count('student_invoices');
    same(302, $admin->post('/finance/invoices', ['student_id' => (string) $studentId, 'title' => 'HTTP Tuition', 'total_amount' => '1000', 'discount_amount' => '0', 'due_date' => '2026-12-31', 'notes' => '', '_csrf_token' => $formToken])['status'], 'POST /finance/invoices redirects');
    same($before + 1, $count('student_invoices'), 'POST /finance/invoices persisted one invoice');
    $invoiceId = (int) $scalar("SELECT id FROM student_invoices WHERE title = 'HTTP Tuition' ORDER BY id DESC LIMIT 1");
    same(302, $admin->post('/finance/invoices', ['student_id' => (string) $studentId, 'title' => 'HTTP Bad Invoice', 'total_amount' => '0', 'discount_amount' => '0', '_csrf_token' => $formToken])['status'], 'Invoice without an amount is rejected');
    same($before + 1, $count('student_invoices'), 'Rejected invoice created no row');

    same(302, $admin->post('/finance/invoices/' . $invoiceId . '/payments', ['amount' => '250', 'payment_date' => date('Y-m-d'), 'payment_method' => 'cash', 'reference_number' => '', 'notes' => '', '_csrf_token' => $formToken])['status'], 'Payment redirects');
    same('250.00', number_format((float) $scalar("SELECT paid_amount FROM student_invoices WHERE id = {$invoiceId}"), 2), 'Payment updated the invoice balance');
    same('partial', $scalar("SELECT status FROM student_invoices WHERE id = {$invoiceId}"), 'Partial payment set the invoice status');
    $paymentsBefore = $count('invoice_payments');
    same(302, $admin->post('/finance/invoices/' . $invoiceId . '/payments', ['amount' => '9999', 'payment_date' => date('Y-m-d'), 'payment_method' => 'cash', '_csrf_token' => $formToken])['status'], 'Overpayment is refused without a server error');
    same($paymentsBefore, $count('invoice_payments'), 'Refused overpayment stored no receipt');

    // Transport: route + assignment
    $before = $count('transport_routes');
    same(302, $admin->post('/transport', ['route_name' => 'HTTP Route', 'vehicle_number' => 'V-HTTP', 'driver_name' => 'HTTP Driver', 'driver_phone' => '0100000000', 'capacity' => '2', 'fee_amount' => '100', '_csrf_token' => $formToken])['status'], 'POST /transport redirects');
    same($before + 1, $count('transport_routes'), 'POST /transport persisted one route');
    $routeId = (int) $scalar("SELECT id FROM transport_routes WHERE route_name = 'HTTP Route' LIMIT 1");
    same(302, $admin->post('/transport', ['route_name' => '', 'vehicle_number' => '', 'driver_name' => '', 'driver_phone' => '', '_csrf_token' => $formToken])['status'], 'Invalid route is rejected');
    same($before + 1, $count('transport_routes'), 'Rejected route created no row');
    same(302, $admin->post('/transport/' . $routeId . '/assign', ['student_id' => (string) $studentId, 'pickup_point' => 'Stop A', 'dropoff_point' => 'Stop B', '_csrf_token' => $formToken])['status'], 'Transport assignment redirects');
    same(1, (int) $scalar("SELECT COUNT(*) FROM student_transport WHERE route_id = {$routeId} AND student_id = {$studentId}"), 'Transport assignment persisted');

    // Library: book + borrow + exhausted stock + return + double return
    $before = $count('library_books');
    same(302, $admin->post('/library', ['isbn' => '', 'title' => 'HTTP Book', 'author' => 'HTTP Author', 'category' => 'General', 'total_copies' => '1', 'shelf_location' => 'A1', '_csrf_token' => $formToken])['status'], 'POST /library redirects');
    same($before + 1, $count('library_books'), 'POST /library persisted one book');
    $bookId = (int) $scalar("SELECT id FROM library_books WHERE title = 'HTTP Book' LIMIT 1");
    same('1', $scalar("SELECT available_copies FROM library_books WHERE id = {$bookId}"), 'New book starts with its full stock');
    same(302, $admin->post('/library', ['title' => '', 'author' => '', '_csrf_token' => $formToken])['status'], 'Book without title/author is rejected');
    same($before + 1, $count('library_books'), 'Rejected book created no row');

    $borrowsBefore = $count('library_borrows');
    same(302, $admin->post('/library/borrow', ['book_id' => (string) $bookId, 'borrower_type' => 'student', 'student_id' => (string) $studentId, 'due_date' => '2026-12-31', '_csrf_token' => $formToken])['status'], 'Borrow redirects');
    same($borrowsBefore + 1, $count('library_borrows'), 'Borrow persisted one loan');
    same('0', $scalar("SELECT available_copies FROM library_books WHERE id = {$bookId}"), 'Borrow decremented availability');
    $borrowId = (int) $scalar("SELECT id FROM library_borrows WHERE book_id = {$bookId} ORDER BY id DESC LIMIT 1");
    same(302, $admin->post('/library/borrow', ['book_id' => (string) $bookId, 'borrower_type' => 'student', 'student_id' => (string) $studentId, '_csrf_token' => $formToken])['status'], 'Borrowing an exhausted title is refused without a server error');
    same($borrowsBefore + 1, $count('library_borrows'), 'Refused borrow stored no loan');
    same(302, $admin->post('/library/borrows/' . $borrowId . '/return', ['_csrf_token' => $formToken])['status'], 'Return redirects');
    same('1', $scalar("SELECT available_copies FROM library_books WHERE id = {$bookId}"), 'Return restored availability');
    $admin->post('/library/borrows/' . $borrowId . '/return', ['_csrf_token' => $formToken]);
    same('1', $scalar("SELECT available_copies FROM library_books WHERE id = {$bookId}"), 'Double return did not inflate stock');

    // HR: leave request + decision + department
    $before = $count('staff_leaves');
    same(302, $admin->post('/hr/leaves', ['teacher_id' => (string) $teacherId, 'leave_type' => 'annual', 'start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'reason' => 'HTTP leave', '_csrf_token' => $formToken])['status'], 'POST /hr/leaves redirects');
    same($before + 1, $count('staff_leaves'), 'POST /hr/leaves persisted one request');
    $leaveId = (int) $scalar("SELECT id FROM staff_leaves WHERE reason = 'HTTP leave' LIMIT 1");
    same(302, $admin->post('/hr/leaves', ['teacher_id' => (string) $teacherId, 'start_date' => '', 'end_date' => '', 'reason' => '', '_csrf_token' => $formToken])['status'], 'Leave without dates/reason is rejected');
    same($before + 1, $count('staff_leaves'), 'Rejected leave created no row');
    same(302, $admin->post('/hr/leaves/' . $leaveId . '/action', ['status' => 'approved', 'action_notes' => 'ok', '_csrf_token' => $formToken])['status'], 'Leave decision redirects');
    same('approved', $scalar("SELECT status FROM staff_leaves WHERE id = {$leaveId}"), 'Leave decision persisted');
    $admin->post('/hr/leaves/' . $leaveId . '/action', ['status' => 'rejected', '_csrf_token' => $formToken]);
    same('approved', $scalar("SELECT status FROM staff_leaves WHERE id = {$leaveId}"), 'A decided leave cannot be re-decided');
    $departmentsBefore = $count('staff_departments');
    same(302, $admin->post('/hr/departments', ['code' => 'HTTPD', 'name_en' => 'HTTP Dept', 'name_ar' => 'قسم اختبار', '_csrf_token' => $formToken])['status'], 'POST /hr/departments redirects');
    same($departmentsBefore + 1, $count('staff_departments'), 'POST /hr/departments persisted one department');

    // Users (create + duplicate rejection)
    $before = $count('users');
    same(302, $admin->post('/users', ['username' => 'http_created', 'password' => 'Str0ng!Pass2026', 'full_name' => 'HTTP Created User', 'role_code' => 'staff', 'email' => 'http.created@example.com', '_csrf_token' => $formToken])['status'], 'POST /users redirects');
    same($before + 1, $count('users'), 'POST /users persisted one user');
    same(302, $admin->post('/users', ['username' => 'http_created', 'password' => 'Str0ng!Pass2026', 'full_name' => 'Duplicate User', 'role_code' => 'staff', '_csrf_token' => $formToken])['status'], 'Duplicate username is rejected');
    same($before + 1, $count('users'), 'Duplicate username created no row');
    same(302, $admin->post('/users', ['username' => 'ab', 'password' => 'Str0ng!Pass2026', 'full_name' => 'Bad Username', 'role_code' => 'staff', '_csrf_token' => $formToken])['status'], 'Invalid username is rejected');
    same($before + 1, $count('users'), 'Invalid username created no row');

    // Academic years last: creating one must not disturb anything above that
    // depends on the active year.
    $before = $count('academic_years');
    same(302, $admin->post('/academic-years', ['label' => '2099/2100', 'start_date' => '2099-09-01', 'end_date' => '2100-06-30', '_csrf_token' => $formToken])['status'], 'POST /academic-years redirects');
    same($before + 1, $count('academic_years'), 'POST /academic-years persisted one year');
    same(200, $admin->post('/academic-years', ['label' => '', 'start_date' => 'not-a-date', 'end_date' => '', '_csrf_token' => $formToken])['status'], 'Invalid academic year re-renders the form');
    same($before + 1, $count('academic_years'), 'Invalid academic year created no row');

    // Students: the ordinary create/edit path (the conversion path above
    // already produced one student, so this exercises the form itself).
    $before = $count('students');
    same(302, $admin->post('/students', [
        'full_name' => 'HTTP Direct Student', 'gender' => 'f', 'date_of_birth' => '2014-05-10',
        'religion' => 'muslim', 'phone' => '0101111111', 'guardian_phone' => '0101111112',
        'address' => 'HTTP Address', 'notes' => '', 'grade_id' => (string) $gradeId,
        'class_id' => (string) $classId, '_csrf_token' => $formToken,
    ])['status'], 'POST /students redirects');
    same($before + 1, $count('students'), 'POST /students persisted one student');
    $directStudentId = (int) $scalar("SELECT id FROM students WHERE full_name = 'HTTP Direct Student' LIMIT 1");
    ok($directStudentId > 0, 'Created student is retrievable');
    same(200, $admin->post('/students', [
        'full_name' => '', 'gender' => 'x', 'date_of_birth' => '', 'religion' => '',
        'grade_id' => (string) $gradeId, '_csrf_token' => $formToken,
    ])['status'], 'Invalid student re-renders the form');
    same($before + 1, $count('students'), 'Invalid student created no row');
    same(302, $admin->post('/students/' . $directStudentId, [
        'full_name' => 'HTTP Direct Student Edited', 'gender' => 'f', 'date_of_birth' => '2014-05-10',
        'religion' => 'muslim', 'phone' => '0101111111', 'guardian_phone' => '0101111112',
        'class_id' => (string) $classId, '_csrf_token' => $formToken,
    ])['status'], 'Edit student redirects');
    same('HTTP Direct Student Edited', $scalar("SELECT full_name FROM students WHERE id = {$directStudentId}"), 'Edit student persisted');

    // Exams (create + edit)
    $before = $count('exams');
    same(302, $admin->post('/exams', ['name_en' => 'HTTP Exam', 'name_ar' => 'امتحان اختبار', 'term' => '1', 'max_score' => '100', 'weight' => '100', '_csrf_token' => $formToken])['status'], 'POST /exams redirects');
    same($before + 1, $count('exams'), 'POST /exams persisted one exam');
    $examId = (int) $scalar("SELECT id FROM exams WHERE name_en = 'HTTP Exam' LIMIT 1");
    same(302, $admin->post('/exams', ['name_en' => '', 'name_ar' => '', 'term' => '1', 'max_score' => '100', 'weight' => '100', '_csrf_token' => $formToken])['status'], 'Exam without a name is rejected');
    same($before + 1, $count('exams'), 'Rejected exam created no row');
    same(302, $admin->post('/exams/' . $examId . '/update', ['name_en' => 'HTTP Exam Edited', 'name_ar' => 'امتحان اختبار', 'term' => '1', 'max_score' => '100', 'weight' => '100', '_csrf_token' => $formToken])['status'], 'Edit exam redirects');
    same('HTTP Exam Edited', $scalar("SELECT name_en FROM exams WHERE id = {$examId}"), 'Edit exam persisted');

    // Attendance sheet for the converted student
    $before = $count('attendance_records');
    same(302, $admin->post('/attendance', [
        'class_id' => (string) $classId, 'date' => '2026-11-02',
        'statuses' => [(string) $studentId => 'present'], 'notes' => [(string) $studentId => ''],
        '_csrf_token' => $formToken,
    ])['status'], 'POST /attendance redirects');
    same($before + 1, $count('attendance_records'), 'POST /attendance persisted one mark');
    same(302, $admin->post('/attendance', ['class_id' => '0', 'date' => '2026-11-02', '_csrf_token' => $formToken])['status'], 'Attendance without a class is rejected');
    same($before + 1, $count('attendance_records'), 'Rejected attendance created no row');

    // Teaching assignment
    $before = $count('teacher_assignments');
    same(302, $admin->post('/assignments', ['teacher_id' => (string) $teacherId, 'subject_id' => (string) $subjectId, 'class_id' => (string) $classId, 'weekly_periods' => '3', '_csrf_token' => $formToken])['status'], 'POST /assignments redirects');
    same($before + 1, $count('teacher_assignments'), 'POST /assignments persisted one assignment');
    same(200, $admin->post('/assignments', ['teacher_id' => '0', 'subject_id' => '0', 'class_id' => '0', 'weekly_periods' => '0', '_csrf_token' => $formToken])['status'], 'Invalid assignment re-renders the form');
    same($before + 1, $count('teacher_assignments'), 'Rejected assignment created no row');

    // Timetable slot. Deliberately after the assignment: addSlot() refuses a
    // teacher/subject/class tuple that has no teaching assignment, so a slot
    // posted first is correctly rejected (the ordering is the prerequisite).
    $before = $count('timetables');
    same(302, $admin->post('/timetable', ['class_id' => (string) $classId, 'day_of_week' => '1', 'period_number' => '1', 'subject_id' => (string) $subjectId, 'teacher_id' => (string) $teacherId, '_csrf_token' => $formToken])['status'], 'POST /timetable redirects');
    same($before + 1, $count('timetables'), 'POST /timetable persisted one slot');
    same(302, $admin->post('/timetable', ['class_id' => '0', 'day_of_week' => '9', 'period_number' => '0', 'subject_id' => '0', 'teacher_id' => '0', '_csrf_token' => $formToken])['status'], 'Invalid timetable slot is rejected');
    same($before + 1, $count('timetables'), 'Rejected timetable slot created no row');
    same(302, $admin->post('/timetable', ['class_id' => (string) $classId, 'day_of_week' => '1', 'period_number' => '1', 'subject_id' => (string) $subjectId, 'teacher_id' => (string) $teacherId, '_csrf_token' => $formToken])['status'], 'A duplicate class slot is rejected by redirect');
    same($before + 1, $count('timetables'), 'A duplicate class slot created no second row');

    // Welfare: discipline and health
    $before = $count('disciplinary_records');
    same(302, $admin->post('/welfare/discipline', ['student_id' => (string) $studentId, 'incident_date' => '2026-11-03', 'type' => 'infraction', 'severity' => 'low', 'title' => 'HTTP Incident', 'description' => 'HTTP description', 'action_taken' => '', '_csrf_token' => $formToken])['status'], 'POST /welfare/discipline redirects');
    same($before + 1, $count('disciplinary_records'), 'POST /welfare/discipline persisted one record');
    same(302, $admin->post('/welfare/discipline', ['student_id' => '0', 'incident_date' => '2026-11-03', 'title' => '', 'description' => '', '_csrf_token' => $formToken])['status'], 'Discipline record without a student is rejected');
    same($before + 1, $count('disciplinary_records'), 'Rejected discipline record created no row');

    $before = $count('health_records');
    same(302, $admin->post('/welfare/health', ['student_id' => (string) $studentId, 'record_type' => 'clinic_visit', 'date_logged' => '2026-11-03', 'title' => 'HTTP Visit', 'details' => 'HTTP details', '_csrf_token' => $formToken])['status'], 'POST /welfare/health redirects');
    same($before + 1, $count('health_records'), 'POST /welfare/health persisted one record');
    same(302, $admin->post('/welfare/health', ['student_id' => '0', 'record_type' => 'clinic_visit', 'date_logged' => '2026-11-03', 'title' => '', 'details' => '', '_csrf_token' => $formToken])['status'], 'Health record without a student is rejected');
    same($before + 1, $count('health_records'), 'Rejected health record created no row');

    // Settings: profile, localization, academic rules, security
    same(302, $admin->post('/settings/profile', ['name' => 'HTTP School', 'name_ar' => 'مدرسة اختبار', 'address' => '', 'phone' => '', 'email' => '', 'website' => '', 'country' => 'EG', 'region' => '', 'timezone' => 'Africa/Cairo', 'currency' => 'EGP', 'school_week' => '0,1,2,3,4', 'school_type' => 'language_school', 'school_structure' => 'k12_combined', 'footer_text' => 'HTTP footer', 'tax_number' => '', 'affiliation_code' => '', '_csrf_token' => $formToken])['status'], 'POST /settings/profile redirects');
    same('HTTP School', $scalar('SELECT name FROM schools ORDER BY id LIMIT 1'), 'Profile save persisted the school name');
    same(200, $admin->post('/settings/profile', ['name' => '', 'name_ar' => '', '_csrf_token' => $formToken])['status'], 'Invalid profile re-renders the form');
    same('HTTP School', $scalar('SELECT name FROM schools ORDER BY id LIMIT 1'), 'Rejected profile changed nothing');

    same(302, $admin->post('/settings/localization', ['default_language' => 'en', '_csrf_token' => $formToken])['status'], 'POST /settings/localization redirects');
    same('en', $scalar("SELECT value FROM settings WHERE setting_key = 'app.default_language'"), 'Localization save persisted');
    same(302, $admin->post('/settings/localization', ['default_language' => 'xx', '_csrf_token' => $formToken])['status'], 'Invalid language is rejected');
    same('en', $scalar("SELECT value FROM settings WHERE setting_key = 'app.default_language'"), 'Rejected language changed nothing');

    same(302, $admin->post('/settings/academic', ['expected_weekly_capacity' => '24', 'student_id_pattern' => 'STU-{year}-{seq:5}', 'teacher_id_pattern' => 'TCH-{seq:5}', '_csrf_token' => $formToken])['status'], 'POST /settings/academic redirects');
    same('24', $scalar("SELECT value FROM settings WHERE setting_key = 'academic.expected_weekly_capacity'"), 'Academic rules save persisted');
    same(200, $admin->post('/settings/academic', ['expected_weekly_capacity' => '999', 'student_id_pattern' => 'NO-SEQ', 'teacher_id_pattern' => 'NO-SEQ', '_csrf_token' => $formToken])['status'], 'Out-of-range academic rules re-render the form');

    same(302, $admin->post('/settings/security', ['login_max_attempts' => '5', 'lockout_minutes' => '15', 'session_timeout_minutes' => '60', '_csrf_token' => $formToken])['status'], 'POST /settings/security redirects');
    same('60', $scalar("SELECT value FROM settings WHERE setting_key = 'security.session_timeout_minutes'"), 'Security save persisted');
    same(200, $admin->post('/settings/security', ['login_max_attempts' => '2', 'lockout_minutes' => '15', 'session_timeout_minutes' => '60', '_csrf_token' => $formToken])['status'], 'Out-of-range security value re-renders the form');

    // Module toggling, including the core-module refusal
    $libraryKey = $scalar("SELECT module_key FROM installed_modules WHERE module_key = 'library' LIMIT 1");
    if ($libraryKey !== '') {
        same(302, $admin->post('/settings/modules/' . $libraryKey . '/toggle', ['enable' => '0', '_csrf_token' => $formToken])['status'], 'Module toggle redirects');
        same('0', $scalar("SELECT is_enabled FROM installed_modules WHERE module_key = 'library'"), 'Module toggle disabled the module');
        $admin->post('/settings/modules/' . $libraryKey . '/toggle', ['enable' => '1', '_csrf_token' => $formToken]);
        same('1', $scalar("SELECT is_enabled FROM installed_modules WHERE module_key = 'library'"), 'Module toggle re-enabled the module');
    } else {
        skipped('Module toggle (no library row in installed_modules)');
    }
    $coreKey = $scalar('SELECT module_key FROM installed_modules WHERE is_core = 1 LIMIT 1');
    if ($coreKey !== '') {
        $admin->post('/settings/modules/' . $coreKey . '/toggle', ['enable' => '0', '_csrf_token' => $formToken]);
        same('1', $scalar("SELECT is_enabled FROM installed_modules WHERE module_key = '{$coreKey}'"), 'A core module cannot be disabled');
    } else {
        skipped('Core-module refusal (no core module row)');
    }

    // The role workspaces legitimately redirect when the signed-in user has no
    // teacher/child link, so they are not asserted to be 200. They must not
    // crash (500) and must not be permission-denied (403) for an administrator.
    http_out();
    http_out('Role workspaces (administrator)');
    foreach (['/portal/teacher', '/portal/family', '/portal/student'] as $portal) {
        $response = $admin->get($portal);
        ok($response['status'] !== 500, $portal . ' does not crash');
        ok($response['status'] !== 403, $portal . ' is not permission-denied for admin');
    }

    // --------------------------------------------------------- module gate
    http_out();
    http_out('Module gate');
    $hasModules = (bool) $pdo->query("SHOW TABLES LIKE 'installed_modules'")->fetchColumn();
    $libraryModule = $hasModules ? $pdo->query("SELECT id, is_core FROM installed_modules WHERE module_key = 'library' LIMIT 1")->fetch() : false;
    if ($libraryModule !== false && (int) $libraryModule['is_core'] === 0) {
        $pdo->exec("UPDATE installed_modules SET is_enabled = 0 WHERE module_key = 'library'");
        $gated = $admin->get('/library');
        same(302, $gated['status'], 'Disabled module does not render');
        ok(str_contains((string) $gated['location'], '/dashboard'), 'Disabled module redirects to the dashboard');
        $pdo->exec("UPDATE installed_modules SET is_enabled = 1 WHERE module_key = 'library'");
        same(200, $admin->get('/library')['status'], 'Re-enabled module renders again');
    } else {
        skipped('Module gate (no non-core library row in installed_modules)');
    }

    // ------------------------------------------------------------ locale
    http_out();
    http_out('Locale switch and open-redirect guard');

    $sameOrigin = $admin->get('/lang?to=en', ['Referer: ' . $base . '/students']);
    same(302, $sameOrigin['status'], 'Locale switch redirects');
    same('/students', $sameOrigin['location'], 'Locale switch returns to the same-origin path that referred it');

    $foreign = $admin->get('/lang?to=en', ['Referer: https://evil.example/phish']);
    same('/login', $foreign['location'], 'Locale switch refuses a foreign Referer host (open-redirect guard)');

    // ------------------------------------------------------- authorization
    http_out();
    http_out('Zero-permission role (deny by default)');

    $limited = new Browser($base, 'limited');
    $limitedToken = $limited->csrf($limited->get('/login')['body']);
    $limitedSignIn = $limited->post('/login', ['username' => 'http_limited', 'password' => $password, '_csrf_token' => (string) $limitedToken]);
    same(302, $limitedSignIn['status'], 'Limited user can sign in');

    same(200, $limited->get('/dashboard')['status'], 'Limited user can load a route that only requires authentication');
    same(403, $limited->get('/students')['status'], 'Limited user is denied /students (students.view)');
    same(403, $limited->get('/settings/profile')['status'], 'Limited user is denied /settings/profile (settings.manage)');
    same(403, $limited->get('/finance')['status'], 'Limited user is denied /finance (finance.view)');
    same(403, $limited->get('/settings/structure')['status'], 'Limited user is denied the academic configuration screens');

    $limitedDashboard = $limited->get('/dashboard');
    $limitedCsrf = $limited->csrf($limitedDashboard['body']);
    if ($limitedCsrf === null) {
        skipped('Per-module permission denial (no CSRF token rendered on the dashboard shell)');
    } else {
        // One write endpoint per module. A valid token gets the request past
        // CSRF, so a 403 here is authorization and nothing else.
        $denials = [
            ['/grades', ['name_en' => 'Forbidden Grade', 'name_ar' => 'ممنوع', 'sort_order' => '9'], 'grades'],
            ['/subjects', ['code' => 'FORBID', 'name_en' => 'Forbidden Subject', 'name_ar' => 'ممنوع'], 'subjects'],
            ['/teachers', ['full_name' => 'Forbidden Teacher', 'phone' => '0100000000'], 'teachers'],
            ['/classes', ['grade_id' => (string) $gradeId, 'name' => 'Forbidden Class', 'capacity' => '20'], 'classes'],
            ['/admissions', ['applicant_name' => 'Forbidden Applicant', 'gender' => 'm', 'date_of_birth' => '2015-01-01', 'grade_id' => (string) $gradeId, 'guardian_name' => 'X', 'guardian_phone' => '0100000000'], 'admissions_applications'],
            ['/guardians', ['full_name' => 'Forbidden Guardian', 'phone' => '0100000000'], 'guardians'],
            ['/finance/invoices', ['student_id' => (string) $studentId, 'title' => 'Forbidden Invoice', 'total_amount' => '10', 'discount_amount' => '0'], 'student_invoices'],
            ['/hr/leaves', ['teacher_id' => (string) $teacherId, 'leave_type' => 'annual', 'start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'reason' => 'Forbidden'], 'staff_leaves'],
            ['/transport', ['route_name' => 'Forbidden Route', 'vehicle_number' => 'X', 'driver_name' => 'X', 'driver_phone' => '0100000000'], 'transport_routes'],
            ['/library', ['title' => 'Forbidden Book', 'author' => 'X'], 'library_books'],
            ['/users', ['username' => 'forbidden_user', 'password' => 'Str0ng!Pass2026', 'full_name' => 'Forbidden User', 'role_code' => 'staff'], 'users'],
            ['/academic-years', ['label' => '2098/2099', 'start_date' => '2098-09-01', 'end_date' => '2099-06-30'], 'academic_years'],
            ['/students', ['full_name' => 'Forbidden Student', 'gender' => 'm', 'date_of_birth' => '2014-01-01', 'religion' => 'muslim', 'grade_id' => (string) $gradeId], 'students'],
            ['/exams', ['name_en' => 'Forbidden Exam', 'name_ar' => 'ممنوع', 'term' => '1', 'max_score' => '100', 'weight' => '100'], 'exams'],
            ['/attendance', ['class_id' => (string) $classId, 'date' => '2026-11-02', 'statuses' => [(string) $studentId => 'present']], 'attendance_records'],
            ['/timetable', ['class_id' => (string) $classId, 'day_of_week' => '2', 'period_number' => '2', 'subject_id' => (string) $subjectId, 'teacher_id' => (string) $teacherId], 'timetables'],
            ['/assignments', ['teacher_id' => (string) $teacherId, 'subject_id' => (string) $subjectId, 'class_id' => (string) $classId, 'weekly_periods' => '2'], 'teacher_assignments'],
            ['/welfare/discipline', ['student_id' => (string) $studentId, 'incident_date' => '2026-11-03', 'title' => 'Forbidden', 'description' => 'Forbidden'], 'disciplinary_records'],
            ['/welfare/health', ['student_id' => (string) $studentId, 'record_type' => 'clinic_visit', 'date_logged' => '2026-11-03', 'title' => 'Forbidden', 'details' => 'Forbidden'], 'health_records'],
        ];
        foreach ($denials as [$path, $payload, $table]) {
            $payload['_csrf_token'] = $limitedCsrf;
            $before = $count($table);
            same(403, $limited->post($path, $payload)['status'], 'Limited user is denied POST ' . $path);
            same($before, $count($table), 'Denied POST ' . $path . ' persisted nothing');
        }

        // Settings and module toggling update rather than insert, so counting
        // rows would prove nothing: assert the value itself did not change.
        same(403, $limited->post('/settings/profile', ['name' => 'Forbidden School', 'name_ar' => 'ممنوع', '_csrf_token' => $limitedCsrf])['status'], 'Limited user is denied POST /settings/profile');
        ok($scalar('SELECT name FROM schools ORDER BY id LIMIT 1') !== 'Forbidden School', 'Denied profile save changed nothing');

        if ($libraryKey !== '') {
            same(403, $limited->post('/settings/modules/' . $libraryKey . '/toggle', ['enable' => '1', '_csrf_token' => $limitedCsrf])['status'], 'Limited user is denied POST /settings/modules/{key}/toggle');
            same('1', $scalar("SELECT is_enabled FROM installed_modules WHERE module_key = 'library'"), 'Denied module toggle changed nothing');
        }
    }

    // ------------------------------------------------------------- logout
    http_out();
    http_out('Logout');

    $adminPage = $admin->get('/dashboard');
    $logoutToken = $admin->csrf($adminPage['body']);
    if ($logoutToken !== null) {
        $logout = $admin->post('/logout', ['_csrf_token' => $logoutToken]);
        same(302, $logout['status'], 'Logout redirects');
        same(302, $admin->get('/dashboard')['status'], 'Session no longer authenticates after logout');
    } else {
        skipped('Logout (no CSRF token on the dashboard shell)');
    }

    $admin->post('/logout', []);
    same(419, $admin->post('/logout', [])['status'], 'Logout without a CSRF token is rejected');
} finally {
    if (is_resource($serverProcess)) {
        proc_terminate($serverProcess);
        usleep(400000);
        proc_close($serverProcess);
    }
    $serverLogTail = (string) @file_get_contents($serverLog);
    if ($keep) {
        http_out();
        http_out('KEEP: database=' . $database);
        http_out('KEEP: data_path=' . $tempPath);
    } else {
        try {
            $basePdo->exec("DROP DATABASE IF EXISTS `{$database}`");
        } catch (Throwable $e) {
            fwrite(STDERR, "Warning: could not drop {$database}: {$e->getMessage()}\n");
        }
        removeTree($tempPath);
    }
}

http_out();
http_out(str_repeat('-', 60));
http_out("HTTP pipeline: {$passed} passed, {$failed} failed, {$skipped} skipped");

if ($failed > 0) {
    http_out();
    http_out('Failed assertions:');
    foreach ($failures as $failure) {
        http_out('  - ' . $failure);
    }
    $errorLines = array_values(array_filter(
        explode("\n", $serverLogTail),
        static fn (string $line): bool => str_contains($line, 'ERROR') || str_contains($line, 'PHP Fatal') || str_contains($line, 'Uncaught')
    ));
    if ($errorLines !== []) {
        http_out();
        http_out('Server errors:');
        foreach (array_slice($errorLines, -8) as $line) {
            http_out('  ' . $line);
        }
    }
    exit(1);
}

http_out('PASS: routing, session regeneration, CSRF rejection, permission denial, module gate, validation, flash redirects, logout');
exit(0);

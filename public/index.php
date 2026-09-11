<?php

declare(strict_types=1);

// Only relevant under `php -S ... public/index.php` (local dev/testing) --
// PHP's built-in server routes *every* request through this script when
// invoked this way, unlike Apache/XAMPP (production), whose own
// mod_rewrite (public/.htaccess) already serves a real file directly and
// never reaches this script for one. Without this, a vendored CSS/JS asset
// 404s through the application router instead of being served as-is.
// PHP_SAPI is never 'cli-server' under Apache/php-fpm, so this is inert in
// the shipped deployment target.
if (PHP_SAPI === 'cli-server') {
    $assetPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $assetFile = __DIR__ . $assetPath;
    if ($assetPath !== '/' && is_file($assetFile)) {
        return false;
    }
}

// Platform health check (Railway/Render) -- deliberately answered here,
// before bootstrap.php, config, or any database/session code runs at all.
// A health check exists to answer one question only ("is this container
// alive"), not "is the database reachable" (already covered by this app's
// own extensive testing) or "does this route return exactly 200 for an
// unauthenticated request." Found live: pointing the platform healthcheck
// at /login instead left the deployment stuck indefinitely -- every check
// got a 302 (showLogin() redirecting somewhere), which is a perfectly
// correct response for a browser but which the platform's checker doesn't
// follow and doesn't accept as healthy.
if (($_SERVER['REQUEST_URI'] ?? '') === '/healthz') {
    http_response_code(200);
    header('Content-Type: text/plain');
    echo 'ok';
    exit;
}

// TEMPORARY -- diagnosing why this deployment's database connection isn't
// reaching Supabase. Gated on a one-off query token so it isn't just a
// public "here's whether the DB is reachable" probe; removed once diagnosed.
if (($_SERVER['REQUEST_URI'] ?? '') === '/diag?t=nizam2026debug') {
    header('Content-Type: text/plain');
    try {
        $config = require dirname(__DIR__) . '/config/config.php';
    } catch (\Throwable $e) {
        echo "config.php require failed: " . $e->getMessage() . "\n";
        exit;
    }
    echo "driver=" . ($config['driver'] ?? '?') . " host=" . ($config['host'] ?? '?')
        . " port=" . ($config['port'] ?? '?') . " db=" . ($config['database'] ?? '?')
        . " user=" . ($config['username'] ?? '?') . "\n";
    try {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['database']);
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        $result = $pdo->query('SELECT 1')->fetchColumn();
        echo "CONNECTED. SELECT 1 = {$result}\n";
    } catch (\Throwable $e) {
        echo "CONNECTION FAILED: " . get_class($e) . ": " . $e->getMessage() . "\n";
    }
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AcademicYearController;
use App\Controllers\ActivityLogController;
use App\Controllers\AssignmentController;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\ClassController;
use App\Controllers\DashboardController;
use App\Controllers\GradeController;
use App\Controllers\PromotionController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\SetupController;
use App\Controllers\StudentController;
use App\Controllers\SubjectController;
use App\Controllers\TeacherController;
use App\Controllers\UserController;
use App\Middleware\AcademicYearContextMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleGuardMiddleware;
use App\Middleware\SessionMiddleware;
use App\Middleware\SetupGateMiddleware;
use App\Request;
use App\Response;
use App\Router;

Response::securityHeaders();

$session = new SessionMiddleware();
$csrf = new CsrfMiddleware();
$yearContext = new AcademicYearContextMiddleware();
$setupGate = new SetupGateMiddleware();

$request = new Request();

// The Setup Wizard gate runs before anything else, including the locale
// switch and routing: while setup is incomplete nothing but /setup/* (and
// /lang) is reachable, and once complete /setup/* is permanently
// unreachable -- see SetupGateMiddleware's own docblock.
if (!$setupGate->handle($request)) {
    exit;
}

// Locale switch -- infrastructure-level i18n foundation (§C). The actual
// Bootstrap RTL/LTR stylesheet swap is a vendored public/ asset; this is
// the session-side half: which language is active.
if ($request->path() === '/lang') {
    $session->handle($request);
    $to = $request->query('to', 'ar');
    $_SESSION['locale'] = in_array($to, ['ar', 'en'], true) ? $to : 'ar';
    // Redirect back to where the request came from -- but ONLY the path,
    // and ONLY if it's actually this same site. Blindly trusting the
    // Referer header as a redirect target is an open-redirect vector: a
    // link on an attacker's page that points here would carry that page as
    // the Referer, and redirecting there verbatim would bounce the victim
    // back to it looking like it came from a trusted flow.
    $back = '/login';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    // PHP_URL_HOST never includes the port (it's a separate URL component),
    // but $_SERVER['HTTP_HOST'] does (e.g. "127.0.0.1:8080") -- comparing
    // them directly would reject even a genuinely same-origin Referer.
    $currentHost = explode(':', $_SERVER['HTTP_HOST'] ?? '', 2)[0];
    if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === $currentHost) {
        $back = (parse_url($referer, PHP_URL_PATH) ?: '/login');
    }
    Response::redirect($back);
    exit;
}

$router = new Router();

if ($request->path() === '/') {
    Response::redirect('/login');
    exit;
}

// ---------------------------------------------------------------- Setup
$router->get('/setup', [SetupController::class, 'show'], [$session, $csrf]);
$router->post('/setup/database', [SetupController::class, 'saveDatabase'], [$session, $csrf]);
$router->post('/setup/schema', [SetupController::class, 'runSchema'], [$session, $csrf]);
$router->post('/setup/school', [SetupController::class, 'saveSchool'], [$session, $csrf]);
$router->post('/setup/year', [SetupController::class, 'saveYear'], [$session, $csrf]);
$router->post('/setup/admin', [SetupController::class, 'saveAdmin'], [$session, $csrf]);
$router->post('/setup/complete', [SetupController::class, 'complete'], [$session, $csrf]);

// ---------------------------------------------------------------- Auth
$router->get('/login', [AuthController::class, 'showLogin'], [$session, $csrf]);
$router->post('/login', [AuthController::class, 'login'], [$session, $csrf]);
$router->post('/logout', [AuthController::class, 'logout'], [$session, $csrf, RoleGuardMiddleware::requiresAuth()]);

// ---------------------------------------------------------------- Dashboard
$router->get('/dashboard', [DashboardController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresAuth()]);

// ---------------------------------------------------------------- Phase 6
// Every module below follows §Q's plain-CRUD route convention (GET
// /{module}, GET /{module}/create + POST /{module}, GET /{module}/{id}/edit
// + POST /{module}/{id}, POST /{module}/{id}/archive + /restore); the
// workflow-specific routes (promotion, activate/close/reopen,
// reassign-class) are the ones §Q's route table names explicitly. Every
// route carries $yearContext -- Phase 6 is where AcademicYearContext
// actually starts mattering beyond the dashboard -- and the exact §J
// permission code for that action, never a bare requiresAuth().

// Academic Years -- academic_years.view / .manage / .close
$router->get('/academic-years', [AcademicYearController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('academic_years.view')]);
$router->get('/academic-years/create', [AcademicYearController::class, 'create'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('academic_years.manage')]);
$router->post('/academic-years', [AcademicYearController::class, 'store'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('academic_years.manage')]);
$router->post('/academic-years/{id}/activate', [AcademicYearController::class, 'activate'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('academic_years.manage')]);
$router->post('/academic-years/{id}/close', [AcademicYearController::class, 'close'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('academic_years.close')]);
$router->post('/academic-years/{id}/reopen', [AcademicYearController::class, 'reopen'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('academic_years.close')]);

// Grades -- grades.view / .manage (§Q screen inventory: "Grades list" only, inline add/edit)
$router->get('/grades', [GradeController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('grades.view')]);
$router->post('/grades', [GradeController::class, 'store'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('grades.manage')]);
$router->post('/grades/{id}', [GradeController::class, 'update'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('grades.manage')]);
$router->post('/grades/{id}/archive', [GradeController::class, 'archive'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('grades.manage')]);
$router->post('/grades/{id}/restore', [GradeController::class, 'restore'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('grades.manage')]);

// Subjects -- subjects.view / .manage
$router->get('/subjects', [SubjectController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('subjects.view')]);
$router->get('/subjects/create', [SubjectController::class, 'create'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('subjects.manage')]);
$router->post('/subjects', [SubjectController::class, 'store'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('subjects.manage')]);
$router->get('/subjects/{id}/edit', [SubjectController::class, 'edit'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('subjects.manage')]);
$router->post('/subjects/{id}', [SubjectController::class, 'update'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('subjects.manage')]);
$router->post('/subjects/{id}/archive', [SubjectController::class, 'archive'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('subjects.manage')]);
$router->post('/subjects/{id}/restore', [SubjectController::class, 'restore'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('subjects.manage')]);

// Classes -- classes.view / .manage
$router->get('/classes', [ClassController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('classes.view')]);
$router->get('/classes/create', [ClassController::class, 'create'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('classes.manage')]);
$router->post('/classes', [ClassController::class, 'store'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('classes.manage')]);
$router->get('/classes/{id}/edit', [ClassController::class, 'edit'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('classes.manage')]);
$router->post('/classes/{id}', [ClassController::class, 'update'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('classes.manage')]);
$router->post('/classes/{id}/archive', [ClassController::class, 'archive'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('classes.manage')]);
$router->post('/classes/{id}/restore', [ClassController::class, 'restore'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('classes.manage')]);

// Teachers -- teachers.view / .create / .edit / .archive (no separate .restore code -- archive covers both directions)
$router->get('/teachers', [TeacherController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('teachers.view')]);
$router->get('/teachers/archived', [TeacherController::class, 'archived'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('teachers.view')]);
$router->get('/teachers/create', [TeacherController::class, 'create'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('teachers.create')]);
$router->post('/teachers', [TeacherController::class, 'store'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('teachers.create')]);
$router->get('/teachers/{id}', [TeacherController::class, 'show'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('teachers.view')]);
$router->get('/teachers/{id}/edit', [TeacherController::class, 'edit'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('teachers.edit')]);
$router->post('/teachers/{id}', [TeacherController::class, 'update'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('teachers.edit')]);
$router->post('/teachers/{id}/archive', [TeacherController::class, 'archive'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('teachers.archive')]);
$router->post('/teachers/{id}/restore', [TeacherController::class, 'restore'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('teachers.archive')]);
$router->get('/teachers/{id}/photo', [TeacherController::class, 'photo'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('teachers.view')]);

// Students -- students.view / .create / .edit / .archive / .restore / .promote
$router->get('/students', [StudentController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.view')]);
$router->get('/students/archived', [StudentController::class, 'archived'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.view')]);
$router->get('/students/create', [StudentController::class, 'create'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.create')]);
$router->post('/students', [StudentController::class, 'store'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.create')]);
// /students/promotion must be registered before /students/{id} -- both are
// literal-vs-\d+ so they can't actually collide, but this keeps the two
// "families" of student routes visually grouped in registration order.
$router->get('/students/promotion', [PromotionController::class, 'preview'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.promote')]);
$router->post('/students/promotion', [PromotionController::class, 'confirm'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.promote')]);
$router->get('/students/{id}', [StudentController::class, 'show'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.view')]);
$router->get('/students/{id}/edit', [StudentController::class, 'edit'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.edit')]);
$router->post('/students/{id}', [StudentController::class, 'update'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.edit')]);
$router->post('/students/{id}/archive', [StudentController::class, 'archive'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.archive')]);
$router->post('/students/{id}/restore', [StudentController::class, 'restore'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.restore')]);
$router->post('/students/{id}/reassign-class', [StudentController::class, 'reassignClass'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.edit')]);
$router->post('/students/{id}/undo-promotion', [PromotionController::class, 'undo'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.promote')]);
$router->get('/students/{id}/photo', [StudentController::class, 'photo'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('students.view')]);

// Assignments -- assignments.view / .manage
$router->get('/assignments', [AssignmentController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('assignments.view')]);
$router->get('/assignments/workload', [AssignmentController::class, 'workloadSummary'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('assignments.view')]);
$router->get('/assignments/create', [AssignmentController::class, 'create'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('assignments.manage')]);
$router->post('/assignments', [AssignmentController::class, 'store'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('assignments.manage')]);
$router->post('/assignments/{id}', [AssignmentController::class, 'update'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('assignments.manage')]);
$router->post('/assignments/{id}/archive', [AssignmentController::class, 'archive'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('assignments.manage')]);

// Backups -- backups.run / .restore
$router->get('/backups', [BackupController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('backups.run')]);
$router->post('/backups', [BackupController::class, 'store'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('backups.run')]);
$router->get('/backups/{id}/download', [BackupController::class, 'download'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('backups.run')]);
$router->post('/backups/restore', [BackupController::class, 'restore'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('backups.restore')]);
// No session/CSRF/RoleGuard: a machine caller (external scheduler), not a
// browser -- BackupController::scheduled() does its own bearer-token check.
$router->post('/backups/scheduled', [BackupController::class, 'scheduled']);

// Reports (§K) -- reports.view / .export, granted to BOTH admin and staff
// per §J (unlike almost every other module's "manage" split). Read-only:
// no POST route exists here at all, so CSRF validation never actually
// triggers for these -- $csrf stays in the pipeline only so the shared
// layout's own logout form still has a fresh token to render.
$router->get('/reports', [ReportController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('reports.view')]);
$router->get('/reports/{key}', [ReportController::class, 'show'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('reports.view')]);
$router->get('/reports/{key}/export/{format}', [ReportController::class, 'export'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('reports.export')]);

// Users -- users.manage (Administrator only, §J: no separate view code)
$router->get('/users', [UserController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('users.manage')]);
$router->post('/users', [UserController::class, 'store'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('users.manage')]);
$router->post('/users/{id}', [UserController::class, 'update'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('users.manage')]);
$router->post('/users/{id}/archive', [UserController::class, 'archive'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('users.manage')]);
$router->post('/users/{id}/restore', [UserController::class, 'restore'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('users.manage')]);

// Settings -- settings.manage, five grouped screens per §O-21
$router->get('/settings/profile', [SettingsController::class, 'profile'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('settings.manage')]);
$router->post('/settings/profile', [SettingsController::class, 'saveProfile'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('settings.manage')]);
$router->get('/settings/localization', [SettingsController::class, 'localization'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('settings.manage')]);
$router->post('/settings/localization', [SettingsController::class, 'saveLocalization'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('settings.manage')]);
$router->get('/settings/academic', [SettingsController::class, 'academic'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('settings.manage')]);
$router->post('/settings/academic', [SettingsController::class, 'saveAcademic'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('settings.manage')]);
$router->get('/settings/security', [SettingsController::class, 'security'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('settings.manage')]);
$router->post('/settings/security', [SettingsController::class, 'saveSecurity'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('settings.manage')]);
$router->get('/settings/backup', [SettingsController::class, 'backup'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('settings.manage')]);
$router->post('/settings/backup', [SettingsController::class, 'saveBackup'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('settings.manage')]);
// Shown in the app shell for every authenticated role regardless of settings.manage -- requiresAuth() only, matching decision #6/§J "shown on the dashboard."
$router->get('/logo', [SettingsController::class, 'logo'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresAuth()]);

// Activity Log -- activity_log.view, held by both roles (§J), scope enforced in the controller
$router->get('/activity-log', [ActivityLogController::class, 'index'], [$session, $csrf, $yearContext, RoleGuardMiddleware::requiresPermission('activity_log.view')]);

$router->dispatch($request);

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Database;
use App\Flash;
use App\Repositories\AcademicYearRepository;
use App\Repositories\SchoolRepository;
use App\Request;
use App\Services\AuthService;
use App\Services\EnvironmentCheckService;
use App\Services\MigrationService;
use App\Services\SeedService;
use App\Services\SettingsService;
use App\Services\SetupStatusService;
use DateTime;
use PDO;
use PDOException;

/**
 * Nizam -- first-run Setup Wizard (§N, hardened by §O-6). One GET entry
 * point that always shows whatever SetupStatusService says the current step
 * is; step-specific POST actions that each re-check they're still the
 * current step before doing anything, so replaying an old form (browser
 * back button, a stale tab) can't create a duplicate school/year/admin or
 * skip ahead. Every POST goes through the existing CsrfMiddleware -- wired
 * in public/index.php, not re-implemented here.
 */
final class SetupController extends Controller
{
    public function show(Request $request): void
    {
        $status = new SetupStatusService();
        $step = $status->currentStep();

        $data = ['error' => null];

        switch ($step) {
            case SetupStatusService::STEP_DATABASE:
                $data += [
                    'host' => '127.0.0.1', 'port' => '3306', 'database' => 'nizam', 'username' => 'root',
                    'environment' => (new EnvironmentCheckService())->check(),
                ];
                $this->view('setup/database', $data);
                return;
            case SetupStatusService::STEP_SCHEMA:
                $pending = (new MigrationService())->pendingCount(Database::connection(), dirname(__DIR__, 2) . '/database/migrations');
                $this->view('setup/schema', $data + ['pending' => $pending]);
                return;
            case SetupStatusService::STEP_SCHOOL:
                $this->view('setup/school', $data);
                return;
            case SetupStatusService::STEP_YEAR:
                $this->view('setup/year', $data);
                return;
            case SetupStatusService::STEP_ADMIN:
                $this->view('setup/admin', $data);
                return;
            default:
                $this->view('setup/done', []);
        }
    }

    public function saveDatabase(Request $request): void
    {
        if ((new SetupStatusService())->currentStep() !== SetupStatusService::STEP_DATABASE) {
            $this->redirect('/setup');
            return;
        }

        $environment = (new EnvironmentCheckService())->check();
        if (!$environment['ok']) {
            // Same rule as the view's disabled form, enforced again here --
            // a client could POST directly, bypassing the disabled button.
            $this->view('setup/database', [
                'error' => __('setup.environment.required_missing', ['list' => implode(', ', $environment['missingRequired'])]),
                'host' => $request->post('host', '') ?: '', 'port' => $request->post('port', '3306') ?: '3306',
                'database' => $request->post('database', '') ?: '', 'username' => $request->post('username', '') ?: '',
                'environment' => $environment,
            ]);
            return;
        }

        $host = $request->post('host', '') ?: '';
        $port = (int) ($request->post('port', '3306') ?: 3306);
        $name = $request->post('database', '') ?: '';
        $username = $request->post('username', '') ?: '';
        $password = $request->post('password', '') ?: '';

        if ($host === '' || $name === '' || $username === '') {
            $this->view('setup/database', [
                'error' => __('validation.required'),
                'host' => $host, 'port' => (string) $port, 'database' => $name, 'username' => $username,
                'environment' => $environment,
            ]);
            return;
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        try {
            new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            $this->view('setup/database', [
                'error' => __('setup.database.connect_failed', ['detail' => $e->getMessage()]),
                'host' => $host, 'port' => (string) $port, 'database' => $name, 'username' => $username,
                'environment' => $environment,
            ]);
            return;
        }

        $this->writeConfigFile($host, $port, $name, $username, $password);
        Flash::set('success', __('setup.database.already_connected'));
        $this->redirect('/setup');
    }

    public function runSchema(Request $request): void
    {
        if ((new SetupStatusService())->currentStep() !== SetupStatusService::STEP_SCHEMA) {
            $this->redirect('/setup');
            return;
        }

        $pdo = Database::connection();
        $base = dirname(__DIR__, 2) . '/database';

        $migrationResult = (new MigrationService())->run($pdo, $base . '/migrations');
        if ($migrationResult['failure'] !== null) {
            $this->view('setup/schema', [
                'error' => __('setup.schema.failed', ['detail' => $migrationResult['failure']['error']]),
                'pending' => 1,
            ]);
            return;
        }

        $seedResult = (new SeedService())->run($pdo, $base . '/seeds');
        if ($seedResult['failure'] !== null) {
            $this->view('setup/schema', [
                'error' => __('setup.schema.failed', ['detail' => $seedResult['failure']['error']]),
                'pending' => 0,
            ]);
            return;
        }

        Flash::set('success', __('setup.schema.done', ['count' => count($migrationResult['applied'])]));
        $this->redirect('/setup');
    }

    public function saveSchool(Request $request): void
    {
        if ((new SetupStatusService())->currentStep() !== SetupStatusService::STEP_SCHOOL) {
            $this->redirect('/setup');
            return;
        }

        $name = $request->post('name', '') ?: '';
        $nameAr = $request->post('name_ar', '') ?: '';
        $address = $request->post('address', '') ?: null;
        $phone = $request->post('phone', '') ?: null;

        if ($name === '' || $nameAr === '') {
            $this->view('setup/school', ['error' => __('validation.required')]);
            return;
        }

        (new SchoolRepository())->create($name, $nameAr, $address, $phone);
        $this->redirect('/setup');
    }

    public function saveYear(Request $request): void
    {
        if ((new SetupStatusService())->currentStep() !== SetupStatusService::STEP_YEAR) {
            $this->redirect('/setup');
            return;
        }

        $label = $request->post('label', '') ?: '';
        $start = $request->post('start_date', '') ?: '';
        $end = $request->post('end_date', '') ?: '';

        if ($label === '' || $start === '' || $end === '') {
            $this->view('setup/year', ['error' => __('validation.required')]);
            return;
        }
        if (!preg_match('/^\d{4}\/\d{4}$/', $label)) {
            $this->view('setup/year', ['error' => __('validation.year_label_format')]);
            return;
        }
        $startTs = DateTime::createFromFormat('Y-m-d', $start);
        $endTs = DateTime::createFromFormat('Y-m-d', $end);
        if ($startTs === false || $endTs === false) {
            $this->view('setup/year', ['error' => __('validation.required')]);
            return;
        }
        if ($endTs <= $startTs) {
            $this->view('setup/year', ['error' => __('validation.date_order')]);
            return;
        }

        (new AcademicYearRepository())->createFirst($label, $start, $end);
        $this->redirect('/setup');
    }

    public function saveAdmin(Request $request): void
    {
        if ((new SetupStatusService())->currentStep() !== SetupStatusService::STEP_ADMIN) {
            $this->redirect('/setup');
            return;
        }

        $fullName = $request->post('full_name', '') ?: '';
        $username = $request->post('username', '') ?: '';
        $password = $request->post('password', '') ?: '';
        $confirm = $request->post('password_confirm', '') ?: '';

        if ($fullName === '' || $username === '' || $password === '') {
            $this->view('setup/admin', ['error' => __('validation.required')]);
            return;
        }
        if (strlen($password) < 8) {
            $this->view('setup/admin', ['error' => __('validation.password_length')]);
            return;
        }
        if ($password !== $confirm) {
            $this->view('setup/admin', ['error' => __('validation.password_mismatch')]);
            return;
        }

        $pdo = Database::connection();
        $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
        $exists->execute(['u' => $username]);
        if ((int) $exists->fetchColumn() > 0) {
            $this->view('setup/admin', ['error' => __('validation.username_taken')]);
            return;
        }

        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'admin'")->fetchColumn();
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, full_name, role_id, is_active) VALUES (:u, :h, :f, :r, 1)'
        );
        $stmt->execute([
            'u' => $username,
            'h' => password_hash($password, PASSWORD_DEFAULT),
            'f' => $fullName,
            'r' => $roleId,
        ]);

        // Reuse the existing auth infrastructure to log the new admin in
        // immediately, rather than making them re-type credentials they
        // just entered -- per this phase's own "use existing authentication
        // infrastructure, do not duplicate" rule. The setup.completed flag
        // is deliberately NOT set yet: currentStep() now sees every piece of
        // data it checks for (school, year, admin) and reports STEP_DONE,
        // which is what lets a genuine confirmation screen render before the
        // wizard locks itself -- see complete() below.
        (new AuthService())->attempt($username, $password, $request->ip());

        $this->redirect('/setup');
    }

    /**
     * The wizard's actual lock point (§O-6): flips settings.system.setup_completed,
     * after which SetupGateMiddleware refuses /setup/* to everyone, forever,
     * with no in-app way back in. Separated from saveAdmin() so "Setup
     * Complete" is a real confirmation screen the admin sees and
     * acknowledges, not a silent side effect of the previous step.
     */
    public function complete(Request $request): void
    {
        if ((new SetupStatusService())->currentStep() !== SetupStatusService::STEP_DONE) {
            $this->redirect('/setup');
            return;
        }

        SettingsService::set('system.setup_completed', true, 'bool');
        $this->redirect('/dashboard');
    }

    /**
     * Emergency admin recovery route — ONLY accessible when users table is
     * literally empty (zero rows). Gates on live ground truth (no accounts
     * exist), NOT on setup_completed flag (which can drift from reality).
     * 
     * Reuses exact validation/creation logic from saveAdmin() above, but
     * does NOT touch setup_completed or re-run schema migrations. This is
     * NOT part of the setup wizard — it's a narrow escape hatch for
     * production deployments where setup_completed was force-set via
     * direct database UPDATE but no admin was actually created.
     * 
     * Once ANY user exists, this route behaves as if it doesn't exist
     * (404 or redirect to login) — mirrors §O-6's "no exceptions" spirit.
     */
    public function showRecoverAdmin(Request $request): void
    {
        // Check ground truth: do ANY users exist?
        $pdo = Database::connection();
        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        
        if ($userCount > 0) {
            // Users exist — this route is inert, redirect to login
            $this->redirect('/login');
            return;
        }

        // Zero users — show the recovery form
        $this->view('setup/recover-admin', ['error' => null]);
    }

    public function recoverAdmin(Request $request): void
    {
        // Re-check ground truth on POST (prevent race condition)
        $pdo = Database::connection();
        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        
        if ($userCount > 0) {
            // Users exist now — someone else created one, abort
            $this->redirect('/login');
            return;
        }

        // Exact same validation as saveAdmin()
        $fullName = $request->post('full_name', '') ?: '';
        $username = $request->post('username', '') ?: '';
        $password = $request->post('password', '') ?: '';
        $confirm = $request->post('password_confirm', '') ?: '';

        if ($fullName === '' || $username === '' || $password === '') {
            $this->view('setup/recover-admin', ['error' => __('validation.required')]);
            return;
        }
        if (strlen($password) < 8) {
            $this->view('setup/recover-admin', ['error' => __('validation.password_length')]);
            return;
        }
        if ($password !== $confirm) {
            $this->view('setup/recover-admin', ['error' => __('validation.password_mismatch')]);
            return;
        }

        // Username check is technically redundant (users table is empty) but
        // kept for consistency with saveAdmin() logic
        $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
        $exists->execute(['u' => $username]);
        if ((int) $exists->fetchColumn() > 0) {
            $this->view('setup/recover-admin', ['error' => __('validation.username_taken')]);
            return;
        }

        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'admin'")->fetchColumn();
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, full_name, role_id, is_active) VALUES (:u, :h, :f, :r, 1)'
        );
        $stmt->execute([
            'u' => $username,
            'h' => password_hash($password, PASSWORD_DEFAULT),
            'f' => $fullName,
            'r' => $roleId,
        ]);

        // Log this recovery action for audit trail
        $pdo->prepare(
            "INSERT INTO activity_log (user_id, action, entity_type, entity_id, details) 
             VALUES (:uid, 'admin.recover', 'user', :eid, :details)"
        )->execute([
            'uid' => $pdo->lastInsertId(),
            'eid' => $pdo->lastInsertId(),
            'details' => 'Emergency admin account created via /setup/recover-admin',
        ]);

        // Auto-login the new admin
        (new AuthService())->attempt($username, $password, $request->ip());

        Flash::success(__('setup.admin_recovered'));
        $this->redirect('/dashboard');
    }

    private function writeConfigFile(string $host, int $port, string $database, string $username, string $password): void
    {
        $export = var_export([
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
        ], true);

        $contents = "<?php\n/**\n * Nizam -- generated by the Setup Wizard. Holds real local credentials --\n"
            . " * never committed with the rest of the source tree.\n */\nreturn {$export};\n";

        $configDir = dataPath() . '/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        file_put_contents($configDir . '/config.php', $contents);
    }
}

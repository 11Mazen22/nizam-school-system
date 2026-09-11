<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\ErrorHandler;
use App\Flash;
use App\Repositories\BackupRepository;
use App\Request;
use App\Response;
use App\Services\AuthService;
use App\Services\BackupService;
use App\Services\RestoreService;
use RuntimeException;

/**
 * Nizam -- §I.2/§I.3 Backup & Restore. Both actions are admin-only
 * (backups.run / backups.restore, §J), enforced by RoleGuardMiddleware on
 * the route -- nothing here re-checks or substitutes for that.
 */
final class BackupController extends Controller
{
    private const CONFIRM_PHRASE = 'RESTORE';

    public function __construct(
        private readonly BackupRepository $repo = new BackupRepository(),
        private readonly BackupService $backups = new BackupService(),
        private readonly RestoreService $restore = new RestoreService(),
    ) {
    }

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', '1'));
        $limit = 20;

        $this->view('backups/index', [
            'backups' => $this->repo->getPaginated($limit, ($page - 1) * $limit),
            'page' => $page,
            'limit' => $limit,
            'total' => $this->repo->getTotalCount(),
        ]);
    }

    public function store(Request $request): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        try {
            $this->backups->run('manual', $userId, $request->post('notes'));
            Flash::set('success', __('backups.create_success'));
        } catch (RuntimeException) {
            Flash::set('danger', __('backups.create_failed'));
        }

        $this->redirect('/backups');
    }

    /**
     * Machine-triggered backup (type 'auto', §I.2's schema already reserves
     * this value) -- no browser session, so none of the usual session/CSRF/
     * RoleGuard middleware applies here; a single shared bearer token
     * (SCHEDULED_BACKUP_TOKEN env var, timing-safe compared) is the entire
     * auth story, matching what a machine-to-machine call actually needs.
     * Neither deployment target has a built-in scheduler this app can rely
     * on (Render's free tier has no cron; the Windows install has no
     * always-on process beyond Apache/MariaDB themselves) -- the intended
     * caller is an external trigger (a GitHub Actions scheduled workflow for
     * the online deployment, optionally a Windows Scheduled Task hitting
     * localhost for the offline one) calling this once a day. No route
     * exists for THIS specific request without the correct token, by
     * design -- see packaging-online/README.md for how it's wired up.
     */
    public function scheduled(Request $request): void
    {
        $configured = getenv('SCHEDULED_BACKUP_TOKEN');
        // Apache/mod_php doesn't pass Authorization through to $_SERVER by
        // default (apache-nizam.conf's own SetEnvIf works around this at
        // the vhost level) -- apache_request_headers() reads it directly
        // from the request regardless, so this stays correct even if that
        // vhost-level workaround is ever missing (e.g. a differently
        // configured host).
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $header = $value;
                    break;
                }
            }
        }
        $provided = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

        header('Content-Type: text/plain');
        if ($configured === false || $configured === '' || !hash_equals($configured, $provided)) {
            http_response_code(401);
            echo 'unauthorized';
            return;
        }

        try {
            $this->backups->run('auto', null, 'Automated scheduled backup');
            echo 'ok';
        } catch (RuntimeException) {
            http_response_code(500);
            echo 'backup_failed';
        }
    }

    /** §S-10: resolved by database id, never a client-supplied path. */
    public function download(Request $request): void
    {
        $id = $request->paramInt('id');
        $backup = $id !== null ? $this->repo->findById($id) : null;
        if ($backup === null) {
            ErrorHandler::renderNotFound();
            return;
        }

        try {
            $contents = $this->backups->retrieve($backup['filename']);
        } catch (RuntimeException) {
            ErrorHandler::renderNotFound();
            return;
        }

        Response::download($contents, basename($backup['filename']), 'application/sql');
    }

    public function restore(Request $request): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            Flash::set('danger', __('backups.upload_required'));
            $this->redirect('/backups');
            return;
        }

        // §S-16: extension is only ever a first, cheap filter -- the file's
        // own signature/checksum (validated inside RestoreService) is the
        // real authority. Rejecting an obviously-wrong file here just gives
        // a faster, friendlier message than reading its content first.
        $originalName = (string) $_FILES['backup_file']['name'];
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'sql') {
            Flash::set('danger', __('backups.invalid_extension'));
            $this->redirect('/backups');
            return;
        }

        // §I.3 step 2: a typed confirmation phrase, not just an OK button.
        // Checked server-side too -- the view disables the submit button
        // until this matches, but that is a UI convenience, not the
        // enforcement; a direct POST must be rejected the same way.
        if ($request->post('confirm_phrase') !== self::CONFIRM_PHRASE) {
            Flash::set('danger', __('backups.confirm_phrase_mismatch'));
            $this->redirect('/backups');
            return;
        }

        try {
            $this->restore->execute($_FILES['backup_file']['tmp_name'], $userId);
        } catch (RuntimeException $e) {
            Flash::set('danger', $this->restoreErrorMessage($e->getMessage()));
            $this->redirect('/backups');
            return;
        }

        // §I.3 step 6: forced re-login. The users table this session was
        // authenticated against no longer necessarily exists in the form it
        // did a moment ago -- continuing the session would trust cached
        // identity/permissions that may no longer correspond to anything.
        (new AuthService())->logout();
        Flash::set('success', __('backups.restore_success_relogin'));
        $this->redirect('/login');
    }

    /** Maps RestoreService's short reason codes to a specific, translated, non-technical message (§Q error catalog). */
    private function restoreErrorMessage(string $reason): string
    {
        if (str_starts_with($reason, 'restore_failed:')) {
            $filename = substr($reason, strlen('restore_failed:'));
            return __('backups.error.restore_failed', ['filename' => $filename]);
        }

        return match ($reason) {
            'file_not_found' => __('backups.error.file_not_found'),
            'invalid_signature' => __('backups.error.invalid_signature'),
            'unsupported_format_version' => __('backups.error.unsupported_format_version'),
            'checksum_mismatch' => __('backups.error.checksum_mismatch'),
            'schema_version_mismatch' => __('backups.error.schema_version_mismatch'),
            'structural_no_admin' => __('backups.error.structural_no_admin'),
            'structural_no_year' => __('backups.error.structural_no_year'),
            'structural_bad_enrollment', 'structural_bad_assignment' => __('backups.error.structural_bad_fk'),
            default => __('backups.error.generic'),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Request;
use App\Services\RosterVerificationService;
use RuntimeException;

/**
 * Nizam -- roster verification screens.
 *
 * The point of this module is a staff member opening one class at a time,
 * reading the stored students next to the printed page, correcting anything
 * that was misread, and signing the class off. Nothing is verified
 * automatically: an imported roster is "pending" until a person says they
 * checked it.
 */
final class RosterVerificationController extends Controller
{
    public function __construct(
        private readonly RosterVerificationService $service = new RosterVerificationService(),
    ) {
    }

    /** GET /students/verification */
    public function index(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        $classes = $yearId === null ? [] : $this->service->overview($yearId);

        $this->view('students/verification', [
            'classes' => $classes,
            'activeYear' => AcademicYearContext::label(),
        ]);
    }

    /** GET /students/verification/{id} */
    public function show(Request $request): void
    {
        $classId = $request->paramInt('id');
        $yearId = AcademicYearContext::activeYearId();
        if ($classId === null || $yearId === null) {
            $this->redirect('/students/verification');
            return;
        }

        try {
            $detail = $this->service->detail($classId, $yearId);
        } catch (RuntimeException) {
            Flash::set('danger', __('verification.error_class_not_found'));
            $this->redirect('/students/verification');
            return;
        }

        $this->view('students/verification-class', $detail + ['hasPage' => $this->pagePath($detail['class']) !== null]);
    }

    /** POST /students/verification/{id} -- save corrections. */
    public function save(Request $request): void
    {
        $classId = $request->paramInt('id');
        $yearId = AcademicYearContext::activeYearId();
        if ($classId === null || $yearId === null) {
            $this->redirect('/students/verification');
            return;
        }

        try {
            $changed = $this->service->saveCorrections(
                $classId,
                $yearId,
                $request->postMap('name'),
                $request->postMap('attribute')
            );
            Flash::set('success', strtr(__('verification.corrections_saved'), ['{count}' => (string) $changed]));
        } catch (RuntimeException $e) {
            Flash::set('danger', $this->errorMessage($e->getMessage()));
        }

        $this->redirect('/students/verification/' . $classId);
    }

    /** POST /students/verification/{id}/confirm */
    public function confirm(Request $request): void
    {
        $classId = $request->paramInt('id');
        $yearId = AcademicYearContext::activeYearId();
        if ($classId === null || $yearId === null) {
            $this->redirect('/students/verification');
            return;
        }

        try {
            $this->service->confirm(
                $classId,
                $yearId,
                $request->post('note'),
                isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
            );
            Flash::set('success', __('verification.verified_success'));
        } catch (RuntimeException $e) {
            Flash::set('danger', $this->errorMessage($e->getMessage()));
        }

        $this->redirect('/students/verification/' . $classId);
    }

    /** POST /students/verification/{id}/reopen */
    public function reopen(Request $request): void
    {
        $classId = $request->paramInt('id');
        $yearId = AcademicYearContext::activeYearId();
        if ($classId === null || $yearId === null) {
            $this->redirect('/students/verification');
            return;
        }

        $this->service->reopen($classId, $yearId);
        Flash::set('success', __('verification.reopened'));
        $this->redirect('/students/verification/' . $classId);
    }

    /**
     * GET /students/verification/{id}/page -- the printed roster page itself.
     *
     * Streamed from the source package rather than copied into the web root:
     * the page contains minors' names, so it is only ever readable through
     * this permission-checked route, never as a public asset.
     */
    public function page(Request $request): void
    {
        $classId = $request->paramInt('id');
        $yearId = AcademicYearContext::activeYearId();
        if ($classId === null || $yearId === null) {
            http_response_code(404);
            echo 'not found';
            return;
        }

        $class = null;
        try {
            $class = $this->service->detail($classId, $yearId)['class'];
        } catch (RuntimeException) {
            http_response_code(404);
            echo 'not found';
            return;
        }

        $path = $this->pagePath($class);
        if ($path === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'reference page not installed';
            return;
        }

        header('Content-Type: image/png');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
    }

    /** @param array<string,mixed> $class */
    private function pagePath(array $class): ?string
    {
        $name = strtoupper((string) ($class['name'] ?? ''));
        if (preg_match('/^([AB])(\d{1,2})$/', $name, $match) !== 1) {
            return null;
        }

        $base = realpath(dirname(__DIR__, 2) . '/docs/source/rosters');
        if ($base === false) {
            return null;
        }
        $path = realpath($base . DIRECTORY_SEPARATOR . $match[1] . DIRECTORY_SEPARATOR . $name . '.png');
        if ($path === false || !is_file($path) || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }

    private function errorMessage(string $code): string
    {
        return match ($code) {
            'name_required' => __('verification.error_name_required'),
            'name_too_long' => __('verification.error_name_too_long'),
            'duplicate_name' => __('verification.error_duplicate_name'),
            'class_empty' => __('verification.error_class_empty'),
            'missing_attribute' => __('verification.error_missing_attribute'),
            'attribute_too_long', 'note_too_long' => __('verification.error_too_long'),
            'class_not_found' => __('verification.error_class_not_found'),
            default => __('validation.required'),
        };
    }
}

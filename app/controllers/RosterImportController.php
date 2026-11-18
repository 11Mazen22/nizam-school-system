<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Request;
use App\Services\RosterImportService;
use RuntimeException;
use Throwable;

/**
 * Nizam -- student roster CSV import: template, upload, preview, commit.
 *
 * The preview is not decoration. Parsed rows are staged in the session, shown
 * with their per-row verdict, and only written when the operator confirms;
 * the commit re-validates so a row that became a duplicate in between is not
 * silently inserted.
 */
final class RosterImportController extends Controller
{
    private const SESSION_KEY = 'roster_import';

    public function show(Request $request): void
    {
        $this->view('students/import-roster', [
            'headers' => RosterImportService::HEADERS,
        ]);
    }

    public function template(Request $request): void
    {
        $csv = (new RosterImportService())->templateCsv(currentLocale());

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="student-roster-template.csv"');
        header('Content-Length: ' . strlen($csv));
        header('Cache-Control: max-age=0');
        echo $csv;
        exit;
    }

    public function preview(Request $request): void
    {
        $file = $_FILES['roster_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Flash::set('danger', __('roster.file_missing'));
            $this->redirect('/students/import/roster');
            return;
        }

        try {
            $service = new RosterImportService();
            $parsed = $service->parse((string) $file['tmp_name']);
            if ($parsed === []) {
                Flash::set('warning', __('roster.no_rows'));
                $this->redirect('/students/import/roster');
                return;
            }
            $result = $service->validate($parsed);
        } catch (RuntimeException $e) {
            Flash::set('danger', __('roster.' . $e->getMessage()));
            $this->redirect('/students/import/roster');
            return;
        } catch (Throwable $e) {
            error_log('Roster import parse error: ' . $e->getMessage());
            Flash::set('danger', __('roster.invalid_format'));
            $this->redirect('/students/import/roster');
            return;
        }

        $token = bin2hex(random_bytes(12));
        $staged = $_SESSION[self::SESSION_KEY] ?? [];
        $staged = array_filter($staged, static fn (array $batch): bool => ($batch['created_at'] ?? 0) > time() - 3600);
        $staged[$token] = [
            'rows' => $result['rows'],
            'file' => (string) ($file['name'] ?? ''),
            'created_at' => time(),
        ];
        $_SESSION[self::SESSION_KEY] = $staged;

        $this->view('students/import-roster-preview', [
            'token' => $token,
            'rows' => $result['rows'],
            'counts' => $result['counts'],
            'fileName' => (string) ($file['name'] ?? ''),
        ]);
    }

    public function commit(Request $request): void
    {
        $token = (string) ($request->post('token', '') ?: '');
        $staged = $_SESSION[self::SESSION_KEY][$token] ?? null;

        if (!is_array($staged)) {
            Flash::set('danger', __('roster.preview_expired'));
            $this->redirect('/students/import/roster');
            return;
        }

        unset($_SESSION[self::SESSION_KEY][$token]);

        $result = (new RosterImportService())->commit($staged['rows']);

        $updated = (int) ($result['updated'] ?? 0);
        if ($result['created'] === 0 && $updated === 0) {
            Flash::set('warning', __('roster.nothing_imported'));
        } else {
            Flash::set('success', strtr(__('roster.imported'), [
                '{created}' => (string) $result['created'],
                '{updated}' => (string) $updated,
                '{skipped}' => (string) $result['skipped'],
            ]));
        }

        if ($result['errors'] !== []) {
            error_log('Roster import row errors: ' . implode(' | ', $result['errors']));
        }

        $this->redirect('/students');
    }
}

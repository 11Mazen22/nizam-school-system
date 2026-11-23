<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\ErrorHandler;
use App\Logger;
use App\Request;
use App\Response;
use App\Services\ActivityLogger;
use App\Services\Documents\BulkExportJob;
use App\Services\Documents\DocumentEngine;
use App\Services\Documents\ExportFormat;
use RuntimeException;
use Throwable;

/**
 * HTTP surface of the Document Engine.
 *
 * Every route re-checks the same permission its on-screen equivalent already
 * requires -- the document renderer is not a way around authorisation, it is
 * another view of data the user is already allowed to read.
 *
 *   GET /documents/{key}                    standalone document HTML (preview / print)
 *   GET /documents/{key}/capabilities       formats + bulk scopes with real counts (JSON)
 *   GET /documents/{key}/export/{format}    pdf | png | xlsx | csv | html | zip
 *   GET /documents/bulk/start               create a step-wise bulk job (JSON)
 *   GET /documents/bulk/{token}/step        generate one document (JSON progress)
 *   GET /documents/bulk/{token}/download    stream the finished archive
 *   GET /documents/bulk/{token}/cancel      discard the job
 *
 * Failures are reported as JSON with a stable machine code as well as a
 * localized human message, so the browser can decide whether to offer a
 * retry, the print fallback, or an explanation -- and so support can be told
 * exactly which stage failed.
 */
final class DocumentController extends Controller
{
    /** Query keys that configure the request rather than the document. */
    private const RESERVED = [
        'format', 'embed', 'print', 'signatures', 'watermark', 'compact', 'scale',
        'inline', 'group', 'bulk_format', 'token', 'key',
    ];

    public function __construct(
        private readonly DocumentEngine $engine = new DocumentEngine(),
        private readonly BulkExportJob $jobs = new BulkExportJob(),
    ) {
    }

    public function show(Request $request): void
    {
        $key = (string) ($request->paramString('key') ?? '');
        if (!$this->authorize($key)) {
            return;
        }

        $options = $this->options($request);
        // Printing needs the self-contained HTML (fonts and seal embedded) so
        // the print dialog never waits for an asset or falls back to a system
        // font; the framed preview can keep the smaller linked stylesheet.
        $print = $request->query('print') === '1';
        $options['embed'] = !$print && $request->query('embed') === '1';
        $options['print'] = $print;

        try {
            $html = $this->engine->html($key, $this->params($request), $options, $print);
        } catch (Throwable $exception) {
            $this->renderFailure($exception);
            return;
        }

        Response::noStore();
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
    }

    /** JSON: everything the export interface should render, with real data. */
    public function capabilities(Request $request): void
    {
        $key = (string) ($request->paramString('key') ?? '');
        if (!$this->authorize($key, true)) {
            return;
        }

        try {
            $this->json($this->engine->capabilities($key, $this->params($request)));
        } catch (Throwable $exception) {
            $this->jsonError($exception);
        }
    }

    public function export(Request $request): void
    {
        $key = (string) ($request->paramString('key') ?? '');
        $format = (string) ($request->paramString('format') ?? '');
        if (!$this->authorize($key)) {
            return;
        }

        $options = $this->options($request);
        // A direct ZIP link without an inner format is a ZIP of PDFs.
        $options['bulk_format'] ??= ExportFormat::PDF;

        try {
            $result = $this->engine->export($key, $this->params($request), $format, $options);
        } catch (Throwable $exception) {
            $this->jsonError($exception);
            return;
        }

        ActivityLogger::log(
            'document.export',
            'documents',
            null,
            $key . ' (' . $result['format'] . ')',
            [
                'format' => $result['format'],
                'document' => $key,
                'bytes' => strlen($result['bytes']),
                'pages' => $result['pages'],
                'entries' => $result['entries'],
            ]
        );

        Response::noStore();
        if ($result['pages'] > 0) {
            header('X-Nizam-Pages: ' . $result['pages']);
        }
        if ($result['entries'] > 1) {
            header('X-Nizam-Entries: ' . $result['entries']);
        }
        header('X-Nizam-Format: ' . $result['format']);

        $inline = $request->query('inline') === '1'
            && in_array($result['format'], [ExportFormat::PDF, ExportFormat::HTML], true);

        Response::downloadFile($result['bytes'], $result['filename'], $result['contentType'], $inline);
    }

    public function bulkStart(Request $request): void
    {
        $key = (string) ($request->query('key', '') ?? '');
        if (!$this->authorize($key, true)) {
            return;
        }

        $type = $this->engine->type($key);
        $params = $this->params($request);
        $group = (string) ($request->query('group', '') ?? '');
        $scopes = $type->bulkScopes($params);

        if ($scopes === []) {
            $this->json(['error' => __('document.errors.unsupported'), 'code' => 'unsupported_format'], 422);
            return;
        }

        if ($group === '' || !isset($scopes[$group])) {
            $group = (string) array_key_first($scopes);
        }
        $items = (array) ($scopes[$group]['items'] ?? []);
        if ($items === []) {
            $this->json(['error' => __('document.errors.empty'), 'code' => 'document_empty'], 422);
            return;
        }

        $format = ExportFormat::normalize((string) ($request->query('format', $request->query('bulk_format', 'pdf')) ?? 'pdf'));
        if (!in_array($format, ExportFormat::bulk(), true)) {
            $this->json(['error' => __('document.errors.unsupported'), 'code' => 'unsupported_format'], 422);
            return;
        }

        try {
            $job = $this->jobs->create(
                $key,
                $params,
                $items,
                $format,
                $this->options($request),
                __('document.title.' . $key)
            );
        } catch (Throwable $exception) {
            $this->jsonError($exception);
            return;
        }

        $this->json([
            'token' => $job['token'],
            'total' => $job['total'],
            'label' => (string) ($scopes[$group]['label'] ?? ''),
            'group' => $group,
            'format' => $job['format'],
        ]);
    }

    public function bulkStep(Request $request): void
    {
        $token = (string) ($request->paramString('token') ?? '');
        $job = $this->jobs->load($token);
        if ($job === null) {
            $this->json(['error' => __('document.errors.expired'), 'code' => 'document_job_missing'], 404);
            return;
        }
        if (!$this->authorize((string) $job['key'], true)) {
            return;
        }

        try {
            $progress = $this->jobs->step($token, $this->engine);
        } catch (Throwable $exception) {
            $this->jsonError($exception);
            return;
        }

        $this->json($progress);
    }

    public function bulkDownload(Request $request): void
    {
        $token = (string) ($request->paramString('token') ?? '');
        $job = $this->jobs->load($token);
        if ($job === null) {
            $this->json(['error' => __('document.errors.expired'), 'code' => 'document_job_missing'], 404);
            return;
        }
        if (!$this->authorize((string) $job['key'], true)) {
            return;
        }

        try {
            $archive = $this->jobs->archive($token);
        } catch (Throwable $exception) {
            $this->jsonError($exception);
            return;
        }

        ActivityLogger::log(
            'document.export',
            'documents',
            null,
            $job['key'] . ' (bulk ' . $job['format'] . ', ' . count((array) $job['files']) . ' files)',
            [
                'format' => $job['format'],
                'document' => $job['key'],
                'bytes' => strlen($archive['bytes']),
                'files' => count((array) $job['files']),
                'failed' => (int) $job['failed'],
            ]
        );

        Response::noStore();
        header('X-Nizam-Files: ' . count((array) $job['files']));
        header('X-Nizam-Failed: ' . (int) $job['failed']);
        Response::downloadFile($archive['bytes'], $archive['filename'], $archive['contentType']);

        // The archive is already streamed fully; the job's working files are
        // no longer needed and are removed immediately.
        $this->jobs->purge($token);
    }

    public function bulkCancel(Request $request): void
    {
        $token = (string) ($request->paramString('token') ?? '');

        // Cancelling is idempotent: a job that already expired or was already
        // purged is still "cancelled" from the browser's point of view.
        $job = $this->jobs->load($token);
        if ($job === null) {
            $this->json(['cancelled' => true]);
            return;
        }
        if (!$this->authorize((string) $job['key'], true)) {
            return;
        }

        $this->jobs->purge($token);
        $this->json(['cancelled' => true]);
    }

    private function authorize(string $key, bool $json = false): bool
    {
        if ($key === '' || !$this->engine->has($key)) {
            if ($json) {
                $this->json(['error' => __('document.errors.not_found'), 'code' => 'document_not_found'], 404);
                return false;
            }
            ErrorHandler::renderNotFound();
            return false;
        }

        $permission = $this->engine->type($key)->permission();
        if (!hasPermission($permission)) {
            if ($json) {
                $this->json(['error' => __('document.errors.forbidden'), 'code' => 'forbidden'], 403);
                return false;
            }
            ErrorHandler::renderForbidden();
            return false;
        }

        return true;
    }

    /** @return array<string,string> */
    private function params(Request $request): array
    {
        $params = [];
        foreach ($_GET as $name => $value) {
            if (in_array((string) $name, self::RESERVED, true) || !is_scalar($value)) {
                continue;
            }
            $params[(string) $name] = (string) $value;
        }

        return $params;
    }

    /** @return array<string,mixed> */
    private function options(Request $request): array
    {
        $options = [
            'signatures' => $request->query('signatures', '1') !== '0',
            'watermark' => $request->query('watermark', '0') === '1',
            'compact' => $request->query('compact', '0') === '1',
        ];

        if ($request->query('scale') !== null) {
            $options['scale'] = max(1.0, min(3.0, (float) $request->query('scale', '2')));
        }
        if ($request->query('group') !== null && $request->query('group') !== '') {
            $options['group'] = (string) $request->query('group');
        }
        $bulkFormat = $request->query('bulk_format');
        if ($bulkFormat !== null && $bulkFormat !== '') {
            $options['bulk_format'] = (string) $bulkFormat;
        }

        return $options;
    }

    private function renderFailure(Throwable $exception): void
    {
        Logger::error('document render failed: ' . $exception->getMessage());
        $mapped = $this->mapError($exception);

        http_response_code($mapped['status']);
        header('Content-Type: text/html; charset=UTF-8');
        Response::noStore();
        echo '<!DOCTYPE html><html dir="' . e(currentDirection()) . '" lang="' . e(currentLocale()) . '"><head>'
            . '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . e(__('document.errors.title')) . '</title>'
            . '<style>body{font-family:"Segoe UI",Tahoma,sans-serif;padding:2rem;color:#334155;background:#F7F8F5;margin:0}'
            . 'main{max-width:540px;margin:2rem auto;background:#fff;border:1px solid #DCE4DE;border-radius:14px;padding:1.6rem 1.75rem;'
            . 'box-shadow:0 18px 48px rgba(15,81,50,.10)}'
            . '.mark{width:2.6rem;height:2.6rem;border-radius:12px;background:#FEF3F2;color:#B42318;display:flex;'
            . 'align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;margin-bottom:.9rem}'
            . 'h1{font-size:1.05rem;margin:0 0 .5rem;color:#0F2E22}p{margin:0 0 .9rem;font-size:.9rem;line-height:1.75;color:#475569}'
            . 'code{font-size:.72rem;color:#94A3B8;display:block;margin-top:.4rem}'
            . 'button{font:inherit;font-size:.85rem;background:#0F5132;color:#fff;border:0;border-radius:9px;padding:.55rem 1rem;cursor:pointer}'
            . 'button:hover{background:#0B3F27}</style></head><body><main>'
            . '<div class="mark" aria-hidden="true">!</div>'
            . '<h1>' . e(__('document.errors.title')) . '</h1>'
            . '<p>' . e(__('document.errors.' . $mapped['key'])) . '</p>'
            . '<button type="button" onclick="location.reload()">' . e(__('common.retry')) . '</button>'
            . '<code>' . e($mapped['code']) . '</code>'
            . '</main></body></html>';
    }

    private function jsonError(Throwable $exception): void
    {
        Logger::error('document export failed: ' . $exception->getMessage());
        $mapped = $this->mapError($exception);

        $this->json([
            'error' => __('document.errors.' . $mapped['key']),
            'code' => $mapped['code'],
            'retryable' => $mapped['retryable'],
        ], $mapped['status']);
    }

    /**
     * @return array{key:string, code:string, status:int, retryable:bool}
     */
    private function mapError(Throwable $exception): array
    {
        return match ($exception->getMessage()) {
            'document_renderer_unavailable' => ['key' => 'renderer_unavailable', 'code' => 'renderer_unavailable', 'status' => 503, 'retryable' => false],
            'document_renderer_timeout' => ['key' => 'renderer_timeout', 'code' => 'renderer_timeout', 'status' => 504, 'retryable' => true],
            'document_renderer_failed' => ['key' => 'renderer_failed', 'code' => 'renderer_failed', 'status' => 502, 'retryable' => true],
            'document_workspace_unavailable' => ['key' => 'workspace', 'code' => 'workspace', 'status' => 500, 'retryable' => true],
            'document_export_failed' => ['key' => 'export_failed', 'code' => 'export_failed', 'status' => 500, 'retryable' => true],
            'document_not_found', 'unknown_document' => ['key' => 'not_found', 'code' => 'document_not_found', 'status' => 404, 'retryable' => false],
            'document_job_missing' => ['key' => 'expired', 'code' => 'document_job_missing', 'status' => 404, 'retryable' => false],
            'no_active_year' => ['key' => 'no_active_year', 'code' => 'no_active_year', 'status' => 409, 'retryable' => false],
            'document_empty' => ['key' => 'empty', 'code' => 'document_empty', 'status' => 422, 'retryable' => false],
            'unsupported_format' => ['key' => 'unsupported', 'code' => 'unsupported_format', 'status' => 415, 'retryable' => false],
            'document_page_mismatch' => ['key' => 'page_mismatch', 'code' => 'document_page_mismatch', 'status' => 422, 'retryable' => true],
            default => ['key' => 'generic', 'code' => 'document_failed', 'status' => 500, 'retryable' => true],
        };
    }

    /** @param array<string,mixed> $payload */
    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        Response::noStore();
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}

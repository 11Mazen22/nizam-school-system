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
use RuntimeException;

/**
 * HTTP surface of the Document Engine.
 *
 * Every route re-checks the same permission its on-screen equivalent already
 * requires -- the document renderer is not a way around authorisation, it is
 * another view of data the user is already allowed to read.
 *
 *   GET /documents/{key}                    standalone document HTML (preview/print frame)
 *   GET /documents/{key}/export/{format}    pdf | xlsx | png | zip
 *   GET /documents/bulk/start               create a step-wise bulk job (JSON)
 *   GET /documents/bulk/{token}/step        generate one document (JSON progress)
 *   GET /documents/bulk/{token}/download    stream the finished archive
 *   GET /documents/bulk/{token}/cancel      discard the job
 */
final class DocumentController extends Controller
{
    /** Query keys that configure the request rather than the document. */
    private const RESERVED = [
        'format', 'embed', 'signatures', 'watermark', 'compact', 'scale',
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
        $options['embed'] = $request->query('embed') === '1';

        try {
            $html = $this->engine->html($key, $this->params($request), $options, false);
        } catch (RuntimeException $exception) {
            $this->renderFailure($exception);
            return;
        }

        Response::noStore();
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
    }

    public function export(Request $request): void
    {
        $key = (string) ($request->paramString('key') ?? '');
        $format = (string) ($request->paramString('format') ?? '');
        if (!$this->authorize($key)) {
            return;
        }

        try {
            $result = $this->engine->export($key, $this->params($request), $format, $this->options($request));
        } catch (RuntimeException $exception) {
            $this->jsonError($exception);
            return;
        }

        ActivityLogger::log(
            'document.export',
            'documents',
            null,
            $key . ' (' . $format . ')',
            ['format' => $format, 'document' => $key]
        );

        Response::noStore();
        Response::downloadFile(
            $result['bytes'],
            $result['filename'],
            $result['contentType'],
            $request->query('inline') === '1'
        );
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
            $this->json(['error' => __('document.errors.unsupported')], 422);
            return;
        }

        if ($group === '' || !isset($scopes[$group])) {
            $group = (string) array_key_first($scopes);
        }
        $items = $scopes[$group]['items'];

        $format = (string) ($request->query('bulk_format', 'pdf') ?? 'pdf');
        $format = in_array($format, ['pdf', 'png', 'xlsx'], true) ? $format : 'pdf';

        try {
            $job = $this->jobs->create(
                $key,
                $params,
                $items,
                $format,
                $this->options($request),
                __('document.title.' . $key)
            );
        } catch (RuntimeException $exception) {
            $this->jsonError($exception);
            return;
        }

        $this->json([
            'token' => $job['token'],
            'total' => $job['total'],
            'label' => $scopes[$group]['label'],
            'group' => $group,
            'format' => $format,
        ]);
    }

    /** JSON: the bulk scopes this document supports, with real counts. */
    public function bulkOptions(Request $request): void
    {
        $key = (string) ($request->paramString('key') ?? '');
        if (!$this->authorize($key, true)) {
            return;
        }

        $groups = [];
        foreach ($this->engine->type($key)->bulkScopes($this->params($request)) as $groupKey => $scope) {
            $groups[] = [
                'key' => (string) $groupKey,
                'label' => (string) $scope['label'],
                'count' => count($scope['items']),
            ];
        }

        $this->json(['groups' => $groups]);
    }

    public function bulkStep(Request $request): void
    {
        $token = (string) ($request->paramString('token') ?? '');
        $job = $this->jobs->load($token);
        if ($job === null) {
            $this->json(['error' => __('document.errors.expired')], 404);
            return;
        }
        if (!$this->authorize((string) $job['key'], true)) {
            return;
        }

        try {
            $progress = $this->jobs->step($token, $this->engine);
        } catch (RuntimeException $exception) {
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
            $this->json(['error' => __('document.errors.expired')], 404);
            return;
        }
        if (!$this->authorize((string) $job['key'], true)) {
            return;
        }

        try {
            $archive = $this->jobs->archive($token);
        } catch (RuntimeException $exception) {
            $this->jsonError($exception);
            return;
        }

        ActivityLogger::log(
            'document.export',
            'documents',
            null,
            $job['key'] . ' (bulk ' . $job['format'] . ', ' . count((array) $job['files']) . ' files)',
            ['format' => $job['format'], 'document' => $job['key']]
        );

        Response::noStore();
        Response::downloadFile($archive['bytes'], $archive['filename'], $archive['contentType']);
        $this->jobs->purge($token);
    }

    public function bulkCancel(Request $request): void
    {
        $token = (string) ($request->paramString('token') ?? '');
        $job = $this->jobs->load($token);

        // Cancelling is idempotent: a job that already expired or was already
        // purged is still "cancelled" from the browser's point of view.
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
                $this->json(['error' => __('document.errors.not_found')], 404);
                return false;
            }
            ErrorHandler::renderNotFound();
            return false;
        }

        $permission = $this->engine->type($key)->permission();
        if (!hasPermission($permission)) {
            if ($json) {
                $this->json(['error' => __('document.errors.forbidden')], 403);
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

    /** @return array<string,bool|float> */
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

        return $options;
    }

    private function renderFailure(RuntimeException $exception): void
    {
        Logger::error('document render failed: ' . $exception->getMessage());
        http_response_code(422);
        header('Content-Type: text/html; charset=UTF-8');
        Response::noStore();
        echo '<!DOCTYPE html><html dir="' . e(currentDirection()) . '"><head><meta charset="UTF-8">'
            . '<title>' . e(__('document.errors.title')) . '</title>'
            . '<style>body{font-family:sans-serif;padding:2rem;color:#334155;background:#F7F8F5}'
            . 'div{max-width:520px;margin:0 auto;background:#fff;border:1px solid #DCE4DE;border-radius:10px;padding:1.5rem}'
            . 'h1{font-size:1.05rem;margin:0 0 .5rem;color:#0F5132}p{margin:0;font-size:.9rem;line-height:1.6}</style></head><body><div>'
            . '<h1>' . e(__('document.errors.title')) . '</h1><p>' . e(__('document.errors.' . $this->errorKey($exception))) . '</p>'
            . '</div></body></html>';
    }

    private function errorKey(RuntimeException $exception): string
    {
        return match ($exception->getMessage()) {
            'document_renderer_unavailable' => 'renderer_unavailable',
            'document_renderer_timeout' => 'renderer_timeout',
            'document_not_found' => 'not_found',
            'no_active_year' => 'no_active_year',
            'document_empty' => 'empty',
            'unsupported_format' => 'unsupported',
            'document_page_mismatch' => 'page_mismatch',
            default => 'generic',
        };
    }

    private function jsonError(RuntimeException $exception): void
    {
        Logger::error('document export failed: ' . $exception->getMessage());
        header('Content-Type: application/json; charset=UTF-8');
        Response::noStore();
        http_response_code(422);
        echo json_encode([
            'error' => __('document.errors.' . $this->errorKey($exception)),
        ], JSON_UNESCAPED_UNICODE);
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

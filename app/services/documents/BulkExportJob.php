<?php

declare(strict_types=1);

namespace App\Services\Documents;

use RuntimeException;

/**
 * Step-wise bulk document export.
 *
 * One document is generated per request, so the browser always knows the real
 * completed count (no fake percentages), the interface stays responsive while
 * fifty timetables are rendered, a single failed document is reported without
 * corrupting the archive, and the whole operation is cancellable simply by not
 * asking for the next step. Everything lives under storage/tmp and is removed
 * on download, cancel or once it is older than two hours.
 */
final class BulkExportJob
{
    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? dataPath() . '/storage/tmp/documents/jobs';
    }

    /**
     * @param array<string,string> $params
     * @param list<array{label:string,params:array<string,string>}> $items
     * @param array<string,mixed> $options
     * @return array{token:string,total:int}
     */
    public function create(string $key, array $params, array $items, string $format, array $options, string $title): array
    {
        $items = array_values($items);
        if ($items === []) {
            throw new RuntimeException('document_empty');
        }

        $token = bin2hex(random_bytes(16));
        $dir = $this->dir($token);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('document_workspace_unavailable');
        }

        $job = [
            'token' => $token,
            'key' => $key,
            // An Excel workbook is one file by nature: one step, honest 0->100.
            'format' => $format === 'xlsx' ? 'xlsx' : $format,
            'params' => $params,
            'options' => $options,
            'title' => $title,
            'total' => $format === 'xlsx' ? 1 : count($items),
            'cursor' => 0,
            'failed' => 0,
            'created' => time(),
            'items' => $items,
            'files' => [],
            'errors' => [],
            'complete' => false,
        ];

        $this->save($job);
        $this->prune();

        return ['token' => $token, 'total' => $job['total']];
    }

    /** @return array<string,mixed>|null */
    public function load(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }
        $path = $this->dir($token) . '/job.json';
        if (!is_file($path)) {
            return null;
        }
        $job = json_decode((string) file_get_contents($path), true);

        return is_array($job) ? $job : null;
    }

    /**
     * Generate the next document and report real progress.
     *
     * @return array{done:int,total:int,label:string,failed:int,complete:bool,error:?string}
     */
    public function step(string $token, DocumentEngine $engine): array
    {
        $job = $this->load($token);
        if ($job === null) {
            throw new RuntimeException('document_job_missing');
        }

        if ($job['complete'] || $job['cursor'] >= $job['total']) {
            $job['complete'] = true;
            $this->save($job);

            return [
                'done' => $job['cursor'],
                'total' => $job['total'],
                'label' => '',
                'failed' => $job['failed'],
                'complete' => true,
                'error' => null,
            ];
        }

        $label = '';
        $error = null;

        try {
            if ($job['format'] === 'xlsx') {
                $bytes = $engine->workbookForBulk(
                    (string) $job['key'],
                    (array) $job['params'],
                    (array) $job['items'],
                    (array) $job['options']
                );
                $name = DocumentArchiveBuilder::safeName((string) $job['title']) . '.xlsx';
                file_put_contents($this->dir($token) . '/' . $name, $bytes);
                $job['files'][] = $name;
                $job['total'] = 1;
            } else {
                $index = (int) $job['cursor'];
                $item = $job['items'][$index] ?? null;
                if ($item === null) {
                    throw new RuntimeException('document_job_missing');
                }
                $label = (string) $item['label'];
                $prefix = sprintf('%02d_', $index + 1);
                $name = $prefix . DocumentArchiveBuilder::safeName($label);
                $built = $engine->build(
                    (string) $job['key'],
                    array_merge((array) $job['params'], (array) $item['params']),
                    (array) $job['options']
                );

                if ($job['format'] === 'png') {
                    $pages = $engine->pngBytes($built['document'], $built['identity'], (array) $job['options'], (float) ($job['options']['scale'] ?? 2));
                    foreach ($pages as $pageIndex => $bytes) {
                        $file = $name . '-' . ($pageIndex + 1) . '.png';
                        file_put_contents($this->dir($token) . '/' . $file, $bytes);
                        $job['files'][] = $file;
                    }
                } else {
                    $pdf = $engine->pdfBytes($built['document'], $built['identity'], (array) $job['options']);
                    $file = $name . '.pdf';
                    file_put_contents($this->dir($token) . '/' . $file, $pdf['bytes']);
                    $job['files'][] = $file;
                }
            }
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
            $job['failed'] = (int) $job['failed'] + 1;
            $job['errors'][] = ['label' => $label, 'message' => $error];
        }

        $job['cursor'] = (int) $job['cursor'] + 1;
        $job['complete'] = $job['cursor'] >= $job['total'];
        $this->save($job);

        return [
            'done' => $job['cursor'],
            'total' => $job['total'],
            'label' => $label,
            'failed' => $job['failed'],
            'complete' => (bool) $job['complete'],
            'error' => $error,
        ];
    }

    /**
     * Package the finished job. A single Excel workbook is returned directly;
     * everything else becomes a UTF-8 ZIP with a README.
     *
     * @return array{bytes:string, filename:string, contentType:string}
     */
    public function archive(string $token): array
    {
        $job = $this->load($token);
        if ($job === null) {
            throw new RuntimeException('document_job_missing');
        }

        $dir = $this->dir($token);
        $files = array_values(array_filter(
            (array) $job['files'],
            static fn (string $file): bool => is_file($dir . '/' . $file)
        ));

        if ($files === []) {
            throw new RuntimeException('document_empty');
        }

        if ($job['format'] === 'xlsx' && count($files) === 1) {
            return [
                'bytes' => (string) file_get_contents($dir . '/' . $files[0]),
                'filename' => $files[0],
                'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
        }

        $entries = [];
        foreach ($files as $file) {
            $entries[] = ['path' => $file, 'bytes' => (string) file_get_contents($dir . '/' . $file)];
        }

        $folder = DocumentArchiveBuilder::safeName((string) $job['title']);
        $yearLabel = (string) (\App\Middleware\AcademicYearContext::label() ?? date('Y'));
        $readme = "NIZAM Document Export\n=====================\n\n"
            . 'Document: ' . $job['title'] . "\n"
            . 'Academic year: ' . $yearLabel . "\n"
            . 'Files: ' . count($files) . "\n"
            . 'Failed: ' . (int) $job['failed'] . "\n"
            . 'Generated: ' . date('d/m/Y H:i') . "\n";

        $bytes = (new DocumentArchiveBuilder())->build(
            'NIZAM_Export_' . str_replace('/', '-', $yearLabel),
            array_map(static fn (array $entry): array => [
                'path' => $folder . '/' . $entry['path'],
                'bytes' => $entry['bytes'],
            ], $entries),
            $readme
        );

        return [
            'bytes' => $bytes,
            'filename' => 'NIZAM-' . $job['key'] . '-' . date('Ymd-His') . '.zip',
            'contentType' => 'application/zip',
        ];
    }

    public function purge(string $token): void
    {
        $dir = $this->dir($token);
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /** Remove abandoned jobs so a school server never fills its disk with stale exports. */
    public function prune(int $maxAgeSeconds = 7200): void
    {
        if (!is_dir($this->root)) {
            return;
        }
        
        $dirs = glob($this->root . '/*', GLOB_ONLYDIR);
        if ($dirs === false) {
            return;
        }
        
        foreach ($dirs as $dir) {
            $manifest = $dir . '/job.json';
            if (!is_file($manifest)) {
                // Not a valid job directory, remove immediately
                $this->purgeDir($dir);
                continue;
            }
            
            $age = time() - (int) filemtime($manifest);
            if ($age < $maxAgeSeconds) {
                continue;
            }
            
            $this->purgeDir($dir);
        }
    }

    private function purgeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /** @param array<string,mixed> $job */
    private function save(array $job): void
    {
        file_put_contents($this->dir((string) $job['token']) . '/job.json', json_encode($job, JSON_UNESCAPED_UNICODE));
    }

    private function dir(string $token): string
    {
        return $this->root . '/' . $token;
    }
}

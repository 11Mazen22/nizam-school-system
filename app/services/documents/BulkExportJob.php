<?php

declare(strict_types=1);

namespace App\Services\Documents;

use RuntimeException;
use Throwable;

/**
 * Step-wise bulk document export.
 *
 * One document is generated per request, so the browser always knows the real
 * completed count (no fake percentages), the interface stays responsive while
 * fifty timetables are rendered, a single failed document is reported without
 * corrupting the archive, and the whole operation is cancellable simply by not
 * asking for the next step.
 *
 * Reliability rules this class enforces:
 *  - a job token is 128 random bits and is validated as hex on *every* path
 *    that turns it into a filesystem path (an unvalidated token would let a
 *    caller point the purge routine anywhere on the server);
 *  - a transient renderer failure is retried once before the item is marked
 *    failed, because Chromium occasionally times out on a cold start;
 *  - the manifest inside the archive lists every file with its page count and
 *    every failure with its reason, so a teacher can see exactly what is
 *    missing instead of finding out later;
 *  - abandoned jobs are pruned after two hours.
 *
 * Everything lives under storage/tmp and is removed on download, cancel or
 * once it is older than two hours.
 */
final class BulkExportJob
{
    private const TOKEN_PATTERN = '/^[a-f0-9]{32}$/';

    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? dataPath() . '/storage/tmp/documents/jobs';
    }

    /**
     * @param array<string,string> $params
     * @param list<array{label:string,params:array<string,string>}> $items
     * @param array<string,mixed> $options
     * @return array{token:string,total:int,format:string}
     */
    public function create(string $key, array $params, array $items, string $format, array $options, string $title): array
    {
        $items = array_values($items);
        if ($items === []) {
            throw new RuntimeException('document_empty');
        }

        $format = ExportFormat::normalize($format);
        if (!in_array($format, ExportFormat::bulk(), true)) {
            throw new RuntimeException('unsupported_format');
        }

        $token = bin2hex(random_bytes(16));
        $dir = $this->dir($token);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('document_workspace_unavailable');
        }

        $job = [
            'version' => 2,
            'token' => $token,
            'key' => $key,
            'format' => $format,
            'params' => $params,
            'options' => $options,
            'title' => $title,
            // An Excel workbook is one file by nature: one step, honest 0->100.
            'total' => $format === ExportFormat::XLSX ? 1 : count($items),
            'cursor' => 0,
            'failed' => 0,
            'created' => time(),
            'items' => $items,
            'files' => [],
            'results' => [],
            'errors' => [],
            'complete' => false,
        ];

        $this->save($job);
        $this->prune();

        return ['token' => $token, 'total' => $job['total'], 'format' => $format];
    }

    /** @return array<string,mixed>|null */
    public function load(string $token): ?array
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return null;
        }
        $path = $this->root . '/' . $token . '/job.json';
        if (!is_file($path)) {
            return null;
        }
        $job = json_decode((string) file_get_contents($path), true);

        return is_array($job) ? $job : null;
    }

    /**
     * Generate the next document and report real progress.
     *
     * @return array{
     *     done:int, total:int, label:string, failed:int, complete:bool,
     *     error:?string, errorCode:?string, pages:int, file:?string
     * }
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
                'done' => (int) $job['cursor'],
                'total' => (int) $job['total'],
                'label' => '',
                'failed' => (int) $job['failed'],
                'complete' => true,
                'error' => null,
                'errorCode' => null,
                'pages' => 0,
                'file' => null,
            ];
        }

        $label = '';
        $error = null;
        $errorCode = null;
        $pages = 0;
        $file = null;

        try {
            if ($job['format'] === ExportFormat::XLSX) {
                $bytes = $engine->workbookForBulk(
                    (string) $job['key'],
                    (array) $job['params'],
                    (array) $job['items'],
                    (array) $job['options']
                );
                $file = DocumentArchiveBuilder::safeName((string) $job['title']) . '.xlsx';
                file_put_contents($this->dir($token) . '/' . $file, $bytes);
                $job['files'][] = $file;
                $job['results'][] = ['file' => $file, 'label' => (string) $job['title'], 'status' => 'ok', 'pages' => 0];
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

                // A cold Chromium start can time out once; retrying the same
                // deterministic build is safe and avoids a false failure in a
                // fifty-document export.
                $attempt = 0;
                while (true) {
                    try {
                        $built = $engine->build(
                            (string) $job['key'],
                            array_merge((array) $job['params'], (array) $item['params']),
                            (array) $job['options']
                        );

                        if ($job['format'] === ExportFormat::PNG) {
                            $images = $engine->pngBytes(
                                $built['document'],
                                $built['identity'],
                                (array) $job['options'],
                                (float) ($job['options']['scale'] ?? 2)
                            );
                            $pages = count($images);
                            foreach ($images as $pageIndex => $bytes) {
                                $image = $name . '-' . ($pageIndex + 1) . '.png';
                                file_put_contents($this->dir($token) . '/' . $image, $bytes);
                                $job['files'][] = $image;
                                $job['results'][] = ['file' => $image, 'label' => $label, 'status' => 'ok', 'pages' => 1];
                            }
                            $file = $name . '-1.png';
                        } elseif ($job['format'] === ExportFormat::CSV) {
                            $entries = [];
                            $csv = new CsvExporter();
                            foreach ($csv->entries($built['document']) as $entry) {
                                $entries[] = $entry;
                                $csvFile = $name . '-' . basename($entry['path']);
                                file_put_contents($this->dir($token) . '/' . $csvFile, $entry['bytes']);
                                $job['files'][] = $csvFile;
                                $job['results'][] = ['file' => $csvFile, 'label' => $label, 'status' => 'ok', 'pages' => 0];
                            }
                            $file = $entries === [] ? null : $name . '-' . basename($entries[0]['path']);
                        } elseif ($job['format'] === ExportFormat::HTML) {
                            $html = $engine->html(
                                (string) $job['key'],
                                array_merge((array) $job['params'], (array) $item['params']),
                                (array) $job['options'],
                                true
                            );
                            $htmlFile = $name . '.html';
                            file_put_contents($this->dir($token) . '/' . $htmlFile, $html);
                            $job['files'][] = $htmlFile;
                            $pages = $engine->pageCount(
                                (string) $job['key'],
                                array_merge((array) $job['params'], (array) $item['params']),
                                (array) $job['options']
                            );
                            $job['results'][] = ['file' => $htmlFile, 'label' => $label, 'status' => 'ok', 'pages' => $pages];
                            $file = $htmlFile;
                        } else {
                            $pdf = $engine->pdfBytes($built['document'], $built['identity'], (array) $job['options']);
                            $pdfFile = $name . '.pdf';
                            file_put_contents($this->dir($token) . '/' . $pdfFile, $pdf['bytes']);
                            $pages = $pdf['pages'];
                            $job['files'][] = $pdfFile;
                            $job['results'][] = ['file' => $pdfFile, 'label' => $label, 'status' => 'ok', 'pages' => $pages];
                            $file = $pdfFile;
                        }
                        break;
                    } catch (Throwable $exception) {
                        $attempt++;
                        if ($attempt === 1 && $this->isTransient($exception)) {
                            usleep(300_000);
                            continue;
                        }
                        throw $exception;
                    }
                }
            }
        } catch (Throwable $exception) {
            $errorCode = $exception->getMessage();
            $error = $errorCode;
            $job['failed'] = (int) $job['failed'] + 1;
            $job['errors'][] = ['label' => $label, 'code' => $errorCode, 'message' => $error];
            $job['results'][] = ['file' => '', 'label' => $label, 'status' => 'failed', 'pages' => 0, 'error' => $errorCode];
        }

        $job['cursor'] = (int) $job['cursor'] + 1;
        $job['complete'] = $job['cursor'] >= $job['total'];
        $this->save($job);

        return [
            'done' => (int) $job['cursor'],
            'total' => (int) $job['total'],
            'label' => $label,
            'failed' => (int) $job['failed'],
            'complete' => (bool) $job['complete'],
            'error' => $error,
            'errorCode' => $errorCode,
            'pages' => $pages,
            'file' => $file,
        ];
    }

    /**
     * Package the finished job. A single Excel workbook is returned directly;
     * everything else becomes a UTF-8 ZIP with a README and a manifest.
     *
     * @return array{bytes:string, filename:string, contentType:string, files:int}
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

        if ($job['format'] === ExportFormat::XLSX && count($files) === 1) {
            return [
                'bytes' => (string) file_get_contents($dir . '/' . $files[0]),
                'filename' => $files[0],
                'contentType' => ExportFormat::mime(ExportFormat::XLSX),
                'files' => 1,
            ];
        }

        $entries = [];
        foreach ($files as $file) {
            $entries[] = ['path' => $file, 'bytes' => (string) file_get_contents($dir . '/' . $file)];
        }

        $entries[] = ['path' => 'manifest.csv', 'bytes' => $this->manifest($job)];

        $folder = DocumentArchiveBuilder::safeName((string) $job['title']);
        $yearLabel = (string) (\App\Middleware\AcademicYearContext::label() ?? date('Y'));
        $readme = "NIZAM Document Export\n=====================\n\n"
            . 'Document: ' . $job['title'] . "\n"
            . 'Academic year: ' . $yearLabel . "\n"
            . 'Format: ' . strtoupper((string) $job['format']) . "\n"
            . 'Files: ' . count($files) . "\n"
            . 'Failed: ' . (int) $job['failed'] . "\n"
            . 'Generated: ' . date('d/m/Y H:i') . "\n\n";

        if (!empty($job['errors'])) {
            $readme .= "The following items could not be generated and are not in this archive:\n";
            foreach ($job['errors'] as $error) {
                $readme .= '  - ' . $error['label'] . ': ' . $error['code'] . "\n";
            }
            $readme .= "\n";
        }
        $readme .= "manifest.csv lists every file with its page count.\n";

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
            'contentType' => ExportFormat::mime(ExportFormat::ZIP),
            'files' => count($files),
        ];
    }

    public function purge(string $token): void
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return;
        }
        $dir = $this->root . '/' . $token;
        if (!is_dir($dir)) {
            return;
        }
        $this->purgeDir($dir);
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
                // Not a valid job directory (for example a crashed render):
                // remove it once it is clearly stale, never while a request
                // could still be writing into it.
                if (time() - (int) filemtime($dir) > 600) {
                    $this->purgeDir($dir);
                }
                continue;
            }

            $age = time() - (int) filemtime($manifest);
            if ($age < $maxAgeSeconds) {
                continue;
            }

            $this->purgeDir($dir);
        }
    }

    /** @param array<string,mixed> $job */
    private function manifest(array $job): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['File', 'Label', 'Status', 'Pages', 'Error'], ',', '"', '');

        foreach ((array) ($job['results'] ?? []) as $result) {
            fputcsv($handle, [
                (string) ($result['file'] ?? ''),
                (string) ($result['label'] ?? ''),
                (string) ($result['status'] ?? ''),
                (string) ($result['pages'] ?? ''),
                (string) ($result['error'] ?? ''),
            ], ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /** @param array<string,mixed> $job */
    private function save(array $job): void
    {
        $directory = $this->dir((string) $job['token']);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $path = $directory . '/job.json';
        $temp = $path . '.' . bin2hex(random_bytes(4));
        $encoded = json_encode($job, JSON_UNESCAPED_UNICODE);
        if ($encoded === false || @file_put_contents($temp, $encoded, LOCK_EX) === false) {
            @unlink($temp);
            throw new RuntimeException('document_workspace_unavailable');
        }

        // Rename is atomic on the same filesystem: a reader never sees a
        // half-written job, even if two tabs poll at the same moment.
        if (!@rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException('document_workspace_unavailable');
        }
    }

    private function purgeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }

    private function dir(string $token): string
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw new RuntimeException('document_job_missing');
        }

        return $this->root . '/' . $token;
    }

    private function isTransient(Throwable $exception): bool
    {
        return in_array($exception->getMessage(), [
            'document_renderer_timeout',
            'document_renderer_failed',
        ], true);
    }
}

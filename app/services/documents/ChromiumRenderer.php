<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Logger;
use RuntimeException;

/**
 * Deterministic PDF and PNG rendering through headless Chromium (Playwright).
 *
 * This is deliberately not mPDF for the document engine. Chromium shapes
 * Arabic through HarfBuzz with the real embedded font, keeps text selectable
 * and searchable in the PDF, honours CSS page geometry exactly, and can
 * screenshot each designed page at 2x/3x for the WhatsApp-friendly PNG
 * exports. It renders a *file on disk*, never the running application, so no
 * session, URL or application markup can ever appear in the output.
 *
 * Every call is isolated in its own temporary directory that is removed in a
 * finally block, so a failed or timed-out render can never leave a half
 * written PDF, a stray PNG or a multi-megabyte HTML file behind on the school
 * server.
 *
 * If Node/Chromium is unavailable on a given installation the caller is told
 * plainly (document_renderer_unavailable) instead of silently producing a
 * lower-quality document -- the UI then offers the print-ready document,
 * which still carries no browser artefacts.
 */
final class ChromiumRenderer
{
    private const DEFAULT_TIMEOUT = 180.0;

    private readonly string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /**
     * @return array{bytes:string, pages:int, fitted:int, overflow:bool, width:int, height:int}
     */
    public function pdf(string $html): array
    {
        return $this->run($html, 'pdf', 1.0);
    }

    /**
     * One PNG per designed page, already numbered from 1.
     *
     * @return array{pages: list<string>, count:int, fitted:int, overflow:bool}
     */
    public function pngs(string $html, float $scale = 2.0): array
    {
        $result = $this->run($html, 'png', $scale);

        return [
            'pages' => $result['files'],
            'count' => count($result['files']),
            'fitted' => $result['fitted'],
            'overflow' => $result['overflow'],
        ];
    }

    /**
     * Measure the composed document without producing a file. Used by the
     * verification suite and by the preview when it needs the true physical
     * page count.
     *
     * @return array{pages:int, fitted:int, overflow:bool, width:int, height:int}
     */
    public function measure(string $html): array
    {
        $result = $this->run($html, 'measure', 1.0);

        return [
            'pages' => $result['pages'],
            'fitted' => $result['fitted'],
            'overflow' => $result['overflow'],
            'width' => $result['width'],
            'height' => $result['height'],
        ];
    }

    /**
     * @return array{bytes:string, files:list<string>, pages:int, fitted:int, overflow:bool, width:int, height:int}
     */
    private function run(string $html, string $mode, float $scale): array
    {
        $this->assertAvailable();

        $directory = $this->workspace() . DIRECTORY_SEPARATOR . 'render-' . bin2hex(random_bytes(6));
        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('document_workspace_unavailable');
        }

        $htmlPath = $directory . DIRECTORY_SEPARATOR . 'document.html';
        $outPath = $directory . DIRECTORY_SEPARATOR . 'document'
            . ($mode === 'pdf' ? '.pdf' : ($mode === 'png' ? '-page' : '.measure'));

        if (@file_put_contents($htmlPath, $html) === false) {
            $this->removeDirectory($directory);
            throw new RuntimeException('document_renderer_failed');
        }

        try {
            [$code, $stdout, $stderr] = $this->execute([
                $this->nodeBinary(),
                $this->projectRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'document-render.mjs',
                '--html', $htmlPath,
                '--out', $outPath,
                '--mode', $mode,
                '--scale', (string) $scale,
            ]);

            $payload = $this->payload($stdout);
            if ($code !== 0 || !is_array($payload) || empty($payload['ok'])) {
                $reason = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
                Logger::error('document renderer failed (' . $mode . '): ' . mb_substr($reason, 0, 1200));

                if (preg_match('/not recognized|not found|Cannot find module|ERR_MODULE_NOT_FOUND|ENOENT/i', $reason) === 1) {
                    throw new RuntimeException('document_renderer_unavailable');
                }
                if (preg_match('/timed? ?out|timeout/i', $reason) === 1) {
                    throw new RuntimeException('document_renderer_timeout');
                }
                throw new RuntimeException('document_renderer_failed');
            }

            $meta = [
                'fitted' => (int) ($payload['fitted'] ?? 0),
                'overflow' => (bool) ($payload['overflow'] ?? false),
                'width' => (int) ($payload['width'] ?? 0),
                'height' => (int) ($payload['height'] ?? 0),
            ];
            $pageCount = (int) ($payload['pages'] ?? 0);

            if ($mode === 'pdf') {
                if (!is_file($outPath)) {
                    throw new RuntimeException('document_renderer_failed');
                }

                return [
                    'bytes' => (string) file_get_contents($outPath),
                    'files' => [],
                    'pages' => $pageCount,
                    ...$meta,
                ];
            }

            if ($mode === 'png') {
                $files = glob($outPath . '-*.png') ?: [];
                usort($files, static fn (string $a, string $b): int => strnatcmp($a, $b));
                if ($files === []) {
                    throw new RuntimeException('document_renderer_failed');
                }
                $pages = [];
                foreach ($files as $file) {
                    $pages[] = (string) file_get_contents($file);
                }

                return [
                    'bytes' => '',
                    'files' => $pages,
                    'pages' => count($pages),
                    ...$meta,
                ];
            }

            return [
                'bytes' => '',
                'files' => [],
                'pages' => $pageCount,
                ...$meta,
            ];
        } finally {
            $this->removeDirectory($directory);
        }
    }

    /** @return array<string,mixed>|null */
    private function payload(string $stdout): ?array
    {
        foreach (array_reverse(preg_split('/\r?\n/', trim($stdout)) ?: []) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] !== '{') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /** @return array{0:int,1:string,2:string} */
    private function execute(array $arguments): array
    {
        $command = implode(' ', array_map(
            static fn (string $argument): string => escapeshellarg($argument),
            $arguments
        ));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, $this->projectRoot);
        if (!is_resource($process)) {
            throw new RuntimeException('document_renderer_unavailable');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + $this->timeout();

        while (true) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                throw new RuntimeException('document_renderer_timeout');
            }
            usleep(50_000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return [$code, $stdout, $stderr];
    }

    /**
     * Fail before spawning a process when the installation plainly cannot
     * render, so the user gets "print instead" rather than a 30-second wait.
     */
    private function assertAvailable(): void
    {
        $script = $this->projectRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'document-render.mjs';
        if (!is_file($script)) {
            throw new RuntimeException('document_renderer_unavailable');
        }
        if (!is_dir($this->projectRoot . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'playwright')) {
            throw new RuntimeException('document_renderer_unavailable');
        }
    }

    private function nodeBinary(): string
    {
        $configured = getenv('NIZAM_NODE_BIN');
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return 'node';
    }

    private function timeout(): float
    {
        $configured = getenv('NIZAM_DOC_RENDER_TIMEOUT');
        if (is_string($configured) && is_numeric($configured)) {
            return max(30.0, min(600.0, (float) $configured));
        }

        return self::DEFAULT_TIMEOUT;
    }

    private function workspace(): string
    {
        $base = dataPath() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'documents';
        if (!is_dir($base) && !@mkdir($base, 0775, true) && !is_dir($base)) {
            throw new RuntimeException('document_workspace_unavailable');
        }

        return $base;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($directory);
    }
}

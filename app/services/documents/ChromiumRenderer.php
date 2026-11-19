<?php

declare(strict_types=1);

namespace App\Services\Documents;

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
 * If Node/Chromium is unavailable on a given installation the caller is told
 * plainly (document_renderer_unavailable) instead of silently producing a
 * lower-quality document -- the UI then offers the print-ready document,
 * which still carries no browser artefacts.
 */
final class ChromiumRenderer
{
    private readonly string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /** @return array{bytes:string, pages:int} */
    public function pdf(string $html): array
    {
        return $this->run($html, 'pdf', 1.0);
    }

    /**
     * One PNG per designed page, already numbered from 1.
     *
     * @return array{pages: list<string>, count:int}
     */
    public function pngs(string $html, float $scale = 2.0): array
    {
        $result = $this->run($html, 'png', $scale);
        $pages = [];
        for ($index = 1; $index <= $result['pages']; $index++) {
            $path = $result['out'] . '-' . $index . '.png';
            if (is_file($path)) {
                $pages[] = (string) file_get_contents($path);
                @unlink($path);
            }
        }

        return ['pages' => $pages, 'count' => count($pages)];
    }

    /**
     * @return array{bytes:string, pages:int, out:string}
     */
    private function run(string $html, string $mode, float $scale): array
    {
        $workspace = $this->workspace();
        $token = bin2hex(random_bytes(8));
        $htmlPath = $workspace . DIRECTORY_SEPARATOR . 'document-' . $token . '.html';
        $outPath = $workspace . DIRECTORY_SEPARATOR . 'document-' . $token . ($mode === 'pdf' ? '.pdf' : '');

        if (file_put_contents($htmlPath, $html) === false) {
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

            if ($code !== 0) {
                $reason = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
                if (preg_match('/not recognized|not found|Cannot find module|ENOENT/i', $reason) === 1) {
                    throw new RuntimeException('document_renderer_unavailable');
                }
                throw new RuntimeException('document_renderer_failed');
            }

            $payload = json_decode(trim($stdout), true);
            if (!is_array($payload) || empty($payload['ok'])) {
                throw new RuntimeException('document_renderer_failed');
            }

            if ($mode === 'pdf') {
                if (!is_file($outPath)) {
                    throw new RuntimeException('document_renderer_failed');
                }
                $bytes = (string) file_get_contents($outPath);
                @unlink($outPath);

                return [
                    'bytes' => $bytes,
                    'pages' => (int) ($payload['pages'] ?? 0),
                    'out' => $outPath,
                ];
            }

            return [
                'bytes' => '',
                'pages' => (int) ($payload['pages'] ?? 0),
                'out' => $outPath,
            ];
        } finally {
            // Only the input is removed here; PDF/PNG outputs are read by the
            // caller from the returned path and cleaned up with the workspace.
            @unlink($htmlPath);
        }
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
        $deadline = microtime(true) + 120.0;

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
            usleep(60_000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return [$code, $stdout, $stderr];
    }

    private function nodeBinary(): string
    {
        $configured = getenv('NIZAM_NODE_BIN');
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return PHP_OS_FAMILY === 'Windows' ? 'node' : 'node';
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
}

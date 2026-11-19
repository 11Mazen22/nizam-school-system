<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Logger;
use App\Services\Documents\Types\DocumentType;
use RuntimeException;

/**
 * NIZAM Document Engine -- one pipeline for every document in the system.
 *
 *   module data -> DocumentType -> Document model -> PageComposer
 *               -> DocumentHtmlRenderer -> PDF | PNG | XLSX | ZIP
 *
 * Everything downstream (preview modal, export menu, bulk progress, audit)
 * talks to this class and nothing else, which is what keeps the school's
 * timetables, registers, statements and report cards one coherent product
 * rather than a collection of separate print features.
 */
final class DocumentEngine
{
    public function __construct(
        private readonly DocumentRegistry $registry = new DocumentRegistry(),
        private readonly DocumentHtmlRenderer $renderer = new DocumentHtmlRenderer(),
        private readonly ChromiumRenderer $chromium = new ChromiumRenderer(),
        private readonly ExcelWorkbookBuilder $excel = new ExcelWorkbookBuilder(),
        private readonly DocumentArchiveBuilder $archive = new DocumentArchiveBuilder(),
    ) {
    }

    public function type(string $key): DocumentType
    {
        return $this->registry->make($key);
    }

    public function has(string $key): bool
    {
        return $this->registry->has($key);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return $this->registry->keys();
    }

    /**
     * @param array<string,string> $params
     * @param array<string,mixed> $options
     * @return array{document:Document, identity:DocumentIdentity}
     */
    public function build(string $key, array $params, array $options = []): array
    {
        $type = $this->type($key);
        $identity = $this->identity($params, $options);

        return ['document' => $type->build($this->sanitize($params), $identity), 'identity' => $identity];
    }

    /**
     * @param array<string,string> $params
     * @param array<string,mixed> $options
     */
    public function html(string $key, array $params, array $options = [], bool $forExport = true): string
    {
        $built = $this->build($key, $params, $options);

        return $this->renderer->render(
            $built['document'],
            $built['identity'],
            $this->renderOptions($options),
            $forExport
        );
    }

    /**
     * @param array<string,string> $params
     * @param array<string,mixed> $options
     * @return array{bytes:string, filename:string, contentType:string}
     */
    public function export(string $key, array $params, string $format, array $options = []): array
    {
        $type = $this->type($key);
        if (!in_array($format, $type->formats(), true) && $format !== 'zip') {
            throw new RuntimeException('unsupported_format');
        }

        $stamp = date('Ymd-His');
        $base = 'NIZAM-' . $key . '-' . $stamp;

        // A bulk archive is a collection, not one document with a collection
        // appended: the base single document (which may legitimately need a
        // class id) must not be built at all on this path.
        if ($format === 'zip') {
            return $this->bulkArchive($key, $type, $params, $options, $this->identity($params, $options), $base);
        }

        $built = $this->build($key, $params, $options);
        /** @var Document $document */
        $document = $built['document'];
        $identity = $built['identity'];

        return match ($format) {
            'pdf' => [
                'bytes' => $this->pdfBytes($document, $identity, $options)['bytes'],
                'filename' => $base . '.pdf',
                'contentType' => 'application/pdf',
            ],
            'png' => $this->png($document, $identity, $options, $base),
            'xlsx' => [
                'bytes' => $this->excel->build([$document], $identity, $document->title),
                'filename' => $base . '.xlsx',
                'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            default => throw new RuntimeException('unsupported_format'),
        };
    }

    /**
     * @return array{bytes:string,pages:int}
     * @throws RuntimeException 'document_page_mismatch' when the browser emits
     *   a different number of physical pages than the footer numbers. Such a
     *   PDF is never returned: a wrong "page X of Y" is worse than no export.
     */
    public function pdfBytes(Document $document, DocumentIdentity $identity, array $options = []): array
    {
        $html = $this->renderer->render($document, $identity, $this->renderOptions($options), true);
        $result = $this->chromium->pdf($html);
        $composed = $this->renderer->pageCount($document);
        $actual = PdfPageCount::fromBytes($result['bytes']);

        if ($actual !== $composed) {
            Logger::error(sprintf(
                'PDF pagination mismatch for %s: composed %d pages, browser emitted %d (renderer said %d)',
                $document->key,
                $composed,
                $actual,
                (int) $result['pages']
            ));
            throw new RuntimeException('document_page_mismatch');
        }

        return ['bytes' => $result['bytes'], 'pages' => $actual];
    }

    /** @return list<string> one PNG per designed page */
    public function pngBytes(Document $document, DocumentIdentity $identity, array $options = [], float $scale = 2.0): array
    {
        $html = $this->renderer->render($document, $identity, $this->renderOptions($options), true);

        return $this->chromium->pngs($html, $scale)['pages'];
    }

    /** @return array{bytes:string,pages:int,filename:string,contentType:string} */
    public function previewPages(string $key, array $params, array $options = []): array
    {
        $built = $this->build($key, $params, $options);

        return [
            'bytes' => '',
            'pages' => $this->renderer->pageCount($built['document']),
            'filename' => $built['document']->filename,
            'contentType' => 'text/html',
        ];
    }

    /**
     * Build the ZIP for a document's own bulk scope in one request. The UI
     * uses the step-wise bulk job for live progress; this path exists for
     * direct links and for scopes that are already small.
     *
     * @return array{bytes:string, filename:string, contentType:string}
     */
    private function bulkArchive(
        string $key,
        DocumentType $type,
        array $params,
        array $options,
        DocumentIdentity $identity,
        string $base,
    ): array {
        $scopes = $type->bulkScopes($params);
        if ($scopes === []) {
            throw new RuntimeException('unsupported_format');
        }
        $scope = reset($scopes);
        $items = $scope['items'] ?? [];
        if ($items === []) {
            throw new RuntimeException('document_empty');
        }

        $inner = (string) ($options['bulk_format'] ?? 'pdf');
        $entries = [];
        foreach (array_values($items) as $index => $item) {
            $built = $this->build($key, array_merge($params, $item['params']), $options);
            $prefix = sprintf('%02d_', $index + 1);
            $name = $prefix . DocumentArchiveBuilder::safeName((string) $item['label']);

            if ($inner === 'png') {
                $pages = $this->pngBytes($built['document'], $built['identity'], $options, (float) ($options['scale'] ?? 2));
                foreach ($pages as $pageIndex => $bytes) {
                    $entries[] = [
                        'path' => $name . '-' . ($pageIndex + 1) . '.png',
                        'bytes' => $bytes,
                    ];
                }
                continue;
            }

            $pdf = $this->pdfBytes($built['document'], $built['identity'], $options);
            $entries[] = ['path' => $name . '.pdf', 'bytes' => $pdf['bytes']];
        }

        return [
            'bytes' => $this->archive->build(
                'NIZAM_Export_' . ($identity->yearLabel !== '' ? str_replace('/', '-', $identity->yearLabel) : date('Y')),
                $entries,
                $this->readme($identity, $type, count($items))
            ),
            'filename' => $base . '.zip',
            'contentType' => 'application/zip',
        ];
    }

    /**
     * One Excel workbook covering every item of a bulk scope -- the useful
     * shape for spreadsheets (one sheet per class/teacher), as opposed to a
     * ZIP of dozens of one-sheet workbooks.
     *
     * @param array<string,string> $params
     * @param list<array{label:string,params:array<string,string>}> $items
     * @param array<string,mixed> $options
     */
    public function workbookForBulk(string $key, array $params, array $items, array $options = []): string
    {
        $documents = [];
        $identity = null;
        foreach ($items as $item) {
            $built = $this->build($key, array_merge($params, $item['params']), $options);
            $documents[] = $built['document'];
            $identity ??= $built['identity'];
        }

        if ($documents === [] || $identity === null) {
            throw new RuntimeException('document_empty');
        }

        return $this->excel->build($documents, $identity, __('document.title.' . $key));
    }

    public function readme(DocumentIdentity $identity, DocumentType $type, int $count): string
    {
        $lines = [
            'NIZAM Document Export',
            '=====================',
            '',
            'School: ' . $identity->nameAr . ' / ' . $identity->nameEn,
            'Document: ' . __('document.title.' . $type->key()),
            'Academic year: ' . $identity->yearLabel,
            'Documents: ' . $count,
            'Generated: ' . $identity->issueDate,
            '',
            'Every file in this archive was generated independently from the',
            'school system data by the NIZAM document engine.',
        ];

        return implode("\n", $lines) . "\n";
    }

    /** @param array<string,mixed> $options */
    private function renderOptions(array $options): array
    {
        return [
            'signatures' => !isset($options['signatures']) || dbBool($options['signatures']),
            'watermark' => isset($options['watermark']) && dbBool($options['watermark']),
            'compact' => isset($options['compact']) && dbBool($options['compact']),
            'embed' => isset($options['embed']) && dbBool($options['embed']),
        ];
    }

    /** @param array<string,mixed> $options */
    private function identity(array $params, array $options): DocumentIdentity
    {
        $signatures = array_key_exists('signatures', $options) ? dbBool($options['signatures']) : null;
        $watermark = array_key_exists('watermark', $options) ? dbBool($options['watermark']) : null;

        return DocumentIdentity::resolve(
            currentLocale(),
            isset($params['year_label']) && $params['year_label'] !== '' ? (string) $params['year_label'] : null,
            $signatures,
            $watermark
        );
    }

    /**
     * Only scalar, bounded query values ever reach a document builder.
     *
     * @param array<string,mixed> $params
     * @return array<string,string>
     */
    private function sanitize(array $params): array
    {
        $clean = [];
        foreach ($params as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            
            $name = preg_replace('/[^a-z0-9_]/i', '', (string) $key);
            if ($name === '') {
                continue;
            }
            
            $val = (string) $value;
            $clean[$name] = mb_substr($val, 0, 200);
        }

        return $clean;
    }

    /** @return array{bytes:string, filename:string, contentType:string} */
    private function png(Document $document, DocumentIdentity $identity, array $options, string $base): array
    {
        $scale = (float) ($options['scale'] ?? 2);
        $scale = max(1.0, min(3.0, $scale));
        $pages = $this->pngBytes($document, $identity, $options, $scale);

        if (count($pages) <= 1) {
            return [
                'bytes' => $pages[0] ?? '',
                'filename' => $base . '.png',
                'contentType' => 'image/png',
            ];
        }

        $entries = [];
        foreach ($pages as $index => $bytes) {
            $entries[] = [
                'path' => DocumentArchiveBuilder::safeName($document->filename) . '-' . ($index + 1) . '.png',
                'bytes' => $bytes,
            ];
        }

        return [
            'bytes' => $this->archive->build(
                'NIZAM_Export_' . ($identity->yearLabel !== '' ? str_replace('/', '-', $identity->yearLabel) : date('Y')),
                $entries,
                $this->readme($identity, $this->type($document->key), 1)
            ),
            'filename' => $base . '.zip',
            'contentType' => 'application/zip',
        ];
    }
}

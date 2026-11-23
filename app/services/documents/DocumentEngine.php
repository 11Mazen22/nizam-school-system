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
 *               -> DocumentHtmlRenderer -> PDF | PNG | XLSX | CSV | HTML | ZIP
 *
 * Everything downstream (preview modal, export menu, bulk progress, audit)
 * talks to this class and nothing else, which is what keeps the school's
 * timetables, registers, statements and report cards one coherent product
 * rather than a collection of separate print features.
 *
 * The engine also owns the promises the UI relies on:
 *  - a PDF is only returned when its physical page count matches the page
 *    numbering printed in its footer (verified by reading the file back);
 *  - PNG of a multi-page document is a labelled archive, never a silent
 *    single-page surprise;
 *  - every format is available for every document type, because they all
 *    share the same block model;
 *  - a bulk archive is a collection of independently built documents, with a
 *    README describing exactly what is inside.
 */
final class DocumentEngine
{
    /** Every download format a single document supports, in menu order. */
    public const DOCUMENT_FORMATS = [
        ExportFormat::PDF,
        ExportFormat::PNG,
        ExportFormat::XLSX,
        ExportFormat::CSV,
        ExportFormat::HTML,
    ];

    public function __construct(
        private readonly DocumentRegistry $registry = new DocumentRegistry(),
        private readonly DocumentHtmlRenderer $renderer = new DocumentHtmlRenderer(),
        private readonly ChromiumRenderer $chromium = new ChromiumRenderer(),
        private readonly ExcelWorkbookBuilder $excel = new ExcelWorkbookBuilder(),
        private readonly CsvExporter $csv = new CsvExporter(),
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

    /** @return list<string> */
    public function formatsFor(string $key): array
    {
        $declared = $this->type($key)->formats();

        // Intersect with the platform's own list so an unknown or retired
        // format in a document type can never reach a download header.
        $formats = array_values(array_intersect(self::DOCUMENT_FORMATS, $declared));

        return $formats === [] ? self::DOCUMENT_FORMATS : $formats;
    }

    /**
     * Everything the export interface needs to render itself honestly: the
     * supported formats with their real labels and the bulk scopes with real
     * counts. No guessing in JavaScript, no dead menu items.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function capabilities(string $key, array $params = []): array
    {
        $type = $this->type($key);
        $params = $this->sanitize($params);

        $formats = [];
        foreach ($this->formatsFor($key) as $format) {
            $formats[] = ExportFormat::meta($format);
        }

        $groups = [];
        foreach ($type->bulkScopes($params) as $groupKey => $scope) {
            $groups[] = [
                'key' => (string) $groupKey,
                'label' => (string) ($scope['label'] ?? ''),
                'count' => count((array) ($scope['items'] ?? [])),
            ];
        }

        $bulkFormats = [];
        if ($groups !== []) {
            foreach (ExportFormat::bulk() as $format) {
                $bulkFormats[] = ExportFormat::meta($format);
            }
        }

        return [
            'key' => $key,
            'title' => __('document.title.' . $key),
            'formats' => $formats,
            'print' => ExportFormat::meta(ExportFormat::PRINT),
            'bulk' => [
                'groups' => $groups,
                'formats' => $bulkFormats,
            ],
        ];
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
     * @return array{
     *     bytes:string, filename:string, contentType:string,
     *     format:string, pages:int, entries:int, warnings:list<string>
     * }
     */
    public function export(string $key, array $params, string $format, array $options = []): array
    {
        $format = ExportFormat::normalize($format);
        if ($format === ExportFormat::PRINT) {
            $format = ExportFormat::HTML;
        }

        if (!in_array($format, $this->formatsFor($key), true) && $format !== ExportFormat::ZIP) {
            throw new RuntimeException('unsupported_format');
        }

        $stamp = date('Ymd-His');
        $base = 'NIZAM-' . $key . '-' . $stamp;

        // A bulk archive is a collection, not one document with a collection
        // appended: the base single document (which may legitimately need a
        // class id) must not be built at all on this path.
        if ($format === ExportFormat::ZIP) {
            return $this->bulkArchive($key, $this->type($key), $params, $options, $this->identity($params, $options), $base);
        }

        $built = $this->build($key, $params, $options);
        /** @var Document $document */
        $document = $built['document'];
        $identity = $built['identity'];

        if ($format === ExportFormat::PDF) {
            $pdf = $this->pdfBytes($document, $identity, $options);

            return [
                'bytes' => $pdf['bytes'],
                'filename' => $base . '.pdf',
                'contentType' => ExportFormat::mime(ExportFormat::PDF),
                'format' => ExportFormat::PDF,
                'pages' => $pdf['pages'],
                'entries' => 1,
                'warnings' => $pdf['warnings'],
            ];
        }

        if ($format === ExportFormat::PNG) {
            return $this->png($document, $identity, $options, $base);
        }

        if ($format === ExportFormat::XLSX) {
            return [
                'bytes' => $this->excel->build([$document], $identity, $document->title),
                'filename' => $base . '.xlsx',
                'contentType' => ExportFormat::mime(ExportFormat::XLSX),
                'format' => ExportFormat::XLSX,
                'pages' => 0,
                'entries' => 1,
                'warnings' => [],
            ];
        }

        if ($format === ExportFormat::CSV) {
            $csv = $this->csv->export($document, $base);

            return [
                'bytes' => $csv['bytes'],
                'filename' => $csv['filename'],
                'contentType' => $csv['contentType'],
                'format' => ExportFormat::CSV,
                'pages' => 0,
                'entries' => $csv['entries'],
                'warnings' => [],
            ];
        }

        if ($format === ExportFormat::HTML) {
            $html = $this->renderer->render(
                $document,
                $identity,
                $this->renderOptions($options),
                true
            );

            return [
                'bytes' => $html,
                'filename' => $base . '.html',
                'contentType' => ExportFormat::mime(ExportFormat::HTML),
                'format' => ExportFormat::HTML,
                'pages' => $this->renderer->pageCount($document),
                'entries' => 1,
                'warnings' => [],
            ];
        }

        throw new RuntimeException('unsupported_format');
    }

    /**
     * @return array{bytes:string, pages:int, warnings:list<string>}
     * @throws RuntimeException 'document_page_mismatch' when the browser emits
     *   a different number of physical pages than the footer numbers. Such a
     *   PDF is never returned: a wrong "page X of Y" is worse than no export.
     */
    public function pdfBytes(Document $document, DocumentIdentity $identity, array $options = []): array
    {
        $composed = $this->renderer->pageCount($document);
        $warnings = [];

        $html = $this->renderer->render($document, $identity, $this->renderOptions($options), true);
        $result = $this->chromium->pdf($html);
        $actual = PdfPageCount::fromBytes($result['bytes']);

        if ($actual !== $composed) {
            Logger::error(sprintf(
                'PDF pagination mismatch for %s: composed %d pages, browser emitted %d (renderer said %d, fitted %d)',
                $document->key,
                $composed,
                $actual,
                (int) $result['pages'],
                (int) $result['fitted']
            ));

            // One deterministic repair: the same composed pages, tighter
            // typography. The page count cannot change here (the composer
            // already decided it); this only removes a height overflow.
            $tight = array_merge($options, ['compact' => true]);
            $tightHtml = $this->renderer->render($document, $identity, $this->renderOptions($tight), true);
            $repair = $this->chromium->pdf($tightHtml);
            $repaired = PdfPageCount::fromBytes($repair['bytes']);

            if ($repaired !== $composed) {
                Logger::error(sprintf(
                    'PDF pagination repair failed for %s: composed %d, first %d, repaired %d',
                    $document->key,
                    $composed,
                    $actual,
                    $repaired
                ));
                throw new RuntimeException('document_page_mismatch');
            }

            $result = $repair;
            $actual = $repaired;
            $warnings[] = 'compacted';
        }

        if (!PdfPageCount::isA4($result['bytes'], $document->isLandscape())) {
            $warnings[] = 'paper_size';
        }

        // The renderer already tightened up to three levels. Reaching here with
        // overflow still set means a single unsplittable block is taller than
        // the paper itself; the file is still correct as a PDF, but the fact
        // belongs in the log rather than being silently clipped.
        if (!empty($result['overflow'])) {
            Logger::error(sprintf(
                'Document %s still overflows its page after automatic fitting',
                $document->key
            ));
            $warnings[] = 'overflow';
        }

        return ['bytes' => $result['bytes'], 'pages' => $actual, 'warnings' => $warnings];
    }

    /**
     * One PNG per designed page.
     *
     * @return list<string>
     */
    public function pngBytes(Document $document, DocumentIdentity $identity, array $options = [], float $scale = 2.0): array
    {
        $scale = max(1.0, min(3.0, $scale));
        $html = $this->renderer->render($document, $identity, $this->renderOptions($options), true);
        $result = $this->chromium->pngs($html, $scale);

        if ($result['pages'] === []) {
            throw new RuntimeException('document_renderer_failed');
        }

        return $result['pages'];
    }

    /**
     * The physical page count a document will have, without rendering it.
     *
     * @param array<string,string> $params
     * @param array<string,mixed> $options
     */
    public function pageCount(string $key, array $params, array $options = []): int
    {
        $built = $this->build($key, $params, $options);

        return $this->renderer->pageCount($built['document']);
    }

    /** @return array{bytes:string, pages:int, filename:string, contentType:string} */
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
            $built = $this->build($key, array_merge($params, (array) $item['params']), $options);
            $documents[] = $built['document'];
            $identity ??= $built['identity'];
        }

        if ($documents === [] || $identity === null) {
            throw new RuntimeException('document_empty');
        }

        return $this->excel->build($documents, $identity, __('document.title.' . $key));
    }

    public function readme(DocumentIdentity $identity, DocumentType $type, int $count, string $inner = ''): string
    {
        $lines = [
            'NIZAM Document Export',
            '=====================',
            '',
            'School: ' . $identity->nameAr . ' / ' . $identity->nameEn,
            'Document: ' . __('document.title.' . $type->key()),
            'Academic year: ' . $identity->yearLabel,
            'Documents: ' . $count,
        ];
        if ($inner !== '') {
            $lines[] = 'File format: ' . strtoupper($inner);
        }
        $lines[] = 'Generated: ' . $identity->issueDate;
        $lines[] = '';
        $lines[] = 'Every file in this archive was generated independently from the';
        $lines[] = 'school system data by the NIZAM document engine. File names begin';
        $lines[] = 'with a number in the same order as the records in the system.';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Build the ZIP for a document's own bulk scope in one request. The UI
     * uses the step-wise bulk job for live progress; this path exists for
     * direct links and for scopes that are already small.
     *
     * @return array{
     *     bytes:string, filename:string, contentType:string,
     *     format:string, pages:int, entries:int, warnings:list<string>
     * }
     */
    private function bulkArchive(
        string $key,
        DocumentType $type,
        array $params,
        array $options,
        DocumentIdentity $identity,
        string $base,
    ): array {
        $params = $this->sanitize($params);
        $scopes = $type->bulkScopes($params);
        if ($scopes === []) {
            throw new RuntimeException('unsupported_format');
        }

        $group = (string) ($options['group'] ?? '');
        if ($group === '' || !isset($scopes[$group])) {
            $group = (string) array_key_first($scopes);
        }
        $items = array_values((array) ($scopes[$group]['items'] ?? []));
        if ($items === []) {
            throw new RuntimeException('document_empty');
        }

        $inner = ExportFormat::normalize((string) ($options['bulk_format'] ?? ExportFormat::PDF));
        if (!in_array($inner, ExportFormat::bulk(), true)) {
            throw new RuntimeException('unsupported_format');
        }

        $entries = [];
        $pageTotal = 0;

        // An Excel workbook is one coherent file by nature: build it once for
        // the whole scope rather than zipping dozens of one-sheet workbooks.
        if ($inner === ExportFormat::XLSX) {
            $entries[] = [
                'path' => DocumentArchiveBuilder::safeName($type->key() . '-' . $group) . '.xlsx',
                'bytes' => $this->workbookForBulk($key, $params, $items, $options),
            ];
        } else {
            foreach ($items as $index => $item) {
                $built = $this->build($key, array_merge($params, (array) $item['params']), $options);
                /** @var Document $document */
                $document = $built['document'];
                $name = sprintf('%02d_', $index + 1) . DocumentArchiveBuilder::safeName((string) $item['label']);

                if ($inner === ExportFormat::PNG) {
                    $pages = $this->pngBytes($document, $built['identity'], $options, (float) ($options['scale'] ?? 2));
                    $pageTotal += count($pages);
                    foreach ($pages as $pageIndex => $bytes) {
                        $entries[] = ['path' => $name . '-' . ($pageIndex + 1) . '.png', 'bytes' => $bytes];
                    }
                    continue;
                }

                if ($inner === ExportFormat::CSV) {
                    foreach ($this->csv->entries($document) as $entry) {
                        $entries[] = ['path' => $name . '-' . basename($entry['path']), 'bytes' => $entry['bytes']];
                    }
                    continue;
                }

                if ($inner === ExportFormat::HTML) {
                    $entries[] = [
                        'path' => $name . '.html',
                        'bytes' => $this->renderer->render($document, $built['identity'], $this->renderOptions($options), true),
                    ];
                    continue;
                }

                $pdf = $this->pdfBytes($document, $built['identity'], $options);
                $pageTotal += $pdf['pages'];
                $entries[] = ['path' => $name . '.pdf', 'bytes' => $pdf['bytes']];
            }
        }

        return [
            'bytes' => $this->archive->build(
                'NIZAM_Export_' . ($identity->yearLabel !== '' ? str_replace('/', '-', $identity->yearLabel) : date('Y')),
                $entries,
                $this->readme($identity, $type, count($items), $inner)
            ),
            'filename' => $base . '.zip',
            'contentType' => ExportFormat::mime(ExportFormat::ZIP),
            'format' => ExportFormat::ZIP,
            'pages' => $pageTotal,
            'entries' => count($entries),
            'warnings' => [],
        ];
    }

    /** @param array<string,mixed> $options */
    private function renderOptions(array $options): array
    {
        return [
            'signatures' => !isset($options['signatures']) || dbBool($options['signatures']),
            'watermark' => isset($options['watermark']) && dbBool($options['watermark']),
            'compact' => isset($options['compact']) && dbBool($options['compact']),
            'embed' => isset($options['embed']) && dbBool($options['embed']),
            'print' => isset($options['print']) && dbBool($options['print']),
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

            $clean[$name] = mb_substr((string) $value, 0, 200);
        }

        return $clean;
    }

    /**
     * @return array{
     *     bytes:string, filename:string, contentType:string,
     *     format:string, pages:int, entries:int, warnings:list<string>
     * }
     */
    private function png(Document $document, DocumentIdentity $identity, array $options, string $base): array
    {
        $scale = max(1.0, min(3.0, (float) ($options['scale'] ?? 2)));
        $pages = $this->pngBytes($document, $identity, $options, $scale);

        if (count($pages) === 1) {
            return [
                'bytes' => $pages[0],
                'filename' => $base . '.png',
                'contentType' => ExportFormat::mime(ExportFormat::PNG),
                'format' => ExportFormat::PNG,
                'pages' => 1,
                'entries' => 1,
                'warnings' => [],
            ];
        }

        $entries = [];
        $name = DocumentArchiveBuilder::safeName($document->filename);
        foreach ($pages as $index => $bytes) {
            $entries[] = ['path' => $name . '-' . ($index + 1) . '.png', 'bytes' => $bytes];
        }

        $readme = "NIZAM PNG Export\n================\n\n"
            . 'Document: ' . $document->title . "\n"
            . 'Images: ' . count($pages) . ' (one per page, in reading order)' . "\n"
            . 'Resolution: ' . (int) round($scale * 96) . " dpi\n"
            . 'Generated: ' . $identity->issueDate . "\n";

        return [
            'bytes' => $this->archive->build('NIZAM_Images', $entries, $readme),
            'filename' => $base . '-pages.zip',
            'contentType' => ExportFormat::mime(ExportFormat::ZIP),
            'format' => ExportFormat::PNG,
            'pages' => count($pages),
            'entries' => count($pages),
            'warnings' => ['multi_page'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * CSV export of a Document's real tabular data.
 *
 * The workbook is for people who want a formatted page; CSV is for people who
 * want the records. So this exporter makes no attempt to reproduce the paper
 * design: it writes the rows exactly as the document resolved them, one clean
 * table per block, so they can be opened in Excel, LibreOffice, Google Sheets
 * or imported by another system without cleaning up merged title rows.
 *
 * Details that matter in a real school office:
 *  - a UTF-8 BOM, because Excel on Windows otherwise opens an Arabic file as
 *    mojibake;
 *  - CRLF line endings, the RFC 4180 convention every spreadsheet accepts;
 *  - values starting with = + @ (and a bare -) are neutralised so a student
 *    name cannot execute as a formula in Excel;
 *  - a document with more than one table becomes a ZIP of one CSV per table,
 *    rather than one file with unrelated tables stacked on top of each other.
 */
final class CsvExporter
{
    private const BOM = "\xEF\xBB\xBF";

    /**
     * One entry per logical table in the document.
     *
     * @return list<array{path:string, bytes:string, rows:int, title:string}>
     */
    public function entries(Document $document): array
    {
        $sections = $this->sections($document);

        // CSV is for records, not for the page furniture: when a document has
        // a real record table, the summary strip and the note are not exported
        // as separate files just because they exist. A profile document with
        // no table still exports its fields.
        $records = array_values(array_filter(
            $sections,
            static fn (array $section): bool => in_array($section['kind'] ?? '', ['table', 'grid'], true)
        ));
        if ($records !== []) {
            $sections = $records;
        }

        if ($sections === []) {
            $sections = [['title' => $document->title, 'rows' => [[__('document.no_data')]], 'kind' => 'table']];
        }

        $entries = [];
        $used = [];
        $multiple = count($sections) > 1;

        foreach ($sections as $index => $section) {
            $title = trim((string) $section['title']);
            if ($title === '') {
                $title = $document->title;
            }
            $base = DocumentArchiveBuilder::safeName($title);
            if ($multiple) {
                $base = sprintf('%02d_', $index + 1) . $base;
            }
            $base = $this->unique($base, $used);

            $entries[] = [
                'path' => $base . '.csv',
                'bytes' => $this->encode($section['rows']),
                'rows' => max(0, count($section['rows']) - 1),
                'title' => $title,
            ];
        }

        return $entries;
    }

    /**
     * A single CSV when the document has one table, otherwise a ZIP of CSVs.
     *
     * @return array{bytes:string, filename:string, contentType:string, entries:int}
     */
    public function export(Document $document, string $base): array
    {
        $entries = $this->entries($document);

        if (count($entries) === 1) {
            return [
                'bytes' => $entries[0]['bytes'],
                'filename' => $base . '.csv',
                'contentType' => ExportFormat::mime(ExportFormat::CSV),
                'entries' => 1,
            ];
        }

        $archive = [];
        foreach ($entries as $entry) {
            $archive[] = ['path' => $entry['path'], 'bytes' => $entry['bytes']];
        }

        $readme = "NIZAM CSV Export\n================\n\n"
            . 'Document: ' . $document->title . "\n"
            . 'Tables: ' . count($entries) . "\n"
            . "Generated: " . date('d/m/Y H:i') . "\n\n"
            . "Each file is one table from the document, with its original columns.\n";

        return [
            'bytes' => (new DocumentArchiveBuilder())->build('NIZAM_CSV', $archive, $readme),
            'filename' => $base . '-csv.zip',
            'contentType' => ExportFormat::mime(ExportFormat::ZIP),
            'entries' => count($entries),
        ];
    }

    /**
     * Extract every block that carries records as a header row plus data rows.
     *
     * @return list<array{title:string, rows:list<list<string>>}>
     */
    private function sections(Document $document): array
    {
        $sections = [];

        foreach ($document->blocks as $block) {
            $type = (string) ($block['type'] ?? 'table');
            $title = trim((string) ($block['title'] ?? ''));
            if ($title === '') {
                $title = (string) ($block['type'] ?? $document->title);
            }

            if ($type === 'table') {
                $columns = $block['columns'] ?? [];
                if ($columns === [] && empty($block['rows'])) {
                    continue;
                }
                $rows = [];
                $head = [];
                foreach ($columns as $column) {
                    $head[] = (string) ($column['label'] ?? '');
                }
                $rows[] = $head;

                foreach ($block['rows'] ?? [] as $row) {
                    $line = [];
                    foreach ($row as $cell) {
                        $line[] = is_array($cell) ? (string) ($cell['value'] ?? '') : (string) $cell;
                    }
                    $rows[] = $line;
                }

                if (!empty($block['totals'])) {
                    $line = [];
                    foreach ($block['totals'] as $cell) {
                        $line[] = is_array($cell) ? (string) ($cell['value'] ?? '') : (string) $cell;
                    }
                    $rows[] = $line;
                }

                $sections[] = ['title' => $title, 'rows' => $rows, 'kind' => 'table'];
                continue;
            }

            if ($type === 'grid') {
                $head = $block['head'] ?? [];
                if ($head === [] && empty($block['rows'])) {
                    continue;
                }
                $rows = [];
                $header = [(string) ($block['corner'] ?? '')];
                foreach ($head as $heading) {
                    $header[] = (string) $heading;
                }
                $rows[] = $header;

                foreach ($block['rows'] ?? [] as $gridRow) {
                    $line = [(string) ($gridRow['label'] ?? '')];
                    foreach ($gridRow['cells'] ?? [] as $cell) {
                        $line[] = $this->gridCell($cell);
                    }
                    $rows[] = $line;
                }

                $sections[] = ['title' => $title, 'rows' => $rows, 'kind' => 'grid'];
                continue;
            }

            if ($type === 'fields') {
                $rows = [[
                    __('document.csv_section'),
                    __('document.csv_field'),
                    __('document.csv_value'),
                ]];
                foreach ($block['sections'] ?? [] as $section) {
                    $sectionTitle = (string) ($section['title'] ?? '');
                    foreach ($section['fields'] ?? [] as $field) {
                        $rows[] = [
                            $sectionTitle,
                            (string) ($field['label'] ?? ''),
                            (string) ($field['value'] ?? ''),
                        ];
                    }
                }
                if (count($rows) > 1) {
                    $sections[] = ['title' => $title, 'rows' => $rows, 'kind' => 'fields'];
                }
                continue;
            }

            if ($type === 'summary') {
                $rows = [[
                    __('document.csv_metric'),
                    __('document.csv_value'),
                ]];
                foreach ($block['cards'] ?? [] as $card) {
                    $rows[] = [(string) ($card['label'] ?? ''), (string) ($card['value'] ?? '')];
                }
                if (count($rows) > 1) {
                    $sections[] = ['title' => $title, 'rows' => $rows, 'kind' => 'summary'];
                }
                continue;
            }

            if ($type === 'notes' && trim((string) ($block['text'] ?? '')) !== '') {
                $sections[] = [
                    'title' => $title,
                    'rows' => [[__('document.csv_note')], [trim((string) $block['text'])]],
                    'kind' => 'notes',
                ];
            }
        }

        return $sections;
    }

    /** @param array<string,mixed> $cell */
    private function gridCell(array $cell): string
    {
        $type = (string) ($cell['type'] ?? 'lesson');
        if ($type === 'break') {
            return (string) ($cell['text'] ?? __('document.break'));
        }
        if ($type === 'empty') {
            return '';
        }

        $parts = [];
        foreach (['subject', 'teacher', 'class'] as $key) {
            $value = trim((string) ($cell[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }
        if (!empty($cell['clash'])) {
            $parts[] = __('document.clash_badge');
        } elseif (!empty($cell['resolved'])) {
            $parts[] = __('document.clash_status_resolved');
        } elseif (!empty($cell['unassigned'])) {
            $parts[] = __('document.legend_unassigned');
        }

        return implode(' · ', $parts);
    }

    /**
     * RFC 4180 encoding with a UTF-8 BOM and formula-injection protection.
     *
     * @param list<list<string>> $rows
     */
    private function encode(array $rows): string
    {
        $out = self::BOM;
        foreach ($rows as $row) {
            $cells = [];
            foreach (array_values($row) as $value) {
                $cells[] = $this->cell((string) $value);
            }
            $out .= implode(',', $cells) . "\r\n";
        }

        return $out;
    }

    private function cell(string $value): string
    {
        // A cell is a number only when it is exactly a number; everything
        // else (including an Arabic name and a student code) stays text.
        if (preg_match('/^-?\d+(\.\d+)?$/u', $value) === 1) {
            return $value;
        }

        // Excel executes a leading = + @ as a formula. A hyphen is only
        // dangerous when it is not the sign of a number.
        if (preg_match('/^[=+@]/u', $value) === 1 || preg_match('/^-(?![\d.])/u', $value) === 1) {
            $value = "'" . $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /** @param array<string,bool> $used */
    private function unique(string $base, array &$used): string
    {
        $candidate = $base;
        $suffix = 2;
        while (isset($used[$candidate])) {
            $candidate = $base . '-' . $suffix++;
        }
        $used[$candidate] = true;

        return $candidate;
    }
}

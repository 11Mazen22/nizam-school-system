<?php

declare(strict_types=1);

namespace App\Services\Documents;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Genuine, deliberately formatted .xlsx workbooks.
 *
 * Not a CSV renamed to .xlsx, and not a raw dump: every sheet carries the
 * school identity, the document title, the academic year and the issue date;
 * headings are styled, borders are thin and consistent, rows alternate, the
 * heading row repeats when printed, panes freeze below it, and an Arabic
 * workbook is right-to-left throughout with right-aligned text.
 *
 * Values are written exactly as the documents print them (already resolved
 * and formatted by the same services), so a workbook can never disagree with
 * its PDF.
 */
final class ExcelWorkbookBuilder
{
    private const EMERALD = '0F5132';
    private const EMERALD_DARK = '083D26';
    private const EMERALD_SOFT = 'EAF3ED';
    private const GOLD = 'C8A45D';
    private const BORDER = 'DCE4DE';
    private const ZEBRA = 'F8FAF8';

    /**
     * @param list<Document> $documents one sheet per document
     */
    public function build(array $documents, DocumentIdentity $identity, string $title): string
    {
        if ($documents === []) {
            throw new RuntimeException('document_empty');
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('NIZAM')
            ->setTitle($title)
            ->setDescription($identity->nameAr);

        $spreadsheet->removeSheetByIndex(0);
        $usedNames = [];

        // Names are assigned before any sheet is written so the index sheet
        // can point at the exact name each document received (including the
        // numeric suffix a duplicate class or teacher name needs).
        $names = [];
        foreach ($documents as $index => $document) {
            $names[$index] = $this->sheetName($document, $index, $usedNames);
        }

        $offset = 0;
        if (count($documents) > 1) {
            $indexSheet = new Worksheet($spreadsheet, $this->freeSheetName(__('document.workbook_index'), $usedNames));
            $spreadsheet->addSheet($indexSheet, 0);
            $this->writeIndex($indexSheet, $documents, $names, $identity);
            $offset = 1;
        }

        foreach ($documents as $index => $document) {
            $sheet = new Worksheet($spreadsheet, $names[$index]);
            $spreadsheet->addSheet($sheet, $index + $offset);
            $this->writeSheet($sheet, $document, $identity);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $temp = tempnam(sys_get_temp_dir(), 'nizam_xlsx_');
        if ($temp === false) {
            throw new RuntimeException('document_export_failed');
        }
        $writer->save($temp);
        $bytes = (string) file_get_contents($temp);
        @unlink($temp);

        return $bytes;
    }

    private function sheetName(Document $document, int $index, array &$used): string
    {
        $raw = trim((string) ($document->meta['sheet_name'] ?? $document->subtitle));
        if ($raw === '') {
            $raw = $document->title;
        }
        if (trim($raw) === '') {
            $raw = 'Sheet ' . ($index + 1);
        }

        return $this->freeSheetName($raw, $used);
    }

    /** Assign a unique, Excel-legal sheet name (31 characters, no []:*?/\\). */
    private function freeSheetName(string $raw, array &$used): string
    {
        $clean = function_exists('mb_substr') ? mb_substr($raw, 0, 31) : substr($raw, 0, 31);
        $clean = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/u', ' ', $clean) ?? $clean;
        $clean = trim($clean);
        if ($clean === '') {
            $clean = 'Sheet';
        }

        $base = $clean;
        $suffix = 2;
        while (isset($used[$clean])) {
            $tail = ' ' . $suffix++;
            $limit = 31 - strlen($tail);
            $clean = (function_exists('mb_substr') ? mb_substr($base, 0, $limit) : substr($base, 0, $limit)) . $tail;
        }
        $used[$clean] = true;

        return $clean;
    }

    /**
     * A first sheet listing every other sheet. A fifty-teacher workbook is
     * unusable without it: the tab strip shows a handful of names and the
     * office has no way to find the rest except scrolling.
     *
     * @param list<Document> $documents
     * @param array<int,string> $names
     */
    private function writeIndex(Worksheet $sheet, array $documents, array $names, DocumentIdentity $identity): void
    {
        $isRtl = $identity->locale === 'ar';
        $sheet->setRightToLeft($isRtl);
        $sheet->setShowGridlines(false);

        $columns = [
            ['label' => '#', 'width' => 8.0],
            ['label' => __('document.workbook_sheet'), 'width' => 30.0],
            ['label' => __('document.workbook_document'), 'width' => 34.0],
            ['label' => __('document.rows_count'), 'width' => 12.0],
            ['label' => __('document.workbook_scope'), 'width' => 46.0],
        ];
        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

        $row = 1;
        $this->titleRow($sheet, $row, $lastColumn, $identity->nameAr, 14, self::EMERALD_DARK);
        $row++;
        if ($identity->nameEn !== '' && $identity->nameEn !== $identity->nameAr) {
            $this->titleRow($sheet, $row, $lastColumn, $identity->nameEn, 10, '64748B');
            $row++;
        }
        $this->titleRow($sheet, $row, $lastColumn, __('document.workbook_title'), 12, self::EMERALD);
        $row++;
        $this->titleRow(
            $sheet,
            $row,
            $lastColumn,
            __('document.academic_year') . ': ' . $identity->yearLabel . '   |   '
                . __('document.issued_on') . ': ' . $identity->issueDate,
            9,
            '64748B'
        );
        $row += 2;

        $headerRow = $row;
        foreach ($columns as $index => $column) {
            $sheet->setCellValue([$index + 1, $row], $column['label']);
        }
        $this->styleHeader($sheet, [1, $headerRow, count($columns), $headerRow]);
        $sheet->setAutoFilter('A' . $headerRow . ':' . $lastColumn . $headerRow);
        $row++;

        foreach ($documents as $index => $document) {
            $sheet->setCellValue([1, $row], (string) ($index + 1));
            $sheet->getStyle([1, $row])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue([2, $row], $names[$index] ?? '');
            $sheet->getStyle([2, $row])->getFont()->getColor()->setRGB(self::EMERALD);
            $sheet->setCellValue([3, $row], $document->title . ($document->subtitle !== '' ? ' — ' . $document->subtitle : ''));
            $sheet->setCellValue([4, $row], $this->recordCount($document));
            $sheet->getStyle([4, $row])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $scope = [];
            foreach ($document->scopePairs() as $pair) {
                $scope[] = $pair['label'] . ': ' . $pair['value'];
            }
            $sheet->setCellValue([5, $row], implode('   |   ', $scope));
            if (($index % 2) === 1) {
                $sheet->getStyle([1, $row, count($columns), $row])->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ZEBRA);
            }
            $row++;
        }

        $range = [1, $headerRow, count($columns), max($headerRow, $row - 1)];
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BORDER);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal($isRtl ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        foreach ($columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($letter)->setWidth((float) $column['width']);
        }

        $sheet->freezePane([1, $headerRow + 1]);
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);
        $sheet->getHeaderFooter()->setOddFooter('&L&8' . $identity->nameAr . '&R&8&P / &N');
    }

    private function recordCount(Document $document): int
    {
        $count = 0;
        foreach ($document->blocks as $block) {
            if (in_array((string) ($block['type'] ?? ''), ['table', 'grid'], true)) {
                $count += count((array) ($block['rows'] ?? []));
            }
        }

        return $count;
    }

    private function writeSheet(Worksheet $sheet, Document $document, DocumentIdentity $identity): void
    {
        $isRtl = $identity->locale === 'ar';
        $sheet->setRightToLeft($isRtl);

        $columnCount = 1;
        foreach ($document->blocks as $block) {
            $width = count($block['columns'] ?? $block['head'] ?? []);
            // A timetable sheet also writes an explicit clash table below the
            // grid when the printed paper double-booked a teacher (or when a
            // clash has since been resolved), so its sheet must be wide enough
            // for every machine-readable clash column.
            if (($block['type'] ?? '') === 'grid' && !empty($block['clashes'])) {
                $width = max($width + 1, 9);
            }
            $columnCount = max($columnCount, $width);
        }
        $columnCount = max($columnCount, 4);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        $row = 1;
        $this->titleRow($sheet, $row, $lastColumn, $identity->nameAr, 14, self::EMERALD_DARK);
        $row++;
        if ($identity->nameEn !== '' && $identity->nameEn !== $identity->nameAr) {
            $this->titleRow($sheet, $row, $lastColumn, $identity->nameEn, 10, '64748B');
            $row++;
        }
        foreach ($identity->institutionalLines() as $line) {
            $this->titleRow($sheet, $row, $lastColumn, $line, 9, '64748B');
            $row++;
        }

        $this->titleRow($sheet, $row, $lastColumn, $document->title, 12, self::EMERALD);
        $row++;
        if ($document->subtitle !== '') {
            $this->titleRow($sheet, $row, $lastColumn, $document->subtitle, 10, '17251F');
            $row++;
        }

        $scopeLine = [];
        if ($identity->yearLabel !== '') {
            $scopeLine[] = __('document.academic_year') . ': ' . $identity->yearLabel;
        }
        foreach ($document->scopePairs() as $pair) {
            $scopeLine[] = $pair['label'] . ': ' . $pair['value'];
        }
        if ($scopeLine !== []) {
            $this->titleRow($sheet, $row, $lastColumn, implode('   |   ', $scopeLine), 9, '64748B');
            $row++;
        }
        $this->titleRow($sheet, $row, $lastColumn, __('document.issued_on') . ': ' . $identity->issueDate, 9, '64748B');
        $row += 2;

        $headerRowForPrint = null;
        $autoFilterSet = false;

        // Column widths are measured from the text actually written, in
        // characters. PhpSpreadsheet's font-metric sizing leaves every Arabic
        // column at the default width, which clips the names this school's
        // documents are mostly made of.
        $widthHints = [];
        $noteWidth = static function (int $column, string $text) use (&$widthHints): void {
            $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
            $widthHints[$column] = max($widthHints[$column] ?? 0, $length);
        };

        foreach ($document->blocks as $block) {
            $type = (string) ($block['type'] ?? 'table');

            if ($type === 'summary') {
                foreach ($block['cards'] ?? [] as $card) {
                    // Summary cards are not table columns: measuring them here
                    // would widen the first data column with a label.
                    $sheet->setCellValue([1, $row], (string) ($card['label'] ?? ''));
                    $sheet->getStyle([1, $row])->getFont()->setBold(true)->getColor()->setRGB(self::EMERALD);
                    $sheet->setCellValue([2, $row], (string) ($card['value'] ?? ''));
                    $sheet->getStyle([2, $row])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $row++;
                }
                $row++;
                continue;
            }

            if ($type === 'fields') {
                foreach ($block['sections'] ?? [] as $section) {
                    $sheet->setCellValue([1, $row], (string) ($section['title'] ?? ''));
                    $sheet->mergeCells([1, $row, $columnCount, $row]);
                    $sheet->getStyle([1, $row])->getFont()->setBold(true)->getColor()->setRGB(self::EMERALD);
                    $sheet->getStyle([1, $row])->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(self::EMERALD_SOFT);
                    $row++;
                    foreach ($section['fields'] ?? [] as $field) {
                        $sheet->setCellValue([1, $row], (string) ($field['label'] ?? ''));
                        $noteWidth(1, (string) ($field['label'] ?? ''));
                        $sheet->getStyle([1, $row])->getFont()->getColor()->setRGB('64748B');
                        $sheet->setCellValue([2, $row], (string) ($field['value'] ?? ''));
                        $noteWidth(2, (string) ($field['value'] ?? ''));
                        $sheet->getStyle([2, $row])->getFont()->setBold(!($field['missing'] ?? false));
                        $row++;
                    }
                    $row++;
                }
                continue;
            }

            if ($type === 'grid') {
                $head = $block['head'] ?? [];
                $sheet->mergeCells([1, $row, max(2, count($head) + 1), $row]);
                $sheet->setCellValue([1, $row], (string) ($block['title'] ?? ''));
                $sheet->getStyle([1, $row])->getFont()->setBold(true)->getColor()->setRGB(self::EMERALD);
                $row++;

                $sheet->setCellValue([1, $row], (string) ($block['corner'] ?? ''));
                $column = 2;
                foreach ($head as $heading) {
                    $sheet->setCellValue([$column, $row], (string) $heading);
                    $noteWidth($column, (string) $heading);
                    $column++;
                }
                $headerRange = [1, $row, max(2, $column - 1), $row];
                $this->styleHeader($sheet, $headerRange);
                $headerRowForPrint ??= $row;
                $row++;

                foreach ($block['rows'] ?? [] as $gridRow) {
                    $sheet->setCellValue([1, $row], (string) ($gridRow['label'] ?? ''));
                    $sheet->getStyle([1, $row])->getFont()->setBold(true);
                    $column = 2;
                    foreach ($gridRow['cells'] ?? [] as $cell) {
                        $text = match ((string) ($cell['type'] ?? 'empty')) {
                            'break' => (string) ($cell['text'] ?? __('document.break')),
                            'empty' => '—',
                            default => trim(($cell['subject'] ?? '') . ' ' . ($cell['teacher'] ?? '') . ' ' . ($cell['class'] ?? '')),
                        };
                        $sheet->setCellValue([$column, $row], $text);
                        $noteWidth($column, $text);
                        $sheet->getStyle([$column, $row])->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                            ->setWrapText(true);
                        if (!empty($cell['clash'])) {
                            $sheet->getStyle([$column, $row])->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
                            $sheet->getStyle([$column, $row])->getFont()->getColor()->setRGB('7F1D1D');
                        } elseif (!empty($cell['resolved'])) {
                            $sheet->getStyle([$column, $row])->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7');
                            $sheet->getStyle([$column, $row])->getFont()->getColor()->setRGB('166534');
                        } elseif (!empty($cell['unassigned'])) {
                            $sheet->getStyle([$column, $row])->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
                        }
                        $column++;
                    }
                    $row++;
                }

                // A printed clash is not just a coloured cell: every one is
                // written again as an explicit row with its own columns, so a
                // workbook consumer can filter or count them without parsing
                // cell text.
                if (!empty($block['clashes'])) {
                    $row++;
                    $headings = [
                        __('document.clash_day'),
                        __('document.clash_period'),
                        __('document.clash_class'),
                        __('document.clash_subject'),
                        __('document.clash_assigned_teacher'),
                        __('document.clash_printed_teacher'),
                        __('document.clash_other_class'),
                        __('document.clash_status'),
                        __('document.clash_source'),
                    ];
                    $sheet->mergeCells([1, $row, max(count($headings), count($head) + 1), $row]);
                    $sheet->setCellValue([1, $row], __('document.clash_export_title'));
                    $sheet->getStyle([1, $row])->getFont()->setBold(true)->getColor()->setRGB('7F1D1D');
                    $sheet->getStyle([1, $row])->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
                    $row++;

                    $column = 1;
                    foreach ($headings as $heading) {
                        $sheet->setCellValue([$column, $row], $heading);
                        $noteWidth($column, $heading);
                        $column++;
                    }
                    $this->styleHeader($sheet, [1, $row, count($headings), $row]);
                    $row++;

                    // Every clash stays a plain, filterable row: an office
                    // assistant can sort by status or filter the class column
                    // without deciphering a coloured cell or a tooltip.
                    foreach ($block['clashes'] as $clash) {
                        $resolved = (string) ($clash['status'] ?? 'open') === 'resolved';
                        $values = [
                            (string) ($clash['day'] ?? ''),
                            (string) ($clash['period'] ?? ''),
                            (string) ($clash['class'] ?? ''),
                            (string) ($clash['subject'] ?? ''),
                            (string) ($clash['teacher'] ?? ''),
                            (string) ($clash['printed_teacher'] ?? $clash['teacher'] ?? ''),
                            (string) ($clash['other'] ?? ''),
                            $resolved ? __('document.clash_status_resolved') : __('document.clash_status_open'),
                            (string) ($clash['source'] ?? ''),
                        ];
                        $column = 1;
                        foreach ($values as $value) {
                            $sheet->setCellValue([$column, $row], $value);
                            $noteWidth($column, $value);
                            $column++;
                        }
                        if ($resolved) {
                            $sheet->getStyle([1, $row, count($values), $row])->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7');
                            $sheet->getStyle([8, $row])->getFont()->getColor()->setRGB('166534');
                        } else {
                            $sheet->getStyle([1, $row, count($values), $row])->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
                            $sheet->getStyle([3, $row])->getFont()->getColor()->setRGB('7F1D1D');
                            $sheet->getStyle([8, $row])->getFont()->getColor()->setRGB('92400E');
                        }
                        $row++;
                    }
                }
                $row++;
                continue;
            }

            // Table block
            $columns = $block['columns'] ?? [];
            if (!empty($block['title'])) {
                $sheet->mergeCells([1, $row, $columnCount, $row]);
                $sheet->setCellValue([1, $row], (string) $block['title']);
                $sheet->getStyle([1, $row])->getFont()->setBold(true)->getColor()->setRGB(self::EMERALD);
                $row++;
            }

            $headerRow = $row;
            $column = 1;
            foreach ($columns as $columnDefinition) {
                $sheet->setCellValue([$column, $row], (string) ($columnDefinition['label'] ?? ''));
                $noteWidth($column, (string) ($columnDefinition['label'] ?? ''));
                $column++;
            }
            $this->styleHeader($sheet, [1, $headerRow, max(1, $column - 1), $headerRow]);
            $headerRowForPrint ??= $headerRow;
            if (!$autoFilterSet) {
                $sheet->setAutoFilter(
                    'A' . $headerRow . ':' . Coordinate::stringFromColumnIndex(max(1, $column - 1)) . $headerRow
                );
                $autoFilterSet = true;
            }
            $row++;

            $firstDataRow = $row;
            foreach ($block['rows'] ?? [] as $dataRow) {
                $column = 1;
                foreach ($dataRow as $cell) {
                    $value = is_array($cell) ? (string) ($cell['value'] ?? '') : (string) $cell;
                    $sheet->setCellValue([$column, $row], $value);
                    $noteWidth($column, $value);
                    $column++;
                }
                if (($row - $firstDataRow) % 2 === 1) {
                    $sheet->getStyle([1, $row, max(1, $column - 1), $row])->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ZEBRA);
                }
                $row++;
            }

            if (!empty($block['totals'])) {
                $column = 1;
                foreach ($block['totals'] as $cell) {
                    $value = is_array($cell) ? (string) ($cell['value'] ?? '') : (string) $cell;
                    $sheet->setCellValue([$column, $row], $value);
                    $noteWidth($column, $value);
                    $column++;
                }
                $range = [1, $row, max(1, $column - 1), $row];
                $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB(self::EMERALD_DARK);
                $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::EMERALD_SOFT);
                $row++;
            }

            $row++;
        }

        $sheet->setCellValue([1, $row], __('document.generated_by') . ' · ' . $identity->issueDate);
        $sheet->getStyle([1, $row])->getFont()->setItalic(true)->setSize(8)->getColor()->setRGB('94A3B8');

        // Borders and alignment over everything that actually carries data.
        $dataRange = [1, 1, $columnCount, max(1, $row - 1)];
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BORDER);
        // Right-to-left cell order is inherited from the worksheet's own
        // setRightToLeft(); this only sets the visual alignment of the text.
        $sheet->getStyle($dataRange)->getAlignment()
            ->setHorizontal($isRtl ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // Header alignment must stay centred after the range-level default.
        if ($headerRowForPrint !== null) {
            $sheet->getStyle([1, $headerRowForPrint, $columnCount, $headerRowForPrint])->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach (range(1, $columnCount) as $index) {
            $letter = Coordinate::stringFromColumnIndex($index);
            $characters = (float) ($widthHints[$index] ?? 10) * 1.1 + 2.0;
            $sheet->getColumnDimension($letter)->setWidth(min(46.0, max(10.0, $characters)));
        }

        if ($headerRowForPrint !== null) {
            $sheet->freezePane([1, $headerRowForPrint + 1]);
        }

        // Print settings: A4, fit to one page wide, repeating title row.
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setOrientation($document->isLandscape() ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setHorizontalCentered(true);
        if ($headerRowForPrint !== null) {
            // Repeat the heading row when the sheet is printed across pages,
            // matching the PDF's own repeated table headings.
            $pageSetup->setRowsToRepeatAtTopByStartAndEnd($headerRowForPrint, $headerRowForPrint);
        }
        $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);
        $sheet->setShowGridlines(false);
        $sheet->setSelectedCell('A1');
        // The workbook is a print artefact too: its own footer carries the
        // school name and page numbers just like the PDF. &P / &N are Excel's
        // page / total-pages codes.
        $sheet->getHeaderFooter()->setOddFooter('&L&8' . $identity->nameAr . '&R&8&P / &N');
    }

    private function titleRow(Worksheet $sheet, int $row, string $lastColumn, string $text, int $size, string $color): void
    {
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->setCellValue([1, $row], $text);
        $sheet->getStyle([1, $row])->getFont()->setBold($size >= 10)->setSize($size)->getColor()->setRGB($color);
        $sheet->getStyle([1, $row])->getAlignment()->setWrapText(false);
    }

    /** @param array{0:int,1:int,2:int,3:int} $range */
    private function styleHeader(Worksheet $sheet, array $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::EMERALD);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::GOLD);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class ImportService
{
    /**
     * Parses an uploaded Excel or CSV file.
     * Expects the first row to be headers.
     * Returns an array of associative arrays: [ ["Header1" => "Value1"], ... ]
     */
    public function parseFile(string $filePath, array $expectedHeaders = []): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found");
        }

        if (filesize($filePath) > 5 * 1024 * 1024) {
            throw new RuntimeException('Import file is too large');
        }
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        if ($highestRow > 10001 || \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn) > 50) {
            throw new RuntimeException('Import exceeds row or column limit');
        }
        
        $headers = [];
        $data = [];
        
        // Read headers
        $headerRow = $sheet->rangeToArray("A1:{$highestColumn}1", null, false, false)[0];
        foreach ($headerRow as $colIndex => $headerText) {
            $headers[$colIndex] = trim((string) $headerText);
        }
        if (in_array('', $headers, true) || count(array_unique($headers)) !== count($headers)) {
            throw new RuntimeException('Import headers must be nonempty and unique');
        }
        if ($expectedHeaders !== [] && (array_diff($expectedHeaders, $headers) !== [] || array_diff($headers, $expectedHeaders) !== [])) {
            throw new RuntimeException('Import headers do not match the template');
        }
        
        // Read data rows
        if ($highestRow > 1) {
            $dataRows = $sheet->rangeToArray("A2:{$highestColumn}{$highestRow}", null, false, false);
            foreach ($dataRows as $row) {
                $rowData = [];
                $isEmptyRow = true;
                foreach ($row as $colIndex => $cellValue) {
                    $header = $headers[$colIndex] ?? "Col_{$colIndex}";
                    $val = trim((string) $cellValue);
                    $rowData[$header] = $val;
                    if ($val !== "") {
                        $isEmptyRow = false;
                    }
                }
                if (!$isEmptyRow) {
                    $data[] = $rowData;
                }
            }
        }
        
        return $data;
    }

    /**
     * Generates a blank Excel template with the specified headers.
     */
    public function generateTemplate(array $headers, string $filename, string $locale, array $referenceRows = [], array $dataRows = []): void
    {
        $bytes = $this->templateBytes($headers, $locale, $referenceRows, $dataRows);

        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=\"{$filename}.xlsx\"");
        header("Content-Length: " . strlen($bytes));
        header("Cache-Control: max-age=0");

        echo $bytes;
        exit;
    }

    /**
     * Build template bytes without emitting headers or terminating the request.
     * Keeping generation separate from HTTP delivery makes the XLSX contract
     * directly testable and reusable while generateTemplate() preserves the
     * existing download endpoint behavior.
     */
    public function templateBytes(array $headers, string $locale, array $referenceRows = [], array $dataRows = []): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft($locale === "ar");
        
        // Write headers
        $col = "A";
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}1", $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
        
        $sheet->getStyle("A1:" . $sheet->getHighestColumn() . "1")->getFont()->setBold(true);

        foreach ($dataRows as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
                $sheet->setCellValue($column . ($rowIndex + 2), $value);
            }
        }

        if ($referenceRows !== []) {
            $reference = $spreadsheet->createSheet();
            $reference->setTitle($locale === 'ar' ? 'الفصول المتاحة' : 'Available Classes');
            $reference->setRightToLeft($locale === 'ar');
            $columns = array_keys($referenceRows[0]);
            foreach ($columns as $index => $header) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                $reference->setCellValue("{$column}1", $header);
                $reference->getColumnDimension($column)->setAutoSize(true);
            }
            foreach ($referenceRows as $rowIndex => $row) {
                foreach (array_values($row) as $columnIndex => $value) {
                    $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1);
                    $reference->setCellValue("{$column}" . ($rowIndex + 2), $value);
                }
            }
            $reference->getStyle('A1:' . $reference->getHighestColumn() . '1')->getFont()->setBold(true);
        }
        
        $writer = new Xlsx($spreadsheet);
        ob_start();
        try {
            $writer->save('php://output');
            $bytes = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        if (!is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Could not generate import template');
        }

        return $bytes;
    }
}


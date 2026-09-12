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
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found");
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        
        $headers = [];
        $data = [];
        
        // Read headers
        $headerRow = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0];
        foreach ($headerRow as $colIndex => $headerText) {
            $headers[$colIndex] = trim((string) $headerText);
        }
        
        // Read data rows
        if ($highestRow > 1) {
            $dataRows = $sheet->rangeToArray("A2:{$highestColumn}{$highestRow}", null, true, false);
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
    public function generateTemplate(array $headers, string $filename, string $locale): void
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
        
        $writer = new Xlsx($spreadsheet);
        
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=\"{$filename}.xlsx\"");
        header("Cache-Control: max-age=0");
        
        $writer->save("php://output");
        exit;
    }
}


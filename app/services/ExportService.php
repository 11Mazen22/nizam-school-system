<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SchoolRepository;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Nizam -- §K's shared PDF/Excel export builders (§Q "ExportService | toPdf,
 * toExcel"). Deliberately generic: both methods take the exact same
 * {title, generated_at, year_label, filters_summary, headers, rows} shape
 * ReportService::build() already returns for every one of the six reports,
 * so this class carries no per-report knowledge at all.
 */
final class ExportService
{
    public function __construct(private readonly SchoolRepository $schools = new SchoolRepository())
    {
    }

    /**
     * mPDF, Arabic-shaping font embedded per O-15 (see the font-choice note
     * below), RTL/LTR set explicitly per O-32 rather than left to a library
     * default. A4, header/footer carrying the §K "shared report conventions"
     * block (school identity, title, active year, filters, generated-on,
     * page numbers), no split table rows (thead repeats via mPDF's own
     * <thead> handling).
     */
    public function toPdf(array $report, string $locale): string
    {
        $fontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new FontVariables())->getDefaults()['fontdata'];

        // Phase 7 verification found that the vendored Amiri 1.000 build's
        // GSUB table uses a lookup subtable format mPDF's own OTL parser
        // doesn't support (Mpdf\Exception\FontException: "GPOS Lookup Type
        // ..., Format ... not supported"), thrown unconditionally at font
        // load time whenever useOTL is enabled for it. The original Phase 7
        // pass worked around the crash with useOTL=0 -- but useOTL is what
        // drives ALL of mPDF's Arabic contextual-joining/ligature
        // substitution (Otl::applyOTL() never runs when it's falsy), so that
        // workaround silently disabled real Arabic shaping: every letter was
        // drawn in one fixed, context-independent glyph form regardless of
        // position, confirmed by inspecting the actual PDF output (e.g. "ﻻ"
        // lam+alef -- a mandatory ligature in virtually every Arabic font --
        // encoded as two unjoined glyphs instead of the one ligature glyph).
        // 'lateef' is mPDF's own bundled font (vendor/mpdf/mpdf/ttfonts,
        // OFL-licensed, already part of the committed vendor tree -- no new
        // download) and is mPDF's own default useOTL=0xFF configuration for
        // Arabic-family scripts; it loads without the GSUB error and was
        // confirmed to actually ligate (lam+alef -> U+FEFB, the correct
        // Unicode presentation-form codepoint) where Amiri did not.
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 30,
            'margin_bottom' => 20,
            'margin_header' => 8,
            'margin_footer' => 8,
            'fontDir' => array_merge($fontDirs, [dirname(__DIR__, 2) . '/vendor-assets/fonts']),
            'fontdata' => $fontData,
            'default_font' => 'lateef',
        ]);

        // §O-32: explicit per export, never left to "mPDF supports RTL" alone.
        $mpdf->SetDirectionality($locale === 'ar' ? 'rtl' : 'ltr');

        $mpdf->SetHTMLHeader($this->headerHtml($report, $locale));
        $mpdf->SetHTMLFooter($this->footerHtml($locale));
        $mpdf->WriteHTML($this->tableHtml($report, $locale));

        return $mpdf->Output('', 'S');
    }

    /**
     * PhpSpreadsheet, RTL sheet direction set explicitly for Arabic (O-32) --
     * dates/numbers in $report['rows'] already arrive as plain formatted
     * strings from the repository layer (never a raw DateTime), so there is
     * no library auto-formatting to fight with column by column.
     */
    public function toExcel(array $report, string $locale): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft($locale === 'ar');
        $sheet->setTitle(substr(preg_replace('/[^A-Za-z0-9 ]/', '', $report['title']) ?: 'Report', 0, 31));

        $school = $this->schools->current();
        $schoolName = $school === null ? '' : ($locale === 'ar' ? $school['name_ar'] : $school['name']);

        $row = 1;
        $sheet->setCellValue("A{$row}", $schoolName);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row++;
        $sheet->setCellValue("A{$row}", $report['title']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue("A{$row}", ($locale === 'ar' ? 'العام الدراسي: ' : 'Academic Year: ') . $report['year_label']);
        $row++;
        foreach ($report['filters_summary'] as $label => $value) {
            $sheet->setCellValue("A{$row}", "{$label}: {$value}");
            $row++;
        }
        $sheet->setCellValue("A{$row}", ($locale === 'ar' ? 'تاريخ الإنشاء: ' : 'Generated on: ') . $report['generated_at']);
        $row += 2;

        $headerRow = $row;
        foreach ($report['headers'] as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, $headerRow, $header);
        }
        $sheet->getStyle("A{$headerRow}:" . $sheet->getHighestColumn() . "{$headerRow}")->getFont()->setBold(true);
        $row++;

        foreach ($report['rows'] as $dataRow) {
            foreach ($dataRow as $col => $value) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $value);
            }
            $row++;
        }

        // Applied last, over the sheet's real final dimension -- computed
        // any earlier (e.g. right after the header row) would only cover
        // what existed at that point, not the data rows written afterward.
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()
            ->setHorizontal($locale === 'ar' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT);

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $tmpFile = tempnam(sys_get_temp_dir(), 'nizam_xlsx_');
        $writer->save($tmpFile);
        $bytes = (string) file_get_contents($tmpFile);
        unlink($tmpFile);

        return $bytes;
    }

    private function headerHtml(array $report, string $locale): string
    {
        $school = $this->schools->full();
        $schoolName = $school === null ? '' : e($locale === 'ar' ? $school['name_ar'] : $school['name']);

        $filters = '';
        foreach ($report['filters_summary'] as $label => $value) {
            $filters .= '<span style="margin-inline-end:12px;">' . e($label) . ': ' . e($value) . '</span>';
        }

        $yearLabel = $locale === 'ar' ? 'العام الدراسي' : 'Academic Year';

        $identityBlock = '<div style="font-size:13pt; font-weight:bold;">' . $schoolName . '</div>'
            . '<div style="font-weight:bold;">' . e($report['title']) . '</div>'
            . '<div>' . e($yearLabel) . ': ' . e($report['year_label']) . ($filters !== '' ? ' &nbsp; ' . $filters : '') . '</div>';

        // §K "every printed/exported report carries school name and logo" --
        // a two-cell table, not flexbox: mPDF's CSS support doesn't cover
        // flex, and a table is the reliable way to place a fixed-width image
        // cell beside text in header/footer HTML. Logo cell simply doesn't
        // exist when no logo is configured yet (decision #6/§P#6) -- no
        // placeholder image, matching §N's "never fake data" rule extended
        // to imagery.
        $logoDataUri = $this->logoDataUri($school['logo_path'] ?? null);
        if ($logoDataUri === null) {
            return '<div style="border-bottom:1px solid #999; padding-bottom:4px; font-size:9pt;">' . $identityBlock . '</div>';
        }

        // Same HTML column order regardless of locale -- the document's own
        // SetDirectionality('rtl') (set once, above) is what mirrors a
        // table's visual column order for Arabic; re-ordering these cells
        // here too would double-mirror them (O-32: direction is set
        // explicitly once per export, not layered ad hoc per element).
        $logoCell = '<img src="' . e($logoDataUri) . '" style="height:42px;">';
        return '<table width="100%" style="border-bottom:1px solid #999; padding-bottom:4px; font-size:9pt;"><tr>'
            . '<td style="width:60px; vertical-align:middle;">' . $logoCell . '</td>'
            . '<td style="vertical-align:middle;">' . $identityBlock . '</td>'
            . '</tr></table>';
    }

    /** Embedded as a data: URI rather than a filesystem path -- mPDF resolves relative paths against its own working context, and a data: URI sidesteps that entirely. Returns null (never a broken-image icon) if no logo is configured or the stored file has since gone missing. Retrieval itself (local disk vs Supabase Storage) is UploadService's own driver branch, not duplicated here. */
    private function logoDataUri(?string $logoPath): ?string
    {
        $contents = UploadService::retrieve($logoPath);
        if ($contents === null) {
            return null;
        }
        $extension = strtolower(pathinfo((string) $logoPath, PATHINFO_EXTENSION));
        $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function footerHtml(string $locale): string
    {
        $pageLabel = $locale === 'ar' ? 'صفحة {PAGENO} من {nbpg}' : 'Page {PAGENO} of {nbpg}';
        return '<div style="border-top:1px solid #999; padding-top:4px; font-size:8pt; text-align:center;">' . $pageLabel . '</div>';
    }

    /** Every cell escaped (§S-13: report content is exactly as untrusted as any other user input before mPDF parses it). */
    private function tableHtml(array $report, string $locale): string
    {
        $generatedLabel = 'Generated: ' . e($report['generated_at']);
        $html = '<p style="font-size:8pt; color:#666;">' . $generatedLabel . '</p>';
        $html .= '<table width="100%" style="border-collapse:collapse;" cellpadding="4">';
        $html .= '<thead><tr>';
        foreach ($report['headers'] as $header) {
            $html .= '<th style="border-bottom:2px solid #333; text-align:inherit; font-size:9pt;">' . e($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($report['rows'] as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $cellStr = (string) $cell;
                // §O-32: same fix as the HTML view -- a negative/percentage
                // value inside mPDF's own RTL text direction reorders the
                // sign the same way a browser does, unless isolated.
                $content = $locale === 'ar' && isRtlSafeNumericCell($cellStr)
                    ? '<span dir="ltr">' . e($cellStr) . '</span>' : e($cellStr);
                $html .= '<td style="border-bottom:1px solid #ddd; font-size:9pt;">' . $content . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\ReportService;
use RuntimeException;

/**
 * Any of NIZAM's analytic reports (§K) as a designed document.
 *
 * The table is exactly the one ReportService builds for the screen -- same
 * rows, same totals, same applied filters -- so the PDF and the page a staff
 * member is looking at can never diverge.
 */
final class ReportDocument extends DocumentType
{
    public function key(): string
    {
        return 'report';
    }

    public function permission(): string
    {
        return 'reports.view';
    }

    public function formats(): array
    {
        return ['pdf', 'xlsx', 'png'];
    }

    public function signatures(): array
    {
        return ['principal'];
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $reportKey = (string) ($params['report'] ?? '');
        $service = new ReportService();
        if (!$service->isKnownReport($reportKey)) {
            throw new RuntimeException('document_not_found');
        }

        $filters = [];
        foreach (['year', 'grade', 'class', 'subject', 'sort'] as $name) {
            $value = trim((string) ($params[$name] ?? ''));
            if ($value !== '') {
                $filters[$name] = $value;
            }
        }

        $q = trim((string) ($params['q'] ?? ''));
        $report = $service->build($reportKey, $filters, $identity->locale);
        if ($q !== '') {
            $report['rows'] = array_values(array_filter(
                $report['rows'],
                static function (array $row) use ($q): bool {
                    $haystack = '';
                    foreach ($row as $cell) {
                        $haystack .= ' ' . (string) $cell;
                    }
                    return str_contains(searchText($haystack), searchText($q));
                }
            ));
        }

        $columns = [];
        foreach ($report['headers'] as $header) {
            $columns[] = [
                'label' => (string) $header,
                'width' => max(14.0, min(52.0, mb_strlen((string) $header) * 2.4)),
            ];
        }
        $columns = self::fitWidths($columns, count($columns) >= 6);

        $scope = [];
        if ($identity->yearLabel !== '') {
            $scope[__('document.academic_year')] = $identity->yearLabel;
        }
        foreach ($report['filters_summary'] as $label => $value) {
            $scope[(string) $label] = (string) $value;
        }

        $document = new Document(
            key: $this->key(),
            title: (string) $report['title'],
            subtitle: '',
            orientation: count($columns) >= 6 ? 'landscape' : 'portrait',
            scope: $scope,
            filename: 'report-' . $reportKey,
            meta: ['sheet_name' => (string) $report['title'], 'repeat_title' => true],
        );

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 3,
            'cards' => [
                ['label' => __('document.rows_count'), 'value' => (string) count($report['rows']), 'numeric' => true],
                ['label' => __('document.columns_count'), 'value' => (string) count($columns), 'numeric' => true],
                ['label' => __('document.academic_year'), 'value' => $identity->yearLabel, 'numeric' => false],
            ],
        ]);

        $document->addBlock([
            'type' => 'table',
            'title' => '',
            'columns' => $columns,
            'rows' => $report['rows'],
            'compact' => count($columns) >= 6,
        ]);

        $document->signatures = $this->signatures();

        return $document;
    }
}

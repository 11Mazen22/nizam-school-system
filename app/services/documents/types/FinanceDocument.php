<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\FinanceRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\SchoolSettingsService;

/**
 * Institutional financial statement.
 *
 * Figures come straight from FinanceRepository::getSummary() and the invoice
 * ledger, formatted through the school's configured currency. The document
 * adds no arithmetic of its own and never hides invoices: every row in scope
 * is printed, paginated if necessary.
 */
final class FinanceDocument extends DocumentType
{
    public function key(): string
    {
        return 'finance';
    }

    public function permission(): string
    {
        return 'finance.view';
    }


    public function signatures(): array
    {
        return ['financial_affairs', 'principal'];
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $yearId = $this->activeYearId();
        $repository = new FinanceRepository();
        $summary = $repository->getSummary($yearId);
        $invoices = $repository->allInvoices($yearId);

        $rows = [];
        $number = 1;
        foreach ($invoices as $invoice) {
            $total = (float) ($invoice['total_amount'] ?? 0);
            $discount = (float) ($invoice['discount_amount'] ?? 0);
            $paid = (float) ($invoice['paid_amount'] ?? 0);
            $balance = $total - $discount - $paid;

            $rows[] = [
                ['value' => (string) $number++, 'class' => 'cell-num'],
                ['value' => (string) ($invoice['invoice_number'] ?? ('#' . $invoice['id'])), 'class' => 'cell-num'],
                ['value' => (string) ($invoice['student_name'] ?? ''), 'class' => 'cell-strong'],
                ['value' => (string) ($invoice['student_code'] ?? ''), 'class' => 'cell-num'],
                ['value' => (string) ($invoice['due_date'] ?? $invoice['issue_date'] ?? '—'), 'class' => 'cell-num'],
                ['value' => SchoolSettingsService::money($total, $identity->locale), 'class' => 'cell-num'],
                ['value' => SchoolSettingsService::money($discount, $identity->locale), 'class' => 'cell-num'],
                ['value' => SchoolSettingsService::money($paid, $identity->locale), 'class' => 'cell-num'],
                ['value' => SchoolSettingsService::money($balance, $identity->locale), 'class' => 'cell-num'],
                ['value' => self::status((string) ($invoice['status'] ?? '')), 'class' => ''],
            ];
        }

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: '',
            orientation: 'landscape',
            scope: array_filter([__('document.academic_year') => $identity->yearLabel], static fn (string $value): bool => trim($value) !== ''),
            filename: 'finance-statement',
            meta: ['sheet_name' => __('document.title.finance')],
        );

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 4,
            'cards' => [
                ['label' => __('document.total_billed'), 'value' => SchoolSettingsService::money((float) ($summary['total_billed'] ?? 0), $identity->locale), 'numeric' => true],
                ['label' => __('document.total_collected'), 'value' => SchoolSettingsService::money((float) ($summary['total_collected'] ?? 0), $identity->locale), 'numeric' => true],
                ['label' => __('document.total_outstanding'), 'value' => SchoolSettingsService::money((float) ($summary['total_outstanding'] ?? 0), $identity->locale), 'numeric' => true],
                ['label' => __('finance.invoice_count'), 'value' => (string) (int) ($summary['invoice_count'] ?? 0), 'numeric' => true],
            ],
        ]);

        $document->addBlock([
            'type' => 'table',
            'title' => __('finance.invoices'),
            'columns' => self::fitWidths([
                ['label' => '#', 'width' => 7, 'align' => 'center'],
                ['label' => __('finance.invoice_number'), 'width' => 20],
                ['label' => __('students.full_name'), 'width' => 38],
                ['label' => __('students.code'), 'width' => 22],
                ['label' => __('finance.due_date'), 'width' => 18, 'align' => 'center'],
                ['label' => __('finance.total'), 'width' => 20, 'align' => 'end'],
                ['label' => __('finance.discount'), 'width' => 18, 'align' => 'end'],
                ['label' => __('finance.paid'), 'width' => 20, 'align' => 'end'],
                ['label' => __('finance.balance'), 'width' => 20, 'align' => 'end'],
                ['label' => __('common.status'), 'width' => 16],
            ], true),
            'rows' => $rows,
            'totals' => [
                ['value' => __('document.total') . ': ' . count($invoices), 'class' => 'cell-total', 'colspan' => 3],
                ['value' => '', 'class' => ''],
                ['value' => '', 'class' => ''],
                ['value' => SchoolSettingsService::money((float) ($summary['total_billed'] ?? 0), $identity->locale), 'class' => 'cell-num'],
                ['value' => '', 'class' => ''],
                ['value' => SchoolSettingsService::money((float) ($summary['total_collected'] ?? 0), $identity->locale), 'class' => 'cell-num'],
                ['value' => SchoolSettingsService::money((float) ($summary['total_outstanding'] ?? 0), $identity->locale), 'class' => 'cell-num'],
                ['value' => '', 'class' => ''],
            ],
            'compact' => true,
        ]);

        $document->addBlock([
            'type' => 'notes',
            'text' => __('document.finance_note'),
        ]);

        $document->signatures = $this->signatures();

        return $document;
    }

    private static function status(string $status): string
    {
        $key = 'finance.status_' . $status;
        $translated = __($key);
        return $translated === $key ? ($status !== '' ? $status : '—') : $translated;
    }
}

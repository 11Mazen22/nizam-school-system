<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\FinanceRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\SchoolSettingsService;
use RuntimeException;

/**
 * One student invoice, as a document a parent can be handed.
 *
 * Figures are the stored invoice and payment rows -- no recomputation beyond
 * the same balance rule the invoice screen uses (total - discount - paid).
 */
final class InvoiceDocument extends DocumentType
{
    public function key(): string
    {
        return 'invoice';
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
        $invoiceId = (int) ($params['id'] ?? 0);
        $repository = new FinanceRepository();
        $invoice = $invoiceId > 0 ? $repository->findInvoice($invoiceId) : null;
        if ($invoice === null) {
            throw new RuntimeException('document_not_found');
        }

        $locale = $identity->locale;
        $total = (float) $invoice['total_amount'];
        $discount = (float) $invoice['discount_amount'];
        $paid = (float) $invoice['paid_amount'];
        $balance = max(0.0, ($total - $discount) - $paid);

        $payments = $repository->getPayments($invoiceId);

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: (string) $invoice['invoice_number'],
            orientation: 'portrait',
            scope: array_filter([
                __('document.academic_year') => (string) ($invoice['academic_year_label'] ?? $identity->yearLabel),
                __('finance.due_date') => (string) $invoice['due_date'],
            ], static fn (string $value): bool => trim($value) !== ''),
            filename: 'invoice-' . (string) $invoice['invoice_number'],
            meta: ['sheet_name' => (string) $invoice['invoice_number']],
        );

        $document->addBlock([
            'type' => 'fields',
            'sections' => [
                [
                    'title' => __('finance.invoice_details'),
                    'fields' => [
                        ['label' => __('finance.invoice_number'), 'value' => (string) $invoice['invoice_number'], 'ltr' => true],
                        ['label' => __('students.full_name'), 'value' => (string) ($invoice['student_name'] ?? '')],
                        ['label' => __('students.code'), 'value' => (string) ($invoice['student_code'] ?? ''), 'ltr' => true],
                        ['label' => __('finance.due_date'), 'value' => (string) $invoice['due_date'], 'ltr' => true],
                        ['label' => __('finance.invoice_title'), 'value' => (string) ($invoice['title'] ?? ''), 'wide' => true],
                        ['label' => __('common.status'), 'value' => self::status((string) $invoice['status'])],
                    ],
                ],
            ],
        ]);

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 4,
            'cards' => [
                ['label' => __('finance.total'), 'value' => SchoolSettingsService::money($total, $locale), 'numeric' => true],
                ['label' => __('finance.discount'), 'value' => SchoolSettingsService::money($discount, $locale), 'numeric' => true],
                ['label' => __('finance.paid'), 'value' => SchoolSettingsService::money($paid, $locale), 'numeric' => true],
                ['label' => __('finance.balance'), 'value' => SchoolSettingsService::money($balance, $locale), 'numeric' => true],
            ],
        ]);

        if ($payments !== []) {
            $rows = [];
            foreach ($payments as $payment) {
                $rows[] = [
                    ['value' => (string) ($payment['receipt_number'] ?? ''), 'class' => 'cell-num'],
                    ['value' => (string) ($payment['payment_date'] ?? ''), 'class' => 'cell-num'],
                    ['value' => self::method((string) ($payment['payment_method'] ?? '')), 'class' => ''],
                    ['value' => SchoolSettingsService::money((float) ($payment['amount'] ?? 0), $locale), 'class' => 'cell-num'],
                    ['value' => (string) ($payment['reference_number'] ?? '—'), 'class' => 'cell-num'],
                ];
            }

            $document->addBlock([
                'type' => 'table',
                'title' => __('finance.payments'),
                'columns' => self::fitWidths([
                    ['label' => __('finance.receipt'), 'width' => 26],
                    ['label' => __('finance.payment_date'), 'width' => 24, 'align' => 'center'],
                    ['label' => __('finance.method'), 'width' => 28],
                    ['label' => __('finance.amount'), 'width' => 28, 'align' => 'end'],
                    ['label' => __('finance.reference'), 'width' => 28],
                ]),
                'rows' => $rows,
                'totals' => [
                    ['value' => __('document.total'), 'class' => ''],
                    ['value' => '', 'class' => ''],
                    ['value' => '', 'class' => ''],
                    ['value' => SchoolSettingsService::money($paid, $locale), 'class' => 'cell-num'],
                    ['value' => '', 'class' => ''],
                ],
            ]);
        }

        if (trim((string) ($invoice['notes'] ?? '')) !== '') {
            $document->addBlock(['type' => 'notes', 'title' => __('document.section_notes'), 'text' => (string) $invoice['notes']]);
        }

        $document->signatures = $this->signatures();

        return $document;
    }

    private static function status(string $status): string
    {
        $key = 'finance.status_' . $status;
        $translated = __($key);
        return $translated === $key ? $status : $translated;
    }

    private static function method(string $method): string
    {
        $key = 'finance.method_' . $method;
        $translated = __($key);
        return $translated === $key ? $method : $translated;
    }
}

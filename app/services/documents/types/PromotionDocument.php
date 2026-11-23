<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\AcademicYearRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\PromotionService;
use RuntimeException;

/**
 * The year-end promotion list as a document.
 *
 * What prints is the proposal the school is about to confirm: every active
 * enrolment, the class it is leaving, the grade it moves to, and the default
 * action. It records no decision that has not been made yet and never invents
 * a target class.
 */
final class PromotionDocument extends DocumentType
{
    public function key(): string
    {
        return 'promotion';
    }

    public function permission(): string
    {
        return 'students.promote';
    }


    public function signatures(): array
    {
        return ['student_affairs', 'principal'];
    }

    /**
     * A promotion list is only meaningful for a real source→target pair. With
     * a single academic year there is no pair, so no bulk action is offered --
     * the school must first open the next year before a promotion ZIP exists.
     */
    public function bulkScopes(array $params): array
    {
        $years = (new AcademicYearRepository())->all();
        $items = [];
        foreach ($years as $source) {
            foreach ($years as $target) {
                if ((int) $source['id'] === (int) $target['id']) {
                    continue;
                }
                $items[] = [
                    'label' => (string) $source['label'] . ' → ' . (string) $target['label'],
                    'params' => [
                        'source' => (string) $source['id'],
                        'target' => (string) $target['id'],
                    ],
                ];
            }
        }

        return self::scopes([
            'year_pairs' => [
                'label' => __('document.bulk_year_pairs'),
                'items' => $items,
            ],
        ]);
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $locale = $identity->locale;
        $sourceId = (int) ($params['source'] ?? 0);
        $targetId = (int) ($params['target'] ?? 0);

        $years = new AcademicYearRepository();
        $source = $sourceId > 0 ? $years->find($sourceId) : null;
        $target = $targetId > 0 ? $years->find($targetId) : null;
        if ($source === null || $target === null || $sourceId === $targetId) {
            throw new RuntimeException('document_not_found');
        }

        $rows = (new PromotionService())->preview($sourceId, $targetId);

        $actionLabels = [
            'promote' => __('promotion.action_promote'),
            'repeat' => __('promotion.action_repeat'),
            'graduate' => __('promotion.action_graduate'),
            'transferred' => __('promotion.action_transferred'),
            'withdrawn' => __('promotion.action_withdrawn'),
        ];

        $graduates = 0;
        $tableRows = [];
        $number = 1;
        foreach ($rows as $row) {
            $graduates += !empty($row['forced_graduate']) ? 1 : 0;
            $current = self::localized($row['current_grade_name_ar'] ?? null, $row['current_grade_name_en'] ?? null, $locale);
            if (trim((string) ($row['current_class_name'] ?? '')) !== '') {
                $current .= ' — ' . (string) $row['current_class_name'];
            }
            $next = self::localized($row['next_grade_name_ar'] ?? null, $row['next_grade_name_en'] ?? null, $locale);
            $tableRows[] = [
                ['value' => (string) $number++, 'class' => 'cell-num'],
                ['value' => self::present($row['student_code'] ?? null), 'class' => 'cell-num'],
                ['value' => self::present($row['student_name'] ?? null), 'class' => 'cell-strong'],
                ['value' => $current, 'class' => ''],
                ['value' => $next, 'class' => ''],
                ['value' => $actionLabels[(string) ($row['default_action'] ?? '')] ?? self::present($row['default_action'] ?? null), 'class' => ''],
            ];
        }

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: (string) $source['label'] . ' → ' . (string) $target['label'],
            orientation: 'portrait',
            scope: array_filter([
                __('promotion.source_year') => (string) $source['label'],
                __('promotion.target_year') => (string) $target['label'],
            ], static fn (string $value): bool => trim($value) !== ''),
            filename: 'promotion-' . preg_replace('/[^0-9A-Za-z]+/', '-', (string) $source['label']) . '-to-' . preg_replace('/[^0-9A-Za-z]+/', '-', (string) $target['label']),
            meta: ['sheet_name' => __('document.title.promotion'), 'repeat_title' => true],
        );

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 3,
            'cards' => [
                ['label' => __('promotion.students'), 'value' => (string) count($rows), 'numeric' => true],
                ['label' => __('promotion.action_graduate'), 'value' => (string) $graduates, 'numeric' => true],
                ['label' => __('promotion.action_promote'), 'value' => (string) (count($rows) - $graduates), 'numeric' => true],
            ],
        ]);

        $document->addBlock([
            'type' => 'table',
            'title' => __('promotion.preview_title'),
            'columns' => self::fitWidths([
                ['label' => '#', 'width' => 10, 'align' => 'center'],
                ['label' => __('students.code'), 'width' => 30],
                ['label' => __('students.full_name'), 'width' => 56],
                ['label' => __('students.grade') . ' / ' . __('students.class'), 'width' => 38],
                ['label' => __('promotion.target_class'), 'width' => 30],
                ['label' => __('promotion.action'), 'width' => 26],
            ]),
            'rows' => $tableRows,
            'totals' => [
                ['value' => __('document.total') . ': ' . count($rows), 'class' => 'cell-total', 'colspan' => 3],
                ['value' => '', 'class' => ''],
                ['value' => '', 'class' => ''],
                ['value' => '', 'class' => ''],
            ],
        ]);

        $document->addBlock([
            'type' => 'notes',
            'text' => __('promotion.confirm_warning'),
        ]);

        $document->signatures = $this->signatures();

        return $document;
    }
}

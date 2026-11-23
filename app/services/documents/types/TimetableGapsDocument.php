<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Database;
use App\Repositories\ClassRepository;
use App\Repositories\TimetableGapRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\TimetableGapService;
use App\Services\TimetableService;

/**
 * The printed cells the digital timetable could not load, as a document.
 *
 * This is a working list for the academic office, so it prints exactly what
 * the screen filters: the printed subject and teacher text, why the cell did
 * not load, and its status. After the source-completion pass a gap only exists
 * when the paper itself cannot be represented (an unrecognised subject, or a
 * printed name with no staff record).
 */
final class TimetableGapsDocument extends DocumentType
{
    public function key(): string
    {
        return 'timetable-gaps';
    }

    public function permission(): string
    {
        return 'timetable.view';
    }


    public function signatures(): array
    {
        return ['deputy', 'principal'];
    }

    /**
     * One gap list per class that has at least one recorded gap (open or
     * resolved). Classes with no printed gaps are deliberately not offered as
     * an empty extra file in the ZIP.
     */
    public function bulkScopes(array $params): array
    {
        $yearId = \App\Middleware\AcademicYearContext::activeYearId();
        if ($yearId === null) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT c.id, c.name, g.name_ar AS grade_ar, g.name_en AS grade_en
             FROM timetable_gaps tg
             JOIN classes c ON c.id = tg.class_id
             JOIN grades g ON g.id = c.grade_id
             WHERE tg.academic_year_id = :year
             ORDER BY g.sort_order ASC, c.name ASC"
        );
        $stmt->execute(['year' => $yearId]);

        return self::scopes([
            'classes' => [
                'label' => __('document.bulk_classes_with_gaps'),
                'items' => array_map(static fn (array $row): array => [
                    'label' => trim((currentLocale() === 'ar' ? $row['grade_ar'] : $row['grade_en']) . ' — ' . $row['name']),
                    'params' => ['class_id' => (string) $row['id'], 'status' => 'all'],
                ], $stmt->fetchAll()),
            ],
        ]);
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $yearId = $this->activeYearId();
        $locale = $identity->locale;

        // The same filter definition the screen uses, so the exported list can
        // never disagree with the page it was exported from.
        $filter = TimetableGapService::normalizeFilters($params);
        $status = $filter['status'];
        $reason = $filter['reason'];
        $classId = $filter['class_id'];
        $teacher = $filter['teacher'];

        $repository = new TimetableGapRepository();
        $gaps = $repository->forYear($yearId, $filter['filters']);
        $counts = $repository->counts($yearId);
        $dayLabels = (new TimetableService())->getDayLabels();

        $scope = [__('document.academic_year') => $identity->yearLabel];
        if ($status !== 'all') {
            $scope[__('verification.status')] = TimetableGapService::statusLabel($status);
        }
        if ($reason !== '') {
            $scope[__('timetable.gaps_reason')] = TimetableGapService::reasonLabel($reason);
        }
        if ($classId > 0) {
            $class = (new ClassRepository())->find($classId);
            $scope[__('classes.class')] = (string) ($class['name'] ?? $classId);
        }
        if ($teacher !== '') {
            $scope[__('timetable.gaps_teacher')] = $teacher;
        }

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: __('document.scope_school'),
            orientation: 'landscape',
            scope: array_filter($scope, static fn (string $value): bool => trim($value) !== ''),
            filename: 'timetable-gaps',
            meta: ['sheet_name' => __('document.title.timetable-gaps'), 'repeat_title' => true],
        );

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 4,
            'cards' => [
                ['label' => __('timetable.gaps_open'), 'value' => (string) $counts['open'], 'numeric' => true],
                ['label' => __('timetable.gaps_resolved'), 'value' => (string) $counts['resolved'], 'numeric' => true],
                ['label' => __('timetable.gaps_ignored'), 'value' => (string) $counts['ignored'], 'numeric' => true],
                ['label' => __('timetable.gaps_filter_all'), 'value' => (string) $counts['total'], 'numeric' => true],
            ],
        ]);

        $rows = [];
        foreach ($gaps as $gap) {
            $day = (int) $gap['day_of_week'];
            $rows[] = [
                ['value' => (string) ($gap['class_name'] ?? '—'), 'class' => 'cell-strong'],
                ['value' => ($dayLabels[$day] ?? (string) $day) . ' — ' . __('timetable.period') . ' ' . (int) $gap['period_number'], 'class' => ''],
                ['value' => self::present($gap['subject_raw'] ?? null), 'class' => ''],
                ['value' => self::present($gap['printed_teacher'] ?? null), 'class' => ''],
                ['value' => TimetableGapService::reasonLabel((string) $gap['reason']), 'class' => ''],
                ['value' => TimetableGapService::statusLabel((string) $gap['status']), 'class' => ''],
            ];
        }

        $document->addBlock([
            'type' => 'table',
            'title' => '',
            'columns' => self::fitWidths([
                ['label' => __('timetable.gaps_class'), 'width' => 40],
                ['label' => __('timetable.gaps_slot'), 'width' => 52],
                ['label' => __('timetable.gaps_subject'), 'width' => 60],
                ['label' => __('timetable.gaps_teacher'), 'width' => 46],
                ['label' => __('timetable.gaps_reason'), 'width' => 40],
                ['label' => __('verification.status'), 'width' => 33],
            ], true),
            'rows' => $rows,
            'totals' => [
                ['value' => __('document.total') . ': ' . count($gaps), 'class' => 'cell-total', 'colspan' => 3],
                ['value' => '', 'class' => ''],
                ['value' => '', 'class' => ''],
                ['value' => '', 'class' => ''],
            ],
        ]);

        if ($gaps === []) {
            $document->addBlock(['type' => 'notes', 'text' => __('timetable.gaps_none')]);
        }

        $document->signatures = $this->signatures();

        return $document;
    }
}

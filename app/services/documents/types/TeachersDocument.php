<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\TeacherAssignmentRepository;
use App\Repositories\TeacherRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;

/**
 * Teaching staff directory.
 *
 * Weekly periods come from the same workload query the workload report uses,
 * so a teacher's total in this directory can never disagree with the report
 * or with their own timetable.
 */
final class TeachersDocument extends DocumentType
{
    public function key(): string
    {
        return 'teachers';
    }

    public function permission(): string
    {
        return 'teachers.view';
    }


    public function signatures(): array
    {
        return ['staff_affairs', 'principal'];
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $yearId = $this->activeYearId();
        $locale = $identity->locale;
        $term = trim((string) ($params['q'] ?? ''));
        $religion = (string) ($params['religion'] ?? '');

        $repository = new TeacherRepository();
        $teachers = $term !== ''
            ? $repository->search($term, 'active', $religion)
            : $repository->all('active', $religion);

        $workload = [];
        foreach ((new TeacherAssignmentRepository())->workloadReportForYear($yearId, null) as $row) {
            $workload[(string) $row['full_name']] = $row;
        }

        $rows = [];
        $totalPeriods = 0;
        $number = 1;
        foreach ($teachers as $teacher) {
            $load = $workload[(string) $teacher['full_name']] ?? null;
            $periods = (int) ($load['total_periods'] ?? 0);
            $totalPeriods += $periods;

            $rows[] = [
                ['value' => (string) $number++, 'class' => 'cell-num'],
                ['value' => (string) $teacher['teacher_code'], 'class' => 'cell-num'],
                ['value' => (string) $teacher['full_name'], 'class' => 'cell-strong'],
                ['value' => self::present($teacher['phone'] ?? null), 'class' => 'cell-num'],
                ['value' => self::present($teacher['email'] ?? null), 'class' => ''],
                ['value' => self::religion($teacher['religion'] ?? null), 'class' => ''],
                ['value' => ($load['subjects_ar'] ?? '') !== '' || ($load['subjects_en'] ?? '') !== ''
                    ? self::localized($load['subjects_ar'] ?? null, $load['subjects_en'] ?? null, $locale)
                    : '—', 'class' => ''],
                ['value' => (string) $periods, 'class' => 'cell-num'],
            ];
        }

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: __('document.scope_staff'),
            orientation: 'portrait',
            scope: array_filter([
                __('document.academic_year') => $identity->yearLabel,
                __('common.search') => $term,
                __('teachers.religion') => $religion !== '' ? self::religion($religion) : '',
            ], static fn ($value): bool => trim((string) $value) !== ''),
            filename: 'teachers',
            meta: ['repeat_title' => true],
        );

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 3,
            'cards' => [
                ['label' => __('document.total_teachers'), 'value' => (string) count($teachers), 'numeric' => true],
                ['label' => __('document.total_periods'), 'value' => (string) $totalPeriods, 'numeric' => true],
                ['label' => __('document.academic_year'), 'value' => $identity->yearLabel, 'numeric' => false],
            ],
        ]);

        $document->addBlock([
            'type' => 'table',
            'title' => __('teachers.title'),
            'columns' => self::fitWidths([
                ['label' => '#', 'width' => 8, 'align' => 'center'],
                ['label' => __('teachers.code'), 'width' => 22],
                ['label' => __('teachers.full_name'), 'width' => 40],
                ['label' => __('teachers.phone'), 'width' => 24],
                ['label' => __('teachers.email'), 'width' => 34],
                ['label' => __('teachers.religion'), 'width' => 16],
                ['label' => __('document.subjects'), 'width' => 38],
                ['label' => __('document.weekly_periods'), 'width' => 18, 'align' => 'center'],
            ]),
            'rows' => $rows,
            'totals' => [
                ['value' => __('document.total') . ': ' . count($teachers), 'class' => 'cell-total', 'colspan' => 3],
                ['value' => '', 'class' => ''],
                ['value' => '', 'class' => ''],
                ['value' => '', 'class' => ''],
                ['value' => '', 'class' => ''],
                ['value' => (string) $totalPeriods, 'class' => 'cell-num'],
            ],
            'compact' => true,
        ]);

        $document->signatures = $this->signatures();

        return $document;
    }

    private static function religion(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        return match ($value) {
            'muslim' => __('students.religion_muslim'),
            'christian' => __('students.religion_christian'),
            'other' => __('students.religion_other'),
            default => '—',
        };
    }
}

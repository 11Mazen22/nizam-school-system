<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\ClassRepository;
use App\Repositories\TeacherAssignmentRepository;
use App\Repositories\TeacherRepository;
use App\Repositories\TimetableRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\TimetableService;
use RuntimeException;

/**
 * Teacher record: identity, contact, real assignments and the weekly
 * timetable, on one landscape sheet.
 */
final class TeacherDocument extends DocumentType
{
    public function key(): string
    {
        return 'teacher';
    }

    public function permission(): string
    {
        return 'teachers.view';
    }

    public function formats(): array
    {
        return ['pdf', 'png', 'xlsx'];
    }

    public function signatures(): array
    {
        return ['staff_affairs', 'principal'];
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $teacherId = (int) ($params['id'] ?? 0);
        $teacher = $teacherId > 0 ? (new TeacherRepository())->find($teacherId) : null;
        if ($teacher === null) {
            throw new RuntimeException('document_not_found');
        }

        $yearId = $this->activeYearId();
        $locale = $identity->locale;

        $assignments = (new TeacherAssignmentRepository())->detailForTeacher($teacherId, $yearId);
        $slots = (new TimetableRepository())->rowsForTeacher($teacherId, $yearId);
        $dayLabels = TimetableBlocks::dayLabels();
        $maxPeriods = (new TimetableService())->getMaxPeriods();

        $classes = [];
        foreach ($assignments as $assignment) {
            if (!empty($assignment['class_name'])) {
                $classes[(string) $assignment['class_name']] = true;
            }
        }

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: (string) $teacher['full_name'],
            orientation: 'landscape',
            scope: array_filter([
                __('document.academic_year') => $identity->yearLabel,
                __('teachers.code') => (string) $teacher['teacher_code'],
            ], static fn ($value): bool => trim((string) $value) !== ''),
            filename: 'teacher-' . (string) $teacher['teacher_code'],
            meta: ['sheet_name' => (string) $teacher['full_name']],
        );

        $document->addBlock([
            'type' => 'fields',
            'sections' => [
                [
                    'title' => __('document.section_identity'),
                    'fields' => [
                        ['label' => __('teachers.full_name'), 'value' => (string) $teacher['full_name']],
                        ['label' => __('teachers.code'), 'value' => (string) $teacher['teacher_code'], 'ltr' => true],
                        ['label' => __('teachers.phone'), 'value' => self::present($teacher['phone'] ?? null), 'ltr' => true],
                        ['label' => __('teachers.email'), 'value' => self::present($teacher['email'] ?? null), 'ltr' => true],
                        ['label' => __('teachers.status'), 'value' => (string) ($teacher['status'] === 'active' ? __('common.status_active') : __('common.status_archived'))],
                        ['label' => __('document.classes_count'), 'value' => (string) count($classes), 'ltr' => true],
                    ],
                ],
            ],
        ]);

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 4,
            'cards' => TimetableBlocks::summary($slots, $dayLabels, $locale),
        ]);

        if ($assignments !== []) {
            $rows = [];
            foreach ($assignments as $assignment) {
                $rows[] = [
                    ['value' => self::localized($assignment['subject_name_ar'] ?? null, $assignment['subject_name_en'] ?? null, $locale), 'class' => 'cell-strong'],
                    ['value' => self::present($assignment['class_name'] ?? null), 'class' => ''],
                    ['value' => self::localized($assignment['grade_name_ar'] ?? null, $assignment['grade_name_en'] ?? null, $locale), 'class' => ''],
                    ['value' => (string) (int) ($assignment['weekly_periods'] ?? 0), 'class' => 'cell-num'],
                ];
            }

            $document->addBlock([
                'type' => 'table',
                'title' => __('document.section_assignments'),
                'columns' => self::fitWidths([
                    ['label' => __('assignments.subject'), 'width' => 60],
                    ['label' => __('classes.class'), 'width' => 40],
                    ['label' => __('classes.grade'), 'width' => 60],
                    ['label' => __('document.weekly_periods'), 'width' => 30, 'align' => 'center'],
                ], true),
                'rows' => $rows,
                'compact' => true,
            ]);
        }

        $document->addBlock(TimetableBlocks::grid(
            __('document.section_timetable'),
            $slots,
            $dayLabels,
            $maxPeriods,
            $locale,
            true
        ));

        $document->signatures = $this->signatures();

        return $document;
    }
}

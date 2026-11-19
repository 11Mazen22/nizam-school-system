<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\TeacherAssignmentRepository;
use App\Repositories\TimetableRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\TimetableService;
use RuntimeException;

/**
 * Individual teacher timetable, with the class each period belongs to.
 */
final class TeacherTimetableDocument extends DocumentType
{
    public function key(): string
    {
        return 'timetable-teacher';
    }

    public function permission(): string
    {
        return 'timetable.view';
    }

    public function formats(): array
    {
        return ['pdf', 'png', 'xlsx', 'zip'];
    }

    public function signatures(): array
    {
        return ['deputy', 'principal'];
    }

    public function bulkScopes(array $params): array
    {
        $yearId = \App\Middleware\AcademicYearContext::activeYearId();
        if ($yearId === null) {
            return [];
        }
        $teachers = (new TeacherAssignmentRepository())->workloadReportForYear($yearId, null);

        return self::scopes([
            'teachers' => [
                'label' => __('document.bulk_all_teachers'),
                'items' => array_map(static fn (array $row): array => [
                    'label' => (string) $row['full_name'],
                    'params' => ['teacher_id' => (string) $row['teacher_id']],
                ], $teachers),
            ],
        ]);
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $teacherId = (int) ($params['teacher_id'] ?? 0);
        $teacher = $teacherId > 0 ? (new \App\Repositories\TeacherRepository())->find($teacherId) : null;
        if ($teacher === null) {
            throw new RuntimeException('document_not_found');
        }

        $yearId = $this->activeYearId();
        $locale = $identity->locale;

        $repository = new TimetableRepository();
        $slots = $repository->rowsForTeacher($teacherId, $yearId);
        $dayLabels = TimetableBlocks::dayLabels();
        $maxPeriods = (new TimetableService())->getMaxPeriods();

        // A printed clash has no teacher_id, so a teacher's own timetable used
        // to hide the very lesson the paper gave them in another room. The
        // shared conflict source finds those cells; a cell that collides with
        // a real lesson this teacher already has at that time stays in the
        // explicit clash table below instead of overwriting it.
        $conflictSlots = \App\Services\TimetableConflicts::forTeacher($teacherId, $yearId);
        $conflictIndex = \App\Services\TimetableConflicts::forYear($yearId);
        $occupied = [];
        foreach ($slots as $slot) {
            $occupied[(int) $slot['day_of_week'] . '|' . (int) $slot['period_number']] = true;
        }
        foreach ($conflictSlots as $conflictSlot) {
            $key = (int) $conflictSlot['day_of_week'] . '|' . (int) $conflictSlot['period_number'];
            if (!isset($occupied[$key])) {
                $slots[] = $conflictSlot;
                $occupied[$key] = true;
            }
        }
        usort($slots, static fn (array $a, array $b): int => [$a['day_of_week'], $a['period_number']] <=> [$b['day_of_week'], $b['period_number']]);

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: (string) $teacher['full_name'],
            orientation: 'landscape',
            scope: array_filter([
                __('document.academic_year') => $identity->yearLabel,
                __('teachers.code') => (string) $teacher['teacher_code'],
            ], static fn ($value): bool => trim((string) $value) !== ''),
            filename: 'teacher-timetable-' . (string) $teacherId,
            meta: ['sheet_name' => (string) $teacher['full_name']],
        );

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 4,
            'cards' => TimetableBlocks::summary($slots, $dayLabels, $locale),
        ]);

        $document->addBlock(TimetableBlocks::grid(
            __('document.section_timetable'),
            $slots,
            $dayLabels,
            $maxPeriods,
            $locale,
            true,
            $conflictIndex
        ));

        if ($conflictSlots !== []) {
            $rows = [];
            foreach ($conflictSlots as $conflictSlot) {
                $day = (int) $conflictSlot['day_of_week'];
                $meta = $conflictIndex[(int) $conflictSlot['class_id'] . '|' . $day . '|' . (int) $conflictSlot['period_number']] ?? null;
                $rows[] = [
                    ['value' => (string) ($dayLabels[$day] ?? $day), 'class' => ''],
                    ['value' => __('timetable.period') . ' ' . (int) $conflictSlot['period_number'], 'class' => 'cell-num'],
                    ['value' => self::present($conflictSlot['class_name'] ?? null), 'class' => 'cell-strong'],
                    ['value' => self::localized($conflictSlot['subject_ar'] ?? null, $conflictSlot['subject_en'] ?? null, $locale), 'class' => ''],
                    ['value' => self::present($conflictSlot['printed_teacher'] ?? null), 'class' => ''],
                    ['value' => implode('، ', (array) ($meta['other_classes'] ?? [])), 'class' => ''],
                    ['value' => (string) ($meta['source'] ?? ''), 'class' => 'cell-muted'],
                    ['value' => __('document.clash_status_open'), 'class' => ''],
                ];
            }

            $document->addBlock([
                'type' => 'table',
                'title' => __('document.clash_teacher_title'),
                'columns' => self::fitWidths([
                    ['label' => __('document.clash_day'), 'width' => 26],
                    ['label' => __('timetable.period'), 'width' => 20, 'align' => 'center'],
                    ['label' => __('timetable.gaps_class'), 'width' => 30],
                    ['label' => __('timetable.gaps_subject'), 'width' => 42],
                    ['label' => __('document.clash_printed_teacher'), 'width' => 32],
                    ['label' => __('document.clash_other_class'), 'width' => 30],
                    ['label' => __('document.clash_source'), 'width' => 32],
                    ['label' => __('document.clash_status'), 'width' => 30],
                ], true),
                'rows' => $rows,
            ]);

            $document->addBlock(['type' => 'notes', 'text' => __('document.clash_teacher_note')]);
        }

        if ($slots === []) {
            $document->addBlock(['type' => 'notes', 'text' => __('document.empty_timetable')]);
        }

        $document->signatures = $this->signatures();

        return $document;
    }
}

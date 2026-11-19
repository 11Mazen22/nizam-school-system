<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\ClassRepository;
use App\Repositories\GradeRepository;
use App\Repositories\TimetableRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\TimetableService;

/**
 * Master school timetable: every class's real weekly grid, one after another,
 * composed across as many pages as it genuinely needs. Never one giant image
 * scaled down until nothing is readable.
 */
final class MasterTimetableDocument extends DocumentType
{
    public function key(): string
    {
        return 'timetable-master';
    }

    public function permission(): string
    {
        return 'timetable.view';
    }

    public function formats(): array
    {
        return ['pdf', 'png', 'xlsx'];
    }

    public function signatures(): array
    {
        return ['deputy', 'principal'];
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $yearId = $this->activeYearId();
        $locale = $identity->locale;
        $gradeId = (int) ($params['grade_id'] ?? 0);

        $classes = (new ClassRepository())->allForYear($yearId);
        if ($gradeId > 0) {
            $classes = array_values(array_filter(
                $classes,
                static fn (array $class): bool => (int) $class['grade_id'] === $gradeId
            ));
        }

        $grade = $gradeId > 0 ? (new GradeRepository())->find($gradeId) : null;
        $gradeName = $grade === null ? '' : (string) ($locale === 'ar' ? $grade['name_ar'] : $grade['name_en']);

        $repository = new TimetableRepository();
        $dayLabels = TimetableBlocks::dayLabels();
        $maxPeriods = (new TimetableService())->getMaxPeriods();
        // One conflict index for the whole workbook: the master timetable must
        // communicate exactly the clash the class timetable reports.
        $conflicts = \App\Services\TimetableConflicts::forYear($yearId);

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: $gradeName !== '' ? $gradeName : __('document.scope_school'),
            orientation: 'landscape',
            scope: array_filter([
                __('document.academic_year') => $identity->yearLabel,
                __('classes.grade') => $gradeName,
            ], static fn (string $value): bool => trim($value) !== ''),
            filename: 'master-timetable',
            meta: ['repeat_title' => true],
        );

        $totalSlots = 0;
        $filled = 0;
        foreach ($classes as $class) {
            $slots = $repository->rowsForClass((int) $class['id'], $yearId);
            $totalSlots += count($slots);
            if ($slots !== []) {
                $filled++;
            }

            $gradeLabel = $locale === 'ar' ? $class['grade_name_ar'] : $class['grade_name_en'];
            $document->addBlock(TimetableBlocks::grid(
                trim($gradeLabel . ' — ' . $class['name'], ' —'),
                $slots,
                $dayLabels,
                $maxPeriods,
                $locale,
                false,
                $conflicts,
                (string) $class['name']
            ));
        }

        $document->blocks = array_merge([[
            'type' => 'summary',
            'per_row' => 4,
            'cards' => [
                ['label' => __('document.classes_count'), 'value' => (string) count($classes), 'numeric' => true],
                ['label' => __('document.classes_with_timetable'), 'value' => (string) $filled, 'numeric' => true],
                ['label' => __('document.total_periods'), 'value' => (string) $totalSlots, 'numeric' => true],
                ['label' => __('document.academic_year'), 'value' => $identity->yearLabel, 'numeric' => false],
            ],
        ]], $document->blocks);

        $document->signatures = $this->signatures();

        return $document;
    }
}

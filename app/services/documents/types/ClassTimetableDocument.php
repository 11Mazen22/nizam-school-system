<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\ClassRepository;
use App\Repositories\GradeRepository;
use App\Repositories\TimetableRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\TimetableService;
use RuntimeException;

/**
 * Individual class timetable -- the sheet a student pins above their desk and
 * a parent photographs.
 */
final class ClassTimetableDocument extends DocumentType
{
    public function key(): string
    {
        return 'timetable-class';
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
        $classes = (new ClassRepository())->allForYear($yearId);

        return self::scopes([
            'classes' => [
                'label' => __('document.bulk_all_classes'),
                'items' => array_map(static function (array $class): array {
                    $grade = currentLocale() === 'ar' ? $class['grade_name_ar'] : $class['grade_name_en'];
                    return [
                        'label' => trim($grade . ' — ' . $class['name']),
                        'params' => ['class_id' => (string) $class['id']],
                    ];
                }, $classes),
            ],
        ]);
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $classId = (int) ($params['class_id'] ?? 0);
        $class = $classId > 0 ? (new ClassRepository())->find($classId) : null;
        if ($class === null) {
            throw new RuntimeException('document_not_found');
        }

        $yearId = $this->activeYearId();
        $locale = $identity->locale;
        $grade = (new GradeRepository())->find((int) $class['grade_id']);
        $gradeName = $grade === null ? '' : (string) ($locale === 'ar' ? $grade['name_ar'] : $grade['name_en']);

        $slots = (new TimetableRepository())->rowsForClass($classId, $yearId);
        $dayLabels = TimetableBlocks::dayLabels();
        $maxPeriods = (new TimetableService())->getMaxPeriods();

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: trim($gradeName . ' — ' . $class['name'], ' —'),
            orientation: 'landscape',
            // The grade and class are already the document subtitle; repeating
            // them in the scope line reads as duplicated metadata.
            scope: array_filter([
                __('document.academic_year') => $identity->yearLabel,
            ], static fn ($value): bool => trim((string) $value) !== ''),
            filename: 'class-timetable-' . (string) $classId,
            meta: ['sheet_name' => trim($gradeName . ' ' . $class['name'])],
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
            false,
            \App\Services\TimetableConflicts::forYear($yearId),
            (string) $class['name']
        ));

        if ($slots === []) {
            $document->addBlock(['type' => 'notes', 'text' => __('document.empty_timetable')]);
        }

        $document->signatures = $this->signatures();

        return $document;
    }
}

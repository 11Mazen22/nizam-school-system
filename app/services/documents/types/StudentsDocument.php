<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\ClassRepository;
use App\Repositories\GradeRepository;
use App\Repositories\StudentRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;

/**
 * Official student register.
 *
 * Every active student in scope, in one document -- not the current screen's
 * page of 25. Contact details are opt-in (a general roster does not print
 * phone numbers unless the school explicitly asked the document to), and a
 * value the school has not recorded prints as a real gap rather than an
 * invented one.
 */
final class StudentsDocument extends DocumentType
{
    public function key(): string
    {
        return 'students';
    }

    public function permission(): string
    {
        return 'students.view';
    }

    public function formats(): array
    {
        return ['pdf', 'xlsx', 'png', 'zip'];
    }

    public function signatures(): array
    {
        return ['student_affairs', 'principal'];
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
                'label' => __('document.bulk_by_class'),
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
        $yearId = $this->activeYearId();
        $locale = $identity->locale;
        $gradeId = (int) ($params['grade_id'] ?? 0);
        $classId = (int) ($params['class_id'] ?? 0);
        $religion = (string) ($params['religion'] ?? '');
        $missing = (string) ($params['missing'] ?? '');
        $term = trim((string) ($params['q'] ?? ''));
        $withContacts = (string) ($params['contacts'] ?? '') === '1';

        $rows = (new StudentRepository())->rosterForYear($yearId, $gradeId, $classId, $missing, $religion, $term);

        $grade = $gradeId > 0 ? (new GradeRepository())->find($gradeId) : null;
        $class = $classId > 0 ? (new ClassRepository())->find($classId) : null;

        $scope = [];
        if ($identity->yearLabel !== '') {
            $scope[__('document.academic_year')] = $identity->yearLabel;
        }
        if ($grade !== null) {
            $scope[__('classes.grade')] = (string) ($locale === 'ar' ? $grade['name_ar'] : $grade['name_en']);
        }
        if ($class !== null) {
            $scope[__('classes.class')] = (string) $class['name'];
        }
        if ($term !== '') {
            $scope[__('common.search')] = $term;
        }
        if ($missing !== '') {
            $scope[__('students.data_quality')] = match ($missing) {
                'personal' => __('students.missing_personal'),
                'guardian' => __('students.missing_guardian'),
                'contact' => __('students.missing_contact'),
                default => '',
            };
        }
        if ($religion !== '') {
            $scope[__('students.religion')] = __('students.religion_' . $religion);
        }

        $columns = [
            ['label' => '#', 'width' => 11, 'align' => 'center'],
            // 26mm keeps STU-2026-000028 on one line; a wrapped code makes
            // every roster row two lines tall for no informational gain.
            ['label' => __('students.code'), 'width' => 29],
            ['label' => __('students.full_name'), 'width' => $withContacts ? 40 : 48],
            ['label' => __('classes.grade'), 'width' => 27],
            ['label' => __('classes.class'), 'width' => 17],
            ['label' => __('students.gender'), 'width' => 13],
            ['label' => __('students.date_of_birth'), 'width' => 19],
            ['label' => __('students.religion'), 'width' => 16],
        ];
        if ($withContacts) {
            $columns[] = ['label' => __('students.guardian_phone'), 'width' => 25];
        }

        $tableRows = [];
        $classNames = [];
        $gradeNames = [];
        $number = 1;
        foreach ($rows as $row) {
            $classNames[(string) ($row['class_name'] ?? '')] = true;
            $gradeNames[(string) ($row['grade_name_ar'] ?? '')] = true;

            $line = [
                ['value' => (string) $number++, 'class' => 'cell-num'],
                ['value' => self::present($row['student_code'] ?? null), 'class' => ''],
                ['value' => self::present($row['full_name'] ?? null), 'class' => 'cell-strong'],
                ['value' => self::localized($row['grade_name_ar'] ?? null, $row['grade_name_en'] ?? null, $locale), 'class' => ''],
                ['value' => self::present($row['class_name'] ?? null), 'class' => ''],
                ['value' => self::gender($row['gender'] ?? null), 'class' => ''],
                ['value' => self::present($row['date_of_birth'] ?? null), 'class' => 'cell-num'],
                ['value' => self::religion($row['religion'] ?? null), 'class' => ''],
            ];
            if ($withContacts) {
                $line[] = ['value' => self::present($row['guardian_phone'] ?? null), 'class' => 'cell-num'];
            }
            $tableRows[] = $line;
        }

        $totals = [__('document.total'), (string) count($rows)];
        for ($index = 2; $index < count($columns); $index++) {
            $totals[] = '';
        }

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: __('document.scope_roster'),
            orientation: 'portrait',
            scope: array_filter($scope, static fn ($value): bool => trim((string) $value) !== ''),
            filename: 'students',
            meta: ['repeat_title' => true],
        );

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 4,
            'cards' => [
                ['label' => __('document.total_students'), 'value' => (string) count($rows), 'numeric' => true],
                ['label' => __('document.classes_count'), 'value' => (string) count(array_filter(array_keys($classNames))), 'numeric' => true],
                ['label' => __('document.grades_count'), 'value' => (string) count(array_filter(array_keys($gradeNames))), 'numeric' => true],
                ['label' => __('document.academic_year'), 'value' => $identity->yearLabel, 'numeric' => false],
            ],
        ]);

        $document->addBlock([
            'type' => 'table',
            'title' => __('students.title'),
            'columns' => self::fitWidths($columns),
            'rows' => $tableRows,
            'totals' => $totals,
            'compact' => count($columns) > 8,
        ]);

        $document->addBlock([
            'type' => 'notes',
            'text' => __('document.roster_note'),
        ]);

        $document->signatures = $this->signatures();

        return $document;
    }

    private static function gender(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        return match ($value) {
            'male', 'm', 'ذكر' => __('document.gender_male'),
            'female', 'f', 'أنثى', 'انثى' => __('document.gender_female'),
            default => '—',
        };
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

<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Database;
use App\Repositories\ClassRepository;
use App\Repositories\GradeRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\ExamService;
use RuntimeException;

/**
 * Class ranking as a document.
 *
 * The rows are exactly the ones the ranking screen shows -- same weighted
 * average, same band, same real rank positions -- read from the same service,
 * so a printed ranking can never disagree with the screen.
 */
final class RankingDocument extends DocumentType
{
    public function key(): string
    {
        return 'ranking';
    }

    public function permission(): string
    {
        return 'exams.view';
    }


    public function signatures(): array
    {
        return ['student_affairs', 'principal'];
    }

    /**
     * One ranking per class that actually has an enrolled roster. A class
     * with no students would produce a ranking document with nothing to rank;
     * leaving it out keeps the ZIP honest.
     */
    public function bulkScopes(array $params): array
    {
        $yearId = \App\Middleware\AcademicYearContext::activeYearId();
        if ($yearId === null) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            "SELECT c.id, c.name, g.name_ar AS grade_ar, g.name_en AS grade_en
             FROM classes c
             JOIN grades g ON g.id = c.grade_id
             WHERE c.academic_year_id = :year AND c.is_active = 1
               AND EXISTS (
                 SELECT 1 FROM student_enrollments e
                 WHERE e.class_id = c.id AND e.status = 'active'
               )
             ORDER BY g.sort_order ASC, c.name ASC"
        );
        $stmt->execute(['year' => $yearId]);

        return self::scopes([
            'classes' => [
                'label' => __('document.bulk_by_class'),
                'items' => array_map(static fn (array $row): array => [
                    'label' => trim((currentLocale() === 'ar' ? $row['grade_ar'] : $row['grade_en']) . ' — ' . $row['name']),
                    'params' => ['class_id' => (string) $row['id']],
                ], $stmt->fetchAll()),
            ],
        ]);
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $yearId = $this->activeYearId();
        $locale = $identity->locale;
        $classId = (int) ($params['class_id'] ?? 0);
        $q = trim((string) ($params['q'] ?? ''));

        $class = $classId > 0 ? (new ClassRepository())->find($classId) : null;
        if ($class === null) {
            throw new RuntimeException('document_not_found');
        }
        $grade = (new GradeRepository())->find((int) $class['grade_id']);
        $gradeName = $grade === null ? '' : (string) ($locale === 'ar' ? $grade['name_ar'] : $grade['name_en']);

        $ranking = (new ExamService())->getClassRanking($classId, $yearId);
        if ($q !== '') {
            $needle = searchText($q);
            $ranking = array_values(array_filter($ranking, static function (array $row) use ($needle): bool {
                return str_contains(searchText((string) ($row['full_name'] ?? '')), $needle)
                    || str_contains(searchText((string) ($row['student_code'] ?? '')), $needle);
            }));
        }

        $scoredRows = array_values(array_filter($ranking, static fn (array $row): bool => $row['weighted_avg'] !== null));
        $unscoredRows = array_values(array_filter($ranking, static fn (array $row): bool => $row['weighted_avg'] === null));
        $average = $scoredRows === []
            ? '—'
            : number_format(array_sum(array_map(static fn (array $row): float => (float) $row['weighted_avg'], $scoredRows)) / count($scoredRows), 1) . '%';

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: trim($gradeName . ' — ' . $class['name'], ' —'),
            orientation: 'portrait',
            scope: array_filter([
                __('document.academic_year') => $identity->yearLabel,
                __('classes.class') => (string) $class['name'],
                __('common.search') => $q,
            ], static fn (string $value): bool => trim($value) !== ''),
            filename: 'ranking-' . $classId,
            meta: ['sheet_name' => __('document.title.ranking'), 'repeat_title' => true],
        );

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 3,
            'cards' => [
                ['label' => __('document.total_students'), 'value' => (string) count($ranking), 'numeric' => true],
                ['label' => __('exams.weighted_avg'), 'value' => $average, 'numeric' => true],
                ['label' => __('exams.unscored_count'), 'value' => (string) count($unscoredRows), 'numeric' => true],
            ],
        ]);

        $columns = self::fitWidths([
            ['label' => __('exams.rank'), 'width' => 14, 'align' => 'center'],
            ['label' => __('students.code'), 'width' => 32],
            ['label' => __('students.full_name'), 'width' => 70],
            ['label' => __('exams.weighted_avg'), 'width' => 30, 'align' => 'center'],
            ['label' => __('exams.grade_band'), 'width' => 41],
        ]);

        if ($scoredRows !== []) {
            $rows = [];
            foreach ($scoredRows as $row) {
                $rows[] = [
                    ['value' => (string) (int) $row['rank'], 'class' => 'cell-num'],
                    ['value' => self::present($row['student_code'] ?? null), 'class' => 'cell-num'],
                    ['value' => self::present($row['full_name'] ?? null), 'class' => 'cell-strong'],
                    ['value' => number_format((float) $row['weighted_avg'], 1) . '%', 'class' => 'cell-num'],
                    ['value' => self::present($row['band_label'] ?? null), 'class' => ''],
                ];
            }

            $document->addBlock([
                'type' => 'table',
                'title' => $unscoredRows === [] ? __('exams.ranking') : __('exams.scored_section'),
                'columns' => $columns,
                'rows' => $rows,
                'totals' => [
                    ['value' => __('document.total') . ': ' . count($scoredRows), 'class' => 'cell-total', 'colspan' => 3],
                    ['value' => '', 'class' => ''],
                    ['value' => '', 'class' => ''],
                ],
            ]);
        }

        // A student without a mark is a real member of the class. They keep
        // their place on the roster with an explicit dash in every numeric
        // column -- never rank 0, never a zero average, never omitted.
        if ($unscoredRows !== []) {
            $rows = [];
            foreach ($unscoredRows as $row) {
                $rows[] = [
                    ['value' => '—', 'class' => 'cell-num'],
                    ['value' => self::present($row['student_code'] ?? null), 'class' => 'cell-num'],
                    ['value' => self::present($row['full_name'] ?? null), 'class' => 'cell-strong'],
                    ['value' => '—', 'class' => 'cell-num'],
                    ['value' => __('exams.unscored_reason'), 'class' => 'cell-muted'],
                ];
            }

            $document->addBlock([
                'type' => 'table',
                'title' => $scoredRows === [] ? __('exams.ranking') : __('exams.unscored_section'),
                'columns' => $columns,
                'rows' => $rows,
            ]);
        }

        // A header row above nothing reads as a broken document. Say plainly
        // that this class has no enrolled students.
        if ($ranking === []) {
            $document->addBlock(['type' => 'notes', 'text' => __('document.no_data')]);
        }

        $document->signatures = $this->signatures();

        return $document;
    }
}

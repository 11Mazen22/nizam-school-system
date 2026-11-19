<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Database;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\LessonCoverService;
use App\Services\TimetableService;
use RuntimeException;

/**
 * One day's substitute-cover board as a document.
 *
 * The day's lessons, their regular teacher, who is on approved leave and who
 * has been assigned as cover -- the same board the screen shows, printed for
 * the staff room.
 */
final class TimetableCoversDocument extends DocumentType
{
    public function key(): string
    {
        return 'timetable-covers';
    }

    public function permission(): string
    {
        return 'timetable.view';
    }

    public function formats(): array
    {
        return ['pdf', 'xlsx', 'png'];
    }

    public function signatures(): array
    {
        return ['deputy', 'principal'];
    }

    /**
     * Every date that really has a recorded cover, one cover board per date.
     * A school with no cover assignments has no bulk scope at all, so the ZIP
     * action stays hidden instead of producing a bundle of empty boards.
     */
    public function bulkScopes(array $params): array
    {
        $yearId = \App\Middleware\AcademicYearContext::activeYearId();
        if ($yearId === null) {
            return [];
        }
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT cover_date FROM lesson_covers
             WHERE academic_year_id = :year ORDER BY cover_date ASC'
        );
        $stmt->execute(['year' => $yearId]);

        return self::scopes([
            'dates' => [
                'label' => __('document.bulk_cover_dates'),
                'items' => array_map(static fn (array $row): array => [
                    'label' => date('d/m/Y', strtotime((string) $row['cover_date'])),
                    'params' => ['date' => (string) $row['cover_date']],
                ], $stmt->fetchAll()),
            ],
        ]);
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $yearId = $this->activeYearId();
        $locale = $identity->locale;

        $date = trim((string) ($params['date'] ?? ''));
        if ($date === '') {
            $date = (new \DateTimeImmutable('now', new \DateTimeZone(\App\Services\SchoolSettingsService::timezone())))->format('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new RuntimeException('document_not_found');
        }

        // board() answers a non-school day with an empty board; only a real
        // failure should surface as one, so it is not swallowed here.
        $board = (new LessonCoverService())->board($yearId, $date);

        $dayLabels = (new TimetableService())->getDayLabels();
        $day = $board['day'] ?? null;
        $lessons = $board['lessons'] ?? [];

        $leave = 0;
        $covered = 0;
        foreach ($lessons as $lesson) {
            $leave += (int) ($lesson['on_leave'] ?? 0) === 1 ? 1 : 0;
            $covered += !empty($lesson['cover_teacher_id']) ? 1 : 0;
        }

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: $day !== null ? (string) ($dayLabels[(int) $day] ?? '') . ' — ' . $date : $date,
            orientation: 'landscape',
            // The date is already the subtitle; repeating it in the scope strip
            // prints the same day twice on one page.
            scope: array_filter([
                __('document.academic_year') => $identity->yearLabel,
            ], static fn (string $value): bool => trim($value) !== ''),
            filename: 'timetable-covers-' . $date,
            meta: ['sheet_name' => __('document.title.timetable-covers'), 'repeat_title' => true],
        );

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 4,
            'cards' => [
                ['label' => __('timetable.covers_lessons'), 'value' => (string) count($lessons), 'numeric' => true],
                ['label' => __('timetable.covers_on_leave'), 'value' => (string) $leave, 'numeric' => true],
                ['label' => __('timetable.covers_covered'), 'value' => (string) $covered, 'numeric' => true],
                ['label' => __('timetable.covers_pending'), 'value' => (string) max(0, $leave - $covered), 'numeric' => true],
            ],
        ]);

        $rows = [];
        foreach ($lessons as $lesson) {
            $rows[] = [
                ['value' => (string) (int) $lesson['period_number'], 'class' => 'cell-num'],
                ['value' => self::present($lesson['class_name'] ?? null), 'class' => 'cell-strong'],
                ['value' => self::localized($lesson['subject_ar'] ?? null, $lesson['subject_en'] ?? null, $locale), 'class' => ''],
                ['value' => self::present($lesson['teacher_name'] ?? null), 'class' => ''],
                ['value' => (int) ($lesson['on_leave'] ?? 0) === 1 ? __('timetable.covers_on_leave_short') : '', 'class' => ''],
                ['value' => self::present($lesson['cover_teacher_name'] ?? null), 'class' => ''],
                ['value' => self::present($lesson['cover_note'] ?? null), 'class' => ''],
            ];
        }

        $document->addBlock([
            'type' => 'table',
            'title' => '',
            'columns' => self::fitWidths([
                ['label' => __('timetable.period'), 'width' => 18, 'align' => 'center'],
                ['label' => __('timetable.gaps_class'), 'width' => 40],
                ['label' => __('timetable.gaps_subject'), 'width' => 58],
                ['label' => __('timetable.covers_regular'), 'width' => 48],
                ['label' => __('timetable.covers_on_leave_short'), 'width' => 28],
                ['label' => __('timetable.covers_cover'), 'width' => 48],
                ['label' => __('timetable.covers_note'), 'width' => 31],
            ], true),
            'rows' => $rows,
            'totals' => [
                ['value' => __('document.total'), 'class' => ''],
                ['value' => (string) count($lessons), 'class' => 'cell-num'],
                ['value' => '', 'class' => ''],
                ['value' => '', 'class' => ''],
                ['value' => (string) $leave, 'class' => 'cell-num'],
                ['value' => (string) $covered, 'class' => 'cell-num'],
                ['value' => '', 'class' => ''],
            ],
        ]);

        if ($day === null) {
            $document->addBlock([
                'type' => 'notes',
                'text' => __('timetable.covers_no_school_hint'),
            ]);
        } elseif ($lessons === []) {
            $document->addBlock(['type' => 'notes', 'text' => __('timetable.covers_none')]);
        }

        $document->signatures = $this->signatures();

        return $document;
    }
}

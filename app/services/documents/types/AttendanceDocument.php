<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\AttendanceRepository;
use App\Repositories\ClassRepository;
use App\Repositories\GradeRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\SchoolSettingsService;
use RuntimeException;

/**
 * Attendance register for a class and period, or the school's chronic
 * absenteeism list when no class is selected.
 *
 * Percentages use the same present/absent/late/excused counts the attendance
 * screen shows -- the document performs no independent arithmetic and no
 * invented policy threshold.
 */
final class AttendanceDocument extends DocumentType
{
    public function key(): string
    {
        return 'attendance';
    }

    public function permission(): string
    {
        return 'attendance.view';
    }


    public function signatures(): array
    {
        return ['student_affairs', 'principal'];
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $yearId = $this->activeYearId();
        $locale = $identity->locale;
        $classId = (int) ($params['class_id'] ?? 0);
        $from = self::date($params['from'] ?? null);
        $to = self::date($params['to'] ?? null);

        $repository = new AttendanceRepository();

        if ($classId <= 0) {
            return $this->chronic($repository, $identity, $yearId);
        }

        $class = (new ClassRepository())->find($classId);
        if ($class === null) {
            throw new RuntimeException('document_not_found');
        }
        $grade = (new GradeRepository())->find((int) $class['grade_id']);
        $gradeName = $grade === null ? '' : (string) ($locale === 'ar' ? $grade['name_ar'] : $grade['name_en']);

        $summary = $repository->summaryForClass($classId, $from, $to);
        $daily = $repository->dailyTotalsForClass($classId, $from, $to);

        $present = $absent = $late = $excused = $records = 0;
        foreach ($summary as $row) {
            $present += (int) $row['present_count'];
            $absent += (int) $row['absent_count'];
            $late += (int) $row['late_count'];
            $excused += (int) $row['excused_count'];
            $records += (int) $row['total_records'];
        }
        $rate = $records > 0 ? number_format((($present + $late) / $records) * 100, 1) . '%' : '—';

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: trim($gradeName . ' — ' . $class['name'], ' —'),
            orientation: 'portrait',
            scope: array_filter([
                __('document.academic_year') => $identity->yearLabel,
                __('attendance.from') => $from,
                __('attendance.to') => $to,
            ], static fn (string $value): bool => trim($value) !== ''),
            filename: 'attendance-' . (string) $classId,
            meta: ['sheet_name' => trim($gradeName . ' ' . $class['name'])],
        );

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 4,
            'cards' => [
                ['label' => __('document.total_students'), 'value' => (string) count($summary), 'numeric' => true],
                ['label' => __('attendance.present'), 'value' => (string) $present, 'numeric' => true],
                ['label' => __('attendance.absent'), 'value' => (string) $absent, 'numeric' => true],
                ['label' => __('attendance.late'), 'value' => (string) $late, 'numeric' => true],
                ['label' => __('attendance.excused'), 'value' => (string) $excused, 'numeric' => true],
                ['label' => __('attendance.records'), 'value' => (string) $records, 'numeric' => true],
                ['label' => __('attendance.rate'), 'value' => $rate, 'numeric' => true],
                ['label' => __('attendance.days_recorded'), 'value' => (string) count($daily), 'numeric' => true],
            ],
        ]);

        $rows = [];
        $number = 1;
        foreach ($summary as $row) {
            $studentTotal = (int) $row['total_records'];
            $studentRate = $studentTotal > 0
                ? number_format((((int) $row['present_count'] + (int) $row['late_count']) / $studentTotal) * 100, 1) . '%'
                : '—';
            $rows[] = [
                ['value' => (string) $number++, 'class' => 'cell-num'],
                ['value' => (string) $row['student_code'], 'class' => 'cell-num'],
                ['value' => (string) $row['full_name'], 'class' => 'cell-strong'],
                ['value' => (string) (int) $row['present_count'], 'class' => 'cell-num'],
                ['value' => (string) (int) $row['absent_count'], 'class' => 'cell-num'],
                ['value' => (string) (int) $row['late_count'], 'class' => 'cell-num'],
                ['value' => (string) (int) $row['excused_count'], 'class' => 'cell-num'],
                ['value' => (string) $studentTotal, 'class' => 'cell-num'],
                ['value' => $studentRate, 'class' => 'cell-num'],
            ];
        }

        $document->addBlock([
            'type' => 'table',
            'title' => __('attendance.report_title'),
            'columns' => self::fitWidths([
                ['label' => '#', 'width' => 8, 'align' => 'center'],
                ['label' => __('students.code'), 'width' => 24],
                ['label' => __('students.full_name'), 'width' => 48],
                ['label' => __('attendance.present'), 'width' => 14, 'align' => 'center'],
                ['label' => __('attendance.absent'), 'width' => 14, 'align' => 'center'],
                ['label' => __('attendance.late'), 'width' => 13, 'align' => 'center'],
                ['label' => __('attendance.excused'), 'width' => 14, 'align' => 'center'],
                ['label' => __('attendance.records'), 'width' => 15, 'align' => 'center'],
                ['label' => __('attendance.rate'), 'width' => 15, 'align' => 'center'],
            ]),
            'rows' => $rows,
            'totals' => [
                ['value' => __('document.total') . ': ' . count($summary), 'class' => 'cell-total', 'colspan' => 3],
                ['value' => (string) $present, 'class' => 'cell-num'],
                ['value' => (string) $absent, 'class' => 'cell-num'],
                ['value' => (string) $late, 'class' => 'cell-num'],
                ['value' => (string) $excused, 'class' => 'cell-num'],
                ['value' => (string) $records, 'class' => 'cell-num'],
                ['value' => $rate, 'class' => 'cell-num'],
            ],
            'compact' => true,
        ]);

        if ($daily !== []) {
            $dailyRows = [];
            foreach ($daily as $day) {
                $dailyRows[] = [
                    ['value' => (string) $day['record_date'], 'class' => 'cell-num'],
                    ['value' => (string) (int) $day['present_count'], 'class' => 'cell-num'],
                    ['value' => (string) (int) $day['absent_count'], 'class' => 'cell-num'],
                    ['value' => (string) (int) $day['late_count'], 'class' => 'cell-num'],
                    ['value' => (string) (int) $day['total'], 'class' => 'cell-num'],
                ];
            }

            $document->addBlock([
                'type' => 'table',
                'title' => __('attendance.daily_totals'),
                'columns' => self::fitWidths([
                    ['label' => __('attendance.date'), 'width' => 30, 'align' => 'center'],
                    ['label' => __('attendance.present'), 'width' => 25, 'align' => 'center'],
                    ['label' => __('attendance.absent'), 'width' => 25, 'align' => 'center'],
                    ['label' => __('attendance.late'), 'width' => 25, 'align' => 'center'],
                    ['label' => __('attendance.records'), 'width' => 25, 'align' => 'center'],
                ]),
                'rows' => $dailyRows,
            ]);
        }

        $document->addBlock([
            'type' => 'notes',
            'title' => __('document.legend'),
            'items' => [
                __('attendance.present') . ' — ' . __('attendance.present_hint'),
                __('attendance.absent') . ' — ' . __('attendance.absent_hint'),
                __('attendance.late') . ' — ' . __('attendance.late_hint'),
                __('attendance.excused') . ' — ' . __('attendance.excused_hint'),
            ],
        ]);

        $document->signatures = $this->signatures();

        return $document;
    }

    private function chronic(AttendanceRepository $repository, DocumentIdentity $identity, int $yearId): Document
    {
        $rows = $repository->chronicAbsentees($yearId);

        $document = new Document(
            key: $this->key(),
            title: $this->title('chronic'),
            subtitle: __('document.scope_school'),
            orientation: 'portrait',
            scope: array_filter([__('document.academic_year') => $identity->yearLabel], static fn (string $value): bool => trim($value) !== ''),
            filename: 'attendance-chronic',
            meta: ['sheet_name' => __('document.title.attendance.chronic')],
        );

        $tableRows = [];
        $number = 1;
        foreach ($rows as $row) {
            $tableRows[] = [
                ['value' => (string) $number++, 'class' => 'cell-num'],
                ['value' => (string) $row['student_code'], 'class' => 'cell-num'],
                ['value' => (string) $row['full_name'], 'class' => 'cell-strong'],
                ['value' => self::localized($row['grade_name_ar'] ?? null, $row['grade_name_en'] ?? null, $identity->locale), 'class' => ''],
                ['value' => (string) ($row['class_name'] ?? '—'), 'class' => ''],
                ['value' => (string) (int) $row['absent_count'], 'class' => 'cell-num'],
            ];
        }

        $document->addBlock([
            'type' => 'table',
            'title' => '',
            'columns' => self::fitWidths([
                ['label' => '#', 'width' => 8, 'align' => 'center'],
                ['label' => __('students.code'), 'width' => 26],
                ['label' => __('students.full_name'), 'width' => 52],
                ['label' => __('classes.grade'), 'width' => 30],
                ['label' => __('classes.class'), 'width' => 22],
                ['label' => __('attendance.absent'), 'width' => 18, 'align' => 'center'],
            ]),
            'rows' => $tableRows,
            'totals' => [
                ['value' => __('document.total') . ': ' . count($rows), 'class' => 'cell-total', 'colspan' => 3],
                ['value' => '', 'class' => ''],
                ['value' => '', 'class' => ''],
                ['value' => '', 'class' => ''],
            ],
        ]);

        $document->signatures = ['student_affairs', 'principal'];

        return $document;
    }

    private static function date(?string $value): string
    {
        $value = trim((string) $value);
        if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        // Default range: the first day of the current month, in the school's
        // own timezone. "Today" is never used as a silent substitute for a
        // range the user did not choose.
        return (new \DateTimeImmutable('now', new \DateTimeZone(SchoolSettingsService::timezone())))->format('Y-m-01');
    }
}

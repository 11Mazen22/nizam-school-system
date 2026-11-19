<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Services\SettingsService;
use App\Services\SchoolSettingsService;

/**
 * Shared construction of timetable document content.
 *
 * One implementation of "what a school timetable looks like", used by the
 * class, teacher and master documents, so the three can never drift into
 * three different visual languages.
 *
 * Subject colours are derived from the subject's own name, never from a
 * random value or from row order: الجبر is the same pastel in July's export
 * as it was in September's, and the same in every class it appears in.
 */
final class TimetableBlocks
{
    /** @var array<int,string> */
    private const TONES = [
        1 => '#EAF3ED', 2 => '#EDF3FB', 3 => '#FDF3E6',
        4 => '#F3EFFA', 5 => '#FCEEEE', 6 => '#EBF6F4',
    ];

    /** @return array<int,string> enabled timetable day numbers => localized label */
    public static function dayLabels(): array
    {
        $all = [
            1 => __('timetable.day_sat'),
            2 => __('timetable.day_sun'),
            3 => __('timetable.day_mon'),
            4 => __('timetable.day_tue'),
            5 => __('timetable.day_wed'),
            6 => __('timetable.day_thu'),
        ];
        $allowed = SchoolSettingsService::timetableDays();

        return array_filter($all, static fn (int $day): bool => in_array($day, $allowed, true), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param list<array<string,mixed>> $slots timetable rows (day_of_week, period_number, subject_*, teacher_name, class_name)
     * @param array<int,string> $dayLabels
     */
    public static function grid(
        string $title,
        array $slots,
        array $dayLabels,
        int $maxPeriods,
        string $locale,
        bool $showClass = false,
        ?array $conflicts = null,
        string $classLabel = '',
    ): array {
        $map = [];
        $subjects = [];
        foreach ($slots as $slot) {
            $map[(int) $slot['day_of_week']][(int) $slot['period_number']] = $slot;
            $name = self::subject($slot, $locale);
            $subjects[$name] = self::tone($name);
        }
        $conflicts ??= [];
        $openClashes = 0;
        $resolvedClashes = 0;
        $unassignedCells = 0;

        $breakAfter = (int) SettingsService::get('timetable.break_after_period', 0);
        $rows = [];
        $clashes = [];
        for ($period = 1; $period <= $maxPeriods; $period++) {
            $cells = [];
            $hasLesson = false;
            foreach ($dayLabels as $day => $label) {
                $slot = $map[$day][$period] ?? null;
                if ($slot === null) {
                    $cells[] = ['type' => 'empty'];
                    continue;
                }
                $hasLesson = true;
                $subject = self::subject($slot, $locale);
                $printed = trim((string) ($slot['printed_teacher'] ?? ''));
                $classId = (int) ($slot['class_id'] ?? 0);
                $conflict = $conflicts[$classId . '|' . $day . '|' . $period] ?? null;

                // A printed clash has no real teacher id: the paper places the
                // teacher in two rooms at once, so the database keeps only the
                // printed name as evidence. A resolved clash keeps that same
                // printed text as provenance but now has a real teacher.
                $isClash = $printed !== '' && (int) ($slot['teacher_id'] ?? 0) === 0;
                $isResolvedClash = $printed !== '' && !$isClash;
                $isUnassigned = $printed === '' && (int) ($slot['teacher_id'] ?? 0) === 0;
                $status = $conflict['status'] ?? ($isResolvedClash ? 'resolved' : 'open');

                if ($isClash) {
                    $openClashes++;
                } elseif ($isResolvedClash) {
                    $resolvedClashes++;
                } elseif ($isUnassigned) {
                    $unassignedCells++;
                }

                if ($isClash || $isResolvedClash) {
                    $clashes[] = [
                        'day' => (string) $label,
                        'day_number' => (int) $day,
                        'period' => $period,
                        'class' => $showClass
                            ? (string) ($slot['class_name'] ?? '')
                            : $classLabel,
                        'subject' => $subject,
                        'teacher' => (string) ($slot['teacher_name'] ?? ''),
                        'printed_teacher' => $printed,
                        'other' => implode('، ', (array) ($conflict['other_classes'] ?? [])),
                        'status' => (string) $status,
                        'source' => (string) ($conflict['source'] ?? ''),
                    ];
                }

                $cells[] = [
                    'type' => 'lesson',
                    'subject' => $subject,
                    // A cell the paper double-books has no real teacher id, but
                    // its printed teacher is still what the school expects to
                    // see on the official timetable.
                    'teacher' => (string) (($slot['teacher_name'] ?? '') !== '' ? $slot['teacher_name'] : $printed),
                    'class' => $showClass ? (string) ($slot['class_name'] ?? '') : '',
                    'tone' => self::tone($subject),
                    'clash' => $isClash,
                    'resolved' => $isResolvedClash,
                    'unassigned' => $isUnassigned,
                    'printed_teacher' => $printed !== '' ? $printed : '',
                    'other' => implode('، ', (array) ($conflict['other_classes'] ?? [])),
                    'status' => (string) $status,
                    'source' => (string) ($conflict['source'] ?? ''),
                ];
            }

            // A break is only ever printed when the school has actually
            // configured where it falls -- never inferred or invented.
            if ($hasLesson || $breakAfter === 0) {
                $rows[] = ['label' => __('timetable.period') . ' ' . $period, 'cells' => $cells];
            }

            if ($breakAfter > 0 && $period === $breakAfter) {
                $rows[] = [
                    'label' => __('document.break'),
                    'meta' => '',
                    'cells' => array_fill(0, count($dayLabels), ['type' => 'break', 'text' => __('document.break')]),
                ];
            }
        }

        $legend = [];
        foreach (array_keys($subjects) as $name) {
            $legend[] = [
                'label' => $name,
                'style' => 'background:' . self::tonnes(self::tone($name)),
            ];
        }
        // One legend for every timetable: the same facts, the same colours,
        // whether the sheet is read on screen, printed or filtered in Excel.
        if ($openClashes > 0) {
            $legend[] = [
                'label' => __('document.clash_legend'),
                'style' => 'background:#FEE2E2;border-color:#B91C1C;',
                'class' => 'legend-clash',
            ];
        }
        if ($resolvedClashes > 0) {
            $legend[] = [
                'label' => __('document.clash_legend_resolved'),
                'style' => 'background:#DCFCE7;border-color:#15803D;',
                'class' => 'legend-resolved',
            ];
        }
        if ($unassignedCells > 0) {
            $legend[] = [
                'label' => __('document.legend_unassigned'),
                'style' => 'background:#F1F5F9;border-color:#94A3B8;',
                'class' => 'legend-unassigned',
            ];
        }
        if ($breakAfter > 0) {
            $legend[] = [
                'label' => __('document.break'),
                'style' => 'background:#FFF7ED;border-color:#FDBA74;',
                'class' => 'legend-break',
            ];
        }
        $legend[] = [
            'label' => __('document.legend_empty'),
            'style' => 'background:#FFFFFF;border-color:#CBD5E1;',
            'class' => 'legend-empty',
        ];

        return [
            'type' => 'grid',
            'title' => $title,
            'corner' => __('document.period_short'),
            'head' => array_values($dayLabels),
            'rows' => $rows,
            'legend' => $legend,
            // Machine-readable companion to the visual badge: the same rows
            // are written as explicit columns in the XLSX export.
            'clashes' => $clashes,
        ];
    }

    public static function tone(string $subject): int
    {
        return (int) (crc32($subject) % 6) + 1;
    }

    public static function tonnes(int $tone): string
    {
        return self::TONES[$tone] ?? self::TONES[1];
    }

    /** @param array<string,mixed> $slot */
    public static function subject(array $slot, string $locale): string
    {
        $ar = (string) ($slot['subject_ar'] ?? '');
        $en = (string) ($slot['subject_en'] ?? '');
        $value = $locale === 'ar' ? $ar : $en;
        return trim($value !== '' ? $value : ($locale === 'ar' ? $en : $ar));
    }

    /**
     * Real, counted facts about a timetable -- nothing estimated.
     *
     * @param list<array<string,mixed>> $slots
     * @return list<array{label:string,value:string,numeric:bool}>
     */
    public static function summary(array $slots, array $dayLabels, string $locale): array
    {
        $subjects = [];
        $classes = [];
        $days = [];
        $clashes = 0;
        foreach ($slots as $slot) {
            $subjects[self::subject($slot, $locale)] = true;
            $classes[(string) ($slot['class_name'] ?? '')] = true;
            $days[(int) $slot['day_of_week']] = true;
            if ((int) ($slot['teacher_id'] ?? 0) === 0 && trim((string) ($slot['printed_teacher'] ?? '')) !== '') {
                $clashes++;
            }
        }

        $cards = [
            ['label' => __('document.total_periods'), 'value' => (string) count($slots), 'numeric' => true],
            ['label' => __('document.subjects_count'), 'value' => (string) count($subjects), 'numeric' => true],
            ['label' => __('document.days_count'), 'value' => (string) count(array_intersect(array_keys($days), array_keys($dayLabels))), 'numeric' => true],
        ];

        if ($clashes > 0) {
            $cards[] = ['label' => __('document.clashes_count'), 'value' => (string) $clashes, 'numeric' => true];
        }

        if (count($classes) > 1) {
            $cards[] = ['label' => __('document.classes_count'), 'value' => (string) count($classes), 'numeric' => true];
        }

        return $cards;
    }
}

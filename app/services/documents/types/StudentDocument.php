<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\AttendanceRepository;
use App\Repositories\ClassRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\GradeRepository;
use App\Repositories\GuardianRepository;
use App\Repositories\StudentRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use RuntimeException;

/**
 * Formal student record -- portrait, sectioned, and honest about gaps.
 *
 * Reads exactly the same student, enrollment, guardian and attendance data
 * the profile screen shows; the only difference is that it is composed as a
 * school record instead of a dashboard.
 */
final class StudentDocument extends DocumentType
{
    public function key(): string
    {
        return 'student';
    }

    public function permission(): string
    {
        return 'students.view';
    }


    public function signatures(): array
    {
        return ['student_affairs', 'principal'];
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $studentId = (int) ($params['id'] ?? 0);
        $student = $studentId > 0 ? (new StudentRepository())->find($studentId) : null;
        if ($student === null) {
            throw new RuntimeException('document_not_found');
        }

        $yearId = $this->activeYearId();
        $locale = $identity->locale;

        $enrollment = (new EnrollmentRepository())->forStudentInYear($studentId, $yearId);
        $class = $enrollment !== null && !empty($enrollment['class_id'])
            ? (new ClassRepository())->find((int) $enrollment['class_id']) : null;
        $grade = $enrollment !== null && !empty($enrollment['grade_id'])
            ? (new GradeRepository())->find((int) $enrollment['grade_id']) : null;
        $guardians = (new GuardianRepository())->getForStudent($studentId);
        $attendance = (new AttendanceRepository())->summaryForStudent($studentId, $yearId);

        $gradeName = $grade === null ? '—' : (string) ($locale === 'ar' ? $grade['name_ar'] : $grade['name_en']);

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: (string) $student['full_name'],
            orientation: 'portrait',
            scope: array_filter([
                __('document.academic_year') => $identity->yearLabel,
                __('students.code') => (string) $student['student_code'],
                __('classes.class') => $class === null ? '' : (string) $class['name'],
            ], static fn ($value): bool => trim((string) $value) !== ''),
            filename: 'student-' . (string) $student['student_code'],
            meta: ['sheet_name' => (string) $student['full_name']],
        );

        $document->addBlock([
            'type' => 'fields',
            'sections' => [
                [
                    'title' => __('document.section_identity'),
                    'fields' => [
                        ['label' => __('students.full_name'), 'value' => (string) $student['full_name']],
                        ['label' => __('students.code'), 'value' => (string) $student['student_code'], 'ltr' => true],
                        ['label' => __('students.gender'), 'value' => self::gender($student['gender'] ?? null)],
                        ['label' => __('students.date_of_birth'), 'value' => self::present($student['date_of_birth'] ?? null), 'ltr' => true],
                        ['label' => __('students.religion'), 'value' => self::religion($student['religion'] ?? null)],
                        ['label' => __('students.status'), 'value' => (string) ($student['status'] === 'active' ? __('common.status_active') : __('common.status_archived'))],
                    ],
                ],
                [
                    'title' => __('document.section_academic'),
                    'fields' => [
                        ['label' => __('classes.grade'), 'value' => $gradeName],
                        ['label' => __('classes.class'), 'value' => $class === null ? '—' : (string) $class['name']],
                        ['label' => __('students.enrollment_date'), 'value' => self::present($enrollment['enrollment_date'] ?? null), 'ltr' => true],
                        ['label' => __('students.data_source'), 'value' => ($student['import_source'] ?? '') === 'roster_csv' ? __('students.roster_source') : __('students.manual_source')],
                    ],
                ],
                [
                    'title' => __('document.section_guardian'),
                    'fields' => self::guardianFields($guardians),
                ],
                [
                    'title' => __('document.section_attendance'),
                    'fields' => self::attendanceFields($attendance),
                ],
            ],
        ]);

        $notes = trim((string) ($student['notes'] ?? ''));
        if ($notes !== '') {
            $document->addBlock(['type' => 'notes', 'title' => __('document.section_notes'), 'text' => $notes]);
        }

        if ($guardians === [] || ($attendance === null)) {
            $document->addBlock([
                'type' => 'notes',
                'text' => __('document.missing_data_note'),
            ]);
        }

        $document->signatures = $this->signatures();

        return $document;
    }

    /** @param array<int, array<string,mixed>> $guardians */
    private static function guardianFields(array $guardians): array
    {
        if ($guardians === []) {
            return [[
                'label' => __('guardians.title'),
                'value' => __('students.not_recorded'),
                'missing' => true,
                'wide' => true,
            ]];
        }

        $fields = [];
        foreach (array_slice($guardians, 0, 2) as $guardian) {
            $name = trim((string) ($guardian['full_name'] ?? ''));
            $relation = trim((string) ($guardian['relation'] ?? ''));
            $fields[] = [
                'label' => $relation !== '' ? $relation : __('guardians.title'),
                'value' => $name !== '' ? $name : '—',
            ];
            $fields[] = [
                'label' => __('guardians.phone'),
                'value' => self::present($guardian['phone'] ?? null),
                'ltr' => true,
            ];
        }

        return $fields;
    }

    /** @param array<string,mixed>|null $summary */
    private static function attendanceFields(?array $summary): array
    {
        if ($summary === null) {
            return [[
                'label' => __('attendance.report_title'),
                'value' => __('attendance.no_records'),
                'missing' => true,
                'wide' => true,
            ]];
        }

        $total = (int) ($summary['total_records'] ?? 0);
        $present = (int) ($summary['present_count'] ?? 0);
        $late = (int) ($summary['late_count'] ?? 0);
        $rate = $total > 0 ? number_format((($present + $late) / $total) * 100, 1) . '%' : '—';

        return [
            ['label' => __('attendance.present'), 'value' => (string) $present, 'ltr' => true],
            ['label' => __('attendance.absent'), 'value' => (string) (int) ($summary['absent_count'] ?? 0), 'ltr' => true],
            ['label' => __('attendance.late'), 'value' => (string) $late, 'ltr' => true],
            ['label' => __('attendance.excused'), 'value' => (string) (int) ($summary['excused_count'] ?? 0), 'ltr' => true],
            ['label' => __('attendance.rate'), 'value' => $rate, 'ltr' => true],
            ['label' => __('attendance.records'), 'value' => (string) $total, 'ltr' => true],
        ];
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

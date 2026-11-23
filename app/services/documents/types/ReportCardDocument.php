<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Repositories\ClassRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\GradeRepository;
use App\Repositories\StudentRepository;
use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;
use App\Services\ExamService;
use RuntimeException;

/**
 * Student report card / transcript.
 *
 * Every percentage, band and GPA point comes from ExamService -> the academic
 * calculator, exactly as the scores screen and the existing report-card PDF
 * produce them. This template changes the presentation, never the maths.
 */
final class ReportCardDocument extends DocumentType
{
    public function key(): string
    {
        return 'report-card';
    }

    public function permission(): string
    {
        return 'exams.view';
    }


    public function signatures(): array
    {
        return ['student_affairs', 'principal'];
    }

    public function build(array $params, DocumentIdentity $identity): Document
    {
        $studentId = (int) ($params['student_id'] ?? $params['id'] ?? 0);
        $student = $studentId > 0 ? (new StudentRepository())->find($studentId) : null;
        if ($student === null) {
            throw new RuntimeException('document_not_found');
        }

        $yearId = $this->activeYearId();
        $locale = $identity->locale;
        $report = (new ExamService())->getStudentReport($studentId, $yearId);

        $enrollment = (new EnrollmentRepository())->forStudentInYear($studentId, $yearId);
        $class = $enrollment !== null && !empty($enrollment['class_id'])
            ? (new ClassRepository())->find((int) $enrollment['class_id']) : null;
        $grade = $enrollment !== null && !empty($enrollment['grade_id'])
            ? (new GradeRepository())->find((int) $enrollment['grade_id']) : null;

        $overall = $report['overall'];
        $scale = $report['scale'];

        $document = new Document(
            key: $this->key(),
            title: $this->title(),
            subtitle: (string) $student['full_name'],
            orientation: 'portrait',
            scope: array_filter([
                __('document.academic_year') => $identity->yearLabel,
                __('students.code') => (string) $student['student_code'],
                __('classes.grade') => $grade === null ? '' : (string) ($locale === 'ar' ? $grade['name_ar'] : $grade['name_en']),
                __('classes.class') => $class === null ? '' : (string) $class['name'],
            ], static fn ($value): bool => trim((string) $value) !== ''),
            filename: 'report-card-' . (string) $student['student_code'],
            meta: ['sheet_name' => (string) $student['full_name']],
        );

        $document->addBlock([
            'type' => 'summary',
            'per_row' => 3,
            'cards' => [
                ['label' => __('exams.overall'), 'value' => $overall['percentage'] !== null ? number_format((float) $overall['percentage'], 2) . '%' : '—', 'numeric' => true],
                ['label' => __('exams.grade_band'), 'value' => (string) ($overall['band_label'] ?? '—'), 'numeric' => false],
                ['label' => __('structure.grading.band_gpa'), 'value' => $overall['gpa_point'] !== null ? number_format((float) $overall['gpa_point'], 2) : '—', 'numeric' => true],
            ],
        ]);

        if ($report['subjects'] !== []) {
            $rows = [];
            foreach ($report['subjects'] as $subject) {
                $rows[] = [
                    ['value' => self::localized($subject['subject_ar'] ?? null, $subject['subject_en'] ?? null, $locale), 'class' => 'cell-strong'],
                    ['value' => (string) (int) $subject['exam_count'], 'class' => 'cell-num'],
                    ['value' => $subject['percentage'] !== null ? number_format((float) $subject['percentage'], 2) . '%' : '—', 'class' => 'cell-num'],
                    ['value' => (string) ($subject['band_label'] ?? '—'), 'class' => ''],
                    ['value' => $subject['gpa_point'] !== null ? number_format((float) $subject['gpa_point'], 2) : '—', 'class' => 'cell-num'],
                ];
            }

            $document->addBlock([
                'type' => 'table',
                'title' => __('exams.subject_summary'),
                'columns' => self::fitWidths([
                    ['label' => __('subjects.subject'), 'width' => 58],
                    ['label' => __('exams.exam_count'), 'width' => 18, 'align' => 'center'],
                    ['label' => __('exams.weighted_avg'), 'width' => 28, 'align' => 'center'],
                    ['label' => __('exams.grade_band'), 'width' => 30, 'align' => 'center'],
                    ['label' => __('structure.grading.band_gpa'), 'width' => 22, 'align' => 'center'],
                ]),
                'rows' => $rows,
            ]);
        }

        foreach ($report['grid'] as $examRows) {
            $first = reset($examRows);
            if ($first === false) {
                continue;
            }
            $rows = [];
            foreach ($examRows as $row) {
                $rows[] = [
                    ['value' => self::localized($row['name_ar'] ?? null, $row['name_en'] ?? null, $locale), 'class' => ''],
                    ['value' => $row['score'] !== null ? number_format((float) $row['score'], 1) : '—', 'class' => 'cell-num'],
                    ['value' => number_format((float) ($row['max_score'] ?? 0), 0), 'class' => 'cell-num'],
                    ['value' => $row['percentage'] !== null ? number_format((float) $row['percentage'], 2) . '%' : '—', 'class' => 'cell-num'],
                    ['value' => (string) ($row['band_label'] ?? '—'), 'class' => ''],
                ];
            }

            $document->addBlock([
                'type' => 'table',
                'title' => self::localized($first['subject_ar'] ?? null, $first['subject_en'] ?? null, $locale),
                'columns' => self::fitWidths([
                    ['label' => __('exams.exam'), 'width' => 56],
                    ['label' => __('exams.score'), 'width' => 22, 'align' => 'center'],
                    ['label' => __('exams.max_score'), 'width' => 22, 'align' => 'center'],
                    ['label' => __('exams.weighted_avg'), 'width' => 28, 'align' => 'center'],
                    ['label' => __('exams.grade_band'), 'width' => 30, 'align' => 'center'],
                ]),
                'rows' => $rows,
                'compact' => true,
            ]);
        }

        if ($scale !== null) {
            $document->addBlock([
                'type' => 'notes',
                'title' => __('exams.grade_scale'),
                'text' => (string) ($scale['name_ar'] ?? $scale['name_en'] ?? ''),
            ]);
        }

        if ($report['subjects'] === []) {
            $document->addBlock(['type' => 'notes', 'text' => __('exams.no_scores_yet')]);
        }

        $document->signatures = $this->signatures();

        return $document;
    }
}

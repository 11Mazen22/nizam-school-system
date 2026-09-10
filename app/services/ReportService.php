<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\ClassRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\GradeRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\SubjectStaffingRequirementRepository;
use App\Repositories\TeacherAssignmentRepository;
use RuntimeException;

/**
 * Nizam -- §K's six report builders, one per §Q's "ReportService | build
 * (one method, dispatches by report key)". build() always returns the same
 * shape -- title, the header-block metadata every report shares (§K "Shared
 * report conventions"), and an already-locale-resolved headers[]/rows[][]
 * table -- so the HTML view, the PDF builder, and the Excel builder all
 * consume identically instead of each knowing 6 reports' worth of column
 * meaning. Locale is resolved HERE (not left to the view) specifically so
 * export (which has no view) gets the same correct-language table the
 * screen does, from one call.
 */
final class ReportService
{
    /** @var array<string, array{formats: string[], required: string[], optional: string[]}> */
    private const CATALOG = [
        'religion' => ['formats' => ['print', 'pdf', 'excel'], 'required' => ['year'], 'optional' => ['grade', 'class']],
        'class-list' => ['formats' => ['print', 'pdf'], 'required' => ['year', 'grade', 'class'], 'optional' => []],
        'density' => ['formats' => ['print', 'excel'], 'required' => ['year'], 'optional' => ['grade']],
        'teachers-by-subject' => ['formats' => ['print', 'excel'], 'required' => ['year'], 'optional' => ['subject']],
        'workload' => ['formats' => ['print', 'pdf', 'excel'], 'required' => ['year'], 'optional' => ['subject']],
        'shortage' => ['formats' => ['print', 'excel'], 'required' => ['year'], 'optional' => []],
    ];

    public function __construct(
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
        private readonly GradeRepository $grades = new GradeRepository(),
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly SubjectRepository $subjects = new SubjectRepository(),
        private readonly EnrollmentRepository $enrollments = new EnrollmentRepository(),
        private readonly TeacherAssignmentRepository $assignments = new TeacherAssignmentRepository(),
        private readonly SubjectStaffingRequirementRepository $staffing = new SubjectStaffingRequirementRepository(),
    ) {
    }

    /** @return array<string, array{formats: string[], required: string[], optional: string[]}> */
    public function catalog(): array
    {
        return self::CATALOG;
    }

    public function isKnownReport(string $key): bool
    {
        return isset(self::CATALOG[$key]);
    }

    public function isValidFormat(string $key, string $format): bool
    {
        return in_array($format, self::CATALOG[$key]['formats'] ?? [], true);
    }

    /**
     * @param array<string,string> $filters raw query-string values, still strings
     * @return array{key:string, title:string, generated_at:string, year_label:string, filters_summary:array<string,string>, headers:string[], rows:array<int,string[]>, formats:string[]}
     * @throws RuntimeException 'unknown_report'|'missing_filter'|'invalid_filter'
     */
    public function build(string $key, array $filters, string $locale): array
    {
        if (!$this->isKnownReport($key)) {
            throw new RuntimeException('unknown_report');
        }

        $year = $this->requireYear($filters);

        return match ($key) {
            'religion' => $this->buildReligion($year, $filters, $locale),
            'class-list' => $this->buildClassList($year, $filters, $locale),
            'density' => $this->buildDensity($year, $filters, $locale),
            'teachers-by-subject' => $this->buildTeachersBySubject($year, $filters, $locale),
            'workload' => $this->buildWorkload($year, $filters, $locale),
            'shortage' => $this->buildShortage($year, $locale),
            default => throw new RuntimeException('unknown_report'),
        };
    }

    // ---------------------------------------------------------------- K.1

    private function buildReligion(array $year, array $filters, string $locale): array
    {
        $grade = $this->optionalGrade($filters);
        $class = $this->optionalClass($filters, $grade, (int) $year['id']);

        $rows = $this->enrollments->religionBreakdownByGradeClass(
            (int) $year['id'], $grade !== null ? (int) $grade['id'] : null, $class !== null ? (int) $class['id'] : null
        );

        $t = fn (string $en, string $ar): string => $locale === 'ar' ? $ar : $en;
        $headers = [$t('Grade', 'الصف'), $t('Class', 'الفصل'), $t('Muslim', 'مسلم'), $t('Christian', 'مسيحي'),
            $t('Other', 'أخرى'), $t('Total', 'الإجمالي'), $t('Muslim %', 'نسبة المسلمين'), $t('Christian %', 'نسبة المسيحيين')];

        $tableRows = [];
        foreach ($rows as $r) {
            $total = (int) $r['total'];
            $tableRows[] = [
                $locale === 'ar' ? $r['grade_name_ar'] : $r['grade_name_en'],
                $r['class_name'] ?? '—',
                (string) (int) $r['muslim'],
                (string) (int) $r['christian'],
                (string) (int) $r['other'],
                (string) $total,
                $total > 0 ? number_format(((int) $r['muslim']) / $total * 100, 1) . '%' : '—',
                $total > 0 ? number_format(((int) $r['christian']) / $total * 100, 1) . '%' : '—',
            ];
        }

        return $this->envelope('religion', $locale === 'ar' ? 'إحصائية الديانة' : 'Religion Statistics',
            $year, $locale, $headers, $tableRows, $this->filtersSummary($locale, $grade, $class, null));
    }

    // ---------------------------------------------------------------- K.2

    private function buildClassList(array $year, array $filters, string $locale): array
    {
        $grade = $this->requiredGrade($filters);
        $class = $this->requiredClass($filters, $grade, (int) $year['id']);
        $sortBy = ($filters['sort'] ?? 'name') === 'code' ? 'code' : 'name';

        $rows = $this->enrollments->classRoster((int) $class['id'], $sortBy);

        $t = fn (string $en, string $ar): string => $locale === 'ar' ? $ar : $en;
        $headers = ['#', $t('Student Code', 'رمز الطالب'), $t('Full Name', 'الاسم الكامل'),
            $t('Religion', 'الديانة'), $t('Date of Birth', 'تاريخ الميلاد'), $t('Guardian Phone', 'هاتف ولي الأمر')];

        $tableRows = [];
        $i = 1;
        foreach ($rows as $r) {
            $tableRows[] = [
                (string) $i++, $r['student_code'], $r['full_name'],
                $t(ucfirst($r['religion']), match ($r['religion']) {
                    'muslim' => 'مسلم', 'christian' => 'مسيحي', default => 'أخرى',
                }),
                $r['date_of_birth'], $r['guardian_phone'] ?? '—',
            ];
        }

        return $this->envelope('class-list', $locale === 'ar' ? 'قائمة طلاب الفصل' : 'Class Student List',
            $year, $locale, $headers, $tableRows, $this->filtersSummary($locale, $grade, $class, null));
    }

    // ---------------------------------------------------------------- K.3

    private function buildDensity(array $year, array $filters, string $locale): array
    {
        $grade = $this->optionalGrade($filters);
        $direction = ($filters['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        // Stable since PHP 8.0: ties on enrolled count keep the repository's
        // own grade-order/class-name tiebreak rather than an arbitrary one.
        $rows = $this->classes->densityForYear((int) $year['id'], $grade !== null ? (int) $grade['id'] : null);
        usort($rows, static function (array $a, array $b) use ($direction): int {
            return $direction === 'asc' ? $a['enrolled'] <=> $b['enrolled'] : $b['enrolled'] <=> $a['enrolled'];
        });

        $t = fn (string $en, string $ar): string => $locale === 'ar' ? $ar : $en;
        $headers = [$t('Class', 'الفصل'), $t('Grade', 'الصف'), $t('Enrolled Count', 'عدد المسجلين'),
            $t('Capacity', 'السعة'), $t('Fill %', 'نسبة الإشغال')];

        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                $r['class_name'],
                $locale === 'ar' ? $r['grade_name_ar'] : $r['grade_name_en'],
                (string) $r['enrolled'],
                $r['capacity'] !== null ? (string) $r['capacity'] : '—',
                $r['fill_pct'] !== null ? number_format($r['fill_pct'], 1) . '%' : '—',
            ];
        }

        return $this->envelope('density', $locale === 'ar' ? 'كثافة الفصول' : 'Class Density',
            $year, $locale, $headers, $tableRows, $this->filtersSummary($locale, $grade, null, null));
    }

    // ---------------------------------------------------------------- K.4

    private function buildTeachersBySubject(array $year, array $filters, string $locale): array
    {
        $subject = $this->optionalSubject($filters);

        $rows = $this->assignments->bySubjectForYear((int) $year['id'], $subject !== null ? (int) $subject['id'] : null);

        $t = fn (string $en, string $ar): string => $locale === 'ar' ? $ar : $en;
        $headers = [$t('Subject', 'المادة'), $t('Number of Teachers', 'عدد المعلمين'),
            $t('Number of Assignments', 'عدد التكليفات'), $t('Total Weekly Periods', 'إجمالي الحصص الأسبوعية')];

        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                $locale === 'ar' ? $r['subject_name_ar'] : $r['subject_name_en'],
                (string) $r['teacher_count'], (string) $r['assignment_count'], (string) $r['total_periods'],
            ];
        }

        return $this->envelope('teachers-by-subject', $locale === 'ar' ? 'المعلمون حسب المادة' : 'Teachers by Subject',
            $year, $locale, $headers, $tableRows, $this->filtersSummary($locale, null, null, $subject));
    }

    // ---------------------------------------------------------------- K.5

    private function buildWorkload(array $year, array $filters, string $locale): array
    {
        $subject = $this->optionalSubject($filters);
        $order = ($filters['sort'] ?? 'highest') === 'lowest' ? 'lowest' : 'highest';

        $rows = $this->assignments->workloadReportForYear((int) $year['id'], $subject !== null ? (int) $subject['id'] : null);
        if ($order === 'lowest') {
            // Flips only the primary key (total_periods); a plain array_reverse()
            // would also flip the repository's full_name ASC tiebreaker into
            // DESC, so teachers tied on periods would list differently between
            // the two toggle states for no reason. usort() is stable since
            // PHP 8.0, so ties still fall back to the array's existing name order.
            usort($rows, static fn (array $a, array $b): int => $a['total_periods'] <=> $b['total_periods']);
        }

        $expected = $year['expected_weekly_capacity'] !== null ? (int) $year['expected_weekly_capacity'] : null;

        $t = fn (string $en, string $ar): string => $locale === 'ar' ? $ar : $en;
        $headers = [$t('Teacher', 'المعلم'), $t('Subject(s)', 'المادة/المواد'), $t('Weekly Periods', 'الحصص الأسبوعية'),
            $t('Expected', 'المتوقع'), $t('Difference', 'الفرق')];

        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                $r['full_name'],
                ($locale === 'ar' ? $r['subjects_ar'] : $r['subjects_en']) ?: '—',
                (string) $r['total_periods'],
                $expected !== null ? (string) $expected : '—',
                $expected !== null ? (string) ($r['total_periods'] - $expected) : '—',
            ];
        }

        return $this->envelope('workload', $locale === 'ar' ? 'العبء التدريسي للمعلمين' : 'Teacher Workload',
            $year, $locale, $headers, $tableRows, $this->filtersSummary($locale, null, null, $subject));
    }

    // ---------------------------------------------------------------- K.6

    private function buildShortage(array $year, string $locale): array
    {
        $rows = $this->staffing->shortageReportForYear((int) $year['id']);
        // Repository already orders by shortage DESC via the SQL ORDER BY expression.

        $t = fn (string $en, string $ar): string => $locale === 'ar' ? $ar : $en;
        $headers = [$t('Subject', 'المادة'), $t('Required Teachers', 'المعلمون المطلوبون'),
            $t('Available Teachers', 'المعلمون المتاحون'), $t('Shortage', 'العجز')];

        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                $locale === 'ar' ? $r['name_ar'] : $r['name_en'],
                (string) $r['required_teachers'], (string) $r['available_teachers'], (string) $r['shortage'],
            ];
        }

        return $this->envelope('shortage', $locale === 'ar' ? 'تقرير النقص في التوظيف' : 'Staffing Shortage',
            $year, $locale, $headers, $tableRows, $this->filtersSummary($locale, null, null, null));
    }

    // ---------------------------------------------------------------- shared

    /** @throws RuntimeException 'missing_filter'|'invalid_filter' */
    private function requireYear(array $filters): array
    {
        $id = isset($filters['year']) && $filters['year'] !== '' ? (int) $filters['year'] : null;
        if ($id === null) {
            throw new RuntimeException('missing_filter');
        }
        $year = $this->years->find($id);
        if ($year === null) {
            throw new RuntimeException('invalid_filter');
        }
        return $year;
    }

    /** @throws RuntimeException 'invalid_filter' */
    private function optionalGrade(array $filters): ?array
    {
        if (!isset($filters['grade']) || $filters['grade'] === '') {
            return null;
        }
        $grade = $this->grades->find((int) $filters['grade']);
        if ($grade === null) {
            throw new RuntimeException('invalid_filter');
        }
        return $grade;
    }

    /** @throws RuntimeException 'missing_filter'|'invalid_filter' */
    private function requiredGrade(array $filters): array
    {
        $grade = $this->optionalGrade($filters);
        if ($grade === null) {
            throw new RuntimeException('missing_filter');
        }
        return $grade;
    }

    /** @throws RuntimeException 'invalid_filter' if the class doesn't exist or doesn't belong to $grade/$yearId when $grade is given */
    private function optionalClass(array $filters, ?array $grade, int $yearId): ?array
    {
        if (!isset($filters['class']) || $filters['class'] === '') {
            return null;
        }
        $class = $this->classes->find((int) $filters['class']);
        if ($class === null || (int) $class['academic_year_id'] !== $yearId) {
            throw new RuntimeException('invalid_filter');
        }
        if ($grade !== null && (int) $class['grade_id'] !== (int) $grade['id']) {
            throw new RuntimeException('invalid_filter');
        }
        return $class;
    }

    /** @throws RuntimeException 'missing_filter'|'invalid_filter' */
    private function requiredClass(array $filters, array $grade, int $yearId): array
    {
        $class = $this->optionalClass($filters, $grade, $yearId);
        if ($class === null) {
            throw new RuntimeException('missing_filter');
        }
        return $class;
    }

    /** @throws RuntimeException 'invalid_filter' */
    private function optionalSubject(array $filters): ?array
    {
        if (!isset($filters['subject']) || $filters['subject'] === '') {
            return null;
        }
        $subject = $this->subjects->find((int) $filters['subject']);
        if ($subject === null) {
            throw new RuntimeException('invalid_filter');
        }
        return $subject;
    }

    /** @return array<string,string> localized "label: value" pairs for the shared header block's "applied filters" line. */
    private function filtersSummary(string $locale, ?array $grade, ?array $class, ?array $subject): array
    {
        $t = fn (string $en, string $ar): string => $locale === 'ar' ? $ar : $en;
        $summary = [];
        if ($grade !== null) {
            $summary[$t('Grade', 'الصف')] = $locale === 'ar' ? $grade['name_ar'] : $grade['name_en'];
        }
        if ($class !== null) {
            $summary[$t('Class', 'الفصل')] = $class['name'];
        }
        if ($subject !== null) {
            $summary[$t('Subject', 'المادة')] = $locale === 'ar' ? $subject['name_ar'] : $subject['name_en'];
        }
        return $summary;
    }

    /**
     * @param string[] $headers
     * @param array<int,string[]> $rows
     * @param array<string,string> $filtersSummary
     */
    private function envelope(string $key, string $title, array $year, string $locale, array $headers, array $rows, array $filtersSummary): array
    {
        return [
            'key' => $key,
            'title' => $title,
            // §O-32: date format is set explicitly, the same way regardless of
            // locale -- an ISO-ish Y-m-d order is unambiguous in both
            // directions, which is the actual goal, not a per-locale format.
            'generated_at' => date('Y-m-d H:i'),
            'year_label' => $year['label'],
            'filters_summary' => $filtersSummary,
            'headers' => $headers,
            'rows' => $rows,
            'formats' => self::CATALOG[$key]['formats'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicYearRepository;
use App\Repositories\TeacherAssignmentRepository;

/**
 * Nizam -- §I.5 weekly workload calculation. Every figure here takes an
 * explicit academic year (O-23: never "all years combined"), and reads
 * expected_weekly_capacity from that year's OWN column -- never the live
 * academic.expected_weekly_capacity setting, which is only ever copied onto
 * a year at creation time (§S-11). Changing the global default afterward
 * must never rewrite a past year's already-reported target.
 */
final class WorkloadService
{
    public function __construct(
        private readonly TeacherAssignmentRepository $assignments = new TeacherAssignmentRepository(),
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
    ) {
    }

    /** @return array{total:int, expected:?int, difference:?int, detail: array<int,array>} */
    public function calculateForTeacher(int $teacherId, int $academicYearId): array
    {
        $year = $this->years->find($academicYearId);
        $expected = $year !== null && $year['expected_weekly_capacity'] !== null
            ? (int) $year['expected_weekly_capacity'] : null;
        $total = $this->assignments->totalWeeklyPeriods($teacherId, $academicYearId);

        return [
            'total' => $total,
            'expected' => $expected,
            'difference' => $expected !== null ? $total - $expected : null,
            'detail' => $this->assignments->detailForTeacher($teacherId, $academicYearId),
        ];
    }

    /** @return array{perTeacher: array<int,array{teacher_id:int,full_name:string,total:int}>, highest:int, lowest:int, average:float} */
    public function calculateForYear(int $academicYearId): array
    {
        $perTeacher = $this->assignments->workloadSummaryForYear($academicYearId);
        $totals = array_map(static fn (array $row): int => (int) $row['total'], $perTeacher);

        return [
            'perTeacher' => $perTeacher,
            'highest' => $totals === [] ? 0 : max($totals),
            'lowest' => $totals === [] ? 0 : min($totals),
            'average' => $totals === [] ? 0.0 : round(array_sum($totals) / count($totals), 1),
        ];
    }
}

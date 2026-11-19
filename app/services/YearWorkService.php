<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\YearWorkRepository;
use RuntimeException;

/**
 * Year-work (أعمال السنة) engine.
 *
 * Entirely independent of attendance: eligibility here is the share of the
 * expected weekly/monthly assessments that have a recorded mark. Whether
 * year-work exists at all, its completion threshold and its component weights
 * come from AcademicRulesService (per academic year, grade level and track).
 */
final class YearWorkService
{
    public function __construct(
        private readonly YearWorkRepository $repo = new YearWorkRepository(),
        private readonly AttendancePolicyService $attendance = new AttendancePolicyService(),
    ) {
    }

    /** @return array<string,mixed> */
    public function rulesForStudent(int $studentId, int $yearId, int $gradeLevel, ?string $yearLabel = null): array
    {
        $track = AcademicRulesService::trackForEnrollment($studentId, $yearId, $gradeLevel, $yearLabel);
        return AcademicRulesService::yearWorkRules($track, $gradeLevel, $yearLabel);
    }

    /** @return array<string,mixed> */
    public function statusForStudent(int $studentId, int $yearId, int $gradeLevel, ?string $yearLabel = null): array
    {
        $rules = $this->rulesForStudent($studentId, $yearId, $gradeLevel, $yearLabel);
        $entries = $this->repo->entriesFor($studentId, $yearId);
        $assessmentEntries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => in_array((string) $entry['category'], ['weekly_assessment', 'monthly_assessment'], true)
        ));

        // Expected assessments = one per school week in the academic year so far.
        $weeks = [];
        foreach ($this->attendance->schoolDays($yearId) as $date) {
            $weeks[date('o-W', strtotime($date))] = true;
        }
        $expected = max(1, count($weeks));
        $recorded = count($assessmentEntries);
        $completion = (int) round($recorded / $expected * 100);

        // Weighted mark, computed only from recorded entries; missing entries
        // are never invented and never treated as zero.
        $weighted = 0.0;
        $weightTotal = 0.0;
        foreach ($entries as $entry) {
            $category = (string) $entry['category'];
            $weight = (float) ($rules['weights'][$category] ?? 0);
            $max = (float) ($entry['max_score'] ?? 100);
            if ($weight <= 0 || $max <= 0) {
                continue;
            }
            $weighted += ((float) $entry['score'] / $max) * $weight;
            $weightTotal += $weight;
        }
        $mark = $weightTotal > 0 ? (int) round($weighted / $weightTotal * 100) : null;

        return [
            'enabled' => (bool) $rules['enabled'],
            'track' => (string) $rules['track'],
            'expected' => $expected,
            'recorded' => $recorded,
            'completion_percent' => $completion,
            'required_percent' => (int) $rules['completion_percent'],
            'complete' => $completion >= (int) $rules['completion_percent'],
            'mark' => $mark,
            'weights' => (array) $rules['weights'],
            'entries' => $entries,
        ];
    }

    /**
     * Record one component mark. Refused when year-work is disabled for the
     * student's track/year so a General Secondary grade-12 student in
     * 2026/2027 can never acquire year-work marks.
     *
     * @return int entry id
     */
    public function record(
        int $studentId,
        int $yearId,
        int $gradeLevel,
        string $entryDate,
        string $category,
        ?string $label,
        ?string $sourceKey,
        float $score,
        float $maxScore,
        string $source = 'manual',
        ?string $yearLabel = null
    ): int {
        $rules = $this->rulesForStudent($studentId, $yearId, $gradeLevel, $yearLabel);
        if (!$rules['enabled']) {
            throw new RuntimeException('year_work_disabled');
        }
        if (!array_key_exists($category, $rules['weights'])) {
            throw new RuntimeException('year_work_invalid_category');
        }
        if ($maxScore <= 0 || $score < 0 || $score > $maxScore) {
            throw new RuntimeException('year_work_invalid_score');
        }
        return $this->repo->record(
            $yearId, $studentId, $entryDate, $category, $label, $sourceKey, $score, $maxScore, $source
        );
    }
}

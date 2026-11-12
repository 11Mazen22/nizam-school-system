<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicConfigRepository;

/**
 * Nizam -- the one place a mark becomes a grade.
 *
 * Before this class, "what percentage is this score?" and "what grade is
 * that percentage?" were answered independently in the ranking SQL, the
 * report-card PDF, both portals and the score-entry screen. Each had its own
 * rounding and its own thresholds, so the same student could rank A in one
 * screen and B on their printed report card.
 *
 * Every consumer now asks this service. It owns three things and nothing
 * else:
 *   1. score -> percentage            (percentage, weightedPercentage)
 *   2. percentage -> band              (bandFor, describePercentage)
 *   3. the active grading scale       (scale, cached per request)
 *
 * The scale is deliberately read from the school's configuration rather than
 * hardcoded: a school on a 4.0 GPA model and a school on letter grades use
 * the same code path and get their own answers.
 */
final class AcademicCalculationService
{
    private ?array $cachedScale = null;
    private bool $scaleResolved = false;

    public function __construct(
        private readonly AcademicConfigRepository $config = new AcademicConfigRepository(),
    ) {
    }

    /** Forget the cached scale (used after the configuration screens change it). */
    public function reset(): void
    {
        $this->cachedScale = null;
        $this->scaleResolved = false;
    }

    /**
     * A score out of a maximum, expressed as 0..100.
     * Null when the student has no mark yet, or the exam's maximum is not a
     * positive number -- both are "no result", never zero.
     */
    public function percentage(mixed $score, mixed $maxScore, int $precision = 2): ?float
    {
        if ($score === null || $score === '' || !is_numeric($score)) {
            return null;
        }
        if ($maxScore === null || $maxScore === '' || !is_numeric($maxScore)) {
            return null;
        }

        $max = (float) $maxScore;
        if ($max <= 0.0) {
            return null;
        }

        return round(((float) $score) / $max * 100, $precision);
    }

    /**
     * Weighted average across assessment entries, as a percentage.
     *
     * Each entry contributes (score / max_score) * weight. Dividing by the
     * sum of weights means an exam worth 30% moves the result three times as
     * much as one worth 10%, while a school that weights everything equally
     * (all weights 1) still gets a plain mean. Entries without a mark are
     * excluded rather than counted as zero -- an unmarked exam must not drag
     * a student's average down.
     *
     * @param array<int, array<string, mixed>> $entries rows containing score, max_score, weight
     * @return ?float null when nothing is marked
     */
    public function weightedPercentage(array $entries, int $precision = 2): ?float
    {
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($entries as $entry) {
            $score = $entry['score'] ?? null;
            $max = $entry['max_score'] ?? null;
            $weight = $entry['weight'] ?? 0;

            if ($score === null || $score === '' || !is_numeric($score)) {
                continue;
            }
            if ($max === null || !is_numeric($max) || (float) $max <= 0.0) {
                continue;
            }
            if (!is_numeric($weight) || (float) $weight <= 0.0) {
                continue;
            }

            $weightedSum += ((float) $score / (float) $max) * (float) $weight;
            $weightTotal += (float) $weight;
        }

        if ($weightTotal <= 0.0) {
            return null;
        }

        return round($weightedSum / $weightTotal * 100, $precision);
    }

    /**
     * The active grading scale with its bands, or null when a school has not
     * configured one. Cached per request instance: a report card renders once
     * but resolves a band per row, and re-reading the scale for every subject
     * would be pure waste.
     *
     * @return array{id:int,name_en:string,name_ar:string,scale_type:string,bands:array<int,array<string,mixed>>}|null
     */
    public function scale(): ?array
    {
        if ($this->scaleResolved) {
            return $this->cachedScale;
        }

        $this->scaleResolved = true;
        $scale = $this->config->defaultScale();
        if ($scale === null) {
            $this->cachedScale = null;
            return null;
        }

        $bands = $this->config->bands((int) $scale['id']);
        if ($bands === []) {
            $this->cachedScale = null;
            return null;
        }

        $scale['bands'] = $bands;
        $this->cachedScale = $scale;

        return $this->cachedScale;
    }

    /**
     * The band a percentage falls into, or null when it falls outside every
     * configured band (a school that only defines 50..100 leaves the bottom
     * open, and this returns null rather than inventing an "F").
     *
     * @param array{bands:array<int,array<string,mixed>>}|null $scale
     * @return array<string,mixed>|null
     */
    public function bandFor(?float $percentage, ?array $scale = null): ?array
    {
        if ($percentage === null) {
            return null;
        }

        $resolved = $scale ?? $this->scale();
        if ($resolved === null || empty($resolved['bands'])) {
            return null;
        }

        $value = round($percentage, 2);
        foreach ($resolved['bands'] as $band) {
            $min = (float) $band['min_score'];
            $max = (float) $band['max_score'];
            if ($value >= $min && $value <= $max) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Everything a screen needs to render one percentage: the number, the
     * band label, the GPA point and whether it passes.
     *
     * @param array{bands:array<int,array<string,mixed>>}|null $scale
     * @return array{percentage:?float,band_label:?string,gpa_point:?float,is_passing:?bool,scale_id:?int,scale_type:?string,scale_name:?string}
     */
    public function describePercentage(?float $percentage, ?array $scale = null): array
    {
        $resolved = $scale ?? $this->scale();
        $band = $this->bandFor($percentage, $resolved);

        return [
            'percentage' => $percentage,
            'band_label' => $band['band_label'] ?? null,
            'gpa_point'  => isset($band['gpa_point']) && $band['gpa_point'] !== null
                ? (float) $band['gpa_point'] : null,
            'is_passing' => isset($band['is_passing']) ? (int) $band['is_passing'] === 1 : null,
            'scale_id'   => isset($resolved['id']) ? (int) $resolved['id'] : null,
            'scale_type' => $resolved['scale_type'] ?? null,
            'scale_name' => $resolved['name_en'] ?? null,
        ];
    }

    /**
     * @param array{bands:array<int,array<string,mixed>>}|null $scale
     * @return array{percentage:?float,band_label:?string,gpa_point:?float,is_passing:?bool,scale_id:?int,scale_type:?string,scale_name:?string}
     */
    public function describe(mixed $score, mixed $maxScore, ?array $scale = null): array
    {
        return $this->describePercentage($this->percentage($score, $maxScore), $scale);
    }

    /**
     * Add percentage / band / GPA / passing keys to each row that carries a
     * score and a max_score. Keys are preserved, so a roster keyed by student
     * id stays keyed by student id.
     *
     * @param array<int|string, array<string,mixed>> $rows
     * @param array{bands:array<int,array<string,mixed>>}|null $scale
     * @return array<int|string, array<string,mixed>>
     */
    public function decorateRows(array $rows, ?array $scale = null): array
    {
        $resolved = $scale ?? $this->scale();

        foreach ($rows as $key => $row) {
            $description = $this->describe($row['score'] ?? null, $row['max_score'] ?? null, $resolved);
            $rows[$key]['percentage'] = $description['percentage'];
            $rows[$key]['band_label'] = $description['band_label'];
            $rows[$key]['gpa_point'] = $description['gpa_point'];
            $rows[$key]['is_passing'] = $description['is_passing'];
        }

        return $rows;
    }
}

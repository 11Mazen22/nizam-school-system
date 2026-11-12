<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicConfigRepository;

/**
 * Nizam -- validation and transactional rules for universal academic
 * configuration. The repository owns SQL; this service owns the decisions a
 * school administrator can and cannot make, and every returned error is a
 * translation key rather than a pre-formatted sentence so both locales get
 * the same message.
 *
 * Why the rules live here: "one school, one connected platform" only holds if
 * a stage, a term and a grading band mean the same thing everywhere they are
 * consumed. Duplicating these checks in controllers or views is how two
 * screens end up disagreeing about whether a term may overlap another.
 */
final class AcademicConfigService
{
    private const SCALE_TYPES = ['percentage', 'letter', 'gpa', 'points'];

    public function __construct(private readonly AcademicConfigRepository $repository = new AcademicConfigRepository())
    {
    }

    // ----------------------------------------------------------- Stages

    /** @return array{ok: bool, error?: string, id?: int} */
    public function saveStage(?int $id, string $code, string $nameEn, string $nameAr, int $sortOrder, bool $active): array
    {
        $code = strtoupper(trim($code));

        if ($nameEn === '' || $nameAr === '' || $code === '') {
            return ['ok' => false, 'error' => 'validation.required'];
        }
        if (!preg_match('/^[A-Z0-9_]{2,30}$/', $code)) {
            return ['ok' => false, 'error' => 'structure.error.code'];
        }
        if ($sortOrder < 1 || $sortOrder > 99) {
            return ['ok' => false, 'error' => 'structure.error.order'];
        }
        if ($this->repository->stageCodeExists($code, $id)) {
            return ['ok' => false, 'error' => 'structure.error.code_taken'];
        }

        if ($id === null) {
            $id = $this->repository->createStage($code, $nameEn, $nameAr, $sortOrder);
            ActivityLogger::log('academic_config.stage_create', 'educational_stages', $id, "Stage '{$code}' created");
        } else {
            $this->repository->updateStage($id, $code, $nameEn, $nameAr, $sortOrder, $active);
            ActivityLogger::log('academic_config.stage_update', 'educational_stages', $id, "Stage '{$code}' updated");
        }

        return ['ok' => true, 'id' => $id];
    }

    /**
     * A stage that still has grades in it is not deletable: the FK would
     * quietly null those grades' stage_id, erasing an association the
     * administrator set deliberately. Deactivating it is the supported path.
     *
     * @return array{ok: bool, error?: string}
     */
    public function deleteStage(int $id): array
    {
        $stage = $this->repository->findStage($id);
        if ($stage === null) {
            return ['ok' => false, 'error' => 'structure.stage.not_found'];
        }
        if ((int) $stage['grade_count'] > 0) {
            return ['ok' => false, 'error' => 'structure.stage.delete_blocked'];
        }

        $this->repository->deleteStage($id);
        ActivityLogger::log('academic_config.stage_delete', 'educational_stages', $id, null);

        return ['ok' => true];
    }

    /** @param array<int,int|null> $map grade_id => stage_id|null */
    public function assignGradeStages(array $map): void
    {
        $clean = [];
        foreach ($map as $gradeId => $stageId) {
            $clean[(int) $gradeId] = $stageId === null || $stageId === 0 ? null : (int) $stageId;
        }
        $this->repository->assignGradeStages($clean);
        ActivityLogger::log('academic_config.grade_stages', 'grades', null, 'Grade stage assignment updated');
    }

    // ------------------------------------------------------------ Terms

    /** @return array{ok: bool, error?: string, id?: int} */
    public function saveTerm(
        ?int $id,
        int $yearId,
        string $nameEn,
        string $nameAr,
        int $termNumber,
        string $startDate,
        string $endDate,
        bool $active,
        ?string $yearStart = null,
        ?string $yearEnd = null
    ): array {
        if ($nameEn === '' || $nameAr === '') {
            return ['ok' => false, 'error' => 'validation.required'];
        }
        if ($termNumber < 1 || $termNumber > 12) {
            return ['ok' => false, 'error' => 'structure.error.order'];
        }
        if (!self::isDate($startDate) || !self::isDate($endDate) || $startDate >= $endDate) {
            return ['ok' => false, 'error' => 'validation.date_order'];
        }
        if ($yearStart !== null && $yearEnd !== null
            && ($startDate < $yearStart || $endDate > $yearEnd)) {
            return ['ok' => false, 'error' => 'structure.terms.outside_year'];
        }
        if ($this->repository->termsOverlap($yearId, $startDate, $endDate, $id)) {
            return ['ok' => false, 'error' => 'structure.terms.overlap'];
        }
        if ($this->repository->termNumberExists($yearId, $termNumber, $id)) {
            return ['ok' => false, 'error' => 'structure.terms.number_taken'];
        }

        if ($id === null) {
            $id = $this->repository->createTerm($yearId, $nameEn, $nameAr, $termNumber, $startDate, $endDate);
            ActivityLogger::log('academic_config.term_create', 'academic_terms', $id, "Term {$termNumber} created");
        } else {
            $this->repository->updateTerm($id, $nameEn, $nameAr, $termNumber, $startDate, $endDate, $active);
            ActivityLogger::log('academic_config.term_update', 'academic_terms', $id, "Term {$termNumber} updated");
        }

        return ['ok' => true, 'id' => $id];
    }

    public function deleteTerm(int $id): array
    {
        if ($this->repository->findTerm($id) === null) {
            return ['ok' => false, 'error' => 'structure.terms.not_found'];
        }
        $this->repository->deleteTerm($id);
        ActivityLogger::log('academic_config.term_delete', 'academic_terms', $id, null);
        return ['ok' => true];
    }

    public function activateTerm(int $id): array
    {
        $term = $this->repository->findTerm($id);
        if ($term === null) {
            return ['ok' => false, 'error' => 'structure.terms.not_found'];
        }
        $this->repository->clearActiveTerms((int) $term['academic_year_id']);
        $this->repository->setTermActive($id);
        ActivityLogger::log('academic_config.term_activate', 'academic_terms', $id, null);
        return ['ok' => true];
    }

    // ---------------------------------------------------------- Grading

    /**
     * @param array<int, array{label:string,min:string|float,max:string|float,gpa:?string,passing:bool,desc_en:?string,desc_ar:?string}> $bands
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function saveScale(
        ?int $id,
        string $nameEn,
        string $nameAr,
        string $scaleType,
        string $description,
        bool $isDefault,
        array $bands
    ): array {
        if ($nameEn === '' || $nameAr === '') {
            return ['ok' => false, 'error' => 'validation.required'];
        }
        if (!in_array($scaleType, self::SCALE_TYPES, true)) {
            return ['ok' => false, 'error' => 'structure.error.scale_type'];
        }

        $bandError = self::validateBands($bands);
        if ($bandError !== null) {
            return ['ok' => false, 'error' => $bandError];
        }

        if ($id === null) {
            $id = $this->repository->createScale($nameEn, $nameAr, $scaleType, $description, false);
            ActivityLogger::log('academic_config.scale_create', 'grading_scales', $id, "Scale '{$nameEn}' created");
        } else {
            $this->repository->updateScale($id, $nameEn, $nameAr, $scaleType, $description);
            ActivityLogger::log('academic_config.scale_update', 'grading_scales', $id, "Scale '{$nameEn}' updated");
        }

        $this->repository->replaceBands($id, $bands);

        if ($isDefault) {
            $this->repository->setDefaultScale($id);
        }

        return ['ok' => true, 'id' => $id];
    }

    public function makeDefault(int $id): array
    {
        if ($this->repository->findScale($id) === null) {
            return ['ok' => false, 'error' => 'structure.grading.not_found'];
        }
        $this->repository->setDefaultScale($id);
        ActivityLogger::log('academic_config.scale_default', 'grading_scales', $id, null);
        return ['ok' => true];
    }

    /**
     * The default scale is what every unscoped calculation falls back to, so
     * deleting it is refused outright rather than silently leaving the school
     * with no grading rule at all.
     */
    public function deleteScale(int $id): array
    {
        $scale = $this->repository->findScale($id);
        if ($scale === null) {
            return ['ok' => false, 'error' => 'structure.grading.not_found'];
        }
        if ((int) $scale['is_default'] === 1) {
            return ['ok' => false, 'error' => 'structure.grading.delete_blocked'];
        }

        $this->repository->deleteScale($id);
        ActivityLogger::log('academic_config.scale_delete', 'grading_scales', $id, null);
        return ['ok' => true];
    }

    /**
     * Bands must form a complete, ordered, non-overlapping partition of
     * 0..100. Overlap is refused because a score would then map to two
     * different letters depending on row order, which is exactly the kind of
     * quiet inconsistency a report card must never have.
     *
     * @param array<int, array{label:string,min:string|float,max:string|float,gpa:?string,passing:bool,desc_en:?string,desc_ar:?string}> $bands
     */
    private static function validateBands(array $bands): ?string
    {
        if ($bands === []) {
            return 'structure.grading.invalid_band';
        }

        $ranges = [];
        foreach ($bands as $band) {
            $label = trim($band['label']);
            $min = $band['min'];
            $max = $band['max'];

            if ($label === '' || !is_numeric($min) || !is_numeric($max)) {
                return 'structure.grading.invalid_band';
            }
            $min = (float) $min;
            $max = (float) $max;

            if ($min < 0 || $max > 100 || $min >= $max) {
                return 'structure.grading.invalid_band';
            }
            $ranges[] = ['label' => $label, 'min' => $min, 'max' => $max];
        }

        usort($ranges, static fn (array $a, array $b): int => $a['min'] <=> $b['min']);

        for ($i = 1, $count = count($ranges); $i < $count; $i++) {
            if ($ranges[$i]['min'] <= $ranges[$i - 1]['max']) {
                return 'structure.grading.overlap';
            }
        }

        return null;
    }

    private static function isDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));
        return checkdate($m, $d, $y);
    }
}

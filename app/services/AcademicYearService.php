<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Repositories\AcademicYearRepository;
use App\Repositories\EnrollmentRepository;
use RuntimeException;

/**
 * Nizam -- §I.4 academic-year activation, closing & rollover. create()
 * mirrors the blueprint's "Create" step exactly, including the optional
 * §O-11 rollover-from-previous-year; activate()/close()/reopen() are the
 * three permission-gated, logged transitions.
 */
final class AcademicYearService
{
    public function __construct(
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
        private readonly EnrollmentRepository $enrollments = new EnrollmentRepository(),
        private readonly YearRolloverService $rollover = new YearRolloverService(),
    ) {
    }

    /** @return array{id: int} */
    public function create(string $label, string $startDate, string $endDate, ?int $rolloverFromYearId): array
    {
        $id = $this->years->create($label, $startDate, $endDate);

        if ($rolloverFromYearId !== null) {
            $classMap = $this->rollover->cloneClasses($rolloverFromYearId, $id);
            $this->rollover->cloneAssignments($rolloverFromYearId, $id, $classMap);
        }

        return ['id' => $id];
    }

    /** §I.4 "Activate": deactivates whatever was active, in the same transaction, backstopped by the DB's own unique active_flag index (§O-2). */
    public function activate(int $yearId): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->years->deactivateAll();
            $this->years->setActive($yearId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        ActivityLogger::log('academic_year.activate', 'academic_years', $yearId, "Academic year #{$yearId} activated");
    }

    /**
     * §S-3: refuses while any enrollment in this year is still 'active' --
     * promotion always precedes closing its own source year, and this guard
     * is what actually enforces that instead of merely recommending it.
     *
     * @throws RuntimeException with the exact remaining-count message §Q's error catalog specifies
     */
    public function close(int $yearId): void
    {
        $remaining = $this->enrollments->countActiveInYear($yearId);
        if ($remaining > 0) {
            throw new RuntimeException((string) $remaining);
        }

        $this->years->setClosed($yearId, true);
        ActivityLogger::log('academic_year.close', 'academic_years', $yearId, "Academic year #{$yearId} closed");
    }

    /** §I.4 "Reopen": separate, permission-gated, logged -- never implicit, never a side effect of another operation. */
    public function reopen(int $yearId): void
    {
        $this->years->setClosed($yearId, false);
        ActivityLogger::log('academic_year.reopen', 'academic_years', $yearId, "Academic year #{$yearId} reopened");
    }
}

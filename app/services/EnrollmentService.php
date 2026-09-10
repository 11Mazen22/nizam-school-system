<?php

declare(strict_types=1);

namespace App\Services;

use App\Middleware\AcademicYearContext;
use App\Repositories\EnrollmentRepository;

/** Nizam -- read-side of the Students/enrollments relationship (§Q service inventory: "currentEnrollmentFor, historyFor"). */
final class EnrollmentService
{
    public function __construct(private readonly EnrollmentRepository $enrollments = new EnrollmentRepository())
    {
    }

    /** The student's enrollment in the currently active academic year, or null if they have none there. */
    public function currentEnrollmentFor(int $studentId): ?array
    {
        $yearId = AcademicYearContext::activeYearId();
        return $yearId === null ? null : $this->enrollments->forStudentInYear($studentId, $yearId);
    }

    /** @return array<int, array<string, mixed>> every year this student has ever been enrolled in, newest first. */
    public function historyFor(int $studentId): array
    {
        return $this->enrollments->historyForStudent($studentId);
    }
}

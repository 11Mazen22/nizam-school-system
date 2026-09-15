<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Repositories\AcademicYearRepository;
use App\Repositories\ClassRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\StudentRepository;
use RuntimeException;

/**
 * Nizam -- Students module (§E). create() is the one place a student and
 * their first enrollment come into existence together -- a genuine
 * multi-table write, so it runs inside a transaction (§Q "PHP" rules).
 * Everything else that touches an enrollment afterward goes through
 * reassignClass() (§I.9) or PromotionService (§I.1) -- never a generic edit,
 * matching student_enrollments' immutability (§S-7).
 */
final class StudentService
{
    public function __construct(
        private readonly StudentRepository $students = new StudentRepository(),
        private readonly EnrollmentRepository $enrollments = new EnrollmentRepository(),
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
        private readonly UploadService $uploads = new UploadService(),
    ) {
    }

    /** @throws RuntimeException 'no_active_year'|'wrong_scope'|'year_closed' */
    public function create(
        string $fullName,
        string $gender,
        string $dateOfBirth,
        string $religion,
        ?string $phone,
        ?string $guardianPhone,
        ?string $address,
        ?string $notes,
        int $gradeId,
        ?int $classId
    ): int {
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId === null) {
            throw new RuntimeException('no_active_year');
        }
        // §I.4: a closed year rejects every enrollment write. Defense in
        // depth -- AcademicYearService::close() doesn't itself require the
        // year to be inactive first, so "active" and "closed" can coexist.
        if ($this->years->isClosed($yearId)) {
            throw new RuntimeException('year_closed');
        }

        // Friendly check first (§Q's "form checks first" pattern) -- the
        // composite FK (§S-1) is still the real backstop, but without this a
        // mismatched grade/class pair (a tampered request, or a client-JS bug)
        // would surface as a raw constraint-violation 500 instead of a normal
        // validation message.
        if ($classId !== null) {
            $class = $this->classes->find($classId);
            if ($class === null || (int) $class['is_active'] !== 1
                || (int) $class['grade_id'] !== $gradeId || (int) $class['academic_year_id'] !== $yearId) {
                throw new RuntimeException('wrong_scope');
            }
        }

        $pattern = SettingsService::get('academic.student_id_pattern', 'STU-{year}-{seq:6}');
        $codeYear = substr(AcademicYearContext::label() ?? '', 0, 4) ?: date('Y');

        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $studentId = $this->students->create(
                $fullName, $gender, $dateOfBirth, $religion, $phone, $guardianPhone, $address, $notes,
                (string) $pattern, $codeYear
            );
            $this->enrollments->createInitial($studentId, $yearId, $gradeId, $classId, date('Y-m-d'));
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        ActivityLogger::log('student.create', 'students', $studentId, "Student '{$fullName}' enrolled");
        return $studentId;
    }

    public function update(
        int $id,
        string $fullName,
        string $gender,
        string $dateOfBirth,
        string $religion,
        ?string $phone,
        ?string $guardianPhone,
        ?string $address,
        ?string $notes,
        ?int $classId = null,
        ?int $gradeId = null
    ): void {
        if ($gradeId !== null) {
            $yearId = AcademicYearContext::activeYearId();
            if ($yearId === null || $this->years->isClosed($yearId)) {
                throw new RuntimeException('year_closed');
            }
            if ($this->enrollments->forStudentInYear($id, $yearId) !== null) {
                throw new RuntimeException('wrong_scope');
            }
            if ($classId !== null) {
                $targetClass = $this->classes->find($classId);
                if ($targetClass === null || (int) $targetClass['is_active'] !== 1
                    || (int) $targetClass['grade_id'] !== $gradeId || (int) $targetClass['academic_year_id'] !== $yearId) {
                    throw new RuntimeException('wrong_scope');
                }
            }
            $pdo = Database::connection();
            $pdo->beginTransaction();
            try {
                $this->students->update($id, $fullName, $gender, $dateOfBirth, $religion, $phone, $guardianPhone, $address, $notes);
                $this->enrollments->createInitial($id, $yearId, $gradeId, $classId, date('Y-m-d'));
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            ActivityLogger::log('student.update', 'students', $id, "Student '{$fullName}' updated and enrolled");
            return;
        }

        if ($classId === null) {
            $this->students->update($id, $fullName, $gender, $dateOfBirth, $religion, $phone, $guardianPhone, $address, $notes);
            ActivityLogger::log('student.update', 'students', $id, "Student '{$fullName}' updated");
            return;
        }

        $yearId = AcademicYearContext::activeYearId();
        $enrollment = $yearId === null ? null : $this->enrollments->forStudentInYear($id, $yearId);
        if ($enrollment === null || $enrollment['status'] !== 'active') {
            throw new RuntimeException('no_active_enrollment');
        }
        if ($this->years->isClosed((int) $enrollment['academic_year_id'])) {
            throw new RuntimeException('year_closed');
        }
        $targetClass = $this->classes->find($classId);
        if ($targetClass === null || (int) $targetClass['is_active'] !== 1
            || (int) $targetClass['grade_id'] !== (int) $enrollment['grade_id']
            || (int) $targetClass['academic_year_id'] !== (int) $enrollment['academic_year_id']) {
            throw new RuntimeException('wrong_scope');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->students->update($id, $fullName, $gender, $dateOfBirth, $religion, $phone, $guardianPhone, $address, $notes);
            $this->enrollments->reassignClass((int) $enrollment['id'], $classId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        ActivityLogger::log('student.update', 'students', $id, "Student '{$fullName}' updated");
        ActivityLogger::log('student.reassign_class', 'students', $id, "Reassigned to class #{$classId}");
    }

    /**
     * §I.8: archiving a student with an active enrollment in the CURRENT year
     * is not a bare status flip -- in the same transaction, close that
     * enrollment to 'withdrawn' too, so a report joining on enrollment status
     * and a report checking the student's own status never quietly disagree.
     */
    public function archive(int $id): void
    {
        $student = $this->students->find($id);
        if ($student === null) {
            throw new RuntimeException('not_found');
        }
        if ($student['status'] !== 'active') {
            throw new RuntimeException('not_active');
        }
        $yearId = AcademicYearContext::activeYearId();
        if ($yearId !== null && $this->years->isClosed($yearId)) {
            throw new RuntimeException('year_closed');
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->students->setStatus($id, 'archived');

            if ($yearId !== null) {
                $enrollment = $this->enrollments->forStudentInYear($id, $yearId);
                if ($enrollment !== null && $enrollment['status'] === 'active') {
                    $this->enrollments->closeStatus((int) $enrollment['id'], 'withdrawn');
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        ActivityLogger::log('student.archive', 'students', $id, null);
    }

    /** Restore the student and reverse the current enrollment withdrawal made by archive(). */
    public function restore(int $id): void
    {
        $student = $this->students->find($id);
        if ($student === null) {
            throw new RuntimeException('not_found');
        }
        if ($student['status'] !== 'archived') {
            throw new RuntimeException('not_archived');
        }

        $yearId = AcademicYearContext::activeYearId();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->students->setStatus($id, 'active');
            // Closed years remain historical. In an open year, archive()'s
            // withdrawal is reversible and must not leave a restored student
            // missing from their class roster.
            if ($yearId !== null && !$this->years->isClosed($yearId)) {
                $enrollment = $this->enrollments->forStudentInYear($id, $yearId);
                if ($enrollment !== null && $enrollment['status'] === 'withdrawn') {
                    $this->enrollments->reopenToActive((int) $enrollment['id']);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        ActivityLogger::log('student.restore', 'students', $id, null);
    }

    /**
     * Decision #11: replaces this student's photo, deleting whatever file
     * used to be there once the new one is safely written -- never the other
     * order, so a failed upload never destroys a perfectly good existing
     * photo.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @throws RuntimeException 'upload_error'|'too_large'|'invalid_type'|'not_an_image'|'write_failed'
     */
    public function updatePhoto(int $id, array $file): void
    {
        $student = $this->students->find($id);
        $newPath = $this->uploads->store($file, 'students');
        $this->students->updatePhoto($id, $newPath);
        $this->uploads->delete($student['photo_path'] ?? null);
        ActivityLogger::log('student.photo_update', 'students', $id, null);
    }

    public function removePhoto(int $id): void
    {
        $student = $this->students->find($id);
        $this->students->updatePhoto($id, null);
        $this->uploads->delete($student['photo_path'] ?? null);
        ActivityLogger::log('student.photo_remove', 'students', $id, null);
    }

    /**
     * §I.9: moves exactly one field -- class_id -- on the student's CURRENT
     * (active) enrollment, and only within the same grade/year the
     * enrollment already has. The composite FK (§S-1) is the hard backstop;
     * this is the friendly check first.
     *
     * @throws RuntimeException 'no_active_enrollment'|'wrong_scope'|'year_closed'
     */
    public function reassignClass(int $studentId, int $newClassId): void
    {
        $yearId = AcademicYearContext::activeYearId();
        $enrollment = $yearId === null ? null : $this->enrollments->forStudentInYear($studentId, $yearId);

        if ($enrollment === null || $enrollment['status'] !== 'active') {
            throw new RuntimeException('no_active_enrollment');
        }
        if ($this->years->isClosed((int) $enrollment['academic_year_id'])) {
            throw new RuntimeException('year_closed');
        }

        $targetClass = $this->classes->find($newClassId);
        if (
            $targetClass === null
            || (int) $targetClass['grade_id'] !== (int) $enrollment['grade_id']
            || (int) $targetClass['academic_year_id'] !== (int) $enrollment['academic_year_id']
        ) {
            throw new RuntimeException('wrong_scope');
        }

        $this->enrollments->reassignClass((int) $enrollment['id'], $newClassId);
        ActivityLogger::log('student.reassign_class', 'students', $studentId, "Reassigned to class #{$newClassId}");
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Repositories\AdmissionsRepository;
use App\Repositories\GuardianRepository;
use App\Repositories\StudentRepository;
use Exception;
use PDO;

final class AdmissionsService
{
    private AdmissionsRepository $admissionsRepo;
    private GuardianRepository $guardianRepo;

    public function __construct(
        ?AdmissionsRepository $admissionsRepo = null,
        ?GuardianRepository $guardianRepo = null
    ) {
        $this->admissionsRepo = $admissionsRepo ?? new AdmissionsRepository();
        $this->guardianRepo = $guardianRepo ?? new GuardianRepository();
    }

    public function generateApplicationNumber(): string
    {
        $year = date('Y');
        $random = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        return "APP-{$year}-{$random}";
    }

    public function createApplication(array $data): array
    {
        if (empty($data['applicant_name']) || empty($data['guardian_phone']) || empty($data['grade_id'])) {
            return ['success' => false, 'error' => 'Missing required application fields'];
        }

        $appNumber = $this->generateApplicationNumber();
        $data['application_number'] = $appNumber;

        $id = $this->admissionsRepo->create($data);
        return ['success' => true, 'id' => $id, 'application_number' => $appNumber];
    }

    /**
     * Atomically converts an accepted applicant into an active Student with
     * linked Guardian and Enrollment.
     *
     * Only an application that has actually been offered a place may be
     * converted. Previously anything except "enrolled" was accepted, so an
     * unreviewed inquiry -- or a rejected application -- could become an
     * enrolled child by pressing the convert button.
     *
     * @return array{success: bool, student_id?: int, error?: string}
     */
    public function convertApplicantToStudent(int $applicationId, ?int $classId = null): array
    {
        $app = $this->admissionsRepo->find($applicationId);
        if ($app === null) {
            return ['success' => false, 'error' => 'Application not found'];
        }

        if ($app['status'] === 'enrolled') {
            return ['success' => false, 'error' => 'Application has already been converted to an enrolled student'];
        }

        // 'decision' is accepted alongside 'offer' because recordDecision()
        // stores an accepted decision as 'offer'; the pair covers both the
        // explicit offer step and a decision recorded as accepted.
        if (!in_array((string) $app['status'], ['offer', 'decision'], true)) {
            return ['success' => false, 'error' => 'admissions.convert_invalid_status'];
        }

        // A class belonging to another grade or academic year would produce an
        // enrollment the academic structure cannot explain.
        if ($classId !== null) {
            $classStmt = Database::connection()->prepare(
                'SELECT id, grade_id, academic_year_id FROM classes WHERE id = :id LIMIT 1'
            );
            $classStmt->execute(['id' => $classId]);
            $class = $classStmt->fetch();
            if ($class === false
                || (int) $class['grade_id'] !== (int) $app['grade_id']
                || (int) $class['academic_year_id'] !== (int) $app['academic_year_id']) {
                return ['success' => false, 'error' => 'admissions.convert_class_mismatch'];
            }
        }

        $pdo = Database::connection();
        $inTx = $pdo->inTransaction();
        if (!$inTx) {
            $pdo->beginTransaction();
        }

        try {
            // 1. Generate a unique student code (retried, because a collision
            //    would otherwise abort the whole conversion).
            $code = $this->generateStudentCode($pdo);

            // 2. Insert Student. Statuses are bound as parameters rather than
            //    written as double-quoted SQL literals, which PostgreSQL reads
            //    as identifiers rather than strings.
            $studentId = Database::insert(
                $pdo,
                'INSERT INTO students (
                    student_code, full_name, gender, date_of_birth, religion,
                    phone, guardian_phone, address, status, created_at, updated_at
                 ) VALUES (
                    :code, :name, :gender, :dob, :religion,
                    :phone, :g_phone, :address, :status, NOW(), NOW()
                 )',
                [
                    'code'     => $code,
                    'name'     => $app['applicant_name'],
                    'gender'   => $app['gender'],
                    'dob'      => $app['date_of_birth'],
                    'religion' => 'other',
                    'phone'    => $app['guardian_phone'],
                    'g_phone'  => $app['guardian_phone'],
                    'address'  => 'Registered via Admissions',
                    'status'   => 'active',
                ]
            );

            // 3. Find or Create Guardian
            $guardian = $this->guardianRepo->findByPhone($app['guardian_phone']);
            if ($guardian === null) {
                $gId = $this->guardianRepo->create([
                    'full_name'         => $app['guardian_name'],
                    'phone'             => $app['guardian_phone'],
                    'email'             => $app['guardian_email'],
                    'relationship_type' => $app['guardian_relationship'] ?? 'father',
                ]);
            } else {
                $gId = (int) $guardian['id'];
            }

            // 4. Link Student & Guardian
            $this->guardianRepo->linkStudent($studentId, $gId, $app['guardian_relationship'] ?? 'father', true, true, true, true);

            // 5. Create active enrollment
            $enrollStmt = $pdo->prepare(
                'INSERT INTO student_enrollments (
                    student_id, academic_year_id, grade_id, class_id, enrollment_date, status, created_at
                 ) VALUES (
                    :s_id, :y_id, :g_id, :c_id, :edate, :status, NOW()
                 )'
            );
            $enrollStmt->execute([
                's_id'   => $studentId,
                'y_id'   => $app['academic_year_id'],
                'g_id'   => $app['grade_id'],
                'c_id'   => $classId,
                'edate'  => date('Y-m-d'),
                'status' => 'active',
            ]);

            // 6. Update Application Record
            $this->admissionsRepo->markEnrolled($applicationId, $studentId);

            if (!$inTx) {
                $pdo->commit();
            }

            return ['success' => true, 'student_id' => $studentId];
        } catch (\Throwable $e) {
            if (!$inTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** @param PDO $pdo */
    private function generateStudentCode(PDO $pdo): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = 'STU-' . date('Y') . '-' . str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
            $check = $pdo->prepare('SELECT COUNT(*) FROM students WHERE student_code = :code');
            $check->execute(['code' => $code]);
            if ((int) $check->fetchColumn() === 0) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not generate a unique student code');
    }
}

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
     * Atomically converts an accepted applicant into an active Student with linked Guardian and Enrollment.
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

        $pdo = Database::connection();
        $inTx = $pdo->inTransaction();
        if (!$inTx) {
            $pdo->beginTransaction();
        }

        try {
            // 1. Generate unique student code
            $code = 'STU-' . date('Y') . '-' . str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);

            // 2. Insert Student
            $stmt = $pdo->prepare(
                'INSERT INTO students (
                    student_code, full_name, gender, date_of_birth, religion,
                    phone, guardian_phone, address, status, created_at, updated_at
                 ) VALUES (
                    :code, :name, :gender, :dob, "other",
                    :phone, :g_phone, :address, "active", NOW(), NOW()
                 )'
            );
            $stmt->execute([
                'code'    => $code,
                'name'    => $app['applicant_name'],
                'gender'  => $app['gender'],
                'dob'     => $app['date_of_birth'],
                'phone'   => $app['guardian_phone'],
                'g_phone' => $app['guardian_phone'],
                'address' => 'Registered via Admissions',
            ]);
            $studentId = (int) $pdo->lastInsertId();

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
                    :s_id, :y_id, :g_id, :c_id, :edate, "active", NOW()
                 )'
            );
            $enrollStmt->execute([
                's_id'  => $studentId,
                'y_id'  => $app['academic_year_id'],
                'g_id'  => $app['grade_id'],
                'c_id'  => $classId,
                'edate' => date('Y-m-d'),
            ]);

            // 6. Update Application Record
            $this->admissionsRepo->markEnrolled($applicationId, $studentId);

            if (!$inTx) {
                $pdo->commit();
            }

            return ['success' => true, 'student_id' => $studentId];
        } catch (Exception $e) {
            if (!$inTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

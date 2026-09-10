<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Repositories\TeacherAssignmentRepository;
use App\Repositories\TeacherRepository;

/** Nizam -- Teachers module (§E, §I.8 archive-cascade). */
final class TeacherService
{
    public function __construct(
        private readonly TeacherRepository $teachers = new TeacherRepository(),
        private readonly TeacherAssignmentRepository $assignments = new TeacherAssignmentRepository(),
        private readonly UploadService $uploads = new UploadService(),
    ) {
    }

    public function create(string $fullName, ?string $phone, ?string $email): int
    {
        $pattern = SettingsService::get('academic.teacher_id_pattern', 'TCH-{seq:6}');
        $id = $this->teachers->create($fullName, $phone, $email, (string) $pattern);
        ActivityLogger::log('teacher.create', 'teachers', $id, "Teacher '{$fullName}' created");
        return $id;
    }

    public function update(int $id, string $fullName, ?string $phone, ?string $email): void
    {
        $this->teachers->update($id, $fullName, $phone, $email);
        ActivityLogger::log('teacher.update', 'teachers', $id, "Teacher '{$fullName}' updated");
    }

    /**
     * §I.8: archiving a teacher with an active assignment in the CURRENT year is
     * not a bare status flip -- in the same transaction, close that assignment
     * to 'archived' too, so a workload/staffing report never lists an archived
     * teacher as if they still carried a live assignment.
     */
    public function archive(int $id): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->teachers->setStatus($id, 'archived');

            $yearId = AcademicYearContext::activeYearId();
            if ($yearId !== null) {
                foreach ($this->assignments->activeIdsForTeacherInYear($id, $yearId) as $assignmentId) {
                    $this->assignments->setStatus($assignmentId, 'archived');
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        ActivityLogger::log('teacher.archive', 'teachers', $id, null);
    }

    public function restore(int $id): void
    {
        $this->teachers->setStatus($id, 'active');
        ActivityLogger::log('teacher.restore', 'teachers', $id, null);
    }

    /**
     * Decision #11: replaces this teacher's photo, deleting whatever file
     * used to be there only once the new one is safely written.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @throws \RuntimeException 'upload_error'|'too_large'|'invalid_type'|'not_an_image'|'write_failed'
     */
    public function updatePhoto(int $id, array $file): void
    {
        $teacher = $this->teachers->find($id);
        $newPath = $this->uploads->store($file, 'teachers');
        $this->teachers->updatePhoto($id, $newPath);
        $this->uploads->delete($teacher['photo_path'] ?? null);
        ActivityLogger::log('teacher.photo_update', 'teachers', $id, null);
    }

    public function removePhoto(int $id): void
    {
        $teacher = $this->teachers->find($id);
        $this->teachers->updatePhoto($id, null);
        $this->uploads->delete($teacher['photo_path'] ?? null);
        ActivityLogger::log('teacher.photo_remove', 'teachers', $id, null);
    }
}

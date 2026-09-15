<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Database;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Request;
use App\Repositories\ClassRepository;
use App\Services\ImportService;
use App\Services\GradeService;
use App\Services\ClassService;
use App\Services\SubjectService;
use App\Services\TeacherService;
use App\Services\StudentService;
use App\Services\UserService;
use RuntimeException;
use Throwable;

final class ImportController extends Controller
{
    private function getEntityConfig(?string $entity): ?array
    {
        return match ($entity) {
            "grades" => [
                "headers" => ["Name (English)", "Name (Arabic)", "Sort Order"],
                "process" => function (array $row) {
                    $nameEn = trim((string) ($row["Name (English)"] ?? ""));
                    $nameAr = trim((string) ($row["Name (Arabic)"] ?? ""));
                    $sortOrder = (int) ($row["Sort Order"] ?? 0);
                    if ($nameEn === "" || $nameAr === "") throw new RuntimeException("Missing data");
                    (new GradeService())->create($nameEn, $nameAr, $sortOrder);
                },
                "permission" => "grades.manage",
                "redirect" => "/grades"
            ],
            "classes" => [
                "headers" => ["Grade ID", "Name", "Capacity Override"],
                "process" => function (array $row) {
                    $yearId = AcademicYearContext::activeYearId();
                    if (!$yearId) throw new RuntimeException("No active year");
                    $gradeId = (int) ($row["Grade ID"] ?? 0);
                    $name = trim((string) ($row["Name"] ?? ""));
                    $capacity = trim((string) ($row["Capacity Override"] ?? "")) !== "" ? (int) $row["Capacity Override"] : null;
                    if ($gradeId <= 0 || $name === "") throw new RuntimeException("Missing data");
                    (new ClassService())->create($gradeId, $yearId, $name, $capacity);
                },
                "permission" => "classes.manage",
                "redirect" => "/classes"
            ],
            "subjects" => [
                "headers" => ["Code", "Name (English)", "Name (Arabic)"],
                "process" => function (array $row) {
                    $code = trim((string) ($row["Code"] ?? ""));
                    $nameEn = trim((string) ($row["Name (English)"] ?? ""));
                    $nameAr = trim((string) ($row["Name (Arabic)"] ?? ""));
                    if ($nameEn === "" || $nameAr === "" || $code === "") throw new RuntimeException("Missing data");
                    (new SubjectService())->create($code, $nameEn, $nameAr);
                },
                "permission" => "subjects.manage",
                "redirect" => "/subjects"
            ],
            "teachers" => [
                "headers" => ["Full Name", "Email", "Phone"],
                "process" => function (array $row) {
                    $fullName = trim((string) ($row["Full Name"] ?? ""));
                    $email = trim((string) ($row["Email"] ?? "")) ?: null;
                    $phone = trim((string) ($row["Phone"] ?? "")) ?: null;
                    if ($fullName === "") throw new RuntimeException("Missing data");
                    (new TeacherService())->create($fullName, $phone, $email);
                },
                "permission" => "teachers.create",
                "redirect" => "/teachers"
            ],
            "students" => [
                "headers" => ["Full Name", "Gender (M/F)", "Date of Birth (YYYY-MM-DD)", "Religion", "Grade ID", "Class Name"],
                "process" => function (array $row) {
                    $fullName = trim((string) ($row["Full Name"] ?? ""));
                    $gender = strtolower(trim((string) ($row["Gender (M/F)"] ?? "")));
                    $dob = trim((string) ($row["Date of Birth (YYYY-MM-DD)"] ?? ""));
                    $religion = strtolower(trim((string) ($row["Religion"] ?? "")));
                    $gradeId = (int) ($row["Grade ID"] ?? 0);
                    $className = trim((string) ($row["Class Name"] ?? ""));
                    if ($fullName === "" || !in_array($gender, ['m', 'f'], true)
                        || !in_array($religion, ['muslim', 'christian', 'other'], true)
                        || $dob === "" || $gradeId <= 0) throw new RuntimeException("Missing data");
                    $yearId = AcademicYearContext::activeYearId();
                    if ($yearId === null) throw new RuntimeException("No active year");
                    $classId = null;
                    if ($className !== '') {
                        $class = (new ClassRepository())->findByName($gradeId, $yearId, $className);
                        if ($class === null || (int) $class['is_active'] !== 1) throw new RuntimeException("Unknown class");
                        $classId = (int) $class['id'];
                    }
                    
                    (new StudentService())->create(
                        $fullName, $gender, $dob, $religion,
                        null, null, null, null,
                        $gradeId, $classId
                    );
                },
                "permission" => "students.create",
                "redirect" => "/students"
            ],
            "users" => [
                "headers" => ["Username", "Password", "Full Name", "Role"],
                "process" => function (array $row) {
                    $username = trim((string) ($row["Username"] ?? ""));
                    $password = (string) ($row["Password"] ?? "");
                    $fullName = trim((string) ($row["Full Name"] ?? ""));
                    $role = trim((string) ($row["Role"] ?? ""));
                    if ($username === "" || $fullName === "" || $role === "") throw new RuntimeException("Missing data");
                    (new UserService())->create($username, $password, $fullName, $role);
                },
                "permission" => "users.manage",
                "redirect" => "/users"
            ],
            "assignments" => [
                "headers" => ["Teacher ID", "Subject ID", "Class ID", "Weekly Periods"],
                "process" => function (array $row) {
                    $yearId = AcademicYearContext::activeYearId();
                    if (!$yearId) throw new RuntimeException("No active year");
                    $teacherId = (int) ($row["Teacher ID"] ?? 0);
                    $subjectId = (int) ($row["Subject ID"] ?? 0);
                    $classId = (int) ($row["Class ID"] ?? 0);
                    $periods = (int) ($row["Weekly Periods"] ?? 0);
                    if ($teacherId <= 0 || $subjectId <= 0 || $classId <= 0 || $periods <= 0) throw new RuntimeException("Missing data");
                    (new \App\Services\AssignmentService())->create($teacherId, $subjectId, $classId, $yearId, $periods);
                },
                "permission" => "assignments.manage",
                "redirect" => "/assignments"
            ],
            default => null
        };
    }

    public function template(Request $request): void
    {
        $entity = $request->paramString("entity");
        $config = $this->getEntityConfig($entity);
        if (!$config) {
            $this->redirect("/");
            return;
        }

        // Permission check
        if (!in_array($config["permission"], $_SESSION["permissions"] ?? [], true)) {
            \App\ErrorHandler::renderNotFound();
            return;
        }

        $referenceRows = [];
        if ($entity === 'students') {
            $yearId = AcademicYearContext::activeYearId();
            if ($yearId !== null) {
                foreach ((new ClassRepository())->allForYear($yearId) as $class) {
                    if ((int) $class['is_active'] === 1) {
                        $referenceRows[] = [
                            'Grade ID' => $class['grade_id'],
                            'Class Name' => $class['name'],
                            'Grade' => currentLocale() === 'ar' ? $class['grade_name_ar'] : $class['grade_name_en'],
                        ];
                    }
                }
            }
        }

        (new ImportService())->generateTemplate($config["headers"], $entity . "_template", currentLocale(), $referenceRows);
    }

    public function import(Request $request): void
    {
        $entity = $request->paramString("entity");
        $config = $this->getEntityConfig($entity);
        if (!$config) {
            $this->redirect("/");
            return;
        }

        if (!in_array($config["permission"], $_SESSION["permissions"] ?? [], true)) {
            \App\ErrorHandler::renderNotFound();
            return;
        }

        if (!isset($_FILES["import_file"]) || $_FILES["import_file"]["error"] !== UPLOAD_ERR_OK) {
            Flash::set("danger", __("import.no_file"));
            $this->redirect($config["redirect"]);
            return;
        }

        try {
            $rows = (new ImportService())->parseFile($_FILES["import_file"]["tmp_name"], $config["headers"]);
        } catch (Throwable $e) {
            Flash::set("danger", __("import.invalid_format"));
            $this->redirect($config["redirect"]);
            return;
        }

        if (empty($rows)) {
            Flash::set("warning", __("import.no_data"));
            $this->redirect($config["redirect"]);
            return;
        }

        $success = 0;
        $errors = 0;
        $process = $config["process"];

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                try {
                    $process($row);
                    $success++;
                } catch (Throwable $e) {
                    $errors++;
                    // Log the specific error for debugging
                    error_log("Import row error for {$entity}: " . $e->getMessage());
                }
            }

            // Imports are atomic: showing a partly-successful upload is not
            // acceptable when later rows can invalidate the batch's intended
            // relationships. Keep individual errors in the server log, but
            // commit only a completely valid source file.
            if ($errors > 0) {
                $pdo->rollBack();
                Flash::set("danger", __("import.failed"));
            } else {
                $pdo->commit();
                Flash::set("success", strtr(__("app.import_success"), ["{success}" => $success, "{errors}" => $errors]));
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Catch any unexpected errors during the import process
            Flash::set("danger", __("import.error"));
            error_log("Import process error for {$entity}: " . $e->getMessage());
        }

        $this->redirect($config["redirect"]);
    }
}

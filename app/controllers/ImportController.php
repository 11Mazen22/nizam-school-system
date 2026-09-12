<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Request;
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
    private function getEntityConfig(string $entity): ?array
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
                    (new ClassService())->create($yearId, $gradeId, $name, $capacity);
                },
                "permission" => "classes.manage",
                "redirect" => "/classes"
            ],
            "subjects" => [
                "headers" => ["Name (English)", "Name (Arabic)", "Code", "Weekly Periods", "Sort Order"],
                "process" => function (array $row) {
                    $nameEn = trim((string) ($row["Name (English)"] ?? ""));
                    $nameAr = trim((string) ($row["Name (Arabic)"] ?? ""));
                    $code = trim((string) ($row["Code"] ?? ""));
                    $periods = (int) ($row["Weekly Periods"] ?? 0);
                    $sortOrder = (int) ($row["Sort Order"] ?? 0);
                    if ($nameEn === "" || $nameAr === "" || $code === "" || $periods <= 0) throw new RuntimeException("Missing data");
                    (new SubjectService())->create($nameEn, $nameAr, $code, $periods, $sortOrder);
                },
                "permission" => "subjects.manage",
                "redirect" => "/subjects"
            ],
            "teachers" => [
                "headers" => ["Name (English)", "Name (Arabic)", "Email", "Phone", "Max Weekly Periods"],
                "process" => function (array $row) {
                    $nameEn = trim((string) ($row["Name (English)"] ?? ""));
                    $nameAr = trim((string) ($row["Name (Arabic)"] ?? ""));
                    $email = trim((string) ($row["Email"] ?? "")) ?: null;
                    $phone = trim((string) ($row["Phone"] ?? "")) ?: null;
                    $maxPeriods = trim((string) ($row["Max Weekly Periods"] ?? "")) !== "" ? (int) $row["Max Weekly Periods"] : null;
                    if ($nameEn === "" || $nameAr === "") throw new RuntimeException("Missing data");
                    (new TeacherService())->create($nameEn, $nameAr, $email, $phone, $maxPeriods);
                },
                "permission" => "teachers.manage",
                "redirect" => "/teachers"
            ],
            "students" => [
                "headers" => ["Name (English)", "Name (Arabic)", "National ID", "Gender (M/F)", "Date of Birth (YYYY-MM-DD)", "Enrollment Grade ID"],
                "process" => function (array $row) {
                    $nameEn = trim((string) ($row["Name (English)"] ?? ""));
                    $nameAr = trim((string) ($row["Name (Arabic)"] ?? ""));
                    $nationalId = trim((string) ($row["National ID"] ?? "")) ?: null;
                    $gender = strtoupper(trim((string) ($row["Gender (M/F)"] ?? "")));
                    if (!in_array($gender, ["M", "F"])) $gender = "M"; // fallback
                    $dob = trim((string) ($row["Date of Birth (YYYY-MM-DD)"] ?? ""));
                    if (!$dob) $dob = null;
                    
                    $gradeId = (int) ($row["Enrollment Grade ID"] ?? 0);
                    $yearId = AcademicYearContext::activeYearId();
                    
                    if ($nameEn === "" || $nameAr === "") throw new RuntimeException("Missing data");
                    
                    $studentService = new StudentService();
                    // We only have create for student. 
                    // Actually let us just construct the array ClassController requires or call StudentService directly.
                    // The service layer might need more fields. We pass basic ones.
                    // Wait, StudentService::create requires many params.
                    // create(string $nameEn, string $nameAr, ?string $nationalId, string $gender, ?string $dob, ?string $bloodType, ?string $address, ?string $medicalNotes, ?int $enrollmentYearId, ?int $enrollmentGradeId): int
                    $studentService->create($nameEn, $nameAr, $nationalId, $gender, $dob, null, null, null, $yearId, $gradeId > 0 ? $gradeId : null);
                },
                "permission" => "students.manage", // Wait, students have students.edit or students.create? Actually usually students.edit
                "redirect" => "/students"
            ],
                        "users" => [
                "headers" => ["Name", "Email", "Password", "Role"],
                "process" => function (array $row) {
                    $name = trim((string) ($row["Name"] ?? ""));
                    $email = trim((string) ($row["Email"] ?? ""));
                    $password = trim((string) ($row["Password"] ?? ""));
                    $role = trim((string) ($row["Role"] ?? ""));
                    if ($name === "" || $email === "" || $password === "" || $role === "") throw new RuntimeException("Missing data");
                    
                    // Generate a username from email or name
                    $username = explode("@", $email)[0] ?: strtolower(str_replace(" ", ".", $name)) . rand(100,999);
                    (new UserService())->create($username, $password, $name, $role, $email);
    
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
        $entity = $request->param("entity");
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

        (new ImportService())->generateTemplate($config["headers"], $entity . "_template", currentLocale());
    }

    public function import(Request $request): void
    {
        $entity = $request->param("entity");
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
            Flash::set("danger", "File upload failed");
            $this->redirect($config["redirect"]);
            return;
        }

        try {
            $rows = (new ImportService())->parseFile($_FILES["import_file"]["tmp_name"]);
        } catch (Throwable $e) {
            Flash::set("danger", "Invalid file format");
            $this->redirect($config["redirect"]);
            return;
        }

        $success = 0;
        $errors = 0;
        $process = $config["process"];

        foreach ($rows as $row) {
            try {
                $process($row);
                $success++;
            } catch (Throwable) {
                $errors++;
            }
        }

        Flash::set("success", strtr(__("app.import_success"), ["{success}" => $success, "{errors}" => $errors]));
        $this->redirect($config["redirect"]);
    }
}


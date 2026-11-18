<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Database;
use App\Middleware\AcademicYearContext;
use App\Request;
use App\Repositories\AttendanceRepository;
use App\Repositories\ExamRepository;
use App\Repositories\GuardianRepository;
use App\Repositories\StudentRepository;
use App\Repositories\TeacherRepository;
use App\Repositories\TimetableRepository;
use App\Services\AcademicCalculationService;
use App\Services\TimetableService;

final class PortalController extends Controller
{
    public function __construct(
        private readonly AcademicCalculationService $calculator = new AcademicCalculationService(),
    ) {
    }
    /**
     * Teacher Workspace: Assigned classes, today's timetable periods, recent attendance.
     */
    public function teacher(Request $request): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $activeYearId = AcademicYearContext::activeYearId();

        // Find teacher row linked to user or email/name
        $teacher = (new TeacherRepository())->all()[0] ?? null; // default to first teacher if not mapped
        $assignments = [];
        $todayTimetable = [];
        $assessments = [];

        if ($teacher !== null && $activeYearId !== null) {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT ta.*, c.name AS class_name, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar,
                        s.name_en AS subject_name_en, s.name_ar AS subject_name_ar
                 FROM teacher_assignments ta
                 JOIN classes c ON c.id = ta.class_id
                 JOIN grades g ON g.id = c.grade_id
                 JOIN subjects s ON s.id = ta.subject_id
                 WHERE ta.teacher_id = :t_id AND ta.academic_year_id = :y_id AND ta.status = \'active\''
            );
            $stmt->execute(['t_id' => $teacher['id'], 'y_id' => $activeYearId]);
            $assignments = $stmt->fetchAll() ?: [];

            // Today's day of week: PHP w (0 for Sunday to 6 for Saturday)
            $dow = (int) date('w');
            $ttStmt = $pdo->prepare(
                'SELECT tt.*, c.name AS class_name, s.name_en AS subject_name_en, s.name_ar AS subject_name_ar
                 FROM timetables tt
                 JOIN classes c ON c.id = tt.class_id
                 JOIN subjects s ON s.id = tt.subject_id
                 WHERE tt.teacher_id = :t_id AND tt.academic_year_id = :y_id AND tt.day_of_week = :dow
                 ORDER BY tt.period_number ASC'
            );
            $ttStmt->execute(['t_id' => $teacher['id'], 'y_id' => $activeYearId, 'dow' => $dow]);
            $todayTimetable = $ttStmt->fetchAll() ?: [];
            $assessments = $this->assessmentsForClasses(
                $activeYearId,
                array_map(static fn (array $row): int => (int) $row['class_id'], $assignments)
            );
        }

        $this->view('portal/teacher', [
            'teacher'        => $teacher,
            'assignments'    => $assignments,
            'todayTimetable' => $todayTimetable,
            'assessments'    => $assessments,
            'dayLabels'      => (new TimetableService())->getDayLabels(),
        ]);
    }

    /**
     * Parent / Family Portal: Children selector, attendance, grades, invoices.
     */
    public function family(Request $request): void
    {
        $guardianRepo = new GuardianRepository();
        $guardians = $guardianRepo->all();
        $selectedGuardianId = $request->query('guardian_id') ? (int) $request->query('guardian_id') : ($guardians[0]['id'] ?? 0);

        $guardian = $selectedGuardianId > 0 ? $guardianRepo->find($selectedGuardianId) : null;
        $children = $selectedGuardianId > 0 ? $guardianRepo->getStudentsForGuardian($selectedGuardianId) : [];

        // If child selected, load their grades and attendance summary
        $selectedStudentId = $request->query('student_id') ? (int) $request->query('student_id') : ($children[0]['id'] ?? 0);
        $student = null;
        $scores = [];
        $assessments = [];
        $attendanceStats = null;

        if ($selectedStudentId > 0) {
            $student = (new StudentRepository())->find($selectedStudentId);
            $activeYearId = AcademicYearContext::activeYearId();

            if ($activeYearId !== null) {
                $pdo = Database::connection();
                // Exam scores
                $scStmt = $pdo->prepare(
                    'SELECT es.*, e.name_en AS exam_name_en, e.name_ar AS exam_name_ar, e.max_score,
                            s.name_en AS subject_name_en, s.name_ar AS subject_name_ar
                     FROM exam_scores es
                     JOIN exams e ON e.id = es.exam_id
                     JOIN subjects s ON s.id = es.subject_id
                     WHERE es.student_id = :s_id AND e.academic_year_id = :y_id
                     ORDER BY e.created_at DESC'
                );
                $scStmt->execute(['s_id' => $selectedStudentId, 'y_id' => $activeYearId]);
                // Percentage and band come from the one shared calculator, so a
                // parent sees the same grade their child's report card prints.
                $scores = $this->calculator->decorateRows($scStmt->fetchAll() ?: []);

                // Attendance summary
                $attStmt = $pdo->prepare(
                    'SELECT status, COUNT(*) as cnt
                     FROM attendance_records
                     WHERE student_id = :s_id AND academic_year_id = :y_id
                     GROUP BY status'
                );
                $attStmt->execute(['s_id' => $selectedStudentId, 'y_id' => $activeYearId]);
                $attRows = $attStmt->fetchAll() ?: [];
                $attendanceStats = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0];
                foreach ($attRows as $ar) {
                    $attendanceStats[$ar['status']] = (int) $ar['cnt'];
                    $attendanceStats['total'] += (int) $ar['cnt'];
                }

                $classId = $this->classIdForStudent($selectedStudentId, $activeYearId);
                if ($classId !== null) {
                    $assessments = $this->assessmentsForClasses($activeYearId, [$classId]);
                }
            }
        }

        $this->view('portal/family', [
            'guardians'       => $guardians,
            'guardian'        => $guardian,
            'children'        => $children,
            'student'         => $student,
            'scores'          => $scores,
            'assessments'     => $assessments,
            'dayLabels'       => (new TimetableService())->getDayLabels(),
            'attendanceStats' => $attendanceStats,
        ]);
    }

    /**
     * Student Portal: Personal schedule, exam results, attendance.
     */
    public function student(Request $request): void
    {
        $students = (new StudentRepository())->all();
        $selectedStudentId = $request->query('student_id') ? (int) $request->query('student_id') : ($students[0]['id'] ?? 0);
        $student = $selectedStudentId > 0 ? (new StudentRepository())->find($selectedStudentId) : null;

        $activeYearId = AcademicYearContext::activeYearId();
        $scores = [];
        $timetable = [];
        $assessments = [];

        if ($student !== null && $activeYearId !== null) {
            $pdo = Database::connection();
            $scStmt = $pdo->prepare(
                'SELECT es.*, e.name_en AS exam_name_en, e.name_ar AS exam_name_ar, e.max_score,
                        s.name_en AS subject_name_en, s.name_ar AS subject_name_ar
                 FROM exam_scores es
                 JOIN exams e ON e.id = es.exam_id
                 JOIN subjects s ON s.id = es.subject_id
                 WHERE es.student_id = :s_id AND e.academic_year_id = :y_id
                 ORDER BY e.created_at DESC'
            );
            $scStmt->execute(['s_id' => $student['id'], 'y_id' => $activeYearId]);
            $scores = $this->calculator->decorateRows($scStmt->fetchAll() ?: []);

            $classId = $this->classIdForStudent((int) $student['id'], $activeYearId);
            if ($classId !== null) {
                $assessments = $this->assessmentsForClasses($activeYearId, [$classId]);
            }
        }

        $this->view('portal/student', [
            'students'    => $students,
            'student'     => $student,
            'scores'      => $scores,
            'timetable'   => $timetable,
            'assessments' => $assessments,
            'dayLabels'   => (new TimetableService())->getDayLabels(),
        ]);
    }

    /**
     * The weekly assessment distribution for the classes a person belongs to.
     * It is not the timetable: a subject can be assessed on a day it is not
     * taught, which is exactly what families and teachers keep asking about.
     *
     * @param list<int> $classIds
     * @return list<array<string,mixed>>
     */
    private function assessmentsForClasses(int $yearId, array $classIds): array
    {
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        if ($classIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT a.*, c.name AS class_name
             FROM assessment_schedules a
             JOIN classes c ON c.id = a.class_id
             WHERE a.academic_year_id = ? AND a.class_id IN ({$placeholders})
             ORDER BY a.day_of_week ASC, a.sequence ASC, c.name ASC"
        );
        $stmt->execute([$yearId, ...$classIds]);

        return $stmt->fetchAll() ?: [];
    }

    private function classIdForStudent(int $studentId, int $yearId): ?int
    {
        $stmt = Database::connection()->prepare(
            "SELECT class_id FROM student_enrollments
             WHERE student_id = :student AND academic_year_id = :year AND status = 'active'
               AND class_id IS NOT NULL LIMIT 1"
        );
        $stmt->execute(['student' => $studentId, 'year' => $yearId]);
        $classId = $stmt->fetchColumn();

        return $classId !== false ? (int) $classId : null;
    }
}

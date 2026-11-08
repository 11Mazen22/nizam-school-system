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

final class PortalController extends Controller
{
    /**
     * Teacher Workspace: Assigned classes, today's timetable periods, recent attendance.
     */
    public function teacher(Request $request): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $activeYearId = AcademicYearContext::id();

        // Find teacher row linked to user or email/name
        $teacher = (new TeacherRepository())->all()[0] ?? null; // default to first teacher if not mapped
        $assignments = [];
        $todayTimetable = [];

        if ($teacher !== null && $activeYearId !== null) {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT ta.*, c.name AS class_name, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar,
                        s.name_en AS subject_name_en, s.name_ar AS subject_name_ar
                 FROM teacher_assignments ta
                 JOIN classes c ON c.id = ta.class_id
                 JOIN grades g ON g.id = c.grade_id
                 JOIN subjects s ON s.id = ta.subject_id
                 WHERE ta.teacher_id = :t_id AND ta.academic_year_id = :y_id AND ta.status = "active"'
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
        }

        $this->view('portal/teacher', [
            'teacher'        => $teacher,
            'assignments'    => $assignments,
            'todayTimetable' => $todayTimetable,
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
        $attendanceStats = null;

        if ($selectedStudentId > 0) {
            $student = (new StudentRepository())->find($selectedStudentId);
            $activeYearId = AcademicYearContext::id();

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
                $scores = $scStmt->fetchAll() ?: [];

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
            }
        }

        $this->view('portal/family', [
            'guardians'       => $guardians,
            'guardian'        => $guardian,
            'children'        => $children,
            'student'         => $student,
            'scores'          => $scores,
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

        $activeYearId = AcademicYearContext::id();
        $scores = [];
        $timetable = [];

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
            $scores = $scStmt->fetchAll() ?: [];
        }

        $this->view('portal/student', [
            'students'  => $students,
            'student'   => $student,
            'scores'    => $scores,
            'timetable' => $timetable,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Nizam -- student_enrollments (§D "why student_enrollments exists", §I.1
 * promotion, §I.9 reassignment, §S-7 immutability). Every write here is one
 * step of a workflow §I already specifies exactly -- this class has no
 * business logic of its own beyond the SQL, per §Q "all SQL lives in
 * repositories, services never construct queries directly."
 */
final class EnrollmentRepository
{
    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM student_enrollments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** The student's enrollment row in one specific year (there is at most one, per uq_enrollments_student_year). */
    public function forStudentInYear(int $studentId, int $academicYearId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM student_enrollments WHERE student_id = :sid AND academic_year_id = :year'
        );
        $stmt->execute(['sid' => $studentId, 'year' => $academicYearId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> every enrollment a student has ever had, newest year first -- the View screen's history. */
    public function historyForStudent(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*, ay.label AS year_label, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar,
                    c.name AS class_name
             FROM student_enrollments e
             JOIN academic_years ay ON ay.id = e.academic_year_id
             JOIN grades g ON g.id = e.grade_id
             LEFT JOIN classes c ON c.id = e.class_id
             WHERE e.student_id = :sid
             ORDER BY ay.start_date DESC'
        );
        $stmt->execute(['sid' => $studentId]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> active enrollments in a year, joined for the Students list / promotion preview. */
    public function activeInYear(int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT e.*, s.full_name, s.student_code,
                    g.name_en AS grade_name_en, g.name_ar AS grade_name_ar, g.sort_order,
                    c.name AS class_name
             FROM student_enrollments e
             JOIN students s ON s.id = e.student_id
             JOIN grades g ON g.id = e.grade_id
             LEFT JOIN classes c ON c.id = e.class_id
             WHERE e.academic_year_id = :year AND e.status = 'active'
             ORDER BY g.sort_order ASC, c.name ASC, s.full_name ASC"
        );
        $stmt->execute(['year' => $academicYearId]);
        return $stmt->fetchAll();
    }

    /** §S-3 close-guard: how many enrollments in this year still block closing it. */
    public function countActiveInYear(int $academicYearId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM student_enrollments WHERE academic_year_id = :year AND status = 'active'"
        );
        $stmt->execute(['year' => $academicYearId]);
        return (int) $stmt->fetchColumn();
    }

    /** A brand-new student's first enrollment (StudentController::store). */
    public function createInitial(int $studentId, int $academicYearId, int $gradeId, ?int $classId, string $enrollmentDate): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO student_enrollments (student_id, academic_year_id, grade_id, class_id, enrollment_date, status)
             VALUES (:sid, :year, :grade, :class, :date, 'active')"
        );
        $stmt->execute([
            'sid' => $studentId, 'year' => $academicYearId, 'grade' => $gradeId,
            'class' => $classId, 'date' => $enrollmentDate,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** §I.1 step 7: the promoted/repeated/graduated/transferred/withdrawn row this student's enrollment closes to. */
    public function closeStatus(int $enrollmentId, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE student_enrollments SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $enrollmentId]);
    }

    /** §I.1 step 7: the new target-year row for a promoted/repeated student, linked back via previous_enrollment_id. */
    public function createFromPromotion(
        int $studentId,
        int $academicYearId,
        int $gradeId,
        ?int $classId,
        int $previousEnrollmentId,
        string $enrollmentDate
    ): int {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO student_enrollments (student_id, academic_year_id, grade_id, class_id, enrollment_date, status, previous_enrollment_id)
             VALUES (:sid, :year, :grade, :class, :date, 'active', :prev)"
        );
        $stmt->execute([
            'sid' => $studentId, 'year' => $academicYearId, 'grade' => $gradeId, 'class' => $classId,
            'date' => $enrollmentDate, 'prev' => $previousEnrollmentId,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /** §I.9 reassignment: the only routine write to an active enrollment's class_id outside promotion. Composite FK (§S-1) rejects a cross-grade/year target at the database. */
    public function reassignClass(int $enrollmentId, int $newClassId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE student_enrollments SET class_id = :class WHERE id = :id AND status = 'active'"
        );
        $stmt->execute(['class' => $newClassId, 'id' => $enrollmentId]);
    }

    /** §I.1 step 9 (Undo promotion): the source-year row reopens to active. */
    public function reopenToActive(int $enrollmentId): void
    {
        $stmt = Database::connection()->prepare("UPDATE student_enrollments SET status = 'active' WHERE id = :id");
        $stmt->execute(['id' => $enrollmentId]);
    }

    /** §I.1 step 9 (Undo promotion): the target-year row Undo produced is deleted outright -- it never should have existed. */
    public function deleteRow(int $enrollmentId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM student_enrollments WHERE id = :id');
        $stmt->execute(['id' => $enrollmentId]);
    }

    /**
     * §K.1 Religion statistics: one row per grade+class, active enrollments
     * only, counted with conditional SUM (a single GROUP BY pass -- never a
     * per-religion COUNT query, which would risk the rows disagreeing with
     * each other if the underlying data changed between queries). Sorted by
     * grade sort_order then class name -- "pedagogical order, not
     * alphabetical" per §K.1's own sort rule.
     *
     * @return array<int, array{grade_id:int, grade_name_en:string, grade_name_ar:string, class_id:?int, class_name:?string, muslim:int, christian:int, other:int, total:int}>
     */
    public function religionBreakdownByGradeClass(int $academicYearId, ?int $gradeId, ?int $classId): array
    {
        // MySQL coerces a boolean expression to 1/0 inside SUM(); Postgres
        // has no such coercion (SubjectStaffingRequirementRepository's note
        // on the same pattern) -- COUNT(*) FILTER (WHERE ...) is the
        // Postgres-native equivalent for a conditional count.
        $isPgsql = Database::connection()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $muslimExpr = $isPgsql ? "COUNT(*) FILTER (WHERE s.religion = 'muslim')" : "SUM(s.religion = 'muslim')";
        $christianExpr = $isPgsql ? "COUNT(*) FILTER (WHERE s.religion = 'christian')" : "SUM(s.religion = 'christian')";
        $otherExpr = $isPgsql ? "COUNT(*) FILTER (WHERE s.religion = 'other')" : "SUM(s.religion = 'other')";

        $sql = "SELECT g.id AS grade_id, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar,
                       c.id AS class_id, c.name AS class_name,
                       {$muslimExpr} AS muslim,
                       {$christianExpr} AS christian,
                       {$otherExpr} AS other,
                       COUNT(*) AS total
                FROM student_enrollments e
                JOIN students s ON s.id = e.student_id
                JOIN grades g ON g.id = e.grade_id
                LEFT JOIN classes c ON c.id = e.class_id
                WHERE e.academic_year_id = :year AND e.status = 'active'";
        $params = ['year' => $academicYearId];
        if ($gradeId !== null) {
            $sql .= ' AND e.grade_id = :grade';
            $params['grade'] = $gradeId;
        }
        if ($classId !== null) {
            $sql .= ' AND e.class_id = :class';
            $params['class'] = $classId;
        }
        $sql .= ' GROUP BY g.id, g.name_en, g.name_ar, c.id, c.name ORDER BY g.sort_order ASC, c.name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * §K.2 Class student list: one specific class's active roster.
     * $sortBy is validated by the caller (ReportService) against the exact
     * two values §K.2 offers -- 'name' or 'code' -- before it ever reaches
     * SQL, so this never interpolates an unvalidated column name.
     *
     * @return array<int, array{student_code:string, full_name:string, religion:string, date_of_birth:string, guardian_phone:?string}>
     */
    public function classRoster(int $classId, string $sortBy): array
    {
        $orderColumn = $sortBy === 'code' ? 's.student_code' : 's.full_name';
        $stmt = Database::connection()->prepare(
            "SELECT s.student_code, s.full_name, s.religion, s.date_of_birth, s.guardian_phone
             FROM student_enrollments e
             JOIN students s ON s.id = e.student_id
             WHERE e.class_id = :class AND e.status = 'active'
             ORDER BY {$orderColumn} ASC"
        );
        $stmt->execute(['class' => $classId]);
        return $stmt->fetchAll();
    }

    /**
     * Active students enrolled in a specific class, with student details.
     * Used to populate the attendance mark sheet.
     * @return array<int, array<string,mixed>>
     */
    public function activeStudentsForClass(int $classId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT s.id, s.student_code, s.full_name
             FROM student_enrollments e
             JOIN students s ON s.id = e.student_id
             WHERE e.class_id = :class AND e.status = 'active'
             ORDER BY s.full_name ASC"
        );
        $stmt->execute(['class' => $classId]);
        return $stmt->fetchAll();
    }

    /**
     * Current enrollment for a student in the active year — used by StudentController show.
     */
    public function currentEnrollmentFor(int $studentId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT e.*, ay.label AS year_label, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar, c.name AS class_name
             FROM student_enrollments e
             JOIN academic_years ay ON ay.id = e.academic_year_id
             JOIN grades g ON g.id = e.grade_id
             LEFT JOIN classes c ON c.id = e.class_id
             WHERE e.student_id = :sid AND e.status = 'active'
             ORDER BY e.id DESC LIMIT 1"
        );
        $stmt->execute(['sid' => $studentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Full enrollment history for a student — show page.
     */
    public function historyFor(int $studentId): array
    {
        return $this->historyForStudent($studentId);
    }
}



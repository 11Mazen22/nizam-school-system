<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class TeacherAssignmentRepository
{
    /** @return array<int, array<string, mixed>> every assignment in a year, joined for display */
    public function allForYear(int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.*, t.full_name AS teacher_name, s.name_en AS subject_name_en, s.name_ar AS subject_name_ar,
                    c.name AS class_name, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar
             FROM teacher_assignments a
             JOIN teachers t ON t.id = a.teacher_id
             JOIN subjects s ON s.id = a.subject_id
             JOIN classes c ON c.id = a.class_id
             JOIN grades g ON g.id = c.grade_id
             WHERE a.academic_year_id = :year AND a.status = 'active'
             ORDER BY g.sort_order ASC, c.name ASC, s.name_en ASC"
        );
        $stmt->execute(['year' => $academicYearId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM teacher_assignments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return int[] ids of this teacher's active assignments in one year -- the narrow lookup TeacherService::archive() needs, without allForYear()'s display joins. */
    public function activeIdsForTeacherInYear(int $teacherId, int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id FROM teacher_assignments
             WHERE teacher_id = :t AND academic_year_id = :y AND status = 'active'"
        );
        $stmt->execute(['t' => $teacherId, 'y' => $academicYearId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function existsForTuple(int $teacherId, int $subjectId, int $classId, int $academicYearId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM teacher_assignments
             WHERE teacher_id = :t AND subject_id = :s AND class_id = :c AND academic_year_id = :y'
        );
        $stmt->execute(['t' => $teacherId, 's' => $subjectId, 'c' => $classId, 'y' => $academicYearId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function create(int $teacherId, int $subjectId, int $classId, int $academicYearId, int $weeklyPeriods): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO teacher_assignments (teacher_id, subject_id, class_id, academic_year_id, weekly_periods, status)
             VALUES (:t, :s, :c, :y, :w, \'active\')'
        );
        $stmt->execute(['t' => $teacherId, 's' => $subjectId, 'c' => $classId, 'y' => $academicYearId, 'w' => $weeklyPeriods]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, int $weeklyPeriods): void
    {
        $stmt = Database::connection()->prepare('UPDATE teacher_assignments SET weekly_periods = :w WHERE id = :id');
        $stmt->execute(['w' => $weeklyPeriods, 'id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE teacher_assignments SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** §I.5: SUM(weekly_periods) for one teacher in one year, active only. */
    public function totalWeeklyPeriods(int $teacherId, int $academicYearId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(weekly_periods), 0) FROM teacher_assignments
             WHERE teacher_id = :t AND academic_year_id = :y AND status = 'active'"
        );
        $stmt->execute(['t' => $teacherId, 'y' => $academicYearId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array{class_id:int, class_name:string, subject_id:int, subject_name_en:string, subject_name_ar:string, weekly_periods:int}> §I.5 "also grouped by subject and by class" -- one teacher's own assignment detail rows. */
    public function detailForTeacher(int $teacherId, int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.id AS class_id, c.name AS class_name,
                    s.id AS subject_id, s.name_en AS subject_name_en, s.name_ar AS subject_name_ar,
                    a.weekly_periods
             FROM teacher_assignments a
             JOIN classes c ON c.id = a.class_id
             JOIN subjects s ON s.id = a.subject_id
             WHERE a.teacher_id = :t AND a.academic_year_id = :y AND a.status = 'active'
             ORDER BY s.name_en ASC, c.name ASC"
        );
        $stmt->execute(['t' => $teacherId, 'y' => $academicYearId]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array{teacher_id:int, full_name:string, total:int}> every active teacher's total in a year, for the workload summary (§I.5, §K). */
    public function workloadSummaryForYear(int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.id AS teacher_id, t.full_name,
                    COALESCE(SUM(a.weekly_periods), 0) AS total
             FROM teachers t
             LEFT JOIN teacher_assignments a
               ON a.teacher_id = t.id AND a.academic_year_id = :year AND a.status = 'active'
             WHERE t.status = 'active'
             GROUP BY t.id, t.full_name
             ORDER BY t.full_name ASC"
        );
        $stmt->execute(['year' => $academicYearId]);
        return $stmt->fetchAll();
    }

    /** §I.6: per subject, COUNT(DISTINCT teacher_id) actively assigned in the year. */
    public function distinctTeacherCountForSubject(int $subjectId, int $academicYearId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(DISTINCT teacher_id) FROM teacher_assignments
             WHERE subject_id = :s AND academic_year_id = :y AND status = 'active'"
        );
        $stmt->execute(['s' => $subjectId, 'y' => $academicYearId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * §K.4 Teachers by subject: per subject, how many distinct teachers and
     * assignments cover it and the total weekly periods. COUNT(DISTINCT
     * teacher_id) specifically -- a subject/teacher pair with more than one
     * assignment (different classes) must not inflate the teacher count.
     * Only subjects with at least one active assignment appear -- a subject
     * nobody teaches yet has nothing to report here (§K.4 doesn't ask for
     * "0 teachers" rows the way §K.6's shortage report deliberately does).
     *
     * @return array<int, array{subject_id:int, subject_name_en:string, subject_name_ar:string, teacher_count:int, assignment_count:int, total_periods:int}>
     */
    public function bySubjectForYear(int $academicYearId, ?int $subjectId): array
    {
        $sql = "SELECT s.id AS subject_id, s.name_en AS subject_name_en, s.name_ar AS subject_name_ar,
                       COUNT(DISTINCT a.teacher_id) AS teacher_count,
                       COUNT(a.id) AS assignment_count,
                       COALESCE(SUM(a.weekly_periods), 0) AS total_periods
                FROM teacher_assignments a
                JOIN subjects s ON s.id = a.subject_id
                WHERE a.academic_year_id = :year AND a.status = 'active'";
        $params = ['year' => $academicYearId];
        if ($subjectId !== null) {
            $sql .= ' AND a.subject_id = :subject';
            $params['subject'] = $subjectId;
        }
        $sql .= ' GROUP BY s.id, s.name_en, s.name_ar ORDER BY teacher_count DESC, s.name_en ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['teacher_count'] = (int) $row['teacher_count'];
            $row['assignment_count'] = (int) $row['assignment_count'];
            $row['total_periods'] = (int) $row['total_periods'];
        }
        unset($row);
        return $rows;
    }

    /**
     * §K.5 Teacher workload report: one row per active teacher (LEFT JOIN --
     * a teacher with zero assignments still appears, matching §I.5's own
     * "SUM ... across that teacher's assignments" reading of an empty set as
     * zero, not an omission), with a GROUP_CONCAT'd subject list for the
     * "Subject(s)" column -- §K.5 asks for the subjects on one line, not a
     * second query per teacher (N+1). $subjectId, when given, filters which
     * teachers appear (only those with an active assignment in that
     * subject) without narrowing the "Subject(s)" text itself to just that
     * one subject -- the report still shows a teacher's whole load.
     *
     * @return array<int, array{teacher_id:int, full_name:string, subjects_en:string, subjects_ar:string, total_periods:int}>
     */
    public function workloadReportForYear(int $academicYearId, ?int $subjectId): array
    {
        $pdo = Database::connection();
        $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';

        $params = ['year' => $academicYearId];
        if ($subjectId !== null) {
            // MySQL's SUM(bool-expr) relies on its implicit boolean-as-integer
            // coercion, which Postgres does not have (a plain comparison is a
            // genuine boolean there, and SUM() rejects it outright) --
            // COUNT(*) FILTER (WHERE ...) is the direct Postgres-native
            // equivalent of "count of rows where this condition holds."
            $havingSubject = $isPgsql
                ? 'HAVING COUNT(*) FILTER (WHERE a.subject_id = :subject) > 0'
                : 'HAVING SUM(a.subject_id = :subject) > 0';
            $params['subject'] = $subjectId;
        } else {
            $havingSubject = '';
        }

        // subjects_en/subjects_ar, not one locale baked in here -- a
        // repository has no business knowing which language is viewing;
        // the view picks per §C's currentLocale(), same as every other
        // bilingual field this class and its siblings already return.
        //
        // GROUP_CONCAT has no direct Postgres equivalent (STRING_AGG);
        // Postgres's aggregate DISTINCT additionally requires ORDER BY to
        // match the aggregated expression itself, so subjects_ar orders by
        // name_ar there rather than reusing MySQL's "always sort by the
        // English name" -- alphabetizing the Arabic list by its own Arabic
        // text is the more correct behavior anyway, not just a workaround.
        $subjectsEn = $isPgsql
            ? "STRING_AGG(DISTINCT s.name_en, ', ' ORDER BY s.name_en)"
            : "GROUP_CONCAT(DISTINCT s.name_en ORDER BY s.name_en SEPARATOR ', ')";
        $subjectsAr = $isPgsql
            ? "STRING_AGG(DISTINCT s.name_ar, '، ' ORDER BY s.name_ar)"
            : "GROUP_CONCAT(DISTINCT s.name_ar ORDER BY s.name_en SEPARATOR '، ')";

        $sql = "SELECT t.id AS teacher_id, t.full_name,
                       {$subjectsEn} AS subjects_en,
                       {$subjectsAr} AS subjects_ar,
                       COALESCE(SUM(a.weekly_periods), 0) AS total_periods
                FROM teachers t
                LEFT JOIN teacher_assignments a
                  ON a.teacher_id = t.id AND a.academic_year_id = :year AND a.status = 'active'
                LEFT JOIN subjects s ON s.id = a.subject_id
                WHERE t.status = 'active'
                GROUP BY t.id, t.full_name
                {$havingSubject}
                ORDER BY total_periods DESC, t.full_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['total_periods'] = (int) $row['total_periods'];
            $row['subjects_en'] = $row['subjects_en'] ?? '';
            $row['subjects_ar'] = $row['subjects_ar'] ?? '';
        }
        unset($row);
        return $rows;
    }

    /**
     * §I.4/§O-11 rollover: clone every active assignment from the outgoing year
     * onto the matching new class (via $classMap from ClassRepository::cloneActiveForRollover()).
     * An assignment whose old class wasn't cloned (e.g. was inactive) is skipped.
     *
     * @param array<int,int> $classMap old_class_id => new_class_id
     */
    public function cloneActiveForRollover(int $fromYearId, int $toYearId, array $classMap): void
    {
        $pdo = Database::connection();
        $source = $pdo->prepare(
            "SELECT teacher_id, subject_id, class_id, weekly_periods FROM teacher_assignments
             WHERE academic_year_id = :year AND status = 'active'"
        );
        $source->execute(['year' => $fromYearId]);

        $insert = $pdo->prepare(
            'INSERT INTO teacher_assignments (teacher_id, subject_id, class_id, academic_year_id, weekly_periods, status)
             VALUES (:t, :s, :c, :y, :w, \'active\')'
        );

        foreach ($source->fetchAll() as $row) {
            $newClassId = $classMap[(int) $row['class_id']] ?? null;
            if ($newClassId === null) {
                continue;
            }
            $insert->execute([
                't' => $row['teacher_id'], 's' => $row['subject_id'], 'c' => $newClassId,
                'y' => $toYearId, 'w' => $row['weekly_periods'],
            ]);
        }
    }
}

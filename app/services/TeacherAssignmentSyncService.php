<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use Throwable;

/**
 * Nizam -- derive staff teaching load from the loaded timetable.
 *
 * The official package prints the timetable, not an assignment register. A
 * class/subject/teacher triple that appears in the grid is evidence that the
 * teacher teaches that subject to that class, and the number of its weekly
 * cells is the teacher's weekly load for it. Without this pass the timetable
 * is full while /assignments, the workload screen and the teacher workspace
 * show nothing at all.
 *
 * The pass only adds what the grid proves:
 *  - an assignment row per distinct (teacher, subject, class) with
 *    weekly_periods = the number of printed cells;
 *  - the matching teacher_subjects link, marked 'timetable'. That table also
 *    holds declared qualifications, and only 'declared' rows satisfy the
 *    assignment warning, so teaching evidence never poses as a qualification.
 * It never deletes an assignment the school created itself, it does not touch
 * a row's status (an assignment archived on purpose stays archived), and it
 * never overwrites weekly periods the school edited by hand. An assignment is
 * either 'manual' (created or edited on the assignment screen) or 'timetable'
 * (derived here); only the derived rows are corrected when the grid changes.
 *
 * Idempotent: a second run finds the same triples and changes nothing.
 */
final class TeacherAssignmentSyncService
{
    /**
     * @return array{created:int,updated:int,unchanged:int,manual_kept:int,subjects_linked:int,classes:int,dry_run:bool}
     */
    public function sync(int $academicYearId, bool $dryRun = false): array
    {
        $pdo = Database::connection();
        $result = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'manual_kept' => 0,
            'subjects_linked' => 0,
            'classes' => 0,
            'dry_run' => $dryRun,
        ];

        $stmt = $pdo->prepare(
            'SELECT teacher_id, subject_id, class_id, COUNT(*) AS lessons
             FROM timetables
             WHERE academic_year_id = :year AND teacher_id IS NOT NULL
             GROUP BY teacher_id, subject_id, class_id
             ORDER BY class_id, teacher_id, subject_id'
        );
        $stmt->execute(['year' => $academicYearId]);
        $triples = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $existing = [];
        $stmt = $pdo->prepare(
            'SELECT id, teacher_id, subject_id, class_id, weekly_periods, weekly_periods_source
             FROM teacher_assignments WHERE academic_year_id = :year'
        );
        $stmt->execute(['year' => $academicYearId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $row['teacher_id'] . ':' . $row['subject_id'] . ':' . $row['class_id'];
            $existing[$key] = $row;
        }

        $linked = [];
        foreach ($pdo->query('SELECT teacher_id, subject_id, source FROM teacher_subjects')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $linked[$row['teacher_id'] . ':' . $row['subject_id']] = (string) $row['source'];
        }

        $insert = $pdo->prepare(
            "INSERT INTO teacher_assignments (teacher_id, subject_id, class_id, academic_year_id, weekly_periods, weekly_periods_source, status)
             VALUES (:teacher, :subject, :class, :year, :periods, 'timetable', 'active')"
        );
        $update = $pdo->prepare(
            'UPDATE teacher_assignments SET weekly_periods = :periods, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $link = $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id, source) VALUES (:teacher, :subject, 'timetable')");

        $classes = [];
        if ($triples !== [] && !$dryRun) {
            $pdo->beginTransaction();
        }

        try {
            foreach ($triples as $triple) {
                $teacherId = (int) $triple['teacher_id'];
                $subjectId = (int) $triple['subject_id'];
                $classId = (int) $triple['class_id'];
                $periods = (int) $triple['lessons'];
                $classes[$classId] = true;

                $key = $teacherId . ':' . $subjectId . ':' . $classId;
                $row = $existing[$key] ?? null;

                if ($row === null) {
                    $result['created']++;
                    if (!$dryRun) {
                        $insert->execute([
                            'teacher' => $teacherId,
                            'subject' => $subjectId,
                            'class' => $classId,
                            'year' => $academicYearId,
                            'periods' => $periods,
                        ]);
                    }
                } elseif ((int) $row['weekly_periods'] !== $periods) {
                    if ((string) ($row['weekly_periods_source'] ?? 'manual') === 'timetable') {
                        $result['updated']++;
                        if (!$dryRun) {
                            $update->execute(['periods' => $periods, 'id' => (int) $row['id']]);
                        }
                    } else {
                        // Hand-entered or hand-corrected load: the school's
                        // number wins over the grid's cell count.
                        $result['manual_kept']++;
                    }
                } else {
                    $result['unchanged']++;
                }

                $linkKey = $teacherId . ':' . $subjectId;
                if (!isset($linked[$linkKey])) {
                    $result['subjects_linked']++;
                    if (!$dryRun) {
                        $link->execute(['teacher' => $teacherId, 'subject' => $subjectId]);
                        $linked[$linkKey] = 'timetable';
                    }
                }
            }

            if ($triples !== [] && !$dryRun) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if (!$dryRun && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $result['classes'] = count($classes);

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class TimetableRepository
{
    /**
     * Full timetable grid for one class, keyed [dayOfWeek][periodNumber].
     * @return array<int, array<int, array<string,mixed>>>
     */
    public function gridForClass(int $classId, int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.*, sub.name_en AS subject_en, sub.name_ar AS subject_ar,
                    tea.full_name AS teacher_name
             FROM timetables t
             JOIN subjects  sub ON sub.id = t.subject_id
             LEFT JOIN teachers tea ON tea.id = t.teacher_id
             WHERE t.class_id = :class AND t.academic_year_id = :year
             ORDER BY t.day_of_week ASC, t.period_number ASC'
        );
        $stmt->execute(['class' => $classId, 'year' => $yearId]);
        $grid = [];
        foreach ($stmt->fetchAll() as $row) {
            $grid[(int)$row['day_of_week']][(int)$row['period_number']] = $row;
        }
        return $grid;
    }

    /**
     * All timetable rows for a class — for display/export.
     * @return array<int, array<string,mixed>>
     */
    public function rowsForClass(int $classId, int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.*, sub.name_en AS subject_en, sub.name_ar AS subject_ar,
                    tea.full_name AS teacher_name
             FROM timetables t
             JOIN subjects sub ON sub.id = t.subject_id
             LEFT JOIN teachers tea ON tea.id = t.teacher_id
             WHERE t.class_id = :class AND t.academic_year_id = :year
             ORDER BY t.day_of_week ASC, t.period_number ASC'
        );
        $stmt->execute(['class' => $classId, 'year' => $yearId]);
        return $stmt->fetchAll();
    }

    /**
     * Every slot a teacher actually teaches, with the class it belongs to --
     * the teacher's own timetable document. One query for the whole week
     * rather than one per class.
     *
     * @return array<int, array<string,mixed>>
     */
    public function rowsForTeacher(int $teacherId, int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.*, sub.name_en AS subject_en, sub.name_ar AS subject_ar,
                    tea.full_name AS teacher_name, c.name AS class_name,
                    g.name_en AS grade_name_en, g.name_ar AS grade_name_ar
             FROM timetables t
             JOIN subjects sub ON sub.id = t.subject_id
             JOIN teachers tea ON tea.id = t.teacher_id
             JOIN classes  c   ON c.id  = t.class_id
             JOIN grades   g   ON g.id  = c.grade_id
             WHERE t.teacher_id = :teacher AND t.academic_year_id = :year
             ORDER BY t.day_of_week ASC, t.period_number ASC'
        );
        $stmt->execute(['teacher' => $teacherId, 'year' => $yearId]);
        return $stmt->fetchAll();
    }

    /** The highest period actually used in the year, 0 when the year is empty. */
    public function maxPeriodForYear(int $yearId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT MAX(period_number) FROM timetables WHERE academic_year_id = :year'
        );
        $stmt->execute(['year' => $yearId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** Check for teacher conflict: same teacher at same day+period in the same year. */
    public function teacherSlotTaken(int $yearId, int $teacherId, int $day, int $period, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT id FROM timetables WHERE academic_year_id=:year AND teacher_id=:teacher AND day_of_week=:day AND period_number=:period';
        $params = ['year' => $yearId, 'teacher' => $teacherId, 'day' => $day, 'period' => $period];
        if ($excludeId !== null) { $sql .= ' AND id != :excl'; $params['excl'] = $excludeId; }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    /** Check for class slot conflict: same class at same day+period. */
    public function classSlotTaken(int $yearId, int $classId, int $day, int $period, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT id FROM timetables WHERE academic_year_id=:year AND class_id=:class AND day_of_week=:day AND period_number=:period';
        $params = ['year' => $yearId, 'class' => $classId, 'day' => $day, 'period' => $period];
        if ($excludeId !== null) { $sql .= ' AND id != :excl'; $params['excl'] = $excludeId; }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * A lesson may legitimately have no teacher: the printed grid schedules
     * activity periods (sports, financial literacy) without one, and inventing
     * a staff member for them would be worse than leaving the field honest.
     *
     * `origin` records whether the cell was imported from the printed grid or
     * entered/edited by a person. A manual cell sets manual_override so a
     * later re-import preserves it instead of silently replacing the school's
     * correction.
     */
    public function create(
        int $yearId,
        int $classId,
        int $day,
        int $period,
        int $subjectId,
        ?int $teacherId,
        ?string $note = null,
        ?string $printedTeacher = null,
        string $origin = 'manual',
        ?string $importSource = null,
        ?int $userId = null,
    ): int {
        $pdo  = Database::connection();
        $imported = $origin === 'imported';
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO timetables
                (academic_year_id, class_id, day_of_week, period_number, subject_id, teacher_id,
                 note, printed_teacher, origin, import_source, imported_at, manual_override,
                 manual_edited_at, manual_edited_by)
             VALUES (:year, :class, :day, :period, :sub, :teacher, :note, :printed,
                     :origin, :source, :imported_at, :override, :edited_at, :edited_by)'
        );
        $stmt->execute([
            'year' => $yearId, 'class' => $classId, 'day' => $day,
            'period' => $period, 'sub' => $subjectId, 'teacher' => $teacherId,
            'note' => $note, 'printed' => $printedTeacher,
            'origin' => $imported ? 'imported' : 'manual',
            'source' => $imported ? $importSource : null,
            'imported_at' => $imported ? $now : null,
            'override' => $imported ? 0 : 1,
            'edited_at' => $imported ? null : $now,
            'edited_by' => $imported ? null : $userId,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * A manual edit is the school resolving the slot. The printed name stays
     * where it was -- it is the evidence the school compared against -- but
     * the row now has a real, uniquely booked teacher and is marked manual so
     * a re-import will not overwrite it.
     */
    public function update(int $id, int $subjectId, int $teacherId, ?int $userId = null): void
    {
        Database::connection()->prepare(
            "UPDATE timetables
                SET subject_id = :sub, teacher_id = :teacher, origin = 'manual',
                    manual_override = 1, manual_edited_at = CURRENT_TIMESTAMP,
                    manual_edited_by = :user
              WHERE id = :id"
        )->execute(['sub' => $subjectId, 'teacher' => $teacherId, 'user' => $userId, 'id' => $id]);
    }

    /**
     * Settle a printed clash: keep printed_teacher verbatim, attach the real
     * teacher who is free in this period, and record who made the decision.
     */
    public function markClashResolved(int $id, int $teacherId, ?int $userId, ?string $note): void
    {
        Database::connection()->prepare(
            "UPDATE timetables
                SET teacher_id = :teacher, origin = 'manual', manual_override = 1,
                    manual_edited_at = CURRENT_TIMESTAMP, manual_edited_by = :user,
                    clash_resolved_at = CURRENT_TIMESTAMP, clash_resolved_by = :user2,
                    clash_resolution_note = :note
              WHERE id = :id AND teacher_id IS NULL"
        )->execute([
            'teacher' => $teacherId, 'user' => $userId, 'user2' => $userId,
            'note' => $note, 'id' => $id,
        ]);
    }

    /**
     * Every unresolved printed clash in the year. A clash is a real lesson the
     * paper printed with a teacher name but the database holds no teacher_id,
     * because that teacher was already booked in another room at that time.
     *
     * @return list<array<string,mixed>>
     */
    public function printedClashes(int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.*, s.name_ar AS subject_ar, s.name_en AS subject_en,
                    c.name AS class_name, g.name_ar AS grade_name_ar, g.name_en AS grade_name_en
             FROM timetables t
             JOIN subjects s ON s.id = t.subject_id
             JOIN classes c ON c.id = t.class_id
             JOIN grades g ON g.id = c.grade_id
             WHERE t.academic_year_id = :year
               AND t.teacher_id IS NULL
               AND t.printed_teacher IS NOT NULL AND TRIM(t.printed_teacher) <> ''
             ORDER BY c.name ASC, t.day_of_week ASC, t.period_number ASC"
        );
        $stmt->execute(['year' => $yearId]);

        return $stmt->fetchAll();
    }

    public function printedClashCount(int $yearId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM timetables
             WHERE academic_year_id = :year
               AND teacher_id IS NULL
               AND printed_teacher IS NOT NULL AND TRIM(printed_teacher) <> ''"
        );
        $stmt->execute(['year' => $yearId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Which teacher is assigned to which subject/class, so the clash screen
     * can put the staff member the school already expects at the top of the
     * candidate list. Availability is still checked separately.
     *
     * @return array<int, array<int, array<int, true>>>
     */
    public function assignmentTuples(int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT teacher_id, class_id, subject_id FROM teacher_assignments
             WHERE academic_year_id = :year AND status = 'active'"
        );
        $stmt->execute(['year' => $yearId]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['teacher_id']][(int) $row['class_id']][(int) $row['subject_id']] = true;
        }

        return $map;
    }

    /** Remember that a human deliberately removed this printed cell. */
    public function excludeFromImport(int $yearId, int $classId, int $day, int $period, string $reason, ?int $userId): void
    {
        $pdo = Database::connection();
        $params = [
            'year' => $yearId, 'class' => $classId, 'day' => $day,
            'period' => $period, 'reason' => $reason, 'user' => $userId,
        ];
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->prepare(
                'INSERT INTO timetable_import_exclusions
                    (academic_year_id, class_id, day_of_week, period_number, reason, created_by)
                 VALUES (:year, :class, :day, :period, :reason, :user)
                 ON CONFLICT (academic_year_id, class_id, day_of_week, period_number)
                 DO UPDATE SET reason = EXCLUDED.reason'
            )->execute($params);
            return;
        }

        $pdo->prepare(
            'INSERT INTO timetable_import_exclusions
                (academic_year_id, class_id, day_of_week, period_number, reason, created_by)
             VALUES (:year, :class, :day, :period, :reason, :user)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
        )->execute($params);
    }

    /** @return array<string,string> class|day|period => reason */
    public function importExclusions(int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT class_id, day_of_week, period_number, reason
             FROM timetable_import_exclusions WHERE academic_year_id = :year'
        );
        $stmt->execute(['year' => $yearId]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = (int) $row['class_id'] . '|' . (int) $row['day_of_week'] . '|' . (int) $row['period_number'];
            $map[$key] = (string) $row['reason'];
        }

        return $map;
    }

    public function clearImportExclusions(int $yearId): void
    {
        Database::connection()->prepare(
            'DELETE FROM timetable_import_exclusions WHERE academic_year_id = :year'
        )->execute(['year' => $yearId]);
    }

    /** Remove only imported rows, preserving every human correction. */
    public function deleteImportedForYear(int $yearId): int
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM timetables WHERE academic_year_id = :year AND manual_override = 0'
        );
        $stmt->execute(['year' => $yearId]);

        return $stmt->rowCount();
    }

    /** Update a cell the current import owns, leaving the printed evidence alone. */
    public function updateImported(
        int $id,
        int $subjectId,
        ?int $teacherId,
        ?string $note,
        ?string $printedTeacher,
        string $importSource,
    ): void {
        Database::connection()->prepare(
            "UPDATE timetables
                SET subject_id = :sub, teacher_id = :teacher, note = :note,
                    printed_teacher = :printed, origin = 'imported',
                    import_source = :source, imported_at = CURRENT_TIMESTAMP
              WHERE id = :id AND manual_override = 0"
        )->execute([
            'sub' => $subjectId, 'teacher' => $teacherId, 'note' => $note,
            'printed' => $printedTeacher, 'source' => $importSource, 'id' => $id,
        ]);
    }

    /** @param array<string,mixed> $resolution */
    public function recordResolution(array $resolution): void
    {
        Database::connection()->prepare(
            'INSERT INTO timetable_resolutions
                (academic_year_id, timetable_id, class_id, day_of_week, period_number,
                 printed_teacher, previous_teacher_id, previous_subject_id,
                 resolved_teacher_id, resolved_subject_id, note, resolved_by, resolved_at)
             VALUES (:year, :lesson, :class, :day, :period, :printed, :previous_teacher,
                     :previous_subject, :teacher, :subject, :note, :user, CURRENT_TIMESTAMP)'
        )->execute([
            'year' => $resolution['academic_year_id'],
            'lesson' => $resolution['timetable_id'],
            'class' => $resolution['class_id'],
            'day' => $resolution['day_of_week'],
            'period' => $resolution['period_number'],
            'printed' => $resolution['printed_teacher'],
            'previous_teacher' => $resolution['previous_teacher_id'],
            'previous_subject' => $resolution['previous_subject_id'],
            'teacher' => $resolution['resolved_teacher_id'],
            'subject' => $resolution['resolved_subject_id'],
            'note' => $resolution['note'],
            'user' => $resolution['resolved_by'],
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function resolutions(int $yearId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = Database::connection()->prepare(
            "SELECT r.*, c.name AS class_name, s.name_ar AS subject_ar, s.name_en AS subject_en,
                    t.full_name AS resolved_teacher_name, u.full_name AS resolved_by_name
             FROM timetable_resolutions r
             JOIN classes c ON c.id = r.class_id
             JOIN subjects s ON s.id = r.resolved_subject_id
             JOIN teachers t ON t.id = r.resolved_teacher_id
             LEFT JOIN users u ON u.id = r.resolved_by
             WHERE r.academic_year_id = :year
             ORDER BY r.resolved_at DESC, r.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['year' => $yearId]);

        return $stmt->fetchAll();
    }

    public function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM timetables WHERE id=:id')->execute(['id' => $id]);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM timetables WHERE id=:id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Clear all slots for a class in a year — "reset timetable" action. */
    public function clearForClass(int $classId, int $yearId): void
    {
        Database::connection()->prepare(
            'DELETE FROM timetables WHERE class_id=:class AND academic_year_id=:year'
        )->execute(['class' => $classId, 'year' => $yearId]);
    }
}

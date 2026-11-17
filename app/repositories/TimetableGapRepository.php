<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Nizam -- cells the source timetable printed but the import could not load.
 *
 * Every gap keeps the printed subject and teacher text exactly as the page
 * shows it. A gap is never invented from a blank cell: a cell with no printed
 * subject is simply empty, and only printed lessons become gaps.
 */
final class TimetableGapRepository
{
    /**
     * @param array{status?:string, reason?:string, class_id?:int, teacher?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function forYear(int $yearId, array $filters = []): array
    {
        $sql = "SELECT g.*, c.name AS class_name,
                       s.name_ar AS subject_name_ar, s.name_en AS subject_name_en,
                       t.full_name AS resolved_teacher_name
                FROM timetable_gaps g
                JOIN classes c ON c.id = g.class_id
                LEFT JOIN subjects s ON s.id = g.subject_id
                LEFT JOIN teachers t ON t.id = g.resolved_teacher_id
                WHERE g.academic_year_id = :year";
        $params = ['year' => $yearId];

        if (!empty($filters['status'])) {
            $sql .= ' AND g.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['reason'])) {
            $sql .= ' AND g.reason = :reason';
            $params['reason'] = $filters['reason'];
        }
        if (!empty($filters['class_id'])) {
            $sql .= ' AND g.class_id = :class';
            $params['class'] = $filters['class_id'];
        }
        if (!empty($filters['teacher'])) {
            $sql .= ' AND g.printed_teacher LIKE :teacher';
            $params['teacher'] = '%' . $filters['teacher'] . '%';
        }

        $sql .= " ORDER BY CASE g.status WHEN 'open' THEN 0 WHEN 'resolved' THEN 1 ELSE 2 END,
                           c.name ASC, g.day_of_week ASC, g.period_number ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Every loaded lesson in the year, indexed for the gaps screen:
     * day => period => teacher => the class/subject that already holds them.
     *
     * The screen needs this to name the class a double-booked teacher is
     * already teaching, and to split the resolve list into free and busy
     * teachers for that exact period. One query for the whole year, not one
     * per gap.
     *
     * @return array<int, array<int, array<int, array{class_name:string,subject_ar:string,subject_en:string}>>>
     */
    public function bookings(int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.day_of_week, t.period_number, t.teacher_id,
                    c.name AS class_name, s.name_ar AS subject_ar, s.name_en AS subject_en
             FROM timetables t
             JOIN classes c ON c.id = t.class_id
             JOIN subjects s ON s.id = t.subject_id
             WHERE t.academic_year_id = :year'
        );
        $stmt->execute(['year' => $yearId]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['day_of_week']][(int) $row['period_number']][(int) $row['teacher_id']] = [
                'class_name' => (string) $row['class_name'],
                'subject_ar' => (string) $row['subject_ar'],
                'subject_en' => (string) $row['subject_en'],
            ];
        }

        return $map;
    }

    public function find(int $id, int $yearId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT g.*, c.name AS class_name FROM timetable_gaps g
             JOIN classes c ON c.id = g.class_id
             WHERE g.id = :id AND g.academic_year_id = :year'
        );
        $stmt->execute(['id' => $id, 'year' => $yearId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array{open:int, resolved:int, ignored:int, total:int} */
    public function counts(int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT status, COUNT(*) AS total FROM timetable_gaps
             WHERE academic_year_id = :year GROUP BY status'
        );
        $stmt->execute(['year' => $yearId]);

        $counts = ['open' => 0, 'resolved' => 0, 'ignored' => 0, 'total' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $status = (string) $row['status'];
            $counts[$status] = (int) $row['total'];
            $counts['total'] += (int) $row['total'];
        }

        return $counts;
    }

    public function upsert(
        int $yearId,
        int $classId,
        int $day,
        int $period,
        ?int $subjectId,
        string $subjectRaw,
        ?string $printedTeacher,
        string $reason,
        ?string $sourceDocument,
        string $status = 'open'
    ): void {
        $pdo = Database::connection();
        // A gap the school already resolved or ignored keeps that status when
        // the dataset is re-synced; only an open gap stays open. MySQL and
        // Postgres need different upsert syntax for the same rule.
        $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $sql = $isPgsql
            ? "INSERT INTO timetable_gaps
                    (academic_year_id, class_id, day_of_week, period_number, subject_id, subject_raw,
                     printed_teacher, reason, status, source_document)
                 VALUES (:year, :class, :day, :period, :subject, :subject_raw, :teacher, :reason, :status, :source)
                 ON CONFLICT (academic_year_id, class_id, day_of_week, period_number) DO UPDATE SET
                    subject_id = EXCLUDED.subject_id,
                    subject_raw = EXCLUDED.subject_raw,
                    printed_teacher = EXCLUDED.printed_teacher,
                    reason = EXCLUDED.reason,
                    status = CASE WHEN timetable_gaps.status = 'open' THEN 'open' ELSE timetable_gaps.status END"
            : "INSERT INTO timetable_gaps
                    (academic_year_id, class_id, day_of_week, period_number, subject_id, subject_raw,
                     printed_teacher, reason, status, source_document)
                 VALUES (:year, :class, :day, :period, :subject, :subject_raw, :teacher, :reason, :status, :source)
                 ON DUPLICATE KEY UPDATE
                    subject_id = VALUES(subject_id),
                    subject_raw = VALUES(subject_raw),
                    printed_teacher = VALUES(printed_teacher),
                    reason = VALUES(reason),
                    status = IF(status = 'open', 'open', status)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'year' => $yearId,
            'class' => $classId,
            'day' => $day,
            'period' => $period,
            'subject' => $subjectId,
            'subject_raw' => $subjectRaw,
            'teacher' => $printedTeacher,
            'reason' => $reason,
            'status' => in_array($status, ['open', 'resolved', 'ignored'], true) ? $status : 'open',
            'source' => $sourceDocument,
        ]);
    }

    /** A lesson already exists at this slot (loaded earlier or resolved by hand). */
    public function resolveSlot(int $yearId, int $classId, int $day, int $period, ?int $teacherId, ?int $subjectId, ?int $timetableId): void
    {
        Database::connection()->prepare(
            "UPDATE timetable_gaps
             SET status = 'resolved', resolved_teacher_id = :teacher, resolved_subject_id = :subject,
                 resolved_timetable_id = :timetable
             WHERE academic_year_id = :year AND class_id = :class
               AND day_of_week = :day AND period_number = :period AND status = 'open'"
        )->execute([
            'teacher' => $teacherId,
            'subject' => $subjectId,
            'timetable' => $timetableId,
            'year' => $yearId,
            'class' => $classId,
            'day' => $day,
            'period' => $period,
        ]);
    }

    /** A lesson now exists at this slot (loaded earlier or resolved by hand). */
    public function markResolved(int $id, ?int $teacherId, ?int $subjectId, ?int $timetableId, ?string $note = null): void
    {
        Database::connection()->prepare(
            "UPDATE timetable_gaps
             SET status = 'resolved', resolved_teacher_id = :teacher, resolved_subject_id = :subject,
                 resolved_timetable_id = :timetable, note = COALESCE(:note, note)
             WHERE id = :id"
        )->execute([
            'teacher' => $teacherId,
            'subject' => $subjectId,
            'timetable' => $timetableId,
            'note' => $note,
            'id' => $id,
        ]);
    }

    public function markIgnored(int $id): void
    {
        Database::connection()->prepare(
            "UPDATE timetable_gaps SET status = 'ignored' WHERE id = :id"
        )->execute(['id' => $id]);
    }

    public function reopen(int $id): void
    {
        Database::connection()->prepare(
            "UPDATE timetable_gaps
             SET status = 'open', resolved_teacher_id = NULL, resolved_subject_id = NULL,
                 resolved_timetable_id = NULL
             WHERE id = :id"
        )->execute(['id' => $id]);
    }

    /** @return array<int,int> teacher_id => loaded lesson count in the year */
    public function lessonCountsByTeacher(int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT teacher_id, COUNT(*) AS total FROM timetables
             WHERE academic_year_id = :year GROUP BY teacher_id'
        );
        $stmt->execute(['year' => $yearId]);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['teacher_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return array<string,int> printed teacher text => open gap count */
    public function openCountsByPrintedTeacher(int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT printed_teacher, COUNT(*) AS total FROM timetable_gaps
             WHERE academic_year_id = :year AND status = 'open' AND printed_teacher IS NOT NULL AND printed_teacher <> ''
             GROUP BY printed_teacher"
        );
        $stmt->execute(['year' => $yearId]);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['printed_teacher']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return list<string> printed names with an open gap and no teacher record */
    public function unregisteredPrintedNames(int $yearId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT g.printed_teacher
             FROM timetable_gaps g
             LEFT JOIN teachers t ON t.full_name = g.printed_teacher
             LEFT JOIN teacher_aliases a ON a.alias = g.printed_teacher
             WHERE g.academic_year_id = :year AND g.status = 'open'
               AND g.printed_teacher IS NOT NULL AND g.printed_teacher <> ''
               AND t.id IS NULL AND a.teacher_id IS NULL"
        );
        $stmt->execute(['year' => $yearId]);

        return array_map(static fn (array $row): string => (string) $row['printed_teacher'], $stmt->fetchAll());
    }
}

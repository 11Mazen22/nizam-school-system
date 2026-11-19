<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Nizam -- dated substitute cover.
 *
 * The weekly timetable is never edited for an absence. A cover row points at
 * one timetable lesson on one date and names the teacher who takes it, so the
 * official grid and the school's day-to-day staffing stay separate.
 */
final class LessonCoverRepository
{
    /**
     * Lessons of one weekday with their regular teacher, approved-leave flag
     * and any cover already assigned for the date.
     *
     * @return list<array<string,mixed>>
     */
    public function lessonsForDate(int $yearId, int $day, string $date): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.id, t.period_number, t.class_id, t.teacher_id, t.subject_id,
                    c.name AS class_name,
                    s.name_ar AS subject_ar, s.name_en AS subject_en,
                    te.full_name AS teacher_name,
                    lc.cover_teacher_id, lc.note AS cover_note,
                    ct.full_name AS cover_teacher_name,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM staff_leaves l
                        WHERE l.teacher_id = t.teacher_id
                          AND l.status = 'approved'
                          AND :leave_date BETWEEN l.start_date AND l.end_date
                    ) THEN 1 ELSE 0 END AS on_leave
             FROM timetables t
             JOIN classes c ON c.id = t.class_id
             JOIN subjects s ON s.id = t.subject_id
             LEFT JOIN teachers te ON te.id = t.teacher_id
             LEFT JOIN lesson_covers lc
                    ON lc.cover_date = :cover_date AND lc.timetable_id = t.id
             LEFT JOIN teachers ct ON ct.id = lc.cover_teacher_id
             WHERE t.academic_year_id = :year AND t.day_of_week = :day
             ORDER BY t.period_number ASC, c.name ASC"
        );
        $stmt->execute([
            'year' => $yearId,
            'day' => $day,
            'cover_date' => $date,
            'leave_date' => $date,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Who is already teaching in each period on this date: the regular grid
     * plus covers already assigned. Used to offer only genuinely free staff.
     *
     * @return array<int, array<int, string>> period => teacher_id => class
     */
    public function bookingsForDate(int $yearId, int $day, string $date): array
    {
        // Reuse the exact weekly evidence the gaps screen already builds,
        // then overlay this date's covers. The cover board and the gap
        // resolver can therefore never disagree about who is free.
        $weekly = (new TimetableGapRepository())->bookings($yearId);
        $map = [];
        foreach ($weekly[$day] ?? [] as $period => $teachers) {
            foreach ($teachers as $teacherId => $booking) {
                $map[(int) $period][(int) $teacherId] = (string) $booking['class_name'];
            }
        }

        $stmt = Database::connection()->prepare(
            'SELECT lc.period_number, lc.cover_teacher_id, c.name AS class_name
             FROM lesson_covers lc JOIN classes c ON c.id = lc.class_id
             WHERE lc.academic_year_id = :year AND lc.cover_date = :date'
        );
        $stmt->execute(['year' => $yearId, 'date' => $date]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['period_number']][(int) $row['cover_teacher_id']] ??= (string) $row['class_name'];
        }

        return $map;
    }

    /** @return array<int,true> teacher ids on approved leave that date */
    public function onLeave(string $date): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT teacher_id FROM staff_leaves
             WHERE status = 'approved' AND :date BETWEEN start_date AND end_date"
        );
        $stmt->execute(['date' => $date]);
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
    }

    public function findLesson(int $timetableId, int $yearId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.*, c.name AS class_name, te.full_name AS teacher_name
             FROM timetables t
             JOIN classes c ON c.id = t.class_id
             LEFT JOIN teachers te ON te.id = t.teacher_id
             WHERE t.id = :id AND t.academic_year_id = :year'
        );
        $stmt->execute(['id' => $timetableId, 'year' => $yearId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** The class that already holds this teacher, if any, excluding this lesson. */
    public function busyWith(int $yearId, string $date, int $day, int $period, int $teacherId, int $excludeTimetableId): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.name FROM timetables t JOIN classes c ON c.id = t.class_id
             WHERE t.academic_year_id = :year AND t.day_of_week = :day
               AND t.period_number = :period AND t.teacher_id = :teacher
               AND t.id <> :exclude
             LIMIT 1'
        );
        $stmt->execute([
            'year' => $yearId, 'day' => $day, 'period' => $period,
            'teacher' => $teacherId, 'exclude' => $excludeTimetableId,
        ]);
        $class = $stmt->fetchColumn();
        if ($class !== false) {
            return (string) $class;
        }

        $stmt = Database::connection()->prepare(
            'SELECT c.name FROM lesson_covers lc JOIN classes c ON c.id = lc.class_id
             WHERE lc.academic_year_id = :year AND lc.cover_date = :date
               AND lc.period_number = :period AND lc.cover_teacher_id = :teacher
               AND lc.timetable_id <> :exclude
             LIMIT 1'
        );
        $stmt->execute([
            'year' => $yearId, 'date' => $date, 'period' => $period,
            'teacher' => $teacherId, 'exclude' => $excludeTimetableId,
        ]);
        $class = $stmt->fetchColumn();

        return $class !== false ? (string) $class : null;
    }

    public function assign(
        int $yearId,
        string $date,
        int $timetableId,
        int $classId,
        int $period,
        int $originalTeacherId,
        int $coverTeacherId,
        ?string $note,
        ?int $userId
    ): void {
        $pdo = Database::connection();
        $params = [
            'year' => $yearId, 'date' => $date, 'timetable' => $timetableId,
            'class' => $classId, 'period' => $period, 'original' => $originalTeacherId,
            'cover' => $coverTeacherId, 'note' => $note, 'user' => $userId,
        ];

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $sql = "INSERT INTO lesson_covers
                        (academic_year_id, cover_date, timetable_id, class_id, period_number,
                         original_teacher_id, cover_teacher_id, note, created_by)
                    VALUES (:year, :date, :timetable, :class, :period, :original, :cover, :note, :user)
                    ON CONFLICT (cover_date, timetable_id) DO UPDATE SET
                        cover_teacher_id = EXCLUDED.cover_teacher_id,
                        note = EXCLUDED.note,
                        created_by = EXCLUDED.created_by,
                        updated_at = CURRENT_TIMESTAMP";
        } else {
            $sql = "INSERT INTO lesson_covers
                        (academic_year_id, cover_date, timetable_id, class_id, period_number,
                         original_teacher_id, cover_teacher_id, note, created_by)
                    VALUES (:year, :date, :timetable, :class, :period, :original, :cover, :note, :user)
                    ON DUPLICATE KEY UPDATE
                        cover_teacher_id = VALUES(cover_teacher_id),
                        note = VALUES(note),
                        created_by = VALUES(created_by)";
        }

        $pdo->prepare($sql)->execute($params);
    }

    public function remove(string $date, int $timetableId): void
    {
        Database::connection()->prepare(
            'DELETE FROM lesson_covers WHERE cover_date = :date AND timetable_id = :timetable'
        )->execute(['date' => $date, 'timetable' => $timetableId]);
    }
}

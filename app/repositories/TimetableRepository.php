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
             JOIN teachers  tea ON tea.id = t.teacher_id
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
             JOIN teachers tea ON tea.id = t.teacher_id
             WHERE t.class_id = :class AND t.academic_year_id = :year
             ORDER BY t.day_of_week ASC, t.period_number ASC'
        );
        $stmt->execute(['class' => $classId, 'year' => $yearId]);
        return $stmt->fetchAll();
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

    public function create(int $yearId, int $classId, int $day, int $period, int $subjectId, int $teacherId): int
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO timetables (academic_year_id, class_id, day_of_week, period_number, subject_id, teacher_id)
             VALUES (:year, :class, :day, :period, :sub, :teacher)'
        );
        $stmt->execute([
            'year' => $yearId, 'class' => $classId, 'day' => $day,
            'period' => $period, 'sub' => $subjectId, 'teacher' => $teacherId,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, int $subjectId, int $teacherId): void
    {
        Database::connection()->prepare(
            'UPDATE timetables SET subject_id=:sub, teacher_id=:teacher WHERE id=:id'
        )->execute(['sub' => $subjectId, 'teacher' => $teacherId, 'id' => $id]);
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

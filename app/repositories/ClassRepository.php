<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class ClassRepository
{
    /** @return array<int, array<string, mixed>> every class in a year, with its grade names joined in for display */
    public function allForYear(int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar
             FROM classes c JOIN grades g ON g.id = c.grade_id
             WHERE c.academic_year_id = :year
             ORDER BY g.sort_order ASC, c.name ASC'
        );
        $stmt->execute(['year' => $academicYearId]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> classes in one grade+year -- promotion's "assign target classes" step (§I.1) and enrollment forms both need this scoped list. */
    public function forGradeAndYear(int $gradeId, int $academicYearId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM classes WHERE grade_id = :grade AND academic_year_id = :year AND is_active = 1 ORDER BY name ASC'
        );
        $stmt->execute(['grade' => $gradeId, 'year' => $academicYearId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar
             FROM classes c JOIN grades g ON g.id = c.grade_id WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(int $gradeId, int $academicYearId, string $name): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM classes WHERE grade_id = :grade AND academic_year_id = :year AND name = :name'
        );
        $stmt->execute(['grade' => $gradeId, 'year' => $academicYearId, 'name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Current enrolled count -- shown next to capacity (§I.1 step 4: "capacity is shown but only soft-warns, never blocks"). */
    public function activeEnrollmentCount(int $classId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM student_enrollments WHERE class_id = :id AND status = 'active'"
        );
        $stmt->execute(['id' => $classId]);
        return (int) $stmt->fetchColumn();
    }

    public function create(int $gradeId, int $academicYearId, string $name, ?int $capacity): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO classes (grade_id, academic_year_id, name, capacity, is_active)
             VALUES (:grade, :year, :name, :capacity, 1)'
        );
        $stmt->execute(['grade' => $gradeId, 'year' => $academicYearId, 'name' => $name, 'capacity' => $capacity]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, string $name, ?int $capacity): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE classes SET name = :name, capacity = :capacity WHERE id = :id'
        );
        $stmt->execute(['name' => $name, 'capacity' => $capacity, 'id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE classes SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    /**
     * §K.3 Class density: every active class in a year with its own enrolled
     * count, one GROUP BY pass (LEFT JOIN so a class with zero enrollments
     * still appears as a 0, never silently dropped). fill_pct is null when
     * capacity isn't set -- "how full" has no defined answer without a
     * denominator, and the report/view must not fabricate one.
     *
     * @return array<int, array{class_id:int, class_name:string, grade_id:int, grade_name_en:string, grade_name_ar:string, capacity:?int, enrolled:int, fill_pct:?float}>
     */
    public function densityForYear(int $academicYearId, ?int $gradeId): array
    {
        $sql = "SELECT c.id AS class_id, c.name AS class_name, g.id AS grade_id,
                       g.name_en AS grade_name_en, g.name_ar AS grade_name_ar, g.sort_order,
                       c.capacity,
                       COUNT(e.id) AS enrolled
                FROM classes c
                JOIN grades g ON g.id = c.grade_id
                LEFT JOIN student_enrollments e ON e.class_id = c.id AND e.status = 'active'
                WHERE c.academic_year_id = :year AND c.is_active = 1";
        $params = ['year' => $academicYearId];
        if ($gradeId !== null) {
            $sql .= ' AND c.grade_id = :grade';
            $params['grade'] = $gradeId;
        }
        $sql .= ' GROUP BY c.id, c.name, g.id, g.name_en, g.name_ar, g.sort_order, c.capacity
                  ORDER BY g.sort_order ASC, c.name ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['enrolled'] = (int) $row['enrolled'];
            $row['capacity'] = $row['capacity'] !== null ? (int) $row['capacity'] : null;
            $row['fill_pct'] = $row['capacity'] !== null && $row['capacity'] > 0
                ? round(($row['enrolled'] / $row['capacity']) * 100, 1) : null;
        }
        unset($row);
        return $rows;
    }

    /**
     * §I.4/§O-11 rollover: clone every active class from the outgoing year into
     * the new year (same grade/name/capacity, new rows -- classes is scoped by
     * academic_year_id). Returns [old_class_id => new_class_id] so
     * TeacherAssignmentRepository::cloneForRollover() can remap onto the new rows.
     *
     * @return array<int,int>
     */
    public function cloneActiveForRollover(int $fromYearId, int $toYearId): array
    {
        $pdo = Database::connection();
        $source = $pdo->prepare(
            'SELECT id, grade_id, name, capacity FROM classes WHERE academic_year_id = :year AND is_active = 1'
        );
        $source->execute(['year' => $fromYearId]);

        $insert = $pdo->prepare(
            'INSERT INTO classes (grade_id, academic_year_id, name, capacity, is_active)
             VALUES (:grade, :year, :name, :capacity, 1)'
        );

        $map = [];
        foreach ($source->fetchAll() as $row) {
            $insert->execute([
                'grade' => $row['grade_id'], 'year' => $toYearId,
                'name' => $row['name'], 'capacity' => $row['capacity'],
            ]);
            $map[(int) $row['id']] = (int) $pdo->lastInsertId();
        }
        return $map;
    }
}

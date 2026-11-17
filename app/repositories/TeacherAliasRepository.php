<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

/**
 * Nizam -- the printed name a timetable uses for a staff member.
 *
 * The source grids print a first name only, so "مروة" may be one teacher or
 * two different people in two classes. An alias records that a real staff
 * record is known by that printed text; it never merges two teachers, and it
 * never rewrites a lesson. Resolving an ambiguous slot is a separate,
 * explicit choice made on the gaps screen.
 */
final class TeacherAliasRepository
{
    /** @return list<array<string,mixed>> */
    public function forTeacher(int $teacherId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM teacher_aliases WHERE teacher_id = :teacher ORDER BY alias ASC'
        );
        $stmt->execute(['teacher' => $teacherId]);

        return $stmt->fetchAll();
    }

    /** @return array<string,int> alias => teacher_id */
    public function all(): array
    {
        $rows = Database::connection()
            ->query('SELECT alias, teacher_id FROM teacher_aliases')
            ->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            // The alias table is unique per teacher; if two teachers genuinely
            // share a printed name the first wins here and the gaps screen is
            // where the ambiguity is resolved lesson by lesson.
            $map[(string) $row['alias']] ??= (int) $row['teacher_id'];
        }

        return $map;
    }

    public function attach(int $teacherId, string $alias, string $source = 'timetable'): void
    {
        $pdo = Database::connection();
        $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
            ? 'INSERT INTO teacher_aliases (teacher_id, alias, source) VALUES (:teacher, :alias, :source)
               ON CONFLICT (teacher_id, alias) DO UPDATE SET source = EXCLUDED.source'
            : 'INSERT INTO teacher_aliases (teacher_id, alias, source) VALUES (:teacher, :alias, :source)
               ON DUPLICATE KEY UPDATE source = VALUES(source)';
        $pdo->prepare($sql)->execute(['teacher' => $teacherId, 'alias' => $alias, 'source' => $source]);
    }

    public function detach(int $teacherId, string $alias): void
    {
        Database::connection()->prepare(
            'DELETE FROM teacher_aliases WHERE teacher_id = :teacher AND alias = :alias'
        )->execute(['teacher' => $teacherId, 'alias' => $alias]);
    }
}

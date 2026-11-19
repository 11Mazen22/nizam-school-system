<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

/**
 * Nizam -- one shared source of truth for printed timetable clashes.
 *
 * A printed clash is a lesson the official paper gave to a teacher who is
 * already standing in another room at the same period. The database refuses to
 * record that double-booking, so the lesson keeps the printed name in
 * `printed_teacher` and no `teacher_id`. Until this service existed every
 * surface (class timetable, teacher timetable, master timetable, the document
 * grid and the workbook) decided for itself what a clash was -- and the
 * teacher timetable simply could not see one at all, because it read only rows
 * with a real `teacher_id`.
 *
 * The index produced here is deliberately read-only and request-shaped: it
 * never writes, never invents a teacher and never hides the printed evidence.
 * A clash is "open" while the lesson has no real teacher and "resolved" once
 * the school has chosen one; the printed text remains in both states.
 */
final class TimetableConflicts
{
    /**
     * Clash metadata for every printed cell of the year, keyed by
     * "classId|day|period".
     *
     * @return array<string, array{
     *   printed:string, status:string, other_classes:list<string>, source:string
     * }>
     */
    public static function forYear(int $yearId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT t.id, t.class_id, t.day_of_week, t.period_number, t.teacher_id,
                    t.printed_teacher, t.origin, t.import_source, t.clash_resolved_at,
                    c.name AS class_name
             FROM timetables t
             JOIN classes c ON c.id = t.class_id
             WHERE t.academic_year_id = :year'
        );
        $stmt->execute(['year' => $yearId]);
        $rows = $stmt->fetchAll();

        $teachers = $pdo->query('SELECT id, full_name FROM teachers')->fetchAll();
        $aliases = $pdo->query('SELECT teacher_id, alias FROM teacher_aliases')->fetchAll();

        $names = [];
        foreach ($teachers as $teacher) {
            $names[(int) $teacher['id']][] = self::normalise((string) $teacher['full_name']);
        }
        foreach ($aliases as $alias) {
            $names[(int) $alias['teacher_id']][] = self::normalise((string) $alias['alias']);
        }

        $bySlot = [];
        foreach ($rows as $row) {
            $bySlot[(int) $row['day_of_week'] . '|' . (int) $row['period_number']][] = $row;
        }

        $index = [];
        foreach ($rows as $row) {
            $printed = trim((string) ($row['printed_teacher'] ?? ''));
            if ($printed === '') {
                continue;
            }

            $day = (int) $row['day_of_week'];
            $period = (int) $row['period_number'];
            $classId = (int) $row['class_id'];
            $matched = self::teacherFor($printed, $names);
            $others = [];

            foreach ($bySlot[$day . '|' . $period] ?? [] as $other) {
                if ((int) $other['class_id'] === $classId) {
                    continue;
                }
                $sameHeldTeacher = $matched !== null && (int) ($other['teacher_id'] ?? 0) === $matched;
                $samePrinted = trim((string) ($other['printed_teacher'] ?? '')) !== ''
                    && self::normalise((string) $other['printed_teacher']) === self::normalise($printed);
                if ($sameHeldTeacher || ($matched === null && $samePrinted)) {
                    $others[] = (string) $other['class_name'];
                }
            }

            $index[$classId . '|' . $day . '|' . $period] = [
                'printed' => $printed,
                'status' => ($row['teacher_id'] === null || (int) $row['teacher_id'] === 0)
                    ? 'open'
                    : 'resolved',
                'other_classes' => array_values(array_unique($others)),
                'source' => trim((string) ($row['import_source'] ?? '')) !== ''
                    ? (string) $row['import_source']
                    : (string) ($row['origin'] ?? 'imported'),
            ];
        }

        return $index;
    }

    /**
     * The open printed clashes a specific teacher still has to be resolved:
     * cells the paper printed with their name that the timetable could not
     * assign to them. Used by the teacher timetable so a clash is never
     * invisible simply because it has no teacher_id.
     *
     * @return list<array<string,mixed>> timetable-shaped rows
     */
    public static function forTeacher(int $teacherId, int $yearId): array
    {
        $pdo = Database::connection();

        $teacher = $pdo->prepare('SELECT id, full_name FROM teachers WHERE id = :id');
        $teacher->execute(['id' => $teacherId]);
        $row = $teacher->fetch();
        if ($row === false) {
            return [];
        }

        $names = [self::normalise((string) $row['full_name'])];
        $alias = $pdo->prepare('SELECT alias FROM teacher_aliases WHERE teacher_id = :id');
        $alias->execute(['id' => $teacherId]);
        foreach ($alias->fetchAll() as $aliasRow) {
            $names[] = self::normalise((string) $aliasRow['alias']);
        }

        $stmt = $pdo->prepare(
            "SELECT t.*, s.name_ar AS subject_ar, s.name_en AS subject_en,
                    c.name AS class_name, g.name_en AS grade_name_en, g.name_ar AS grade_name_ar
             FROM timetables t
             JOIN subjects s ON s.id = t.subject_id
             JOIN classes c ON c.id = t.class_id
             JOIN grades g ON g.id = c.grade_id
             WHERE t.academic_year_id = :year
               AND t.teacher_id IS NULL
               AND t.printed_teacher IS NOT NULL AND TRIM(t.printed_teacher) <> ''
             ORDER BY t.day_of_week ASC, t.period_number ASC"
        );
        $stmt->execute(['year' => $yearId]);

        $slots = [];
        foreach ($stmt->fetchAll() as $clash) {
            if (!self::printedMatches((string) $clash['printed_teacher'], $names)) {
                continue;
            }
            $clash['teacher_name'] = '';
            $clash['conflict_only'] = true;
            $slots[] = $clash;
        }

        return $slots;
    }

    /** Localized human label for a clash status. */
    public static function statusLabel(string $status): string
    {
        return $status === 'resolved'
            ? __('document.clash_status_resolved')
            : __('document.clash_status_open');
    }

    /**
     * @param array<int,list<string>> $names teacherId => normalized names
     */
    private static function teacherFor(string $printed, array $names): ?int
    {
        foreach ($names as $teacherId => $teacherNames) {
            if (self::printedMatches($printed, $teacherNames)) {
                return (int) $teacherId;
            }
        }

        return null;
    }

    /**
     * The paper prints first names ("إيمان", "خالد/إيناس"); the staff record
     * holds the full name. A printed token therefore matches a full name
     * exactly, as its first word, or an alias the school attached to it.
     *
     * @param list<string> $names normalized names for one teacher
     */
    private static function printedMatches(string $printed, array $names): bool
    {
        foreach (TimetableSource::teacherNames($printed) as $token) {
            $token = self::normalise($token);
            if (mb_strlen($token) < 2) {
                continue;
            }
            foreach ($names as $name) {
                if ($name === '' ) {
                    continue;
                }
                if ($name === $token || str_starts_with($name, $token . ' ')) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function normalise(string $name): string
    {
        $name = str_replace("\u{0640}", '', $name); // tatweel is decoration, not identity
        $name = (string) preg_replace('/\s+/u', ' ', trim($name));
        $name = (string) preg_replace('/^(أ\.|أ\/|د\.|د\/|م\.|م\/)\s*/u', '', $name);

        return mb_strtolower($name);
    }
}

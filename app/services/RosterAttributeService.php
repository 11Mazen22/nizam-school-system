<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use RuntimeException;

/**
 * Nizam -- expands the official rosters' "printed once" column.
 *
 * The school's roster pages write a repeated value once and leave the rows
 * below blank. Ten Muslim students carry "مسلمة" on the first row only; a
 * Christian student is the one row that says "مسيحية". A blank cell therefore
 * means "same as the row above until a different value is printed" -- it does
 * NOT mean "not recorded". This service turns that page convention into an
 * explicit value on every student, and records whether a row printed the
 * value itself or inherited it.
 *
 * The printed text is never rewritten: `class_attribute_raw` keeps exactly
 * what the page says (including the shorthand "م - ف" / "ح - س"). Only the
 * `religion` column is derived, and only from values whose meaning is
 * unambiguous (مسلم/مسلمة, مسيحى/مسيحية, and the م/ح prefix the pages use for
 * the same two religions). A value such as "رياضة", "معفى" or "المانى" is a
 * pathway/language/exemption, not a religion, so religion stays untouched.
 */
final class RosterAttributeService
{
    public const HEADERS = [
        'Full Name',
        'Class Name',
        'Gender (M/F)',
        'Date of Birth (YYYY-MM-DD)',
        'Religion',
        'Class Attribute',
        'Notes',
    ];

    /**
     * Reads a roster CSV and returns one row per student with the carried
     * value resolved. Carry-down is scoped to each class block and runs in
     * document order (the right-hand table first, then the left-hand table),
     * which is exactly how the page is read.
     *
     * @return array{
     *   rows: list<array<string,string>>,
     *   counts: array{rows:int, printed:int, carried:int, religion:int, classes:int}
     * }
     */
    public function expandCsv(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('roster_file_missing');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('roster_file_unreadable');
        }

        $header = fgetcsv($handle, 0, ',');
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('roster_empty_file');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
        $header = array_map(static fn ($value): string => trim((string) $value), $header);
        if (array_diff(self::HEADERS, $header) !== []) {
            fclose($handle);
            throw new RuntimeException('roster_headers_mismatch');
        }

        $rows = [];
        $lastAttribute = '';
        $lastReligion = '';
        $currentClass = null;
        $counts = ['rows' => 0, 'printed' => 0, 'carried' => 0, 'religion' => 0, 'classes' => 0];
        $seenClasses = [];

        while (($values = fgetcsv($handle, 0, ',')) !== false) {
            $data = [];
            $blank = true;
            foreach ($header as $index => $name) {
                $value = trim((string) ($values[$index] ?? ''));
                $data[$name] = $value;
                if ($value !== '') {
                    $blank = false;
                }
            }
            if ($blank) {
                continue;
            }

            $className = strtoupper(trim((string) ($data['Class Name'] ?? '')));
            if ($className !== $currentClass) {
                // A new page starts a new class; the previous page's last
                // value must not leak into it.
                $currentClass = $className;
                $lastAttribute = '';
                $lastReligion = '';
                if (!isset($seenClasses[$className])) {
                    $seenClasses[$className] = true;
                    $counts['classes']++;
                }
            }

            $printedAttribute = trim((string) ($data['Class Attribute'] ?? ''));
            $printedReligion = trim((string) ($data['Religion'] ?? ''));

            if ($printedAttribute !== '') {
                $lastAttribute = $printedAttribute;
                $source = 'printed';
                $counts['printed']++;
            } elseif ($lastAttribute !== '') {
                $source = 'carried_forward';
                $counts['carried']++;
            } else {
                $source = '';
            }

            if ($printedReligion !== '') {
                $lastReligion = $printedReligion;
            }

            $attribute = $printedAttribute !== '' ? $printedAttribute : $lastAttribute;
            $religionRaw = $printedReligion !== '' ? $printedReligion : $lastReligion;
            $religion = $this->decodeReligion($religionRaw !== '' ? $religionRaw : $attribute);
            if ($religion !== null) {
                $counts['religion']++;
            }

            $data['_attribute'] = $attribute;
            $data['_attribute_source'] = $source;
            $data['_religion'] = $religion ?? '';
            $data['_class'] = $className;
            $rows[] = $data;
            $counts['rows']++;
        }

        fclose($handle);

        return ['rows' => $rows, 'counts' => $counts];
    }

    /**
     * Writes the expanded values onto the students that already exist for the
     * active year. Matching is by exact printed full name + class, which is
     * how the import created them; a student whose name was corrected in the
     * app after import is reported as unmatched instead of being guessed at.
     *
     * @return array{
     *   dry_run:bool,
     *   classes: array<string, array{rows:int, printed:int, carried:int, updated:int, religion:int, unmatched:list<string>}>,
     *   updated:int, religion:int, unmatched:int
     * }
     */
    public function apply(string $path, int $yearId, bool $dryRun = false): array
    {
        $expanded = $this->expandCsv($path);
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "SELECT s.id, s.full_name, s.religion, s.class_attribute_raw, s.class_attribute_source, e.class_id
             FROM students s
             JOIN student_enrollments e ON e.student_id = s.id
             WHERE e.academic_year_id = :year AND e.status = 'active' AND s.status = 'active'"
        );
        $stmt->execute(['year' => $yearId]);

        $byClassAndName = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = (int) $row['class_id'] . '|' . $this->nameKey((string) $row['full_name']);
            $byClassAndName[$key] = $row;
        }

        $classes = [];
        foreach ($expanded['rows'] as $row) {
            $class = (string) $row['_class'];
            $classes[$class] ??= ['rows' => 0, 'printed' => 0, 'carried' => 0, 'updated' => 0, 'religion' => 0, 'unmatched' => []];
            $classes[$class]['rows']++;
            if (($row['_attribute_source'] ?? '') === 'printed') {
                $classes[$class]['printed']++;
            } elseif (($row['_attribute_source'] ?? '') === 'carried_forward') {
                $classes[$class]['carried']++;
            }
        }

        // Resolve class ids once.
        $classIds = [];
        $classStmt = $pdo->prepare('SELECT id, name FROM classes WHERE academic_year_id = :year AND is_active = 1');
        $classStmt->execute(['year' => $yearId]);
        foreach ($classStmt->fetchAll() as $classRow) {
            $classIds[strtoupper((string) $classRow['name'])] = (int) $classRow['id'];
        }

        $update = $pdo->prepare(
            'UPDATE students
             SET class_attribute_raw = :attr,
                 class_attribute_source = :source,
                 religion = COALESCE(religion, :religion)
             WHERE id = :id'
        );

        $updated = 0;
        $religionSet = 0;
        $unmatched = 0;

        if (!$dryRun) {
            $pdo->beginTransaction();
        }
        try {
            foreach ($expanded['rows'] as $row) {
                $class = (string) $row['_class'];
                $classId = $classIds[$class] ?? 0;
                $key = $classId . '|' . $this->nameKey((string) $row['Full Name']);
                $student = $byClassAndName[$key] ?? null;

                if ($student === null) {
                    $classes[$class]['unmatched'][] = (string) $row['Full Name'];
                    $unmatched++;
                    continue;
                }

                $attribute = (string) $row['_attribute'];
                $source = (string) $row['_attribute_source'];
                $religion = (string) $row['_religion'];

                $changed = (string) ($student['class_attribute_raw'] ?? '') !== $attribute
                    || ($religion !== '' && $student['religion'] === null)
                    // Provenance is written even when the text itself was
                    // already correct, so every expanded row says whether the
                    // page printed it or the rows below inherited it.
                    || (string) ($student['class_attribute_source'] ?? '') !== $source;

                if ($changed) {
                    $classes[$class]['updated']++;
                    $updated++;
                    if ($religion !== '' && $student['religion'] === null) {
                        $classes[$class]['religion']++;
                        $religionSet++;
                    }
                    if (!$dryRun) {
                        $update->execute([
                            'attr' => $attribute !== '' ? $attribute : null,
                            'source' => $source !== '' ? $source : null,
                            'religion' => $religion !== '' ? $religion : null,
                            'id' => (int) $student['id'],
                        ]);
                    }
                }
            }
            if (!$dryRun) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$dryRun) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'dry_run' => $dryRun,
            'classes' => $classes,
            'updated' => $updated,
            'religion' => $religionSet,
            'unmatched' => $unmatched,
        ];
    }

    /**
     * The one place that decides what a printed attribute value means for the
     * religion column. Returns null when the value does not name a religion.
     */
    public function decodeReligion(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace(['أ', 'إ', 'آ', 'ى', 'ة'], ['ا', 'ا', 'ا', 'ي', 'ه'], $value);
        $normalized = (string) preg_replace('/\s+/u', ' ', $normalized);

        if (str_contains($normalized, 'مسيح')) {
            return 'christian';
        }
        if (str_contains($normalized, 'مسلم')) {
            return 'muslim';
        }

        // The pages shorten the religion to one letter in mixed columns:
        // "م - ف" = مسلم + a language/track, "ح - س" = مسيحي + a language/track.
        if (preg_match('/^\s*([مح])\s*[-–—]\s*/u', $normalized, $match) === 1) {
            return $match[1] === 'م' ? 'muslim' : 'christian';
        }

        return null;
    }

    private function nameKey(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }
}

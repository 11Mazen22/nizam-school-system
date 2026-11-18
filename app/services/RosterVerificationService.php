<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Repositories\RosterVerificationRepository;
use RuntimeException;

/**
 * Nizam -- class-by-class roster verification.
 *
 * A class is verified when a staff member has compared the stored students,
 * in page order, with the printed roster page and signed it off. The sign-off
 * stores a fingerprint of the names and attributes it covered. If a name or
 * attribute is edited later the live fingerprint no longer matches, and the
 * class is shown as "changed since verification" instead of keeping a tick
 * that no longer describes the data.
 */
final class RosterVerificationService
{
    public function __construct(
        private readonly RosterVerificationRepository $repo = new RosterVerificationRepository(),
        private readonly RosterAttributeService $attributes = new RosterAttributeService(),
    ) {
    }

    /**
     * @return list<array<string,mixed>> every active class with its roster
     *     counts and verification state
     */
    public function overview(int $yearId): array
    {
        $classes = $this->repo->classesForYear($yearId);

        foreach ($classes as &$class) {
            $students = $this->repo->studentsForClass((int) $class['id'], $yearId);
            $live = $this->fingerprint($students);

            $class['student_count'] = count($students);
            $class['missing_attribute'] = count(array_filter(
                $students,
                static fn (array $student): bool => trim((string) ($student['class_attribute_raw'] ?? '')) === ''
            ));
            $class['carried_count'] = count(array_filter(
                $students,
                static fn (array $student): bool => ($student['class_attribute_source'] ?? '') === 'carried_forward'
            ));
            $class['fingerprint_live'] = $live;
            $class['verified'] = !empty($class['verified_at']);
            $class['drifted'] = $class['verified'] && !hash_equals((string) $class['fingerprint'], $live);
            $class['state'] = !$class['verified'] ? 'pending' : ($class['drifted'] ? 'changed' : 'verified');
        }
        unset($class);

        return $classes;
    }

    /** @return array{class:array<string,mixed>, students:list<array<string,mixed>>, verification:?array<string,mixed>, state:string, carried:int, missing_attribute:int, shorthand:int} */
    public function detail(int $classId, int $yearId): array
    {
        $class = $this->repo->findClass($classId, $yearId);
        if ($class === null) {
            throw new RuntimeException('class_not_found');
        }

        $students = $this->repo->studentsForClass($classId, $yearId);
        $verification = $this->repo->verificationFor($classId, $yearId);
        $live = $this->fingerprint($students);
        $state = $verification === null
            ? 'pending'
            : (hash_equals((string) $verification['fingerprint'], $live) ? 'verified' : 'changed');

        $shorthand = 0;
        foreach ($students as $student) {
            $raw = trim((string) ($student['class_attribute_raw'] ?? ''));
            if ($raw !== '' && $this->attributes->isShorthand($raw)) {
                $shorthand++;
            }
        }

        return [
            'class' => $class,
            'students' => $students,
            'verification' => $verification,
            'state' => $state,
            'carried' => count(array_filter(
                $students,
                static fn (array $student): bool => ($student['class_attribute_source'] ?? '') === 'carried_forward'
            )),
            'missing_attribute' => count(array_filter(
                $students,
                static fn (array $student): bool => trim((string) ($student['class_attribute_raw'] ?? '')) === ''
            )),
            'shorthand' => $shorthand,
        ];
    }

    /**
     * Saves the corrections a staff member made while comparing the class
     * with its printed page. Any real change invalidates an existing
     * verification: the tick described the old names, not these.
     *
     * @param array<string,string> $names      student id => corrected full name
     * @param array<string,string> $attributes student id => corrected printed attribute
     * @return int number of students changed
     */
    public function saveCorrections(int $classId, int $yearId, array $names, array $attributes): int
    {
        $students = $this->repo->studentsForClass($classId, $yearId);
        $existing = [];
        foreach ($students as $student) {
            $existing[(int) $student['id']] = $student;
        }
        if ($existing === []) {
            throw new RuntimeException('class_empty');
        }

        $changes = [];
        $seen = [];
        foreach ($existing as $id => $student) {
            $name = trim((string) ($names[(string) $id] ?? $student['full_name']));
            $attribute = trim((string) ($attributes[(string) $id] ?? ($student['class_attribute_raw'] ?? '')));

            $name = (string) preg_replace('/\s+/u', ' ', $name);
            if ($name === '' || mb_strlen($name) < 2) {
                throw new RuntimeException('name_required');
            }
            if (mb_strlen($name) > 150) {
                throw new RuntimeException('name_too_long');
            }
            if (mb_strlen($attribute) > 120) {
                throw new RuntimeException('attribute_too_long');
            }

            $nameKey = mb_strtolower($name);
            if (isset($seen[$nameKey])) {
                throw new RuntimeException('duplicate_name');
            }
            $seen[$nameKey] = true;

            $oldName = (string) $student['full_name'];
            $oldAttribute = trim((string) ($student['class_attribute_raw'] ?? ''));
            if ($name !== $oldName || $attribute !== $oldAttribute) {
                $changes[$id] = [
                    'name' => $name,
                    'attribute' => $attribute,
                    // A corrected attribute is now an explicit value on this
                    // row; a name-only correction must not rewrite how the
                    // attribute was obtained.
                    'source' => $attribute !== $oldAttribute
                        ? ($attribute !== '' ? 'printed' : null)
                        : ($student['class_attribute_source'] ?? null),
                    'religion' => $this->attributes->decodeReligion($attribute),
                ];
            }
        }

        if ($changes === []) {
            return 0;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare(
                'UPDATE students
                 SET full_name = :name,
                     class_attribute_raw = :attribute,
                     class_attribute_source = :source,
                     -- A deliberate correction that explicitly names a
                     -- religion (مسلم/مسلمة/مسيحى/مسيحية) is the strongest
                     -- evidence available, so it replaces a stale/default
                     -- value; shorthand or a subject/track value names no
                     -- religion and leaves the column alone.
                     religion = COALESCE(:religion, religion)
                 WHERE id = :id'
            );
            foreach ($changes as $id => $change) {
                $update->execute([
                    'name' => $change['name'],
                    'attribute' => $change['attribute'] !== '' ? $change['attribute'] : null,
                    'source' => $change['source'],
                    'religion' => $change['religion'],
                    'id' => $id,
                ]);
            }

            // The old sign-off no longer describes the roster.
            $this->repo->delete($classId, $yearId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        ActivityLogger::log('roster.verify_correct', 'classes', $classId, 'Roster corrections: ' . count($changes));

        return count($changes);
    }

    public function confirm(int $classId, int $yearId, ?string $note, ?int $userId): void
    {
        $class = $this->repo->findClass($classId, $yearId);
        if ($class === null) {
            throw new RuntimeException('class_not_found');
        }

        $students = $this->repo->studentsForClass($classId, $yearId);
        if ($students === []) {
            throw new RuntimeException('class_empty');
        }

        foreach ($students as $student) {
            if (trim((string) ($student['class_attribute_raw'] ?? '')) === '') {
                throw new RuntimeException('missing_attribute');
            }
        }

        $note = $note === null ? null : trim($note);
        if ($note === '') {
            $note = null;
        }
        if ($note !== null && mb_strlen($note) > 500) {
            throw new RuntimeException('note_too_long');
        }

        $this->repo->save(
            $classId,
            $yearId,
            count($students),
            $this->fingerprint($students),
            $note,
            $userId
        );
        ActivityLogger::log('roster.verify', 'classes', $classId, 'Roster verified: ' . count($students) . ' students');
    }

    public function reopen(int $classId, int $yearId): void
    {
        $this->repo->delete($classId, $yearId);
        ActivityLogger::log('roster.verify_reopen', 'classes', $classId, 'Roster verification reopened');
    }

    /** @param list<array<string,mixed>> $students */
    public function fingerprint(array $students): string
    {
        $lines = [];
        foreach ($students as $student) {
            $lines[] = implode('|', [
                (int) $student['id'],
                (string) $student['full_name'],
                (string) ($student['class_attribute_raw'] ?? ''),
                (string) ($student['class_attribute_source'] ?? ''),
            ]);
        }

        return hash('sha256', implode("\n", $lines));
    }
}

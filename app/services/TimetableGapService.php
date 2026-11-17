<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Repositories\SubjectRepository;
use App\Repositories\TeacherAliasRepository;
use App\Repositories\TeacherAssignmentRepository;
use App\Repositories\TeacherRepository;
use App\Repositories\TimetableGapRepository;
use App\Repositories\TimetableRepository;
use RuntimeException;

/**
 * Nizam -- timetable gaps and printed-name mapping.
 *
 * 507 of the 558 printed lessons loaded directly. The remaining 51 cells are
 * printed lessons that could not become rows: 38 have no teacher printed at
 * all, 12 collide because two different teachers share a first name and the
 * grid prints only that first name, and one is an absence-check period rather
 * than a lesson. They are stored as gaps so the school can see exactly where
 * the paper timetable and the digital one differ, and fill them in.
 */
final class TimetableGapService
{
    public function __construct(
        private readonly TimetableGapRepository $gaps = new TimetableGapRepository(),
        private readonly TimetableRepository $timetables = new TimetableRepository(),
        private readonly TeacherRepository $teachers = new TeacherRepository(),
        private readonly TeacherAliasRepository $aliases = new TeacherAliasRepository(),
        private readonly SubjectRepository $subjects = new SubjectRepository(),
        private readonly TeacherAssignmentRepository $assignments = new TeacherAssignmentRepository(),
    ) {
    }

    /**
     * Rebuilds the gap list from the extracted dataset. Idempotent: running it
     * again updates the printed text and the reason, keeps resolved/ignored
     * gaps as they are, and closes a gap whose lesson has since been loaded.
     *
     * @return array{loaded:int, gaps:int, by_reason:array<string,int>, dry_run:bool}
     */
    public function sync(int $yearId, string $jsonPath, bool $dryRun = false): array
    {
        if (!is_file($jsonPath)) {
            throw new RuntimeException('dataset_missing');
        }
        $data = json_decode((string) file_get_contents($jsonPath), true);
        if (!is_array($data) || !isset($data['classes'])) {
            throw new RuntimeException('dataset_invalid');
        }

        $pdo = Database::connection();

        $classIds = [];
        $classRows = $pdo->prepare('SELECT id, name FROM classes WHERE academic_year_id = :year');
        $classRows->execute(['year' => $yearId]);
        foreach ($classRows->fetchAll() as $row) {
            $classIds[(string) $row['name']] = (int) $row['id'];
        }

        $subjectIds = [];
        foreach ($pdo->query('SELECT id, name_ar FROM subjects') as $row) {
            $subjectIds[(string) $row['name_ar']] = (int) $row['id'];
        }

        $teacherIds = [];
        foreach ($pdo->query("SELECT id, full_name FROM teachers WHERE status = 'active'") as $row) {
            $teacherIds[(string) $row['full_name']] = (int) $row['id'];
        }

        $loaded = [];
        $booked = [];
        $slotRows = $pdo->prepare(
            'SELECT id, class_id, day_of_week, period_number, teacher_id, subject_id
             FROM timetables WHERE academic_year_id = :year'
        );
        $slotRows->execute(['year' => $yearId]);
        foreach ($slotRows->fetchAll() as $row) {
            $loaded[(int) $row['class_id']][(int) $row['day_of_week']][(int) $row['period_number']] = $row;
            $booked[(int) $row['teacher_id']][(int) $row['day_of_week']][(int) $row['period_number']] = true;
        }

        $result = ['loaded' => 0, 'gaps' => 0, 'by_reason' => [], 'dry_run' => $dryRun];

        foreach ($data['classes'] as $class) {
            $className = strtoupper((string) ($class['class_id'] ?? ''));
            $classId = $classIds[$className] ?? 0;
            if ($classId === 0) {
                continue;
            }

            foreach (($class['rows'] ?? []) as $cell) {
                $label = trim((string) ($cell['subject'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $day = TimetableSource::DAYS[(string) ($cell['day'] ?? '')] ?? 0;
                $period = (int) ($cell['period'] ?? 0);
                if ($day === 0 || $period === 0) {
                    continue;
                }

                $existing = $loaded[$classId][$day][$period] ?? null;
                if ($existing !== null) {
                    $result['loaded']++;
                    if (!$dryRun) {
                        $this->gaps->resolveSlot(
                            $yearId,
                            $classId,
                            $day,
                            $period,
                            (int) $existing['teacher_id'],
                            (int) $existing['subject_id'],
                            (int) $existing['id']
                        );
                    }
                    continue;
                }

                $teacherRaw = trim((string) ($cell['teacher'] ?? ''));
                $primary = TimetableSource::primaryTeacher($teacherRaw);
                $teacherId = $primary !== '' ? ($teacherIds[$primary] ?? null) : null;

                if (in_array($label, TimetableSource::NOT_LESSONS, true)) {
                    $reason = 'absence';
                } elseif ($teacherRaw === '') {
                    $reason = 'no_teacher';
                } elseif ($teacherId === null) {
                    $reason = 'unmapped_teacher';
                } elseif (isset($booked[$teacherId][$day][$period])) {
                    $reason = 'teacher_conflict';
                } else {
                    // The cell prints a lesson and a known, free teacher but
                    // still did not load -- e.g. a subject label outside the
                    // package's vocabulary. Keep it visible rather than drop
                    // it on the floor.
                    $reason = 'other';
                }

                $subjectId = TimetableSource::subjectName($label);
                $result['by_reason'][$reason] = ($result['by_reason'][$reason] ?? 0) + 1;
                $result['gaps']++;

                if (!$dryRun) {
                    $this->gaps->upsert(
                        $yearId,
                        $classId,
                        $day,
                        $period,
                        $subjectId !== null ? ($subjectIds[$subjectId] ?? null) : null,
                        $label,
                        $teacherRaw !== '' ? $teacherRaw : null,
                        $reason,
                        (string) ($class['source_pdf'] ?? null),
                        // An absence check is not a lesson the school has to
                        // fill in; it is listed for completeness and starts
                        // out ignored.
                        $reason === 'absence' ? 'ignored' : 'open'
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Loads a gap's lesson with a real teacher and subject, creating the
     * teacher's assignment for that class when the package never had one
     * (the printed lesson itself is the evidence that the assignment exists).
     *
     * @throws RuntimeException 'gap_not_found'|'gap_closed'|'teacher_invalid'|'subject_invalid'|'teacher_conflict'|'class_conflict'
     */
    public function resolve(int $gapId, int $yearId, int $teacherId, int $subjectId, ?string $note = null): int
    {
        $gap = $this->gaps->find($gapId, $yearId);
        if ($gap === null) {
            throw new RuntimeException('gap_not_found');
        }
        if ((string) $gap['status'] !== 'open') {
            throw new RuntimeException('gap_closed');
        }

        $teacher = $this->teachers->find($teacherId);
        if ($teacher === null || (string) $teacher['status'] !== 'active') {
            throw new RuntimeException('teacher_invalid');
        }
        if ($this->subjects->find($subjectId) === null) {
            throw new RuntimeException('subject_invalid');
        }

        $classId = (int) $gap['class_id'];
        $day = (int) $gap['day_of_week'];
        $period = (int) $gap['period_number'];

        if ($this->timetables->teacherSlotTaken($yearId, $teacherId, $day, $period)) {
            throw new RuntimeException('teacher_conflict');
        }
        if ($this->timetables->classSlotTaken($yearId, $classId, $day, $period)) {
            throw new RuntimeException('class_conflict');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if (!$this->assignments->existsForTuple($teacherId, $subjectId, $classId, $yearId)) {
                $this->assignments->create($teacherId, $subjectId, $classId, $yearId, 1);
            }
            $timetableId = $this->timetables->create($yearId, $classId, $day, $period, $subjectId, $teacherId);
            $this->gaps->markResolved($gapId, $teacherId, $subjectId, $timetableId, $note);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        ActivityLogger::log(
            'timetable.gap_resolved',
            'timetables',
            $timetableId,
            'Gap ' . $gapId . ' loaded for class ' . $gap['class_name']
        );

        return $timetableId;
    }

    public function ignore(int $gapId, int $yearId): void
    {
        $gap = $this->gaps->find($gapId, $yearId);
        if ($gap === null) {
            throw new RuntimeException('gap_not_found');
        }
        $this->gaps->markIgnored($gapId);
        ActivityLogger::log('timetable.gap_ignored', 'timetable_gaps', $gapId, null);
    }

    /**
     * Reopens a gap. If resolving it created a lesson, that lesson is removed
     * so the slot is genuinely open again instead of holding a lesson the
     * school just un-decided.
     */
    public function reopen(int $gapId, int $yearId): void
    {
        $gap = $this->gaps->find($gapId, $yearId);
        if ($gap === null) {
            throw new RuntimeException('gap_not_found');
        }
        if (!empty($gap['resolved_timetable_id'])) {
            $lesson = $this->timetables->find((int) $gap['resolved_timetable_id']);
            if ($lesson !== null && (int) $lesson['academic_year_id'] === $yearId) {
                $this->timetables->delete((int) $lesson['id']);
            }
        }
        $this->gaps->reopen($gapId);
        ActivityLogger::log('timetable.gap_reopened', 'timetable_gaps', $gapId, null);
    }

    /**
     * One row per printed name: the staff record that currently represents it,
     * how many lessons it carries, and how many gaps are waiting on it.
     *
     * @return list<array<string,mixed>>
     */
    public function staffOverview(int $yearId): array
    {
        $teachers = $this->teachers->all('active');
        $lessons = $this->gaps->lessonCountsByTeacher($yearId);
        $openGaps = $this->gaps->openCountsByPrintedTeacher($yearId);
        $unregistered = array_flip($this->gaps->unregisteredPrintedNames($yearId));

        $rows = [];
        $known = [];
        foreach ($teachers as $teacher) {
            $id = (int) $teacher['id'];
            $name = (string) $teacher['full_name'];
            $aliasList = array_column($this->aliases->forTeacher($id), 'alias');
            $known[$name] = true;
            foreach ($aliasList as $alias) {
                $known[(string) $alias] = true;
            }
            // Gaps are counted against the printed text, which may be the
            // current name or a name the teacher used to be printed under.
            $gapCount = $openGaps[$name] ?? 0;
            foreach ($aliasList as $alias) {
                $gapCount += $openGaps[(string) $alias] ?? 0;
            }
            $rows[] = [
                'teacher_id' => $id,
                'printed_name' => $aliasList[0] ?? $name,
                'teacher_name' => $name,
                'teacher_code' => $teacher['teacher_code'],
                'lessons' => $lessons[$id] ?? 0,
                'gaps' => $gapCount,
                'aliases' => $aliasList,
            ];
        }

        foreach (array_keys($unregistered) as $printed) {
            if (isset($known[(string) $printed])) {
                continue;
            }
            $rows[] = [
                'teacher_id' => null,
                'printed_name' => (string) $printed,
                'teacher_name' => null,
                'teacher_code' => null,
                'lessons' => 0,
                'gaps' => $openGaps[(string) $printed] ?? 0,
                'aliases' => [],
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return [$b['gaps'], $b['lessons'], $a['printed_name']] <=> [$a['gaps'], $a['lessons'], $b['printed_name']];
        });

        return $rows;
    }

    /**
     * Renames the staff record that was auto-created from a printed first name
     * to the person's real full name. Lessons join the record by id, so the
     * whole timetable follows the rename immediately; the printed text is kept
     * as an alias so the source grid can still be traced back.
     */
    public function renameTeacher(int $teacherId, string $fullName, string $printedName): void
    {
        $teacher = $this->teachers->find($teacherId);
        if ($teacher === null) {
            throw new RuntimeException('teacher_invalid');
        }

        $fullName = trim((string) preg_replace('/\s+/u', ' ', $fullName));
        if ($fullName === '' || mb_strlen($fullName) < 2) {
            throw new RuntimeException('name_required');
        }
        if (mb_strlen($fullName) > 150) {
            throw new RuntimeException('name_too_long');
        }

        (new TeacherService())->update(
            $teacherId,
            $fullName,
            $teacher['phone'] !== null ? (string) $teacher['phone'] : null,
            $teacher['email'] !== null ? (string) $teacher['email'] : null
        );

        $printedName = trim($printedName);
        if ($printedName !== '' && $printedName !== $fullName) {
            $this->aliases->attach($teacherId, $printedName);
        }
        ActivityLogger::log('teacher.rename_from_timetable', 'teachers', $teacherId, 'Printed name: ' . $printedName);
    }

    /** Records that a real staff member is known by the printed first name. */
    public function attachAlias(int $teacherId, string $alias): void
    {
        $alias = trim($alias);
        if ($alias === '') {
            throw new RuntimeException('alias_required');
        }
        if (mb_strlen($alias) > 150) {
            throw new RuntimeException('alias_too_long');
        }
        if ($this->teachers->find($teacherId) === null) {
            throw new RuntimeException('teacher_invalid');
        }

        $this->aliases->attach($teacherId, $alias);
        ActivityLogger::log('teacher.alias_attach', 'teachers', $teacherId, 'Printed name: ' . $alias);
    }

    /**
     * Creates the second staff record that a shared first name needs, and
     * attaches the printed text to it.
     */
    public function createTeacherFromPrinted(string $printed, string $fullName): int
    {
        $printed = trim($printed);
        $fullName = trim((string) preg_replace('/\s+/u', ' ', $fullName));

        if ($printed === '' || mb_strlen($printed) > 150) {
            throw new RuntimeException('alias_required');
        }
        if ($fullName === '' || mb_strlen($fullName) < 2) {
            throw new RuntimeException('name_required');
        }
        if (mb_strlen($fullName) > 150) {
            throw new RuntimeException('name_too_long');
        }

        $id = (new TeacherService())->create($fullName, null, null);
        $this->aliases->attach($id, $printed);
        ActivityLogger::log('teacher.alias_create', 'teachers', $id, 'Created from printed name: ' . $printed);

        return $id;
    }
}

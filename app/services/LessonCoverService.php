<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LessonCoverRepository;
use App\Repositories\TeacherRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * Nizam -- assign a substitute to one dated lesson.
 *
 * The weekly timetable is the school's official schedule. Cover exists for
 * the day a teacher is absent: it names who takes that lesson on that date and
 * never rewrites the grid. A teacher cannot cover two classes in the same
 * period, cannot cover their own lesson, and cannot cover while on approved
 * leave for that date.
 */
final class LessonCoverService
{
    public function __construct(
        private readonly LessonCoverRepository $covers = new LessonCoverRepository(),
        private readonly TeacherRepository $teachers = new TeacherRepository(),
    ) {
    }

    /** ISO school day used by timetables: Sunday=2 ... Thursday=6; null on Friday/Saturday. */
    public static function schoolDay(string $date): ?int
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new RuntimeException('invalid_date');
        }

        return match ((int) $parsed->format('N')) {
            7 => 2, // Sunday
            1 => 3, // Monday
            2 => 4, // Tuesday
            3 => 5, // Wednesday
            4 => 6, // Thursday
            default => null,
        };
    }

    /**
     * @return array{date:string, day:?int, lessons:list<array<string,mixed>>, teachers:list<array<string,mixed>>, bookings:array<int,array<int,string>>, on_leave:array<int,true>}
     */
    public function board(int $yearId, string $date): array
    {
        $day = self::schoolDay($date);
        if ($day === null) {
            return [
                'date' => $date,
                'day' => null,
                'lessons' => [],
                'teachers' => $this->teachers->all('active'),
                'bookings' => [],
                'on_leave' => $this->covers->onLeave($date),
            ];
        }

        return [
            'date' => $date,
            'day' => $day,
            'lessons' => $this->covers->lessonsForDate($yearId, $day, $date),
            'teachers' => $this->teachers->all('active'),
            'bookings' => $this->covers->bookingsForDate($yearId, $day, $date),
            'on_leave' => $this->covers->onLeave($date),
        ];
    }

    /** @throws RuntimeException 'invalid_date'|'non_school_day'|'lesson_missing'|'teacher_invalid'|'same_teacher'|'teacher_on_leave'|'teacher_busy' */
    public function assign(
        int $yearId,
        string $date,
        int $timetableId,
        int $coverTeacherId,
        ?int $userId,
        ?string $note = null
    ): void {
        $day = self::schoolDay($date);
        if ($day === null) {
            throw new RuntimeException('non_school_day');
        }

        $lesson = $this->covers->findLesson($timetableId, $yearId);
        if ($lesson === null || (int) $lesson['day_of_week'] !== $day) {
            throw new RuntimeException('lesson_missing');
        }

        $teacher = $this->teachers->find($coverTeacherId);
        if ($teacher === null || (string) $teacher['status'] !== 'active') {
            throw new RuntimeException('teacher_invalid');
        }
        if ($coverTeacherId === (int) $lesson['teacher_id']) {
            throw new RuntimeException('same_teacher');
        }
        if (isset($this->covers->onLeave($date)[$coverTeacherId])) {
            throw new RuntimeException('teacher_on_leave');
        }
        if ($this->covers->busyWith($yearId, $date, $day, (int) $lesson['period_number'], $coverTeacherId, $timetableId) !== null) {
            throw new RuntimeException('teacher_busy');
        }

        $note = $note !== null ? trim($note) : null;
        $this->covers->assign(
            $yearId,
            $date,
            $timetableId,
            (int) $lesson['class_id'],
            (int) $lesson['period_number'],
            (int) $lesson['teacher_id'],
            $coverTeacherId,
            $note !== '' ? mb_substr($note, 0, 255) : null,
            $userId
        );

        ActivityLogger::log(
            'lesson_cover.assign',
            'timetables',
            $timetableId,
            "Substitute cover for {$lesson['class_name']} period {$lesson['period_number']} on {$date}",
            ['cover_teacher_id' => $coverTeacherId, 'date' => $date]
        );
    }

    /** @throws RuntimeException 'invalid_date' */
    public function remove(string $date, int $timetableId): void
    {
        self::schoolDay($date);
        $this->covers->remove($date, $timetableId);
        ActivityLogger::log(
            'lesson_cover.remove',
            'timetables',
            $timetableId,
            "Substitute cover removed for lesson {$timetableId} on {$date}",
            ['date' => $date]
        );
    }
}

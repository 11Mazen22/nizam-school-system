<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Repositories\AttendanceRepository;

/**
 * Universal attendance engine for all grades and both Egyptian tracks.
 *
 * Two independent questions are answered here:
 *   1. Exam eligibility — attended days / actual school days >= 85% (configurable).
 *   2. Truancy action level — consecutive and separate unexcused absence days,
 *      with warning (10 / 20) and administrative-action (15 / 30) thresholds.
 *
 * Only school days from the school's configured working week count. Excused
 * absence counts as attended for eligibility but never as truancy.
 */
final class AttendancePolicyService
{
    private static array $schoolDaysCache = [];

    public function __construct(
        private readonly AttendanceRepository $repo = new AttendanceRepository(),
    ) {
    }

    /** @return list<string> YYYY-MM-DD school days in the inclusive range */
    public function schoolDays(int $yearId, ?string $from = null, ?string $to = null): array
    {
        $cacheKey = $yearId . '|' . ($from ?? '') . '|' . ($to ?? '');
        if (isset(self::$schoolDaysCache[$cacheKey])) {
            return self::$schoolDaysCache[$cacheKey];
        }

        $pdo = Database::connection();
        $yearStmt = $pdo->prepare('SELECT start_date, end_date FROM academic_years WHERE id = :id');
        $yearStmt->execute(['id' => $yearId]);
        $year = $yearStmt->fetch();
        if ($year === false) {
            return self::$schoolDaysCache[$cacheKey] = [];
        }

        $start = $from !== null && $from !== '' ? $from : (string) $year['start_date'];
        $end = $to !== null && $to !== '' ? $to : (string) $year['end_date'];
        if ($start === '' || $end === '' || strtotime($start) > strtotime($end)) {
            return self::$schoolDaysCache[$cacheKey] = [];
        }

        $weekStmt = $pdo->query('SELECT school_week FROM schools ORDER BY id LIMIT 1');
        $weekRaw = (string) ($weekStmt->fetchColumn() ?: '0,1,2,3,4');
        $weekDays = array_map('intval', array_filter(array_map('trim', explode(',', $weekRaw)), 'ctype_digit'));
        if ($weekDays === []) {
            $weekDays = [0, 1, 2, 3, 4];
        }

        $days = [];
        $cursor = strtotime($start);
        $last = strtotime($end);
        while ($cursor !== false && $cursor <= $last) {
            if (in_array((int) date('w', $cursor), $weekDays, true)) {
                $days[] = date('Y-m-d', $cursor);
            }
            $cursor = strtotime('+1 day', $cursor); // DST-safe: Egypt changes clocks in October
        }
        return self::$schoolDaysCache[$cacheKey] = $days;
    }

    public function schoolDaysElapsed(int $yearId, ?string $asOf = null): int
    {
        $to = $asOf ?? date('Y-m-d');
        return count($this->schoolDays($yearId, null, $to));
    }

    /**
     * @return array{
     *   school_days:int, attended:int, absent:int, consecutive:int,
     *   percent:?float, eligible:bool, level:string, rules:array<string,mixed>
     * }
     */
    public function studentStatus(int $studentId, int $yearId, ?string $asOf = null): array
    {
        $asOf = $asOf ?? date('Y-m-d');
        $rules = AcademicRulesService::attendanceRules();
        $schoolDays = $this->schoolDays($yearId, null, $asOf);
        $schoolDaySet = array_flip($schoolDays);
        $statuses = $this->repo->statusesForStudent($studentId, $yearId, $asOf);

        $attended = 0;
        $absent = 0;
        foreach ($statuses as $date => $status) {
            if (!isset($schoolDaySet[$date])) {
                continue; // weekend / holiday never counts against the student
            }
            if (in_array($status, $rules['attended_statuses'], true)) {
                $attended++;
            } elseif (in_array($status, $rules['truancy_statuses'], true)) {
                $absent++;
            }
        }

        // Current consecutive unexcused streak, walking backwards over school days.
        $consecutive = 0;
        for ($i = count($schoolDays) - 1; $i >= 0; $i--) {
            $date = $schoolDays[$i];
            $status = $statuses[$date] ?? null;
            if ($status !== null && in_array($status, $rules['truancy_statuses'], true)) {
                $consecutive++;
                continue;
            }
            if ($status === null) {
                continue; // a day with no record does not break the streak
            }
            break;
        }

        $elapsed = count($schoolDays);
        $percent = $elapsed > 0 ? round(min(100, $attended / $elapsed * 100), 1) : null;
        $level = 'ok';
        if ($consecutive >= $rules['critical_consecutive'] || $absent >= $rules['critical_separate']) {
            $level = 'critical';
        } elseif ($consecutive >= $rules['warning_consecutive'] || $absent >= $rules['warning_separate']) {
            $level = 'warning';
        }

        return [
            'school_days' => $elapsed,
            'attended' => $attended,
            'absent' => $absent,
            'consecutive' => $consecutive,
            'percent' => $percent,
            'eligible' => $percent === null || $percent >= $rules['min_percent'],
            'level' => $level,
            'rules' => $rules,
        ];
    }

    /**
     * Students whose absence level is warning/critical, for one class or the year.
     *
     * @return list<array<string,mixed>>
     */
    public function alertsForYear(int $yearId, ?int $classId = null, ?string $asOf = null): array
    {
        $asOf = $asOf ?? date('Y-m-d');
        $schoolDaySet = array_flip($this->schoolDays($yearId, null, $asOf));
        $all = $this->repo->statusesForYear($yearId, $asOf);
        $rules = AcademicRulesService::attendanceRules();
        $alerts = [];

        $pdo = Database::connection();
        $studentClass = [];
        $sql = 'SELECT e.student_id, e.class_id, s.full_name, s.student_code, c.name AS class_name
                  FROM student_enrollments e
                  JOIN students s ON s.id = e.student_id
                  LEFT JOIN classes c ON c.id = e.class_id
                  WHERE e.academic_year_id = :year AND e.status = :status';
        $params = ['year' => $yearId, 'status' => 'active'];
        if ($classId !== null) {
            $sql .= ' AND e.class_id = :class';
            $params['class'] = $classId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $studentClass[(int) $row['student_id']] = $row;
        }

        foreach ($all as $studentId => $statuses) {
            if (!isset($studentClass[$studentId])) {
                continue;
            }
            $absent = 0;
            foreach ($statuses as $date => $status) {
                if (isset($schoolDaySet[$date]) && in_array($status, $rules['truancy_statuses'], true)) {
                    $absent++;
                }
            }
            $status = $this->studentStatus($studentId, $yearId, $asOf);
            if ($status['level'] === 'ok') {
                continue;
            }
            $row = $studentClass[$studentId];
            $alerts[] = [
                'student_id' => $studentId,
                'full_name' => (string) $row['full_name'],
                'student_code' => (string) $row['student_code'],
                'class_name' => (string) ($row['class_name'] ?? ''),
                'absent' => $absent,
                'consecutive' => $status['consecutive'],
                'percent' => $status['percent'],
                'level' => $status['level'],
            ];
        }
        usort($alerts, static fn (array $a, array $b): int => $b['absent'] <=> $a['absent']);
        return $alerts;
    }

    public static function clearCache(): void
    {
        self::$schoolDaysCache = [];
    }

    /** True when the given date (default today) is in the school's working week. */
    public static function isSchoolDay(?string $date = null): bool
    {
        $weekday = (int) date('w', strtotime($date ?? date('Y-m-d')));
        $stmt = Database::connection()->query('SELECT school_week FROM schools ORDER BY id LIMIT 1');
        $raw = (string) ($stmt->fetchColumn() ?: '0,1,2,3,4');
        $days = array_map('intval', array_filter(array_map('trim', explode(',', $raw)), 'ctype_digit'));
        return in_array($weekday, $days === [] ? [0, 1, 2, 3, 4] : $days, true);
    }
}

<?php

declare(strict_types=1);

/**
 * Contract tests for the configurable Egyptian academic rules engine.
 */

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';
restore_exception_handler();

use App\Repositories\AttendanceRepository;
use App\Services\AcademicRulesService as Rules;
use App\Services\AttendancePolicyService;
use App\Services\YearWorkService;

$passed = 0;
$failed = 0;
function ruleCheck(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ok    {$label}\n";
    } else {
        $failed++;
        echo "  FAIL  {$label}\n";
    }
}

echo "Academic rules engine\n" . str_repeat('=', 60) . "\n";

$config = Rules::config();
ruleCheck((int) ($config['version'] ?? 0) === 1, 'Committed academic-rules package is version 1');
ruleCheck(count(Rules::tracks()) === 2, 'Both Egyptian tracks are configured');

ruleCheck(
    Rules::tracksForGrade(3, '2026/2027') === ['GENERAL_SECONDARY'],
    'Grade 12 in 2026/2027 is General Secondary only'
);
ruleCheck(
    Rules::tracksForGrade(3, '2027/2028') === ['GENERAL_SECONDARY', 'BACCALAUREATE'],
    'Grade 12 in 2027/2028 supports both tracks'
);
ruleCheck(
    in_array('BACCALAUREATE', Rules::tracksForGrade(2, '2026/2027'), true),
    'Grade 11 in 2026/2027 includes Baccalaureate'
);
ruleCheck(Rules::gradeLevelFromClass('C07') === 3, 'Class C07 maps to grade level 3');
ruleCheck(Rules::gradeLevelFromClass('B4') === 2, 'Class B4 maps to grade level 2');

$attendance = Rules::attendanceRules();
ruleCheck($attendance['min_percent'] === 85, 'Minimum eligibility attendance is 85%');
ruleCheck($attendance['warning_consecutive'] === 10 && $attendance['warning_separate'] === 20, 'Warning thresholds are 10 / 20');
ruleCheck($attendance['critical_consecutive'] === 15 && $attendance['critical_separate'] === 30, 'Action thresholds are 15 / 30');
ruleCheck(in_array('excused', $attendance['attended_statuses'], true), 'Excused absence counts as attended');

$bacG11 = Rules::yearWorkRules('BACCALAUREATE', 2, '2026/2027');
$bacG12 = Rules::yearWorkRules('BACCALAUREATE', 3, '2026/2027');
$bacG12Next = Rules::yearWorkRules('BACCALAUREATE', 3, '2027/2028');
ruleCheck($bacG11['enabled'] === true && $bacG11['completion_percent'] === 60, 'Baccalaureate year-work is active with a 60% requirement');
ruleCheck($bacG12['enabled'] === false, 'Grade 12 General Secondary 2026/2027 has no year-work');
ruleCheck($bacG12Next['enabled'] === true, 'Grade 12 year-work activates in 2027/2028');
ruleCheck(array_sum($bacG11['weights']) === 100, 'Year-work component weights total 100%');

// Policy-level checks with an injected attendance source (no DB writes).
$yearId = (int) (App\Database::connection()->query('SELECT id FROM academic_years ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
$asOf = '2026-11-18';
$days = [];
if ($yearId > 0) {
    $probe = new AttendancePolicyService();
    $days = $probe->schoolDays($yearId, null, $asOf);
}
ruleCheck($days !== [] && count($days) === count(array_unique($days)), 'School days are unique and non-empty');
ruleCheck($days === [] || in_array((int) date('w', strtotime($days[0])), [0, 1, 2, 3, 4], true), 'Only configured school weekdays count');

if ($days !== []) {
    $makeRepo = static fn (array $statuses): AttendanceRepository => new class($statuses) extends AttendanceRepository {
        public function __construct(private readonly array $map) {}
        public function statusesForStudent(int $studentId, int $yearId, ?string $to = null): array
        {
            return $this->map;
        }
    };

    $presentMap = array_fill_keys($days, 'present');
    $present = (new AttendancePolicyService($makeRepo($presentMap)))->studentStatus(1, $yearId, $asOf);
    ruleCheck($present['percent'] === 100.0 && $present['eligible'] && $present['level'] === 'ok', 'Full attendance is eligible with no warning');

    $absentMap = $presentMap;
    foreach (array_slice($days, -12) as $date) {
        $absentMap[$date] = 'absent';
    }
    $warning = (new AttendancePolicyService($makeRepo($absentMap)))->studentStatus(1, $yearId, $asOf);
    ruleCheck($warning['level'] === 'warning' && $warning['consecutive'] === 12, '12 consecutive absences raise a warning');

    $absentMap = $presentMap;
    foreach (array_slice($days, -16) as $date) {
        $absentMap[$date] = 'absent';
    }
    $critical = (new AttendancePolicyService($makeRepo($absentMap)))->studentStatus(1, $yearId, $asOf);
    ruleCheck($critical['level'] === 'critical' && !$critical['eligible'], '16 consecutive absences require administrative action');
}

$ywService = new YearWorkService();
ruleCheck(method_exists($ywService, 'statusForStudent') && method_exists($ywService, 'record'), 'Year-work engine exposes status and recording');

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);

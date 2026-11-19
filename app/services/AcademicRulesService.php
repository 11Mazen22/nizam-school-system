<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Egyptian secondary-education rules, loaded from database/official/academic-rules.json.
 *
 * Everything that used to be scattered as constants (absence thresholds, year-work
 * completion, which track is allowed in which grade/year) is resolved here. A
 * small set of global overrides is read from the typed settings table so an
 * administrator can tune numbers without editing the committed config; the
 * year/grade/track matrix itself stays in the versioned JSON package.
 */
final class AcademicRulesService
{
    private const CONFIG_PATH = __DIR__ . '/../../database/official/academic-rules.json';
    private const CONFIG_VERSION = 1;

    private static ?array $config = null;

    /** @return array<string,mixed> */
    public static function config(): array
    {
        if (self::$config === null) {
            if (!is_file(self::CONFIG_PATH)) {
                throw new RuntimeException('academic_rules_missing');
            }
            $config = json_decode((string) file_get_contents(self::CONFIG_PATH), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($config) || (int) ($config['version'] ?? 0) !== self::CONFIG_VERSION) {
                throw new RuntimeException('academic_rules_version');
            }
            self::$config = $config;
        }
        return self::$config;
    }

    /** Discard the in-process cache (used by tests after editing settings). */
    public static function reload(): void
    {
        self::$config = null;
    }

    public static function activeYearLabel(): string
    {
        return (string) (AcademicYearContext::label() ?? '');
    }

    /** @return array<string,array{name_ar:string,name_en:string}> */
    public static function tracks(): array
    {
        return (array) (self::config()['tracks'] ?? []);
    }

    public static function trackName(string $track, string $locale = 'ar'): string
    {
        $key = $locale === 'ar' ? 'name_ar' : 'name_en';
        return (string) (self::tracks()[$track][$key] ?? $track);
    }

    /** Grade level follows grades.sort_order: 1, 2 or 3. */
    public static function gradeLevelFromClass(string $className): int
    {
        $prefix = strtoupper(substr(trim($className), 0, 1));
        return (int) ((self::config()['class_prefixes'] ?? [])[$prefix] ?? 1);
    }

    /** @return list<string> tracks enabled for this grade in this year */
    public static function tracksForGrade(int $gradeLevel, ?string $yearLabel = null): array
    {
        $year = $yearLabel ?? self::activeYearLabel();
        $matrix = (array) (self::config()['grade_tracks'] ?? []);
        $tracks = (array) ($matrix[$year][(string) $gradeLevel] ?? (self::config()['default_grade_tracks'] ?? []));
        return array_values(array_filter($tracks, static fn ($track): bool => is_string($track) && $track !== ''));
    }

    public static function isTrackEnabled(string $track, int $gradeLevel, ?string $yearLabel = null): bool
    {
        return in_array($track, self::tracksForGrade($gradeLevel, $yearLabel), true);
    }

    /** Deterministic default when a student has no track recorded yet. */
    public static function defaultTrackForGrade(int $gradeLevel, ?string $yearLabel = null): string
    {
        $tracks = self::tracksForGrade($gradeLevel, $yearLabel);
        if (in_array('GENERAL_SECONDARY', $tracks, true)) {
            return 'GENERAL_SECONDARY';
        }
        return $tracks[0] ?? 'GENERAL_SECONDARY';
    }

    /** Resolve a student's track from their enrollment, falling back to the year/grade default. */
    public static function trackForEnrollment(int $studentId, int $yearId, int $gradeLevel, ?string $yearLabel = null): string
    {
        static $cache = [];
        $key = $studentId . '|' . $yearId;
        if (!array_key_exists($key, $cache)) {
            $stmt = \App\Database::connection()->prepare(
                'SELECT academic_track FROM student_enrollments
                  WHERE student_id = :s AND academic_year_id = :y ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['s' => $studentId, 'y' => $yearId]);
            $cache[$key] = (string) ($stmt->fetchColumn() ?: '');
        }
        $stored = $cache[$key];
        if ($stored !== '' && self::isTrackEnabled($stored, $gradeLevel, $yearLabel)) {
            return $stored;
        }
        return self::defaultTrackForGrade($gradeLevel, $yearLabel);
    }

    /** @return array{min_percent:int,warning_consecutive:int,warning_separate:int,critical_consecutive:int,critical_separate:int,truancy_statuses:list<string>,attended_statuses:list<string>} */
    public static function attendanceRules(?string $yearLabel = null): array
    {
        $rules = (array) (self::config()['attendance'] ?? []);
        $warning = (array) ($rules['warning'] ?? []);
        $critical = (array) ($rules['critical'] ?? []);
        return [
            'min_percent' => (int) SettingsService::get('academic.attendance_min_percent', $rules['min_attendance_percent'] ?? 85),
            'warning_consecutive' => (int) SettingsService::get('academic.attendance_warning_consecutive', $warning['consecutive_absences'] ?? 10),
            'warning_separate' => (int) SettingsService::get('academic.attendance_warning_separate', $warning['separate_absences'] ?? 20),
            'critical_consecutive' => (int) SettingsService::get('academic.attendance_critical_consecutive', $critical['consecutive_absences'] ?? 15),
            'critical_separate' => (int) SettingsService::get('academic.attendance_critical_separate', $critical['separate_absences'] ?? 30),
            'truancy_statuses' => array_values((array) ($rules['truancy_statuses'] ?? ['absent'])),
            'attended_statuses' => array_values((array) ($rules['attended_statuses'] ?? ['present', 'late', 'excused'])),
        ];
    }

    /**
     * Year-work (أعمال السنة) is a Baccalaureate-track feature and is never
     * derived from attendance. Grade 12 General Secondary in 2026/2027 is
     * explicitly excluded by the committed matrix.
     *
     * @return array{enabled:bool,completion_percent:int,weights:array<string,int>,track:string}
     */
    public static function yearWorkRules(string $track, int $gradeLevel, ?string $yearLabel = null): array
    {
        $year = $yearLabel ?? self::activeYearLabel();
        $rules = (array) (self::config()['year_work'] ?? []);
        $enabledTracks = array_values((array) ($rules['enabled_tracks'] ?? []));
        $disabled = (array) ($rules['disabled_grade_levels_by_year'] ?? []);
        $disabledForYear = array_map('intval', (array) ($disabled[$year] ?? []));

        return [
            'enabled' => in_array($track, $enabledTracks, true)
                && !in_array($gradeLevel, $disabledForYear, true)
                && self::isTrackEnabled($track, $gradeLevel, $year),
            'completion_percent' => (int) SettingsService::get('academic.year_work_completion_percent', $rules['completion_requirement_percent'] ?? 60),
            'weights' => array_map('intval', (array) ($rules['weights'] ?? [])),
            'track' => $track,
        ];
    }
}

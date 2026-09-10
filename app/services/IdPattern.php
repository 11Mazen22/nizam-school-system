<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Nizam -- renders academic.student_id_pattern / academic.teacher_id_pattern
 * (§J, decision #10) against a row's own id. Always called insert-then-
 * update (§O-7, O-22 -- the id must already exist, never a precomputed
 * MAX+1): {seq:N} becomes that id zero-padded to N digits; {year} becomes
 * the active academic year's label's first 4 characters ("2025/2026" ->
 * "2025") -- the year a student's record was actually opened in, which is
 * the only "year" a student-creation moment has a real answer for. Not used
 * by the teacher pattern (TCH-{seq:6} has no {year} token), so $year is
 * optional.
 */
final class IdPattern
{
    public static function render(string $pattern, int $id, ?string $year = null): string
    {
        $result = preg_replace_callback('/\{seq:(\d+)\}/', function (array $m) use ($id): string {
            return str_pad((string) $id, (int) $m[1], '0', STR_PAD_LEFT);
        }, $pattern) ?? $pattern;

        if ($year !== null) {
            $result = str_replace('{year}', $year, $result);
        }

        return $result;
    }
}

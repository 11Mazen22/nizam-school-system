<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SchoolRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reads the school-wide identity and operational calendar from the single
 * `schools` row. All consumers (shell branding, attendance, timetable,
 * finance formatting, reports) go through here so a Settings -> Profile save
 * is immediately reflected everywhere without page-specific copies.
 */
final class SchoolSettingsService
{
    /** PHP weekday numbers (0=Sunday .. 6=Saturday). */
    public const WEEKDAYS = [
        0 => 'sunday', 1 => 'monday', 2 => 'tuesday', 3 => 'wednesday',
        4 => 'thursday', 5 => 'friday', 6 => 'saturday',
    ];

    private static ?array $cache = null;

    public static function reload(): void
    {
        self::$cache = null;
    }

    public static function profile(): array
    {
        if (self::$cache === null) {
            self::$cache = (new SchoolRepository())->full() ?? [];
        }
        return self::$cache;
    }

    /** @return list<int> normalized distinct PHP weekday numbers */
    public static function workingWeekdays(): array
    {
        $raw = trim((string) (self::profile()['school_week'] ?? '0,1,2,3,4'));
        if ($raw === '') {
            return [0, 1, 2, 3, 4];
        }
        $days = [];
        foreach (explode(',', $raw) as $piece) {
            $day = filter_var(trim($piece), FILTER_VALIDATE_INT);
            if ($day !== false && $day >= 0 && $day <= 6 && !in_array($day, $days, true)) {
                $days[] = $day;
            }
        }
        sort($days);
        return $days === [] ? [0, 1, 2, 3, 4] : $days;
    }

    /**
     * Timetables number days Saturday(1) through Thursday(6). Convert the
     * configured PHP weekday set to that numbering so disabled days can be
     * hidden in the grid and rejected on write.
     *
     * @return list<int>
     */
    public static function timetableDays(): array
    {
        $days = [];
        foreach (self::workingWeekdays() as $phpDay) {
            // PHP Sunday=0 maps to timetable Sunday=2; Saturday=6 maps to 1.
            $mapped = ($phpDay + 2) % 7;
            if ($mapped >= 1 && $mapped <= 6) {
                $days[] = $mapped;
            }
        }
        sort($days);
        return $days;
    }

    public static function isWorkingDate(string $date): bool
    {
        try {
            $parsed = new DateTimeImmutable($date);
        } catch (\Exception) {
            return false;
        }
        return in_array((int) $parsed->format('w'), self::workingWeekdays(), true);
    }

    public static function timezone(): string
    {
        $name = trim((string) (self::profile()['timezone'] ?? 'Africa/Cairo'));
        try {
            return (new DateTimeZone($name))->getName();
        } catch (\Exception) {
            return 'Africa/Cairo';
        }
    }

    public static function currencyCode(): string
    {
        $code = strtoupper(trim((string) (self::profile()['currency'] ?? 'EGP')));
        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : 'EGP';
    }

    /** Locale-aware currency symbol/code for display. */
    public static function currencySymbol(): string
    {
        $symbols = [
            'EGP' => 'E£', 'SAR' => '﷼', 'AED' => 'د.إ', 'USD' => '$', 'EUR' => '€',
            'GBP' => '£', 'KWD' => 'د.ك', 'QAR' => '﷼', 'JOD' => 'د.ا', 'MAD' => 'د.م.',
        ];
        return $symbols[self::currencyCode()] ?? self::currencyCode();
    }

    /** Format every money amount with the school's configured base currency. */
    public static function money(float|int|string $amount, ?string $locale = null): string
    {
        $locale ??= LocaleService::current();
        $value = (float) $amount;
        $digits = (int) SettingsService::get('finance.decimal_places', 2);
        $digits = $digits >= 0 && $digits <= 4 ? $digits : 2;
        $formatted = number_format($value, $digits, '.', ',');
        $symbol = self::currencySymbol();
        $position = SettingsService::get('finance.currency_position', 'after');
        $space = LocaleService::normalize($locale) === 'ar' ? ' ' : ' ';
        return $position === 'before' ? $symbol . $space . $formatted : $formatted . $space . $symbol;
    }

    /** School display name in the active locale. */
    public static function displayName(?string $locale = null): string
    {
        $profile = self::profile();
        $locale ??= LocaleService::current();
        if ($locale === 'ar') {
            return (string) ($profile['name_ar'] ?? $profile['name'] ?? 'Nizam');
        }
        return (string) ($profile['name'] ?? 'Nizam');
    }

    /** Validate/normalize a posted working-days set; throws on invalid input. */
    public static function normalizeWeekdays(mixed $input): string
    {
        if (is_array($input)) {
            $pieces = $input;
        } elseif (is_string($input)) {
            $pieces = explode(',', $input);
        } else {
            $pieces = [];
        }
        $days = [];
        foreach ($pieces as $piece) {
            $day = filter_var(trim((string) $piece), FILTER_VALIDATE_INT);
            if ($day === false || $day < 0 || $day > 6) {
                throw new InvalidArgumentException('school_week_invalid');
            }
            if (!in_array($day, $days, true)) {
                $days[] = $day;
            }
        }
        if ($days === []) {
            throw new InvalidArgumentException('school_week_empty');
        }
        sort($days);
        self::reload();
        return implode(',', $days);
    }

    public static function validateTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        try {
            return (new DateTimeZone($timezone))->getName();
        } catch (\Exception) {
            throw new InvalidArgumentException('timezone_invalid');
        }
    }

    public static function validateCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('currency_invalid');
        }
        return $currency;
    }

    /** Date interval helper used by attendance reporting for configured rules. */
    public static function addDays(string $date, int $days): string
    {
        $tz = new DateTimeZone(self::timezone());
        return (new DateTimeImmutable($date, $tz))->add(new DateInterval('P' . abs($days) . 'D'))->format('Y-m-d');
    }

    public static function requireProfile(): array
    {
        $profile = self::profile();
        if ($profile === []) {
            throw new RuntimeException('school_missing');
        }
        return $profile;
    }
}

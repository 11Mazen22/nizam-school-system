<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Repositories\SchoolRepository;
use App\Services\AcademicYearService;
use App\Services\SettingsService;
use App\Services\UploadService;

/**
 * The institutional identity every NIZAM document is composed from.
 *
 * One resolution point: school name in both languages, the optional
 * ministry/directorate/administration lines, contact details, the official
 * seal, the active academic year and the issue date. Nothing here is
 * hard-coded per template -- a Settings -> Profile save is reflected in the
 * very next document, and a field the school has not configured is omitted
 * rather than rendered as an empty label or a placeholder.
 */
final class DocumentIdentity
{
    public function __construct(
        public readonly string $nameAr,
        public readonly string $nameEn,
        public readonly ?string $logoDataUri,
        public readonly string $ministry,
        public readonly string $directorate,
        public readonly string $administration,
        public readonly string $address,
        public readonly string $phone,
        public readonly string $email,
        public readonly string $yearLabel,
        public readonly string $issueDate,
        public readonly string $locale,
        public readonly bool $showSignatures,
        public readonly bool $showWatermark,
    ) {
    }

    public static function resolve(
        string $locale,
        ?string $yearLabel = null,
        ?bool $signatures = null,
        ?bool $watermark = null,
    ): self {
        $school = (new SchoolRepository())->full() ?? [];
        $nameAr = trim((string) ($school['name_ar'] ?? ''));
        $nameEn = trim((string) ($school['name'] ?? ''));

        return new self(
            nameAr: $nameAr !== '' ? $nameAr : $nameEn,
            nameEn: $nameEn !== '' ? $nameEn : $nameAr,
            logoDataUri: self::logo($school['logo_path'] ?? null),
            ministry: self::setting('document.ministry'),
            directorate: self::setting('document.directorate'),
            administration: self::setting('document.administration'),
            address: trim((string) ($school['address'] ?? '')),
            phone: trim((string) ($school['phone'] ?? '')),
            email: trim((string) ($school['email'] ?? '')),
            yearLabel: $yearLabel ?? (string) (\App\Middleware\AcademicYearContext::label() ?? ''),
            issueDate: self::issueDate(),
            locale: $locale === 'en' ? 'en' : 'ar',
            showSignatures: $signatures ?? self::flag('document.signatures_enabled', true),
            showWatermark: $watermark ?? self::flag('document.watermark_enabled', false),
        );
    }

    public function direction(): string
    {
        return $this->locale === 'ar' ? 'rtl' : 'ltr';
    }

    /** Official name in the active document language, falling back to the other. */
    public function name(): string
    {
        return $this->locale === 'ar' ? $this->nameAr : $this->nameEn;
    }

    /** The optional Arabic identity lines, in the order an Egyptian school document prints them. */
    public function institutionalLines(): array
    {
        return array_values(array_filter(
            [$this->ministry, $this->directorate, $this->administration],
            static fn (string $line): bool => $line !== ''
        ));
    }

    /** Contact line for the English column -- only the parts actually configured. */
    public function contactLines(): array
    {
        $lines = [];
        if ($this->address !== '') {
            $lines[] = $this->address;
        }
        if ($this->phone !== '') {
            $lines[] = $this->phone;
        }
        if ($this->email !== '') {
            $lines[] = $this->email;
        }

        return $lines;
    }

    /**
     * Human-readable issue date (never an ISO timestamp): the school's own
     * authority is the document, so the date must read the way a document
     * reads, not the way a log line reads.
     */
    private static function issueDate(): string
    {
        $timezone = \App\Services\SchoolSettingsService::timezone();
        $now = new \DateTimeImmutable('now', new \DateTimeZone($timezone));

        return $now->format('d/m/Y');
    }

    private static function setting(string $key): string
    {
        $value = SettingsService::get($key, '');
        return trim((string) (is_scalar($value) ? $value : ''));
    }

    private static function flag(string $key, bool $default): bool
    {
        $value = SettingsService::get($key, $default ? '1' : '0');
        return dbBool($value);
    }

    /**
     * The seal as a data: URI so a generated document is fully self-contained
     * -- the same bytes render in the on-screen preview, the Chromium PDF and
     * an exported PNG with no server round-trip or filesystem path guessing.
     *
     * A pre-sized derivative is used when the uploaded seal is very large:
     * base64 of a ~900KB PNG inflates every document by more than a megabyte
     * for a mark that prints at 18mm, with no visible gain at that size.
     */
    private static function logo(?string $logoPath): ?string
    {
        $contents = UploadService::retrieve($logoPath);
        $extension = strtolower(pathinfo((string) $logoPath, PATHINFO_EXTENSION));

        if ($contents !== null && strlen($contents) <= 400_000) {
            $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        }

        $fallback = dirname(__DIR__, 3) . '/public/assets/img/hadaba-logo-pdf.png';
        if (!is_file($fallback)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($fallback));
    }
}

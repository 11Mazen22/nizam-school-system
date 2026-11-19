<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentSignatoryRepository;
use RuntimeException;

/**
 * The two signatures the institution authorises -- configured once, applied
 * everywhere.
 *
 * A document type names the roles it needs (`principal`,
 * `student_affairs`, ...). This service resolves those role names into the
 * school's real configuration: the person's name, their role title, the
 * Arabic typeface that distinguishes one signatory from the other, their
 * optional signature/stamp image, and where the block repeats (every page by
 * default). It never invents a name or a mark: a role with no configured name
 * still prints its honest title and a blank signing line, exactly as the
 * institution's paper documents do before they are signed.
 */
final class SignatoryService
{
    /**
     * Self-hosted Arabic typefaces. Distinct families are a requirement, not
     * decoration: the two authorized signatures must not look like the same
     * hand. All three are SIL Open Font License and bundled with the app so a
     * PDF is self-contained.
     *
     * @var array<string, array{family:string,label:string,regular:string,bold:?string}>
     */
    public const FONTS = [
        'aref-ruqaa' => [
            'family' => 'Aref Ruqaa',
            'label' => 'settings.signatures.font.aref',
            'regular' => 'ArefRuqaa-Regular.woff2',
            'bold' => 'ArefRuqaa-Bold.woff2',
        ],
        'amiri' => [
            'family' => 'Amiri',
            'label' => 'settings.signatures.font.amiri',
            'regular' => 'Amiri-Regular.woff2',
            'bold' => 'Amiri-Bold.woff2',
        ],
        'reem-kufi' => [
            'family' => 'Reem Kufi',
            'label' => 'settings.signatures.font.reem',
            'regular' => 'ReemKufi-Regular.woff2',
            'bold' => null,
        ],
    ];

    public const PLACEMENTS = ['every_page', 'first_page', 'last_page'];

    /** Typographic controls, all bounded so a printout can never be ruined by a typo in settings. */
    public const WEIGHTS = [400, 600, 700];
    public const ALIGNMENTS = ['start', 'center', 'end'];
    public const MIN_SIZE = 8.0;
    public const MAX_SIZE = 16.0;
    public const MAX_TRACKING = 2.0;

    /** The two roles the school explicitly authorised. */
    public const REQUIRED_ROLES = ['principal', 'student_affairs'];

    private const MAX_NAME = 150;
    private const MAX_TITLE = 100;
    private const IMAGE_MAX_DIMENSION = 1400;

    /** @var array<string, array<string,mixed>>|null */
    private static ?array $rows = null;

    public function __construct(
        private readonly DocumentSignatoryRepository $repo = new DocumentSignatoryRepository(),
        private readonly UploadService $uploads = new UploadService(),
    ) {
    }

    /**
     * Presentation rows for the settings screen, in institutional order.
     *
     * @return list<array<string,mixed>>
     */
    public function settingsRows(): array
    {
        return array_map(function (array $row): array {
            $fontKey = (string) ($row['font_key'] ?? 'aref-ruqaa');

            return $row + [
                'role_label' => self::roleLabel((string) $row['role_code']),
                'font_key' => isset(self::FONTS[$fontKey]) ? $fontKey : 'aref-ruqaa',
                'font_size' => self::boundedSize($row['font_size'] ?? null),
                'font_weight' => self::boundedWeight($row['font_weight'] ?? null),
                'text_color' => self::boundedColor($row['text_color'] ?? null),
                'letter_spacing' => self::boundedTracking($row['letter_spacing'] ?? null),
                'alignment' => self::boundedAlignment($row['alignment'] ?? null),
                'placement' => in_array((string) ($row['placement'] ?? ''), self::PLACEMENTS, true)
                    ? (string) $row['placement']
                    : 'every_page',
                'signature_preview' => self::dataUri($row['signature_path'] ?? null),
                'stamp_preview' => self::dataUri($row['stamp_path'] ?? null),
                'configured' => trim((string) ($row['name_ar'] ?? '')) !== ''
                    || trim((string) ($row['name_en'] ?? '')) !== '',
            ];
        }, $this->repo->all());
    }

    /**
     * Resolve a document's declared signature roles into printable blocks.
     *
     * @param list<string> $roleCodes in the order the document wants them
     * @return list<array{
     *   role:string,name:string,title:string,font_key:string,font_family:string,
     *   signature:?string,stamp:?string,placement:string,configured:bool
     * }>
     */
    public static function resolve(array $roleCodes): array
    {
        $rows = self::rows();
        $locale = currentLocale();
        $resolved = [];

        foreach ($roleCodes as $roleCode) {
            $roleCode = (string) $roleCode;
            $row = $rows[$roleCode] ?? null;

            if ($row !== null && !dbBool($row['is_active'] ?? false)) {
                continue;
            }

            $fontKey = (string) ($row['font_key'] ?? 'aref-ruqaa');
            $font = self::FONTS[$fontKey] ?? self::FONTS['aref-ruqaa'];

            $nameAr = trim((string) ($row['name_ar'] ?? ''));
            $nameEn = trim((string) ($row['name_en'] ?? ''));
            $name = $locale === 'ar'
                ? ($nameAr !== '' ? $nameAr : $nameEn)
                : ($nameEn !== '' ? $nameEn : $nameAr);

            $roleTitle = self::roleLabel($roleCode);
            $titleAr = trim((string) ($row['title_ar'] ?? ''));
            $titleEn = trim((string) ($row['title_en'] ?? ''));
            $title = $locale === 'ar'
                ? ($titleAr !== '' ? $titleAr : ($titleEn !== '' ? $titleEn : $roleTitle))
                : ($titleEn !== '' ? $titleEn : ($titleAr !== '' ? $titleAr : $roleTitle));

            $placement = (string) ($row['placement'] ?? 'every_page');
            if (!in_array($placement, self::PLACEMENTS, true)) {
                $placement = 'every_page';
            }

            $resolved[] = [
                'role' => $roleCode,
                'name' => $name,
                'title' => $title,
                'font_key' => isset(self::FONTS[$fontKey]) ? $fontKey : 'aref-ruqaa',
                'font_family' => $font['family'],
                'font_size' => self::boundedSize($row['font_size'] ?? null),
                'font_weight' => self::boundedWeight($row['font_weight'] ?? null),
                'text_color' => self::boundedColor($row['text_color'] ?? null),
                'letter_spacing' => self::boundedTracking($row['letter_spacing'] ?? null),
                'alignment' => self::boundedAlignment($row['alignment'] ?? null),
                'signature' => self::dataUri($row['signature_path'] ?? null),
                'stamp' => self::dataUri($row['stamp_path'] ?? null),
                'placement' => $placement,
                'configured' => $name !== '',
            ];
        }

        return $resolved;
    }

    /**
     * Save one authorized signatory.
     *
     * @param array<string,mixed> $input
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $signatureFile
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $stampFile
     * @return array<string,array{old:mixed,new:mixed}> changes for the audit log
     * @throws RuntimeException 'not_found'|'validation_error'|'image_invalid'
     */
    public function save(
        int $id,
        array $input,
        ?array $signatureFile,
        ?array $stampFile,
        bool $removeSignature,
        bool $removeStamp,
    ): array {
        $row = $this->repo->find($id);
        if ($row === null) {
            throw new RuntimeException('not_found');
        }

        $nameAr = trim((string) ($input['name_ar'] ?? ''));
        $nameEn = trim((string) ($input['name_en'] ?? ''));
        $titleAr = trim((string) ($input['title_ar'] ?? ''));
        $titleEn = trim((string) ($input['title_en'] ?? ''));
        $fontKey = (string) ($input['font_key'] ?? '');
        $placement = (string) ($input['placement'] ?? 'every_page');
        $isActive = !empty($input['is_active']);

        $fontSize = round((float) ($input['font_size'] ?? 10.5), 1);
        $fontWeight = (int) ($input['font_weight'] ?? 600);
        $textColor = strtoupper(trim((string) ($input['text_color'] ?? '#1F2937')));
        $letterSpacing = round((float) ($input['letter_spacing'] ?? 0.0), 2);
        $alignment = (string) ($input['alignment'] ?? 'center');

        if (mb_strlen($nameAr) > self::MAX_NAME || mb_strlen($nameEn) > self::MAX_NAME
            || mb_strlen($titleAr) > self::MAX_TITLE || mb_strlen($titleEn) > self::MAX_TITLE) {
            throw new RuntimeException('validation_error');
        }
        foreach ([$nameAr, $nameEn, $titleAr, $titleEn] as $value) {
            if (preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
                throw new RuntimeException('validation_error');
            }
        }
        if (!isset(self::FONTS[$fontKey])) {
            throw new RuntimeException('validation_error');
        }
        if (!in_array($placement, self::PLACEMENTS, true)) {
            throw new RuntimeException('validation_error');
        }
        if ($fontSize < self::MIN_SIZE || $fontSize > self::MAX_SIZE) {
            throw new RuntimeException('validation_error');
        }
        if (!in_array($fontWeight, self::WEIGHTS, true)) {
            throw new RuntimeException('validation_error');
        }
        if (preg_match('/^#[0-9A-F]{6}$/', $textColor) !== 1) {
            throw new RuntimeException('validation_error');
        }
        if ($letterSpacing < 0.0 || $letterSpacing > self::MAX_TRACKING) {
            throw new RuntimeException('validation_error');
        }
        if (!in_array($alignment, self::ALIGNMENTS, true)) {
            throw new RuntimeException('validation_error');
        }

        $signaturePath = $row['signature_path'] ?? null;
        $stampPath = $row['stamp_path'] ?? null;
        $oldSignature = $signaturePath;
        $oldStamp = $stampPath;
        $changes = [];

        if (self::hasUpload($signatureFile)) {
            try {
                $signaturePath = $this->uploads->store($signatureFile, 'school', self::IMAGE_MAX_DIMENSION);
            } catch (RuntimeException) {
                throw new RuntimeException('image_invalid');
            }
            $changes['signature_image'] = ['old' => $oldSignature !== null ? 'stored' : '', 'new' => 'uploaded'];
        } elseif ($removeSignature && $signaturePath !== null) {
            $signaturePath = null;
            $changes['signature_image'] = ['old' => 'stored', 'new' => 'removed'];
        }

        if (self::hasUpload($stampFile)) {
            try {
                $stampPath = $this->uploads->store($stampFile, 'school', self::IMAGE_MAX_DIMENSION);
            } catch (RuntimeException) {
                if ($signaturePath !== $oldSignature) {
                    $this->uploads->delete($signaturePath);
                }
                throw new RuntimeException('image_invalid');
            }
            $changes['stamp_image'] = ['old' => $oldStamp !== null ? 'stored' : '', 'new' => 'uploaded'];
        } elseif ($removeStamp && $stampPath !== null) {
            $stampPath = null;
            $changes['stamp_image'] = ['old' => 'stored', 'new' => 'removed'];
        }

        $before = [
            'name_ar' => (string) ($row['name_ar'] ?? ''),
            'name_en' => (string) ($row['name_en'] ?? ''),
            'title_ar' => (string) ($row['title_ar'] ?? ''),
            'title_en' => (string) ($row['title_en'] ?? ''),
            'font_key' => (string) ($row['font_key'] ?? ''),
            'font_size' => self::boundedSize($row['font_size'] ?? null),
            'font_weight' => (string) self::boundedWeight($row['font_weight'] ?? null),
            'text_color' => self::boundedColor($row['text_color'] ?? null),
            'letter_spacing' => self::boundedTracking($row['letter_spacing'] ?? null),
            'alignment' => self::boundedAlignment($row['alignment'] ?? null),
            'placement' => (string) ($row['placement'] ?? ''),
            'is_active' => dbBool($row['is_active'] ?? false) ? '1' : '0',
        ];
        $after = [
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'font_key' => $fontKey,
            'font_size' => $fontSize,
            'font_weight' => (string) $fontWeight,
            'text_color' => $textColor,
            'letter_spacing' => $letterSpacing,
            'alignment' => $alignment,
            'placement' => $placement,
            'is_active' => $isActive ? '1' : '0',
        ];
        foreach ($after as $field => $value) {
            if ($before[$field] !== $value) {
                $changes[$field] = ['old' => $before[$field], 'new' => $value];
            }
        }

        $this->repo->update($id, [
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'font_key' => $fontKey,
            'font_size' => $fontSize,
            'font_weight' => $fontWeight,
            'text_color' => $textColor,
            'letter_spacing' => $letterSpacing,
            'alignment' => $alignment,
            'placement' => $placement,
            'is_active' => $isActive,
            'signature_path' => $signaturePath,
            'stamp_path' => $stampPath,
        ]);

        // Only after the row points at the new files may the old ones go.
        if ($oldSignature !== null && $oldSignature !== $signaturePath) {
            $this->uploads->delete($oldSignature);
        }
        if ($oldStamp !== null && $oldStamp !== $stampPath) {
            $this->uploads->delete($oldStamp);
        }

        self::$rows = null;
        SettingsAuditService::record(
            'signatories',
            $changes,
            'Authorized signatory updated: ' . (string) $row['role_code']
        );

        return $changes;
    }

    /**
     * The same bounds the settings form enforces, applied again when a stored
     * row is read -- a hand-edited database or a value written by an older
     * build can never produce an unprintable signature.
     */
    private static function boundedSize(mixed $value): float
    {
        $size = (float) ($value ?? 10.5);
        return round(max(self::MIN_SIZE, min(self::MAX_SIZE, $size)), 1);
    }

    private static function boundedWeight(mixed $value): int
    {
        $weight = (int) ($value ?? 600);
        return in_array($weight, self::WEIGHTS, true) ? $weight : 600;
    }

    private static function boundedColor(mixed $value): string
    {
        $color = strtoupper(trim((string) ($value ?? '')));
        return preg_match('/^#[0-9A-F]{6}$/', $color) === 1 ? $color : '#1F2937';
    }

    private static function boundedTracking(mixed $value): float
    {
        $tracking = (float) ($value ?? 0.0);
        return round(max(0.0, min(self::MAX_TRACKING, $tracking)), 2);
    }

    private static function boundedAlignment(mixed $value): string
    {
        $alignment = (string) ($value ?? 'center');
        return in_array($alignment, self::ALIGNMENTS, true) ? $alignment : 'center';
    }

    /** Localized fallback label for a signature role. */
    public static function roleLabel(string $roleCode): string
    {
        $key = 'document.sig.' . $roleCode;
        $label = __($key);

        return $label === $key ? $roleCode : $label;
    }

    /** @return array<string, array<string,mixed>> keyed by role_code */
    private static function rows(): array
    {
        if (self::$rows !== null) {
            return self::$rows;
        }

        $map = [];
        foreach ((new DocumentSignatoryRepository())->all() as $row) {
            $map[(string) $row['role_code']] = $row;
        }

        return self::$rows = $map;
    }

    /** @param array{error?:int}|null $file */
    private static function hasUpload(?array $file): bool
    {
        return $file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /** A stored image as a data: URI, or null when there is nothing to embed. */
    private static function dataUri(?string $relativePath): ?string
    {
        $path = (string) ($relativePath ?? '');
        if ($path === '') {
            return null;
        }

        $contents = UploadService::retrieve($path);
        if ($contents === null || $contents === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}

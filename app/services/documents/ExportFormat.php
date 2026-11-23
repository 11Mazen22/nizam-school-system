<?php

declare(strict_types=1);

namespace App\Services\Documents;

use InvalidArgumentException;

/**
 * The one list of file formats the NIZAM document platform can produce.
 *
 * Before this class existed the format list lived in four places at once: a
 * per-document-type array, a hard-coded array in the controller, a hard-coded
 * array in the browser script and a hand-written menu in the view. They could
 * (and did) disagree -- a document could advertise PNG and then refuse it.
 *
 * Everything now asks this class: the capability endpoint, the export menu,
 * the bulk dialog, the controller's validation and the archive builder.
 */
final class ExportFormat
{
    public const PRINT = 'print';
    public const PDF = 'pdf';
    public const PNG = 'png';
    public const XLSX = 'xlsx';
    public const CSV = 'csv';
    public const HTML = 'html';
    public const ZIP = 'zip';

    /**
     * @var array<string, array{
     *     extension:string, mime:string, icon:string, kind:string,
     *     label:string, description:string, bulk:bool, file:bool
     * }>
     */
    private const META = [
        self::PDF => [
            'extension' => 'pdf',
            'mime' => 'application/pdf',
            'icon' => 'file-text',
            'kind' => 'document',
            'label' => 'document.format.pdf',
            'description' => 'document.format.pdf_desc',
            'bulk' => true,
            'file' => true,
        ],
        self::PNG => [
            'extension' => 'png',
            'mime' => 'image/png',
            'icon' => 'image',
            'kind' => 'image',
            'label' => 'document.format.png',
            'description' => 'document.format.png_desc',
            'bulk' => true,
            'file' => true,
        ],
        self::XLSX => [
            'extension' => 'xlsx',
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'icon' => 'grid',
            'kind' => 'spreadsheet',
            'label' => 'document.format.xlsx',
            'description' => 'document.format.xlsx_desc',
            'bulk' => true,
            'file' => true,
        ],
        self::CSV => [
            'extension' => 'csv',
            'mime' => 'text/csv; charset=UTF-8',
            'icon' => 'table',
            'kind' => 'data',
            'label' => 'document.format.csv',
            'description' => 'document.format.csv_desc',
            'bulk' => true,
            'file' => true,
        ],
        self::HTML => [
            'extension' => 'html',
            'mime' => 'text/html; charset=UTF-8',
            'icon' => 'code',
            'kind' => 'web',
            'label' => 'document.format.html',
            'description' => 'document.format.html_desc',
            'bulk' => true,
            'file' => true,
        ],
        self::ZIP => [
            'extension' => 'zip',
            'mime' => 'application/zip',
            'icon' => 'archive',
            'kind' => 'archive',
            'label' => 'document.format.zip',
            'description' => 'document.format.zip_desc',
            'bulk' => false,
            'file' => true,
        ],
        self::PRINT => [
            'extension' => 'html',
            'mime' => 'text/html; charset=UTF-8',
            'icon' => 'printer',
            'kind' => 'action',
            'label' => 'document.format.print',
            'description' => 'document.format.print_desc',
            'bulk' => false,
            'file' => false,
        ],
    ];

    /** @return list<string> every key this class understands */
    public static function all(): array
    {
        return array_keys(self::META);
    }

    /** @return list<string> formats that produce a downloadable file for one document */
    public static function documents(): array
    {
        return [self::PDF, self::PNG, self::XLSX, self::CSV, self::HTML];
    }

    /** @return list<string> formats a bulk export can wrap */
    public static function bulk(): array
    {
        return [self::PDF, self::PNG, self::XLSX, self::CSV, self::HTML];
    }

    public static function has(string $format): bool
    {
        return isset(self::META[self::normalize($format)]);
    }

    /**
     * Accept the names users and old links actually use.
     * "excel" was the historical alias; a print request is the same bytes as
     * the self-contained HTML document, opened inline.
     */
    public static function normalize(string $format): string
    {
        $format = strtolower(trim($format));

        return match ($format) {
            'excel', 'spreadsheet', 'xls' => self::XLSX,
            'html', 'htm', 'offline' => self::HTML,
            'images', 'image' => self::PNG,
            'archive' => self::ZIP,
            default => $format,
        };
    }

    /**
     * @return array{
     *     key:string, extension:string, mime:string, icon:string, kind:string,
     *     label:string, description:string, bulk:bool, file:bool
     * }
     */
    public static function meta(string $format): array
    {
        $key = self::normalize($format);
        if (!isset(self::META[$key])) {
            throw new InvalidArgumentException('unknown_format');
        }

        $meta = self::META[$key];

        return [
            'key' => $key,
            'extension' => $meta['extension'],
            'mime' => $meta['mime'],
            'icon' => $meta['icon'],
            'kind' => $meta['kind'],
            'label' => __($meta['label']),
            'description' => __($meta['description']),
            'bulk' => $meta['bulk'],
            'file' => $meta['file'],
        ];
    }

    public static function label(string $format): string
    {
        return self::meta($format)['label'];
    }

    public static function extension(string $format): string
    {
        return self::meta($format)['extension'];
    }

    public static function mime(string $format): string
    {
        return self::meta($format)['mime'];
    }
}

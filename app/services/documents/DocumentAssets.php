<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * Document assets: the institutional stylesheet and the fonts it needs.
 *
 * Fonts are embedded as base64 for export so the generated file is completely
 * self-contained -- Chromium never has a chance to fall back to a system font
 * that happens to be installed on one school's computer and missing on the
 * next, and the PDF's Arabic shaping is identical to the preview the
 * administrator approved. In preview mode the same fonts are linked normally
 * so the HTML stays small.
 */
final class DocumentAssets
{
    private const FONT_DIR = '/public/assets/fonts/';

    /** @var array<int, array{0:string,1:string,2:string,3:string}> file, family, weight, mime */
    private const FONTS = [
        ['IBMPlexSansArabic-Regular.woff2', 'Nizam Sans', '400', 'font/woff2'],
        ['IBMPlexSansArabic-SemiBold.woff2', 'Nizam Sans', '600', 'font/woff2'],
        ['IBMPlexSansArabic-Bold.woff2', 'Nizam Sans', '700', 'font/woff2'],
        ['Inter-Regular.woff2', 'Nizam Latin', '400', 'font/woff2'],
        ['Inter-SemiBold.woff2', 'Nizam Latin', '600', 'font/woff2'],
        ['Inter-Bold.woff2', 'Nizam Latin', '700', 'font/woff2'],
    ];

    /**
     * Arabic typefaces used only for authorized signatures. They are embedded
     * on demand -- a document whose footer uses two of them should not carry
     * the third -- so the exported PDF stays self-contained without bloating
     * every register with unused calligraphic faces.
     *
     * @var array<string, array{family:string,regular:string,bold:?string}>
     */
    public const SIGNATURE_FONTS = [
        'aref-ruqaa' => ['family' => 'Aref Ruqaa', 'regular' => 'ArefRuqaa-Regular.woff2', 'bold' => 'ArefRuqaa-Bold.woff2'],
        'amiri' => ['family' => 'Amiri', 'regular' => 'Amiri-Regular.woff2', 'bold' => 'Amiri-Bold.woff2'],
        'reem-kufi' => ['family' => 'Reem Kufi', 'regular' => 'ReemKufi-Regular.woff2', 'bold' => null],
    ];

    private static array $fontCache = [];

    /** @return string @font-face rules, embedded or linked depending on $inline */
    public static function fontCss(bool $inline): string
    {
        $cacheKey = 'main_' . ($inline ? 'inline' : 'linked');
        if (isset(self::$fontCache[$cacheKey])) {
            return self::$fontCache[$cacheKey];
        }

        $css = '';
        foreach (self::FONTS as [$file, $family, $weight, $mime]) {
            $path = dirname(__DIR__, 3) . self::FONT_DIR . $file;
            if ($inline) {
                if (!is_file($path)) {
                    continue;
                }
                $src = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
            } else {
                $src = '/assets/fonts/' . $file . '?v=' . (is_file($path) ? filemtime($path) : 0);
            }

            $css .= "@font-face{font-family:'{$family}';src:url('{$src}') format('woff2');"
                . "font-weight:{$weight};font-style:normal;font-display:block}";
        }

        return self::$fontCache[$cacheKey] = $css;
    }

    /**
     * @font-face rules for the signature typefaces a specific document
     * actually uses. Unknown keys are ignored rather than silently falling
     * back to a different face.
     *
     * @param list<string> $fontKeys
     */
    public static function signatureFontCss(bool $inline, array $fontKeys): string
    {
        $fontKeys = array_values(array_unique($fontKeys));
        sort($fontKeys);
        $cacheKey = 'sig_' . ($inline ? 'inline' : 'linked') . '_' . implode(',', $fontKeys);
        if (isset(self::$fontCache[$cacheKey])) {
            return self::$fontCache[$cacheKey];
        }

        $css = '';
        foreach ($fontKeys as $key) {
            $font = self::SIGNATURE_FONTS[(string) $key] ?? null;
            if ($font === null) {
                continue;
            }

            $faces = [['400', $font['regular']]];
            if ($font['bold'] !== null) {
                $faces[] = ['700', $font['bold']];
            }

            foreach ($faces as [$weight, $file]) {
                $path = dirname(__DIR__, 3) . self::FONT_DIR . $file;
                if (!is_file($path)) {
                    continue;
                }
                $src = $inline
                    ? 'data:font/woff2;base64,' . base64_encode((string) file_get_contents($path))
                    : '/assets/fonts/' . $file . '?v=' . filemtime($path);

                $css .= "@font-face{font-family:'{$font['family']}';src:url('{$src}') format('woff2');"
                    . "font-weight:{$weight};font-style:normal;font-display:block}";
            }
        }

        return self::$fontCache[$cacheKey] = $css;
    }

    /**
     * The document design system. Loaded by views/documents/document.php and
     * inlined verbatim into the export HTML, so what is previewed is
     * byte-for-byte what is printed.
     */
    public static function documentCss(bool $inline): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/css/document.css';
        $css = is_file($path) ? (string) file_get_contents($path) : '';

        return self::fontCss($inline) . $css;
    }

    /** Asset URL for the design system when the document is served, not exported. */
    public static function stylesheetHref(): string
    {
        $path = dirname(__DIR__, 3) . '/public/assets/css/document.css';
        $version = is_file($path) ? (string) filemtime($path) : '0';

        return '/assets/css/document.css?v=' . $version;
    }
}

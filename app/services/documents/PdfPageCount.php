<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * Read the real page geometry from a generated PDF.
 *
 * The renderer's own page count comes from the composed DOM, which is the
 * number the footer prints. That is only correct while the browser actually
 * emits exactly that many physical pages. Reading the PDF page tree here gives
 * us an independent, authoritative count to check it against -- no PDF library
 * and no external binary needed, and it works on the offline school server.
 *
 * Page size is read for the same reason: an A4 document that silently came
 * out as US Letter would still open, but it would not print correctly on the
 * school's paper, so exports verify both dimensions.
 */
final class PdfPageCount
{
    public static function fromBytes(string $bytes): int
    {
        if (!str_starts_with($bytes, '%PDF')) {
            return 0;
        }

        // Count the leaf /Type /Page objects. Chromium builds a balanced page
        // tree for long documents: several intermediate /Type /Pages nodes each
        // carry their own /Count (8, 8, 8, 5...) and only the root says 29.
        // Taking the first /Count would therefore under-report a long register
        // by a whole subtree -- counting leaves is unambiguous.
        $leaves = preg_match_all('/\/Type\s*\/Page\b/s', $bytes);
        if ($leaves > 0) {
            return $leaves;
        }

        // Fallback for producers that only emit the page-tree count.
        if (preg_match('/\/Type\s*\/Pages\b[^>]{0,600}?\/Count\s+(\d+)/s', $bytes, $match) === 1) {
            return (int) $match[1];
        }

        return 0;
    }

    /**
     * Every distinct MediaBox in the file, in points, as [width, height].
     *
     * @return list<array{0:float,1:float}>
     */
    public static function pageSizes(string $bytes): array
    {
        if (preg_match_all(
            '/\/MediaBox\s*\[\s*(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s*\]/s',
            $bytes,
            $matches,
            PREG_SET_ORDER
        ) === false || $matches === []) {
            return [];
        }

        $sizes = [];
        foreach ($matches as $match) {
            $width = round(abs((float) $match[3] - (float) $match[1]), 1);
            $height = round(abs((float) $match[4] - (float) $match[2]), 1);
            if ($width > 1 && $height > 1) {
                $sizes[$width . 'x' . $height] = [$width, $height];
            }
        }

        return array_values($sizes);
    }

    /**
     * True when every page is A4 in the expected orientation.
     * Tolerance is 2pt (~0.7mm): 595.28 x 841.89 points is A4.
     */
    public static function isA4(string $bytes, bool $landscape = false): bool
    {
        $sizes = self::pageSizes($bytes);
        if ($sizes === []) {
            return false;
        }

        $expectedWidth = $landscape ? 841.89 : 595.28;
        $expectedHeight = $landscape ? 595.28 : 841.89;

        foreach ($sizes as [$width, $height]) {
            if (abs($width - $expectedWidth) > 2.0 || abs($height - $expectedHeight) > 2.0) {
                return false;
            }
        }

        return true;
    }
}

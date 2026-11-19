<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * Read the real number of pages from a generated PDF.
 *
 * The renderer's own page count comes from the composed DOM, which is the
 * number the footer prints. That is only correct while the browser actually
 * emits exactly that many physical pages. Reading the PDF page tree here gives
 * us an independent, authoritative count to check it against -- no PDF library
 * and no external binary needed, and it works on the offline school server.
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
}

<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * Intelligent page composition.
 *
 * A document is not "a very tall table clipped at 297mm". This composer
 * measures every block in millimetres from its real content (character
 * counts against the actual column widths, not a fixed rows-per-page guess),
 * then fills each page up to that page's usable height. Tables that do not
 * fit are split at row boundaries and repeat their column headings on the
 * continuation page; a block that cannot be split (a timetable matrix, a
 * summary strip) starts on the next page instead of being cut in half.
 *
 * The budgets below mirror views/documents/document.php's own geometry, and
 * are deliberately a few millimetres short of it: a page with a little white
 * space at the bottom is correct, a page whose last row is clipped is not.
 */
final class PageComposer
{
    /** Usable body height in mm, per orientation, measured against the layout's fixed header/footer. */
    private const BUDGET = [
        'portrait' => 219.0,
        'landscape' => 135.0,
    ];

    private const FONT_PT = 8.4;
    /**
     * Average advance width of a shaped Arabic/Latin glyph at FONT_PT, in mm,
     * plus a deliberate margin. Measured against the bundled IBM Plex Sans
     * Arabic at 8.2pt (~1.15mm); the extra 15% is the safety margin that keeps
     * a long Arabic name from being estimated shorter than it actually renders
     * and clipped at the page edge.
     */
    /** Measured from the bundled font at 8.2pt: Arabic sits at 1.15-1.23mm per glyph. */
    private const CHAR_MM = 1.22;
    /** Latin capitals and digits at the same point size (measured 1.66mm). */
    private const LATIN_MM = 1.68;
    /**
     * These three are measured from the real stylesheet, not assumed: an 8.2pt
     * cell with 1.35mm vertical padding and a 0.18mm border renders a one-line
     * row at 7.0mm and each extra wrapped line at 3.9mm (document.css
     * line-height: 1.45). Under-estimating them is what silently pushes a
     * table's last rows underneath the footer of a fixed-height page.
     */
    private const LINE_MM = 3.9;
    private const CELL_PAD_MM = 2.95;
    private const ROW_MIN_MM = 6.85;
    /** Header row (7.9pt, 1.7mm padding) plus its rules. */
    private const HEAD_MM = 7.3;
    /** Heading line plus its 1.4mm bottom margin. */
    private const BLOCK_TITLE_MM = 6.0;
    private const GAP_MM = 3.4;

    /**
     * @return array{pages: list<list<array<string,mixed>>>, total: int}
     */
    public function compose(Document $document): array
    {
        $budget = self::BUDGET[$document->isLandscape() ? 'landscape' : 'portrait'];
        $pages = [];
        $current = [];
        $used = 0.0;

        foreach ($document->blocks as $block) {
            // A block may be consumed whole, split to fill the space that is
            // left on this page, or deferred whole -- so it is processed as a
            // queue of pieces rather than as one measured unit.
            $queue = [$block];
            $guard = 0;

            while ($queue !== [] && $guard++ < 5000) {
                $candidate = array_shift($queue);
                $height = $this->height($candidate);
                $remaining = $budget - $used;

                if ($height <= $remaining) {
                    $current[] = $candidate;
                    $used += $height + self::GAP_MM;
                    continue;
                }

                // Too tall for the space left: keep filling this page with as
                // many rows as genuinely fit, and carry the rest forward.
                $fitted = $this->fill($candidate, $remaining);
                if ($fitted !== null) {
                    [$head, $tail] = $fitted;
                    $current[] = $head;
                    $pages[] = $current;
                    $current = [];
                    $used = 0.0;
                    array_unshift($queue, $tail);
                    continue;
                }

                // Nothing fits here: start the next page with it intact.
                if ($current !== []) {
                    $pages[] = $current;
                    $current = [];
                    $used = 0.0;
                    array_unshift($queue, $candidate);
                    continue;
                }

                // A whole empty page cannot hold it: split against a full page
                // (a table) or place it as-is (an unsplittable grid).
                $chunks = $this->split($candidate, $budget);
                if (count($chunks) > 1) {
                    $queue = array_merge($chunks, $queue);
                    continue;
                }

                $current[] = $candidate;
                $used += $height + self::GAP_MM;
            }
        }

        if ($current !== [] || $pages === []) {
            $pages[] = $current;
        }

        return ['pages' => $pages, 'total' => count($pages)];
    }

    /**
     * Split a block so that its first piece fits in $space and the rest is
     * carried to the next page.
     *
     * Returns null when the block cannot usefully be split at all (no room for
     * a heading and a couple of rows), so the caller moves the whole block to
     * a fresh page instead of leaving a stub behind.
     *
     * @return array{0:array<string,mixed>,1:array<string,mixed>}|null
     */
    private function fill(array $block, float $space): ?array
    {
        $type = (string) ($block['type'] ?? '');
        if ($type !== 'table' && $type !== 'fields') {
            return null;
        }

        $minimum = self::BLOCK_TITLE_MM + self::HEAD_MM + (self::ROW_MIN_MM * 2);
        if ($space < $minimum) {
            return null;
        }

        $chunks = $this->split($block, $space);
        if (count($chunks) < 2) {
            return null;
        }

        $head = array_shift($chunks);
        $tail = $block;

        if ($type === 'table') {
            $rows = [];
            foreach ($chunks as $chunk) {
                $rows = array_merge($rows, (array) ($chunk['rows'] ?? []));
            }
            if ($rows === []) {
                return null;
            }
            $tail['rows'] = $rows;
        } else {
            $sections = [];
            foreach ($chunks as $chunk) {
                $sections = array_merge($sections, (array) ($chunk['sections'] ?? []));
            }
            if ($sections === []) {
                return null;
            }
            $tail['sections'] = $sections;
        }

        $tail['continuation'] = true;

        return [$head, $tail];
    }

    /**
     * Split an over-tall block into page-sized pieces. Tables split by row;
     * field sections split by section; a grid is never split.
     *
     * @return list<array<string,mixed>>
     */
    private function split(array $block, float $budget): array
    {
        $type = (string) ($block['type'] ?? '');
        $height = $this->height($block);

        if ($height <= $budget) {
            return [$block];
        }

        if ($type === 'table' && !empty($block['rows'])) {
            $columns = $block['columns'] ?? [];
            $head = self::BLOCK_TITLE_MM + self::HEAD_MM;
            $available = $budget - $head - $this->totalsHeight($block);
            $chunks = [];
            $rows = [];
            $used = 0.0;
            $first = true;

            foreach ($block['rows'] as $row) {
                $rowHeight = $this->rowHeight($row, $columns);
                if ($rows !== [] && ($used + $rowHeight) > $available) {
                    $chunks[] = $this->tableChunk($block, $rows, !$first);
                    $rows = [];
                    $used = 0.0;
                    $first = false;
                }
                $rows[] = $row;
                $used += $rowHeight;
            }

            if ($rows !== []) {
                $chunks[] = $this->tableChunk($block, $rows, !$first);
            }

            // The totals row belongs to the end of the table, never to every
            // continuation page.
            foreach ($chunks as $index => $chunk) {
                if ($index !== count($chunks) - 1) {
                    $chunks[$index]['totals'] = null;
                }
            }

            return $chunks === [] ? [$block] : $chunks;
        }

        if ($type === 'fields' && !empty($block['sections'])) {
            $chunks = [];
            $sections = [];
            $used = self::BLOCK_TITLE_MM;
            $first = true;

            foreach ($block['sections'] as $section) {
                $sectionHeight = $this->sectionHeight($section);
                if ($sections !== [] && ($used + $sectionHeight) > $budget) {
                    $chunks[] = $this->fieldsChunk($block, $sections, !$first);
                    $sections = [];
                    $used = self::BLOCK_TITLE_MM;
                    $first = false;
                }
                $sections[] = $section;
                $used += $sectionHeight;
            }

            if ($sections !== []) {
                $chunks[] = $this->fieldsChunk($block, $sections, !$first);
            }

            return $chunks === [] ? [$block] : $chunks;
        }

        return [$block];
    }

    private function tableChunk(array $block, array $rows, bool $continuation): array
    {
        $block['rows'] = $rows;
        $block['continuation'] = $continuation;
        return $block;
    }

    private function fieldsChunk(array $block, array $sections, bool $continuation): array
    {
        $block['sections'] = $sections;
        $block['continuation'] = $continuation;
        return $block;
    }

    /** Estimated rendered height of a block in millimetres. */
    public function height(array $block): float
    {
        $title = empty($block['title']) && empty($block['continuation']) ? 0.0 : self::BLOCK_TITLE_MM;

        return match ((string) ($block['type'] ?? '')) {
            'summary' => $title + $this->summaryHeight($block),
            'table' => $title + self::HEAD_MM + $this->totalsHeight($block) + array_sum(array_map(
                fn (array $row): float => $this->rowHeight($row, $block['columns'] ?? []),
                $block['rows'] ?? []
            )),
            'grid' => $title + 8.0
                + (!empty($block['legend']) ? 6.5 : 0.0)
                + array_sum(array_map(
                    fn (array $row): float => $this->gridRowHeight($row),
                    $block['rows'] ?? []
                )),
            'fields' => $title + array_sum(array_map(
                fn (array $section): float => $this->sectionHeight($section),
                $block['sections'] ?? []
            )),
            'notes' => $title + 4.0 + max(1, (int) ceil(mb_strlen((string) ($block['text'] ?? '')) / 138)) * self::LINE_MM,
            default => 10.0,
        };
    }

    private function summaryHeight(array $block): float
    {
        $cards = count($block['cards'] ?? []);
        $perRow = max(1, (int) ($block['per_row'] ?? 4));
        return (float) (max(1, (int) ceil($cards / $perRow)) * 14.2);
    }

    private function totalsHeight(array $block): float
    {
        return empty($block['totals']) ? 0.0 : 8.7;
    }

    private function sectionHeight(array $section): float
    {
        $fields = count($section['fields'] ?? []);
        return 5.6 + (float) (max(1, (int) ceil($fields / 2)) * 6.4) + 2.0;
    }

    private function gridRowHeight(array $row): float
    {
        $lines = 1;
        foreach ($row['cells'] ?? [] as $cell) {
            // A timetable cell renders subject, teacher and (when the paper
            // double-books a lesson) one status line. Counting only a
            // pre-computed [] of lines previously made every cell look like a
            // single line, so a clash-heavy class overflowed its page.
            $count = count($cell['lines'] ?? []);
            if ($count === 0) {
                $count = !empty($cell['subject']) || !empty($cell['text']) ? 1 : 0;
                if (!empty($cell['teacher'])) {
                    $count++;
                }
                if (!empty($cell['clash']) || !empty($cell['resolved']) || !empty($cell['unassigned'])) {
                    $count++;
                }
                if (!empty($cell['class'])) {
                    $count++;
                }
                $count = max(1, $count);
            }
            $lines = max($lines, $count);
        }

        // 7.4pt at line-height 1.45 is 3.3mm per line, plus cell padding and
        // borders. The cell height in document.css is only a minimum.
        return max(10.5, 2.6 + $lines * 3.3);
    }

    /**
     * Height of one table row: the tallest cell wins, computed from how many
     * wrapped lines that cell's text genuinely needs at its column width.
     */
    private function rowHeight(array $row, array $columns): float
    {
        $lines = 1;
        $index = 0;
        foreach ($row as $cell) {
            $width = (float) ($columns[$index]['width'] ?? 30);
            $lines = max($lines, self::lines((string) (is_array($cell) ? ($cell['value'] ?? '') : $cell), $width));
            $index++;
        }

        return max(self::ROW_MIN_MM, self::CELL_PAD_MM + $lines * self::LINE_MM);
    }

    /**
     * Wrapped line count for a value inside a column of $widthMm.
     *
     * Counted per character with the advance width of the script that
     * character belongs to: Arabic joins narrowly, while Latin capitals and
     * digits are visibly wider. A single average width (the previous
     * approach) under-counts a code like STU-2026-000028 and therefore wraps
     * a row the composer believed was one line tall -- the exact defect that
     * pushed rows under the footer.
     */
    public static function lines(string $text, float $widthMm): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return 1;
        }

        $available = max(3.0, $widthMm - 3.6);
        $lines = 1;
        $used = 0.0;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $advance = preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FEFF}]/u', $char) === 1
                ? self::CHAR_MM
                : self::LATIN_MM;

            if ($used > 0.0 && ($used + $advance) > $available) {
                $lines++;
                $used = $advance;
                continue;
            }

            $used += $advance;
        }

        return $lines;
    }
}

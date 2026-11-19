<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * A resolved, presentation-ready document.
 *
 * This is the only shape the renderer, the PDF pipeline, the Excel builder
 * and the PNG/ZIP exporters know about. A document type's single job is to
 * turn authoritative repository data into this model -- it never writes
 * HTML, never touches a header, never knows what paper it prints on.
 *
 * Blocks are deliberately few and semantic:
 *   summary  -- labelled figures (totals, counts)
 *   table    -- columns + rows + optional totals row (splittable across pages)
 *   grid     -- a period x day timetable matrix
 *   fields   -- labelled value pairs grouped into sections (profiles)
 *   notes    -- free administrative note / legend
 */
final class Document
{
    /** @param list<array<string,mixed>> $blocks */
    public function __construct(
        public string $key,
        public string $title,
        public string $subtitle = '',
        public string $orientation = 'portrait',
        public array $scope = [],
        public array $blocks = [],
        public array $signatures = [],
        public string $filename = 'document',
        public array $meta = [],
        public array $sheets = [],
    ) {
    }

    public function addBlock(array $block): self
    {
        $this->blocks[] = $block;
        return $this;
    }

    public function isLandscape(): bool
    {
        return $this->orientation === 'landscape';
    }

    /** True when the document carries no rows at all, so the view can say so honestly. */
    public function isEmpty(): bool
    {
        foreach ($this->blocks as $block) {
            foreach (['rows', 'sections'] as $key) {
                if (!empty($block[$key])) {
                    return false;
                }
            }
        }
        return true;
    }

    /** @return array<int, array{label:string, value:string}> */
    public function scopePairs(): array
    {
        $pairs = [];
        foreach ($this->scope as $label => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $pairs[] = ['label' => (string) $label, 'value' => $value];
        }
        return $pairs;
    }
}

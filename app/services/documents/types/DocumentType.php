<?php

declare(strict_types=1);

namespace App\Services\Documents\Types;

use App\Services\Documents\Document;
use App\Services\Documents\DocumentIdentity;

/**
 * One NIZAM document type.
 *
 * A type owns exactly one responsibility: turn authoritative repository data
 * into a Document model. It does not know about paper, chromes, file formats,
 * HTTP, sessions or permissions beyond the single permission code that
 * authorises reading the same data on screen.
 */
abstract class DocumentType
{
    abstract public function key(): string;

    /** @param array<string,string> $params sanitised query parameters */
    abstract public function build(array $params, DocumentIdentity $identity): Document;

    /** The permission its on-screen equivalent already requires. */
    abstract public function permission(): string;

    /** @return list<string> */
    public function formats(): array
    {
        return ['pdf', 'xlsx', 'png'];
    }

    /** @return list<string> */
    public function signatures(): array
    {
        return ['principal'];
    }

    /**
     * Bulk scopes this document supports: a stable key, a localized label and
     * a resolver that lists the individual documents to generate.
     *
     * @param array<string,string> $params
     * @return array<string, array{label:string, items:callable}>|array{}
     */
    public function bulkScopes(array $params): array
    {
        return [];
    }

    /**
     * Keep only scopes that actually contain records. An empty scope is not a
     * meaningful bulk action: the export menu reads this same method, so a
     * school with no covers or no year pairs must not be offered a ZIP button
     * that would only produce an empty archive.
     *
     * @param array<string,array{label:string,items:array}> $groups
     * @return array<string,array{label:string,items:array}>
     */
    protected static function scopes(array $groups): array
    {
        return array_filter($groups, static fn (array $group): bool => !empty($group['items']));
    }

    /** Localized report title, so a document is named the same everywhere. */
    protected function title(string $suffix = ''): string
    {
        $key = 'document.title.' . $this->key() . ($suffix !== '' ? '.' . $suffix : '');
        $translated = __($key);
        return $translated === $key ? $this->key() : $translated;
    }

    /** Deterministic pastel tone (1-6) for a timetable subject: same subject, same colour, every export. */
    protected static function toneFor(string $name): int
    {
        $hash = crc32($name);
        return (int) ($hash % 6) + 1;
    }

    protected static function present(?string $value): string
    {
        $value = trim((string) $value);
        return $value === '' ? '—' : $value;
    }

    /** Active academic year, or a clear failure -- a document without a year is not a document. */
    protected function activeYearId(): int
    {
        $yearId = \App\Middleware\AcademicYearContext::activeYearId();
        if ($yearId === null) {
            throw new \RuntimeException('no_active_year');
        }

        return $yearId;
    }

    /**
     * Scale declared column widths so the table always spans exactly the
     * printable body width of its own orientation. Declared widths are
     * proportions, not guesses -- this keeps a 4-column and a 12-column table
     * both filling the page without hand-tuning either.
     *
     * @param list<array<string,mixed>> $columns
     * @return list<array<string,mixed>>
     */
    protected static function fitWidths(array $columns, bool $landscape = false): array
    {
        $total = $landscape ? 271.0 : 185.0;
        $sum = 0.0;
        foreach ($columns as $column) {
            $sum += (float) ($column['width'] ?? 10);
        }
        if ($sum <= 0.0) {
            return $columns;
        }
        foreach ($columns as $index => $column) {
            $columns[$index]['width'] = round(((float) ($column['width'] ?? 10) / $sum) * $total, 2);
        }

        return $columns;
    }

    protected static function localized(?string $ar, ?string $en, string $locale): string
    {
        $value = $locale === 'ar' ? (string) $ar : (string) $en;
        if (trim($value) === '') {
            $value = $locale === 'ar' ? (string) $en : (string) $ar;
        }
        return self::present($value);
    }
}

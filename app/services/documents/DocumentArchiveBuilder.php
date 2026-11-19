<?php

declare(strict_types=1);

namespace App\Services\Documents;

use RuntimeException;
use ZipArchive;

/**
 * ZIP packaging for bulk document exports.
 *
 * Folder and file names are generated from the real records (an Arabic class
 * or teacher name, not a database id), paginated with a numeric prefix so two
 * teachers who share a name can never overwrite each other, and written as
 * UTF-8 entries so the archive opens correctly on Windows, macOS and phones.
 */
final class DocumentArchiveBuilder
{
    /**
     * @param list<array{path:string, bytes:string}> $entries
     */
    public function build(string $rootFolder, array $entries, string $readme = ''): string
    {
        if ($entries === []) {
            throw new RuntimeException('document_empty');
        }

        $temp = tempnam(sys_get_temp_dir(), 'nizam_zip_');
        if ($temp === false) {
            throw new RuntimeException('document_export_failed');
        }
        @unlink($temp);

        $archive = new ZipArchive();
        if ($archive->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('document_export_failed');
        }

        $root = trim($rootFolder, '/') . '/';
        foreach ($entries as $entry) {
            $path = $root . ltrim((string) $entry['path'], '/');
            $archive->addFromString($path, (string) $entry['bytes']);
        }

        if ($readme !== '') {
            $archive->addFromString($root . 'README.txt', $readme);
        }

        $archive->setArchiveComment('NIZAM document export');
        $archive->close();

        $bytes = (string) file_get_contents($temp);
        @unlink($temp);

        return $bytes;
    }

    /**
     * Filesystem-safe document file name that still reads like the record it
     * belongs to. Keeps Arabic, strips only characters Windows/Android refuse.
     */
    public static function safeName(string $label, string $fallback = 'document'): string
    {
        $label = trim($label);
        $label = preg_replace('/[\\\\\\/\\:\\*\\?"<>\\|\\x00-\\x1F]+/u', ' ', $label) ?? $label;
        $label = preg_replace('/\\s+/u', ' ', $label) ?? $label;
        $label = trim($label, " .\t\n\r");

        if ($label === '') {
            $label = $fallback;
        }

        return function_exists('mb_substr') ? mb_substr($label, 0, 120) : substr($label, 0, 120);
    }
}

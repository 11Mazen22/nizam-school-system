<?php

declare(strict_types=1);

namespace App\Services\Documents;

use RuntimeException;
use ZipArchive;

/**
 * ZIP packaging for bulk document exports.
 *
 * Folder and file names are generated from the real records (an Arabic class
 * or teacher name, not a database id), paginated so two teachers who share a
 * name can never overwrite each other, and written as UTF-8 entries so the
 * archive opens correctly on Windows, macOS and phones.
 *
 * The builder also owns path safety: an entry can never escape the archive's
 * own folder through "..", a leading slash or a control character, and two
 * entries that resolve to the same name are disambiguated instead of one
 * silently replacing the other.
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

        try {
            $root = trim($this->safePath($rootFolder), '/') . '/';
            $used = [];

            foreach ($entries as $entry) {
                $path = $root . $this->safePath((string) $entry['path']);
                if ($path === $root) {
                    continue;
                }
                $path = $this->uniquePath($path, $used);

                if (!$archive->addFromString($path, (string) $entry['bytes'])) {
                    throw new RuntimeException('document_export_failed');
                }
                // 0664, no executable bit: these are documents, not programs.
                $archive->setExternalAttributesName(
                    $path,
                    ZipArchive::OPSYS_UNIX,
                    (0100664 << 16)
                );
            }

            if ($readme !== '') {
                $archive->addFromString($root . 'README.txt', $readme);
            }

            $archive->setArchiveComment('NIZAM document export');
        } finally {
            $archive->close();
        }

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
        $label = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $label) ?? $label;
        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;
        $label = trim($label, " .\t\n\r");

        if ($label === '' || $label === '.' || $label === '..') {
            $label = $fallback;
        }

        return function_exists('mb_substr') ? mb_substr($label, 0, 120) : substr($label, 0, 120);
    }

    /** Normalise an archive path so it can never leave its own folder. */
    private function safePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('/[\x00-\x1F]/u', '', $path) ?? $path;

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /** @param array<string,bool> $used */
    private function uniquePath(string $path, array &$used): string
    {
        $candidate = $path;
        $extension = '';
        $dot = strrpos($path, '.');
        $slash = strrpos($path, '/');
        $stem = $path;

        if ($dot !== false && ($slash === false || $dot > $slash)) {
            $extension = substr($path, $dot);
            $stem = substr($path, 0, $dot);
        }

        $suffix = 2;
        while (isset($used[$candidate])) {
            $candidate = $stem . '-' . $suffix++ . $extension;
        }
        $used[$candidate] = true;

        return $candidate;
    }
}

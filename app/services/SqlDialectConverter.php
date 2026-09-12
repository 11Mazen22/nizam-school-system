<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Converts Nizam backup batches between the small syntax differences used by
 * MariaDB and PostgreSQL. The converter is deliberately data-preserving:
 * values inside INSERT statements are never rewritten. In particular, the
 * application's 0/1 SMALLINT flags (including is_active) are not booleans.
 */
final class SqlDialectConverter
{
    /** @return 'pgsql'|'mysql'|null */
    public static function detectSource(string $sql): ?string
    {
        if (preg_match('/^-- NIZAM-BACKUP v1-pg\b/m', $sql) === 1) {
            return 'pgsql';
        }
        if (preg_match('/^-- NIZAM-BACKUP v1\b/m', $sql) === 1) {
            return 'mysql';
        }
        return null;
    }

    /** @param 'pgsql'|'mysql' $from @param 'pgsql'|'mysql' $to */
    public static function convert(string $sql, string $from, string $to): string
    {
        if ($from === $to) {
            return $sql;
        }
        return match ($from . ':' . $to) {
            'pgsql:mysql' => self::postgresqlToMysql($sql),
            'mysql:pgsql' => self::mysqlToPostgresql($sql),
            default => throw new InvalidArgumentException("Unsupported conversion: {$from} to {$to}"),
        };
    }

    private static function postgresqlToMysql(string $sql): string
    {
        $sql = preg_replace('/^-- NIZAM-BACKUP v1-pg\b/m', '-- NIZAM-BACKUP v1', $sql) ?? $sql;
        $sql = self::convertIdentifiers($sql, '"', '`');
        $sql = preg_replace('/\s+OVERRIDING\s+SYSTEM\s+VALUE\b/i', '', $sql) ?? $sql;
        $sql = self::replaceOutsideStrings($sql, static fn (string $segment): string => preg_replace(
            '/::\s*(?:smallint|integer|bigint|numeric|decimal|real|double\s+precision|boolean|date|timestamp(?:\s+with(?:out)?\s+time\s+zone)?|text|character\s+varying)\b/i',
            '',
            $segment
        ) ?? $segment);
        $sql = self::replaceOutsideStrings($sql, static fn (string $segment): string => preg_replace(
            '/\bSELECT\s+setval\s*\([^;]*\)\s*;?/i', '', $segment
        ) ?? $segment);
        return $sql;
    }

    private static function mysqlToPostgresql(string $sql): string
    {
        $sql = preg_replace('/^-- NIZAM-BACKUP v1\b(?!-pg)/m', '-- NIZAM-BACKUP v1-pg', $sql) ?? $sql;
        $sql = self::convertIdentifiers($sql, '`', '"');
        $sql = self::replaceOutsideStrings($sql, static fn (string $segment): string => self::addOverridingSystemValue($segment));
        $sql = self::replaceOutsideStrings($sql, static fn (string $segment): string => self::addCascadeToDrops($segment));
        return self::replaceOutsideStrings($sql, static fn (string $segment): string => preg_replace(
            [
                '/\s+ENGINE\s*=\s*\w+/i',
                '/\s+(?:DEFAULT\s+)?CHARSET\s*=\s*\w+/i',
                '/\s+COLLATE\s*=\s*\w+/i',
                '/\s+AUTO_INCREMENT\s*=\s*\d+/i',
            ],
            '',
            $segment
        ) ?? $segment);
    }

    /** Convert identifier delimiters without touching single-quoted data. */
    private static function convertIdentifiers(string $sql, string $from, string $to): string
    {
        $length = strlen($sql);
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                $end = self::quotedEnd($sql, $i, "'");
                $out .= substr($sql, $i, $end - $i + 1);
                $i = $end;
                continue;
            }
            if ($char === $from) {
                $end = self::quotedEnd($sql, $i, $from);
                $identifier = substr($sql, $i + 1, $end - $i - 1);
                $out .= $to . str_replace($from . $from, $to . $to, $identifier) . $to;
                $i = $end;
                continue;
            }
            $out .= $char;
        }
        return $out;
    }

    /** Adds OVERRIDING SYSTEM VALUE to multiline INSERTs that explicitly provide id. */
    private static function addOverridingSystemValue(string $sql): string
    {
        return preg_replace_callback(
            '/\bINSERT\s+INTO\s+"[^"]+"\s*(\((?:[^()]|\([^()]*\))*\))(\s*)(?!OVERRIDING\s+SYSTEM\s+VALUE\b)(?=VALUES\b)/is',
            static function (array $match): string {
                if (preg_match('/(?:^|,)\s*"id"\s*(?=,|$)/i', trim($match[1], '() \t\r\n')) !== 1) {
                    return $match[0];
                }
                return substr($match[0], 0, -strlen($match[2])) . ' OVERRIDING SYSTEM VALUE' . $match[2];
            },
            $sql
        ) ?? $sql;
    }

    /** Adds exactly one CASCADE modifier, including on multiline DROP statements. */
    private static function addCascadeToDrops(string $sql): string
    {
        return preg_replace_callback(
            '/\bDROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?(?:"[^"]+"|[A-Za-z_][A-Za-z0-9_]*)(?:\s*,\s*(?:"[^"]+"|[A-Za-z_][A-Za-z0-9_]*))*\s*(?:CASCADE\s*)?;/i',
            static fn (array $match): string => preg_match('/\bCASCADE\s*;$/i', $match[0]) === 1
                ? $match[0]
                : rtrim(substr($match[0], 0, -1)) . ' CASCADE;',
            $sql
        ) ?? $sql;
    }

    /** Applies a transformation only to SQL outside single-quoted values. */
    private static function replaceOutsideStrings(string $sql, callable $replace): string
    {
        $length = strlen($sql);
        $out = '';
        $start = 0;
        for ($i = 0; $i < $length; $i++) {
            if ($sql[$i] !== "'") {
                continue;
            }
            $out .= $replace(substr($sql, $start, $i - $start));
            $end = self::quotedEnd($sql, $i, "'");
            $out .= substr($sql, $i, $end - $i + 1);
            $i = $end;
            $start = $i + 1;
        }
        return $out . $replace(substr($sql, $start));
    }

    /** Finds the end of an SQL string/identifier using doubled delimiters. */
    private static function quotedEnd(string $sql, int $start, string $delimiter): int
    {
        $length = strlen($sql);
        for ($i = $start + 1; $i < $length; $i++) {
            if ($delimiter === "'" && $sql[$i] === '\\' && $i + 1 < $length) {
                $i++;
                continue;
            }
            if ($sql[$i] !== $delimiter) {
                continue;
            }
            if ($i + 1 < $length && $sql[$i + 1] === $delimiter) {
                $i++;
                continue;
            }
            return $i;
        }
        return $length - 1;
    }
}

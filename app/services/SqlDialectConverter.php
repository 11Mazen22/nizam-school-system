<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Hadaba Al-Ahram School -- Cross-Database SQL Converter
 * 
 * Automatically converts SQL dumps between PostgreSQL and MySQL/MariaDB formats
 * so production (Supabase/PostgreSQL) backups can restore to local (MariaDB) 
 * and vice versa, without manual conversion.
 * 
 * Detects source database from backup signature and converts to target database type.
 */
final class SqlDialectConverter
{
    /**
     * Detect source database type from backup signature line
     * 
     * @return 'pgsql'|'mysql'|null
     */
    public static function detectSource(string $sql): ?string
    {
        if (preg_match('/^-- NIZAM-BACKUP v1-pg/m', $sql)) {
            return 'pgsql';
        }
        if (preg_match('/^-- NIZAM-BACKUP v1/m', $sql)) {
            return 'mysql';
        }
        return null;
    }

    /**
     * Convert SQL from one dialect to another
     * 
     * @param 'pgsql'|'mysql' $from Source database type
     * @param 'pgsql'|'mysql' $to Target database type
     */
    public static function convert(string $sql, string $from, string $to): string
    {
        // No conversion needed if same database type
        if ($from === $to) {
            return $sql;
        }

        if ($from === 'pgsql' && $to === 'mysql') {
            return self::postgresqlToMysql($sql);
        }

        if ($from === 'mysql' && $to === 'pgsql') {
            return self::mysqlToPostgresql($sql);
        }

        throw new \InvalidArgumentException("Unsupported conversion: {$from} to {$to}");
    }

    /**
     * Convert PostgreSQL dump to MySQL/MariaDB format
     */
    private static function postgresqlToMysql(string $sql): string
    {
        // 1. Change signature
        $sql = preg_replace('/^-- NIZAM-BACKUP v1-pg/m', '-- NIZAM-BACKUP v1', $sql);

        // 2. Double quotes to backticks for identifiers
        $sql = preg_replace('/"([a-zA-Z_][a-zA-Z0-9_]*)"/','`$1`', $sql);

        // 3. Boolean values: true/false → 1/0
        $sql = preg_replace('/\btrue\b/i', '1', $sql);
        $sql = preg_replace('/\bfalse\b/i', '0', $sql);

        // 4. OVERRIDING SYSTEM VALUE → remove (MySQL doesn't need it)
        $sql = preg_replace('/\s+OVERRIDING SYSTEM VALUE/i', '', $sql);

        // 5. PostgreSQL-specific type casts: ::type → remove or convert
        $sql = preg_replace('/::date\b/', '', $sql);
        $sql = preg_replace('/::timestamp\b/', '', $sql);
        $sql = preg_replace('/::integer\b/', '', $sql);
        $sql = preg_replace('/::bigint\b/', '', $sql);
        $sql = preg_replace('/::boolean\b/', '', $sql);
        $sql = preg_replace('/::text\b/', '', $sql);

        // 6. Sequences: remove sequence-related commands (MySQL uses AUTO_INCREMENT)
        $sql = preg_replace('/SELECT setval\([^)]+\);?/i', '', $sql);

        // 7. String concatenation: || → CONCAT()
        // Complex: handle within VALUES, skip for now (rarely used in data dumps)

        return $sql;
    }

    /**
     * Convert MySQL/MariaDB dump to PostgreSQL format
     */
    private static function mysqlToPostgresql(string $sql): string
    {
        // 1. Change signature
        $sql = preg_replace('/^-- NIZAM-BACKUP v1\b/m', '-- NIZAM-BACKUP v1-pg', $sql);

        // 2. Backticks to double quotes for identifiers
        $sql = preg_replace('/`([a-zA-Z_][a-zA-Z0-9_]*)`/','"$1"', $sql);

        // 3. Boolean values: 1/0 → true/false (context-aware)
        // Only in INSERT VALUES, not in numbers like "2024" or "10"
        // Complex: needs proper parsing, simplified version:
        $sql = preg_replace_callback(
            '/VALUES\s*\(([^)]+)\)/i',
            function ($matches) {
                $values = $matches[1];
                // Replace standalone 1, 0 that look like booleans
                // Avoid replacing in dates/numbers: look for , 1, or , 0,
                $values = preg_replace('/,\s*1\s*,/', ', true,', $values);
                $values = preg_replace('/,\s*0\s*,/', ', false,', $values);
                $values = preg_replace('/^\s*1\s*,/', 'true,', $values); // First value
                $values = preg_replace('/,\s*1\s*$/', ', true', $values); // Last value
                $values = preg_replace('/^\s*0\s*,/', 'false,', $values);
                $values = preg_replace('/,\s*0\s*$/', ', false', $values);
                return 'VALUES (' . $values . ')';
            },
            $sql
        );

        // 4. Add OVERRIDING SYSTEM VALUE for identity columns
        $sql = preg_replace(
            '/INSERT INTO\s+"([a-zA-Z_][a-zA-Z0-9_]*)"\s+\(([^)]*"id"[^)]*)\)/i',
            'INSERT INTO "$1" ($2) OVERRIDING SYSTEM VALUE',
            $sql
        );

        // 5. DROP TABLE IF EXISTS → PostgreSQL format
        // MySQL: DROP TABLE IF EXISTS `table`;
        // PostgreSQL: DROP TABLE IF EXISTS "table" CASCADE;
        $sql = preg_replace('/DROP TABLE IF EXISTS ([^;]+);/i', 'DROP TABLE IF EXISTS $1 CASCADE;', $sql);

        // 6. Remove MySQL-specific syntax
        $sql = preg_replace('/ENGINE\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/DEFAULT CHARSET\s*=\s*\w+/i', '', $sql);
        $sql = preg_replace('/AUTO_INCREMENT\s*=\s*\d+/i', '', $sql);
        $sql = preg_replace('/CHARACTER SET\s+\w+\s+COLLATE\s+\w+/i', '', $sql);

        return $sql;
    }
}

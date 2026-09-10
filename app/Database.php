<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

/**
 * Nizam -- PDO connection layer (§Q "Database": PDO + prepared statements
 * only, everywhere). One connection per request, lazily created and reused --
 * repositories ask Database::connection() rather than each opening their own.
 *
 * Deliberately separate from database/migrate.php's own inline connection
 * code: those are standalone Phase 3 CLI tools that already work and were
 * runtime-verified as-is (§S-3 of the Phase 3 report -- "preserve the
 * existing schema... work with the Phase 3 database exactly as verified");
 * this class is the connection the application layer (controllers, services,
 * repositories) uses from Phase 4 on.
 */
final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $configPath = dataPath() . '/config/config.php';
        if (!is_file($configPath)) {
            throw new PDOException(
                'Missing config/config.php. Copy config/config.example.php and fill in real values.'
            );
        }

        /** @var array{driver?:string,host:string,port:int,database:string,username:string,password:string,charset:string} $config */
        $config = require $configPath;
        // Absent in every existing local/offline/Windows-packaged config.php
        // (written before this key existed) -- defaulting to 'mysql' keeps
        // every one of those deployments running exactly as before.
        $driver = $config['driver'] ?? 'mysql';

        if ($driver === 'pgsql') {
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $config['host'],
                $config['port'],
                $config['database']
            );
            self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Supabase's Transaction-mode pooler (port 6543) does not
                // support real server-side prepared statements -- each
                // statement in a "transaction" may be routed to a different
                // backend connection, breaking PREPARE/EXECUTE correlation.
                // Client-side emulated prepares are required for this
                // driver; MySQL keeps real prepares (below), which has
                // never had this constraint.
                PDO::ATTR_EMULATE_PREPARES => true,
            ]);
            // Postgres has no equivalent of MySQL's non-strict sql_mode
            // coercion (§S-9) to guard against -- type/constraint violations
            // are rejected by default, not silently coerced.
            return self::$connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        self::$connection = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // §S-9's own finding, confirmed live during Phase 3 verification: an
            // invalid ENUM value is only silently coerced under non-strict
            // sql_mode. Force a strict mode on every application connection so
            // that guarantee holds regardless of the server's own my.ini default.
            PDO::MYSQL_ATTR_INIT_COMMAND =>
                "SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'",
        ]);

        return self::$connection;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Nizam -- Setup Wizard's environment check (§N step 1). Folded into the
 * database-connection screen rather than given its own separate wizard
 * step: unlike every other step, a PHP-runtime check produces no persistent
 * artifact (no row, no file) for SetupStatusService to detect as "done," so
 * there is nothing to resume into -- it is simply re-run and displayed
 * every time step 1 is shown, which is the correct behavior for a stateless
 * check anyway.
 *
 * pdo_mysql and mbstring are hard requirements -- nothing already built
 * (Phase 3's own migrations, Phase 4's whole auth layer) works without them.
 * gd/zip/intl/fileinfo are checked and shown, but do not block progress:
 * nothing through Phase 5 uses them (they matter for Phase 6 photo uploads
 * and Phase 7 Excel export), so treating their absence as fatal here would
 * block a real install over a requirement that isn't actually load-bearing
 * yet.
 */
final class EnvironmentCheckService
{
    private const REQUIRED = ['pdo_mysql', 'mbstring'];
    private const OPTIONAL = ['gd', 'zip', 'intl', 'fileinfo'];

    /** @return array{phpVersion: string, missingRequired: string[], missingOptional: string[], ok: bool} */
    public function check(): array
    {
        $missingRequired = array_values(array_filter(self::REQUIRED, static fn (string $ext): bool => !extension_loaded($ext)));
        $missingOptional = array_values(array_filter(self::OPTIONAL, static fn (string $ext): bool => !extension_loaded($ext)));

        return [
            'phpVersion' => PHP_VERSION,
            'missingRequired' => $missingRequired,
            'missingOptional' => $missingOptional,
            'ok' => empty($missingRequired),
        ];
    }
}

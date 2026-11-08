<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ModuleRepository;

final class ModuleManager
{
    private static ?array $enabledCache = null;
    private ModuleRepository $repository;

    /**
     * Module dependencies map. A module cannot be enabled if its prerequisites are disabled.
     */
    private const DEPENDENCIES = [
        'admissions' => ['people', 'academics'],
        'finance'    => ['people', 'academics'],
        'hr'         => ['people'],
        'transport'  => ['people'],
        'library'    => ['people'],
    ];

    public function __construct(?ModuleRepository $repository = null)
    {
        $this->repository = $repository ?? new ModuleRepository();
    }

    public static function resetCache(): void
    {
        self::$enabledCache = null;
    }

    public function isEnabled(string $moduleKey): bool
    {
        if (self::$enabledCache === null) {
            self::$enabledCache = [];
            foreach ($this->repository->all() as $m) {
                self::$enabledCache[$m['module_key']] = (bool) $m['is_enabled'];
            }
        }

        return self::$enabledCache[$moduleKey] ?? true;
    }

    public function all(): array
    {
        return $this->repository->all();
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function setEnabled(string $moduleKey, bool $enabled): array
    {
        $module = $this->repository->find($moduleKey);
        if ($module === null) {
            return ['success' => false, 'error' => 'Module not found'];
        }

        if ($module['is_core'] && !$enabled) {
            return ['success' => false, 'error' => 'Core modules cannot be disabled'];
        }

        if ($enabled && isset(self::DEPENDENCIES[$moduleKey])) {
            foreach (self::DEPENDENCIES[$moduleKey] as $dep) {
                if (!$this->isEnabled($dep)) {
                    return [
                        'success' => false,
                        'error'   => "Cannot enable {$moduleKey} because prerequisite module '{$dep}' is disabled",
                    ];
                }
            }
        }

        $this->repository->setEnabled($moduleKey, $enabled);
        self::resetCache();

        return ['success' => true];
    }

    public function toggle(string $moduleKey, ?bool $enabled = null): bool
    {
        $target = $enabled ?? !$this->isEnabled($moduleKey);
        $res = $this->setEnabled($moduleKey, $target);
        return $res['success'];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ModuleRepository;

final class ModuleManager
{
    private static ?array $enabledCache = null;
    private ModuleRepository $repository;

    /**
     * Module dependency graph (single source of truth). A module cannot be
     * enabled while a prerequisite is disabled, and a module that other ACTIVE
     * modules depend on cannot be disabled out from under them.
     *
     * @var array<string,list<string>>
     */
    public const DEPENDENCIES = [
        'people'         => ['core'],
        'academics'      => ['core', 'people'],
        'attendance'     => ['core', 'people', 'academics'],
        'exams'          => ['core', 'people', 'academics'],
        'welfare'        => ['core', 'people'],
        'health_records' => ['core', 'people', 'welfare'],
        'reports'        => ['core'],
        'communication'  => ['core', 'people'],
        'guardian_portal'=> ['core', 'people', 'communication'],
        'admissions'     => ['people', 'academics'],
        'finance'        => ['people', 'academics'],
        'hr'             => ['people'],
        'transport'      => ['people'],
        'library'        => ['people'],
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
                self::$enabledCache[$m['module_key']] = dbBool($m['is_enabled']);
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
            return ['success' => false, 'error' => 'core_module'];
        }

        if ($enabled && isset(self::DEPENDENCIES[$moduleKey])) {
            foreach (self::DEPENDENCIES[$moduleKey] as $dep) {
                if (!$this->isEnabled($dep)) {
                    return [
                        'success' => false,
                        'error'   => 'missing_dependency:' . $dep,
                    ];
                }
            }
        }

        if (!$enabled) {
            $activeDependents = $this->activeDependents($moduleKey);
            if ($activeDependents !== []) {
                return [
                    'success' => false,
                    'error'   => 'active_dependents:' . implode(',', $activeDependents),
                ];
            }
        }

        $this->repository->setEnabled($moduleKey, $enabled);
        self::resetCache();

        ActivityLogger::log(
            'module.toggle', 'modules', (int) $module['id'],
            ($enabled ? 'Enabled' : 'Disabled') . ' module ' . $moduleKey,
            ['module' => $moduleKey, 'old' => !$enabled, 'new' => $enabled, 'time' => date('Y-m-d H:i:s')]
        );

        return ['success' => true];
    }

    public function toggle(string $moduleKey, ?bool $enabled = null): bool
    {
        $target = $enabled ?? !$this->isEnabled($moduleKey);
        $res = $this->setEnabled($moduleKey, $target);
        return $res['success'];
    }

    /** @return list<string> modules that declare $moduleKey as a prerequisite and are currently enabled */
    public function activeDependents(string $moduleKey): array
    {
        $dependents = [];
        foreach (self::DEPENDENCIES as $child => $parents) {
            if (in_array($moduleKey, $parents, true) && $this->isEnabled($child)) {
                $dependents[] = $child;
            }
        }
        return $dependents;
    }

    /** @return list<string> prerequisites for a module (for UI tooltips). */
    public function dependenciesFor(string $moduleKey): array
    {
        return self::DEPENDENCIES[$moduleKey] ?? [];
    }
}

<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Flash;
use App\Request;
use App\Response;
use App\Services\ModuleManager;

final class ModuleGateMiddleware implements MiddlewareInterface
{
    private string $moduleKey;
    private ModuleManager $manager;

    public function __construct(string $moduleKey, ?ModuleManager $manager = null)
    {
        $this->moduleKey = $moduleKey;
        $this->manager = $manager ?? new ModuleManager();
    }

    public static function for(string $moduleKey): self
    {
        return new self($moduleKey);
    }

    public function handle(Request $request): bool
    {
        if ($this->manager->isEnabled($this->moduleKey)) {
            return true;
        }

        Flash::warning(__('modules.disabled_notice', ['module' => $this->moduleKey]));
        Response::redirect('/dashboard');
        return false;
    }
}

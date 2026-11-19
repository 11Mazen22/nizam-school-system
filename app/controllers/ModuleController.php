<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Request;
use App\Response;
use App\Services\ModuleManager;

final class ModuleController extends Controller
{
    private ModuleManager $manager;

    public function __construct(?ModuleManager $manager = null)
    {
        $this->manager = $manager ?? new ModuleManager();
    }

    public function index(Request $request): void
    {
        $modules = $this->manager->all();
        $this->view('settings/modules', ['modules' => $modules]);
    }

    public function toggle(Request $request): void
    {
        $key = $request->paramString('key');
        if ($key === null) {
            Response::redirect('/settings/modules');
            return;
        }
        $enable = (bool) $request->post('enable', false);
        $result = $this->manager->setEnabled($key, $enable);

        if ($result['success']) {
            Flash::success(__('modules.toggle_success', ['module' => moduleLabel($key)]));
        } else {
            Flash::error($this->errorMessage((string) ($result['error'] ?? '')));
        }

        Response::redirect('/settings/modules');
    }

    /** Map stable ModuleManager reason codes to localized, specific messages. */
    private function errorMessage(string $code): string
    {
        if ($code === 'core_module') {
            return __('modules.error.core');
        }
        if (str_starts_with($code, 'missing_dependency:')) {
            $dep = substr($code, strlen('missing_dependency:'));
            return __('modules.error.missing_dependency', ['module' => moduleLabel($dep)]);
        }
        if (str_starts_with($code, 'active_dependents:')) {
            $keys = array_filter(explode(',', substr($code, strlen('active_dependents:'))));
            $labels = array_map(static fn (string $k): string => moduleLabel($k), $keys);
            return __('modules.error.active_dependents', ['modules' => implode('، ', $labels)]);
        }
        return $code !== '' ? $code : __('modules.toggle_error');
    }
}

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

    public function toggle(Request $request, string $key): void
    {
        $enable = (bool) $request->post('enable', false);
        $result = $this->manager->setEnabled($key, $enable);

        if ($result['success']) {
            Flash::success(__('modules.toggle_success'));
        } else {
            Flash::error($result['error'] ?? __('modules.toggle_error'));
        }

        Response::redirect('/settings/modules');
    }
}

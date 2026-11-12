<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Repositories\AcademicConfigRepository;
use App\Repositories\AcademicYearRepository;
use App\Repositories\GradeRepository;
use App\Request;
use App\Services\AcademicConfigService;

/**
 * Nizam -- Settings -> Academic Structure, Terms and Grading.
 *
 * These are the three configuration surfaces that make the platform serve a
 * school other than the one it was originally written for: how many stages a
 * school has, how its year is divided, and what its marks actually mean.
 * All three are administrator-only (`settings.manage`), because changing a
 * grading band changes what every historical report card appears to say.
 */
final class AcademicConfigController extends Controller
{
    public function __construct(
        private readonly AcademicConfigRepository $config = new AcademicConfigRepository(),
        private readonly AcademicConfigService $service = new AcademicConfigService(),
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
        private readonly GradeRepository $grades = new GradeRepository(),
    ) {
    }

    // ----------------------------------------------------------- Stages

    public function stages(Request $request): void
    {
        $this->render('stages', ['stages' => $this->config->stages(), 'grades' => $this->grades->all()]);
    }

    public function saveStage(Request $request): void
    {
        $result = $this->service->saveStage(
            null,
            (string) ($request->post('code', '') ?: ''),
            (string) ($request->post('name_en', '') ?: ''),
            (string) ($request->post('name_ar', '') ?: ''),
            (int) ($request->post('sort_order', '') ?: 0),
            true
        );
        $this->flashResult($result, 'structure.stage.created', '/settings/structure');
    }

    public function updateStage(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null || $this->config->findStage($id) === null) {
            $this->redirect('/settings/structure');
            return;
        }

        $result = $this->service->saveStage(
            $id,
            (string) ($request->post('code', '') ?: ''),
            (string) ($request->post('name_en', '') ?: ''),
            (string) ($request->post('name_ar', '') ?: ''),
            (int) ($request->post('sort_order', '') ?: 0),
            $request->post('is_active') === '1'
        );
        $this->flashResult($result, 'structure.stage.updated', '/settings/structure');
    }

    public function deleteStage(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null) {
            $this->redirect('/settings/structure');
            return;
        }
        $this->flashResult($this->service->deleteStage($id), 'structure.stage.deleted', '/settings/structure');
    }

    public function assignGradeStages(Request $request): void
    {
        $map = [];
        foreach ($request->postMap('grade_stage') as $gradeId => $stageId) {
            $map[(int) $gradeId] = $stageId === '' ? null : (int) $stageId;
        }
        $this->service->assignGradeStages($map);
        Flash::set('success', __('structure.grades.saved'));
        $this->redirect('/settings/structure');
    }

    // ------------------------------------------------------------ Terms

    public function terms(Request $request): void
    {
        $this->render('terms', [
            'terms' => $this->config->terms(),
            'years' => $this->years->all(),
        ]);
    }

    public function saveTerm(Request $request): void
    {
        $yearId = (int) ($request->post('academic_year_id', '') ?: 0);
        $year = $yearId > 0 ? $this->years->find($yearId) : null;
        if ($year === null) {
            Flash::set('danger', __('structure.terms.no_years'));
            $this->redirect('/settings/terms');
            return;
        }

        $result = $this->service->saveTerm(
            null,
            $yearId,
            (string) ($request->post('name_en', '') ?: ''),
            (string) ($request->post('name_ar', '') ?: ''),
            (int) ($request->post('term_number', '') ?: 0),
            (string) ($request->post('start_date', '') ?: ''),
            (string) ($request->post('end_date', '') ?: ''),
            true,
            (string) $year['start_date'],
            (string) $year['end_date']
        );
        $this->flashResult($result, 'structure.terms.created', '/settings/terms');
    }

    public function updateTerm(Request $request): void
    {
        $id = $request->paramInt('id');
        $term = $id !== null ? $this->config->findTerm($id) : null;
        if ($term === null) {
            $this->redirect('/settings/terms');
            return;
        }

        $year = $this->years->find((int) $term['academic_year_id']);
        $result = $this->service->saveTerm(
            (int) $term['id'],
            (int) $term['academic_year_id'],
            (string) ($request->post('name_en', '') ?: ''),
            (string) ($request->post('name_ar', '') ?: ''),
            (int) ($request->post('term_number', '') ?: 0),
            (string) ($request->post('start_date', '') ?: ''),
            (string) ($request->post('end_date', '') ?: ''),
            $request->post('is_active') === '1',
            $year !== null ? (string) $year['start_date'] : null,
            $year !== null ? (string) $year['end_date'] : null
        );
        $this->flashResult($result, 'structure.terms.updated', '/settings/terms');
    }

    public function deleteTerm(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null) {
            $this->redirect('/settings/terms');
            return;
        }
        $this->flashResult($this->service->deleteTerm($id), 'structure.terms.deleted', '/settings/terms');
    }

    public function activateTerm(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null) {
            $this->redirect('/settings/terms');
            return;
        }
        $this->flashResult($this->service->activateTerm($id), 'structure.terms.updated', '/settings/terms');
    }

    // ---------------------------------------------------------- Grading

    public function grading(Request $request): void
    {
        $scales = $this->config->scales();

        $selectedId = (int) ($request->query('scale', '') ?: 0);
        $selected = $selectedId > 0 ? $this->config->findScale($selectedId) : null;
        if ($selected === null) {
            $selected = $this->config->defaultScale();
        }
        if ($selected === null && $scales !== []) {
            $selected = $this->config->findScale((int) $scales[0]['id']);
        }

        $this->render('grading', [
            'scales'   => $scales,
            'selected' => $selected,
            'bands'    => $selected !== null ? $this->config->bands((int) $selected['id']) : [],
        ]);
    }

    public function saveScale(Request $request): void
    {
        $result = $this->service->saveScale(
            null,
            (string) ($request->post('name_en', '') ?: ''),
            (string) ($request->post('name_ar', '') ?: ''),
            (string) ($request->post('scale_type', '') ?: ''),
            (string) ($request->post('description', '') ?: ''),
            $request->post('is_default') === '1',
            self::bandsFromRequest($request)
        );
        $this->flashResult($result, 'structure.grading.created', '/settings/grading');
    }

    public function updateScale(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null || $this->config->findScale($id) === null) {
            $this->redirect('/settings/grading');
            return;
        }

        $result = $this->service->saveScale(
            $id,
            (string) ($request->post('name_en', '') ?: ''),
            (string) ($request->post('name_ar', '') ?: ''),
            (string) ($request->post('scale_type', '') ?: ''),
            (string) ($request->post('description', '') ?: ''),
            $request->post('is_default') === '1',
            self::bandsFromRequest($request)
        );
        $this->flashResult($result, 'structure.grading.updated', '/settings/grading?scale=' . $id);
    }

    public function deleteScale(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null) {
            $this->redirect('/settings/grading');
            return;
        }
        $this->flashResult($this->service->deleteScale($id), 'structure.grading.deleted', '/settings/grading');
    }

    public function makeDefault(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id === null) {
            $this->redirect('/settings/grading');
            return;
        }
        $this->flashResult($this->service->makeDefault($id), 'structure.grading.defaulted', '/settings/grading');
    }

    /**
     * Rows are enumerated by the hidden band_index[] field rather than by
     * position: a checkbox inside a repeated row only appears in POST when it
     * is checked, so positional pairing of band_passing[] with band_label[]
     * would silently shift every flag after the first unchecked row.
     *
     * @return array<int, array{label:string,min:string,max:string,gpa:?string,passing:bool,desc_en:?string,desc_ar:?string}>
     */
    private static function bandsFromRequest(Request $request): array
    {
        $labels = $request->postArray('band_label');
        $mins = $request->postArray('band_min');
        $maxs = $request->postArray('band_max');
        $gpas = $request->postArray('band_gpa');
        $descEn = $request->postArray('band_desc_en');
        $descAr = $request->postArray('band_desc_ar');
        $passing = $request->postMap('band_passing');

        $bands = [];
        foreach ($labels as $index => $label) {
            if (trim($label) === '') {
                continue;
            }
            $bands[] = [
                'label'   => trim($label),
                'min'     => $mins[$index] ?? '',
                'max'     => $maxs[$index] ?? '',
                'gpa'     => $gpas[$index] ?? null,
                'passing' => isset($passing[(string) $index]),
                'desc_en' => trim((string) ($descEn[$index] ?? '')) ?: null,
                'desc_ar' => trim((string) ($descAr[$index] ?? '')) ?: null,
            ];
        }

        return $bands;
    }

    /** @param array{ok: bool, error?: string, id?: int} $result */
    private function flashResult(array $result, string $successKey, string $redirect): void
    {
        if ($result['ok']) {
            Flash::set('success', __($successKey));
        } else {
            Flash::set('danger', __($result['error'] ?? 'validation.required'));
        }
        $this->redirect($redirect);
    }

    /** @param array<string,mixed> $data */
    private function render(string $section, array $data): void
    {
        $this->view('settings/structure', $data + [
            'section' => $section,
            'error'   => null,
            'stages'  => [],
            'grades'  => [],
            'terms'   => [],
            'years'   => [],
            'scales'  => [],
            'selected' => null,
            'bands'   => [],
        ]);
    }
}

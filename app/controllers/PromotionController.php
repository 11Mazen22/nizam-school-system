<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Repositories\AcademicYearRepository;
use App\Repositories\EnrollmentRepository;
use App\Request;
use App\Services\PromotionService;
use RuntimeException;

/** §I.1 student promotion -- the workflow-specific route (not plain CRUD), per §Q's route table. */
final class PromotionController extends Controller
{
    public function __construct(
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
        private readonly EnrollmentRepository $enrollments = new EnrollmentRepository(),
        private readonly PromotionService $service = new PromotionService(),
    ) {
    }

    /** GET /students/promotion -- year picker first, then (once both are chosen) the actual preview table. */
    public function preview(Request $request): void
    {
        $sourceId = (int) ($request->query('source', '') ?: 0);
        $targetId = (int) ($request->query('target', '') ?: 0);

        if ($sourceId <= 0 || $targetId <= 0) {
            $this->view('students/promotion-pick', ['error' => null, 'years' => $this->years->all()]);
            return;
        }

        $source = $this->years->find($sourceId);
        $target = $this->years->find($targetId);
        if ($source === null || $target === null || $sourceId === $targetId) {
            $this->view('students/promotion-pick', [
                'error' => __('promotion.invalid_years'), 'years' => $this->years->all(),
            ]);
            return;
        }

        $rows = $this->service->preview($sourceId, $targetId);
        $this->view('students/promotion-preview', [
            'source' => $source, 'target' => $target, 'rows' => $rows,
        ]);
    }

    /** POST /students/promotion -- the actual batch (§I.1 steps 6-8). */
    public function confirm(Request $request): void
    {
        $sourceId = (int) ($request->post('source_year_id', '') ?: 0);
        $targetId = (int) ($request->post('target_year_id', '') ?: 0);
        $source = $this->years->find($sourceId);
        $target = $this->years->find($targetId);

        if ($source === null || $target === null || $sourceId === $targetId) {
            $this->redirect('/students/promotion');
            return;
        }

        $decisions = $this->collectDecisions($request);

        try {
            $result = $this->service->confirm($sourceId, $targetId, $decisions);
        } catch (RuntimeException $e) {
            Flash::set('danger', match ($e->getMessage()) {
                'lock_held' => __('promotion.lock_held'),
                'year_closed' => __('academic_years.year_closed'),
                default => __('error.500.title'),
            });
            $this->redirect('/students/promotion');
            return;
        }

        $c = $result['counts'];
        Flash::set('success', __('promotion.batch_done', [
            'promoted' => $c['promoted'], 'repeated' => $c['repeated'], 'graduated' => $c['graduated'],
            'transferred' => $c['transferred'], 'withdrawn' => $c['withdrawn'],
        ]));
        if (count($result['skipped']) > 0) {
            Flash::set('warning', __('promotion.some_skipped', ['count' => count($result['skipped'])]));
        }
        $this->redirect('/students');
    }

    /** POST /students/{id}/undo-promotion -- {id} here is the STUDENT's current (target-year) enrollment id. */
    public function undo(Request $request): void
    {
        $enrollmentId = $request->paramInt('id');
        if ($enrollmentId === null) {
            $this->redirect('/students');
            return;
        }

        $enrollment = $this->enrollments->find($enrollmentId);
        try {
            $this->service->undoLast($enrollmentId);
            Flash::set('success', __('promotion.undo_done'));
        } catch (RuntimeException $e) {
            Flash::set('danger', $e->getMessage() === 'year_closed'
                ? __('academic_years.year_closed')
                : __('promotion.undo_failed'));
        }

        $this->redirect($enrollment !== null ? "/students/{$enrollment['student_id']}" : '/students');
    }

    /** @return array<int, array{action: string, class_id: ?int}> keyed by enrollment_id */
    private function collectDecisions(Request $request): array
    {
        $enrollmentIds = $request->postArray('enrollment_id');
        $actions = $request->postMap('action');
        $classIds = $request->postMap('class_id');

        $decisions = [];
        foreach ($enrollmentIds as $idStr) {
            $id = (int) $idStr;
            if ($id <= 0) {
                continue;
            }
            $action = $actions[$idStr] ?? 'graduate';
            $classIdRaw = $classIds[$idStr] ?? '';
            $decisions[$id] = [
                'action' => in_array($action, ['promote', 'repeat', 'graduate', 'transferred', 'withdrawn'], true) ? $action : 'graduate',
                'class_id' => $classIdRaw !== '' ? (int) $classIdRaw : null,
            ];
        }
        return $decisions;
    }
}

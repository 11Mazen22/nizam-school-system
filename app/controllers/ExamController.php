<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Repositories\ClassRepository;
use App\Repositories\ExamRepository;
use App\Request;
use App\Response;
use App\Services\ExamScoreImportService;
use App\Services\ExamService;
use RuntimeException;
use Throwable;

final class ExamController extends Controller
{
    public function __construct(
        private readonly ExamService     $service = new ExamService(),
        private readonly ExamRepository  $repo    = new ExamRepository(),
        private readonly ClassRepository $classes = new ClassRepository(),
    ) {
    }

    /** GET /exams — list exams for active year, exam management. */
    public function index(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();
        $exams  = $yearId ? $this->service->getExamsForYear($yearId) : [];
        $this->view('exams/index', ['exams' => $exams, 'yearId' => $yearId]);
    }

    /** POST /exams — create exam. */
    public function store(Request $request): void
    {
        $nameEn   = $request->post('name_en', '') ?? '';
        $nameAr   = $request->post('name_ar', '') ?? '';
        $term     = (int) ($request->post('term', '1') ?? '1');
        $maxScore = (float) ($request->post('max_score', '100') ?? '100');
        $weight   = (float) ($request->post('weight', '100') ?? '100');

        try {
            $this->service->createExam($nameEn, $nameAr, $term, $maxScore, $weight);
            Flash::set('success', __('exams.created'));
        } catch (RuntimeException $e) {
            Flash::set('danger', $e->getMessage() === 'no_active_year'
                ? __('students.no_active_year')
                : __('validation.required'));
        }
        $this->redirect('/exams');
    }

    /** POST /exams/{id}/update — update exam. */
    public function update(Request $request): void
    {
        $id       = (int) ($request->paramInt('id') ?? 0);
        $nameEn   = $request->post('name_en', '') ?? '';
        $nameAr   = $request->post('name_ar', '') ?? '';
        $term     = (int) ($request->post('term', '1') ?? '1');
        $maxScore = (float) ($request->post('max_score', '100') ?? '100');
        $weight   = (float) ($request->post('weight', '100') ?? '100');

        try {
            $this->service->updateExam($id, $nameEn, $nameAr, $term, $maxScore, $weight);
            Flash::set('success', __('exams.updated'));
        } catch (RuntimeException) {
            Flash::set('danger', __('validation.required'));
        }
        $this->redirect('/exams');
    }

    /** POST /exams/{id}/delete — delete exam. */
    public function delete(Request $request): void
    {
        $id = $request->paramInt('id');
        if ($id !== null) {
            $this->service->deleteExam($id);
            Flash::set('success', __('exams.deleted'));
        }
        $this->redirect('/exams');
    }

    /** GET /exams/{id}/scores?class_id=N — grade entry sheet. */
    public function scores(Request $request): void
    {
        $examId  = $request->paramInt('id') ?? 0;
        $classId = (int) ($request->query('class_id') ?? 0);
        $yearId  = AcademicYearContext::activeYearId();

        $exam    = $this->repo->find($examId);
        if ($exam === null || (int) $exam['academic_year_id'] !== $yearId) {
            Flash::set('danger', __('exams.not_found'));
            $this->redirect('/exams');
            return;
        }

        $classes  = $yearId ? $this->classes->allForYear($yearId) : [];
        $subjects = $classId > 0 && $yearId ? $this->repo->subjectsForClass($classId, $yearId) : [];
        $subjectId = (int) ($request->query('subject_id') ?? ($subjects[0]['id'] ?? 0));
        if (!in_array($subjectId, array_map('intval', array_column($subjects, 'id')), true)) {
            $subjectId = 0;
        }
        $sheet = $classId > 0 ? $this->service->getScoreSheet($examId, $classId, $subjectId) : [];

        $this->view('exams/scores', [
            'exam'     => $exam,
            'classes'  => $classes,
            'classId'  => $classId,
            'subjects' => $subjects,
            'subjectId' => $subjectId,
            'sheet'    => $sheet,
            'scale'    => $this->service->activeScale(),
        ]);
    }

    /** POST /exams/{id}/scores — save grades. */
    public function saveScores(Request $request): void
    {
        $examId  = (int) ($request->paramInt('id') ?? 0);
        $classId = (int) ($request->post('class_id', '0') ?? '0');
        $userId  = (int) ($_SESSION['user_id'] ?? 0);

        // scores[studentId][subject_id|score|notes]
        $rawScores = $_POST['scores'] ?? [];
        $records   = [];
        try {
            if (!is_array($rawScores)) {
                throw new RuntimeException('validation_error');
            }
            foreach ($rawScores as $sid => $entry) {
                if (filter_var($sid, FILTER_VALIDATE_INT) === false || !is_array($entry)) {
                    throw new RuntimeException('validation_error');
                }
                $records[$sid] = $entry;
            }
            $this->service->saveScores($examId, $classId, $records, $userId ?: null);
            Flash::set('success', __('exams.scores_saved'));
        } catch (RuntimeException) {
            Flash::set('danger', __('exams.scores_failed'));
        }
        $subjectId = (int) ($request->post('subject_id', '0') ?? '0');
        $this->redirect("/exams/{$examId}/scores?class_id={$classId}&subject_id={$subjectId}");
    }

    // ── Excel score import ────────────────────────────────────────────────────

    private const IMPORT_SESSION = 'exam_score_import';

    /** GET /exams/{id}/scores/import — workbook upload and template download. */
    public function importForm(Request $request): void
    {
        $context = $this->importContext($request);
        if ($context === null) {
            $this->redirect('/exams');
            return;
        }

        $this->view('exams/score-import', [
            'exam' => $context['exam'],
            'classes' => $context['classes'],
            'classId' => $context['classId'],
            'headers' => ExamScoreImportService::HEADERS,
        ]);
    }

    /** GET /exams/{id}/scores/import/template?class_id=N */
    public function importTemplate(Request $request): void
    {
        $context = $this->importContext($request);
        if ($context === null) {
            $this->redirect('/exams');
            return;
        }

        try {
            $bytes = (new ExamScoreImportService())->template(
                (int) $context['exam']['id'],
                $context['classId'],
                currentLocale()
            );
        } catch (RuntimeException) {
            Flash::set('danger', __('exams.import_class_required'));
            $this->redirect('/exams/' . (int) $context['exam']['id'] . '/scores/import?class_id=' . $context['classId']);
            return;
        }

        Response::noStore();
        Response::downloadFile(
            $bytes,
            'exam-scores-' . (string) $context['classId'] . '.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        exit;
    }

    /** POST /exams/{id}/scores/import/preview — parse + validate, write nothing. */
    public function importPreview(Request $request): void
    {
        $context = $this->importContext($request);
        if ($context === null) {
            $this->redirect('/exams');
            return;
        }
        $examId = (int) $context['exam']['id'];
        $classId = $context['classId'];
        $back = "/exams/{$examId}/scores/import?class_id={$classId}";

        $file = $_FILES['scores_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Flash::set('danger', __('exams.import_file_missing'));
            $this->redirect($back);
            return;
        }

        try {
            $service = new ExamScoreImportService();
            $parsed = $service->parse((string) $file['tmp_name']);
            if ($parsed === []) {
                Flash::set('warning', __('exams.import_no_rows'));
                $this->redirect($back);
                return;
            }
            $result = $service->validate($parsed, $examId, $classId);
        } catch (RuntimeException $e) {
            Flash::set('danger', __($this->importErrorKey($e->getMessage())));
            $this->redirect($back);
            return;
        } catch (Throwable $e) {
            error_log('Exam score import parse error: ' . $e->getMessage());
            Flash::set('danger', __('exams.import_unreadable'));
            $this->redirect($back);
            return;
        }

        $token = bin2hex(random_bytes(12));
        $directory = dataPath() . '/storage/imports/exam-scores';
        @mkdir($directory, 0775, true);
        $path = $directory . '/' . $token . '.xlsx';
        if (!move_uploaded_file((string) $file['tmp_name'], $path) && !rename((string) $file['tmp_name'], $path)) {
            Flash::set('danger', __('exams.import_unreadable'));
            $this->redirect($back);
            return;
        }

        $staged = $_SESSION[self::IMPORT_SESSION] ?? [];
        if (!is_array($staged)) {
            $staged = [];
        }
        foreach ($staged as $oldToken => $old) {
            if (!is_array($old) || (int) ($old['created_at'] ?? 0) < time() - 3600) {
                if (is_string($old['path'] ?? null) && is_file($old['path'])) {
                    @unlink($old['path']);
                }
                unset($staged[$oldToken]);
            }
        }
        $staged[$token] = [
            'path' => $path,
            'exam_id' => $examId,
            'class_id' => $classId,
            'file' => (string) ($file['name'] ?? ''),
            'created_at' => time(),
        ];
        $_SESSION[self::IMPORT_SESSION] = $staged;

        $this->view('exams/score-import-preview', [
            'exam' => $context['exam'],
            'classId' => $classId,
            'className' => $context['className'],
            'token' => $token,
            'rows' => $result['rows'],
            'counts' => $result['counts'],
            'fileName' => (string) ($file['name'] ?? ''),
        ]);
    }

    /** POST /exams/{id}/scores/import/commit — re-validate, then write in one transaction. */
    public function importCommit(Request $request): void
    {
        $examId = (int) ($request->paramInt('id') ?? 0);
        $token = (string) ($request->post('token', '') ?: '');
        $staged = is_array($_SESSION[self::IMPORT_SESSION] ?? null)
            ? ($_SESSION[self::IMPORT_SESSION][$token] ?? null) : null;

        if (!is_array($staged) || (int) $staged['exam_id'] !== $examId) {
            Flash::set('danger', __('exams.import_expired'));
            $this->redirect('/exams/' . $examId . '/scores');
            return;
        }
        unset($_SESSION[self::IMPORT_SESSION][$token]);
        $path = (string) ($staged['path'] ?? '');

        try {
            $service = new ExamScoreImportService();
            $parsed = $service->parse($path);
            $result = $service->validate($parsed, (int) $staged['exam_id'], (int) $staged['class_id']);
            if ($result['counts']['error'] > 0) {
                Flash::set('danger', __('exams.import_errors_changed'));
                $this->redirect('/exams/' . $examId . '/scores/import?class_id=' . (int) $staged['class_id']);
                return;
            }
            $outcome = $service->commit(
                $result['rows'],
                $examId,
                (int) ($_SESSION['user_id'] ?? 0) ?: null
            );
            Flash::set('success', strtr(__('exams.import_done'), ['{saved}' => (string) $outcome['saved']]));
        } catch (RuntimeException $e) {
            Flash::set('danger', __($this->importErrorKey($e->getMessage())));
        } catch (Throwable $e) {
            error_log('Exam score import commit error: ' . $e->getMessage());
            Flash::set('danger', __('exams.import_failed'));
        } finally {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }

        $this->redirect("/exams/{$examId}/scores?class_id=" . (int) $staged['class_id']);
    }

    private function importErrorKey(string $code): string
    {
        return match ($code) {
            'exam_import_file_missing' => 'exams.import_file_missing',
            'exam_import_file_too_large' => 'exams.import_file_too_large',
            'exam_import_format' => 'exams.import_format',
            'exam_import_unreadable' => 'exams.import_unreadable',
            'exam_import_empty' => 'exams.import_no_rows',
            'exam_import_headers' => 'exams.import_headers',
            'exam_import_too_many_rows' => 'exams.import_too_many_rows',
            'exam_not_found' => 'exams.import_exam_not_found',
            'class_not_found' => 'exams.import_class_not_found',
            'export_failed' => 'exams.import_export_failed',
            default => 'exams.import_failed',
        };
    }

    /** @return array{exam:array,classes:array,classId:int,className:string}|null */
    private function importContext(Request $request): ?array
    {
        $examId = (int) ($request->paramInt('id') ?? 0);
        $yearId = AcademicYearContext::activeYearId();
        $exam = $this->repo->find($examId);
        if ($exam === null || $yearId === null || (int) $exam['academic_year_id'] !== $yearId) {
            Flash::set('danger', __('exams.not_found'));
            return null;
        }
        $classes = $this->classes->allForYear($yearId);
        $classId = (int) ($request->query('class_id') ?? $request->post('class_id', '0') ?? '0');
        if ($classId > 0 && !in_array($classId, array_map('intval', array_column($classes, 'id')), true)) {
            $classId = 0;
        }
        $className = '';
        foreach ($classes as $class) {
            if ((int) $class['id'] === $classId) {
                $className = trim((string) $class['name']);
                break;
            }
        }

        return ['exam' => $exam, 'classes' => $classes, 'classId' => $classId, 'className' => $className];
    }

    /** GET /exams/ranking?class_id=N — class ranking table. */
    public function ranking(Request $request): void
    {
        $yearId  = AcademicYearContext::activeYearId();
        $classId = (int) ($request->query('class_id') ?? 0);
        $classes = $yearId ? $this->classes->allForYear($yearId) : [];
        $q       = trim((string) ($request->query('q') ?? ''));
        $ranking = ($classId > 0 && $yearId)
            ? $this->service->getClassRanking($classId, $yearId) : [];
        $rankingTotal = count($ranking);
        if ($q !== '') {
            // Filtering happens on the server so ?q= also works with scripts
            // off and survives a refresh; the ranks keep their real positions.
            $ranking = filterRowsBySearch($ranking, $q, ['full_name', 'student_code']);
        }

        $this->view('exams/ranking', [
            'classes'  => $classes,
            'classId'  => $classId,
            'q'        => $q,
            'ranking'  => $ranking,
            'rankingTotal' => $rankingTotal,
        ]);
    }

    /** GET /exams/report-card/{studentId} — printable PDF report card. */
    public function reportCard(Request $request): void
    {
        $studentId = $request->paramInt('id') ?? 0;
        $yearId    = AcademicYearContext::activeYearId() ?? 0;

        // The report card is a NIZAM document like every other export: one
        // engine, one design system, correct Arabic shaping. The previous
        // mPDF view here rendered the same data with a fallback font and no
        // institutional identity, so it was retired with the rest of the
        // legacy print path.
        try {
            $result = (new \App\Services\Documents\DocumentEngine())->export(
                'report-card',
                ['student_id' => (string) $studentId],
                'pdf',
                []
            );
        } catch (\Throwable) {
            Flash::set('danger', __('document.errors.generic'));
            $this->redirect('/students/' . $studentId);
            return;
        }

        \App\Response::noStore();
        \App\Response::downloadFile($result['bytes'], $result['filename'], $result['contentType'], true);
        exit;
    }
}

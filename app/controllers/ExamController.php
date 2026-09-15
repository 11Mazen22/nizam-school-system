<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Middleware\AcademicYearContext;
use App\Repositories\ClassRepository;
use App\Repositories\ExamRepository;
use App\Request;
use App\Services\ExamService;
use Mpdf\Mpdf;
use RuntimeException;

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

    /** GET /exams/ranking?class_id=N — class ranking table. */
    public function ranking(Request $request): void
    {
        $yearId  = AcademicYearContext::activeYearId();
        $classId = (int) ($request->query('class_id') ?? 0);
        $classes = $yearId ? $this->classes->allForYear($yearId) : [];
        $ranking = ($classId > 0 && $yearId)
            ? $this->service->getClassRanking($classId, $yearId) : [];

        $this->view('exams/ranking', [
            'classes'  => $classes,
            'classId'  => $classId,
            'ranking'  => $ranking,
        ]);
    }

    /** GET /exams/report-card/{studentId} — printable PDF report card. */
    public function reportCard(Request $request): void
    {
        $studentId = $request->paramInt('id') ?? 0;
        $yearId    = AcademicYearContext::activeYearId() ?? 0;

        $grid = $this->service->getStudentScoreGrid($studentId, $yearId);

        // Check if mPDF is available
        if (!class_exists(Mpdf::class)) {
            Flash::set('danger', __('exams.pdf_unavailable'));
            $this->redirect('/exams');
            return;
        }

        // Fetch student name for PDF header
        $studentRow = \App\Database::connection()
            ->prepare('SELECT full_name, student_code FROM students WHERE id = :id');
        $studentRow->execute(['id' => $studentId]);
        $student = $studentRow->fetch() ?: ['full_name' => "Student #{$studentId}", 'student_code' => ''];

        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'default_font' => 'dejavusans']);
        $mpdf->SetDirectionality(currentLocale() === 'ar' ? 'rtl' : 'ltr');

        ob_start();
        require dirname(__DIR__, 2) . '/views/exams/report_card_pdf.php';
        $html = ob_get_clean();

        $mpdf->WriteHTML($html);
        $mpdf->Output("report_card_{$studentId}.pdf", 'I');
        exit;
    }
}

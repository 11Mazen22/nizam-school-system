<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\ErrorHandler;
use App\Repositories\AcademicYearRepository;
use App\Repositories\ClassRepository;
use App\Repositories\GradeRepository;
use App\Repositories\SubjectRepository;
use App\Request;
use App\Response;
use App\Services\ExportService;
use App\Services\ReportService;
use RuntimeException;

/**
 * Nizam -- §K's six reports. GET /reports/{key} shows the filter form +
 * on-screen table (which doubles as the print view via print.css's @media
 * print rules -- §K's "shared report conventions" don't ask for a separate
 * print screen, just print-safe CSS on this same one). GET
 * /reports/{key}/export/{format} streams the PDF/Excel file. Every
 * "reports.view"/"reports.export" permission check is server-side
 * (RoleGuardMiddleware on the route) -- nothing here re-implements or
 * substitutes for that.
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports = new ReportService(),
        private readonly ExportService $export = new ExportService(),
        private readonly AcademicYearRepository $years = new AcademicYearRepository(),
        private readonly GradeRepository $grades = new GradeRepository(),
        private readonly ClassRepository $classes = new ClassRepository(),
        private readonly SubjectRepository $subjects = new SubjectRepository(),
    ) {
    }

    public function index(Request $request): void
    {
        $this->view('reports/index', ['catalog' => $this->reports->catalog()]);
    }

    public function show(Request $request): void
    {
        $key = $this->keyFromPath($request);
        if ($key === null || !$this->reports->isKnownReport($key)) {
            ErrorHandler::renderNotFound();
            return;
        }

        $filters = $this->collectFilters($request);
        $locale = currentLocale();

        $report = null;
        $error = null;
        // A required filter is simply not yet chosen on first load (e.g. no
        // year picked) -- that's a normal empty filter form, not an error;
        // only an actually-invalid value (a nonexistent or cross-scope id)
        // is shown as one.
        if (!empty($filters)) {
            try {
                $report = $this->reports->build($key, $filters, $locale);
            } catch (RuntimeException $e) {
                if ($e->getMessage() !== 'missing_filter') {
                    $error = $e->getMessage() === 'invalid_filter' ? __('reports.invalid_filter') : __('error.500.title');
                }
            }
        }

        $this->view('reports/show', [
            'key' => $key,
            'report' => $report,
            'error' => $error,
            'filters' => $filters,
            'years' => $this->years->all(),
            'grades' => $this->grades->all(true),
            'subjects' => $this->subjects->all(true),
            'classesForGrade' => isset($filters['grade']) && $filters['grade'] !== '' && isset($filters['year']) && $filters['year'] !== ''
                ? $this->classes->forGradeAndYear((int) $filters['grade'], (int) $filters['year']) : [],
        ]);
    }

    public function export(Request $request): void
    {
        $key = $this->keyFromPath($request);
        $format = $request->paramString('format');
        if ($key === null || $format === null || !$this->reports->isKnownReport($key)) {
            ErrorHandler::renderNotFound();
            return;
        }
        // §Q "Prevent invalid combinations": a format not in this report's
        // own §K-specified list is rejected even if the string itself
        // (e.g. "pdf") is valid for some OTHER report.
        if (!$this->reports->isValidFormat($key, $format)) {
            ErrorHandler::renderNotFound();
            return;
        }

        $filters = $this->collectFilters($request);
        $locale = currentLocale();

        try {
            $report = $this->reports->build($key, $filters, $locale);
        } catch (RuntimeException $e) {
            ErrorHandler::renderNotFound();
            return;
        }

        $filename = $key . '_' . date('Ymd_His');
        if ($format === 'pdf') {
            Response::download($this->export->toPdf($report, $locale), "{$filename}.pdf", 'application/pdf');
        } elseif ($format === 'excel') {
            Response::download(
                $this->export->toExcel($report, $locale),
                "{$filename}.xlsx",
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
        } else {
            ErrorHandler::renderNotFound();
        }
    }

    private function keyFromPath(Request $request): ?string
    {
        return $request->paramString('key');
    }

    /** @return array<string,string> only the recognized filter keys, never arbitrary query params */
    private function collectFilters(Request $request): array
    {
        $filters = [];
        foreach (['year', 'grade', 'class', 'subject', 'sort'] as $name) {
            $value = $request->query($name, '');
            if ($value !== '') {
                $filters[$name] = $value;
            }
        }
        return $filters;
    }
}

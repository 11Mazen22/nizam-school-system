<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Database;
use App\Middleware\AcademicYearContext;
use App\Request;

/**
 * Nizam -- weekly assessment distribution.
 *
 * Read-only view of the official A1-A9 schedule imported from the 2026/2027
 * school package. Rows keep the exact source cell text (including the
 * explicit "no assessment" marker) so nothing is paraphrased on screen.
 */
final class AssessmentController extends Controller
{
    public function index(Request $request): void
    {
        $yearId = AcademicYearContext::activeYearId();

        $byClass = [];
        if ($yearId !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT a.class_id, a.day_of_week, a.sequence, a.subjects_raw, a.no_assessment,
                        a.source_document, c.name AS class_name, c.pathway_ar,
                        g.name_ar AS grade_name_ar, g.sort_order
                 FROM assessment_schedules a
                 JOIN classes c ON c.id = a.class_id
                 JOIN grades g ON g.id = c.grade_id
                 WHERE a.academic_year_id = :year
                 ORDER BY g.sort_order ASC, c.name ASC, a.sequence ASC'
            );
            $stmt->execute(['year' => $yearId]);
            foreach ($stmt->fetchAll() as $row) {
                $byClass[(string) $row['class_name']][] = $row;
            }
        }

        $this->view('assessments/index', [
            'byClass' => $byClass,
            'hasActiveYear' => $yearId !== null,
        ]);
    }
}

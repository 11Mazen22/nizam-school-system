<?php
/**
 * Report card / transcript PDF.
 *
 * Every percentage and grade on this page comes from
 * ExamService::getStudentReport(), which delegates to
 * AcademicCalculationService. This view performs no arithmetic of its own --
 * that is what keeps the printed grade identical to the configured grading
 * scale and to the ranking screen.
 *
 * @var array $report
 * @var array $student
 */
$reportCardSchool = (new \App\Repositories\SchoolRepository())->full();
$reportCardSchoolName = $reportCardSchool !== null
    ? (currentLocale() === 'ar' ? $reportCardSchool['name_ar'] : $reportCardSchool['name'])
    : __('app.name');

// Same fallback + fixed-size derivative ExportService::logoDataUri() uses --
// mPDF's header/footer HTML normalization is regex-based, and a full-size
// embedded image's base64 length alone can blow PHP's pcre.backtrack_limit
// (found live building this exact style of PDF for the reports module).
$reportCardLogoPath = dirname(__DIR__, 2) . '/public/assets/img/hadaba-logo-pdf.png';
$reportCardLogoDataUri = is_file($reportCardLogoPath)
    ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($reportCardLogoPath))
    : null;
ini_set('pcre.backtrack_limit', '10000000');

$grid = $report['grid'] ?? [];
$subjects = $report['subjects'] ?? [];
$overall = $report['overall'] ?? ['percentage' => null, 'band_label' => null, 'gpa_point' => null, 'is_passing' => null];
$scale = $report['scale'] ?? null;
?>
<!DOCTYPE html>
<html dir="<?= currentLocale() === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: 'dejavusans', sans-serif; font-size: 11pt; color: #1a2420; }
  h1   { font-size: 18pt; color: #1f6f5c; text-align: center; margin: 0; }
  h2   { font-size: 13pt; color: #333; text-align: center; margin: 4px 0 16px; }
  .header-box { border: 2px solid #1f6f5c; border-radius: 8px; padding: 12px; text-align: center; margin-bottom: 16px; }
  .header-logo { display: block; margin: 0 auto 8px; height: 60px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th    { background: #1f6f5c; color: #fff; padding: 6px 8px; text-align: center; }
  td    { border: 1px solid #cad6cd; padding: 5px 8px; text-align: center; }
  tr:nth-child(even) td { background: #f1f8f5; }
  .score-cell { font-weight: bold; }
  .high { color: #2f8a54; }
  .mid  { color: #b8791f; }
  .low  { color: #c1453f; }
  .summary { border: 1px solid #cad6cd; background: #f1f8f5; padding: 8px; text-align: center; margin-bottom: 16px; }
  .summary span { display: inline-block; margin: 0 10px; }
  .summary .value { font-weight: bold; font-size: 13pt; }
  .scale-note { text-align: center; color: #777; font-size: 9pt; margin-bottom: 12px; }
  .footer { text-align: center; color: #888; font-size: 9pt; margin-top: 24px; }
</style>
</head>
<body>
<div class="header-box">
  <?php if ($reportCardLogoDataUri !== null): ?>
    <img class="header-logo" src="<?= e($reportCardLogoDataUri) ?>" alt="">
  <?php endif; ?>
  <h1><?= e($reportCardSchoolName) ?></h1>
  <h2><?= e(__('exams.report_card')) ?></h2>
  <p><strong><?= e(__('students.full_name')) ?>:</strong> <?= e($student['full_name']) ?>
     &nbsp;&nbsp; <strong><?= e(__('students.code')) ?>:</strong> <?= e($student['student_code']) ?></p>
</div>

<?php if (empty($grid)): ?>
  <p style="text-align:center;color:#888;"><?= e(__('exams.no_scores_yet')) ?></p>
<?php else: ?>

  <div class="summary">
    <span><?= e(__('exams.overall')) ?>:
      <span class="value"><?= $overall['percentage'] !== null ? number_format((float) $overall['percentage'], 2) . '%' : '—' ?></span>
    </span>
    <span><?= e(__('exams.grade_band')) ?>:
      <span class="value"><?= $overall['band_label'] !== null ? e($overall['band_label']) : '—' ?></span>
    </span>
    <?php if (($overall['gpa_point'] ?? null) !== null): ?>
      <span><?= e(__('structure.grading.band_gpa')) ?>:
        <span class="value"><?= number_format((float) $overall['gpa_point'], 2) ?></span>
      </span>
    <?php endif; ?>
  </div>

  <?php if ($scale !== null): ?>
    <div class="scale-note"><?= e(__('exams.grade_scale')) ?>: <?= e($scale['name_en']) ?></div>
  <?php else: ?>
    <div class="scale-note"><?= e(__('exams.no_scale')) ?></div>
  <?php endif; ?>

  <?php if ($subjects !== []): ?>
    <table>
      <thead>
        <tr>
          <th colspan="4"><?= e(__('exams.subject_summary')) ?></th>
        </tr>
        <tr>
          <th><?= e(__('subjects.subject')) ?></th>
          <th><?= e(__('exams.weighted_avg')) ?></th>
          <th><?= e(__('exams.grade_band')) ?></th>
          <th><?= e(__('structure.grading.band_gpa')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subjects as $subject): ?>
          <?php
            $subjectName = currentLocale() === 'ar' ? $subject['subject_ar'] : $subject['subject_en'];
            $passing = $subject['is_passing'];
            $cls = $passing === false ? 'low' : ($passing === true ? 'high' : '');
          ?>
          <tr>
            <td><?= e($subjectName) ?></td>
            <td class="score-cell <?= $cls ?>"><?= $subject['percentage'] !== null ? number_format((float) $subject['percentage'], 2) . '%' : '—' ?></td>
            <td><?= $subject['band_label'] !== null ? e($subject['band_label']) : '—' ?></td>
            <td><?= $subject['gpa_point'] !== null ? number_format((float) $subject['gpa_point'], 2) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php foreach ($grid as $subjectId => $examScores): ?>
    <?php
      $firstRow = array_values($examScores)[0];
      $subjectName = currentLocale() === 'ar' ? $firstRow['subject_ar'] : $firstRow['subject_en'];
    ?>
    <table>
      <thead>
        <tr><th colspan="5"><?= e($subjectName) ?></th></tr>
        <tr>
          <th><?= e(__('exams.exam')) ?></th>
          <th><?= e(__('exams.score')) ?></th>
          <th><?= e(__('exams.max_score')) ?></th>
          <th><?= e(__('exams.weighted_avg')) ?></th>
          <th><?= e(__('exams.grade_band')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($examScores as $examId => $row): ?>
          <?php
            $score = $row['score'] !== null ? (float) $row['score'] : null;
            $pct = $row['percentage'] ?? null;
            $cls = $pct === null ? '' : ($pct >= 75 ? 'high' : ($pct >= 50 ? 'mid' : 'low'));
          ?>
          <tr>
            <td><?= e(currentLocale() === 'ar' ? $row['name_ar'] : $row['name_en']) ?></td>
            <td class="score-cell"><?= $score !== null ? number_format($score, 1) : '—' ?></td>
            <td><?= number_format((float) $row['max_score'], 0) ?></td>
            <td class="<?= $cls ?>"><?= $pct !== null ? number_format((float) $pct, 2) . '%' : '—' ?></td>
            <td><?= !empty($row['band_label']) ? e($row['band_label']) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>

<?php endif; ?>

<div class="footer"><?= e(__('reports.generated_on')) ?>: <?= date('Y-m-d') ?></div>
</body>
</html>

<?php
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
?>
<!DOCTYPE html>
<html dir="<?= currentLocale() === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: 'dejavusans', sans-serif; font-size: 12pt; color: #1a2420; }
  h1   { font-size: 18pt; color: #1f6f5c; text-align: center; margin: 0; }
  h2   { font-size: 13pt; color: #333; text-align: center; margin: 4px 0 20px; }
  .header-box { border: 2px solid #1f6f5c; border-radius: 8px; padding: 12px; text-align: center; margin-bottom: 20px; }
  .header-logo { display: block; margin: 0 auto 8px; height: 60px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  th    { background: #1f6f5c; color: #fff; padding: 7px 10px; text-align: center; }
  td    { border: 1px solid #cad6cd; padding: 6px 10px; text-align: center; }
  tr:nth-child(even) td { background: #f1f8f5; }
  .score-cell  { font-weight: bold; }
  .high  { color: #2f8a54; }
  .mid   { color: #b8791f; }
  .low   { color: #c1453f; }
  .footer { text-align: center; color: #888; font-size: 9pt; margin-top: 30px; }
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
  <?php foreach ($grid as $subjectId => $examScores): ?>
    <?php
      $firstRow = array_values($examScores)[0];
      $subjectName = currentLocale() === 'ar' ? $firstRow['subject_ar'] : $firstRow['subject_en'];
    ?>
    <table>
      <thead>
        <tr><th colspan="3"><?= e($subjectName) ?></th></tr>
        <tr>
          <th><?= e(__('exams.exam')) ?></th>
          <th><?= e(__('exams.score')) ?></th>
          <th><?= e(__('exams.max_score')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($examScores as $examId => $row): ?>
          <?php
            $score    = $row['score'] !== null ? (float)$row['score'] : null;
            $max      = (float)$row['max_score'];
            $pct      = $score !== null && $max > 0 ? $score / $max * 100 : null;
            $cls      = $pct === null ? '' : ($pct >= 75 ? 'high' : ($pct >= 50 ? 'mid' : 'low'));
          ?>
          <tr>
            <td><?= e(currentLocale() === 'ar' ? $row['name_ar'] : $row['name_en']) ?></td>
            <td class="score-cell <?= $cls ?>"><?= $score !== null ? number_format($score, 1) : '—' ?></td>
            <td><?= number_format($max, 0) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>
<?php endif; ?>

<div class="footer"><?= e(__('reports.generated_on')) ?>: <?= date('Y-m-d') ?></div>
</body>
</html>

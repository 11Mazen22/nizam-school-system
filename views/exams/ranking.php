<?php
/**
 * @var array  $classes
 * @var int    $classId
 * @var array  $ranking  rows with rank, student_id, full_name, student_code, weighted_avg
 */
$pageTitle = __('exams.ranking');
$activeNav = 'exams';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper"><?= icon('trending-up', 'n-grades-icon') ?></div>
        <?= e(__('exams.ranking')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('exams.ranking_subtitle')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <a href="/exams" class="btn btn-outline-secondary"><?= icon('star') ?> <?= e(__('exams.title')) ?></a>
    </div>
  </div>
</div>

<form method="get" action="/exams/ranking" class="n-filter-panel">
  <div class="n-filter-row">
    <div class="flex-grow-1" style="min-width:200px;">
      <label class="form-label fw-semibold"><?= e(__('attendance.class')) ?></label>
      <select name="class_id" class="form-select">
        <option value="0"><?= e(__('attendance.choose_class')) ?></option>
        <?php foreach ($classes as $cls): ?>
          <option value="<?= (int)$cls['id'] ?>" <?= (int)$cls['id'] === $classId ? 'selected' : '' ?>>
            <?= e(currentLocale() === 'ar' ? $cls['grade_name_ar'] : $cls['grade_name_en']) ?> — <?= e($cls['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary"><?= icon('filter') ?> <?= e(__('reports.apply_filters')) ?></button>
  </div>
</form>

<?php if (!empty($ranking)): ?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:60px;"><?= e(__('exams.rank')) ?></th>
          <th><?= e(__('students.full_name')) ?></th>
          <th><?= e(__('students.code')) ?></th>
          <th><?= e(__('exams.weighted_avg')) ?></th>
          <th><?= e(__('exams.grade_band')) ?></th>
          <th><?= e(__('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ranking as $row): ?>
          <?php
            $avg = $row['weighted_avg'] !== null ? (float) $row['weighted_avg'] : 0.0;
            $badgeClass = $row['is_passing'] === false ? 'bg-danger' : ($avg >= 85 ? 'bg-success' : ($avg >= 70 ? 'bg-warning text-dark' : 'bg-primary'));
          ?>
          <tr>
            <td><span class="n-rank-chip <?= $row['rank'] <= 3 ? 'top' : '' ?>"><?= (int) $row['rank'] ?></span></td>
            <td class="fw-semibold"><?= e($row['full_name']) ?></td>
            <td class="mono small"><?= e($row['student_code']) ?></td>
            <td>
              <?php if ($row['weighted_avg'] !== null): ?>
                <span class="badge <?= $badgeClass ?> fs-6"><?= number_format($avg, 1) ?>%</span>
              <?php else: ?>
                <span class="text-body-secondary">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($row['band_label'])): ?>
                <span class="badge fs-6 <?= $row['is_passing'] === false ? 'text-bg-danger' : 'text-bg-success' ?>"><?= e($row['band_label']) ?></span>
              <?php else: ?>
                <span class="text-body-secondary">—</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="/exams/report-card/<?= (int)$row['student_id'] ?>" class="btn btn-sm btn-outline-secondary">
                <?= icon('file-text') ?> <?= e(__('exams.report_card')) ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif ($classId > 0): ?>
  <div class="n-empty-powerful">
    <div class="n-empty-powerful-content">
      <div class="n-empty-powerful-icon">
        <?= icon('trending-up', 'n-icon-massive') ?>
      </div>
      <h2 class="n-empty-powerful-title"><?= e(__('exams.no_scores_yet')) ?></h2>
    </div>
  </div>
<?php else: ?>
  <div class="n-empty-powerful">
    <div class="n-empty-powerful-content">
      <div class="n-empty-powerful-icon">
        <?= icon('trending-up', 'n-icon-massive') ?>
      </div>
      <h2 class="n-empty-powerful-title"><?= e(__('attendance.choose_class_prompt')) ?></h2>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

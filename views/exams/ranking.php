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
      <a href="/exams" class="btn btn-outline-light"><?= icon('star') ?> <?= e(__('exams.title')) ?></a>
    </div>
  </div>
</div>

<form method="get" action="/exams/ranking" class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex gap-3 align-items-end flex-wrap">
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
<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:60px;"><?= e(__('exams.rank')) ?></th>
          <th><?= e(__('students.full_name')) ?></th>
          <th><?= e(__('students.code')) ?></th>
          <th><?= e(__('exams.weighted_avg')) ?></th>
          <th><?= e(__('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ranking as $row): ?>
          <?php
            $avg = (float) $row['weighted_avg'];
            $medal = $row['rank'] === 1 ? '🥇' : ($row['rank'] === 2 ? '🥈' : ($row['rank'] === 3 ? '🥉' : ''));
            $badgeClass = $avg >= 85 ? 'bg-success' : ($avg >= 70 ? 'bg-warning text-dark' : 'bg-danger');
          ?>
          <tr class="<?= $row['rank'] <= 3 ? 'table-warning' : '' ?>">
            <td class="fw-bold fs-5"><?= $medal ?: '#' . $row['rank'] ?></td>
            <td class="fw-semibold"><?= e($row['full_name']) ?></td>
            <td class="mono small"><?= e($row['student_code']) ?></td>
            <td>
              <span class="badge <?= $badgeClass ?> fs-6"><?= number_format($avg, 1) ?>%</span>
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
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('trending-up', 'n-icon-xl') ?></div>
    <h3 class="n-empty-title"><?= e(__('exams.no_scores_yet')) ?></h3>
  </div>
<?php else: ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('trending-up', 'n-icon-xl') ?></div>
    <h3 class="n-empty-title"><?= e(__('attendance.choose_class_prompt')) ?></h3>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

<?php
/**
 * Class ranking -- the whole roster.
 *
 * Every actively enrolled student appears. Students with marked assessments
 * are ranked by weighted average; students with no mark are listed separately
 * with "لم تُرصد درجات بعد", never as a zero and never silently dropped.
 *
 * @var array  $classes
 * @var int    $classId
 * @var array  $ranking  rows with rank, student_id, full_name, student_code, weighted_avg, scored
 * @var int    $rankingTotal
 * @var string $q
 */
$pageTitle = __('exams.ranking');
$activeNav = 'exams';
$scoredRows = array_values(array_filter($ranking, static fn (array $row): bool => !empty($row['scored'])));
$unscoredRows = array_values(array_filter($ranking, static fn (array $row): bool => empty($row['scored'])));
$scoredAverage = $scoredRows === []
    ? null
    : array_sum(array_map(static fn (array $row): float => (float) $row['weighted_avg'], $scoredRows)) / count($scoredRows);

ob_start(); ?>
<p class="n-hero-sub"><?= e(__('exams.ranking_subtitle')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/exams" class="btn btn-outline-secondary"><?= icon('star') ?> <?= e(__('exams.title')) ?></a>
<?php if ($classId > 0): ?>
  <?php
  $docKey = 'ranking';
  $docParams = ['class_id' => (string) ((int) $classId), 'q' => (string) ($q ?? '')];
  $docCompact = true;
  require dirname(__DIR__) . '/partials/export-menu.php';
  ?>
<?php endif; ?>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

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

<?php if ($classId > 0 && $rankingTotal > 0): ?>
<form method="get" action="/exams/ranking" class="n-filter-panel n-list-search no-print" data-live-filter data-live-target="#ranking-results">
  <div class="n-filter-row">
    <div class="n-filter-field n-search-field flex-grow-1">
      <?= icon('search') ?>
      <label class="visually-hidden" for="ranking-search"><?= e(__('common.search')) ?></label>
      <input id="ranking-search" type="search" class="form-control" name="q"
             value="<?= e($q ?? '') ?>"
             placeholder="<?= e(__('common.search')) ?>"
             autocomplete="off">
    </div>
    <input type="hidden" name="class_id" value="<?= (int) $classId ?>">
    <button type="submit" class="btn btn-primary"><?= icon('search') ?><?= e(__('common.search')) ?></button>
    <?php if (($q ?? '') !== ''): ?><a href="/exams/ranking?class_id=<?= (int) $classId ?>" class="btn btn-outline-secondary"><?= icon('close') ?><?= e(__('common.clear_filters')) ?></a><?php endif; ?>
  </div>
</form>

<div id="ranking-results" data-live-results>
  <?php if ($ranking === []): ?>
    <div class="n-empty py-4">
      <div class="n-empty-icon"><?= icon('search') ?></div>
      <p class="mb-0"><?= e(__('common.no_results')) ?></p>
    </div>
  <?php else: ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <span class="badge text-bg-success"><?= e(__('exams.scored_count')) ?>: <?= count($scoredRows) ?></span>
      <span class="badge text-bg-secondary"><?= e(__('exams.unscored_count')) ?>: <?= count($unscoredRows) ?></span>
      <?php if ($scoredAverage !== null): ?>
        <span class="badge text-bg-light border"><?= e(__('exams.weighted_avg')) ?>: <?= number_format($scoredAverage, 1) ?>%</span>
      <?php endif; ?>
    </div>

    <?php if ($scoredRows !== []): ?>
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center gap-2">
          <?= icon('trending-up') ?>
          <strong><?= e(__('exams.scored_section')) ?></strong>
          <span class="badge text-bg-success ms-auto"><?= count($scoredRows) ?></span>
        </div>
        <div class="table-responsive">
          <table id="ranking-table" class="table table-hover align-middle mb-0">
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
              <?php foreach ($scoredRows as $row): ?>
                <?php
                  $avg = (float) $row['weighted_avg'];
                  $badgeClass = $row['is_passing'] === false ? 'bg-danger' : ($avg >= 85 ? 'bg-success' : ($avg >= 70 ? 'bg-warning text-dark' : 'bg-primary'));
                ?>
                <tr>
                  <td><span class="n-rank-chip <?= (int) $row['rank'] <= 3 ? 'top' : '' ?>"><?= (int) $row['rank'] ?></span></td>
                  <td class="fw-semibold"><?= e($row['full_name']) ?></td>
                  <td class="mono small"><?= e($row['student_code']) ?></td>
                  <td><span class="badge <?= $badgeClass ?> fs-6"><?= number_format($avg, 1) ?>%</span></td>
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
    <?php elseif ($unscoredRows !== []): ?>
      <div class="alert alert-info d-flex gap-2">
        <?= icon('info', 'n-icon-lg flex-shrink-0') ?>
        <div><?= e(__('exams.no_scores_yet')) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($unscoredRows !== []): ?>
      <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
          <?= icon('clock') ?>
          <strong><?= e(__('exams.unscored_section')) ?></strong>
          <span class="badge text-bg-secondary ms-auto"><?= count($unscoredRows) ?></span>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= e(__('students.full_name')) ?></th>
                <th><?= e(__('students.code')) ?></th>
                <th><?= e(__('exams.weighted_avg')) ?></th>
                <th><?= e(__('common.actions')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($unscoredRows as $row): ?>
                <tr>
                  <td class="fw-semibold"><?= e($row['full_name']) ?></td>
                  <td class="mono small"><?= e($row['student_code']) ?></td>
                  <td><span class="badge text-bg-secondary"><?= e(__('exams.unscored_reason')) ?></span></td>
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
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php elseif ($classId > 0): ?>
  <div class="n-empty-powerful">
    <div class="n-empty-powerful-content">
      <div class="n-empty-powerful-icon">
        <?= icon('users', 'n-icon-massive') ?>
      </div>
      <h2 class="n-empty-powerful-title"><?= e(__('exams.no_students_in_class')) ?></h2>
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

<?php
/** @var array $catalog */
$pageTitle = __('reports.title');
$activeNav = 'reports';
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('reports.description')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<div class="n-stat-mini"><span class="n-stat-mini-value"><?= count($catalog) ?></span><span class="n-stat-mini-label"><?= e(__('reports.available_reports')) ?></span></div>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';

$reportIcons = ['religion' => 'pie-chart', 'class-list' => 'clipboard-list', 'density' => 'grid', 'teachers-by-subject' => 'book', 'workload' => 'bar-chart', 'shortage' => 'alert-triangle'];
$groups = [
    'reports.category_students' => ['religion', 'class-list', 'density'],
    'reports.category_staff' => ['teachers-by-subject', 'workload', 'shortage'],
];
?>



<?php foreach ($groups as $groupLabel => $keys): ?>
  <section class="n-report-group mb-4" aria-labelledby="<?= e(str_replace('.', '-', $groupLabel)) ?>">
    <div class="n-section-head border-0 px-0"><div><h2 id="<?= e(str_replace('.', '-', $groupLabel)) ?>"><?= e(__($groupLabel)) ?></h2></div></div>
    <div class="row g-3 n-reveal-group">
      <?php foreach ($keys as $key): ?><?php if (!isset($catalog[$key])) continue; $meta = $catalog[$key]; ?>
        <div class="col-md-6 col-xl-4"><a href="/reports/<?= e($key) ?>" class="n-report-card"><span class="n-report-icon"><?= icon($reportIcons[$key] ?? 'file-text') ?></span><span class="n-report-copy"><strong><?= e(__('reports.' . $key)) ?></strong><small><?= e(__('reports.' . $key . '_desc')) ?></small><span class="n-format-list"><?php foreach ($meta['formats'] as $format): ?><b><?= e(strtoupper($format)) ?></b><?php endforeach; ?></span></span><?= icon('arrow-forward', 'n-report-arrow n-flip-rtl') ?></a></div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<section class="n-report-group mb-4" aria-labelledby="reports-category-operational">
  <div class="n-section-head border-0 px-0">
    <div><h2 id="reports-category-operational"><?= e(__('reports.category_operational')) ?></h2></div>
  </div>
  <div class="row g-3 n-reveal-group">
    <?php if (hasPermission('attendance.view')): ?>
      <div class="col-md-6 col-xl-4">
        <a href="/attendance/report" class="n-report-card">
          <span class="n-report-icon"><?= icon('check-circle') ?></span>
          <span class="n-report-copy">
            <strong><?= e(__('attendance.report_title')) ?></strong>
            <small><?= e(__('attendance.report_subtitle')) ?></small>
            <span class="n-format-list"><b>LIVE</b><b>PRINT</b></span>
          </span>
          <?= icon('arrow-forward', 'n-report-arrow n-flip-rtl') ?>
        </a>
      </div>
    <?php endif; ?>
    <?php if (hasPermission('exams.view')): ?>
      <div class="col-md-6 col-xl-4">
        <a href="/exams/ranking" class="n-report-card">
          <span class="n-report-icon"><?= icon('star') ?></span>
          <span class="n-report-copy">
            <strong><?= e(__('exams.ranking')) ?></strong>
            <small><?= e(__('exams.ranking_subtitle')) ?></small>
            <span class="n-format-list"><b>RANK</b><b>PRINT</b></span>
          </span>
          <?= icon('arrow-forward', 'n-report-arrow n-flip-rtl') ?>
        </a>
      </div>
    <?php endif; ?>
    <?php if (hasPermission('assignments.view')): ?>
      <div class="col-md-6 col-xl-4">
        <a href="/assignments/workload" class="n-report-card">
          <span class="n-report-icon"><?= icon('chart') ?></span>
          <span class="n-report-copy">
            <strong><?= e(__('assignments.workload_summary')) ?></strong>
            <small><?= e(__('assignments.workload_subtitle')) ?></small>
            <span class="n-format-list"><b>CHART</b><b>ANALYTICS</b></span>
          </span>
          <?= icon('arrow-forward', 'n-report-arrow n-flip-rtl') ?>
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

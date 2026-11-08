<?php
/** @var array $catalog */
$pageTitle = __('reports.title');
$activeNav = 'reports';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';

$reportIcons = ['religion' => 'pie-chart', 'class-list' => 'clipboard-list', 'density' => 'grid', 'teachers-by-subject' => 'book', 'workload' => 'bar-chart', 'shortage' => 'alert-triangle'];
$groups = [
    'reports.category_students' => ['religion', 'class-list', 'density'],
    'reports.category_staff' => ['teachers-by-subject', 'workload', 'shortage'],
];
?>

<div class="n-grades-header"><div class="n-grades-header-content"><div class="n-grades-header-text"><h1 class="n-grades-title"><span class="n-grades-icon-wrapper"><?= icon('chart', 'n-grades-icon') ?></span><?= e(__('reports.title')) ?></h1><p class="n-grades-subtitle"><?= e(__('reports.description')) ?></p></div><div class="n-grades-header-actions"><div class="n-stat-mini"><span class="n-stat-mini-value"><?= count($catalog) ?></span><span class="n-stat-mini-label"><?= e(__('reports.available_reports')) ?></span></div></div></div></div>

<?php foreach ($groups as $groupLabel => $keys): ?>
  <section class="n-report-group" aria-labelledby="<?= e(str_replace('.', '-', $groupLabel)) ?>">
    <div class="n-section-head border-0 px-0"><div><h2 id="<?= e(str_replace('.', '-', $groupLabel)) ?>"><?= e(__($groupLabel)) ?></h2></div></div>
    <div class="row g-3 n-reveal-group">
      <?php foreach ($keys as $key): ?><?php if (!isset($catalog[$key])) continue; $meta = $catalog[$key]; ?>
        <div class="col-md-6 col-xl-4"><a href="/reports/<?= e($key) ?>" class="n-report-card"><span class="n-report-icon"><?= icon($reportIcons[$key] ?? 'file-text') ?></span><span class="n-report-copy"><strong><?= e(__('reports.' . $key)) ?></strong><small><?= e(__('reports.' . $key . '_desc')) ?></small><span class="n-format-list"><?php foreach ($meta['formats'] as $format): ?><b><?= e(strtoupper($format)) ?></b><?php endforeach; ?></span></span><?= icon('arrow-forward', 'n-report-arrow n-flip-rtl') ?></a></div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

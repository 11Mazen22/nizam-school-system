<?php
/** @var array $catalog */
$pageTitle = __('reports.title');
$activeNav = 'reports';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
?>

<?php
$reportIcons = [
    'religion' => 'chart',
    'class-list' => 'clipboard-list',
    'density' => 'grid',
    'teachers-by-subject' => 'book',
    'workload' => 'clock',
    'shortage' => 'alert-triangle',
];
?>

<!-- POWERFUL PAGE HEADER -->
<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper">
          <?= icon('chart', 'n-grades-icon') ?>
        </div>
        <?= e(__('reports.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('reports.description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= count($catalog) ?></span>
          <span class="n-stat-mini-label"><?= e(__('reports.available_reports')) ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 n-reveal-group">
  <?php foreach ($catalog as $key => $meta): ?>
    <div class="col-md-6 col-lg-4">
      <a href="/reports/<?= e($key) ?>" class="text-decoration-none">
        <div class="card h-100 n-card-hover">
          <div class="card-body">
            <div class="mb-3 d-inline-flex align-items-center justify-content-center"
                 style="width: 48px; height: 48px; border-radius: var(--n-radius); background: var(--n-primary-soft); color: var(--n-primary-strong);">
              <?= icon($reportIcons[$key] ?? 'file-text', 'n-icon-lg') ?>
            </div>
            <h2 class="h5 mb-2"><?= e(__('reports.' . $key)) ?></h2>
            <div class="d-flex gap-1 flex-wrap">
              <?php foreach ($meta['formats'] as $format): ?>
                <span class="badge text-bg-light border"><?= e(ucfirst($format)) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

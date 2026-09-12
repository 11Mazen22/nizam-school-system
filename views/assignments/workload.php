<?php
/** @var ?array $summary @var bool $hasActiveYear */
$pageTitle = __('assignments.workload_summary');
$activeNav = 'assignments';
$pageScripts = $hasActiveYear ? ['/assets/vendor/chartjs/chart.umd.min.js', '/assets/js/workload-chart.js'] : [];
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('chart', 'n-icon-lg text-body-secondary') ?>
      <?= e($pageTitle) ?>
    </h1>
  </div>
  <div class="n-page-head-actions">
    <a href="/assignments" class="btn btn-outline-secondary d-flex align-items-center gap-2">
      <?= icon('chevron-right', 'n-flip-rtl') ?>
      <span><?= e(__('assignments.title')) ?></span>
    </a>
  </div>
</div>

<?php if (!$hasActiveYear): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2">
    <?= icon('alert-triangle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e(__('assignments.no_active_year')) ?></span>
  </div>
<?php elseif (empty($summary['perTeacher'])): ?>
  <div class="card">
    <div class="card-body">
      <div class="n-empty py-4">
        <div class="n-empty-icon"><?= icon('chart') ?></div>
        <p class="mb-0"><?= e(__('common.no_results')) ?></p>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card stat-card h-100"><div class="card-body">
        <div class="stat-value"><?= (int) $summary['highest'] ?></div>
        <div class="stat-label"><?= e(__('assignments.highest')) ?></div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card stat-card h-100"><div class="card-body">
        <div class="stat-value"><?= (int) $summary['lowest'] ?></div>
        <div class="stat-label"><?= e(__('assignments.lowest')) ?></div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card stat-card h-100"><div class="card-body">
        <div class="stat-value"><?= e((string) $summary['average']) ?></div>
        <div class="stat-label"><?= e(__('assignments.average')) ?></div>
      </div></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <canvas id="workload-chart" height="120"
        data-chart='<?= e(json_encode([
            'labels' => array_map(static fn (array $r) => $r['full_name'], $summary['perTeacher']),
            'totals' => array_map(static fn (array $r) => (int) $r['total'], $summary['perTeacher']),
        ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex align-items-center gap-2">
      <?= icon('graduation-cap') ?>
      <span><?= e(__('assignments.teachers_count')) ?></span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 n-table-stack">
          <thead>
            <tr>
              <th><?= e(__('teachers.full_name')) ?></th>
              <th><?= e(__('assignments.total_periods')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($summary['perTeacher'] as $row): ?>
              <tr>
                <td data-label="<?= e(__('teachers.full_name')) ?>">
                  <a href="/teachers/<?= (int) $row['teacher_id'] ?>"><?= e($row['full_name']) ?></a>
                </td>
                <td data-label="<?= e(__('assignments.total_periods')) ?>" class="font-variant-numeric-tabular"><?= (int) $row['total'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

<?php
/** @var ?array $summary @var bool $hasActiveYear */
$pageTitle = __('assignments.workload_summary');
$activeNav = 'assignments';
$pageScripts = $hasActiveYear ? ['/assets/vendor/chartjs/chart.umd.min.js', '/assets/js/workload-chart.js'] : [];
require dirname(__DIR__) . '/layout/start.php';
?>

<?php if (!$hasActiveYear): ?>
  <div class="alert alert-warning"><?= e(__('assignments.no_active_year')) ?></div>
<?php elseif (empty($summary['perTeacher'])): ?>
  <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
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

  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
      <thead>
        <tr>
          <th><?= e(__('teachers.full_name')) ?></th>
          <th><?= e(__('assignments.total_periods')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summary['perTeacher'] as $row): ?>
          <tr>
            <td><a href="/teachers/<?= (int) $row['teacher_id'] ?>"><?= e($row['full_name']) ?></a></td>
            <td class="font-variant-numeric-tabular"><?= (int) $row['total'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

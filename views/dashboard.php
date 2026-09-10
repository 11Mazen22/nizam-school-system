<?php
/** @var array $stats */
$pageTitle = __('dashboard.title');
$activeNav = 'dashboard';
$pageScripts = $stats['hasActiveYear'] ? ['/assets/vendor/chartjs/chart.umd.min.js', '/assets/js/dashboard.js'] : [];
require __DIR__ . '/layout/start.php';
?>

<?php if (!$stats['hasActiveYear']): ?>
  <div class="alert alert-warning"><?= e(__('dashboard.no_academic_year')) ?></div>
<?php else: ?>

  <div class="row g-3 mb-4">
    <?php
    $cards = [
        ['dashboard.total_students', $stats['students']],
        ['dashboard.total_teachers', $stats['teachers']],
        ['dashboard.total_classes', $stats['classes']],
        ['dashboard.total_subjects', $stats['subjects']],
        ['dashboard.total_grades', $stats['grades']],
    ];
    ?>
    <?php foreach ($cards as [$labelKey, $value]): ?>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card h-100">
          <div class="card-body">
            <div class="stat-value"><?= (int) $value ?></div>
            <div class="stat-label"><?= e(__($labelKey)) ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><?= e(__('dashboard.religion_breakdown')) ?></div>
        <div class="card-body">
          <?php if ($stats['students'] === 0): ?>
            <p class="text-muted mb-0"><?= e(__('dashboard.no_students_yet')) ?></p>
          <?php else: ?>
            <canvas id="religion-chart" height="160"
              data-chart='<?= e(json_encode([
                  'labels' => [__('dashboard.muslim'), __('dashboard.christian'), __('dashboard.other')],
                  'counts' => [$stats['religion']['muslim'], $stats['religion']['christian'], $stats['religion']['other']],
              ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><?= e(__('dashboard.recent_activity')) ?></div>
        <ul class="list-group list-group-flush">
          <?php if (empty($stats['activity'])): ?>
            <li class="list-group-item text-muted"><?= e(__('dashboard.no_activity')) ?></li>
          <?php else: ?>
            <?php foreach ($stats['activity'] as $entry): ?>
              <li class="list-group-item d-flex justify-content-between align-items-start">
                <div>
                  <div><?= e($entry['description'] ?? $entry['action']) ?></div>
                  <div class="text-muted small"><?= e($entry['full_name'] ?? '') ?></div>
                </div>
                <span class="text-muted small text-nowrap ms-2"><?= e($entry['created_at']) ?></span>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
        <?php if (hasPermission('activity_log.view')): ?>
          <div class="card-footer text-end">
            <a href="/activity-log" class="small"><?= e(__('dashboard.view_all_activity')) ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/layout/end.php'; ?>

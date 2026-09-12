<?php
/** @var array $stats */
$pageTitle = __('dashboard.title');
$activeNav = 'dashboard';
$pageScripts = $stats['hasActiveYear'] ? ['/assets/vendor/chartjs/chart.umd.min.js', '/assets/js/dashboard.js'] : [];
require __DIR__ . '/layout/start.php';
?>

<?php if (!$stats['hasActiveYear']): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2">
    <?= icon('alert-triangle', 'n-icon-lg flex-shrink-0') ?>
    <?= e(__('dashboard.no_academic_year')) ?>
  </div>
<?php else: ?>

  <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3 mb-4 n-reveal-group">
    <?php
    $cards = [
        ['dashboard.total_students', $stats['students'], 'users'],
        ['dashboard.total_teachers', $stats['teachers'], 'graduation-cap'],
        ['dashboard.total_classes', $stats['classes'], 'chalkboard'],
        ['dashboard.total_subjects', $stats['subjects'], 'book'],
        ['dashboard.total_grades', $stats['grades'], 'layers'],
    ];
    ?>
    <?php foreach ($cards as [$labelKey, $value, $cardIcon]): ?>
      <div class="col">
        <div class="card stat-card h-100">
          <div class="card-body">
            <div>
              <div class="stat-value" data-count-up><?= (int) $value ?></div>
              <div class="stat-label"><?= e(__($labelKey)) ?></div>
            </div>
            <div class="stat-icon"><?= icon($cardIcon) ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3 n-reveal-group">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2"><?= icon('chart') ?><?= e(__('dashboard.religion_breakdown')) ?></div>
        <div class="card-body">
          <?php if ($stats['students'] === 0): ?>
            <div class="n-empty py-4">
              <div class="n-empty-icon"><?= icon('users') ?></div>
              <p class="mb-0"><?= e(__('dashboard.no_students_yet')) ?></p>
            </div>
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
        <div class="card-header d-flex align-items-center gap-2"><?= icon('activity') ?><?= e(__('dashboard.recent_activity')) ?></div>
        <ul class="list-group list-group-flush">
          <?php if (empty($stats['activity'])): ?>
            <li class="list-group-item">
              <div class="n-empty py-3">
                <div class="n-empty-icon"><?= icon('inbox') ?></div>
                <p class="mb-0"><?= e(__('dashboard.no_activity')) ?></p>
              </div>
            </li>
          <?php else: ?>
            <?php foreach ($stats['activity'] as $entry): ?>
              <li class="list-group-item d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold"><?= e(activityLabel($entry['action'])) ?></div>
                  <?php if (!empty($entry['description'])): ?>
                    <div class="text-muted small"><?= e($entry['description']) ?></div>
                  <?php endif; ?>
                  <div class="text-muted small"><?= e($entry['full_name'] ?? '') ?></div>
                </div>
                <span class="text-muted small text-nowrap ms-2"><?= e($entry['created_at']) ?></span>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
        <?php if (hasPermission('activity_log.view')): ?>
          <div class="card-footer text-end">
            <a href="/activity-log" class="small d-inline-flex align-items-center gap-1">
              <?= e(__('dashboard.view_all_activity')) ?><?= icon('chevron-left', 'n-flip-rtl') ?>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/layout/end.php'; ?>

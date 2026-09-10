<?php
/** @var array $catalog */
$pageTitle = __('reports.title');
$activeNav = 'reports';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="row g-3">
  <?php foreach ($catalog as $key => $meta): ?>
    <div class="col-md-6 col-lg-4">
      <a href="/reports/<?= e($key) ?>" class="text-decoration-none">
        <div class="card h-100">
          <div class="card-body">
            <h2 class="h6 mb-2"><?= e(__('reports.' . $key)) ?></h2>
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

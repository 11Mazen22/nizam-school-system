<?php
/** @var array $routes */
$pageTitle = __('transport.title');
$activeNav = 'transport';
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('transport.subtitle')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/transport/create" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <?= icon('plus') ?>
        <span><?= e(__('transport.add_route')) ?></span>
      </a>
<?php $pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>



<div class="card">
  <div class="card-body p-0">
    <?php if (empty($routes)): ?>
      <div class="n-empty py-5">
        <div class="n-empty-icon"><?= icon('bus') ?></div>
        <p class="mb-2 fw-semibold"><?= e(__('transport.no_routes_yet')) ?></p>
        <?php if (hasPermission('transport.manage')): ?>
          <a href="/transport/create" class="btn btn-sm btn-primary"><?= e(__('transport.add_route')) ?></a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= e(__('transport.route_name')) ?></th>
              <th><?= e(__('transport.vehicle_no')) ?></th>
              <th><?= e(__('transport.driver_name')) ?></th>
              <th><?= e(__('transport.driver_phone')) ?></th>
              <th><?= e(__('transport.passengers')) ?></th>
              <th><?= e(__('transport.fee')) ?></th>
              <th class="text-end"><?= e(__('common.action')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($routes as $r): ?>
              <tr>
                <td class="fw-semibold">
                  <a href="/transport/<?= (int) $r['id'] ?>" class="text-decoration-none">
                    <?= e($r['route_name']) ?>
                  </a>
                </td>
                <td dir="ltr" class="font-monospace small"><?= e($r['vehicle_number']) ?></td>
                <td><?= e($r['driver_name']) ?></td>
                <td dir="ltr" class="font-monospace small"><?= e($r['driver_phone']) ?></td>
                <td>
                  <span class="badge text-bg-light border">
                    <?= (int) $r['assigned_students'] ?> / <?= (int) $r['capacity'] ?>
                  </span>
                </td>
                <td dir="ltr" class="font-monospace small"><?= number_format((float) $r['fee_amount'], 2) ?></td>
                <td class="text-end">
                  <a href="/transport/<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    <?= icon('eye') ?>
                    <span><?= e(__('common.view')) ?></span>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

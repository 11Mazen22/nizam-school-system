<?php
$pageTitle = __('transport.add_route');
$suppressPageTitle = true;
$activeNav = 'transport';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('bus', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('transport.add_route')) ?>
    </h1>
  </div>
  <div>
    <a href="/transport" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
  </div>
</div>

<div class="card col-lg-8 mx-auto">
  <div class="card-body p-4">
    <form method="post" action="/transport">
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label" for="route_name"><?= e(__('transport.route_name')) ?></label>
        <input type="text" class="form-control" id="route_name" name="route_name" placeholder="Route 101 - Downtown / Maadi" required>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="vehicle_number"><?= e(__('transport.vehicle_no')) ?></label>
          <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" placeholder="BUS-428" required dir="ltr">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="capacity"><?= e(__('transport.capacity')) ?></label>
          <input type="number" min="1" max="100" class="form-control" id="capacity" name="capacity" value="30" required dir="ltr">
        </div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="driver_name"><?= e(__('transport.driver_name')) ?></label>
          <input type="text" class="form-control" id="driver_name" name="driver_name" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="driver_phone"><?= e(__('transport.driver_phone')) ?></label>
          <input type="text" class="form-control" id="driver_phone" name="driver_phone" required dir="ltr">
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label" for="fee_amount"><?= e(__('transport.fee')) ?></label>
        <input type="number" step="0.01" min="0" class="form-control" id="fee_amount" name="fee_amount" value="0.00" dir="ltr">
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="/transport" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
          <?= icon('check') ?>
          <span><?= e(__('common.save')) ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

<?php
/** @var array $route @var array $students @var array $allStudents */
$pageTitle = $route['route_name'];
$suppressPageTitle = true;
$activeNav = 'transport';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('bus', 'n-icon-lg text-body-secondary') ?>
      <?= e($route['route_name']) ?>
    </h1>
    <div class="text-muted small">
      <?= e(__('transport.vehicle_no')) ?>: <strong dir="ltr" class="font-monospace"><?= e($route['vehicle_number']) ?></strong> |
      <?= e(__('transport.driver_name')) ?>: <strong><?= e($route['driver_name']) ?></strong> (<span dir="ltr" class="font-monospace"><?= e($route['driver_phone']) ?></span>)
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="/transport" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
    <?php if (hasPermission('transport.manage')): ?>
      <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#assignModal">
        <?= icon('plus') ?>
        <span><?= e(__('transport.assign_student')) ?></span>
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold"><?= e(__('transport.passengers')) ?></span>
    <span class="badge text-bg-light border"><?= count($students) ?> / <?= (int) $route['capacity'] ?></span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($students)): ?>
      <div class="n-empty py-4">
        <div class="n-empty-icon"><?= icon('bus') ?></div>
        <p class="text-muted small mb-0"><?= e(__('transport.no_passengers_yet')) ?></p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= e(__('students.code')) ?></th>
              <th><?= e(__('students.name')) ?></th>
              <th><?= e(__('classes.class')) ?></th>
              <th><?= e(__('transport.pickup_point')) ?></th>
              <th><?= e(__('transport.dropoff_point')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $s): ?>
              <tr>
                <td dir="ltr" class="font-monospace small"><?= e($s['student_code']) ?></td>
                <td class="fw-semibold">
                  <a href="/students/<?= (int) $s['student_id'] ?>" class="text-decoration-none">
                    <?= e($s['full_name']) ?>
                  </a>
                </td>
                <td><?= e($s['class_name'] ?? '—') ?></td>
                <td><?= e($s['pickup_point'] ?? '—') ?></td>
                <td><?= e($s['dropoff_point'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Assign Student Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="/transport/<?= (int) $route['id'] ?>/assign">
        <?= CsrfMiddleware::field() ?>
        <div class="modal-header">
          <h5 class="modal-title" id="assignModalLabel"><?= e(__('transport.assign_student')) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="student_id"><?= e(__('students.name')) ?></label>
            <select class="form-select" id="student_id" name="student_id" required>
              <?php foreach ($allStudents as $st): ?>
                <option value="<?= (int) $st['id'] ?>"><?= e($st['full_name']) ?> (<?= e($st['student_code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="pickup_point"><?= e(__('transport.pickup_point')) ?></label>
            <input type="text" class="form-control" id="pickup_point" name="pickup_point" placeholder="Street 10, Corner...">
          </div>
          <div class="mb-3">
            <label class="form-label" for="dropoff_point"><?= e(__('transport.dropoff_point')) ?></label>
            <input type="text" class="form-control" id="dropoff_point" name="dropoff_point" placeholder="School Gate B...">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
          <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

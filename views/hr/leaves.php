<?php
/** @var array $leaves @var array $teachers @var ?string $activeStatus */
$pageTitle = __('hr.leave_management');
$suppressPageTitle = true;
$activeNav = 'hr';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('calendar', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('hr.leave_management')) ?>
    </h1>
  </div>
  <div class="d-flex gap-2">
    <a href="/hr" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#leaveModal">
      <?= icon('plus') ?>
      <span><?= e(__('hr.request_leave')) ?></span>
    </button>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div class="btn-group btn-group-sm" role="group">
      <a href="/hr/leaves" class="btn btn-outline-secondary <?= $activeStatus === null ? 'active' : '' ?>"><?= e(__('common.all')) ?></a>
      <a href="/hr/leaves?status=pending" class="btn btn-outline-secondary <?= $activeStatus === 'pending' ? 'active' : '' ?>"><?= e(__('hr.status_pending')) ?></a>
      <a href="/hr/leaves?status=approved" class="btn btn-outline-secondary <?= $activeStatus === 'approved' ? 'active' : '' ?>"><?= e(__('hr.status_approved')) ?></a>
      <a href="/hr/leaves?status=rejected" class="btn btn-outline-secondary <?= $activeStatus === 'rejected' ? 'active' : '' ?>"><?= e(__('hr.status_rejected')) ?></a>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($leaves)): ?>
      <div class="n-empty py-5">
        <div class="n-empty-icon"><?= icon('calendar') ?></div>
        <p class="text-muted small mb-0"><?= e(__('hr.no_leaves_yet')) ?></p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= e(__('teachers.name')) ?></th>
              <th><?= e(__('hr.leave_type')) ?></th>
              <th><?= e(__('hr.dates')) ?></th>
              <th><?= e(__('hr.reason')) ?></th>
              <th><?= e(__('hr.status')) ?></th>
              <th class="text-end"><?= e(__('common.action')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($leaves as $l): ?>
              <tr>
                <td class="fw-semibold">
                  <?= e($l['teacher_name']) ?>
                  <div dir="ltr" class="font-monospace small text-muted"><?= e($l['teacher_code']) ?></div>
                </td>
                <td>
                  <span class="badge text-bg-light border">
                    <?= e(__('hr.type_' . $l['leave_type'])) ?>
                  </span>
                </td>
                <td dir="ltr" class="small">
                  <?= e($l['start_date']) ?> → <?= e($l['end_date']) ?>
                </td>
                <td class="small text-muted" style="max-width: 250px;">
                  <?= e($l['reason']) ?>
                  <?php if (!empty($l['action_notes'])): ?>
                    <div class="text-danger small mt-1"><em><?= e($l['action_notes']) ?></em></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($l['status'] === 'approved'): ?>
                    <span class="badge text-bg-success"><?= e(__('hr.status_approved')) ?></span>
                  <?php elseif ($l['status'] === 'rejected'): ?>
                    <span class="badge text-bg-danger"><?= e(__('hr.status_rejected')) ?></span>
                  <?php else: ?>
                    <span class="badge text-bg-warning"><?= e(__('hr.status_pending')) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if ($l['status'] === 'pending' && hasPermission('hr.manage')): ?>
                    <form method="post" action="/hr/leaves/<?= (int) $l['id'] ?>/action" class="d-inline">
                      <?= CsrfMiddleware::field() ?>
                      <input type="hidden" name="status" value="approved">
                      <button type="submit" class="btn btn-sm btn-success me-1">
                        <?= icon('check') ?> <?= e(__('hr.approve')) ?>
                      </button>
                    </form>
                    <form method="post" action="/hr/leaves/<?= (int) $l['id'] ?>/action" class="d-inline">
                      <?= CsrfMiddleware::field() ?>
                      <input type="hidden" name="status" value="rejected">
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <?= icon('x') ?> <?= e(__('hr.reject')) ?>
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="small text-muted"><?= e($l['approved_by_name'] ?? '—') ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Request Leave Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1" aria-labelledby="leaveModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="/hr/leaves">
        <?= CsrfMiddleware::field() ?>
        <div class="modal-header">
          <h5 class="modal-title" id="leaveModalLabel"><?= e(__('hr.request_leave')) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="teacher_id"><?= e(__('teachers.name')) ?></label>
            <select class="form-select" id="teacher_id" name="teacher_id" required>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= (int) $t['id'] ?>"><?= e($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="leave_type"><?= e(__('hr.leave_type')) ?></label>
            <select class="form-select" id="leave_type" name="leave_type">
              <option value="annual"><?= e(__('hr.type_annual')) ?></option>
              <option value="sick"><?= e(__('hr.type_sick')) ?></option>
              <option value="emergency"><?= e(__('hr.type_emergency')) ?></option>
              <option value="unpaid"><?= e(__('hr.type_unpaid')) ?></option>
            </select>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="start_date"><?= e(__('hr.start_date')) ?></label>
              <input type="date" class="form-control" id="start_date" name="start_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="end_date"><?= e(__('hr.end_date')) ?></label>
              <input type="date" class="form-control" id="end_date" name="end_date" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="reason"><?= e(__('hr.reason')) ?></label>
            <textarea class="form-control" id="reason" name="reason" rows="2" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
          <button type="submit" class="btn btn-primary"><?= e(__('common.submit')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

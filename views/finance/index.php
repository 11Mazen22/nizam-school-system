<?php
/** @var array $summary @var array $invoices */
$pageTitle = __('finance.title');
$suppressPageTitle = true;
$activeNav = 'finance';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('wallet', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('finance.title')) ?>
    </h1>
    <p class="text-muted small mb-0"><?= e(__('finance.subtitle')) ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="/finance/structures" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('layers') ?>
      <span><?= e(__('finance.fee_structures')) ?></span>
    </a>
    <?php if (hasPermission('finance.manage')): ?>
      <a href="/finance/invoices/create" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <?= icon('plus') ?>
        <span><?= e(__('finance.create_invoice')) ?></span>
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
  <div class="col">
    <div class="card stat-card stat-blue h-100">
      <div class="card-body">
        <div>
          <div class="stat-value" dir="ltr"><?= number_format((float) $summary['total_billed'], 2) ?></div>
          <div class="stat-label"><?= e(__('finance.total_billed')) ?></div>
        </div>
        <div class="stat-icon"><?= icon('wallet') ?></div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card stat-card stat-teal h-100">
      <div class="card-body">
        <div>
          <div class="stat-value text-success" dir="ltr"><?= number_format((float) $summary['total_collected'], 2) ?></div>
          <div class="stat-label"><?= e(__('finance.total_collected')) ?></div>
        </div>
        <div class="stat-icon"><?= icon('check-circle') ?></div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="card stat-card stat-amber h-100">
      <div class="card-body">
        <div>
          <div class="stat-value text-danger" dir="ltr"><?= number_format((float) $summary['total_outstanding'], 2) ?></div>
          <div class="stat-label"><?= e(__('finance.total_outstanding')) ?></div>
        </div>
        <div class="stat-icon"><?= icon('clock') ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold"><?= e(__('finance.recent_invoices')) ?></span>
    <a href="/finance/invoices" class="btn btn-sm btn-link text-decoration-none"><?= e(__('finance.view_all_invoices')) ?></a>
  </div>
  <div class="card-body p-0">
    <?php if (empty($invoices)): ?>
      <div class="n-empty py-5">
        <div class="n-empty-icon"><?= icon('wallet') ?></div>
        <p class="mb-2 fw-semibold"><?= e(__('finance.no_invoices_yet')) ?></p>
        <?php if (hasPermission('finance.manage')): ?>
          <a href="/finance/invoices/create" class="btn btn-sm btn-primary"><?= e(__('finance.create_invoice')) ?></a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= e(__('finance.invoice_number')) ?></th>
              <th><?= e(__('students.name')) ?></th>
              <th><?= e(__('finance.title_col')) ?></th>
              <th><?= e(__('finance.amount')) ?></th>
              <th><?= e(__('finance.paid')) ?></th>
              <th><?= e(__('finance.status')) ?></th>
              <th><?= e(__('finance.due_date')) ?></th>
              <th class="text-end"><?= e(__('common.action')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($invoices as $inv): ?>
              <tr>
                <td dir="ltr" class="font-monospace small fw-bold">
                  <a href="/finance/invoices/<?= (int) $inv['id'] ?>" class="text-decoration-none">
                    <?= e($inv['invoice_number']) ?>
                  </a>
                </td>
                <td class="fw-semibold">
                  <a href="/students/<?= (int) $inv['student_id'] ?>" class="text-decoration-none">
                    <?= e($inv['student_name']) ?>
                  </a>
                </td>
                <td><?= e($inv['title']) ?></td>
                <td dir="ltr" class="font-monospace fw-semibold">
                  <?= number_format((float) $inv['total_amount'] - (float) $inv['discount_amount'], 2) ?>
                </td>
                <td dir="ltr" class="font-monospace text-success">
                  <?= number_format((float) $inv['paid_amount'], 2) ?>
                </td>
                <td>
                  <?php if ($inv['status'] === 'paid'): ?>
                    <span class="badge text-bg-success"><?= e(__('finance.status_paid')) ?></span>
                  <?php elseif ($inv['status'] === 'partial'): ?>
                    <span class="badge text-bg-warning"><?= e(__('finance.status_partial')) ?></span>
                  <?php else: ?>
                    <span class="badge text-bg-danger"><?= e(__('finance.status_pending')) ?></span>
                  <?php endif; ?>
                </td>
                <td dir="ltr" class="small"><?= e($inv['due_date']) ?></td>
                <td class="text-end">
                  <a href="/finance/invoices/<?= (int) $inv['id'] ?>" class="btn btn-sm btn-outline-secondary">
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

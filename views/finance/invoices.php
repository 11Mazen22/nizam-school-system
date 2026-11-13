<?php
/** @var array $invoices @var ?string $activeStatus */
$pageTitle = __('finance.invoices_title');
$suppressPageTitle = true;
$activeNav = 'finance';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('wallet', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('finance.invoices_title')) ?>
    </h1>
  </div>
  <div class="d-flex gap-2">
    <a href="/finance" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
    <?php if (hasPermission('finance.manage')): ?>
      <a href="/finance/invoices/create" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <?= icon('plus') ?>
        <span><?= e(__('finance.create_invoice')) ?></span>
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div class="btn-group btn-group-sm" role="group">
      <a href="/finance/invoices" class="btn btn-outline-secondary <?= $activeStatus === null ? 'active' : '' ?>"><?= e(__('common.all')) ?></a>
      <a href="/finance/invoices?status=pending" class="btn btn-outline-secondary <?= $activeStatus === 'pending' ? 'active' : '' ?>"><?= e(__('finance.status_pending')) ?></a>
      <a href="/finance/invoices?status=partial" class="btn btn-outline-secondary <?= $activeStatus === 'partial' ? 'active' : '' ?>"><?= e(__('finance.status_partial')) ?></a>
      <a href="/finance/invoices?status=paid" class="btn btn-outline-secondary <?= $activeStatus === 'paid' ? 'active' : '' ?>"><?= e(__('finance.status_paid')) ?></a>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($invoices)): ?>
      <div class="n-empty py-5">
        <div class="n-empty-icon"><?= icon('wallet') ?></div>
        <p class="text-muted small mb-0"><?= e(__('finance.no_invoices_yet')) ?></p>
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

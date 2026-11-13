<?php
/** @var array $invoice @var array $payments */
$pageTitle = __('finance.invoice_title', ['number' => $invoice['invoice_number']]);
$suppressPageTitle = true;
$activeNav = 'finance';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$netAmount = (float) $invoice['total_amount'] - (float) $invoice['discount_amount'];
$balance = max(0.0, $netAmount - (float) $invoice['paid_amount']);
?>

<div class="n-page-head">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <h1 class="d-flex align-items-center gap-2 mb-0">
        <?= icon('wallet', 'n-icon-lg text-body-secondary') ?>
        <?= e($invoice['invoice_number']) ?>
      </h1>
      <?php if ($invoice['status'] === 'paid'): ?>
        <span class="badge text-bg-success fs-6"><?= e(__('finance.status_paid')) ?></span>
      <?php elseif ($invoice['status'] === 'partial'): ?>
        <span class="badge text-bg-warning fs-6"><?= e(__('finance.status_partial')) ?></span>
      <?php else: ?>
        <span class="badge text-bg-danger fs-6"><?= e(__('finance.status_pending')) ?></span>
      <?php endif; ?>
    </div>
    <div class="text-muted small">
      <?= e(__('students.name')) ?>: <strong><?= e($invoice['student_name']) ?></strong> (<span dir="ltr" class="font-monospace"><?= e($invoice['student_code']) ?></span>)
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="/finance/invoices" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
    <button type="button" class="btn btn-outline-dark d-inline-flex align-items-center gap-2" onclick="window.print()">
      <?= icon('printer') ?>
      <span><?= e(__('common.print')) ?></span>
    </button>
    <?php if ($balance > 0 && hasPermission('finance.manage')): ?>
      <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#paymentModal">
        <?= icon('plus') ?>
        <span><?= e(__('finance.record_payment')) ?></span>
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header fw-semibold"><?= e(__('finance.invoice_summary')) ?></div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="d-flex justify-content-between mb-2">
            <span class="text-muted"><?= e(__('finance.title_col')) ?></span>
            <strong><?= e($invoice['title']) ?></strong>
          </li>
          <li class="d-flex justify-content-between mb-2">
            <span class="text-muted"><?= e(__('finance.gross_amount')) ?></span>
            <span dir="ltr" class="font-monospace"><?= number_format((float) $invoice['total_amount'], 2) ?></span>
          </li>
          <li class="d-flex justify-content-between mb-2">
            <span class="text-muted"><?= e(__('finance.discount')) ?></span>
            <span dir="ltr" class="font-monospace text-danger">- <?= number_format((float) $invoice['discount_amount'], 2) ?></span>
          </li>
          <hr>
          <li class="d-flex justify-content-between mb-2 fs-5">
            <span class="fw-semibold"><?= e(__('finance.net_amount')) ?></span>
            <strong dir="ltr" class="font-monospace text-primary"><?= number_format($netAmount, 2) ?></strong>
          </li>
          <li class="d-flex justify-content-between mb-2">
            <span class="text-muted"><?= e(__('finance.total_collected')) ?></span>
            <span dir="ltr" class="font-monospace text-success"><?= number_format((float) $invoice['paid_amount'], 2) ?></span>
          </li>
          <li class="d-flex justify-content-between mb-0 fs-5">
            <span class="fw-semibold text-danger"><?= e(__('finance.balance_due')) ?></span>
            <strong dir="ltr" class="font-monospace text-danger"><?= number_format($balance, 2) ?></strong>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= e(__('finance.payment_history')) ?></span>
        <span class="badge text-bg-light border"><?= count($payments) ?> <?= e(__('finance.payments_count')) ?></span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($payments)): ?>
          <div class="n-empty py-4">
            <div class="n-empty-icon"><?= icon('wallet') ?></div>
            <p class="text-muted small mb-0"><?= e(__('finance.no_payments_yet')) ?></p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th><?= e(__('finance.receipt_no')) ?></th>
                  <th><?= e(__('finance.amount')) ?></th>
                  <th><?= e(__('finance.method')) ?></th>
                  <th><?= e(__('finance.date')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $p): ?>
                  <tr>
                    <td dir="ltr" class="font-monospace small fw-bold text-success"><?= e($p['receipt_number']) ?></td>
                    <td dir="ltr" class="font-monospace fw-semibold"><?= number_format((float) $p['amount'], 2) ?></td>
                    <td><?= e(__('finance.method_' . $p['payment_method'])) ?></td>
                    <td dir="ltr" class="small"><?= e($p['payment_date']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($balance > 0 && hasPermission('finance.manage')): ?>
  <!-- Record Payment Modal -->
  <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post" action="/finance/invoices/<?= (int) $invoice['id'] ?>/payments">
          <?= CsrfMiddleware::field() ?>
          <div class="modal-header">
            <h5 class="modal-title" id="paymentModalLabel"><?= e(__('finance.record_payment')) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label" for="amount"><?= e(__('finance.payment_amount')) ?></label>
              <input type="number" step="0.01" min="1" max="<?= e((string) $balance) ?>" class="form-control" id="amount" name="amount" value="<?= e((string) $balance) ?>" required dir="ltr">
            </div>
            <div class="mb-3">
              <label class="form-label" for="payment_method"><?= e(__('finance.method')) ?></label>
              <select class="form-select" id="payment_method" name="payment_method">
                <option value="cash"><?= e(__('finance.method_cash')) ?></option>
                <option value="bank_transfer"><?= e(__('finance.method_bank_transfer')) ?></option>
                <option value="card"><?= e(__('finance.method_card')) ?></option>
                <option value="cheque"><?= e(__('finance.method_cheque')) ?></option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="payment_date"><?= e(__('finance.date')) ?></label>
              <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="reference_number"><?= e(__('finance.reference_no')) ?> (<?= e(__('common.optional')) ?>)</label>
              <input type="text" class="form-control" id="reference_number" name="reference_number" dir="ltr">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
            <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
              <?= icon('check') ?>
              <span><?= e(__('common.save')) ?></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

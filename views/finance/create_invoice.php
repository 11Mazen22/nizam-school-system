<?php
/** @var array $students @var ?int $academicYearId */
$pageTitle = __('finance.create_invoice');
$activeNav = 'finance';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('wallet', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('finance.create_invoice')) ?>
    </h1>
  </div>
  <div>
    <a href="/finance/invoices" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
  </div>
</div>

<div class="card col-lg-8 mx-auto">
  <div class="card-body p-4">
    <form method="post" action="/finance/invoices">
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label" for="student_id"><?= e(__('students.name')) ?></label>
        <select class="form-select" id="student_id" name="student_id" required>
          <option value=""><?= e(__('finance.select_student')) ?></option>
          <?php foreach ($students as $s): ?>
            <option value="<?= (int) $s['id'] ?>">
              <?= e($s['full_name']) ?> (<?= e($s['student_code']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label" for="title"><?= e(__('finance.invoice_title_field')) ?></label>
        <input type="text" class="form-control" id="title" name="title" placeholder="<?= e(__('finance.title_placeholder')) ?>" required>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="total_amount"><?= e(__('finance.amount')) ?></label>
          <input type="number" step="0.01" min="1" class="form-control" id="total_amount" name="total_amount" required dir="ltr">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="discount_amount"><?= e(__('finance.discount')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="number" step="0.01" min="0" class="form-control" id="discount_amount" name="discount_amount" value="0.00" dir="ltr">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="due_date"><?= e(__('finance.due_date')) ?></label>
        <input type="date" class="form-control" id="due_date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
      </div>

      <div class="mb-4">
        <label class="form-label" for="notes"><?= e(__('finance.notes')) ?> (<?= e(__('common.optional')) ?>)</label>
        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="/finance/invoices" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
          <?= icon('check') ?>
          <span><?= e(__('common.save')) ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

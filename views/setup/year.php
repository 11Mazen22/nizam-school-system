<?php
/** @var string|null $error */
use App\Middleware\CsrfMiddleware;
$pageTitle = __('setup.year.title');
$stepNumber = 4;
require dirname(__DIR__) . '/layout/setup-start.php';
?>
      <form method="post" action="/setup/year">
        <?= CsrfMiddleware::field() ?>
        <div class="mb-3">
          <label class="form-label" for="label"><?= e(__('setup.year.label')) ?></label>
          <input type="text" class="form-control" id="label" name="label" placeholder="2025/2026" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="start_date"><?= e(__('setup.year.start_date')) ?></label>
          <input type="date" class="form-control" id="start_date" name="start_date" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="end_date"><?= e(__('setup.year.end_date')) ?></label>
          <input type="date" class="form-control" id="end_date" name="end_date" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
          <?= icon('arrow-forward', 'n-flip-rtl') ?>
          <span><?= e(__('common.save')) ?></span>
        </button>
      </form>
<?php require dirname(__DIR__) . '/layout/setup-end.php'; ?>

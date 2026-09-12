<?php
use App\Middleware\CsrfMiddleware;
$pageTitle = __('setup.done.title');
$stepNumber = 6;
require dirname(__DIR__) . '/layout/setup-start.php';
?>
      <div class="text-center mb-3">
        <div class="d-inline-flex align-items-center justify-content-center mb-3"
             style="width: 64px; height: 64px; border-radius: 50%; background: var(--n-success-soft); color: var(--n-success);">
          <?= icon('check-circle', 'n-icon-xl') ?>
        </div>
        <p class="mb-0"><?= e(__('setup.done.body')) ?></p>
      </div>
      <form method="post" action="/setup/complete">
        <?= CsrfMiddleware::field() ?>
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
          <?= icon('arrow-forward', 'n-flip-rtl') ?>
          <span><?= e(__('setup.done.go_to_dashboard')) ?></span>
        </button>
      </form>
<?php require dirname(__DIR__) . '/layout/setup-end.php'; ?>

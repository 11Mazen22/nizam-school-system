<?php
/** @var string|null $error @var int $pending */
use App\Middleware\CsrfMiddleware;
$pageTitle = __('setup.schema.title');
$stepNumber = 2;
require dirname(__DIR__) . '/layout/setup-start.php';
?>
      <p class="text-muted"><?= e(__('setup.schema.running')) ?></p>
      <div class="d-flex align-items-center gap-2 mb-3 text-body-secondary">
        <?= icon('layers') ?>
        <span><?= e(__('setup.schema.pending_count', ['count' => (int) $pending])) ?></span>
      </div>
      <form method="post" action="/setup/schema">
        <?= CsrfMiddleware::field() ?>
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
          <?= icon('arrow-forward', 'n-flip-rtl') ?>
          <span><?= e(__('setup.schema.continue')) ?></span>
        </button>
      </form>
<?php require dirname(__DIR__) . '/layout/setup-end.php'; ?>

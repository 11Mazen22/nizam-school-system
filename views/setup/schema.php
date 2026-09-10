<?php
/** @var string|null $error @var int $pending */
use App\Middleware\CsrfMiddleware;
$pageTitle = __('setup.schema.title');
$stepNumber = 2;
require dirname(__DIR__) . '/layout/setup-start.php';
?>
      <p class="text-muted"><?= e(__('setup.schema.running')) ?></p>
      <ul>
        <li><?= e(__('setup.schema.pending_count', ['count' => (int) $pending])) ?></li>
      </ul>
      <form method="post" action="/setup/schema">
        <?= CsrfMiddleware::field() ?>
        <button type="submit" class="btn btn-primary w-100"><?= e(__('setup.schema.continue')) ?></button>
      </form>
<?php require dirname(__DIR__) . '/layout/setup-end.php'; ?>

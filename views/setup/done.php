<?php
use App\Middleware\CsrfMiddleware;
$pageTitle = __('setup.done.title');
$stepNumber = 5;
require dirname(__DIR__) . '/layout/setup-start.php';
?>
      <p><?= e(__('setup.done.body')) ?></p>
      <form method="post" action="/setup/complete">
        <?= CsrfMiddleware::field() ?>
        <button type="submit" class="btn btn-primary w-100"><?= e(__('setup.done.go_to_dashboard')) ?></button>
      </form>
<?php require dirname(__DIR__) . '/layout/setup-end.php'; ?>

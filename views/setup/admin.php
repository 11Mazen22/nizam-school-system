<?php
/** @var string|null $error */
use App\Middleware\CsrfMiddleware;
$pageTitle = __('setup.admin.title');
$stepNumber = 5;
require dirname(__DIR__) . '/layout/setup-start.php';
?>
      <form method="post" action="/setup/admin">
        <?= CsrfMiddleware::field() ?>
        <div class="mb-3">
          <label class="form-label" for="full_name"><?= e(__('setup.admin.full_name')) ?></label>
          <input type="text" class="form-control" id="full_name" name="full_name" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="username"><?= e(__('setup.admin.username')) ?></label>
          <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="password"><?= e(__('setup.admin.password')) ?></label>
          <input type="password" class="form-control" id="password" name="password" minlength="8" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="password_confirm"><?= e(__('setup.admin.password_confirm')) ?></label>
          <input type="password" class="form-control" id="password_confirm" name="password_confirm" minlength="8" required>
        </div>
        <button type="submit" class="btn btn-primary w-100"><?= e(__('common.save')) ?></button>
      </form>
<?php require dirname(__DIR__) . '/layout/setup-end.php'; ?>

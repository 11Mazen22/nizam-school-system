<?php
/**
 * Emergency admin recovery form -- reachable ONLY when SetupController::showRecoverAdmin()
 * has confirmed the users table is genuinely empty (checked again on POST too).
 * Deliberately reuses the exact same wizard shell/lang keys as setup/admin.php
 * (this IS effectively that same step, just reachable outside the normal
 * setup_completed gate) rather than a bespoke page, so it looks and behaves
 * like part of the app instead of a one-off hack.
 *
 * @var string|null $error
 */
use App\Middleware\CsrfMiddleware;
$pageTitle = __('setup.recover_admin_title');
$stepNumber = 6; // beyond $totalSteps (5) -- hides the normal step indicator, same trick setup/done.php uses
require dirname(__DIR__) . '/layout/setup-start.php';
?>
      <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
        <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
        <div>
          <strong><?= e(__('setup.recover_admin_warning_title')) ?></strong>
          <div class="small"><?= e(__('setup.recover_admin_warning_text')) ?></div>
        </div>
      </div>
      <form method="post" action="/setup/recover-admin">
        <?= CsrfMiddleware::field() ?>
        <div class="mb-3">
          <label class="form-label" for="full_name"><?= e(__('setup.admin.full_name')) ?></label>
          <input type="text" class="form-control" id="full_name" name="full_name" required autofocus>
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
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
          <?= icon('check') ?>
          <span><?= e(__('setup.recover_admin_submit')) ?></span>
        </button>
      </form>
<?php require dirname(__DIR__) . '/layout/setup-end.php'; ?>

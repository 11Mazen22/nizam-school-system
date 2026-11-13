<?php
/**
 * Password reset with a one-time recovery token.
 *
 * Shown by /setup/recover-admin once the install has user rows. The token is
 * issued from the server shell (`php bin/recover-admin.php <username>`), so
 * nobody can reset an admin password from the browser alone.
 *
 * @var string|null $error
 * @var bool        $hasPendingToken
 */
use App\Middleware\CsrfMiddleware;
$pageTitle = __('setup.recover_admin_title');
$stepNumber = 6; // beyond $totalSteps (5), hides the step indicator
require dirname(__DIR__) . '/layout/setup-start.php';
?>
      <?php if ($error !== null): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
          <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
        <?= icon('shield-alert', 'n-icon-lg flex-shrink-0') ?>
        <div>
          <strong><?= e(__('setup.recover_token_warning_title')) ?></strong>
          <div class="small"><?= e(__('setup.recover_token_warning_text')) ?></div>
        </div>
      </div>

      <div class="n-recover-steps mb-3">
        <div class="n-recover-step">
          <span class="n-recover-step-num">1</span>
          <div>
            <div class="n-recover-step-title"><?= e(__('setup.recover_token_step_cli_title')) ?></div>
            <code class="n-recover-code" dir="ltr">php bin/recover-admin.php &lt;username&gt;</code>
          </div>
        </div>
        <div class="n-recover-step">
          <span class="n-recover-step-num">2</span>
          <div>
            <div class="n-recover-step-title"><?= e(__('setup.recover_token_step_form_title')) ?></div>
            <div class="small text-muted"><?= e(__('setup.recover_token_step_form_text')) ?></div>
          </div>
        </div>
      </div>

      <?php if (!$hasPendingToken): ?>
        <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
          <?= icon('info', 'n-icon-lg flex-shrink-0') ?>
          <span><?= e(__('setup.recover_token_none')) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" action="/setup/recover-admin/reset">
        <?= CsrfMiddleware::field() ?>
        <div class="mb-3">
          <label class="form-label" for="token"><?= e(__('setup.recover_token_field')) ?></label>
          <input type="text" class="form-control n-recover-token-input" id="token" name="token"
                 placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" dir="ltr" autocomplete="one-time-code"
                 spellcheck="false" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label" for="password"><?= e(__('setup.recover_token_new_password')) ?></label>
          <input type="password" class="form-control" id="password" name="password" minlength="8" required
                 autocomplete="new-password">
        </div>
        <div class="mb-3">
          <label class="form-label" for="password_confirm"><?= e(__('setup.admin.password_confirm')) ?></label>
          <input type="password" class="form-control" id="password_confirm" name="password_confirm" minlength="8" required
                 autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
          <?= icon('lock') ?>
          <span><?= e(__('setup.recover_token_submit')) ?></span>
        </button>
      </form>

      <div class="text-center mt-3">
        <a href="/login" class="btn btn-link btn-sm"><?= e(__('setup.recover_token_back')) ?></a>
      </div>
<?php require dirname(__DIR__) . '/layout/setup-end.php'; ?>

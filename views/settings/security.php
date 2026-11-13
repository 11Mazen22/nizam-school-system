<?php
/** @var ?string $error @var int|string $loginMaxAttempts @var int|string $lockoutMinutes @var int|string $sessionTimeoutMinutes */
$pageTitle = __('settings.title');
$activeNav = 'settings';
$activeSettingsTab = 'security';

require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>



<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($error !== null): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2">
    <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<div class="n-settings-card">
  <div class="n-settings-card-head">
    <h2><?= e(__('settings.tab.security')) ?></h2>
    <p><?= e(__('settings.desc.security')) ?></p>
  </div>
  <div class="n-settings-card-body">
    <form method="post" action="/settings/security" data-unsaved-guard data-loading-submit>
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label" for="login_max_attempts"><?= e(__('settings.login_max_attempts')) ?></label>
        <input type="number" class="form-control" id="login_max_attempts" name="login_max_attempts" value="<?= (int) $loginMaxAttempts ?>" min="3" max="20" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="lockout_minutes"><?= e(__('settings.lockout_minutes')) ?></label>
        <input type="number" class="form-control" id="lockout_minutes" name="lockout_minutes" value="<?= (int) $lockoutMinutes ?>" min="1" max="1440" required>
      </div>
      <div class="mb-0">
        <label class="form-label" for="session_timeout_minutes"><?= e(__('settings.session_timeout_minutes')) ?></label>
        <input type="number" class="form-control" id="session_timeout_minutes" name="session_timeout_minutes" value="<?= (int) $sessionTimeoutMinutes ?>" min="5" max="480" required>
        <div class="form-text"><?= e(__('settings.session_timeout_help')) ?></div>
      </div>
      <div class="n-settings-save">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <?= icon('check') ?>
          <span><?= e(__('common.save')) ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_end.php'; ?>
<?php require dirname(__DIR__) . '/layout/end.php'; ?>

<?php
/**
 * @var array<string,mixed> $user
 * @var bool $enabled
 * @var array{secret:string, uri:string}|null $setup
 * @var list<string> $recoveryCodes
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('mfa.title');
$activeNav = 'settings';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold d-flex align-items-center gap-2">
          <?= icon('shield-check') ?>
          <?= e(__('mfa.title')) ?>
        </span>
        <?php if ($enabled): ?>
          <span class="badge text-bg-success"><?= e(__('mfa.on')) ?></span>
        <?php else: ?>
          <span class="badge text-bg-secondary"><?= e(__('mfa.off')) ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3"><?= e(__('mfa.intro')) ?></p>

        <?php if ($recoveryCodes !== []): ?>
          <div class="alert alert-warning">
            <div class="fw-semibold mb-2"><?= e(__('mfa.recovery_title')) ?></div>
            <p class="small mb-2"><?= e(__('mfa.recovery_hint')) ?></p>
            <div class="d-flex flex-wrap gap-2 font-monospace" dir="ltr">
              <?php foreach ($recoveryCodes as $code): ?>
                <span class="badge text-bg-light border"><?= e($code) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($enabled): ?>
          <p class="mb-3"><?= e(__('mfa.enabled_at')) ?>:
            <span class="fw-semibold" dir="ltr"><?= e((string) $user['mfa_enabled_at']) ?></span>
          </p>
          <form method="post" action="/mfa/disable" class="border-top pt-3" data-loading-submit>
            <?= CsrfMiddleware::field() ?>
            <p class="small text-muted"><?= e(__('mfa.disable_hint')) ?></p>
            <div class="row g-2">
              <div class="col-md-5">
                <label class="form-label" for="disable_password"><?= e(__('auth.login.password')) ?></label>
                <input type="password" class="form-control" id="disable_password" name="password" autocomplete="current-password" required>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="disable_code"><?= e(__('mfa.code')) ?></label>
                <input type="text" class="form-control" id="disable_code" name="code" inputmode="numeric" maxlength="12" autocomplete="one-time-code" required dir="ltr">
              </div>
              <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-danger w-100"><?= e(__('mfa.disable')) ?></button>
              </div>
            </div>
          </form>
        <?php elseif ($setup !== null): ?>
          <div class="alert alert-info">
            <div class="fw-semibold mb-1"><?= e(__('mfa.setup_title')) ?></div>
            <p class="small mb-2"><?= e(__('mfa.setup_hint')) ?></p>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <code class="fs-5 user-select-all" dir="ltr"><?= e($setup['secret']) ?></code>
              <button type="button" class="btn btn-sm btn-outline-secondary n-copy-inline"
                      data-copy-text="<?= e($setup['secret']) ?>"
                      data-copy-label="<?= e(__('app.copy')) ?>"
                      data-copied-label="<?= e(__('app.copied')) ?>">
                <?= icon('copy', 'n-icon-sm') ?><span><?= e(__('app.copy')) ?></span>
              </button>
            </div>
            <a class="small d-inline-block mt-2" href="<?= e($setup['uri']) ?>" dir="ltr"><?= e(__('mfa.open_in_app')) ?></a>
          </div>
          <form method="post" action="/mfa/enable" class="row g-2 align-items-end" data-loading-submit>
            <?= CsrfMiddleware::field() ?>
            <div class="col-md-6">
              <label class="form-label" for="enable_code"><?= e(__('mfa.code')) ?></label>
              <input type="text" class="form-control" id="enable_code" name="code" inputmode="numeric" maxlength="12" autocomplete="one-time-code" required dir="ltr" autofocus>
            </div>
            <div class="col-md-6">
              <button type="submit" class="btn btn-primary"><?= e(__('mfa.enable')) ?></button>
              <a href="/mfa" class="btn btn-link"><?= e(__('common.cancel')) ?></a>
            </div>
          </form>
        <?php else: ?>
          <form method="post" action="/mfa/setup">
            <?= CsrfMiddleware::field() ?>
            <button type="submit" class="btn btn-primary"><?= e(__('mfa.start')) ?></button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header fw-semibold"><?= e(__('mfa.why_title')) ?></div>
      <div class="card-body small text-muted">
        <p><?= e(__('mfa.why_body')) ?></p>
        <p class="mb-0"><?= e(__('mfa.why_recovery')) ?></p>
      </div>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

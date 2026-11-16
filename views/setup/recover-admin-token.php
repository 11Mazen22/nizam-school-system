<?php
/**
 * Existing-administrator recovery with a server-issued one-time code.
 *
 * @var string|null $error
 * @var string      $recoveryStatus active|none|expired|unavailable
 * @var int|null    $tokenExpiresAt
 * @var string      $oldToken
 */
use App\Middleware\CsrfMiddleware;
use App\Repositories\SchoolRepository;

$error ??= null;
$recoveryStatus ??= 'none';
$tokenExpiresAt ??= null;
$oldToken ??= '';

$school = (new SchoolRepository())->full();
$schoolName = $school === null
    ? __('app.name')
    : (currentLocale() === 'ar' ? $school['name_ar'] : $school['name']);
$logoUrl = !empty($school['logo_path']) ? '/logo' : '/assets/img/hadaba-logo.png';

$statusClass = match ($recoveryStatus) {
    'active' => 'is-active',
    'expired' => 'is-expired',
    'unavailable' => 'is-unavailable',
    default => 'is-empty',
};
$statusTitle = match ($recoveryStatus) {
    'active' => __('setup.recover_status_active_title'),
    'expired' => __('setup.recover_status_expired_title'),
    'unavailable' => __('setup.recover_status_unavailable_title'),
    default => __('setup.recover_status_empty_title'),
};
$statusText = match ($recoveryStatus) {
    'active' => __('setup.recover_status_active_text'),
    'expired' => __('setup.recover_status_expired_text'),
    'unavailable' => __('setup.recover_status_unavailable_text'),
    default => __('setup.recover_status_empty_text'),
};
?>
<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="description" content="<?= e(__('setup.recover_token_intro')) ?>">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="/assets/img/favicon-32.png">
<link rel="apple-touch-icon" href="/assets/img/favicon-180.png">
<title><?= e(__('setup.recover_admin_title')) ?> — <?= e($schoolName) ?></title>
<?php if (currentDirection() === 'rtl'): ?>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
<?php else: ?>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(assetUrl('/assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(assetUrl('/assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(assetUrl('/assets/css/theme.css')) ?>">
<link rel="stylesheet" href="<?= e(assetUrl('/assets/css/components.css')) ?>">
</head>
<body class="n-auth-body n-recovery-body">
  <div class="n-auth-shell n-recovery-shell">
    <aside class="n-auth-side n-recovery-aside">
      <div class="n-auth-side-top">
        <div class="school-brand-mark school-brand-mark-logo auth-school-mark">
          <img src="<?= e($logoUrl) ?>" alt="">
        </div>
        <div>
          <div class="n-auth-app-name-lg"><?= e($schoolName) ?></div>
          <div class="n-auth-app-subtitle"><?= e(__('auth.login.system_subtitle')) ?></div>
        </div>
      </div>

      <div class="n-auth-side-mid n-recovery-guide">
        <div class="n-recovery-kicker">
          <?= icon('shield-check', 'n-icon-sm') ?>
          <span><?= e(__('setup.recover_secure_process')) ?></span>
        </div>
        <h2 class="n-auth-tagline"><?= e(__('setup.recover_token_side_title')) ?></h2>
        <p class="n-auth-tagline-sub"><?= e(__('setup.recover_token_side_text')) ?></p>

        <ol class="n-recover-steps">
          <li>
            <span class="n-recover-step-num">1</span>
            <div>
              <div class="n-recover-step-title"><?= e(__('setup.recover_step_open_title')) ?></div>
              <div class="n-recover-step-text"><?= e(__('setup.recover_step_open_text')) ?></div>
              <code class="n-recover-code" dir="ltr">php bin/recover-admin.php</code>
            </div>
          </li>
          <li>
            <span class="n-recover-step-num">2</span>
            <div>
              <div class="n-recover-step-title"><?= e(__('setup.recover_step_choose_title')) ?></div>
              <div class="n-recover-step-text"><?= e(__('setup.recover_step_choose_text')) ?></div>
            </div>
          </li>
          <li>
            <span class="n-recover-step-num">3</span>
            <div>
              <div class="n-recover-step-title"><?= e(__('setup.recover_step_reset_title')) ?></div>
              <div class="n-recover-step-text"><?= e(__('setup.recover_step_reset_text')) ?></div>
            </div>
          </li>
        </ol>
      </div>

      <div class="n-auth-side-foot">
        <div class="n-auth-stat-item">
          <span class="n-auth-stat-icon"><?= icon('lock') ?></span>
          <span><?= e(__('setup.recover_token_footer')) ?></span>
        </div>
      </div>
    </aside>

    <main class="n-auth-main n-recovery-main">
      <div class="n-auth-lang">
        <div class="btn-group btn-group-sm n-lang-switch" role="group" aria-label="<?= e(__('nav.language')) ?>">
          <a class="btn btn-outline-secondary<?= currentLocale() === 'ar' ? ' active' : '' ?>" href="/lang?to=ar">عربي</a>
          <a class="btn btn-outline-secondary<?= currentLocale() === 'en' ? ' active' : '' ?>" href="/lang?to=en">English</a>
        </div>
      </div>

      <div class="n-auth-form-wrap n-recovery-panel n-reveal">
        <div class="n-auth-welcome n-recovery-heading">
          <div class="n-auth-welcome-icon"><?= icon('shield-alert') ?></div>
          <div>
            <div class="n-recovery-eyebrow"><?= e(__('setup.recover_identity_verification')) ?></div>
            <h1><?= e(__('setup.recover_admin_title')) ?></h1>
            <p class="n-auth-sub"><?= e(__('setup.recover_token_intro')) ?></p>
          </div>
        </div>

        <?php if ($error !== null): ?>
          <div class="alert alert-danger d-flex align-items-start gap-2 n-auth-alert n-recovery-alert" role="alert">
            <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <div class="n-recovery-status <?= e($statusClass) ?>"
             role="status" aria-live="polite"
             data-expired-title="<?= e(__('setup.recover_status_expired_title')) ?>"
             data-expired-text="<?= e(__('setup.recover_status_expired_text')) ?>"
             <?php if ($tokenExpiresAt !== null): ?>data-recovery-expiry="<?= e((string) $tokenExpiresAt) ?>"<?php endif; ?>>
          <span class="n-recovery-status-icon">
            <?= $recoveryStatus === 'active' ? icon('check-circle') : icon($recoveryStatus === 'unavailable' ? 'alert-triangle' : 'clock') ?>
          </span>
          <span class="n-recovery-status-copy">
            <strong><?= e($statusTitle) ?></strong>
            <span><?= e($statusText) ?></span>
          </span>
          <?php if ($recoveryStatus === 'active'): ?>
            <span class="n-recovery-countdown" dir="ltr" data-recovery-countdown>20:00</span>
          <?php endif; ?>
        </div>

        <details class="n-recovery-help"<?= $recoveryStatus === 'active' ? '' : ' open' ?>>
          <summary>
            <span class="n-recovery-help-icon"><?= icon('users') ?></span>
            <span>
              <strong><?= e(__('setup.recover_forgot_username_title')) ?></strong>
              <small><?= e(__('setup.recover_forgot_username_summary')) ?></small>
            </span>
            <?= icon('chevron-down', 'n-recovery-help-chevron') ?>
          </summary>
          <div class="n-recovery-help-body">
            <p><?= e(__('setup.recover_forgot_username_text')) ?></p>
            <div class="n-recovery-command" dir="ltr">
              <code>php bin/recover-admin.php</code>
              <button type="button" class="btn n-recovery-copy"
                      data-copy-text="php bin/recover-admin.php"
                      data-copy-label="<?= e(__('app.copy')) ?>"
                      data-copied-label="<?= e(__('app.copied')) ?>"
                      aria-label="<?= e(__('setup.recover_command_copy')) ?>">
                <?= icon('copy', 'n-icon-sm') ?>
                <span><?= e(__('app.copy')) ?></span>
              </button>
            </div>
            <p class="n-recovery-help-note"><?= e(__('setup.recover_windows_utility_hint')) ?></p>
          </div>
        </details>

        <form method="post" action="/setup/recover-admin/reset"
              class="n-auth-form n-recovery-form" data-recovery-form data-loading-submit>
          <?= CsrfMiddleware::field() ?>

          <div class="n-form-group">
            <label class="n-form-label" for="token">
              <span class="n-form-label-icon"><?= icon('shield-check') ?></span>
              <span><?= e(__('setup.recover_token_field')) ?></span>
            </label>
            <div class="n-input-enhanced">
              <div class="n-input-icon-prefix"><?= icon('shield') ?></div>
              <input type="text" class="form-control n-input-premium n-recover-token-input"
                     id="token" name="token" value="<?= e($oldToken) ?>"
                     placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" dir="ltr"
                     inputmode="text" maxlength="32" autocomplete="one-time-code"
                     autocapitalize="characters" spellcheck="false"
                     aria-describedby="token-help" required autofocus>
            </div>
            <div class="n-field-help" id="token-help"><?= e(__('setup.recover_token_input_help')) ?></div>
          </div>

          <div class="n-form-group">
            <label class="n-form-label" for="password">
              <span class="n-form-label-icon"><?= icon('lock') ?></span>
              <span><?= e(__('setup.recover_token_new_password')) ?></span>
            </label>
            <div class="n-input-enhanced n-password-input">
              <div class="n-input-icon-prefix"><?= icon('lock') ?></div>
              <input type="password" class="form-control n-input-premium" id="password" name="password"
                     minlength="8" maxlength="128" required autocomplete="new-password"
                     aria-describedby="password-guidance">
              <button type="button" class="n-password-toggle" data-password-toggle="password"
                      data-show-label="<?= e(__('setup.password_show')) ?>"
                      data-hide-label="<?= e(__('setup.password_hide')) ?>"
                      aria-label="<?= e(__('setup.password_show')) ?>">
                <?= icon('eye') ?>
              </button>
            </div>
            <div class="n-password-guidance" id="password-guidance">
              <div class="n-password-meter" aria-hidden="true"><span data-password-meter></span></div>
              <div class="n-password-guidance-row">
                <span data-password-strength
                      data-empty="<?= e(__('setup.password_strength_empty')) ?>"
                      data-weak="<?= e(__('setup.password_strength_weak')) ?>"
                      data-fair="<?= e(__('setup.password_strength_fair')) ?>"
                      data-good="<?= e(__('setup.password_strength_good')) ?>"
                      data-strong="<?= e(__('setup.password_strength_strong')) ?>"><?= e(__('setup.password_strength_empty')) ?></span>
                <span data-password-length
                      data-minimum="<?= e(__('setup.password_minimum')) ?>"
                      data-recommended="<?= e(__('setup.password_recommended')) ?>"><?= e(__('setup.password_minimum')) ?></span>
              </div>
            </div>
          </div>

          <div class="n-form-group">
            <label class="n-form-label" for="password_confirm">
              <span class="n-form-label-icon"><?= icon('check-circle') ?></span>
              <span><?= e(__('setup.admin.password_confirm')) ?></span>
            </label>
            <div class="n-input-enhanced n-password-input">
              <div class="n-input-icon-prefix"><?= icon('check') ?></div>
              <input type="password" class="form-control n-input-premium" id="password_confirm" name="password_confirm"
                     minlength="8" maxlength="128" required autocomplete="new-password"
                     aria-describedby="password-match">
              <button type="button" class="n-password-toggle" data-password-toggle="password_confirm"
                      data-show-label="<?= e(__('setup.password_show')) ?>"
                      data-hide-label="<?= e(__('setup.password_hide')) ?>"
                      aria-label="<?= e(__('setup.password_show')) ?>">
                <?= icon('eye') ?>
              </button>
            </div>
            <div class="n-field-help" id="password-match" data-password-match
                 data-waiting="<?= e(__('setup.password_match_waiting')) ?>"
                 data-match="<?= e(__('setup.password_match_yes')) ?>"
                 data-mismatch="<?= e(__('validation.password_mismatch')) ?>"><?= e(__('setup.password_match_waiting')) ?></div>
          </div>

          <button type="submit" class="btn btn-primary btn-lg w-100 n-btn-animated">
            <span class="n-btn-text"><?= e(__('setup.recover_token_submit')) ?></span>
            <span class="n-btn-icon"><?= icon('arrow-forward', 'n-flip-rtl') ?></span>
            <span class="n-btn-shine"></span>
          </button>

          <div class="n-recovery-assurance">
            <?= icon('shield-check', 'n-icon-sm') ?>
            <span><?= e(__('setup.recover_atomic_assurance')) ?></span>
          </div>
        </form>

        <div class="n-auth-footer">
          <a href="/login" class="n-recover-back">
            <?= icon('chevron-left', 'n-flip-rtl') ?>
            <span><?= e(__('setup.recover_token_back')) ?></span>
          </a>
        </div>
      </div>
    </main>
  </div>

  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="<?= e(assetUrl('/assets/js/app.js')) ?>"></script>
  <script src="<?= e(assetUrl('/assets/js/recover-admin.js')) ?>"></script>
</body>
</html>

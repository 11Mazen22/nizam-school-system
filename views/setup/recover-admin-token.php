<?php
/**
 * Password reset with a one-time recovery code.
 *
 * Shown by /setup/recover-admin once the install has user rows. Deliberately
 * built with the same shell, typography and colours as the sign-in page, so
 * recovery looks like part of the product instead of a plain form on a card.
 *
 * @var string|null $error
 * @var bool        $hasPendingToken
 */
use App\Middleware\CsrfMiddleware;
use App\Repositories\SchoolRepository;

$error ??= null;
$hasPendingToken ??= false;

$school = (new SchoolRepository())->full();
$schoolName = $school === null
    ? __('app.name')
    : (currentLocale() === 'ar' ? $school['name_ar'] : $school['name']);
$logoUrl = !empty($school['logo_path']) ? '/logo' : '/assets/img/hadaba-logo.png';
?>
<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
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
<body class="n-auth-body">
  <div class="n-auth-shell n-recover-shell">
    <aside class="n-auth-side n-recover-aside">
      <div class="n-auth-side-top">
        <div class="school-brand-mark school-brand-mark-logo auth-school-mark">
          <img src="<?= e($logoUrl) ?>" alt="">
        </div>
        <div>
          <div class="n-auth-app-name-lg"><?= e($schoolName) ?></div>
          <div class="n-auth-app-subtitle"><?= e(__('auth.login.system_subtitle')) ?></div>
        </div>
      </div>

      <div class="n-auth-side-mid">
        <h2 class="n-auth-tagline"><?= e(__('setup.recover_token_side_title')) ?></h2>
        <p class="n-auth-tagline-sub"><?= e(__('setup.recover_token_side_text')) ?></p>
        <ol class="n-recover-steps">
          <li>
            <span class="n-recover-step-num">1</span>
            <div>
              <div class="n-recover-step-title"><?= e(__('setup.recover_token_step_cli_title')) ?></div>
              <code class="n-recover-code" dir="ltr">php bin/recover-admin.php &lt;username&gt;</code>
            </div>
          </li>
          <li>
            <span class="n-recover-step-num">2</span>
            <div>
              <div class="n-recover-step-title"><?= e(__('setup.recover_token_step_form_title')) ?></div>
              <div class="n-recover-step-text"><?= e(__('setup.recover_token_step_form_text')) ?></div>
            </div>
          </li>
        </ol>
      </div>

      <div class="n-auth-side-foot">
        <div class="n-auth-stat-item">
          <span class="n-auth-stat-icon"><?= icon('shield-check') ?></span>
          <span><?= e(__('setup.recover_token_footer')) ?></span>
        </div>
      </div>
    </aside>

    <main class="n-auth-main">
      <div class="n-auth-lang">
        <div class="btn-group btn-group-sm n-lang-switch" role="group" aria-label="<?= e(__('nav.language')) ?>">
          <a class="btn btn-outline-secondary<?= currentLocale() === 'ar' ? ' active' : '' ?>" href="/lang?to=ar">عربي</a>
          <a class="btn btn-outline-secondary<?= currentLocale() === 'en' ? ' active' : '' ?>" href="/lang?to=en">English</a>
        </div>
      </div>

      <div class="n-auth-form-wrap n-reveal">
        <div class="n-auth-welcome">
          <div class="n-auth-welcome-icon"><?= icon('shield-alert') ?></div>
          <h1><?= e(__('setup.recover_admin_title')) ?></h1>
          <p class="n-auth-sub"><?= e(__('setup.recover_token_intro')) ?></p>
        </div>

        <?php if ($error !== null): ?>
          <div class="alert alert-danger d-flex align-items-center gap-2 n-auth-alert">
            <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <?php if (!$hasPendingToken): ?>
          <div class="alert alert-warning d-flex align-items-center gap-2 n-auth-alert">
            <?= icon('info', 'n-icon-lg flex-shrink-0') ?>
            <span><?= e(__('setup.recover_token_none')) ?></span>
          </div>
        <?php endif; ?>

        <form method="post" action="/setup/recover-admin/reset" class="n-auth-form">
          <?= CsrfMiddleware::field() ?>

          <div class="n-form-group">
            <label class="n-form-label" for="token"><?= e(__('setup.recover_token_field')) ?></label>
            <input type="text" class="form-control n-input-premium n-recover-token-input" id="token" name="token"
                   placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" dir="ltr" autocomplete="one-time-code"
                   spellcheck="false" required autofocus>
          </div>

          <div class="n-form-group">
            <label class="n-form-label" for="password"><?= e(__('setup.recover_token_new_password')) ?></label>
            <input type="password" class="form-control n-input-premium" id="password" name="password"
                   minlength="8" required autocomplete="new-password">
          </div>

          <div class="n-form-group">
            <label class="n-form-label" for="password_confirm"><?= e(__('setup.admin.password_confirm')) ?></label>
            <input type="password" class="form-control n-input-premium" id="password_confirm" name="password_confirm"
                   minlength="8" required autocomplete="new-password">
          </div>

          <button type="submit" class="btn btn-primary btn-lg w-100 n-btn-animated">
            <span class="n-btn-text"><?= e(__('setup.recover_token_submit')) ?></span>
            <span class="n-btn-icon"><?= icon('arrow-forward', 'n-flip-rtl') ?></span>
          </button>
        </form>

        <div class="n-auth-footer">
          <a href="/login" class="n-recover-back"><?= icon('chevron-left', 'n-flip-rtl') ?><?= e(__('setup.recover_token_back')) ?></a>
        </div>
      </div>
    </main>
  </div>

  <script src="/assets/vendor/jquery/jquery.min.js"></script>
  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="<?= e(assetUrl('/assets/js/app.js')) ?>"></script>
</body>
</html>

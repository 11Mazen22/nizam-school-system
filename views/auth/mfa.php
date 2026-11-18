<?php
/** @var string|null $error */
use App\Middleware\CsrfMiddleware;
use App\Repositories\SchoolRepository;

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
<meta name="robots" content="noindex,nofollow">
<title><?= e(__('auth.mfa.title')) ?> — <?= e($schoolName) ?></title>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="/assets/img/favicon-32.png">
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
  <div class="n-auth-shell">
    <aside class="n-auth-side">
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
        <h2 class="n-auth-tagline"><?= e(__('auth.mfa.tagline')) ?></h2>
        <p class="n-auth-tagline-sub"><?= e(__('auth.mfa.tagline_description')) ?></p>
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
          <div class="n-auth-welcome-icon"><?= icon('shield-check') ?></div>
          <h1><?= e(__('auth.mfa.title')) ?></h1>
          <p class="n-auth-sub"><?= e(__('auth.mfa.subtitle')) ?></p>
        </div>

        <?php if ($error !== null): ?>
          <div class="alert alert-danger d-flex align-items-center gap-2 n-auth-alert">
            <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="post" action="/login/mfa" class="n-auth-form">
          <?= CsrfMiddleware::field() ?>
          <div class="n-form-group">
            <label class="n-form-label" for="code">
              <span class="n-form-label-icon"><?= icon('lock') ?></span>
              <span><?= e(__('auth.mfa.code')) ?></span>
            </label>
            <div class="n-input-enhanced">
              <div class="n-input-icon-prefix"><?= icon('lock') ?></div>
              <input type="text" class="form-control n-input-premium" id="code" name="code"
                     inputmode="numeric" pattern="[0-9A-Za-z -]{6,12}" maxlength="12"
                     placeholder="<?= e(__('auth.mfa.code_placeholder')) ?>"
                     autocomplete="one-time-code" autofocus required dir="ltr">
              <div class="n-input-focus-border"></div>
            </div>
            <div class="form-text"><?= e(__('auth.mfa.help')) ?></div>
          </div>
          <button type="submit" class="btn btn-primary btn-lg w-100 n-btn-animated">
            <span class="n-btn-text"><?= e(__('auth.mfa.submit')) ?></span>
            <span class="n-btn-icon"><?= icon('arrow-forward', 'n-flip-rtl') ?></span>
          </button>
        </form>

        <form method="post" action="/login/mfa/cancel" class="mt-3 text-center">
          <?= CsrfMiddleware::field() ?>
          <button type="submit" class="btn btn-link btn-sm"><?= e(__('auth.mfa.cancel')) ?></button>
        </form>
      </div>
    </main>
  </div>

  <script src="/assets/vendor/jquery/jquery.min.js"></script>
  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="<?= e(assetUrl('/assets/js/app.js')) ?>"></script>
</body>
</html>

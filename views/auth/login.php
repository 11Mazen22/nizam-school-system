<?php
/**
 * @var string|null $error
 * @var string|null $notice
 */
use App\Middleware\CsrfMiddleware;
use App\Repositories\SchoolRepository;
$notice ??= null;

$school = (new SchoolRepository())->full();
$schoolName = $school === null
    ? __('app.name')
    : (currentLocale() === 'ar' ? $school['name_ar'] : $school['name']);
?>
<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="/assets/img/favicon-32.png">
<link rel="apple-touch-icon" href="/assets/img/favicon-180.png">
<title><?= e(__('auth.login.title')) ?> — <?= e($schoolName) ?></title>
<?php if (currentDirection() === 'rtl'): ?>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
<?php else: ?>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="n-auth-body">
  <div class="n-auth-shell">
    <aside class="n-auth-side">
      <div class="n-auth-blob n-auth-blob-1"></div>
      <div class="n-auth-blob n-auth-blob-2"></div>
      <div class="n-auth-blob n-auth-blob-3"></div>
      
      <div class="n-auth-side-top">
        <?php if (!empty($school['logo_path'])): ?>
          <div class="nizam-brand-mark nizam-brand-mark-logo n-auth-mark">
            <img src="/logo" alt="">
          </div>
          <div>
            <div class="n-auth-app-name-lg"><?= e($schoolName) ?></div>
            <div class="n-auth-app-subtitle"><?= e(__('app.name')) ?> — <?= e(__('auth.login.system_subtitle')) ?></div>
          </div>
        <?php else: ?>
          <div class="nizam-brand-mark n-auth-mark n-auth-mark-animated">
            <?= icon('sparkle') ?>
            <div class="n-auth-mark-glow"></div>
          </div>
          <div>
            <div class="n-auth-app-name-lg"><?= e($schoolName) ?></div>
            <div class="n-auth-app-subtitle"><?= e(__('auth.login.system_subtitle')) ?></div>
          </div>
        <?php endif; ?>
      </div>
      
      <div class="n-auth-side-mid">
        <h2 class="n-auth-tagline"><?= e(__('auth.login.tagline')) ?></h2>
        <p class="n-auth-tagline-sub"><?= e(__('auth.login.tagline_description')) ?></p>
        <ul class="n-auth-features">
          <li>
            <div class="n-auth-feature-icon"><?= icon('users') ?></div>
            <div class="n-auth-feature-text">
              <strong><?= e(__('auth.login.feature1_title')) ?></strong>
              <span><?= e(__('auth.login.feature1')) ?></span>
            </div>
          </li>
          <li>
            <div class="n-auth-feature-icon"><?= icon('layers') ?></div>
            <div class="n-auth-feature-text">
              <strong><?= e(__('auth.login.feature2_title')) ?></strong>
              <span><?= e(__('auth.login.feature2')) ?></span>
            </div>
          </li>
          <li>
            <div class="n-auth-feature-icon"><?= icon('chart') ?></div>
            <div class="n-auth-feature-text">
              <strong><?= e(__('auth.login.feature3_title')) ?></strong>
              <span><?= e(__('auth.login.feature3')) ?></span>
            </div>
          </li>
          <li>
            <div class="n-auth-feature-icon"><?= icon('shield-check') ?></div>
            <div class="n-auth-feature-text">
              <strong><?= e(__('auth.login.feature4_title')) ?></strong>
              <span><?= e(__('auth.login.feature4')) ?></span>
            </div>
          </li>
        </ul>
      </div>
      
      <div class="n-auth-side-foot">
        <div class="n-auth-stats">
          <div class="n-auth-stat-item">
            <div class="n-auth-stat-icon"><?= icon('building') ?></div>
            <span><?= e(__('auth.login.trusted_by_schools')) ?></span>
          </div>
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
          <div class="n-auth-welcome-icon"><?= icon('sparkle') ?></div>
          <h1><?= e(__('auth.login.welcome_title', ['school' => $schoolName])) ?></h1>
          <p class="n-auth-sub"><?= e(__('auth.login.subtitle')) ?></p>
        </div>

        <?php if ($notice !== null): ?>
          <div class="alert alert-success d-flex align-items-center gap-2 n-auth-alert">
            <?= icon('check-circle', 'n-icon-lg flex-shrink-0') ?>
            <span><?= e($notice) ?></span>
          </div>
        <?php endif; ?>
        <?php if ($error !== null): ?>
          <div class="alert alert-danger d-flex align-items-center gap-2 n-auth-alert">
            <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="post" action="/login" class="n-auth-form">
          <?= CsrfMiddleware::field() ?>
          
          <div class="n-form-group">
            <label class="n-form-label" for="username">
              <span class="n-form-label-icon"><?= icon('users') ?></span>
              <span><?= e(__('auth.login.username')) ?></span>
            </label>
            <div class="n-input-enhanced">
              <div class="n-input-icon-prefix"><?= icon('users') ?></div>
              <input type="text" class="form-control n-input-premium" id="username" name="username" 
                     placeholder="<?= e(__('auth.login.username_placeholder')) ?>"
                     autofocus required autocomplete="username">
              <div class="n-input-focus-border"></div>
            </div>
          </div>
          
          <div class="n-form-group">
            <label class="n-form-label" for="password">
              <span class="n-form-label-icon"><?= icon('lock') ?></span>
              <span><?= e(__('auth.login.password')) ?></span>
            </label>
            <div class="n-input-enhanced">
              <div class="n-input-icon-prefix"><?= icon('lock') ?></div>
              <input type="password" class="form-control n-input-premium" id="password" name="password"
                     placeholder="<?= e(__('auth.login.password_placeholder')) ?>"
                     required autocomplete="current-password">
              <div class="n-input-focus-border"></div>
            </div>
          </div>
          
          <button type="submit" class="btn btn-primary btn-lg w-100 n-btn-animated">
            <span class="n-btn-text"><?= e(__('auth.login.submit')) ?></span>
            <span class="n-btn-icon"><?= icon('arrow-forward', 'n-flip-rtl') ?></span>
            <div class="n-btn-shine"></div>
          </button>
        </form>
        
        <div class="mt-3 text-center">
          <a href="/setup/recover-admin" class="btn btn-outline-warning btn-sm d-inline-flex align-items-center gap-2">
            <?= icon('shield-alert') ?>
            <span><?= e(__('auth.login.recover_admin')) ?></span>
          </a>
          <p class="text-muted small mt-2 mb-0"><?= e(__('auth.login.recover_admin_hint')) ?></p>
        </div>
        
        <div class="n-auth-footer">
          <div class="n-auth-security">
            <?= icon('shield-check') ?>
            <span><?= e(__('auth.login.secure_connection')) ?></span>
          </div>
        </div>
      </div>
    </main>
  </div>
  
  <script src="/assets/vendor/jquery/jquery.min.js"></script>
  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/js/app.js"></script>
</body>
</html>

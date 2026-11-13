<?php
/**
 * Shared document for the four terminal error screens (403/404/419/500).
 *
 * These render outside the authenticated shell -- there may be no session,
 * no school row and no navigation to show -- so they are intentionally
 * self-contained. They still load the full design system (app + theme +
 * components) so an error looks like part of the product rather than a bare
 * browser page. Previously they linked only app.css and leaned on Bootstrap
 * utilities that were never loaded, so centring, spacing and buttons were
 * inert.
 *
 * @var string $errorCode    HTTP status shown as the display numeral
 * @var string $errorIcon    icon() name
 * @var string $errorTone    primary|danger|warning
 * @var string $errorTitle   translated heading
 * @var string $errorBody    translated explanation
 * @var bool   $errorBack    true to offer "go back" instead of the dashboard
 */
$errorTone = $errorTone ?? 'primary';
$errorBack = $errorBack ?? false;
$appName = __('app.name');
?>
<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#4f46e5">
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" href="/assets/img/favicon-32.png">
  <link rel="apple-touch-icon" href="/assets/img/favicon-180.png">
  <title><?= e($errorTitle) ?> — <?= e($appName) ?></title>
  <link rel="stylesheet" href="<?= e(assetUrl('/assets/css/fonts.css')) ?>">
  <link rel="stylesheet" href="<?= e(assetUrl('/assets/css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(assetUrl('/assets/css/theme.css')) ?>">
  <link rel="stylesheet" href="<?= e(assetUrl('/assets/css/components.css')) ?>">
</head>
<body class="n-error-page">
  <main class="n-error-shell">
    <div class="n-error-card n-reveal">
      <div class="n-error-code" aria-hidden="true"><?= e($errorCode) ?></div>
      <div class="n-error-icon n-error-icon-<?= e($errorTone) ?>"><?= icon($errorIcon, 'n-icon-xl') ?></div>
      <h1 class="n-error-title"><?= e($errorTitle) ?></h1>
      <p class="n-error-text"><?= e($errorBody) ?></p>
      <?php if ($errorBack): ?>
        <button type="button" class="btn btn-primary n-error-action" data-history-back>
          <?= icon('chevron-left', 'n-flip-rtl') ?>
          <span><?= e(__('common.go_back')) ?></span>
        </button>
      <?php else: ?>
        <a href="/dashboard" class="btn btn-primary n-error-action">
          <?= icon('home') ?>
          <span><?= e(__('common.back_to_dashboard')) ?></span>
        </a>
      <?php endif; ?>
    </div>
  </main>
  <?php if ($errorBack): ?>
    <script src="<?= e(assetUrl('/assets/js/app.js')) ?>"></script>
  <?php endif; ?>
</body>
</html>

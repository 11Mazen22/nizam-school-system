<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(__('error.404.title')) ?> — <?= e(__('app.name')) ?></title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
  <div class="d-flex align-items-center justify-content-center" style="min-height: 100vh; padding: 24px;">
    <div class="text-center" style="max-width: 460px;">
      <div class="d-inline-flex align-items-center justify-content-center mb-4"
           style="width: 88px; height: 88px; border-radius: 50%; background: var(--n-surface-sunken);">
        <?= icon('search', 'n-icon-xl') ?>
      </div>
      <h1 class="h3 mb-2"><?= e(__('error.404.title')) ?></h1>
      <p class="text-muted mb-4"><?= e(__('error.404.body')) ?></p>
      <a href="/dashboard" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <?= icon('home') ?>
        <?= e(__('common.back_to_dashboard')) ?>
      </a>
    </div>
  </div>
</body>
</html>

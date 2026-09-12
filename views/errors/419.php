<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(__('error.csrf')) ?> — <?= e(__('app.name')) ?></title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
  <div class="d-flex align-items-center justify-content-center" style="min-height: 100vh; padding: 24px;">
    <div class="text-center" style="max-width: 460px;">
      <div class="d-inline-flex align-items-center justify-content-center mb-4"
           style="width: 88px; height: 88px; border-radius: 50%; background: var(--n-warning-soft); color: var(--n-warning);">
        <?= icon('alert-circle', 'n-icon-xl') ?>
      </div>
      <h1 class="h3 mb-2"><?= e(__('error.csrf')) ?></h1>
      <p class="text-muted mb-4"><?= e(__('error.419.body')) ?></p>
      <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-history-back>
        <?= icon('chevron-left', 'n-flip-rtl') ?>
        <?= e(__('common.go_back')) ?>
      </button>
    </div>
  </div>
  <script src="/assets/js/app.js"></script>
</body>
</html>

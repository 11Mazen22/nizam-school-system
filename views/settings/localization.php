<?php
/** @var string $defaultLanguage */
$pageTitle = __('settings.title');
$activeNav = 'settings';
$activeSettingsTab = 'localization';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('settings', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('settings.title')) ?>
    </h1>
  </div>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="card" style="max-width: 480px;">
  <div class="card-body">
    <form method="post" action="/settings/localization">
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label" for="default_language"><?= e(__('settings.default_language')) ?></label>
        <select class="form-select" id="default_language" name="default_language" required>
          <option value="ar" <?= $defaultLanguage === 'ar' ? 'selected' : '' ?>><?= e(__('common.language_ar')) ?></option>
          <option value="en" <?= $defaultLanguage === 'en' ? 'selected' : '' ?>><?= e(__('common.language_en')) ?></option>
        </select>
        <div class="form-text"><?= e(__('settings.default_language_help')) ?></div>
      </div>
      <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
        <?= icon('check') ?>
        <span><?= e(__('common.save')) ?></span>
      </button>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

<?php
/** @var ?string $error @var ?array $school */
$pageTitle = __('settings.title');
$activeNav = 'settings';
$activeSettingsTab = 'profile';
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

<?php if ($error !== null): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2">
    <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<div class="card" style="max-width: 720px;">
  <div class="card-body">
    <form method="post" action="/settings/profile" enctype="multipart/form-data">
      <?= CsrfMiddleware::field() ?>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="name"><?= e(__('setup.school.name')) ?></label>
          <input type="text" class="form-control" id="name" name="name" value="<?= e($school['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="name_ar"><?= e(__('setup.school.name_ar')) ?></label>
          <input type="text" class="form-control" id="name_ar" name="name_ar" value="<?= e($school['name_ar'] ?? '') ?>" dir="rtl" required>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="address"><?= e(__('setup.school.address')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="address" name="address" value="<?= e($school['address'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="phone"><?= e(__('setup.school.phone')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="phone" name="phone" value="<?= e($school['phone'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="footer_text"><?= e(__('settings.report_footer_text')) ?> (<?= e(__('common.optional')) ?>)</label>
        <input type="text" class="form-control" id="footer_text" name="footer_text" value="<?= e(\App\Services\SettingsService::get('reports.school_footer_text', '') ?? '') ?>">
        <div class="form-text"><?= e(__('settings.report_footer_help')) ?></div>
      </div>

      <hr>
      <label class="form-label d-block"><?= e(__('settings.logo')) ?></label>
      <?php if (!empty($school['logo_path'])): ?>
        <div class="d-flex align-items-center gap-3 mb-2">
          <img src="/logo" alt="" style="height:64px; max-width:160px; object-fit:contain;" class="border rounded p-1 bg-white">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
            <label class="form-check-label" for="remove_logo"><?= e(__('settings.logo_remove')) ?></label>
          </div>
        </div>
      <?php endif; ?>
      <input type="file" class="form-control mb-1" name="logo" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
      <div class="form-text mb-3"><?= e(__('settings.logo_help')) ?></div>

      <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
        <?= icon('check') ?>
        <span><?= e(__('common.save')) ?></span>
      </button>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

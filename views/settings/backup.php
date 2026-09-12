<?php
/** @var ?string $error @var bool $cloudStorage @var string $defaultFolder */
$pageTitle = __('settings.title');
$activeNav = 'settings';
$activeSettingsTab = 'backup';
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

<?php if ($cloudStorage): ?>
  <div class="card" style="max-width: 640px;">
    <div class="card-body d-flex align-items-center gap-2">
      <?= icon('database', 'n-icon-lg text-body-secondary flex-shrink-0') ?>
      <p class="mb-0"><?= e(__('settings.backup_cloud_storage_notice')) ?></p>
    </div>
  </div>
<?php else: ?>
  <div class="card" style="max-width: 640px;">
    <div class="card-body">
      <form method="post" action="/settings/backup">
        <?= CsrfMiddleware::field() ?>
        <div class="mb-3">
          <label class="form-label" for="default_folder"><?= e(__('settings.default_folder')) ?></label>
          <input type="text" class="form-control mono" id="default_folder" name="default_folder" value="<?= e($defaultFolder) ?>" required>
          <div class="form-text"><?= e(__('settings.default_folder_help')) ?></div>
        </div>
        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
          <?= icon('check') ?>
          <span><?= e(__('common.save')) ?></span>
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>

<p class="text-muted small mt-3">
  <?= e(__('settings.backup_run_hint')) ?>
  <a href="/backups"><?= e(__('backups.title')) ?></a>.
</p>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

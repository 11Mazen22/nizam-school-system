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

<div class="n-settings-card">
  <div class="n-settings-card-head">
    <h2><?= e(__('settings.tab.backup')) ?></h2>
    <p><?= e(__('settings.desc.backup')) ?></p>
  </div>
  <div class="n-settings-card-body">
    <?php if ($cloudStorage): ?>
      <div class="alert alert-info d-flex align-items-center gap-2 mb-0">
        <?= icon('database', 'n-icon-lg flex-shrink-0') ?>
        <span><?= e(__('settings.backup_cloud_storage_notice')) ?></span>
      </div>
    <?php else: ?>
      <form method="post" action="/settings/backup" data-unsaved-guard data-loading-submit>
        <?= CsrfMiddleware::field() ?>
        <div class="mb-0">
          <label class="form-label" for="default_folder"><?= e(__('settings.default_folder')) ?></label>
          <input type="text" class="form-control mono" id="default_folder" name="default_folder" value="<?= e($defaultFolder) ?>" required>
          <div class="form-text"><?= e(__('settings.default_folder_help')) ?></div>
        </div>
        <div class="n-settings-save">
          <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
            <?= icon('check') ?>
            <span><?= e(__('common.save')) ?></span>
          </button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<p class="text-muted small mt-3">
  <?= e(__('settings.backup_run_hint')) ?>
  <a href="/backups"><?= e(__('backups.title')) ?></a>.
</p>

<?php require __DIR__ . '/_end.php'; ?>
<?php require dirname(__DIR__) . '/layout/end.php'; ?>

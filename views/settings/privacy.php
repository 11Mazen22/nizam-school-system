<?php
/**
 * @var int $activityLogDays
 * @var int $failedBackupDays
 * @var int $successfulBackupDays
 * @var int $backupsAlwaysKept
 * @var int $sessionHours
 * @var int $mfaEnabledUsers
 */
$pageTitle = __('settings.title');
$activeNav = 'settings';
$activeSettingsTab = 'privacy';
require dirname(__DIR__) . '/layout/start.php';
?>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="n-settings-card">
  <div class="n-settings-card-head">
    <h2><?= e(__('privacy.title')) ?></h2>
    <p><?= e(__('privacy.subtitle')) ?></p>
  </div>
  <div class="n-settings-card-body">
    <div class="alert alert-info small"><?= e(__('privacy.notice')) ?></div>

    <h3 class="h5 mt-4"><?= e(__('privacy.stored_title')) ?></h3>
    <ul class="small">
      <li><?= e(__('privacy.stored_students')) ?></li>
      <li><?= e(__('privacy.stored_staff')) ?></li>
      <li><?= e(__('privacy.stored_academic')) ?></li>
      <li><?= e(__('privacy.stored_finance')) ?></li>
      <li><?= e(__('privacy.stored_audit')) ?></li>
    </ul>

    <h3 class="h5 mt-4"><?= e(__('privacy.retention_title')) ?></h3>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th><?= e(__('privacy.retention_item')) ?></th>
            <th><?= e(__('privacy.retention_window')) ?></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= e(__('privacy.retention_activity')) ?></td>
            <td><?= (int) $activityLogDays ?> <?= e(__('privacy.days')) ?></td>
          </tr>
          <tr>
            <td><?= e(__('privacy.retention_failed_backups')) ?></td>
            <td><?= (int) $failedBackupDays ?> <?= e(__('privacy.days')) ?></td>
          </tr>
          <tr>
            <td><?= e(__('privacy.retention_successful_backups')) ?></td>
            <td>
              <?= (int) $successfulBackupDays ?> <?= e(__('privacy.days')) ?>
              (<?= e(__('privacy.keep_at_least', ['n' => $backupsAlwaysKept])) ?>)
            </td>
          </tr>
          <tr>
            <td><?= e(__('privacy.retention_sessions')) ?></td>
            <td><?= (int) $sessionHours ?> <?= e(__('privacy.hours')) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <h3 class="h5 mt-4"><?= e(__('privacy.access_title')) ?></h3>
    <p class="small mb-2"><?= e(__('privacy.access_body')) ?></p>
    <p class="small mb-0">
      <span class="badge text-bg-light border"><?= e(__('privacy.mfa_count', ['count' => $mfaEnabledUsers])) ?></span>
    </p>

    <h3 class="h5 mt-4"><?= e(__('privacy.requests_title')) ?></h3>
    <p class="small mb-0"><?= e(__('privacy.requests_body')) ?></p>
  </div>
</div>

<?php require __DIR__ . '/_end.php'; ?>
<?php require dirname(__DIR__) . '/layout/end.php'; ?>

<?php
/**
 * Pending offline submissions.
 *
 * The rows are rendered by public/assets/js/offline-queue.js from this
 * browser's IndexedDB -- the server cannot read another machine's queue, and
 * pretending otherwise would be dishonest. This page is the shell: heading,
 * explanation, empty state and the buttons the script binds to.
 *
 * @var string $activeSettingsTab
 */
$pageTitle = __('settings.title');
$activeNav = 'settings';
$activeSettingsTab = 'offline';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';

$queueStrings = [
    'retry' => __('settings.offline.retry'),
    'discard' => __('settings.offline.discard'),
    'discardConfirm' => __('settings.offline.discard_confirm'),
    'queuedAt' => __('settings.offline.queued_at'),
    'fields' => __('settings.offline.fields'),
    'empty' => __('settings.offline.empty'),
];
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('inbox', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('settings.title')) ?>
    </h1>
  </div>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="n-settings-card">
  <div class="n-settings-card-head">
    <h2><?= e(__('settings.tab.offline')) ?></h2>
    <p><?= e(__('settings.desc.offline')) ?></p>
  </div>
  <div class="n-settings-card-body">
    <p class="n-queue-help"><?= e(__('settings.offline.help')) ?></p>

    <div id="offline-queue-panel" class="n-queue-panel"
         data-queue-i18n="<?= e(json_encode($queueStrings, JSON_UNESCAPED_UNICODE)) ?>">
      <div class="n-queue-panel-actions">
        <button type="button" class="btn btn-primary" data-queue-send-all>
          <?= e(__('settings.offline.send_all')) ?>
        </button>
      </div>
      <p class="n-queue-empty" data-queue-empty hidden><?= e(__('settings.offline.empty')) ?></p>
      <ul class="n-queue-list" data-queue-list></ul>
    </div>

    <noscript>
      <div class="alert alert-info mt-3 mb-0"><?= e(__('settings.offline.noscript')) ?></div>
    </noscript>
  </div>
</div>

<?php require __DIR__ . '/_end.php'; ?>
<?php require dirname(__DIR__) . '/layout/end.php'; ?>

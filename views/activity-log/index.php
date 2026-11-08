<?php
/** @var array $entries @var int $page @var int $lastPage @var int $total @var bool $scopedToSelf */
$pageTitle = __('activity_log.title');
$activeNav = 'activity-log';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
?>

<!-- POWERFUL PAGE HEADER -->
<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper">
          <?= icon('activity', 'n-grades-icon') ?>
        </div>
        <?= e(__('activity_log.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('activity_log.description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= (int)$total ?></span>
          <span class="n-stat-mini-label"><?= e(__('activity_log.total_entries')) ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($scopedToSelf): ?>
  <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
    <?= icon('info', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e(__('activity_log.scoped_notice')) ?></span>
  </div>
<?php endif; ?>

<?php if (empty($entries)): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('activity', 'n-icon-xl') ?></div>
    <h2 class="n-empty-title"><?= e(__('activity_log.empty_title')) ?></h2>
    <p class="n-empty-description"><?= e(__('activity_log.empty_description')) ?></p>
  </div>
<?php else: ?>
  <div class="n-data-panel">
    <div class="n-section-head">
      <?php $logSearchId = 'log-row-search'; ?>
      <div class="n-search-field" style="max-width: 260px;">
        <?= icon('search') ?>
        <label class="visually-hidden" for="<?= $logSearchId ?>"><?= e(__('common.search')) ?></label>
        <input type="search" id="<?= $logSearchId ?>" class="form-control form-control-sm" data-table-filter="#log-timeline"
               placeholder="<?= e(__('common.search')) ?>">
      </div>
    </div>
    <ul class="n-activity-list" id="log-timeline">
      <?php foreach ($entries as $entry): ?>
        <li>
          <span class="n-activity-marker"><?= icon('activity') ?></span>
          <span class="n-activity-copy">
            <strong><?= e(activityLabel($entry['action'])) ?></strong>
            <small><?= e($entry['description'] ?? '') ?></small>
            <small><?= e($entry['full_name'] ?? __('activity_log.unknown_user')) ?></small>
          </span>
          <time class="mono" dir="ltr"><?= e($entry['created_at']) ?></time>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <?php if ($lastPage > 1): ?>
    <nav aria-label="Activity log pagination" class="mt-4">
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="/activity-log?page=<?= $page - 1 ?>">&laquo;</a>
        </li>
        <?php for ($p = 1; $p <= $lastPage; $p++): ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="/activity-log?page=<?= $p ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>">
          <a class="page-link" href="/activity-log?page=<?= $page + 1 ?>">&raquo;</a>
        </li>
      </ul>
    </nav>
    <p class="text-center text-muted small mt-2"><?= (int) $total ?> <?= e(__('activity_log.total_suffix')) ?></p>
  <?php endif; ?>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

<?php
/** @var array $entries @var int $page @var int $lastPage @var int $total @var bool $scopedToSelf @var string $q */
$pageTitle = __('activity_log.title');
$activeNav = 'activity-log';
// Uses the shared layout hero: the layout renders the single h1, icon and
// eyebrow, so this screen only contributes its subtitle and total.
$pageSubtitle = __('activity_log.description');
$pageActions = '<div class="n-hero-stat"><b>' . (int) $total . '</b><span>'
    . e(__('activity_log.total_entries')) . '</span></div>';
require dirname(__DIR__) . '/layout/start.php';
?>


<?php if ($scopedToSelf): ?>
  <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
    <?= icon('info', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e(__('activity_log.scoped_notice')) ?></span>
  </div>
<?php endif; ?>

<form method="get" action="/activity-log" class="n-filter-panel no-print" data-live-filter data-live-target="#log-results">
  <div class="n-filter-row">
    <div class="n-filter-field n-search-field flex-grow-1">
      <?= icon('search') ?>
      <label class="visually-hidden" for="log-row-search"><?= e(__('common.search')) ?></label>
      <input id="log-row-search" type="search" class="form-control" name="q" value="<?= e($q ?? '') ?>"
             placeholder="<?= e(__('common.search')) ?>" autocomplete="off">
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('search') ?><span><?= e(__('common.search')) ?></span></button>
    <?php if (($q ?? '') !== ''): ?>
      <a href="/activity-log" class="btn btn-outline-secondary"><?= icon('close') ?><span><?= e(__('common.clear_filters')) ?></span></a>
    <?php endif; ?>
  </div>
</form>

<div id="log-results">
<?php if (empty($entries)): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('activity', 'n-icon-xl') ?></div>
    <h2 class="n-empty-title"><?= e(($q ?? '') !== '' ? __('common.no_results') : __('activity_log.empty_title')) ?></h2>
    <p class="n-empty-description"><?= e(__('activity_log.empty_description')) ?></p>
  </div>
<?php else: ?>
  <div class="n-data-panel">
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
          <a class="page-link" href="/activity-log?<?= e(http_build_query(['page' => max(1, $page - 1), 'q' => $q])) ?>">&laquo;</a>
        </li>
        <?php for ($p = 1; $p <= $lastPage; $p++): ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="/activity-log?<?= e(http_build_query(['page' => $p, 'q' => $q])) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>">
          <a class="page-link" href="/activity-log?<?= e(http_build_query(['page' => min($lastPage, $page + 1), 'q' => $q])) ?>">&raquo;</a>
        </li>
      </ul>
    </nav>
    <p class="text-center text-muted small mt-2"><?= (int) $total ?> <?= e(__('activity_log.total_suffix')) ?></p>
  <?php endif; ?>
<?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

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
  <div class="n-empty-powerful">
    <div class="n-empty-powerful-bg">
      <div class="n-empty-blob n-empty-blob-1"></div>
      <div class="n-empty-blob n-empty-blob-2"></div>
      <div class="n-empty-blob n-empty-blob-3"></div>
    </div>
    <div class="n-empty-powerful-content">
      <div class="n-empty-powerful-icon">
        <?= icon('activity', 'n-icon-massive') ?>
      </div>
      <h2 class="n-empty-powerful-title"><?= e(__('activity_log.empty_title')) ?></h2>
      <p class="n-empty-powerful-description"><?= e(__('activity_log.empty_description')) ?></p>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th><?= e(__('activity_log.when')) ?></th>
              <th><?= e(__('activity_log.who')) ?></th>
              <th><?= e(__('activity_log.what')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <tr>
                <td class="text-nowrap mono small text-muted"><?= e($entry['created_at']) ?></td>
                <td><?= e($entry['full_name'] ?? __('activity_log.unknown_user')) ?></td>
                <td>
                  <div class="fw-semibold"><?= e(activityLabel($entry['action'])) ?></div>
                  <?php if (!empty($entry['description'])): ?>
                    <div class="text-muted small"><?= e($entry['description']) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
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

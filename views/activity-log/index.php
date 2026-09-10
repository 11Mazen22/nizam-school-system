<?php
/** @var array $entries @var int $page @var int $lastPage @var int $total @var bool $scopedToSelf */
$pageTitle = __('activity_log.title');
$activeNav = 'activity-log';
require dirname(__DIR__) . '/layout/start.php';
?>

<?php if ($scopedToSelf): ?>
  <div class="alert alert-info"><?= e(__('activity_log.scoped_notice')) ?></div>
<?php endif; ?>

<?php if (empty($entries)): ?>
  <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
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
            <td class="text-nowrap mono"><?= e($entry['created_at']) ?></td>
            <td><?= e($entry['full_name'] ?? __('activity_log.unknown_user')) ?></td>
            <td><?= e($entry['description'] ?? $entry['action']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($lastPage > 1): ?>
    <nav aria-label="Activity log pagination">
      <ul class="pagination justify-content-center mt-3">
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
    <p class="text-center text-muted small"><?= (int) $total ?> <?= e(__('activity_log.total_suffix')) ?></p>
  <?php endif; ?>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

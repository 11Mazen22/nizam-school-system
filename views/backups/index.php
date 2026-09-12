<?php
/**
 * @var array $backups
 * @var int $page
 * @var int $limit
 * @var int $total
 */
$pageTitle = __('backups.title');
$activeNav = 'backups';
$pageScripts = ['/assets/js/backups-restore.js'];
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$lastPage = max(1, (int) ceil($total / $limit));
?>

<!-- POWERFUL PAGE HEADER -->
<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper">
          <?= icon('shield-check', 'n-grades-icon') ?>
        </div>
        <?= e(__('backups.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('backups.description')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= (int)$total ?></span>
          <span class="n-stat-mini-label"><?= e(__('backups.total_backups')) ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 n-reveal-group mb-4">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <?= icon('database') ?>
        <span><?= e(__('backups.create')) ?></span>
      </div>
      <div class="card-body">
        <form method="post" action="/backups" data-confirm="<?= e(__('backups.confirm_create')) ?>">
          <?= CsrfMiddleware::field() ?>
          <div class="mb-3">
            <label class="form-label"><?= e(__('common.notes')) ?> <span class="text-muted"><?= e(__('common.optional')) ?></span></label>
            <input type="text" name="notes" class="form-control" maxlength="255">
          </div>
          <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
            <?= icon('download') ?>
            <span><?= e(__('backups.create')) ?></span>
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100 border-danger">
      <div class="card-header bg-danger text-white fw-bold d-flex align-items-center gap-2">
        <?= icon('upload') ?>
        <span><?= e(__('backups.restore')) ?></span>
      </div>
      <div class="card-body">
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
          <?= icon('alert-triangle', 'n-icon-lg flex-shrink-0') ?>
          <strong><?= e(__('backups.restore_warning')) ?></strong>
        </div>
        <form method="post" action="/backups/restore" enctype="multipart/form-data" id="restoreForm">
          <?= CsrfMiddleware::field() ?>
          <div class="mb-3">
            <label class="form-label"><?= e(__('backups.upload_prompt')) ?></label>
            <input type="file" name="backup_file" class="form-control" accept=".sql" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="restoreConfirmPhrase"><?= e(__('backups.confirm_phrase_prompt')) ?></label>
            <input type="text" id="restoreConfirmPhrase" class="form-control" autocomplete="off" dir="ltr">
            <input type="hidden" name="confirm_phrase" id="restoreConfirmPhraseValue">
          </div>
          <button type="submit" class="btn btn-danger d-flex align-items-center gap-2" id="restoreSubmit" disabled>
            <?= icon('upload') ?>
            <span><?= e(__('backups.restore')) ?></span>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header d-flex align-items-center gap-2">
    <?= icon('clock') ?>
    <span><?= e(__('backups.history')) ?></span>
    <span class="badge text-bg-light border ms-auto"><?= (int) $total ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= e(__('backups.table.created_at')) ?></th>
            <th><?= e(__('backups.table.filename')) ?></th>
            <th><?= e(__('backups.table.size')) ?></th>
            <th><?= e(__('backups.table.type')) ?></th>
            <th><?= e(__('backups.table.status')) ?></th>
            <th><?= e(__('backups.table.created_by')) ?></th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($backups)): ?>
            <tr>
              <td colspan="7" class="p-0">
                <div class="n-empty py-4">
                  <div class="n-empty-icon"><?= icon('database') ?></div>
                  <p class="mb-0"><?= e(__('common.no_results')) ?></p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($backups as $row): ?>
              <tr>
                <td class="text-nowrap" dir="ltr"><?= e($row['created_at']) ?></td>
                <td class="font-monospace small" <?= !empty($row['notes']) ? 'title="' . e($row['notes']) . '"' : '' ?>><?= e($row['filename']) ?></td>
                <td dir="ltr">
                  <?php
                    $bytes = (int) $row['file_size'];
                    echo $bytes >= 1048576
                        ? e(number_format($bytes / 1048576, 1)) . ' MB'
                        : ($bytes > 0 ? e(number_format($bytes / 1024, 1)) . ' KB' : '-');
                  ?>
                </td>
                <td>
                  <?php
                    $typeClass = $row['type'] === 'pre_restore' ? 'warning' : 'secondary';
                    if ($row['type'] === 'manual') $typeClass = 'primary';
                  ?>
                  <span class="badge text-bg-<?= $typeClass ?>"><?= e(__('backups.type.' . $row['type'])) ?></span>
                </td>
                <td>
                  <?php if ($row['status'] === 'success'): ?>
                    <span class="badge text-bg-success"><?= e(__('backups.status.success')) ?></span>
                  <?php else: ?>
                    <span class="badge text-bg-danger"><?= e(__('backups.status.failed')) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= e($row['created_by_name'] ?? '-') ?></td>
                <td class="text-end">
                  <?php if ($row['status'] === 'success'): ?>
                    <a href="/backups/<?= e((string)$row['id']) ?>/download" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                      <?= icon('download', 'n-icon-sm') ?>
                      <span><?= e(__('backups.action.download')) ?></span>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($lastPage > 1): ?>
  <nav aria-label="Backups pagination">
    <ul class="pagination justify-content-center mt-3">
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="/backups?page=<?= $page - 1 ?>">&laquo;</a>
      </li>
      <?php for ($p = 1; $p <= $lastPage; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
          <a class="page-link" href="/backups?page=<?= $p ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>">
        <a class="page-link" href="/backups?page=<?= $page + 1 ?>">&raquo;</a>
      </li>
    </ul>
  </nav>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

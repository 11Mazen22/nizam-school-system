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
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$lastPage = max(1, (int) ceil($total / $limit));
?>

<div class="row g-4">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold">
        <?= e(__('backups.create')) ?>
      </div>
      <div class="card-body">
        <form method="post" action="/backups" data-confirm="<?= e(__('backups.confirm_create')) ?>">
          <?= CsrfMiddleware::field() ?>
          <div class="mb-3">
            <label class="form-label"><?= e(__('common.notes')) ?> <span class="text-muted"><?= e(__('common.optional')) ?></span></label>
            <input type="text" name="notes" class="form-control" maxlength="255">
          </div>
          <button type="submit" class="btn btn-primary">
            <?= e(__('backups.create')) ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100 border-danger">
      <div class="card-header bg-danger text-white fw-bold">
        <?= e(__('backups.restore')) ?>
      </div>
      <div class="card-body">
        <div class="alert alert-warning">
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
          <button type="submit" class="btn btn-danger" id="restoreSubmit" disabled>
            <?= e(__('backups.restore')) ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header bg-white fw-bold">
    <?= e(__('backups.history')) ?>
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
              <td colspan="7" class="text-center text-muted py-4">
                <?= e(__('common.no_results')) ?>
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
                    <a href="/backups/<?= e((string)$row['id']) ?>/download" class="btn btn-sm btn-outline-secondary">
                      <?= e(__('backups.action.download')) ?>
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

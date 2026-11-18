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
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('backups.description')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value"><?= (int) ($totalAll ?? $total) ?></span>
          <span class="n-stat-mini-label"><?= e(__('backups.total_backups')) ?></span>
        </div>
      </div>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$lastPage = max(1, (int) ceil($total / $limit));
?>

<!-- POWERFUL PAGE HEADER -->


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
    <div class="card h-100 n-danger-zone">
      <div class="card-header fw-bold d-flex align-items-center gap-2">
        <?= icon('shield-alert') ?>
        <span><?= e(__('backups.restore')) ?></span>
      </div>
      <div class="card-body">
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
          <?= icon('alert-triangle', 'n-icon-lg flex-shrink-0') ?>
          <strong><?= e(__('backups.restore_warning')) ?></strong>
        </div>
        <div class="n-protection-summary mb-3" role="note">
          <?= icon('shield-check') ?>
          <span><?= e(__('backups.restore_safety_notice')) ?></span>
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

<?php if ((int) ($totalAll ?? $total) > 0): ?>
<form method="get" action="/backups" class="n-filter-panel n-list-search no-print" data-live-filter data-live-target="#backup-results">
  <div class="n-filter-row">
    <div class="n-filter-field n-search-field flex-grow-1">
      <?= icon('search') ?>
      <label class="visually-hidden" for="backup-search"><?= e(__('common.search')) ?></label>
      <input id="backup-search" type="search" class="form-control" name="q"
             value="<?= e($q ?? '') ?>"
             placeholder="<?= e(__('common.search')) ?>"
             autocomplete="off">
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('search') ?><?= e(__('common.search')) ?></button>
    <?php if (($q ?? '') !== ''): ?><a href="/backups" class="btn btn-outline-secondary"><?= icon('close') ?><?= e(__('common.clear_filters')) ?></a><?php endif; ?>
  </div>
</form>
<?php endif; ?>

<div id="backup-results" data-live-results>
<div class="card mt-4">
  <div class="card-header d-flex align-items-center gap-2">
    <?= icon('clock') ?>
    <span><?= e(__('backups.history')) ?></span>
    <span class="badge text-bg-light border ms-auto"><?= (int) $total ?><?php if (($q ?? '') !== '' && (int) $total !== (int) ($totalAll ?? $total)): ?> / <?= (int) ($totalAll ?? $total) ?><?php endif; ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="backups-table" class="table table-hover align-middle mb-0 n-table-stack">
        <thead class="table-light">
          <tr>
            <th><?= e(__('backups.table.created_at')) ?></th>
            <th><?= e(__('backups.table.filename')) ?></th>
            <th><?= e(__('common.notes')) ?></th>
            <th><?= e(__('backups.table.size')) ?></th>
            <th><?= e(__('backups.table.type')) ?></th>
            <th><?= e(__('backups.table.status')) ?></th>
            <th><?= e(__('backups.table.created_by')) ?></th>
            <th class="text-end n-stack-hide"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($backups)): ?>
            <tr>
              <td colspan="8" class="p-0">
                <div class="n-empty py-4">
                  <div class="n-empty-icon"><?= icon('database') ?></div>
                  <p class="mb-0"><?= e(__('common.no_results')) ?></p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($backups as $row): ?>
              <tr>
                <td class="text-nowrap" data-label="<?= e(__('backups.table.created_at')) ?>" dir="ltr"><?= e($row['created_at']) ?></td>
                <td class="font-monospace small" data-label="<?= e(__('backups.table.filename')) ?>"><?= e($row['filename']) ?></td>
                <td class="text-muted small" data-label="<?= e(__('common.notes')) ?>"><?= e($row['notes'] ?? '') ?></td>
                <td data-label="<?= e(__('backups.table.size')) ?>" dir="ltr">
                  <?php
                    $bytes = (int) $row['file_size'];
                    echo $bytes >= 1048576
                        ? e(number_format($bytes / 1048576, 1)) . ' MB'
                        : ($bytes > 0 ? e(number_format($bytes / 1024, 1)) . ' KB' : '-');
                  ?>
                </td>
                <td data-label="<?= e(__('backups.table.type')) ?>">
                  <?php
                    $typeClass = $row['type'] === 'pre_restore' ? 'warning' : 'secondary';
                    if ($row['type'] === 'manual') $typeClass = 'primary';
                  ?>
                  <span class="badge text-bg-<?= $typeClass ?>"><?= e(__('backups.type.' . $row['type'])) ?></span>
                </td>
                <td data-label="<?= e(__('backups.table.status')) ?>">
                  <?php if ($row['status'] === 'success'): ?>
                    <span class="badge text-bg-success"><?= e(__('backups.status.success')) ?></span>
                  <?php else: ?>
                    <span class="badge text-bg-danger"><?= e(__('backups.status.failed')) ?></span>
                  <?php endif; ?>
                </td>
                <td data-label="<?= e(__('backups.table.created_by')) ?>"><?= e($row['created_by_name'] ?? '-') ?></td>
                <td class="text-end" data-label="<?= e(__('common.actions')) ?>">
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
  <?php $querySuffix = ($q ?? '') !== '' ? '&q=' . rawurlencode($q) : ''; ?>
  <nav aria-label="Backups pagination">
    <ul class="pagination justify-content-center mt-3">
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="/backups?page=<?= $page - 1 ?><?= e($querySuffix) ?>">&laquo;</a>
      </li>
      <?php for ($p = 1; $p <= $lastPage; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
          <a class="page-link" href="/backups?page=<?= $p ?><?= e($querySuffix) ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>">
        <a class="page-link" href="/backups?page=<?= $page + 1 ?><?= e($querySuffix) ?>">&raquo;</a>
      </li>
    </ul>
  </nav>
<?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

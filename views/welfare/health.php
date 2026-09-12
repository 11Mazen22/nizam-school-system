<?php
/**
 * @var array       $records
 * @var string|null $type
 */
$pageTitle = __('welfare.health_title');
$activeNav = 'welfare';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$typeColors = ['allergy' => 'danger', 'medication' => 'warning', 'clinic_visit' => 'info', 'condition' => 'secondary'];
?>

<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper"><?= icon('inbox', 'n-grades-icon') ?></div>
        <?= e(__('welfare.health_title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('welfare.health_subtitle')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <a href="/welfare/discipline" class="btn btn-outline-light ms-2"><?= icon('alert-triangle') ?> <?= e(__('welfare.discipline_title')) ?></a>
      <?php if (hasPermission('welfare.manage')): ?>
        <button class="btn btn-light ms-2" data-bs-toggle="modal" data-bs-target="#addHealth">
          <?= icon('plus') ?> <?= e(__('welfare.add_health')) ?>
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Filter -->
<form method="get" class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex gap-3 align-items-end flex-wrap">
    <div>
      <label class="form-label fw-semibold"><?= e(__('welfare.record_type')) ?></label>
      <select name="type" class="form-select form-select-sm">
        <option value=""><?= e(__('welfare.all_types')) ?></option>
        <?php foreach (['allergy', 'medication', 'clinic_visit', 'condition'] as $t): ?>
          <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(__('welfare.health_' . $t)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary btn-sm"><?= icon('filter') ?> <?= e(__('reports.apply_filters')) ?></button>
  </div>
</form>

<?php if (empty($records)): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('inbox', 'n-icon-xl') ?></div>
    <h3 class="n-empty-title"><?= e(__('welfare.no_records')) ?></h3>
  </div>
<?php else: ?>
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= e(__('welfare.date_logged')) ?></th>
            <th><?= e(__('students.full_name')) ?></th>
            <th><?= e(__('welfare.record_type')) ?></th>
            <th><?= e(__('welfare.title')) ?></th>
            <th><?= e(__('welfare.details')) ?></th>
            <th><?= e(__('welfare.logged_by')) ?></th>
            <?php if (hasPermission('welfare.manage')): ?><th></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $row): ?>
            <tr>
              <td class="text-nowrap"><?= e($row['date_logged']) ?></td>
              <td><a href="/students/<?= (int)$row['student_id'] ?>" class="fw-semibold text-decoration-none"><?= e($row['full_name']) ?></a></td>
              <td>
                <span class="badge bg-<?= $typeColors[$row['record_type']] ?? 'secondary' ?>">
                  <?= e(__('welfare.health_' . $row['record_type'])) ?>
                </span>
              </td>
              <td class="fw-semibold"><?= e($row['title']) ?></td>
              <td class="text-muted small" style="max-width:220px;"><?= e(mb_substr($row['details'], 0, 80)) ?><?= mb_strlen($row['details']) > 80 ? '…' : '' ?></td>
              <td class="text-muted small"><?= e($row['logger_name'] ?? '—') ?></td>
              <?php if (hasPermission('welfare.manage')): ?>
                <td>
                  <form method="post" action="/welfare/health/<?= (int)$row['id'] ?>/delete"
                        data-confirm="<?= e(__('common.confirm_archive')) ?>">
                    <?= CsrfMiddleware::field() ?>
                    <button class="btn btn-sm btn-outline-danger"><?= icon('trash') ?></button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if (hasPermission('welfare.manage')): ?>
<div class="modal fade" id="addHealth" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" action="/welfare/health" class="modal-content">
      <?= CsrfMiddleware::field() ?>
      <div class="modal-header">
        <h5 class="modal-title"><?= e(__('welfare.add_health')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-12">
          <label class="form-label"><?= e(__('students.full_name')) ?> (ID)</label>
          <input type="number" name="student_id" class="form-control" placeholder="Student ID" required min="1">
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= e(__('welfare.record_type')) ?></label>
          <select name="record_type" class="form-select" required>
            <?php foreach (['allergy', 'medication', 'clinic_visit', 'condition'] as $t): ?>
              <option value="<?= $t ?>"><?= e(__('welfare.health_' . $t)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= e(__('welfare.date_logged')) ?></label>
          <input type="date" name="date_logged" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-12">
          <label class="form-label"><?= e(__('welfare.title')) ?></label>
          <input name="title" class="form-control" required>
        </div>
        <div class="col-12">
          <label class="form-label"><?= e(__('welfare.details')) ?></label>
          <textarea name="details" class="form-control" rows="4" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

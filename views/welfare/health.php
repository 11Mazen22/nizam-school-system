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
      <div class="n-stat-mini">
        <span class="n-stat-mini-value"><?= count($records) ?></span>
        <span class="n-stat-mini-label"><?= e(__('welfare.total_records')) ?></span>
      </div>
      <a href="/welfare/discipline" class="btn btn-outline-light"><?= icon('alert-triangle') ?> <?= e(__('welfare.discipline_title')) ?></a>
      <?php if (hasPermission('welfare.manage')): ?>
        <button class="btn btn-light" type="button" data-bs-toggle="modal" data-bs-target="#addHealthModal">
          <?= icon('plus') ?> <?= e(__('welfare.add_health')) ?>
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Filter -->
<form method="get" class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex gap-3 align-items-end flex-wrap">
    <div style="min-width: 200px;">
      <label class="form-label fw-semibold small"><?= e(__('welfare.record_type')) ?></label>
      <select name="type" class="form-select">
        <option value=""><?= e(__('welfare.all_types')) ?></option>
        <?php foreach (['allergy', 'medication', 'clinic_visit', 'condition'] as $t): ?>
          <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(__('welfare.health_' . $t)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('filter') ?> <?= e(__('reports.apply_filters')) ?></button>
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
            <?php if (hasPermission('welfare.manage')): ?><th class="text-end"><?= e(__('common.actions')) ?></th><?php endif; ?>
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
              <td class="text-muted small" style="max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= e($row['details']) ?>">
                <?= e($row['details']) ?>
              </td>
              <td class="text-muted small"><?= e($row['logger_name'] ?? '—') ?></td>
              <?php if (hasPermission('welfare.manage')): ?>
                <td class="text-end">
                  <form method="post" action="/welfare/health/<?= (int)$row['id'] ?>/delete" class="d-inline"
                        onsubmit="return confirm('<?= e(__('common.confirm_archive')) ?>')">
                    <?= CsrfMiddleware::field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= icon('trash') ?></button>
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

<!-- Add Health Record Modal -->
<?php if (hasPermission('welfare.manage')): ?>
<div class="modal fade" id="addHealthModal" tabindex="-1" aria-labelledby="addHealthModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="/welfare/health">
        <?= CsrfMiddleware::field() ?>
        <div class="modal-header">
          <h5 class="modal-title" id="addHealthModalLabel"><?= e(__('welfare.add_health')) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label for="healthStudentId" class="form-label"><?= e(__('students.full_name')) ?> (ID) <span class="text-danger">*</span></label>
              <input type="number" id="healthStudentId" name="student_id" class="form-control" placeholder="<?= e(__('students.code')) ?>" required min="1">
              <div class="form-text"><?= e(__('common.enter')) ?> <?= e(__('students.code')) ?></div>
            </div>
            <div class="col-md-6">
              <label for="healthType" class="form-label"><?= e(__('welfare.record_type')) ?> <span class="text-danger">*</span></label>
              <select id="healthType" name="record_type" class="form-select" required>
                <option value="allergy"><?= e(__('welfare.health_allergy')) ?></option>
                <option value="medication"><?= e(__('welfare.health_medication')) ?></option>
                <option value="clinic_visit"><?= e(__('welfare.health_clinic_visit')) ?></option>
                <option value="condition"><?= e(__('welfare.health_condition')) ?></option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="healthDate" class="form-label"><?= e(__('welfare.date_logged')) ?> <span class="text-danger">*</span></label>
              <input type="date" id="healthDate" name="date_logged" class="form-control" value="<?= date('Y-m-d') ?>" required max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12">
              <label for="healthTitle" class="form-label"><?= e(__('welfare.title')) ?> <span class="text-danger">*</span></label>
              <input type="text" id="healthTitle" name="title" class="form-control" required maxlength="200">
              <div class="form-text"><?= e(__('common.example')) ?>: "Peanut Allergy", "Daily Insulin", "Headache visit"</div>
            </div>
            <div class="col-12">
              <label for="healthDetails" class="form-label"><?= e(__('welfare.details')) ?> <span class="text-danger">*</span></label>
              <textarea id="healthDetails" name="details" class="form-control" rows="5" required maxlength="2000"></textarea>
              <div class="form-text"><?= e(__('common.detailed')) ?> <?= e(__('welfare.description')) ?></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('app.cancel')) ?></button>
          <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= e(__('common.save')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

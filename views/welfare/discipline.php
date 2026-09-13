<?php
/**
 * @var array       $records
 * @var string|null $type
 * @var string|null $severity
 */
$pageTitle = __('welfare.discipline_title');
$activeNav = 'welfare';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper"><?= icon('alert-triangle', 'n-grades-icon') ?></div>
        <?= e(__('welfare.discipline_title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('welfare.discipline_subtitle')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <div class="n-stat-mini">
        <span class="n-stat-mini-value"><?= count($records) ?></span>
        <span class="n-stat-mini-label"><?= e(__('welfare.total_records')) ?></span>
      </div>
      <a href="/welfare/health" class="btn btn-outline-light"><?= icon('inbox') ?> <?= e(__('welfare.health_title')) ?></a>
      <?php if (hasPermission('welfare.manage')): ?>
        <button class="btn btn-light" type="button" data-bs-toggle="modal" data-bs-target="#addDisciplineModal">
          <?= icon('plus') ?> <?= e(__('welfare.add_record')) ?>
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Filters -->
<form method="get" class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex gap-3 flex-wrap align-items-end">
    <div style="min-width: 180px;">
      <label class="form-label fw-semibold small"><?= e(__('welfare.type')) ?></label>
      <select name="type" class="form-select">
        <option value=""><?= e(__('welfare.all_types')) ?></option>
        <option value="infraction" <?= $type === 'infraction' ? 'selected' : '' ?>><?= e(__('welfare.type_infraction')) ?></option>
        <option value="reward"     <?= $type === 'reward'     ? 'selected' : '' ?>><?= e(__('welfare.type_reward')) ?></option>
      </select>
    </div>
    <div style="min-width: 180px;">
      <label class="form-label fw-semibold small"><?= e(__('welfare.severity')) ?></label>
      <select name="severity" class="form-select">
        <option value=""><?= e(__('welfare.all_severities')) ?></option>
        <option value="low"    <?= $severity === 'low'    ? 'selected' : '' ?>><?= e(__('welfare.severity_low')) ?></option>
        <option value="medium" <?= $severity === 'medium' ? 'selected' : '' ?>><?= e(__('welfare.severity_medium')) ?></option>
        <option value="high"   <?= $severity === 'high'   ? 'selected' : '' ?>><?= e(__('welfare.severity_high')) ?></option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('filter') ?> <?= e(__('reports.apply_filters')) ?></button>
  </div>
</form>

<?php if (empty($records)): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('alert-triangle', 'n-icon-xl') ?></div>
    <h3 class="n-empty-title"><?= e(__('welfare.no_records')) ?></h3>
  </div>
<?php else: ?>
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= e(__('welfare.incident_date')) ?></th>
            <th><?= e(__('students.full_name')) ?></th>
            <th><?= e(__('welfare.type')) ?></th>
            <th><?= e(__('welfare.severity')) ?></th>
            <th><?= e(__('welfare.title')) ?></th>
            <th><?= e(__('welfare.action_taken')) ?></th>
            <th><?= e(__('welfare.reported_by')) ?></th>
            <?php if (hasPermission('welfare.manage')): ?><th class="text-end"><?= e(__('common.actions')) ?></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $row): ?>
            <?php
              $sevClass = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger'][$row['severity']] ?? 'secondary';
              $typeClass = $row['type'] === 'reward' ? 'success' : 'danger';
            ?>
            <tr>
              <td class="text-nowrap"><?= e($row['incident_date']) ?></td>
              <td><a href="/students/<?= (int)$row['student_id'] ?>" class="fw-semibold text-decoration-none"><?= e($row['full_name']) ?></a></td>
              <td><span class="badge bg-<?= $typeClass ?>"><?= e(__('welfare.type_' . $row['type'])) ?></span></td>
              <td><span class="badge bg-<?= $sevClass ?>"><?= e(__('welfare.severity_' . $row['severity'])) ?></span></td>
              <td><?= e($row['title']) ?></td>
              <td class="text-muted small"><?= e($row['action_taken'] ?? '—') ?></td>
              <td class="text-muted small"><?= e($row['reporter_name'] ?? '—') ?></td>
              <?php if (hasPermission('welfare.manage')): ?>
                <td class="text-end">
                  <form method="post" action="/welfare/discipline/<?= (int)$row['id'] ?>/delete" class="d-inline"
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

<!-- Add Discipline Modal -->
<?php if (hasPermission('welfare.manage')): ?>
<div class="modal fade" id="addDisciplineModal" tabindex="-1" aria-labelledby="addDisciplineModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="/welfare/discipline">
        <?= CsrfMiddleware::field() ?>
        <div class="modal-header">
          <h5 class="modal-title" id="addDisciplineModalLabel"><?= e(__('welfare.add_discipline')) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.close')) ?>"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label for="disciplineStudentId" class="form-label"><?= e(__('students.full_name')) ?> (ID) <span class="text-danger">*</span></label>
              <input type="number" id="disciplineStudentId" name="student_id" class="form-control" placeholder="<?= e(__('students.code')) ?>" required min="1">
              <div class="form-text"><?= e(__('welfare.student_id_hint')) ?></div>
            </div>
            <div class="col-md-4">
              <label for="disciplineDate" class="form-label"><?= e(__('welfare.incident_date')) ?> <span class="text-danger">*</span></label>
              <input type="date" id="disciplineDate" name="incident_date" class="form-control" value="<?= date('Y-m-d') ?>" required max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
              <label for="disciplineType" class="form-label"><?= e(__('welfare.type')) ?> <span class="text-danger">*</span></label>
              <select id="disciplineType" name="type" class="form-select" required>
                <option value="infraction"><?= e(__('welfare.type_infraction')) ?></option>
                <option value="reward"><?= e(__('welfare.type_reward')) ?></option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="disciplineSeverity" class="form-label"><?= e(__('welfare.severity')) ?> <span class="text-danger">*</span></label>
              <select id="disciplineSeverity" name="severity" class="form-select" required>
                <option value="low"><?= e(__('welfare.severity_low')) ?></option>
                <option value="medium" selected><?= e(__('welfare.severity_medium')) ?></option>
                <option value="high"><?= e(__('welfare.severity_high')) ?></option>
              </select>
            </div>
            <div class="col-12">
              <label for="disciplineTitle" class="form-label"><?= e(__('welfare.title')) ?> <span class="text-danger">*</span></label>
              <input type="text" id="disciplineTitle" name="title" class="form-control" required maxlength="200">
            </div>
            <div class="col-12">
              <label for="disciplineDescription" class="form-label"><?= e(__('welfare.description')) ?> <span class="text-danger">*</span></label>
              <textarea id="disciplineDescription" name="description" class="form-control" rows="4" required maxlength="1000"></textarea>
            </div>
            <div class="col-12">
              <label for="disciplineAction" class="form-label"><?= e(__('welfare.action_taken')) ?></label>
              <input type="text" id="disciplineAction" name="action_taken" class="form-control" maxlength="500">
              <div class="form-text"><?= e(__('common.optional')) ?></div>
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

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
      <a href="/welfare/health" class="btn btn-outline-light ms-2"><?= icon('inbox') ?> <?= e(__('welfare.health_title')) ?></a>
      <?php if (hasPermission('welfare.manage')): ?>
        <button class="btn btn-light ms-2" data-bs-toggle="modal" data-bs-target="#addDiscipline">
          <?= icon('plus') ?> <?= e(__('welfare.add_record')) ?>
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Filters -->
<form method="get" class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex gap-3 flex-wrap align-items-end">
    <div>
      <label class="form-label fw-semibold"><?= e(__('welfare.type')) ?></label>
      <select name="type" class="form-select form-select-sm">
        <option value=""><?= e(__('welfare.all_types')) ?></option>
        <option value="infraction" <?= $type === 'infraction' ? 'selected' : '' ?>><?= e(__('welfare.type_infraction')) ?></option>
        <option value="reward"     <?= $type === 'reward'     ? 'selected' : '' ?>><?= e(__('welfare.type_reward')) ?></option>
      </select>
    </div>
    <div>
      <label class="form-label fw-semibold"><?= e(__('welfare.severity')) ?></label>
      <select name="severity" class="form-select form-select-sm">
        <option value=""><?= e(__('welfare.all_severities')) ?></option>
        <option value="low"    <?= $severity === 'low'    ? 'selected' : '' ?>><?= e(__('welfare.severity_low')) ?></option>
        <option value="medium" <?= $severity === 'medium' ? 'selected' : '' ?>><?= e(__('welfare.severity_medium')) ?></option>
        <option value="high"   <?= $severity === 'high'   ? 'selected' : '' ?>><?= e(__('welfare.severity_high')) ?></option>
      </select>
    </div>
    <button class="btn btn-primary btn-sm"><?= icon('filter') ?> <?= e(__('reports.apply_filters')) ?></button>
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
            <?php if (hasPermission('welfare.manage')): ?><th></th><?php endif; ?>
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
                <td>
                  <form method="post" action="/welfare/discipline/<?= (int)$row['id'] ?>/delete"
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

<!-- Add modal -->
<?php if (hasPermission('welfare.manage')): ?>
<div class="modal fade" id="addDiscipline" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" action="/welfare/discipline" class="modal-content">
      <?= CsrfMiddleware::field() ?>
      <div class="modal-header">
        <h5 class="modal-title"><?= e(__('welfare.add_discipline')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-12">
          <label class="form-label"><?= e(__('students.full_name')) ?> (ID)</label>
          <input type="number" name="student_id" class="form-control" placeholder="Student ID" required min="1">
        </div>
        <div class="col-md-4">
          <label class="form-label"><?= e(__('welfare.incident_date')) ?></label>
          <input type="date" name="incident_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label"><?= e(__('welfare.type')) ?></label>
          <select name="type" class="form-select" required>
            <option value="infraction"><?= e(__('welfare.type_infraction')) ?></option>
            <option value="reward"><?= e(__('welfare.type_reward')) ?></option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label"><?= e(__('welfare.severity')) ?></label>
          <select name="severity" class="form-select" required>
            <option value="low"><?= e(__('welfare.severity_low')) ?></option>
            <option value="medium"><?= e(__('welfare.severity_medium')) ?></option>
            <option value="high"><?= e(__('welfare.severity_high')) ?></option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label"><?= e(__('welfare.title')) ?></label>
          <input name="title" class="form-control" required>
        </div>
        <div class="col-12">
          <label class="form-label"><?= e(__('welfare.description')) ?></label>
          <textarea name="description" class="form-control" rows="3" required></textarea>
        </div>
        <div class="col-12">
          <label class="form-label"><?= e(__('welfare.action_taken')) ?> <span class="text-muted small">(<?= e(__('common.optional')) ?>)</span></label>
          <input name="action_taken" class="form-control">
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

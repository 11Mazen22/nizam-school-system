<?php
/** @var array $student @var array $history @var ?array $currentEnrollment @var array $availableClasses */
$pageTitle = $student['full_name'];
$activeNav = 'students';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$canReassign = $currentEnrollment !== null && $currentEnrollment['status'] === 'active' && !empty($availableClasses);
$canUndo = $currentEnrollment !== null && $currentEnrollment['status'] === 'active' && $currentEnrollment['previous_enrollment_id'] !== null;
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?php if (!empty($student['photo_path'])): ?>
        <img src="/students/<?= (int) $student['id'] ?>/photo" alt="" style="width:40px; height:40px; object-fit:cover;" class="rounded-circle border">
      <?php else: ?>
        <span class="n-avatar" style="width:40px; height:40px;"><?= e(mb_substr($student['full_name'], 0, 1)) ?></span>
      <?php endif; ?>
      <?= e($student['full_name']) ?>
    </h1>
    <p class="mono mb-0"><?= e($student['student_code']) ?></p>
  </div>
  <div class="n-page-head-actions">
    <div class="btn-group me-2">
      <a href="/attendance?q=<?= urlencode($student['student_code']) ?>" class="btn btn-outline-secondary d-flex align-items-center gap-2">
        <?= icon('calendar') ?>
        <span class="d-none d-md-inline"><?= e(__('attendance.title') ?? 'Attendance') ?></span>
      </a>
      <a href="/welfare?q=<?= urlencode($student['student_code']) ?>" class="btn btn-outline-secondary d-flex align-items-center gap-2">
        <?= icon('shield') ?>
        <span class="d-none d-md-inline"><?= e(__('welfare.title') ?? 'Welfare') ?></span>
      </a>
    </div>
    
    <a href="/students/<?= (int) $student['id'] ?>/edit" class="btn btn-outline-secondary d-flex align-items-center gap-2">
      <?= icon('edit') ?>
      <span><?= e(__('common.edit')) ?></span>
    </a>
    <?php if ($student['status'] === 'active'): ?>
      <form method="post" action="/students/<?= (int) $student['id'] ?>/archive" data-confirm="<?= e(__('students.confirm_archive')) ?>" class="d-inline">
        <?= CsrfMiddleware::field() ?>
        <button type="submit" class="btn btn-outline-danger d-flex align-items-center gap-2">
          <?= icon('archive') ?>
          <span><?= e(__('common.archive')) ?></span>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex align-items-center gap-2">
    <?= icon('users') ?>
    <span><?= e(__('students.full_name')) ?></span>
  </div>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3"><?= e(__('students.full_name')) ?></dt><dd class="col-sm-9"><?= e($student['full_name']) ?></dd>
      <dt class="col-sm-3"><?= e(__('students.gender')) ?></dt><dd class="col-sm-9"><?= e(__('students.gender_' . $student['gender'])) ?></dd>
      <dt class="col-sm-3"><?= e(__('students.date_of_birth')) ?></dt><dd class="col-sm-9"><?= e($student['date_of_birth']) ?></dd>
      <dt class="col-sm-3"><?= e(__('students.religion')) ?></dt><dd class="col-sm-9"><?= e(__('students.religion_' . $student['religion'])) ?></dd>
      <dt class="col-sm-3"><?= e(__('students.phone')) ?></dt><dd class="col-sm-9"><?= e($student['phone'] ?? '—') ?></dd>
      <dt class="col-sm-3"><?= e(__('students.guardian_phone')) ?></dt><dd class="col-sm-9"><?= e($student['guardian_phone'] ?? '—') ?></dd>
      <dt class="col-sm-3"><?= e(__('students.address')) ?></dt><dd class="col-sm-9"><?= e($student['address'] ?? '—') ?></dd>
      <?php if (!empty($student['notes'])): ?>
        <dt class="col-sm-3"><?= e(__('students.notes')) ?></dt><dd class="col-sm-9"><?= nl2br(e($student['notes'])) ?></dd>
      <?php endif; ?>
    </dl>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="d-flex align-items-center gap-2">
      <?= icon('chalkboard') ?>
      <?= e(__('students.current_enrollment')) ?>
    </span>
    <div class="d-flex gap-2">
      <?php if ($canReassign): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#reassignModal">
          <?= icon('edit', 'n-icon-sm') ?>
          <span><?= e(__('students.reassign_class')) ?></span>
        </button>
      <?php endif; ?>
      <?php if ($canUndo): ?>
        <form method="post" action="/students/<?= (int) $currentEnrollment['id'] ?>/undo-promotion" data-confirm="<?= e(__('students.confirm_undo_promotion')) ?>">
          <?= CsrfMiddleware::field() ?>
          <button type="submit" class="btn btn-sm btn-outline-warning d-flex align-items-center gap-1">
            <?= icon('unarchive', 'n-icon-sm') ?>
            <span><?= e(__('students.undo_promotion')) ?></span>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body">
    <?php if ($currentEnrollment === null): ?>
      <div class="n-empty py-3">
        <div class="n-empty-icon"><?= icon('inbox') ?></div>
        <p class="mb-0"><?= e(__('students.no_current_enrollment')) ?></p>
      </div>
    <?php else: ?>
      <?php foreach ($history as $row): ?>
        <?php if ((int) $row['id'] === (int) $currentEnrollment['id']): ?>
          <p class="mb-0">
            <?= e($row['year_label']) ?> —
            <?= e(currentLocale() === 'ar' ? $row['grade_name_ar'] : $row['grade_name_en']) ?>
            <?= $row['class_name'] !== null ? ' - ' . e($row['class_name']) : '' ?>
          </p>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center gap-2">
    <?= icon('clock') ?>
    <span><?= e(__('students.enrollment_history')) ?></span>
  </div>
  <?php if (empty($history)): ?>
    <div class="card-body">
      <div class="n-empty py-3">
        <div class="n-empty-icon"><?= icon('inbox') ?></div>
        <p class="mb-0"><?= e(__('common.no_results')) ?></p>
      </div>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th><?= e(__('academic_years.title')) ?></th>
            <th><?= e(__('students.grade')) ?></th>
            <th><?= e(__('students.class')) ?></th>
            <th><?= e(__('academic_years.status')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $row): ?>
            <tr>
              <td><?= e($row['year_label']) ?></td>
              <td><?= e(currentLocale() === 'ar' ? $row['grade_name_ar'] : $row['grade_name_en']) ?></td>
              <td><?= e($row['class_name'] ?? '—') ?></td>
              <td><?= e(__('students.enrollment_status_' . $row['status'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($canReassign): ?>
  <div class="modal fade" id="reassignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post" action="/students/<?= (int) $student['id'] ?>/reassign-class">
          <?= CsrfMiddleware::field() ?>
          <div class="modal-header"><h2 class="h6 m-0"><?= e(__('students.reassign_class')) ?></h2></div>
          <div class="modal-body">
            <label class="form-label" for="reassign_class_id"><?= e(__('students.reassign_to')) ?></label>
            <select class="form-select" id="reassign_class_id" name="class_id" required>
              <?php foreach ($availableClasses as $class): ?>
                <?php if ((int) $class['id'] !== (int) ($currentEnrollment['class_id'] ?? 0)): ?>
                  <option value="<?= (int) $class['id'] ?>"><?= e($class['name']) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.back')) ?></button>
            <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

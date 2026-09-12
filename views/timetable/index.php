<?php
/**
 * @var array  $classes
 * @var int    $classId
 * @var array  $grid       [day][period] => slot row
 * @var array  $dayLabels  [int => string]
 * @var int    $maxPeriods
 * @var array  $subjects
 * @var array  $teachers
 */
$pageTitle = __('timetable.title');
$activeNav = 'timetable';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper"><?= icon('grid', 'n-grades-icon') ?></div>
        <?= e(__('timetable.title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('timetable.subtitle')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <?php if ($classId > 0 && hasPermission('timetable.manage')): ?>
        <form method="post" action="/timetable/clear" data-confirm="<?= e(__('timetable.confirm_clear')) ?>">
          <?= CsrfMiddleware::field() ?>
          <input type="hidden" name="class_id" value="<?= $classId ?>">
          <button class="btn btn-outline-light btn-sm"><?= icon('trash') ?> <?= e(__('timetable.clear')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Class selector -->
<form method="get" class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex gap-3 align-items-end flex-wrap">
    <div class="flex-grow-1" style="min-width:220px;">
      <label class="form-label fw-semibold"><?= e(__('attendance.class')) ?></label>
      <select name="class_id" class="form-select" onchange="this.form.submit()">
        <option value="0"><?= e(__('attendance.choose_class')) ?></option>
        <?php foreach ($classes as $cls): ?>
          <option value="<?= (int)$cls['id'] ?>" <?= (int)$cls['id'] === $classId ? 'selected' : '' ?>>
            <?= e(currentLocale() === 'ar' ? $cls['grade_name_ar'] : $cls['grade_name_en']) ?> — <?= e($cls['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</form>

<?php if ($classId > 0): ?>
<!-- Timetable grid -->
<div class="card border-0 shadow-sm mb-4 overflow-auto">
  <table class="table table-bordered mb-0 text-center" style="min-width:700px;">
    <thead class="table-dark">
      <tr>
        <th style="width:80px;"><?= e(__('timetable.period')) ?></th>
        <?php foreach ($dayLabels as $day => $label): ?>
          <th><?= e($label) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php for ($p = 1; $p <= $maxPeriods; $p++): ?>
        <tr>
          <td class="fw-bold bg-light"><?= $p ?></td>
          <?php foreach ($dayLabels as $day => $label): ?>
            <?php $slot = $grid[$day][$p] ?? null; ?>
            <td class="p-1" style="min-width:120px;">
              <?php if ($slot): ?>
                <div class="rounded p-1" style="background:var(--n-primary-soft);border:1px solid var(--n-border);">
                  <div class="fw-semibold small" style="color:var(--n-primary)">
                    <?= e(currentLocale() === 'ar' ? $slot['subject_ar'] : $slot['subject_en']) ?>
                  </div>
                  <div class="text-muted" style="font-size:.75rem;"><?= e($slot['teacher_name']) ?></div>
                  <?php if (hasPermission('timetable.manage')): ?>
                    <form method="post" action="/timetable/<?= (int)$slot['id'] ?>/delete" class="mt-1">
                      <?= CsrfMiddleware::field() ?>
                      <input type="hidden" name="class_id" value="<?= $classId ?>">
                      <button class="btn btn-link btn-sm text-danger p-0" style="font-size:.7rem;">
                        <?= icon('close') ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php elseif (hasPermission('timetable.manage')): ?>
                <button class="btn btn-sm w-100 h-100" style="border:1px dashed var(--n-border);color:var(--n-text-faint);min-height:56px;"
                        data-bs-toggle="modal" data-bs-target="#addSlotModal"
                        data-day="<?= $day ?>" data-period="<?= $p ?>">
                  <?= icon('plus') ?>
                </button>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>
</div>

<!-- Add Slot Modal -->
<?php if (hasPermission('timetable.manage')): ?>
<div class="modal fade" id="addSlotModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="/timetable" class="modal-content">
      <?= CsrfMiddleware::field() ?>
      <input type="hidden" name="class_id"     value="<?= $classId ?>">
      <input type="hidden" name="day_of_week"  id="slotDay">
      <input type="hidden" name="period_number" id="slotPeriod">
      <div class="modal-header">
        <h5 class="modal-title"><?= e(__('timetable.add_slot')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-12">
          <p class="text-muted mb-2" id="slotInfo"></p>
        </div>
        <div class="col-12">
          <label class="form-label"><?= e(__('exams.subject')) ?></label>
          <select name="subject_id" class="form-select" required>
            <option value="">— <?= e(__('exams.subject')) ?> —</option>
            <?php foreach ($subjects as $sub): ?>
              <option value="<?= (int)$sub['id'] ?>">
                <?= e(currentLocale() === 'ar' ? $sub['name_ar'] : $sub['name_en']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label"><?= e(__('assignments.teacher')) ?></label>
          <select name="teacher_id" class="form-select" required>
            <option value="">— <?= e(__('assignments.teacher')) ?> —</option>
            <?php foreach ($teachers as $tea): ?>
              <option value="<?= (int)$tea['id'] ?>"><?= e($tea['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
        <button type="submit" class="btn btn-primary"><?= e(__('timetable.add_slot')) ?></button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="n-empty-state">
  <div class="n-empty-icon"><?= icon('grid', 'n-icon-xl') ?></div>
  <h3 class="n-empty-title"><?= e(__('attendance.choose_class_prompt')) ?></h3>
  <p class="n-empty-description"><?= e(__('timetable.choose_class_hint')) ?></p>
</div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('addSlotModal');
  if (!modal) return;
  modal.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    const day = btn.dataset.day;
    const period = btn.dataset.period;
    document.getElementById('slotDay').value = day;
    document.getElementById('slotPeriod').value = period;
    const dayLabels = <?= json_encode(array_values(array_map('e', $dayLabels))) ?>;
    document.getElementById('slotInfo').textContent =
      '<?= e(__('timetable.day')) ?>: ' + (dayLabels[day-1] || day) +
      '  |  <?= e(__('timetable.period')) ?>: ' + period;
  });
});
</script>

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
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('timetable.subtitle')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<?php if ($classId > 0): ?>
        <button type="button" class="btn btn-outline-secondary btn-sm no-print" data-print-report>
          <?= icon('printer') ?> <?= e(__('common.print') ?? 'Print') ?>
        </button>
      <?php endif; ?>
      <?php if ($classId > 0 && hasPermission('timetable.manage')): ?>
        <form method="post" action="/timetable/clear" data-confirm="<?= e(__('timetable.confirm_clear')) ?>">
          <?= CsrfMiddleware::field() ?>
          <input type="hidden" name="class_id" value="<?= $classId ?>">
          <button class="btn btn-outline-danger btn-sm"><?= icon('trash') ?> <?= e(__('timetable.clear')) ?></button>
        </form>
      <?php endif; ?>
<?php
use App\Middleware\CsrfMiddleware;
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>



<!-- Class selector -->
<form method="get" class="n-filter-panel no-print">
  <div class="n-filter-row">
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
<div class="card mb-4 overflow-auto n-timetable-scroll">
  <table class="table table-bordered text-center timetable-grid">
    <thead>
      <tr>
        <th class="n-timetable-period-head"><?= e(__('timetable.period')) ?></th>
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
            <td>
              <?php if ($slot): ?>
                <div class="n-timetable-slot">
                  <strong>
                    <?= e(currentLocale() === 'ar' ? $slot['subject_ar'] : $slot['subject_en']) ?>
                  </strong>
                  <small><?= e($slot['teacher_name']) ?></small>
                  <?php if (hasPermission('timetable.manage')): ?>
                    <form method="post" action="/timetable/<?= (int)$slot['id'] ?>/delete" class="mt-1">
                      <?= CsrfMiddleware::field() ?>
                      <input type="hidden" name="class_id" value="<?= $classId ?>">
                      <button class="btn btn-ghost btn-sm text-danger p-0" aria-label="<?= e(__('common.close')) ?>">
                        <?= icon('close') ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php elseif (hasPermission('timetable.manage')): ?>
                <button class="n-timetable-empty"
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
<div class="modal fade" id="addSlotModal" tabindex="-1" aria-labelledby="addSlotModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="/timetable" class="modal-content" data-unsaved-guard data-loading-submit>
      <?= CsrfMiddleware::field() ?>
      <input type="hidden" name="class_id" value="<?= $classId ?>" id="modalClassId">
      <input type="hidden" name="day_of_week" id="slotDay">
      <input type="hidden" name="period_number" id="slotPeriod">
      
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="addSlotModalLabel">
          <?= icon('plus-circle', 'text-primary') ?>
          <?= e(__('timetable.add_slot')) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.cancel')) ?>"></button>
      </div>
      
      <div class="modal-body pt-3">
        <!-- Slot Information Banner -->
        <div class="alert alert-info bg-light border-0 d-flex align-items-center gap-2 mb-4" role="status">
          <?= icon('calendar', 'text-info') ?>
          <span id="slotInfo" class="fw-semibold"></span>
        </div>

        <!-- Subject Selection -->
        <div class="mb-4">
          <label for="slotSubject" class="form-label fw-semibold d-flex align-items-center gap-2">
            <?= icon('book', 'text-muted') ?>
            <?= e(__('timetable.subject')) ?>
            <span class="text-danger">*</span>
          </label>
          <select name="subject_id" id="slotSubject" class="form-select form-select-lg" required>
            <option value="" disabled selected><?= e(__('timetable.select_subject')) ?></option>
            <?php foreach ($subjects as $sub): ?>
              <option value="<?= (int)$sub['id'] ?>">
                <?= e(currentLocale() === 'ar' ? $sub['name_ar'] : $sub['name_en']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Teacher Selection -->
        <div class="mb-3">
          <label for="slotTeacher" class="form-label fw-semibold d-flex align-items-center gap-2">
            <?= icon('user', 'text-muted') ?>
            <?= e(__('timetable.teacher')) ?>
            <span class="text-danger">*</span>
          </label>
          <select name="teacher_id" id="slotTeacher" class="form-select form-select-lg" required>
            <option value="" disabled selected><?= e(__('timetable.select_teacher')) ?></option>
            <?php foreach ($teachers as $tea): ?>
              <option value="<?= (int)$tea['id'] ?>">
                <?= e($tea['full_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="text-muted small mt-3">
          <?= icon('info-circle', 'opacity-75') ?>
          <?= e(__('timetable.subtitle')) ?>
        </div>
      </div>
      
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <?= icon('x') ?>
          <?= e(__('common.cancel')) ?>
        </button>
        <button type="submit" class="btn btn-primary px-4">
          <?= icon('check') ?>
          <?= e(__('timetable.add_slot')) ?>
        </button>
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
  
  const dayLabels = <?= json_encode(array_values(array_map('e', $dayLabels))) ?>;
  const dayLabel = '<?= e(__('timetable.day')) ?>';
  const periodLabel = '<?= e(__('timetable.period')) ?>';
  
  modal.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    const day = btn.dataset.day;
    const period = btn.dataset.period;
    
    // Set hidden fields
    document.getElementById('slotDay').value = day;
    document.getElementById('slotPeriod').value = period;
    
    // Format and display slot information
    const dayName = dayLabels[day - 1] || day;
    document.getElementById('slotInfo').textContent = `${dayLabel}: ${dayName}  |  ${periodLabel}: ${period}`;
    
    // Reset form fields
    document.getElementById('slotSubject').value = '';
    document.getElementById('slotTeacher').value = '';
  });
  
  // Add form validation feedback
  const form = modal.querySelector('form');
  form.addEventListener('submit', function(e) {
    if (!form.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    form.classList.add('was-validated');
  });
});
</script>

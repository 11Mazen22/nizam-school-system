<?php
/**
 * @var array  $classes   all classes in the active year
 * @var int    $classId   currently selected class (0 = none)
 * @var array|null $classRow  selected class row
 * @var string $date      selected date
 * @var array  $students  active students for the class
 * @var array  $existing  existing attendance records keyed by student_id
 * @var int|null $yearId
 */
$pageTitle  = __('attendance.mark_title');
$activeNav  = 'attendance';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper"><?= icon('check-circle', 'n-grades-icon') ?></div>
        <?= e(__('attendance.mark_title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('attendance.mark_subtitle')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <a href="/attendance/report" class="btn btn-outline-light"><?= icon('chart') ?> <?= e(__('attendance.view_report')) ?></a>
    </div>
  </div>
</div>

<!-- Filter bar -->
<form method="get" action="/attendance" class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-wrap gap-3 align-items-end">
    <div class="flex-grow-1" style="min-width:200px;">
      <label class="form-label fw-semibold"><?= e(__('attendance.class')) ?></label>
      <select name="class_id" class="form-select">
        <option value="0"><?= e(__('attendance.choose_class')) ?></option>
        <?php foreach ($classes as $cls): ?>
          <option value="<?= (int) $cls['id'] ?>" <?= (int)$cls['id'] === $classId ? 'selected' : '' ?>>
            <?= e(currentLocale() === 'ar' ? $cls['grade_name_ar'] : $cls['grade_name_en']) ?> — <?= e($cls['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label fw-semibold"><?= e(__('attendance.date')) ?></label>
      <input type="date" name="date" class="form-control" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>">
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('search') ?> <?= e(__('common.search')) ?></button>
  </div>
</form>

<?php if ($classId > 0 && !empty($students)): ?>
<form method="post" action="/attendance">
  <?= CsrfMiddleware::field() ?>
  <input type="hidden" name="class_id" value="<?= (int) $classId ?>">
  <input type="hidden" name="date"     value="<?= e($date) ?>">

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
      <div class="fw-semibold">
        <?= e($classRow ? (currentLocale() === 'ar' ? $classRow['grade_name_ar'] : $classRow['grade_name_en']) . ' — ' . $classRow['name'] : '') ?>
        <span class="text-muted ms-2"><?= e($date) ?></span>
      </div>
      <div class="d-flex gap-2">
        <!-- Quick-mark all buttons -->
        <button type="button" class="btn btn-sm btn-success" onclick="markAll('present')"><?= e(__('attendance.mark_all_present')) ?></button>
        <button type="button" class="btn btn-sm btn-danger"  onclick="markAll('absent')"><?= e(__('attendance.mark_all_absent')) ?></button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th><?= e(__('students.full_name')) ?></th>
            <th><?= e(__('students.code')) ?></th>
            <th><?= e(__('attendance.status')) ?></th>
            <th><?= e(__('common.notes')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $i => $student): ?>
            <?php $rec = $existing[$student['id']] ?? null; ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td class="fw-semibold"><?= e($student['full_name']) ?></td>
              <td class="mono small"><?= e($student['student_code']) ?></td>
              <td style="min-width:220px;">
                <div class="btn-group btn-group-sm attendance-toggle" role="group">
                  <?php foreach (['present','absent','late','excused'] as $st): ?>
                    <?php
                      $currentStatus = $rec ? $rec['status'] : 'present';
                      $colorMap = ['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'excused' => 'info'];
                      $active = $currentStatus === $st ? '' : 'outline-';
                    ?>
                    <input type="radio" class="btn-check" name="statuses[<?= (int)$student['id'] ?>]"
                           id="s<?= (int)$student['id'] ?>_<?= $st ?>"
                           value="<?= $st ?>" <?= $currentStatus === $st ? 'checked' : '' ?>>
                    <label class="btn btn-<?= $active . $colorMap[$st] ?>"
                           for="s<?= (int)$student['id'] ?>_<?= $st ?>">
                      <?= e(__('attendance.status_' . $st)) ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </td>
              <td>
                <input type="text" class="form-control form-control-sm"
                       name="notes[<?= (int)$student['id'] ?>]"
                       value="<?= e($rec ? ($rec['notes'] ?? '') : '') ?>"
                       placeholder="<?= e(__('common.optional')) ?>">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
      <button type="submit" class="btn btn-primary px-4">
        <?= icon('check') ?> <?= e(__('attendance.save_sheet')) ?>
      </button>
    </div>
  </div>
</form>

<?php elseif ($classId > 0 && empty($students)): ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('users', 'n-icon-xl') ?></div>
    <h3 class="n-empty-title"><?= e(__('attendance.no_students')) ?></h3>
  </div>

<?php else: ?>
  <div class="n-empty-state">
    <div class="n-empty-icon"><?= icon('check-circle', 'n-icon-xl') ?></div>
    <h3 class="n-empty-title"><?= e(__('attendance.choose_class_prompt')) ?></h3>
    <p class="n-empty-description"><?= e(__('attendance.choose_class_hint')) ?></p>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
<script>
function markAll(status) {
  document.querySelectorAll('.attendance-toggle input[value="' + status + '"]').forEach(r => r.click());
}
</script>

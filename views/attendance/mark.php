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

// Real, current counts for the header stats -- same default-to-present
// rule the table body below already uses for an unmarked student, so the
// two never disagree.
$statusCounts = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
foreach ($students as $student) {
    $status = $existing[$student['id']]['status'] ?? 'present';
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}
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
      <?php if ($classId > 0 && !empty($students)): ?>
      <div class="n-grades-stats-mini">
        <div class="n-stat-mini">
          <span class="n-stat-mini-value" data-count-up><?= $statusCounts['present'] ?></span>
          <span class="n-stat-mini-label"><?= e(__('attendance.stat_present')) ?></span>
        </div>
        <div class="n-stat-mini">
          <span class="n-stat-mini-value" data-count-up><?= $statusCounts['absent'] ?></span>
          <span class="n-stat-mini-label"><?= e(__('attendance.stat_absent')) ?></span>
        </div>
        <?php if ($statusCounts['late'] > 0): ?>
        <div class="n-stat-mini">
          <span class="n-stat-mini-value" data-count-up><?= $statusCounts['late'] ?></span>
          <span class="n-stat-mini-label"><?= e(__('attendance.stat_late')) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($statusCounts['excused'] > 0): ?>
        <div class="n-stat-mini">
          <span class="n-stat-mini-value" data-count-up><?= $statusCounts['excused'] ?></span>
          <span class="n-stat-mini-label"><?= e(__('attendance.stat_excused')) ?></span>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <a href="/attendance/report" class="btn btn-outline-light d-flex align-items-center gap-2">
        <?= icon('chart') ?>
        <span><?= e(__('attendance.view_report')) ?></span>
      </a>
    </div>
  </div>
</div>

<!-- Filter bar -->
<form method="get" action="/attendance" class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-wrap gap-3 align-items-end">
    <div class="flex-grow-1" style="min-width:200px;">
      <label class="form-label fw-semibold"><?= e(__('attendance.class')) ?></label>
      <select name="class_id" class="form-select" onchange="this.form.submit()">
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
        <button type="button" class="btn btn-sm btn-success d-flex align-items-center gap-1" onclick="markAll('present')">
          <?= icon('check-circle', 'n-icon-sm') ?>
          <span><?= e(__('attendance.mark_all_present')) ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-danger d-flex align-items-center gap-1" onclick="markAll('absent')">
          <?= icon('x-circle', 'n-icon-sm') ?>
          <span><?= e(__('attendance.mark_all_absent')) ?></span>
        </button>
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
  <div class="n-empty-powerful">
    <div class="n-empty-powerful-bg">
      <div class="n-empty-blob n-empty-blob-1"></div>
      <div class="n-empty-blob n-empty-blob-2"></div>
      <div class="n-empty-blob n-empty-blob-3"></div>
    </div>
    <div class="n-empty-powerful-content">
      <div class="n-empty-powerful-icon">
        <?= icon('users', 'n-icon-massive') ?>
      </div>
      <h2 class="n-empty-powerful-title"><?= e(__('attendance.no_students')) ?></h2>
    </div>
  </div>

<?php else: ?>
  <div class="n-empty-powerful">
    <div class="n-empty-powerful-bg">
      <div class="n-empty-blob n-empty-blob-1"></div>
      <div class="n-empty-blob n-empty-blob-2"></div>
      <div class="n-empty-blob n-empty-blob-3"></div>
    </div>
    <div class="n-empty-powerful-content">
      <div class="n-empty-powerful-icon">
        <?= icon('check-circle', 'n-icon-massive') ?>
      </div>
      <h2 class="n-empty-powerful-title"><?= e(__('attendance.choose_class_prompt')) ?></h2>
      <p class="n-empty-powerful-description"><?= e(__('attendance.choose_class_hint')) ?></p>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
<script>
function markAll(status) {
  document.querySelectorAll('.attendance-toggle input[value="' + status + '"]').forEach(r => r.click());
}
</script>

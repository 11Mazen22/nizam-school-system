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
use App\Middleware\CsrfMiddleware;

$pageTitle  = __('attendance.mark_title');
$activeNav  = 'attendance';
$pageScripts = ['/assets/js/attendance.js'];

// Current counts use the same default-to-present rule as the table below.
$statusCounts = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
$canManage = hasPermission('attendance.manage');
foreach ($students as $student) {
    $status = $existing[$student['id']]['status'] ?? 'present';
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}

ob_start(); ?>
<p class="n-hero-sub"><?= e(__('attendance.mark_subtitle')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
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
      <a href="/attendance/report" class="btn btn-outline-secondary d-flex align-items-center gap-2">
        <?= icon('chart') ?>
        <span><?= e(__('attendance.view_report')) ?></span>
      </a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>



<!-- Filter bar -->
<form method="get" action="/attendance" class="n-filter-panel">
  <div class="n-filter-row">
    <div class="flex-grow-1" style="min-width:200px;">
      <label class="form-label fw-semibold"><?= e(__('attendance.class')) ?></label>
      <select name="class_id" class="form-select" data-auto-submit>
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
<?php /* In-page only: this sheet holds unsaved marks, so the field must never
   submit a GET form (a reload would discard typed attendance). It is revealed
   only when scripting can filter the rows in place. */ ?>
<div class="n-filter-panel n-list-search no-print" data-js-only hidden>
  <div class="n-filter-row">
    <div class="n-filter-field n-search-field flex-grow-1">
      <?= icon('search') ?>
      <label class="visually-hidden" for="attendance-search"><?= e(__('common.search')) ?></label>
      <input id="attendance-search" type="search" class="form-control"
             placeholder="<?= e(__('common.search')) ?>"
             autocomplete="off"
             data-table-filter="#attendance-table"
             data-filter-empty="<?= e(__('common.no_results')) ?>">
    </div>
  </div>
</div>
<form method="post" action="/attendance" data-loading-submit>
  <?= CsrfMiddleware::field() ?>
  <input type="hidden" name="class_id" value="<?= (int) $classId ?>">
  <input type="hidden" name="date"     value="<?= e($date) ?>">

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
      <div class="fw-semibold">
        <?= e($classRow ? (currentLocale() === 'ar' ? $classRow['grade_name_ar'] : $classRow['grade_name_en']) . ' — ' . $classRow['name'] : '') ?>
        <span class="text-muted ms-2"><?= e($date) ?></span>
      </div>
      <?php if ($canManage): ?><div class="d-flex gap-2 flex-wrap">
        <!-- Quick-mark all buttons -->
        <button type="button" class="btn btn-sm btn-success d-flex align-items-center gap-1" data-attendance-mark-all="present">
          <?= icon('check-circle', 'n-icon-sm') ?>
          <span><?= e(__('attendance.mark_all_present')) ?></span>
        </button>
        <button type="button" class="btn btn-sm btn-danger d-flex align-items-center gap-1" data-attendance-mark-all="absent">
          <?= icon('x-circle', 'n-icon-sm') ?>
          <span><?= e(__('attendance.mark_all_absent')) ?></span>
        </button>
      </div><?php endif; ?>
    </div>
    <div class="table-responsive">
      <table id="attendance-table" class="table table-hover align-middle mb-0">
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
                            value="<?= $st ?>" <?= $currentStatus === $st ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>>
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
                       placeholder="<?= e(__('common.optional')) ?>" <?= $canManage ? '' : 'disabled' ?>>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($canManage): ?><div class="card-footer d-flex justify-content-end gap-2 n-attendance-save">
      <button type="submit" class="btn btn-primary px-4">
        <?= icon('check') ?> <?= e(__('attendance.save_sheet')) ?>
      </button>
    </div><?php endif; ?>
  </div>
</form>

<?php elseif ($classId > 0 && empty($students)): ?>
  <div class="n-empty-powerful">
    <div class="n-empty-powerful-content">
      <div class="n-empty-powerful-icon">
        <?= icon('users', 'n-icon-massive') ?>
      </div>
      <h2 class="n-empty-powerful-title"><?= e(__('attendance.no_students')) ?></h2>
    </div>
  </div>

<?php else: ?>
  <div class="n-empty-powerful">
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

<?php
/**
 * @var array      $classes
 * @var int        $classId
 * @var array|null $classRow
 * @var string     $from
 * @var string     $to
 * @var array      $summary   per-student counts for chosen class+range
 * @var array      $chronic   school-wide chronic absentees
 */
$pageTitle = __('attendance.report_title');
$activeNav = 'attendance';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-grades-header">
  <div class="n-grades-header-content">
    <div class="n-grades-header-text">
      <h1 class="n-grades-title">
        <div class="n-grades-icon-wrapper"><?= icon('chart', 'n-grades-icon') ?></div>
        <?= e(__('attendance.report_title')) ?>
      </h1>
      <p class="n-grades-subtitle"><?= e(__('attendance.report_subtitle')) ?></p>
    </div>
    <div class="n-grades-header-actions">
      <a href="/attendance" class="btn btn-outline-light"><?= icon('check-circle') ?> <?= e(__('attendance.mark_title')) ?></a>
    </div>
  </div>
</div>

<!-- Filter -->
<form method="get" action="/attendance/report" class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex flex-wrap gap-3 align-items-end">
    <div class="flex-grow-1" style="min-width:200px;">
      <label class="form-label fw-semibold"><?= e(__('attendance.class')) ?></label>
      <select name="class_id" class="form-select">
        <option value="0"><?= e(__('attendance.all_classes')) ?></option>
        <?php foreach ($classes as $cls): ?>
          <option value="<?= (int)$cls['id'] ?>" <?= (int)$cls['id'] === $classId ? 'selected' : '' ?>>
            <?= e(currentLocale() === 'ar' ? $cls['grade_name_ar'] : $cls['grade_name_en']) ?> — <?= e($cls['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label fw-semibold"><?= e(__('attendance.from')) ?></label>
      <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div>
      <label class="form-label fw-semibold"><?= e(__('attendance.to')) ?></label>
      <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('filter') ?> <?= e(__('reports.apply_filters')) ?></button>
  </div>
</form>

<?php if ($classId > 0 && !empty($summary)): ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white py-3 fw-semibold">
    <?= e($classRow ? (currentLocale() === 'ar' ? $classRow['grade_name_ar'] : $classRow['grade_name_en']) . ' — ' . $classRow['name'] : '') ?>
    <span class="text-muted ms-2"><?= e($from) ?> → <?= e($to) ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th><?= e(__('students.full_name')) ?></th>
          <th><?= e(__('students.code')) ?></th>
          <th class="text-success"><?= e(__('attendance.status_present')) ?></th>
          <th class="text-danger"><?= e(__('attendance.status_absent')) ?></th>
          <th class="text-warning"><?= e(__('attendance.status_late')) ?></th>
          <th class="text-info"><?= e(__('attendance.status_excused')) ?></th>
          <th><?= e(__('attendance.rate')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summary as $row): ?>
          <?php
            $total = (int)$row['total_records'];
            $rate  = $total > 0 ? round((int)$row['present_count'] / $total * 100) : 0;
            $rateClass = $rate >= 90 ? 'text-success' : ($rate >= 75 ? 'text-warning' : 'text-danger');
          ?>
          <tr>
            <td class="fw-semibold"><?= e($row['full_name']) ?></td>
            <td class="mono small"><?= e($row['student_code']) ?></td>
            <td class="text-success fw-bold"><?= (int)$row['present_count'] ?></td>
            <td class="text-danger fw-bold"><?= (int)$row['absent_count'] ?></td>
            <td class="text-warning fw-bold"><?= (int)$row['late_count'] ?></td>
            <td class="text-info fw-bold"><?= (int)$row['excused_count'] ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height:6px;">
                  <div class="progress-bar bg-<?= $rate >= 90 ? 'success' : ($rate >= 75 ? 'warning' : 'danger') ?>"
                       style="width:<?= $rate ?>%"></div>
                </div>
                <span class="<?= $rateClass ?> fw-bold small"><?= $rate ?>%</span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($chronic)): ?>
<div class="card border-0 shadow-sm border-start border-danger border-4">
  <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
    <?= icon('alert-triangle', 'text-danger') ?>
    <span class="fw-semibold text-danger"><?= e(__('attendance.chronic_title')) ?></span>
    <span class="badge bg-danger ms-auto"><?= count($chronic) ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th><?= e(__('students.full_name')) ?></th>
          <th><?= e(__('students.code')) ?></th>
          <th><?= e(__('attendance.class')) ?></th>
          <th><?= e(__('attendance.absent_days')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($chronic as $row): ?>
          <tr>
            <td><a href="/students/<?= (int)$row['student_id'] ?>" class="fw-semibold text-decoration-none"><?= e($row['full_name']) ?></a></td>
            <td class="mono small"><?= e($row['student_code']) ?></td>
            <td><?= e(currentLocale() === 'ar' ? $row['grade_name_ar'] : $row['grade_name_en']) ?> — <?= e($row['class_name']) ?></td>
            <td><span class="badge bg-danger fs-6"><?= (int)$row['absent_count'] ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

<?php
/** @var array $guardians @var ?array $guardian @var array $children @var ?array $student @var array $scores @var ?array $attendanceStats */
$pageTitle = __('portal.family_title');
$activeNav = 'portal-family';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('home', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('portal.family_title')) ?>
    </h1>
    <p class="text-muted small mb-0">
      <?= e(__('portal.family_subtitle', ['name' => $guardian['full_name'] ?? 'Guardian'])) ?>
    </p>
  </div>
  <?php if (count($guardians) > 1): ?>
    <div>
      <form method="get" action="/portal/family" class="d-flex gap-2 align-items-center">
        <label class="small text-muted text-nowrap"><?= e(__('guardians.guardian')) ?>:</label>
        <select class="form-select form-select-sm" name="guardian_id" onchange="this.form.submit()">
          <?php foreach ($guardians as $g): ?>
            <option value="<?= (int) $g['id'] ?>" <?= ($guardian['id'] ?? 0) === $g['id'] ? 'selected' : '' ?>>
              <?= e($g['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php if (empty($children)): ?>
  <div class="card">
    <div class="card-body">
      <div class="n-empty py-5">
        <div class="n-empty-icon"><?= icon('users') ?></div>
        <p class="mb-1 fw-semibold"><?= e(__('portal.no_children_linked')) ?></p>
        <p class="text-muted small"><?= e(__('portal.no_children_hint')) ?></p>
      </div>
    </div>
  </div>
<?php else: ?>
  <!-- Children selection tabs -->
  <ul class="nav nav-pills mb-4 gap-2">
    <?php foreach ($children as $c): ?>
      <li class="nav-item">
        <a class="nav-link <?= ($student['id'] ?? 0) === $c['id'] ? 'active' : 'bg-white border' ?>"
           href="/portal/family?guardian_id=<?= (int) ($guardian['id'] ?? 0) ?>&student_id=<?= (int) $c['id'] ?>">
          <?= icon('user', 'me-1') ?>
          <?= e($c['full_name']) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($student !== null): ?>
    <div class="row g-4 mb-4">
      <!-- Attendance Snapshot -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header fw-semibold d-flex align-items-center gap-2">
            <?= icon('check-circle') ?>
            <?= e(__('portal.attendance_summary')) ?>
          </div>
          <div class="card-body">
            <?php if ($attendanceStats !== null && $attendanceStats['total'] > 0):
              $rate = round((($attendanceStats['present'] + $attendanceStats['late']) / $attendanceStats['total']) * 100);
            ?>
              <div class="text-center py-3">
                <div class="display-5 fw-bold text-primary mb-1"><?= (int) $rate ?>%</div>
                <div class="small text-muted"><?= e(__('portal.overall_attendance_rate')) ?></div>
              </div>
              <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between">
                  <span><?= e(__('attendance.status_present')) ?></span>
                  <strong class="text-success"><?= (int) $attendanceStats['present'] ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                  <span><?= e(__('attendance.status_late')) ?></span>
                  <strong class="text-warning"><?= (int) $attendanceStats['late'] ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                  <span><?= e(__('attendance.status_excused')) ?></span>
                  <strong class="text-info"><?= (int) $attendanceStats['excused'] ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                  <span><?= e(__('attendance.status_absent')) ?></span>
                  <strong class="text-danger"><?= (int) $attendanceStats['absent'] ?></strong>
                </li>
              </ul>
            <?php else: ?>
              <div class="n-empty py-4">
                <p class="text-muted small mb-0"><?= e(__('portal.no_attendance_records')) ?></p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Academic Performance Snapshot -->
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header fw-semibold d-flex align-items-center gap-2">
            <?= icon('star') ?>
            <?= e(__('portal.exam_results')) ?>
          </div>
          <div class="card-body p-0">
            <?php if (empty($scores)): ?>
              <div class="n-empty py-5">
                <div class="n-empty-icon"><?= icon('star') ?></div>
                <p class="text-muted small mb-0"><?= e(__('portal.no_exam_scores')) ?></p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th><?= e(__('exams.exam')) ?></th>
                      <th><?= e(__('subjects.subject')) ?></th>
                      <th><?= e(__('exams.score')) ?></th>
                      <th><?= e(__('exams.grade_band')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($scores as $sc): ?>
                      <tr>
                        <td class="fw-semibold">
                          <?= e(currentLocale() === 'ar' ? $sc['exam_name_ar'] : $sc['exam_name_en']) ?>
                        </td>
                        <td>
                          <?= e(currentLocale() === 'ar' ? $sc['subject_name_ar'] : $sc['subject_name_en']) ?>
                        </td>
                        <td dir="ltr" class="font-monospace fw-bold text-primary">
                          <?= (float) $sc['score'] ?> / <?= (float) $sc['max_score'] ?>
                        </td>
                        <td>
                          <?php if (!empty($sc['band_label'])): ?>
                            <span class="badge <?= $sc['is_passing'] === false ? 'text-bg-danger' : 'text-bg-success' ?>"><?= e($sc['band_label']) ?></span>
                          <?php endif; ?>
                          <span class="small text-body-secondary"><?= $sc['percentage'] !== null ? number_format((float) $sc['percentage'], 1) . '%' : '—' ?></span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

<?php
/** @var ?array $teacher @var array $assignments @var array $todayTimetable */
$pageTitle = __('portal.teacher_title');
$suppressPageTitle = true;
$activeNav = 'portal-teacher';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('chalkboard-teacher', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('portal.teacher_title')) ?>
    </h1>
    <p class="text-muted small mb-0">
      <?= e(__('portal.welcome_teacher', ['name' => $teacher['full_name'] ?? 'Teacher'])) ?>
    </p>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold d-flex align-items-center gap-2">
          <?= icon('calendar') ?>
          <?= e(__('portal.today_schedule')) ?>
        </span>
        <span class="badge text-bg-light border"><?= date('l, Y-m-d') ?></span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($todayTimetable)): ?>
          <div class="n-empty py-4">
            <div class="n-empty-icon"><?= icon('calendar') ?></div>
            <p class="text-muted small mb-0"><?= e(__('portal.no_lessons_today')) ?></p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th><?= e(__('timetable.period')) ?></th>
                  <th><?= e(__('classes.class')) ?></th>
                  <th><?= e(__('subjects.subject')) ?></th>
                  <th class="text-end"><?= e(__('common.action')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($todayTimetable as $slot): ?>
                  <tr>
                    <td>
                      <span class="badge text-bg-primary fs-6">
                        <?= e(__('timetable.period_num', ['n' => $slot['period_number']])) ?>
                      </span>
                    </td>
                    <td class="fw-semibold"><?= e($slot['class_name']) ?></td>
                    <td><?= e(currentLocale() === 'ar' ? $slot['subject_name_ar'] : $slot['subject_name_en']) ?></td>
                    <td class="text-end">
                      <a href="/attendance?class_id=<?= (int) $slot['class_id'] ?>" class="btn btn-sm btn-outline-success">
                        <?= icon('check-circle') ?>
                        <span><?= e(__('attendance.mark_title')) ?></span>
                      </a>
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

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <?= icon('chalkboard') ?>
        <?= e(__('portal.assigned_classes')) ?>
      </div>
      <div class="card-body p-0">
        <?php if (empty($assignments)): ?>
          <div class="n-empty py-4">
            <div class="n-empty-icon"><?= icon('chalkboard') ?></div>
            <p class="text-muted small mb-0"><?= e(__('portal.no_assigned_classes')) ?></p>
          </div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($assignments as $a): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold">
                    <?= e(currentLocale() === 'ar' ? $a['grade_name_ar'] : $a['grade_name_en']) ?> - <?= e($a['class_name']) ?>
                  </div>
                  <div class="small text-muted">
                    <?= e(currentLocale() === 'ar' ? $a['subject_name_ar'] : $a['subject_name_en']) ?>
                    (<?= (int) $a['weekly_periods'] ?> <?= e(__('timetable.periods_per_week')) ?>)
                  </div>
                </div>
                <div class="d-flex gap-1">
                  <a href="/attendance?class_id=<?= (int) $a['class_id'] ?>" class="btn btn-sm btn-outline-primary" title="<?= e(__('attendance.title')) ?>">
                    <?= icon('check-circle') ?>
                  </a>
                  <a href="/exams" class="btn btn-sm btn-outline-secondary" title="<?= e(__('exams.title')) ?>">
                    <?= icon('star') ?>
                  </a>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

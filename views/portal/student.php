<?php
/** @var array $students @var ?array $student @var array $scores @var array $timetable */
$pageTitle = __('portal.student_title');
$activeNav = 'portal-student';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('graduation-cap', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('portal.student_title')) ?>
    </h1>
    <p class="text-muted small mb-0">
      <?= e(__('portal.student_subtitle', ['name' => $student['full_name'] ?? 'Student'])) ?>
    </p>
  </div>
</div>

<?php if ($student !== null): ?>
  <div class="card mb-4">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
      <?= icon('star') ?>
      <?= e(__('portal.my_results')) ?>
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
              <?php foreach ($scores as $sc):
                $pct = (float) $sc['max_score'] > 0 ? round(((float) $sc['score'] / (float) $sc['max_score']) * 100) : 0;
              ?>
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
                    <span class="badge <?= $pct >= 85 ? 'text-bg-success' : ($pct >= 50 ? 'text-bg-primary' : 'text-bg-danger') ?>">
                      <?= (int) $pct ?>%
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

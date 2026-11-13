<?php
/** @var array $students @var ?array $student @var array $scores @var array $timetable */
$pageTitle = __('portal.student_title');
$activeNav = 'portal-student';
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('portal.student_subtitle', ['name' => $student['full_name'] ?? 'Student'])) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>



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
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

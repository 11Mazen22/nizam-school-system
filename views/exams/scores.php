<?php
/**
 * @var array  $exam
 * @var array  $classes
 * @var int    $classId
 * @var array  $subjects  subjects for selected class
 * @var array  $sheet     student rows keyed by student_id
 */
$pageTitle = __('exams.enter_scores');
$activeNav = 'exams';
ob_start(); ?>
<p class="n-hero-sub"><?= e(__('exams.max_score')) ?>: <?= e($exam['max_score']) ?> &nbsp;|&nbsp; <?= e(__('exams.term')) ?> <?= (int)$exam['term'] ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/exams" class="btn btn-outline-secondary"><?= icon('chevron-left', 'n-flip-rtl') ?> <?= e(__('common.back')) ?></a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$examLabel = currentLocale() === 'ar' ? $exam['name_ar'] : $exam['name_en'];
?>



<!-- Class picker -->
<form method="get" class="n-filter-panel">
  <div class="n-filter-row">
    <div class="flex-grow-1" style="min-width:200px;">
      <label class="form-label fw-semibold"><?= e(__('attendance.class')) ?></label>
      <select name="class_id" class="form-select" data-auto-submit>
        <option value="0"><?= e(__('attendance.choose_class')) ?></option>
        <?php foreach ($classes as $cls): ?>
          <option value="<?= (int)$cls['id'] ?>" <?= (int)$cls['id'] === $classId ? 'selected' : '' ?>>
            <?= e(currentLocale() === 'ar' ? $cls['grade_name_ar'] : $cls['grade_name_en']) ?> — <?= e($cls['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($subjects !== []): ?>
    <div class="flex-grow-1">
      <label class="form-label" for="score-subject"><?= e(__('exams.subject')) ?></label>
      <select id="score-subject" name="subject_id" class="form-select" data-auto-submit>
        <?php foreach ($subjects as $sub): ?>
        <option value="<?= (int)$sub['id'] ?>" <?= (int)$sub['id'] === $subjectId ? 'selected' : '' ?>><?= e(currentLocale() === 'ar' ? $sub['name_ar'] : $sub['name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
  </div>
</form>

<?php if ($classId > 0 && $subjects === []): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <?= icon('alert-triangle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e(__('exams.no_subjects_for_class')) ?></span>
  </div>
<?php endif; ?>

<?php if ($classId > 0 && !empty($sheet)): ?>
<form method="post" action="/exams/<?= (int)$exam['id'] ?>/scores" data-unsaved-guard data-loading-submit>
  <?= CsrfMiddleware::field() ?>
  <input type="hidden" name="class_id" value="<?= $classId ?>">
  <input type="hidden" name="subject_id" value="<?= $subjectId ?>">

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th><?= e(__('students.full_name')) ?></th>
            <th><?= e(__('students.code')) ?></th>
            <?php if (!empty($subjects)): ?>
              <th><?= e(__('exams.subject')) ?></th>
            <?php endif; ?>
            <th><?= e(__('exams.score')) ?> <small class="text-muted">/ <?= e($exam['max_score']) ?></small></th>
            <th><?= e(__('exams.grade_band')) ?></th>
            <th><?= e(__('common.notes')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($sheet as $studentId => $row): ?>
            <tr>
              <td class="text-muted"><?= $i++ ?></td>
              <td class="fw-semibold"><?= e($row['full_name']) ?></td>
              <td class="mono small"><?= e($row['student_code']) ?></td>
              <?php if (!empty($subjects)): ?>
                <td>
                  <input type="hidden" name="scores[<?= (int)$studentId ?>][subject_id]" value="<?= $subjectId ?>">
                    <?php foreach ($subjects as $sub): ?>
                      <?php if ((int)$sub['id'] === $subjectId): ?>
                        <span class="badge text-bg-info bg-opacity-10 text-info-emphasis border border-info-subtle fs-6 px-3 py-2 rounded-pill">
                          <?= icon('book', 'n-icon-sm me-1') ?>
                          <?= e(currentLocale() === 'ar' ? $sub['name_ar'] : $sub['name_en']) ?>
                        </span>
                      <?php endif; ?>
                    <?php endforeach; ?>
                </td>
              <?php endif; ?>
              <td>
                <input type="number" step="0.01" min="0" max="<?= e($exam['max_score']) ?>"
                       name="scores[<?= (int)$studentId ?>][score]"
                       class="form-control form-control-sm n-score-input"
                       value="<?= isset($row['score']) && $row['score'] !== null ? e($row['score']) : '' ?>"
                       placeholder="—">
              </td>
              <td>
                <?php if (!empty($row['band_label'])): ?>
                  <span class="badge <?= $row['is_passing'] === false ? 'text-bg-danger' : 'text-bg-success' ?>"><?= e($row['band_label']) ?></span>
                  <div class="small text-body-secondary"><?= $row['percentage'] !== null ? number_format((float) $row['percentage'], 1) . '%' : '' ?></div>
                <?php else: ?>
                  <span class="text-body-secondary">—</span>
                <?php endif; ?>
              </td>
              <td>
                <input type="text" name="scores[<?= (int)$studentId ?>][notes]"
                       class="form-control form-control-sm"
                       value="<?= e($row['notes'] ?? '') ?>"
                       placeholder="<?= e(__('common.optional')) ?>">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end py-3">
      <button type="submit" class="btn btn-primary px-5" <?= $subjectId > 0 ? '' : 'disabled' ?>>
        <?= icon('check') ?> <?= e(__('exams.save_scores')) ?>
      </button>
    </div>
  </div>
</form>

<?php elseif ($classId > 0): ?>
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
        <?= icon('edit', 'n-icon-massive') ?>
      </div>
      <h2 class="n-empty-powerful-title"><?= e(__('attendance.choose_class_prompt')) ?></h2>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

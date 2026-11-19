<?php
/**
 * Exam score import -- step 1: choose a class, download its roster-filled
 * workbook, fill the Score column and upload it back for validation.
 *
 * @var array $exam
 * @var array $classes
 * @var int $classId
 * @var array $headers
 */
use App\Middleware\CsrfMiddleware;

$pageTitle = __('exams.import_title');
$activeNav = 'exams';
$examLabel = currentLocale() === 'ar' ? $exam['name_ar'] : $exam['name_en'];
ob_start(); ?>
<p class="n-hero-sub"><?= e($examLabel) ?> · <?= e(__('exams.import_subtitle')) ?></p>
<?php $pageSubtitleHtml = (string) ob_get_clean(); ob_start(); ?>
<a href="/exams/<?= (int) $exam['id'] ?>/scores?class_id=<?= $classId ?>" class="btn btn-outline-secondary"><?= icon('chevron-left', 'n-flip-rtl') ?> <?= e(__('common.back')) ?></a>
<?php
$pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
?>

<form method="get" class="n-filter-panel">
  <div class="n-filter-row">
    <div class="flex-grow-1" style="min-width:220px;">
      <label class="form-label fw-semibold" for="import-class"><?= e(__('attendance.class')) ?></label>
      <select id="import-class" name="class_id" class="form-select" data-auto-submit>
        <option value="0"><?= e(__('attendance.choose_class')) ?></option>
        <?php foreach ($classes as $class): ?>
          <option value="<?= (int) $class['id'] ?>" <?= (int) $class['id'] === $classId ? 'selected' : '' ?>>
            <?= e(currentLocale() === 'ar' ? $class['grade_name_ar'] : $class['grade_name_en']) ?> — <?= e($class['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($classId > 0): ?>
      <div class="align-self-end">
        <a class="btn btn-outline-success" href="/exams/<?= (int) $exam['id'] ?>/scores/import/template?class_id=<?= $classId ?>">
          <?= icon('download') ?> <?= e(__('exams.import_template')) ?>
        </a>
      </div>
    <?php endif; ?>
  </div>
</form>

<div class="n-settings-card mb-4">
  <div class="n-settings-card-head"><h2><?= e(__('exams.import_how_title')) ?></h2></div>
  <div class="n-settings-card-body">
    <ol class="mb-0 ps-3">
      <li><?= e(__('exams.import_step1')) ?></li>
      <li><?= e(__('exams.import_step2')) ?></li>
      <li><?= e(__('exams.import_step3')) ?></li>
    </ol>
    <p class="small text-body-secondary mt-3 mb-0">
      <?= e(__('exams.import_columns')) ?>: <code><?= e(implode(' | ', $headers)) ?></code>
    </p>
  </div>
</div>

<?php if ($classId === 0): ?>
  <div class="n-empty-powerful">
    <div class="n-empty-powerful-content">
      <div class="n-empty-powerful-icon"><?= icon('edit', 'n-icon-massive') ?></div>
      <h2 class="n-empty-powerful-title"><?= e(__('exams.import_class_required')) ?></h2>
    </div>
  </div>
<?php else: ?>
  <div class="n-settings-card">
    <div class="n-settings-card-head"><h2><?= e(__('exams.import_upload')) ?></h2></div>
    <div class="n-settings-card-body">
      <form method="post" enctype="multipart/form-data" action="/exams/<?= (int) $exam['id'] ?>/scores/import/preview" data-loading-submit>
        <?= CsrfMiddleware::field() ?>
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <div class="mb-3">
          <label class="form-label" for="scores-file"><?= e(__('exams.import_file')) ?></label>
          <input class="form-control" type="file" id="scores-file" name="scores_file" accept=".xlsx" required>
          <div class="form-text"><?= e(__('exams.import_file_hint')) ?></div>
        </div>
        <button class="btn btn-primary d-flex align-items-center gap-2">
          <?= icon('eye') ?><span><?= e(__('exams.import_preview')) ?></span>
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

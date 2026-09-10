<?php
/** @var ?string $error @var array $teachers @var array $subjects @var array $classes @var bool $qualificationWarning */
$pageTitle = __('assignments.add');
$activeNav = 'assignments';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<?php if ($error !== null): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($qualificationWarning): ?>
  <div class="alert alert-warning"><?= e(__('assignments.qualification_warning')) ?></div>
<?php endif; ?>

<div class="card" style="max-width: 560px;">
  <div class="card-body">
    <form method="post" action="/assignments">
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label" for="teacher_id"><?= e(__('assignments.teacher')) ?></label>
        <select class="form-select" id="teacher_id" name="teacher_id" required>
          <?php foreach ($teachers as $teacher): ?>
            <option value="<?= (int) $teacher['id'] ?>"><?= e($teacher['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="subject_id"><?= e(__('assignments.subject')) ?></label>
        <select class="form-select" id="subject_id" name="subject_id" required>
          <?php foreach ($subjects as $subject): ?>
            <option value="<?= (int) $subject['id'] ?>"><?= e(currentLocale() === 'ar' ? $subject['name_ar'] : $subject['name_en']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="class_id"><?= e(__('assignments.class')) ?></label>
        <select class="form-select" id="class_id" name="class_id" required>
          <?php foreach ($classes as $class): ?>
            <option value="<?= (int) $class['id'] ?>">
              <?= e(currentLocale() === 'ar' ? $class['grade_name_ar'] : $class['grade_name_en']) ?> - <?= e($class['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="weekly_periods"><?= e(__('assignments.weekly_periods')) ?></label>
        <input type="number" class="form-control" id="weekly_periods" name="weekly_periods" min="1" required>
      </div>
      <button type="submit" class="btn btn-primary w-100"><?= e(__('common.save')) ?></button>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

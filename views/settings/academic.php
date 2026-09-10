<?php
/** @var ?string $error @var int|string $expectedWeeklyCapacity @var string $studentIdPattern @var string $teacherIdPattern */
$pageTitle = __('settings.title');
$activeNav = 'settings';
$activeSettingsTab = 'academic';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($error !== null): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width: 600px;">
  <div class="card-body">
    <form method="post" action="/settings/academic">
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label" for="expected_weekly_capacity"><?= e(__('settings.expected_weekly_capacity')) ?></label>
        <input type="number" class="form-control" id="expected_weekly_capacity" name="expected_weekly_capacity" value="<?= (int) $expectedWeeklyCapacity ?>" min="1" max="60" required>
        <div class="form-text"><?= e(__('settings.expected_weekly_capacity_help')) ?></div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="student_id_pattern"><?= e(__('settings.student_id_pattern')) ?></label>
        <input type="text" class="form-control mono" id="student_id_pattern" name="student_id_pattern" value="<?= e($studentIdPattern) ?>" required>
        <div class="form-text"><?= e(__('settings.pattern_help')) ?></div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="teacher_id_pattern"><?= e(__('settings.teacher_id_pattern')) ?></label>
        <input type="text" class="form-control mono" id="teacher_id_pattern" name="teacher_id_pattern" value="<?= e($teacherIdPattern) ?>" required>
        <div class="form-text"><?= e(__('settings.pattern_help')) ?></div>
      </div>
      <button type="submit" class="btn btn-primary w-100"><?= e(__('common.save')) ?></button>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>

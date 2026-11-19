<?php
/** @var ?string $error @var int|string $expectedWeeklyCapacity @var string $studentIdPattern @var string $teacherIdPattern @var array $rules */
$pageTitle = __('settings.title');
$activeNav = 'settings';
$activeSettingsTab = 'academic';

require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>



<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($error !== null): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2">
    <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<div class="n-settings-card">
  <div class="n-settings-card-head">
    <h2><?= e(__('settings.tab.academic')) ?></h2>
    <p><?= e(__('settings.desc.academic')) ?></p>
  </div>
  <div class="n-settings-card-body">
    <form method="post" action="/settings/academic" data-unsaved-guard data-loading-submit>
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

      <hr>
      <h3 class="h6 mb-3"><?= e(__('settings.attendance_rules_title')) ?></h3>
      <p class="small text-muted"><?= e(__('settings.attendance_rules_help')) ?></p>
      <div class="row">
        <?php
          $ruleFields = [
            'academic.attendance_min_percent' => 'settings.attendance_min_percent',
            'academic.attendance_warning_consecutive' => 'settings.attendance_warning_consecutive',
            'academic.attendance_warning_separate' => 'settings.attendance_warning_separate',
            'academic.attendance_critical_consecutive' => 'settings.attendance_critical_consecutive',
            'academic.attendance_critical_separate' => 'settings.attendance_critical_separate',
            'academic.year_work_completion_percent' => 'settings.year_work_completion_percent',
          ];
        ?>
        <?php foreach ($ruleFields as $key => $label): ?>
          <div class="col-md-4 mb-3">
            <label class="form-label" for="<?= e(str_replace('.', '_', $key)) ?>"><?= e(__($label)) ?></label>
            <input type="number" class="form-control" id="<?= e(str_replace('.', '_', $key)) ?>"
                   name="<?= e(str_replace('.', '_', $key)) ?>"
                   value="<?= (int) ($rules[$key] ?? 0) ?>" min="1" max="365" required>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="n-settings-save">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <?= icon('check') ?>
          <span><?= e(__('common.save')) ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_end.php'; ?>
<?php require dirname(__DIR__) . '/layout/end.php'; ?>

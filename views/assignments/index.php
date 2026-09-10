<?php
/** @var array $assignments @var bool $hasActiveYear */
$pageTitle = __('assignments.title');
$activeNav = 'assignments';
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>

<?php if (!$hasActiveYear): ?>
  <div class="alert alert-warning"><?= e(__('assignments.no_active_year')) ?></div>
<?php else: ?>
  <div class="d-flex justify-content-end mb-3 gap-2">
    <a href="/assignments/workload" class="btn btn-outline-secondary"><?= e(__('assignments.workload_summary')) ?></a>
    <a href="/assignments/create" class="btn btn-primary"><?= e(__('assignments.add')) ?></a>
  </div>

  <?php if (empty($assignments)): ?>
    <div class="alert alert-secondary"><?= e(__('common.no_results')) ?></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle bg-white">
        <thead>
          <tr>
            <th><?= e(__('assignments.teacher')) ?></th>
            <th><?= e(__('assignments.subject')) ?></th>
            <th><?= e(__('assignments.class')) ?></th>
            <th><?= e(__('assignments.weekly_periods')) ?></th>
            <th><?= e(__('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($assignments as $a): ?>
            <tr>
              <td><?= e($a['teacher_name']) ?></td>
              <td><?= e(currentLocale() === 'ar' ? $a['subject_name_ar'] : $a['subject_name_en']) ?></td>
              <td><?= e(currentLocale() === 'ar' ? $a['grade_name_ar'] : $a['grade_name_en']) ?> - <?= e($a['class_name']) ?></td>
              <td class="font-variant-numeric-tabular"><?= (int) $a['weekly_periods'] ?></td>
              <td class="text-nowrap">
                <form method="post" action="/assignments/<?= (int) $a['id'] ?>" class="d-inline-flex gap-1">
                  <?= CsrfMiddleware::field() ?>
                  <input type="number" name="weekly_periods" value="<?= (int) $a['weekly_periods'] ?>" min="1" class="form-control form-control-sm" style="width: 70px;">
                  <button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(__('common.save')) ?></button>
                </form>
                <form method="post" action="/assignments/<?= (int) $a['id'] ?>/archive" class="d-inline" data-confirm="<?= e(__('common.confirm_archive')) ?>">
                  <?= CsrfMiddleware::field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('common.archive')) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
